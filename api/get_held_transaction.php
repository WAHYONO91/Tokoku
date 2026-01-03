<?php
// /tokoapp/api/get_held_transaction.php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../functions.php';
require_login();
require_role(['admin','kasir']);

header('Content-Type: application/json');

$slot = isset($_GET['slot']) ? (int)$_GET['slot'] : 0;
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

$sql = "SELECT id, state_json, created_at, updated_at 
        FROM held_transactions
        WHERE slot = :slot AND " .
       ($user_id ? "user_id = :uid" : "user_id IS NULL") . "
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':slot', $slot, PDO::PARAM_INT);
if ($user_id) $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Slot kosong']);
    exit;
}

echo json_encode([
    'success' => true,
    'slot'    => $slot,
    'id'      => (int)$row['id'],
    'state'   => json_decode($row['state_json'], true),
    'created_at' => $row['created_at'],
    'updated_at' => $row['updated_at'],
]);
