<?php require __DIR__ . '/../../src/bootstrap.php';
$u = require_role('student');
$msg = ''; $type = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$_POST['code']));
    $c = strlen($code) === 8 ? first('classes', ['join_code' => $code]) : null;
    if (!$c) { $msg = t('join_bad'); $type = 'err'; }
    else {
        $uid = (int)$u['id']; $cid = (int)$c['id'];
        $new = store_mutate('members', function (array &$d) use ($uid, $cid) {
            foreach ($d['rows'] as $m) if ((int)$m['class_id'] === $cid && (int)$m['student_id'] === $uid) return false;
            $id = ++$d['seq']; $d['rows'][$id] = ['id' => $id, 'class_id' => $cid, 'student_id' => $uid, 'status' => 'pending', 'created_at' => now()];
            return true;
        });
        if (!$new) { $msg = t('join_dup'); $type = 'err'; }
        else {
            notify((int)$c['teacher_id'], $u['full_name'] . ' → ' . $c['grade'] . $c['letter'] . ' (' . t('pending') . ')', '/teacher/class.php?id=' . $cid);
            audit('join_request', "class $cid"); $msg = t('join_ok');
        }
    }
}
page_head(t('join_class')); ?>
<form method="post" class="card narrow"><h1><?= e(t('join_class')) ?></h1>
<?php if ($msg) echo '<div class="flash ' . $type . '">' . e($msg) . '</div>'; ?><?= csrf_field() ?>
<label><?= e(t('code')) ?><input name="code" maxlength="12" required autocomplete="off" class="code-input"></label>
<button class="btn"><?= e(t('join')) ?></button></form>
<?php page_foot();
