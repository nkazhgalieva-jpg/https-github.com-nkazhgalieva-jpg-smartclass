<?php require __DIR__ . '/../src/bootstrap.php';
$u = user(); if (!$u) redirect('/login.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $uid = (int)$u['id'];
    store_mutate('notes', function (array &$d) use ($uid) { foreach ($d['rows'] as &$n) if ((int)$n['user_id'] === $uid) $n['is_read'] = 1; });
    redirect('/notifications.php');
}
$rows = array_reverse(where('notes', ['user_id' => $u['id']])); $rows = array_slice($rows, 0, 50);
page_head(t('notifications')); ?>
<h1><?= e(t('notifications')) ?></h1>
<form method="post"><?= csrf_field() ?><button class="btn ghost"><?= e(t('mark_read')) ?></button></form>
<?php if (!$rows) echo '<p class="muted">' . e(t('empty')) . '</p>'; ?>
<ul class="list"><?php foreach ($rows as $n): ?>
<li class="<?= $n['is_read'] ? '' : 'unread' ?>"><?php if (!empty($n['link']) && str_starts_with($n['link'], '/')): ?><a href="<?= e($n['link']) ?>"><?= e($n['message']) ?></a><?php else: echo e($n['message']); endif; ?>
<small class="muted"> · <?= e($n['created_at']) ?></small></li><?php endforeach; ?></ul>
<?php page_foot();
