<?php require __DIR__ . '/../src/bootstrap.php';
if (user()) redirect('/');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $n = count(array_filter(store_read('attempts'), fn($a) => $a['ip'] === $ip && $a['ts'] > time() - 600));
    if ($n >= 10) { $err = t('too_many'); }
    else {
        $u = first('users', ['login' => mb_strtolower(trim((string)$_POST['login']))]);
        if ($u && !empty($u['is_active']) && password_verify((string)$_POST['password'], $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            audit('login');
            redirect('/');
        }
        store_mutate('attempts', function (array &$d) use ($ip) {
            foreach ($d['rows'] as $k => $a) if ($a['ts'] < time() - 3600) unset($d['rows'][$k]);
            $id = ++$d['seq']; $d['rows'][$id] = ['id' => $id, 'ip' => $ip, 'ts' => time()];
        });
        $err = t('bad_login');
    }
}
page_head(t('login')); ?>
<form method="post" class="card narrow"><h1><?= e(t('login')) ?></h1>
<?php if ($err) echo '<div class="flash err">' . e($err) . '</div>'; ?>
<?= csrf_field() ?>
<label><?= e(t('email')) ?><input name="login" required autocomplete="username"></label>
<label><?= e(t('password')) ?><input name="password" type="password" required autocomplete="current-password"></label>
<button class="btn"><?= e(t('login')) ?></button>
<p><a href="/register.php"><?= e(t('register')) ?></a></p></form>
<?php page_foot();
