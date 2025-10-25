<?php
// cart.php
require_once 'header.php';
?>

<section class="cart-section">
    <div class="container">
        <h1>Your Cart</h1>
        
        <?php if(empty($_SESSION['cart'])): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Add some delicious items from our menu</p>
                <a href="menu.php" class="btn btn-primary">Browse Menu</a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <div class="cart-items">
                    <?php 
                    $subtotal = 0;
                    foreach($_SESSION['cart'] as $id => $item): 
                        $item_total = $item['price'] * $item['quantity'];
                        $subtotal += $item_total;
                    ?>
                    <div class="cart-item">
                        <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="cart-item-img">
                        <div class="cart-item-details">
                            <h3><?php echo $item['name']; ?></h3>
                            <p class="cart-item-price">₹<?php echo $item['price']; ?></p>
                        </div>
                        <div class="cart-item-controls">
                            <div class="quantity-controls">
                                <form method="POST" action="update_cart.php" style="display:inline;">
                                    <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="action" value="decrease">
                                    <button type="submit" class="quantity-btn">-</button>
                                </form>
                                <span class="quantity-display"><?php echo $item['quantity']; ?></span>
                                <form method="POST" action="update_cart.php" style="display:inline;">
                                    <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="action" value="increase">
                                    <button type="submit" class="quantity-btn">+</button>
                                </form>
                            </div>
                            <form method="POST" action="update_cart.php">
                                <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="remove-item">Remove</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>₹<?php echo $subtotal; ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (5%)</span>
                        <span>₹<?php echo $subtotal * 0.05; ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <span>₹30</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span>₹<?php echo $subtotal + ($subtotal * 0.05) + 30; ?></span>
                    </div>
                    
                    <a href="checkout.php" class="btn btn-primary checkout-btn">Proceed to Checkout</a>
                    <a href="menu.php" class="continue-shopping">Continue Shopping</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'footer.php'; ?>
<style>
    <?php include 'styles.css'; ?>
</style>