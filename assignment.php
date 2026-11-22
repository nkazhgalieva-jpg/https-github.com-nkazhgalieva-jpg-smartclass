<?php require __DIR__ . '/../../src/bootstrap.php';
$u = require_role('student');
$rid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$r = row('recips', $rid);
// IDOR: запись должна принадлежать именно этому ученику
if (!$r || (int)$r['student_id'] !== (int)$u['id']) { http_response_code(404); exit(e(t('denied'))); }
$a = row('assignments', (int)$r['assignment_id']); $c = row('classes', (int)$a['class_id']);
$r['deadline_at'] = $a['deadline_at'];

function upload_ok(array $f, array &$out): bool {
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > cfg('max_file_bytes') || $f['size'] < 1) return false;
    $map = ['pdf' => ['application/pdf'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset($map[$ext])) return false;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, $map[$ext], true)) return false;
    $out = ['name' => mb_substr(basename($f['name']), 0, 255), 'mime' => $mime, 'size' => (int)$f['size'], 'tmp' => $f['tmp_name']];
    return true;
}
$late = $a['deadline_at'] && strtotime($a['deadline_at']) < time() && !$a['allow_late'] && $r['status'] !== 'returned';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $back = '/student/assignment.php?id=' . $rid;
    if (!in_array($r['status'], ['assigned', 'viewed', 'in_progress', 'returned'], true)) { flash(t('denied'), 'err'); redirect($back); }
    if ($late) { flash(t('late_closed'), 'err'); redirect($back); }
    $text = $a['task_type'] === 'file' ? '' : trim((string)($_POST['answer'] ?? ''));
    $uploads = []; $bad = false;
    if (!empty($_FILES['files']['name'][0]) && $a['task_type'] !== 'text') {
        $n = count($_FILES['files']['name']);
        if ($n > cfg('max_files')) $bad = true;
        for ($i = 0; $i < $n && !$bad; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $f = ['name' => $_FILES['files']['name'][$i], 'tmp_name' => $_FILES['files']['tmp_name'][$i], 'size' => $_FILES['files']['size'][$i], 'error' => $_FILES['files']['error'][$i]];
            $o = []; if (upload_ok($f, $o)) $uploads[] = $o; else $bad = true;
        }
    }
    if ($bad) { flash(t('bad_file'), 'err'); redirect($back); }
    if (mb_strlen($text) > 20000) { flash(t('bad_input'), 'err'); redirect($back); }
    if ($text === '' && !$uploads) { flash(t('need_answer'), 'err'); redirect($back); }

    $saved = []; $files = [];
    try {
        foreach ($uploads as $o) {
            $stored = bin2hex(random_bytes(20)); $dest = cfg('upload_dir') . '/' . $stored;
            if (!move_uploaded_file($o['tmp'], $dest)) throw new RuntimeException('upload failed');
            $saved[] = $dest;
            $files[] = ['stored' => $stored, 'name' => $o['name'], 'mime' => $o['mime'], 'size' => $o['size']];
        }
        $old = first('subs', ['recipient_id' => $rid]);
        $attempt = $old ? (int)$old['attempt'] + 1 : 1;
        $data = ['recipient_id' => $rid, 'answer_text' => $text, 'files' => $files, 'attempt' => $attempt, 'submitted_at' => now(), 'score' => null, 'graded_by' => null, 'graded_at' => null,
                 'prev_files' => array_merge($old['prev_files'] ?? [], $old['files'] ?? [])];
        if ($old) upd('subs', (int)$old['id'], $data); else ins('subs', $data);
        set_status($rid, $r['status'] === 'returned' ? 'resubmitted' : 'submitted', (int)$u['id'], ['submitted_at' => now(), 'graded_at' => null]);
        notify((int)$a['teacher_id'], $u['full_name'] . ': ' . $a['title'], '/teacher/review.php?id=' . $rid);
        audit('submit', "recipient $rid attempt $attempt");
    } catch (Throwable $ex) { foreach ($saved as $p) @unlink($p); throw $ex; }
    flash(t('sent_ok')); redirect($back);
}
if ($r['status'] === 'assigned') { set_status($rid, 'viewed', (int)$u['id'], ['viewed_at' => now()]); $r['status'] = 'viewed'; }
$sub = first('subs', ['recipient_id' => $rid]);
$canSend = in_array($r['status'], ['viewed', 'assigned', 'in_progress', 'returned'], true) && !$late;
page_head($a['title']); ?>
<p><a href="/student/index.php">← <?= e(t('back')) ?></a></p>
<h1><?= e($a['title']) ?></h1>
<p><?= status_badge($r) ?> <span class="muted"><?= e($c['subject']) ?><?= $a['topic'] ? ' · ' . e($a['topic']) : '' ?> · <?= e(t('max_score')) ?>: <?= e($a['max_score']) ?><?= $a['deadline_at'] ? ' · ' . e(t('deadline')) . ': ' . e(date('d.m.Y H:i', strtotime($a['deadline_at']))) : '' ?></span></p>
<div class="card"><p class="pre"><?= e($a['description']) ?></p><p class="muted">AI: <?= e(t('ai_' . $a['ai_mode'])) ?></p></div>
<?php if ($sub && $r['status'] === 'graded'): ?><div class="card ok"><h2><?= e(t('your_score')) ?>: <?= e($sub['score']) ?> / <?= e($a['max_score']) ?></h2>
<?php if (!empty($sub['comment'])): ?><p class="pre"><b><?= e(t('teacher_comment')) ?>:</b> <?= e($sub['comment']) ?></p><?php endif; ?></div><?php endif; ?>
<?php if ($sub && $r['status'] === 'returned' && !empty($sub['comment'])): ?><div class="card warn"><p class="pre"><b><?= e(t('teacher_comment')) ?>:</b> <?= e($sub['comment']) ?></p></div><?php endif; ?>
<?php if ($sub && !$canSend): ?><div class="card"><h2><?= e(t('your_answer')) ?></h2><p class="pre"><?= e($sub['answer_text'] ?? '') ?></p>
<?php foreach (($sub['files'] ?? []) as $i => $f): ?><p><a href="/download.php?rid=<?= $rid ?>&i=<?= $i ?>"><?= e($f['name']) ?></a></p><?php endforeach; ?></div><?php endif; ?>
<?php if ($canSend): ?>
<form method="post" enctype="multipart/form-data" class="card" onsubmit="return confirm(<?= e(json_encode(t('confirm_submit'), JSON_UNESCAPED_UNICODE)) ?>)"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $rid ?>">
<?php if ($a['task_type'] !== 'file'): ?><label><?= e(t('your_answer')) ?><textarea name="answer" rows="8" maxlength="20000"><?= e($sub['answer_text'] ?? '') ?></textarea></label><?php endif; ?>
<?php if ($a['task_type'] !== 'text'): ?><label><?= e(t('files')) ?><input type="file" name="files[]" multiple accept=".pdf,.docx,.jpg,.jpeg,.png,.webp"></label><?php endif; ?>
<button class="btn"><?= e(t('submit')) ?></button></form>
<?php elseif (!$sub): ?><div class="flash err"><?= e(t('late_closed')) ?></div><?php endif; ?>
<?php page_foot();
