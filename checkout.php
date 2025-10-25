<?php
// checkout.php

// Start output buffering to prevent header errors
ob_start();

require_once 'config.php';
require_once 'header.php';

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$error = '';
$success = '';
$customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;

// Get customer details if logged in
$customer = null;
if ($customer_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM customers WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle checkout form submission
if ($_POST && isset($_POST['place_order'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $order_type = $_POST['order_type'] ?? '';
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $special_instructions = trim($_POST['special_instructions'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cod';
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($order_type)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($order_type == 'delivery' && empty($delivery_address)) {
        $error = "Please provide delivery address for delivery orders.";
    } else {
        // Calculate total
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        $tax = $subtotal * 0.05; // 5% tax
        $delivery_fee = $order_type == 'delivery' ? 30 : 0;
        $total_amount = $subtotal + $tax + $delivery_fee;
        
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $db->beginTransaction();
            
            // Create order using original database structure
            $query = "INSERT INTO orders (customer_id, customer_name, customer_email, customer_phone, order_type, total_amount, status, delivery_address, special_instructions, payment_method) 
                     VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $customer_id, 
                $name, 
                $email, 
                $phone, 
                $order_type, 
                $total_amount,
                $delivery_address, 
                $special_instructions, 
                $payment_method
            ]);
            $order_id = $db->lastInsertId();
            
            // Add order items using original structure
            foreach ($_SESSION['cart'] as $item_id => $item) {
                $query = "INSERT INTO order_items (order_id, menu_item_id, quantity, price) 
                         VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $order_id, 
                    $item_id, 
                    $item['quantity'], 
                    $item['price']
                ]);
            }
            
            $db->commit();
            
            // Clear cart
            unset($_SESSION['cart']);
            
            // Set success message and order details
            $success = "Order placed successfully!";
            $_SESSION['last_order_id'] = $order_id;
            $_SESSION['last_order_total'] = $total_amount;
            
            // End output buffering and redirect
            ob_end_clean();
            header('Location: order_confirmation.php?order_id=' . $order_id);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error placing order: " . $e->getMessage();
        }
    }
}

// Calculate order summary
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.05;
$delivery_fee = 30; // Default delivery fee
$total_amount = $subtotal + $tax + $delivery_fee;

// End output buffering and send content
ob_end_flush();
?>

<section class="checkout-section">
    <div class="container">
        <div class="checkout-header">
            <h1>Checkout</h1>
            <div class="checkout-steps">
                <div class="step active">1. Cart</div>
                <div class="step active">2. Checkout</div>
                <div class="step">3. Confirmation</div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="checkout-container">
            <div class="checkout-form-container">
                <form method="POST" class="checkout-form" id="checkoutForm">
                    <input type="hidden" name="place_order" value="1">
                    
                    <!-- Customer Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Customer Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" class="form-control" required 
                                       value="<?php echo htmlspecialchars($customer['name'] ?? $_POST['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-control" required 
                                       value="<?php echo htmlspecialchars($customer['email'] ?? $_POST['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" class="form-control" required 
                                   value="<?php echo htmlspecialchars($customer['phone'] ?? $_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Order Type -->
                    <div class="form-section">
                        <h3><i class="fas fa-shopping-bag"></i> Order Type</h3>
                        <div class="order-type-options">
                            <label class="order-type-option">
                                <input type="radio" name="order_type" value="delivery" <?php echo ($_POST['order_type'] ?? 'delivery') === 'delivery' ? 'checked' : ''; ?>>
                                <div class="option-card">
                                    <i class="fas fa-motorcycle"></i>
                                    <h4>Delivery</h4>
                                    <p>₹30 delivery fee</p>
                                    <small>30-45 minutes</small>
                                </div>
                            </label>
                            
                            <label class="order-type-option">
                                <input type="radio" name="order_type" value="pickup" <?php echo ($_POST['order_type'] ?? '') === 'pickup' ? 'checked' : ''; ?>>
                                <div class="option-card">
                                    <i class="fas fa-store"></i>
                                    <h4>Pickup</h4>
                                    <p>No delivery fee</p>
                                    <small>Ready in 20-30 minutes</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <div class="form-section delivery-address-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Delivery Address</h3>
                        <div class="form-group">
                            <label for="delivery_address">Full Address *</label>
                            <textarea id="delivery_address" name="delivery_address" class="form-control" rows="3" 
                                      placeholder="Enter your complete address including landmark, area, and pincode"><?php echo htmlspecialchars($_POST['delivery_address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Special Instructions -->
                    <div class="form-section">
                        <h3><i class="fas fa-sticky-note"></i> Special Instructions</h3>
                        <div class="form-group">
                            <textarea id="special_instructions" name="special_instructions" class="form-control" rows="3" 
                                      placeholder="Any special requests or instructions for your order..."><?php echo htmlspecialchars($_POST['special_instructions'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-section">
                        <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="cod" <?php echo ($_POST['payment_method'] ?? 'cod') === 'cod' ? 'checked' : ''; ?>>
                                <div class="payment-card">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>Cash on Delivery</span>
                                </div>
                            </label>
                            
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="card" <?php echo ($_POST['payment_method'] ?? '') === 'card' ? 'checked' : ''; ?>>
                                <div class="payment-card">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Credit/Debit Card</span>
                                </div>
                            </label>
                            
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="upi" <?php echo ($_POST['payment_method'] ?? '') === 'upi' ? 'checked' : ''; ?>>
                                <div class="payment-card">
                                    <i class="fas fa-mobile-alt"></i>
                                    <span>UPI Payment</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="form-section">
                        <div class="terms-agreement">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" required <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                                <span>I agree to the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#" target="_blank">Privacy Policy</a></span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary-sidebar">
                <div class="order-summary">
                    <h3>Order Summary</h3>
                    
                    <div class="order-items">
                        <?php foreach ($_SESSION['cart'] as $item_id => $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                </div>
                                <div class="item-price">
                                    ₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Subtotal</span>
                            <span>₹<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Tax (5%)</span>
                            <span>₹<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="price-row delivery-fee">
                            <span>Delivery Fee</span>
                            <span id="deliveryFeeDisplay">₹<?php echo number_format($delivery_fee, 2); ?></span>
                        </div>
                        <div class="price-row total">
                            <span><strong>Total Amount</strong></span>
                            <span><strong id="totalAmountDisplay">₹<?php echo number_format($total_amount, 2); ?></strong></span>
                        </div>
                    </div>
                    
                    <button type="submit" form="checkoutForm" class="btn-place-order">
                        <i class="fas fa-lock"></i> Place Order
                    </button>
                    
                    <div class="security-notice">
                        <i class="fas fa-shield-alt"></i>
                        <span>Your payment information is secure and encrypted</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.checkout-section {
    padding: 40px 0;
    background: #f8f9fa;
    min-height: 100vh;
}

.checkout-header {
    text-align: center;
    margin-bottom: 40px;
}

.checkout-header h1 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 2.5rem;
}

.checkout-steps {
    display: flex;
    justify-content: center;
    gap: 20px;
}

.step {
    padding: 10px 20px;
    background: #e9ecef;
    border-radius: 20px;
    color: var(--gray);
    font-weight: 500;
}

.step.active {
    background: var(--primary);
    color: white;
}

.checkout-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.checkout-form-container {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #e9ecef;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.form-section h3 {
    color: var(--primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark);
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(111, 78, 55, 0.1);
}

.order-type-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.order-type-option input {
    display: none;
}

.order-type-option .option-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.order-type-option input:checked + .option-card {
    border-color: var(--primary);
    background: rgba(111, 78, 55, 0.05);
}

.order-type-option .option-card i {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 10px;
}

.order-type-option .option-card h4 {
    margin-bottom: 5px;
    color: var(--dark);
}

.order-type-option .option-card p {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 5px;
}

.order-type-option .option-card small {
    color: var(--gray);
}

.payment-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.payment-option input {
    display: none;
}

.payment-option .payment-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-option input:checked + .payment-card {
    border-color: var(--primary);
    background: rgba(111, 78, 55, 0.05);
}

.payment-option .payment-card i {
    font-size: 1.5rem;
    color: var(--primary);
    width: 30px;
}

.terms-agreement {
    margin-top: 20px;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input {
    margin-top: 3px;
}

.order-summary-sidebar {
    position: sticky;
    top: 20px;
    height: fit-content;
}

.order-summary {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.order-summary h3 {
    color: var(--primary);
    margin-bottom: 20px;
    text-align: center;
}

.order-items {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.order-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}

.order-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details {
    flex: 1;
}

.item-details h4 {
    margin-bottom: 5px;
    font-size: 0.9rem;
    color: var(--dark);
}

.item-details p {
    color: var(--gray);
    font-size: 0.8rem;
}

.item-price {
    font-weight: 600;
    color: var(--primary);
}

.price-breakdown {
    border-top: 2px solid #e9ecef;
    padding-top: 20px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.price-row.total {
    border-top: 1px solid #e9ecef;
    padding-top: 10px;
    margin-top: 10px;
    font-size: 1.1rem;
}

.delivery-fee {
    color: var(--success);
}

.btn-place-order {
    width: 100%;
    padding: 15px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-place-order:hover {
    background: #5a3e2c;
    transform: translateY(-2px);
}

.btn-place-order:disabled {
    background: var(--gray);
    cursor: not-allowed;
    transform: none;
}

.security-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    color: var(--gray);
    font-size: 0.9rem;
    justify-content: center;
}

.security-notice i {
    color: var(--success);
}

/* Responsive Design */
@media (max-width: 968px) {
    .checkout-container {
        grid-template-columns: 1fr;
    }
    
    .order-summary-sidebar {
        position: static;
        order: -1;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .order-type-options {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .checkout-section {
        padding: 20px 0;
    }
    
    .checkout-form-container {
        padding: 20px;
    }
    
    .checkout-header h1 {
        font-size: 2rem;
    }
    
    .checkout-steps {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderTypeRadios = document.querySelectorAll('input[name="order_type"]');
    const deliveryAddressSection = document.querySelector('.delivery-address-section');
    const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');
    const totalAmountDisplay = document.getElementById('totalAmountDisplay');
    
    // Calculate initial totals
    const subtotal = <?php echo $subtotal; ?>;
    const tax = <?php echo $tax; ?>;
    
    function updateOrderSummary() {
        const selectedOrderType = document.querySelector('input[name="order_type"]:checked').value;
        const deliveryFee = selectedOrderType === 'delivery' ? 30 : 0;
        const totalAmount = subtotal + tax + deliveryFee;
        
        deliveryFeeDisplay.textContent = '₹' + deliveryFee.toFixed(2);
        totalAmountDisplay.textContent = '₹' + totalAmount.toFixed(2);
        
        // Show/hide delivery address section
        if (selectedOrderType === 'delivery') {
            deliveryAddressSection.style.display = 'block';
            document.getElementById('delivery_address').required = true;
        } else {
            deliveryAddressSection.style.display = 'none';
            document.getElementById('delivery_address').required = false;
        }
    }
    
    // Add event listeners to order type radios
    orderTypeRadios.forEach(radio => {
        radio.addEventListener('change', updateOrderSummary);
    });
    
    // Initialize
    updateOrderSummary();
    
    // Form validation
    const form = document.getElementById('checkoutForm');
    form.addEventListener('submit', function(e) {
        const termsCheckbox = document.querySelector('input[name="terms"]');
        if (!termsCheckbox.checked) {
            e.preventDefault();
            alert('Please agree to the Terms and Conditions');
            return;
        }
        
        const selectedOrderType = document.querySelector('input[name="order_type"]:checked').value;
        if (selectedOrderType === 'delivery') {
            const deliveryAddress = document.getElementById('delivery_address').value.trim();
            if (!deliveryAddress) {
                e.preventDefault();
                alert('Please provide delivery address');
                return;
            }
        }
        
        // Show loading state
        const submitBtn = document.querySelector('.btn-place-order');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
        submitBtn.disabled = true;
    });
});
</script>

<?php include 'footer.php'; ?>