<?php
require 'config.php';
$stmt = $pdo->prepare("SELECT kode, nama, harga_jual1, harga_jual2, harga_jual3, harga_jual4 FROM items WHERE kode = ?");
$stmt->execute(['123']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
