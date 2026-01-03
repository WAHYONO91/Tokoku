<?php
// /tokoapp/api/hold_transaction.php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../functions.php';
require_login();
require_role(['admin','kasir']);

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$slot  = isset($data['slot']) ? (int)$data['slot'] : 0;
$state = $data['state'] ?? null;

if ($slot < 1 || $slot > 3) {
    echo json_encode(['success' => false, 'error' => 'Slot tidak valid']);
    exit;
}
if (empty($state) || !is_array($state)) {
    echo json_encode(['success' => false, 'error' => 'State kosong']);
    exit;
}

// Ambil user_id dari session (silakan sesuaikan dengan sistem login kamu)
$user_id = null;
if (isset($_SESSION['user']['id'])) {
    $user_id = (int)$_SESSION['user']['id'];
} elseif (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
}

$state_json = json_encode($state, JSON_UNESCAPED_UNICODE);

// Cek apakah sudah ada untuk slot ini & user yang sama
$sqlCheck = "SELECT id FROM held_transactions WHERE slot = :slot AND " .
            ($user_id ? "user_id = :uid" : "user_id IS NULL") . " LIMIT 1";

$stmt = $pdo->prepare($sqlCheck);
$stmt->bindValue(':slot', $slot, PDO::PARAM_INT);
if ($user_id) $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    // update
    $sql = "UPDATE held_transactions 
            SET state_json = :state, updated_at = NOW()
            WHERE id = :id";
    $st = $pdo->prepare($sql);
    $st->bindValue(':state', $state_json);
    $st->bindValue(':id', $row['id'], PDO::PARAM_INT);
    $st->execute();
} else {
    // insert baru
    $sql = "INSERT INTO held_transactions (slot, user_id, state_json) 
            VALUES (:slot, :uid, :state)";
    $st = $pdo->prepare($sql);
    $st->bindValue(':slot', $slot, PDO::PARAM_INT);
    if ($user_id) {
        $st->bindValue(':uid', $user_id, PDO::PARAM_INT);
    } else {
        $st->bindValue(':uid', null, PDO::PARAM_NULL);
    }
    $st->bindValue(':state', $state_json);
    $st->execute();
}

echo json_encode(['success' => true]);
