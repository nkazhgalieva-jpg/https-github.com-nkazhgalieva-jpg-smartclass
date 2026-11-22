<?php require __DIR__ . '/../src/bootstrap.php';
$u = user(); if (!$u) { http_response_code(403); exit; }
$r = row('recips', (int)($_GET['rid'] ?? 0)); $a = $r ? row('assignments', (int)$r['assignment_id']) : null;
$s = $r ? first('subs', ['recipient_id' => $r['id']]) : null;
$f = $s['files'][(int)($_GET['i'] ?? -1)] ?? null;
$ok = $f && $a && (($u['role'] === 'student' && (int)$r['student_id'] === (int)$u['id']) || ($u['role'] === 'teacher' && (int)$a['teacher_id'] === (int)$u['id']));
if (!$ok || !preg_match('/^[a-f0-9]{40}$/', $f['stored'])) { http_response_code(404); exit; }
$path = cfg('upload_dir') . '/' . $f['stored'];
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($f['name']));
header('X-Content-Type-Options: nosniff');
readfile($path);
