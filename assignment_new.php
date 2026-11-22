<?php require __DIR__ . '/../../src/bootstrap.php';
$u = require_role('teacher');
$cid = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$c = row('classes', $cid);
if (!$c || (int)$c['teacher_id'] !== (int)$u['id']) { http_response_code(404); exit(e(t('denied'))); }
$users = store_read('users');
$members = array_map(fn($m) => ['id' => (int)$m['student_id'], 'full_name' => $users[$m['student_id']]['full_name'] ?? '?'], where('members', ['class_id' => $cid, 'status' => 'active']));
usort($members, fn($a, $b) => strcmp($a['full_name'], $b['full_name']));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = trim((string)$_POST['title']); $desc = trim((string)$_POST['description']); $topic = trim((string)($_POST['topic'] ?? ''));
    $type = in_array($_POST['task_type'] ?? '', ['text', 'file', 'both'], true) ? $_POST['task_type'] : 'both';
    $ai = in_array($_POST['ai_mode'] ?? '', ['off', 'hints', 'on'], true) ? $_POST['ai_mode'] : 'off';
    $max = (float)($_POST['max_score'] ?? 0);
    $dl = trim((string)($_POST['deadline'] ?? '')); $dlSql = null;
    if ($dl !== '') { $ts = strtotime($dl); if ($ts === false) $title = ''; else $dlSql = date('Y-m-d H:i:s', $ts); }
    $valid = array_column($members, 'id');
    $targets = ($_POST['recipients_mode'] ?? 'all') === 'selected' ? array_values(array_intersect(array_map('intval', (array)($_POST['students'] ?? [])), $valid)) : $valid;
    if ($title === '' || mb_strlen($title) > 200 || $desc === '' || $max <= 0 || $max > 1000) flash(t('bad_input'), 'err');
    elseif (!$members) flash(t('no_students'), 'err');
    elseif (!$targets) flash(t('pick_students'), 'err');
    else {
        $aid = ins('assignments', ['class_id' => $cid, 'teacher_id' => (int)$u['id'], 'title' => $title, 'description' => $desc, 'topic' => mb_substr($topic, 0, 200),
            'task_type' => $type, 'max_score' => $max, 'deadline_at' => $dlSql, 'allow_late' => empty($_POST['allow_late']) ? 0 : 1, 'ai_mode' => $ai]);
        $rows = array_map(fn($sid) => ['assignment_id' => $aid, 'student_id' => $sid, 'status' => 'assigned',
            'history' => [['from' => null, 'to' => 'assigned', 'actor' => (int)$u['id'], 'at' => now()]]], $targets);
        $rids = ins_many('recips', $rows);
        foreach ($targets as $i => $sid) notify($sid, t('new_assignment') . ': ' . $title, '/student/assignment.php?id=' . $rids[$i]);
        audit('assignment_publish', "assignment $aid, recipients " . count($targets));
        flash(t('saved') . ' (' . count($targets) . ')');
        redirect('/teacher/assignment.php?id=' . $aid);
    }
}
page_head(t('new_assignment')); ?>
<p><a href="/teacher/class.php?id=<?= $cid ?>">← <?= e(t('back')) ?></a></p>
<h1><?= e(t('new_assignment')) ?> · <?= (int)$c['grade'] ?> «<?= e($c['letter']) ?>»</h1>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="class_id" value="<?= $cid ?>">
<label><?= e(t('title')) ?><input name="title" maxlength="200" required value="<?= e($_POST['title'] ?? '') ?>"></label>
<label><?= e(t('description')) ?><textarea name="description" rows="6" required><?= e($_POST['description'] ?? '') ?></textarea></label>
<div class="row"><label><?= e(t('topic')) ?><input name="topic" maxlength="200" value="<?= e($_POST['topic'] ?? '') ?>"></label>
<label><?= e(t('task_type')) ?><select name="task_type"><option value="both"><?= e(t('t_both')) ?></option><option value="text"><?= e(t('t_text')) ?></option><option value="file"><?= e(t('t_file')) ?></option></select></label>
<label><?= e(t('max_score')) ?><input name="max_score" type="number" step="0.5" min="1" max="1000" value="10" required></label>
<label><?= e(t('deadline')) ?><input name="deadline" type="datetime-local"></label>
<label><?= e(t('ai_mode')) ?><select name="ai_mode"><option value="off"><?= e(t('ai_off')) ?></option><option value="hints"><?= e(t('ai_hints')) ?></option><option value="on"><?= e(t('ai_on')) ?></option></select></label></div>
<label class="check"><input type="checkbox" name="allow_late" value="1"> <?= e(t('allow_late')) ?></label>
<fieldset><legend><?= e(t('recipients')) ?></legend>
<label class="check"><input type="radio" name="recipients_mode" value="all" checked> <?= e(t('all_class')) ?> (<?= count($members) ?>)</label>
<label class="check"><input type="radio" name="recipients_mode" value="selected"> <?= e(t('selected')) ?></label>
<div class="checks"><?php foreach ($members as $m): ?><label class="check"><input type="checkbox" name="students[]" value="<?= (int)$m['id'] ?>"> <?= e($m['full_name']) ?></label><?php endforeach; ?></div></fieldset>
<button class="btn"><?= e(t('publish')) ?></button></form>
<script>document.querySelectorAll('.checks input').forEach(function(i){i.addEventListener('change',function(){document.querySelector('input[value=selected]').checked=true;});});</script>
<?php page_foot();
