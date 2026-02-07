<?php
// /tokoapp/api/list_held_transactions.php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../functions.php';
require_access('DASHBOARD');

header('Content-Type: application/json');

// user_id dari session
$user_id = null;
if (isset($_SESSION['user']['id'])) {
    $user_id = (int)$_SESSION['user']['id'];
} elseif (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
}

$sql = "SELECT slot, state_json FROM held_transactions WHERE ";
if ($user_id) {
    $sql .= "user_id = :uid";
} else {
    $sql .= "user_id IS NULL";
}

$stmt = $pdo->prepare($sql);
if ($user_id) $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$stmt->execute();

$slots = [
    1 => null,
    2 => null,
    3 => null,
];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $s = (int)$row['slot'];
    if ($s >= 1 && $s <= 3) {
        $slots[$s] = json_decode($row['state_json'], true);
    }
}

echo json_encode([
    'success' => true,
    'slots'   => $slots,
]);
