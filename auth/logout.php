<?php
require_once __DIR__.'/../config.php';
// Hapus remember me cookie & token DB jika ada
remember_me_clear($pdo);
// Hancurkan session
$_SESSION = [];
session_destroy();
header('Location:/tokoapp/auth/login.php');
exit;