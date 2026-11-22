<?php
// php scripts/create_user.php teacher "Иванова А.Б." teacher@school.kz 'Пароль123'
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../src/bootstrap.php';
[, $role, $name, $login, $pw] = $argv + [null, null, null, null, null];
if (!in_array($role, ['teacher', 'student', 'school_admin'], true) || !$name || !$login || strlen((string)$pw) < 8) exit("Usage: php create_user.php <teacher|student|school_admin> <name> <email> <password(min 8)>\n");
$login = mb_strtolower($login);
$id = store_mutate('users', function (array &$d) use ($role, $name, $login, $pw) {
    foreach ($d['rows'] as $r) if ($r['login'] === $login) return 0;
    $id = ++$d['seq'];
    $d['rows'][$id] = ['id' => $id, 'role' => $role, 'full_name' => $name, 'login' => $login, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'is_active' => 1, 'consent_at' => now(), 'created_at' => now()];
    return $id;
});
echo $id ? "Created $role: $login\n" : "Login already exists\n";
