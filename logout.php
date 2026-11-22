<?php require __DIR__ . '/../src/bootstrap.php';
audit('logout'); $_SESSION = []; session_destroy(); redirect('/login.php');
