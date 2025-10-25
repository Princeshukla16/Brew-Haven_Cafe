<?php
// admin_orders.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Process form submissions
if ($_POST) {
    if (isset($_POST['update_order_status'])) {
        $order_id = intval($_POST['order_id']);
        $status = trim($_POST['status']);
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            // First, verify the order exists
            $stmt = $db->prepare("SELECT id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order_exists = $stmt->fetch();
            
            if (!$order_exists) {
                $message = 'error=order_not_found';
            } else {
                // Update the order status
                $stmt = $db->prepare("UPDATE orders SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $notes, $order_id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = 'success=order_updated';
                } else {
                    $message = 'error=update_failed';
                }
            }
        } catch (Exception $e) {
            error_log("Update order error: " . $e->getMessage());
            $message = 'error=update_failed';
        }
        
        // Redirect back to the order view page
        header("Location: admin_orders.php?action=view&id=$order_id&$message");
        exit;
    }
    elseif (isset($_POST['delete_order'])) {
        $order_id = intval($_POST['order_id']);
        try {
            // First delete order items
            $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            // Then delete the order
            $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'success=order_deleted';
            } else {
                $message = 'error=delete_failed';
            }
        } catch (Exception $e) {
            error_log("Delete order error: " . $e->getMessage());
            $message = 'error=delete_failed';
        }
        
        header("Location: admin_orders.php?$message");
        exit;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$query = "SELECT o.*, c.name as customer_name, c.phone as customer_phone, 
          COUNT(oi.id) as item_count, 
          SUM(oi.quantity) as total_quantity
          FROM orders o 
          LEFT JOIN customers c ON o.customer_id = c.id 
          LEFT JOIN order_items oi ON o.id = oi.order_id";

$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(o.created_at) = ?";
    $params[] = $date_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(o.id LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Get orders
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
}

// Get order details for view/edit
$order = null;
$order_items = [];
if ($action === 'view' && $order_id > 0) {
    try {
        $stmt = $db->prepare("SELECT o.*, c.name as customer_name, c.email as customer_email, 
                             c.phone as customer_phone
                             FROM orders o 
                             LEFT JOIN customers c ON o.customer_id = c.id 
                             WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $stmt = $db->prepare("SELECT oi.*, mi.name as item_name, mi.image_url, mi.price
                                 FROM order_items oi 
                                 LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                 WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            header("Location: admin_orders.php?error=order_not_found");
            exit;
        }
    } catch (Exception $e) {
        error_log("Error fetching order details: " . $e->getMessage());
        header("Location: admin_orders.php?error=order_not_found");
        exit;
    }
}

// Get statistics for the dashboard
$status_stats = [
    'pending' => 0,
    'confirmed' => 0,
    'preparing' => 0,
    'ready' => 0,
    'completed' => 0,
    'cancelled' => 0
];

try {
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($status_counts as $stat) {
        if (isset($status_stats[$stat['status']])) {
            $status_stats[$stat['status']] = $stat['count'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching order statistics: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            color: #6f4e37;
            font-size: 28px;
            margin: 0;
        }

        .btn {
            padding: 10px 20px;
            background: #6f4e37;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: #5a3e2c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(111, 78, 55, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-info {
            background: #17a2b8;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #6f4e37;
            outline: none;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Statistics Cards */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
            border-left: 4px solid #6f4e37;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #6f4e37;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
        }

        /* Orders Table */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .order-id {
            font-weight: 600;
            color: #6f4e37;
        }

        .customer-info {
            display: flex;
            flex-direction: column;
        }

        .customer-name {
            font-weight: 600;
            color: #495057;
        }

        .customer-phone {
            font-size: 12px;
            color: #6c757d;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-preparing { background: #ffeaa7; color: #856404; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-completed { background: #c3e6cb; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .amount {
            font-weight: 600;
            color: #28a745;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        /* Order Details View */
        .order-details-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #6f4e37;
        }

        .info-card h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 16px;
        }

        .info-card p {
            margin: 5px 0;
            color: #6c757d;
        }

        .items-list {
            margin-bottom: 30px;
        }

        .item-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .item-price {
            color: #28a745;
            font-weight: 600;
        }

        .status-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }

        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .no-orders {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-orders i {
            font-size: 48px;
            color: #e9ecef;
            margin-bottom: 20px;
        }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .order-header {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i> Order Management
            </h1>
            <div class="header-actions">
                <a href="admin_orders.php?export=csv" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <a href="admin_dashboard.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                $successMessages = [
                    'order_updated' => 'Order status updated successfully!',
                    'order_deleted' => 'Order deleted successfully!'
                ];
                echo htmlspecialchars($successMessages[$_GET['success']] ?? 'Operation completed successfully!');
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                $errorMessages = [
                    'update_failed' => 'Failed to update order status.',
                    'delete_failed' => 'Failed to delete order.',
                    'order_not_found' => 'Order not found.'
                ];
                echo htmlspecialchars($errorMessages[$_GET['error']] ?? 'An error occurred.');
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($orders); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $status_stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $status_stats['preparing']; ?></div>
                <div class="stat-label">Preparing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $status_stats['ready']; ?></div>
                <div class="stat-label">Ready</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $status_stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="admin_orders.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="preparing" <?php echo $status_filter === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                            <option value="ready" <?php echo $status_filter === 'ready' ? 'selected' : ''; ?>>Ready</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Order ID, Customer Name, Phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="admin_orders.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <?php if ($action === 'view' && $order): ?>
            <!-- Order Details View -->
            <div class="order-details-container">
                <div class="order-header">
                    <div>
                        <h2>Order #<?php echo $order['id']; ?></h2>
                        <p>Placed on <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="action-buttons">
                        <a href="admin_orders.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                        <button onclick="window.print()" class="btn btn-info">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                        <!-- DELETE BUTTON IN ORDER DETAILS VIEW -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="delete_order" value="1">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete order #<?php echo $order['id']; ?>? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete Order
                            </button>
                        </form>
                    </div>
                </div>

                <div class="order-info">
                    <div class="info-card">
                        <h4>Customer Information</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?: 'N/A'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email'] ?: 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Order Details</h4>
                        <p><strong>Type:</strong> <?php echo ucfirst($order['order_type']); ?></p>
                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span></p>
                        <p><strong>Total Amount:</strong> ₹<?php echo number_format($order['total_amount'], 2); ?></p>
                    </div>
                    
                    <?php if ($order['order_type'] === 'delivery' && !empty($order['delivery_address'])): ?>
                    <div class="info-card">
                        <h4>Delivery Address</h4>
                        <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($order['special_instructions'])): ?>
                    <div class="info-card">
                        <h4>Special Instructions</h4>
                        <p><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="items-list">
                    <h3>Order Items (<?php echo count($order_items); ?> items)</h3>
                    <?php foreach ($order_items as $item): ?>
                        <div class="item-card">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                     class="item-image">
                            <?php else: ?>
                                <div class="item-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-utensils" style="color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div>Quantity: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></div>
                                <?php if (!empty($item['special_requests'])): ?>
                                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                        <strong>Note:</strong> <?php echo htmlspecialchars($item['special_requests']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Status Update Form -->
                <form method="POST" class="status-form">
                    <input type="hidden" name="update_order_status" value="1">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="status">Update Status</label>
                            <select id="status" name="status" required>
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Admin Notes</label>
                            <textarea id="notes" name="notes" placeholder="Add any notes or instructions..."><?php echo htmlspecialchars($order['admin_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Order
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Orders List -->
            <div class="table-container">
                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>No Orders Found</h3>
                        <p>There are no orders matching your current filters.</p>
                        <a href="admin_orders.php" class="btn">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <span class="order-id">#<?php echo $order['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <span class="customer-name"><?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?></span>
                                            <?php if ($order['customer_phone']): ?>
                                                <span class="customer-phone"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $order['item_count']; ?> items
                                        (<?php echo $order['total_quantity']; ?> qty)
                                    </td>
                                    <td class="amount">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo ucfirst($order['order_type']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, g:i A', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="admin_orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                               class="btn btn-small btn-info" title="View Order">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <!-- DELETE BUTTON IN ORDERS LIST -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="delete_order" value="1">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn btn-small btn-danger" 
                                                        title="Delete Order"
                                                        onclick="return confirm('Are you sure you want to delete order #<?php echo $order['id']; ?>? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh orders every 30 seconds
        setInterval(() => {
            if (!document.hidden && window.location.href.includes('admin_orders.php') && !window.location.href.includes('action=view')) {
                window.location.reload();
            }
        }, 30000);

        // Status change confirmation
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                if (this.value === 'cancelled') {
                    if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                        this.value = this.defaultValue;
                    }
                }
            });
        });

        // Search functionality
        document.getElementById('search')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>