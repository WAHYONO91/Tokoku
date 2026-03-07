<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['member']);
header('Location: shop.php');
exit;
