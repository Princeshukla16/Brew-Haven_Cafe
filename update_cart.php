<?php
// update_cart.php
require_once 'config.php';

if ($_POST['action'] == 'increase') {
    $_SESSION['cart'][$_POST['item_id']]['quantity']++;
} elseif ($_POST['action'] == 'decrease') {
    if ($_SESSION['cart'][$_POST['item_id']]['quantity'] > 1) {
        $_SESSION['cart'][$_POST['item_id']]['quantity']--;
    }
} elseif ($_POST['action'] == 'remove') {
    unset($_SESSION['cart'][$_POST['item_id']]);
}

header('Location: cart.php');
exit;
?>