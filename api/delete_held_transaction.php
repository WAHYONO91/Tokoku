<?php
// /tokoapp/api/delete_held_transaction.php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../functions.php';
require_login();
require_role(['admin','kasir']);

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$slot = isset($data['slot']) ? (int)$data['slot'] : 0;
if ($slot < 1 || $slot > 3) {
    echo json_encode(['success' => false, 'error' => 'Slot tidak valid']);
    exit;
}

// user_id dari session
$user_id = null;
if (isset($_SESSION['user']['id'])) {
    $user_id = (int)$_SESSION['user']['id'];
} elseif (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
}

$sql = "DELETE FROM held_transactions 
        WHERE slot = :slot AND " .
       ($user_id ? "user_id = :uid" : "user_id IS NULL");

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':slot', $slot, PDO::PARAM_INT);
if ($user_id) $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$stmt->execute();

echo json_encode(['success' => true]);
