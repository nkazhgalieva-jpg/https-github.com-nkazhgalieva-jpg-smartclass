<?php require __DIR__ . '/../../src/bootstrap.php';
$u = require_role('student');
$classes = by_id(store_read('classes')); $tasks = by_id(store_read('assignments'));
$mine = array_filter(where('members', ['student_id' => $u['id']]), fn($m) => in_array($m['status'], ['active', 'pending'], true));
$subs = []; foreach (store_read('subs') as $s) $subs[$s['recipient_id']] = $s;
$rows = where('recips', ['student_id' => $u['id']]);
$done = ['graded', 'submitted', 'reviewing', 'resubmitted'];
usort($rows, function ($x, $y) use ($tasks, $done) {
    $dx = in_array($x['status'], $done, true); $dy = in_array($y['status'], $done, true);
    if ($dx !== $dy) return $dx <=> $dy;
    return strcmp($tasks[$x['assignment_id']]['deadline_at'] ?? '9999', $tasks[$y['assignment_id']]['deadline_at'] ?? '9999') ?: $y['id'] <=> $x['id'];
});
page_head(t('assignments')); ?>
<h1><?= e($u['full_name']) ?></h1>
<p><?php foreach ($mine as $m): $c = $classes[$m['class_id']]; ?><span class="badge"><?= (int)$c['grade'] . e($c['letter']) ?> · <?= e($c['subject']) ?><?= $m['status'] === 'pending' ? ' ⏳' : '' ?></span> <?php endforeach; ?></p>
<?php if (!$mine): ?><div class="card"><a class="btn" href="/student/join.php"><?= e(t('join_class')) ?></a></div><?php endif; ?>
<h2><?= e(t('assignments')) ?></h2>
<?php if (!$rows) echo '<p class="muted">' . e(t('empty')) . '</p>'; ?>
<div class="grid"><?php foreach ($rows as $r): $a = $tasks[$r['assignment_id']]; $r['deadline_at'] = $a['deadline_at']; $s = $subs[$r['id']] ?? null; ?>
<a class="card tile" href="/student/assignment.php?id=<?= (int)$r['id'] ?>"><small class="muted"><?= e($classes[$a['class_id']]['subject'] ?? '') ?></small><h3><?= e($a['title']) ?></h3>
<p><?= status_badge($r) ?></p>
<p class="muted"><?= $a['deadline_at'] ? e(t('deadline')) . ': ' . e(date('d.m.Y H:i', strtotime($a['deadline_at']))) : '' ?></p>
<?php if ($s && isset($s['score']) && $r['status'] === 'graded'): ?><p><b><?= e($s['score']) ?> / <?= e($a['max_score']) ?></b></p><?php endif; ?></a>
<?php endforeach; ?></div>
<?php page_foot();
