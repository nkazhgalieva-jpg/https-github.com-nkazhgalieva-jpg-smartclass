<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/../config.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

function cfg(string $k) { global $cfg; return $cfg[$k]; }
// ---- файловое хранилище (JSON) ----
function data_dir(): string { $d = cfg('data_dir'); if (!is_dir($d)) mkdir($d, 0775, true); return $d; }
function store_read(string $n): array {
    if (isset($GLOBALS['__c'][$n])) return $GLOBALS['__c'][$n];
    $p = data_dir() . "/$n.json"; $rows = [];
    if (is_file($p)) { $d = json_decode((string)file_get_contents($p), true); $rows = $d['rows'] ?? []; }
    return $GLOBALS['__c'][$n] = $rows;
}
function store_mutate(string $n, callable $fn) {
    $dir = data_dir(); $l = fopen("$dir/$n.lock", 'c'); flock($l, LOCK_EX);
    try {
        $p = "$dir/$n.json";
        $d = is_file($p) ? (json_decode((string)file_get_contents($p), true) ?: []) : [];
        $d['seq'] = $d['seq'] ?? 0; $d['rows'] = $d['rows'] ?? [];
        $res = $fn($d);
        $tmp = "$dir/$n." . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        rename($tmp, $p);
        $GLOBALS['__c'][$n] = $d['rows'];
        return $res;
    } finally { flock($l, LOCK_UN); fclose($l); }
}
function now(): string { return date('Y-m-d H:i:s'); }
function ins(string $n, array $row): int { return ins_many($n, [$row])[0]; }
function ins_many(string $n, array $rows): array {
    return store_mutate($n, function (array &$d) use ($rows) {
        $ids = [];
        foreach ($rows as $r) { $id = ++$d['seq']; $r['id'] = $id; $r['created_at'] = now(); $d['rows'][$id] = $r; $ids[] = $id; }
        return $ids;
    });
}
function upd(string $n, int $id, array $f): void {
    store_mutate($n, function (array &$d) use ($id, $f) { if (isset($d['rows'][$id])) $d['rows'][$id] = array_merge($d['rows'][$id], $f); });
}
function row(string $n, int $id): ?array { return store_read($n)[$id] ?? null; }
function where(string $n, array $c): array {
    return array_values(array_filter(store_read($n), function ($r) use ($c) {
        foreach ($c as $k => $v) if (!isset($r[$k]) || (string)$r[$k] !== (string)$v) return false;
        return true;
    }));
}
function first(string $n, array $c): ?array { return where($n, $c)[0] ?? null; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }

// ---- i18n ----
function lang(): string {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'kk'], true)) $_SESSION['lang'] = $_GET['lang'];
    return $_SESSION['lang'] ?? 'ru';
}
function t(string $k): string {
    static $d = null; $d ??= require __DIR__ . '/lang.php';
    return $d[lang()][$k] ?? $d['ru'][$k] ?? $k;
}

// ---- CSRF ----
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit(e(t('bad_csrf')));
    }
}

// ---- auth ----
function user(): ?array {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) { $r = row('users', (int)$_SESSION['uid']); if ($r && !empty($r['is_active'])) $u = $r; }
    }
    return $u;
}
function require_role(string $role): array {
    $u = user();
    if (!$u) redirect('/login.php');
    if ($u['role'] !== $role) { http_response_code(403); exit(e(t('denied'))); }
    return $u;
}
function home_for(string $role): string { return $role === 'teacher' ? '/teacher/index.php' : '/student/index.php'; }

// ---- helpers ----
function audit(string $action, string $details = ''): void {
    ins('audit', ['user_id' => $_SESSION['uid'] ?? null, 'action' => $action, 'details' => mb_substr($details, 0, 500), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
}
function notify(int $uid, string $msg, string $link = ''): void {
    ins('notes', ['user_id' => $uid, 'message' => mb_substr($msg, 0, 255), 'link' => $link, 'is_read' => 0]);
}
function set_status(int $rid, string $to, ?int $actor, array $extra = []): void {
    store_mutate('recips', function (array &$d) use ($rid, $to, $actor, $extra) {
        if (!isset($d['rows'][$rid])) return;
        $r = &$d['rows'][$rid];
        $r['history'][] = ['from' => $r['status'] ?? null, 'to' => $to, 'actor' => $actor, 'at' => now()];
        $r['status'] = $to;
        foreach ($extra as $k => $v) $r[$k] = $v;
    });
}
function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function is_overdue(array $r): bool {
    return !empty($r['deadline_at']) && strtotime($r['deadline_at']) < time()
        && in_array($r['status'], ['assigned', 'viewed', 'in_progress', 'returned'], true);
}
function status_badge(array $r): string {
    if (is_overdue($r)) return '<span class="badge b-overdue">' . e(t('overdue')) . '</span>';
    return '<span class="badge b-' . e($r['status']) . '">' . e(t('st_' . $r['status'])) . '</span>';
}
function gen_join_code(): string {
    $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do { $c = ''; for ($i = 0; $i < 8; $i++) $c .= $a[random_int(0, strlen($a) - 1)]; } while (first('classes', ['join_code' => $c]));
    return $c;
}
function by_id(array $rows): array { $o = []; foreach ($rows as $r) $o[$r['id']] = $r; return $o; }

// ---- layout ----
function page_head(string $title): void {
    $u = user();
    $l = lang();
    echo '<!doctype html><html lang="' . $l . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' · SENDE Smart Class</title><link rel="stylesheet" href="/assets/style.css"></head><body class="' . e($u['role'] ?? 'guest') . '">';
    echo '<header class="top"><a class="brand" href="/">SENDE <b>Smart Class</b></a><nav>';
    if ($u) {
        $unread = count(where('notes', ['user_id' => $u['id'], 'is_read' => 0]));
        echo '<a href="' . home_for($u['role']) . '">' . e(t($u['role'] === 'teacher' ? 'my_classes' : 'assignments')) . '</a>';
        if ($u['role'] === 'student') echo '<a href="/student/join.php">' . e(t('join_class')) . '</a>';
        echo '<a href="/notifications.php">' . e(t('notifications')) . ($unread ? ' <span class="dot">' . $unread . '</span>' : '') . '</a>';
        echo '<a href="/logout.php">' . e(t('logout')) . ' (' . e($u['full_name']) . ')</a>';
    }
    $other = $l === 'ru' ? 'kk' : 'ru';
    echo '<a class="lang" href="?lang=' . $other . '">' . ($other === 'kk' ? 'Қаз' : 'Рус') . '</a></nav></header><main>';
    if ($f = flash()) echo '<div class="flash ' . e($f[1]) . '">' . e($f[0]) . '</div>';
}
function page_foot(): void { echo '</main></body></html>'; }
