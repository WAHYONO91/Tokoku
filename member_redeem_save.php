<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin','kasir']);

$kode = $_POST['kode'] ?? '';
$redeem_points = (int)($_POST['redeem_points'] ?? 0);
$description = $_POST['description'] ?? '';
$redeemed_at = $_POST['redeemed_at'] ?? date('Y-m-d H:i:s');
$user = $_SESSION['user']['username'] ?? 'kasir';

if ($redeem_points <= 0) {
    header("Location: members.php");
    exit;
}

// ambil member
$stmt = $pdo->prepare("SELECT points FROM members WHERE kode=?");
$stmt->execute([$kode]);
$member = $stmt->fetch();

if(!$member){
    echo "<article><mark>Member tidak ditemukan.</mark><p><a href='members.php'>Kembali</a></p></article>";
    exit;
}

$current_points = (int)$member['points'];
if ($redeem_points > $current_points) {
    echo "<article><mark>Poin tidak cukup. Poin sekarang: $current_points</mark><p><a href='member_redeem.php?kode=".urlencode($kode)."'>Kembali</a></p></article>";
    exit;
}

try {
    $pdo->beginTransaction();

    // kurangi poin
    $upd = $pdo->prepare("UPDATE members SET points = points - ? WHERE kode=?");
    $upd->execute([$redeem_points, $kode]);

    // catat ke log
    $ins = $pdo->prepare("INSERT INTO member_point_redemptions (member_kode, qty, description, redeemed_at, created_by)
                          VALUES (?,?,?,?,?)");
    $ins->execute([
        $kode,
        $redeem_points,
        $description,
        $redeemed_at,
        $user
    ]);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<article><mark>Gagal menyimpan penukaran: ".htmlspecialchars($e->getMessage())."</mark><p><a href='members.php'>Kembali</a></p></article>";
    exit;
}

header("Location: members.php");
exit;
