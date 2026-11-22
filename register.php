<?php require __DIR__ . '/../src/bootstrap.php';
// Саморегистрация — только ученики. Учителей создаёт администратор: php scripts/create_user.php
if (user()) redirect('/');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string)$_POST['name']); $login = mb_strtolower(trim((string)$_POST['login'])); $pw = (string)$_POST['password'];
    if (mb_strlen($name) < 3 || mb_strlen($name) > 150 || !filter_var($login, FILTER_VALIDATE_EMAIL)) $err = t('bad_input');
    elseif (strlen($pw) < 8) $err = t('short_pw');
    elseif (empty($_POST['consent'])) $err = t('need_consent');
    else {
        // проверка уникальности и вставка — внутри одной блокировки
        $id = store_mutate('users', function (array &$d) use ($name, $login, $pw) {
            foreach ($d['rows'] as $r) if ($r['login'] === $login) return 0;
            $id = ++$d['seq'];
            $d['rows'][$id] = ['id' => $id, 'role' => 'student', 'full_name' => $name, 'login' => $login, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
                'is_active' => 1, 'consent_at' => now(), 'created_at' => now()];
            return $id;
        });
        if (!$id) $err = t('exists');
        else { session_regenerate_id(true); $_SESSION['uid'] = $id; audit('register'); redirect('/student/join.php'); }
    }
}
page_head(t('register')); ?>
<form method="post" class="card narrow"><h1><?= e(t('register')) ?></h1>
<?php if ($err) echo '<div class="flash err">' . e($err) . '</div>'; ?>
<?= csrf_field() ?>
<label><?= e(t('name')) ?><input name="name" required maxlength="150" value="<?= e($_POST['name'] ?? '') ?>"></label>
<label><?= e(t('email')) ?><input name="login" type="email" required value="<?= e($_POST['login'] ?? '') ?>"></label>
<label><?= e(t('password')) ?><input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
<label class="check"><input type="checkbox" name="consent" value="1" required> <?= e(t('consent')) ?></label>
<button class="btn"><?= e(t('register')) ?></button></form>
<?php page_foot();
