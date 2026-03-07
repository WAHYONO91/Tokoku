<?php
require 'config.php';
$stmt1 = $pdo->query('SHOW COLUMNS FROM sales');
print_r($stmt1->fetchAll(PDO::FETCH_ASSOC));

echo "\n=======\n";

$stmt2 = $pdo->query('SHOW COLUMNS FROM sale_items');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
