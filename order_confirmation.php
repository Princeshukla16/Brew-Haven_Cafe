<?php
// order_confirmation.php
require_once 'header.php';
require_once 'config.php';

// Check if order_id is provided
if (!isset($_GET['order_id']) || !isset($_SESSION['last_order_id'])) {
    header('Location: index.php');
    exit;
}

$order_id = $_GET['order_id'];

// Verify this order belongs to the current customer (if logged in)
$database = new Database();
$db = $database->getConnection();

$query = "SELECT o.*, c.name as customer_name, c.email as customer_email 
          FROM orders o 
          LEFT JOIN customers c ON o.customer_id = c.id 
          WHERE o.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php');
    exit;
}

// Get order items
$query = "SELECT oi.*, mi.name as item_name, mi.image_url 
          FROM order_items oi 
          JOIN menu_items mi ON oi.menu_item_id = mi.id 
          WHERE oi.order_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="confirmation-section">
    <div class="container">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Order Confirmed!</h1>
                <p>Thank you for your order. We've received it and will start preparing it right away.</p>
            </div>
            
            <div class="confirmation-details">
                <div class="order-info">
                    <h3>Order Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Order Number:</strong>
                            <span>#<?php echo $order['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Order Type:</strong>
                            <span><?php echo ucfirst($order['order_type']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Order Date:</strong>
                            <span><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Status:</strong>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Total Amount:</strong>
                            <span class="total-amount">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="order-items-summary">
                    <h3>Order Items</h3>
                    <div class="items-list">
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <img src="<?php echo $item['image_url']; ?>" alt="<?php echo $item['item_name']; ?>">
                                <div class="item-info">
                                    <h4><?php echo $item['item_name']; ?></h4>
                                    <p>Quantity: <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="item-price">
                                    ₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($order['order_type'] == 'delivery' && $order['delivery_address']): ?>
                <div class="delivery-info">
                    <h3>Delivery Information</h3>
                    <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                    <p class="estimated-time">
                        <i class="fas fa-clock"></i>
                        Estimated delivery time: 30-45 minutes
                    </p>
                </div>
                <?php elseif ($order['order_type'] == 'pickup'): ?>
                <div class="pickup-info">
                    <h3>Pickup Information</h3>
                    <p>
                        <strong>BrewHaven Cafe</strong><br>
                        123 Coffee Lane, Brigade Road<br>
                        Bengaluru, Karnataka 560001<br>
                        Phone: +91 80 1234 5678
                    </p>
                    <p class="estimated-time">
                        <i class="fas fa-clock"></i>
                        Estimated ready time: 20-30 minutes
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if ($order['special_instructions']): ?>
                <div class="special-instructions">
                    <h3>Special Instructions</h3>
                    <p><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="confirmation-actions">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Back to Home
                </a>
                <a href="menu.php" class="btn btn-secondary">
                    <i class="fas fa-utensils"></i> Order Again
                </a>
                <button onclick="window.print()" class="btn btn-outline">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>
            
            <div class="whats-next">
                <h3>What's Next?</h3>
                <div class="next-steps">
                    <div class="step">
                        <i class="fas fa-utensils"></i>
                        <h4>We're Preparing Your Order</h4>
                        <p>Our chefs are working on your delicious items</p>
                    </div>
                    <div class="step">
                        <i class="fas fa-bell"></i>
                        <h4>We'll Notify You</h4>
                        <p>You'll receive updates about your order status</p>
                    </div>
                    <div class="step">
                        <i class="fas fa-smile"></i>
                        <h4>Enjoy Your Meal!</h4>
                        <p>Relax while we get your order ready</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .confirmation-section {
        padding: 40px 0;
        background: linear-gradient(135deg, #f8f5f0 0%, #ffffff 100%);
        min-height: 100vh;
    }

    .confirmation-container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .confirmation-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 40px;
        text-align: center;
    }

    .success-icon {
        font-size: 4rem;
        margin-bottom: 20px;
    }

    .confirmation-header h1 {
        margin-bottom: 10px;
        font-size: 2.5rem;
    }

    .confirmation-details {
        padding: 30px;
    }

    .order-info, .order-items-summary, .delivery-info, .pickup-info, .special-instructions {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e9ecef;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
    }

    .status-badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-pending { background: #fff3cd; color: #856404; }
    .status-confirmed { background: #d1ecf1; color: #0c5460; }

    .total-amount {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }

    .order-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 0;
        border-bottom: 1px solid #f8f9fa;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .order-item img {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        object-fit: cover;
    }

    .item-info {
        flex: 1;
    }

    .item-info h4 {
        margin-bottom: 5px;
    }

    .estimated-time {
        background: #e7f3ff;
        padding: 10px 15px;
        border-radius: 8px;
        margin-top: 10px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .confirmation-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        padding: 30px;
        border-top: 1px solid #e9ecef;
        flex-wrap: wrap;
    }

    .whats-next {
        background: #f8f9fa;
        padding: 30px;
        text-align: center;
    }

    .next-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .step {
        padding: 20px;
    }

    .step i {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 15px;
    }

    @media (max-width: 768px) {
        .confirmation-container {
            margin: 20px;
        }
        
        .confirmation-header {
            padding: 30px 20px;
        }
        
        .confirmation-details {
            padding: 20px;
        }
        
        .confirmation-actions {
            flex-direction: column;
        }
        
        .next-steps {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'footer.php'; ?>