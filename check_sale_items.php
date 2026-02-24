<?php
require 'config.php';
$stmt = $pdo->query("DESCRIBE sale_items");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
