<?php
// WARNING: Jalankan sekali lalu hapus file ini (auth/reset_admin.php).
// Mengatur ulang/menetapkan user admin dengan password 'admin123'.

require_once __DIR__.'/../config.php';

$username = 'admin';
$pass = 'admin123';
$hash = password_hash($pass, PASSWORD_BCRYPT);

// Jika user 'admin' sudah ada -> update hash & role
$stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
$stmt->execute([$username]);
$exist = $stmt->fetch();

if($exist){
  $pdo->prepare("UPDATE users SET password_hash=?, role='admin' WHERE username=?")->execute([$hash, $username]);
  echo "Password admin direset. Username: admin, Password: admin123";
} else {
  $pdo->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?, 'admin')")->execute([$username,$hash]);
  echo "User admin dibuat. Username: admin, Password: admin123";
}
?>
