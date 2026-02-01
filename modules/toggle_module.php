<?php
require_once __DIR__ . '/../config.php';

if (!isset($pdo)) {
    die('Koneksi database ($pdo) tidak ditemukan');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('ID tidak valid');
}

$stmt = $pdo->prepare("
    UPDATE modules
    SET is_active = IF(is_active = 1, 0, 1)
    WHERE id = :id
");
$stmt->execute(['id' => $id]);

header('Location: module_management.php');
exit;
