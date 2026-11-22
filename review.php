<?php require __DIR__ . '/../../src/bootstrap.php';
$u = require_role('teacher');
$rid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$r = row('recips', $rid); $a = $r ? row('assignments', (int)$r['assignment_id']) : null;
$sub = $r ? first('subs', ['recipient_id' => $rid]) : null;
if (!$r || !$a || !$sub || (int)$a['teacher_id'] !== (int)$u['id']) { http_response_code(404); exit(e(t('denied'))); }
$student = row('users', (int)$r['student_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? ''; $comment = trim((string)($_POST['comment'] ?? ''));
    if ($act === 'grade') {
        $raw = str_replace(',', '.', (string)($_POST['score'] ?? ''));
        if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > (float)$a['max_score']) { flash(t('bad_score'), 'err'); redirect('/teacher/review.php?id=' . $rid); }
        $score = round((float)$raw, 2);
        $log = $sub['grade_log'] ?? []; $log[] = ['old' => $sub['score'] ?? null, 'new' => $score, 'actor' => (int)$u['id'], 'at' => now()];
        upd('subs', (int)$sub['id'], ['score' => $score, 'comment' => $comment, 'graded_by' => (int)$u['id'], 'graded_at' => now(), 'grade_log' => $log]);
        set_status($rid, 'graded', (int)$u['id'], ['graded_at' => now()]);
        notify((int)$r['student_id'], t('grade_it') . ': ' . $a['title'], '/student/assignment.php?id=' . $rid);
        audit('grade', "recipient $rid score $score");
    } elseif ($act === 'return' && $r['status'] !== 'returned') {
        upd('subs', (int)$sub['id'], ['comment' => $comment]);
        set_status($rid, 'returned', (int)$u['id']);
        notify((int)$r['student_id'], t('return_it') . ': ' . $a['title'], '/student/assignment.php?id=' . $rid);
        audit('return', "recipient $rid");
    }
    flash(t('saved')); redirect('/teacher/review.php?id=' . $rid);
}
if (in_array($r['status'], ['submitted', 'resubmitted'], true)) { set_status($rid, 'reviewing', (int)$u['id']); $r['status'] = 'reviewing'; }
page_head(t('review')); ?>
<p><a href="/teacher/assignment.php?id=<?= (int)$a['id'] ?>">← <?= e($a['title']) ?></a></p>
<h1><?= e($student['full_name'] ?? '?') ?></h1>
<div class="card"><h2><?= e($a['title']) ?></h2><p class="pre"><?= e($a['description']) ?></p></div>
<div class="card"><h2><?= e(t('your_answer')) ?> <small class="muted">(<?= e(t('attempt')) ?> <?= (int)$sub['attempt'] ?> · <?= e($sub['submitted_at']) ?>)</small></h2>
<p class="pre"><?= !empty($sub['answer_text']) ? e($sub['answer_text']) : '<span class="muted">' . e(t('no_answer')) . '</span>' ?></p>
<?php foreach (($sub['files'] ?? []) as $i => $f): ?><p><a href="/download.php?rid=<?= $rid ?>&i=<?= $i ?>"><?= e(t('download')) ?>: <?= e($f['name']) ?></a></p><?php endforeach; ?></div>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $rid ?>">
<div class="row"><label><?= e(t('score')) ?> (0–<?= e($a['max_score']) ?>)<input name="score" inputmode="decimal" value="<?= e($sub['score'] ?? '') ?>"></label></div>
<label><?= e(t('comment')) ?><textarea name="comment" rows="4"><?= e($sub['comment'] ?? '') ?></textarea></label>
<button class="btn" name="action" value="grade"><?= e(t('grade_it')) ?></button>
<?php if ($r['status'] !== 'returned'): ?><button class="btn ghost" name="action" value="return"><?= e(t('return_it')) ?></button><?php endif; ?></form>
<?php page_foot();
