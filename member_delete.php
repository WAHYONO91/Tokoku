<?php
// member_delete.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

// Ambil kode member dari query string
$kode = isset($_GET['kode']) ? trim($_GET['kode']) : '';

if ($kode === '') {
    // Kalau tidak ada kode, balik ke halaman sebelumnya / daftar member
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'member.php';
    header('Location: ' . $redirect);
    exit;
}

try {
    // Hapus member berdasarkan kode
    $stmt = $pdo->prepare('DELETE FROM members WHERE kode = ? LIMIT 1');
    $stmt->execute([$kode]);
} catch (Throwable $e) {
    // Bisa ditambahkan logging jika diperlukan
    // misal: error_log($e->getMessage());
}

// Setelah hapus, kembali ke daftar member
$redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'member.php';
header('Location: ' . $redirect);
exit;
