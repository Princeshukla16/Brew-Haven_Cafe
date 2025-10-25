<?php
// cart_handler.php
require_once 'config.php';

if ($_POST['action'] == 'add') {
    $item_id = $_POST['item_id'];
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add item to cart or increase quantity
    if (isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id]['quantity']++;
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM menu_items WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['cart'][$item_id] = [
            'name' => $item['name'],
            'price' => $item['price'],
            'image' => $item['image_url'],
            'quantity' => 1
        ];
    }
    
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>