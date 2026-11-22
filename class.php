<?php require __DIR__ . '/../../src/bootstrap.php';
$u = require_role('teacher');
$cid = (int)($_GET['id'] ?? 0);
$c = row('classes', $cid);
if (!$c || (int)$c['teacher_id'] !== (int)$u['id']) { http_response_code(404); exit(e(t('denied'))); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $m = row('members', (int)($_POST['member_id'] ?? 0)); $act = $_POST['action'] ?? '';
    if ($m && (int)$m['class_id'] === $cid) {
        if ($act === 'approve' && $m['status'] === 'pending') {
            upd('members', (int)$m['id'], ['status' => 'active', 'decided_at' => now()]);
            notify((int)$m['student_id'], t('approve') . ': ' . $c['grade'] . $c['letter'], '/student/index.php');
            audit('member_approve', "class $cid student {$m['student_id']}");
        } elseif ($act === 'reject' && $m['status'] === 'pending') {
            upd('members', (int)$m['id'], ['status' => 'rejected', 'decided_at' => now()]); audit('member_reject', "class $cid");
        } elseif ($act === 'remove' && $m['status'] === 'active') {
            upd('members', (int)$m['id'], ['status' => 'rejected', 'decided_at' => now()]); audit('member_remove', "class $cid");
        }
        flash(t('saved'));
    }
    redirect('/teacher/class.php?id=' . $cid);
}
$users = store_read('users');
$mem = where('members', ['class_id' => $cid]);
$pending = array_filter($mem, fn($m) => $m['status'] === 'pending');
$active = array_filter($mem, fn($m) => $m['status'] === 'active');
$tasks = array_reverse(where('assignments', ['class_id' => $cid]));
$recips = store_read('recips'); $taskById = by_id($tasks); $subs = store_read('subs');
$subByRec = []; foreach ($subs as $s) $subByRec[$s['recipient_id']] = $s;
$stats = [];
foreach ($recips as $r) {
    if (!isset($taskById[$r['assignment_id']])) continue;
    $sid = $r['student_id']; $stats[$sid]['all'] = ($stats[$sid]['all'] ?? 0) + 1;
    if ($r['status'] === 'graded') {
        $stats[$sid]['done'] = ($stats[$sid]['done'] ?? 0) + 1;
        $s = $subByRec[$r['id']] ?? null;
        if ($s && isset($s['score'])) $stats[$sid]['pct'][] = $s['score'] / $taskById[$r['assignment_id']]['max_score'] * 100;
    }
}
page_head($c['grade'] . $c['letter']); ?>
<p><a href="/teacher/index.php">← <?= e(t('back')) ?></a></p>
<h1><?= (int)$c['grade'] ?> «<?= e($c['letter']) ?>» · <?= e($c['subject']) ?></h1>
<p><?= e(t('code')) ?>: <span class="code"><?= e($c['join_code']) ?></span></p>
<?php if ($pending): ?><div class="card"><h2><?= e(t('pending')) ?></h2><ul class="list">
<?php foreach ($pending as $p): ?><li><?= e($users[$p['student_id']]['full_name'] ?? '?') ?>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int)$p['id'] ?>">
<button class="btn sm" name="action" value="approve"><?= e(t('approve')) ?></button>
<button class="btn sm ghost" name="action" value="reject"><?= e(t('reject')) ?></button></form></li><?php endforeach; ?></ul></div><?php endif; ?>
<h2><?= e(t('students')) ?> (<?= count($active) ?>)</h2>
<?php if (!$active) echo '<p class="muted">' . e(t('no_students')) . '</p>'; else: ?>
<div class="tablewrap"><table><tr><th><?= e(t('student')) ?></th><th>ID</th><th><?= e(t('assignments')) ?></th><th>%</th><th></th></tr>
<?php foreach ($active as $m): $sid = $m['student_id']; $st = $stats[$sid] ?? []; $pc = $st['pct'] ?? []; ?>
<tr><td><?= e($users[$sid]['full_name'] ?? '?') ?></td><td><?= (int)$sid ?></td><td><?= (int)($st['done'] ?? 0) ?> / <?= (int)($st['all'] ?? 0) ?></td>
<td><?= $pc ? round(array_sum($pc) / count($pc)) . '%' : '—' ?></td>
<td><form method="post" class="inline" onsubmit="return confirm('<?= e(t('remove')) ?>?')"><?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>"><button class="btn sm ghost" name="action" value="remove"><?= e(t('remove')) ?></button></form></td></tr>
<?php endforeach; ?></table></div><?php endif; ?>
<h2><?= e(t('assignments')) ?> <a class="btn sm" href="/teacher/assignment_new.php?class_id=<?= $cid ?>">+ <?= e(t('new_assignment')) ?></a></h2>
<?php if (!$tasks) echo '<p class="muted">' . e(t('empty')) . '</p>'; ?>
<ul class="list"><?php foreach ($tasks as $a):
  $rr = array_filter($recips, fn($r) => (int)$r['assignment_id'] === (int)$a['id']);
  $w = count(array_filter($rr, fn($r) => in_array($r['status'], ['submitted', 'resubmitted', 'reviewing'], true))); ?>
<li><a href="/teacher/assignment.php?id=<?= (int)$a['id'] ?>"><?= e($a['title']) ?></a>
<small class="muted"> · <?= count($rr) ?> <?= e(mb_strtolower(t('recips'))) ?><?= $w ? ' · ' . $w . ' ' . e(mb_strtolower(t('to_review'))) : '' ?></small></li><?php endforeach; ?></ul>
<?php page_foot();
