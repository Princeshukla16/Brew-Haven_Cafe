<?php
// admin_customers.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

// Safe output function that handles null values
function safe_output($value, $default = '') {
    if ($value === null || $value === false) {
        return htmlspecialchars($default);
    }
    return htmlspecialchars((string)$value);
}

$database = new Database();
$db = $database->getConnection();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Process form submissions
if ($_POST) {
    if (isset($_POST['update_customer'])) {
        $customer_id = intval($_POST['customer_id']);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $address, $is_active, $customer_id]);
            $message = 'success=customer_updated';
        } catch (Exception $e) {
            $message = 'error=update_failed';
        }
    }
    elseif (isset($_POST['delete_customer'])) {
        $customer_id = intval($_POST['id']);
        
        try {
            // Check if customer exists
            $stmt = $db->prepare("SELECT id FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $customer_exists = $stmt->fetch();
            
            if (!$customer_exists) {
                $message = 'error=customer_not_found';
            } else {
                // Check if customer has orders
                $stmt = $db->prepare("SELECT COUNT(*) as order_count FROM orders WHERE customer_id = ?");
                $stmt->execute([$customer_id]);
                $order_count = $stmt->fetch(PDO::FETCH_ASSOC)['order_count'];
                
                if ($order_count > 0) {
                    // Customer has orders - deactivate instead of delete
                    $stmt = $db->prepare("UPDATE customers SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$customer_id]);
                    $message = 'success=customer_deactivated';
                } else {
                    // Customer has no orders - safe to delete
                    // First delete loyalty transactions
                    try {
                        $stmt = $db->prepare("DELETE FROM loyalty_transactions WHERE customer_id = ?");
                        $stmt->execute([$customer_id]);
                    } catch (Exception $e) {
                        // Ignore if table doesn't exist or other issues
                    }
                    
                    // Now delete the customer
                    $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
                    $stmt->execute([$customer_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'success=customer_deleted';
                    } else {
                        $message = 'error=delete_failed';
                    }
                }
            }
            $action = 'list';
        } catch (Exception $e) {
            error_log("Delete customer error: " . $e->getMessage());
            $message = 'error=delete_failed';
        }
    }
    elseif (isset($_POST['add_loyalty_points'])) {
        $customer_id = intval($_POST['customer_id']);
        $points = intval($_POST['points'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        try {
            // Get current points
            $stmt = $db->prepare("SELECT loyalty_points FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $current_points = $stmt->fetch(PDO::FETCH_COLUMN) ?? 0;
            
            // Update points
            $new_points = $current_points + $points;
            $stmt = $db->prepare("UPDATE customers SET loyalty_points = ? WHERE id = ?");
            $stmt->execute([$new_points, $customer_id]);
            
            // Log the transaction
            $stmt = $db->prepare("INSERT INTO loyalty_transactions (customer_id, points, type, reason) VALUES (?, ?, ?, ?)");
            $type = $points > 0 ? 'added' : 'deducted';
            $stmt->execute([$customer_id, abs($points), $type, $reason]);
            
            $message = 'success=points_updated';
        } catch (Exception $e) {
            $message = 'error=points_update_failed';
        }
    }
    
    header("Location: admin_customers.php?action=$action&id=$customer_id&$message");
    exit;
}

// Get filter parameters with null safety
$status_filter = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Build query with filters
$query = "SELECT c.*, 
          COUNT(o.id) as total_orders,
          COALESCE(SUM(o.total_amount), 0) as total_spent,
          MAX(o.created_at) as last_order_date
          FROM customers c 
          LEFT JOIN orders o ON c.id = o.customer_id";

$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $where_conditions[] = "c.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "c.is_active = 0";
    } elseif ($status_filter === 'with_orders') {
        $where_conditions[] = "o.id IS NOT NULL";
    } elseif ($status_filter === 'no_orders') {
        $where_conditions[] = "o.id IS NULL";
    }
}

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " GROUP BY c.id ORDER BY c.created_at DESC";

// Get customers
$customers = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

// Get customer details for view/edit
$customer = null;
$customer_orders = [];
$loyalty_transactions = [];
if (($action === 'view' || $action === 'edit') && $customer_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            // Get customer orders
            try {
                $stmt = $db->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt->execute([$customer_id]);
                $customer_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error fetching customer orders: " . $e->getMessage());
            }
            
            // Get loyalty transactions if table exists
            try {
                $stmt = $db->prepare("SELECT * FROM loyalty_transactions WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt->execute([$customer_id]);
                $loyalty_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Table might not exist, that's okay
            }
        } else {
            header("Location: admin_customers.php?error=customer_not_found");
            exit;
        }
    } catch (Exception $e) {
        error_log("Error fetching customer details: " . $e->getMessage());
        header("Location: admin_customers.php?error=customer_not_found");
        exit;
    }
}

// Create loyalty_transactions table if it doesn't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS loyalty_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        points INT NOT NULL,
        type ENUM('added', 'deducted') NOT NULL,
        reason VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    // Table creation failed, but we can continue
}

// Get statistics with safe defaults
$stats = [
    'total_customers' => 0,
    'active_customers' => 0,
    'customers_with_orders' => 0,
    'avg_loyalty_points' => 0
];

try {
    $stmt = $db->query("SELECT 
        COUNT(*) as total_customers,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_customers,
        COUNT(DISTINCT o.customer_id) as customers_with_orders,
        COALESCE(AVG(loyalty_points), 0) as avg_loyalty_points
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = array_map(function($value) {
            return $value !== null ? $value : 0;
        }, $result);
    }
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $stats['total_customers'] = count($customers);
    $stats['active_customers'] = count(array_filter($customers, function($c) {
        return ($c['is_active'] ?? 1) == 1;
    }));
}

// Get top customers by spending
$top_customers = [];
try {
    $stmt = $db->query("SELECT c.name, c.email, SUM(o.total_amount) as total_spent 
                       FROM customers c 
                       JOIN orders o ON c.id = o.customer_id 
                       WHERE o.status = 'completed'
                       GROUP BY c.id 
                       ORDER BY total_spent DESC 
                       LIMIT 5");
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching top customers: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; }
        .page-title { color: #6f4e37; font-size: 28px; margin: 0; }
        .btn { padding: 10px 20px; background: #6f4e37; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; transition: all 0.3s; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn:hover { background: #5a3e2c; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(111, 78, 55, 0.3); }
        .btn-secondary { background: #6c757d; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; }
        .btn-danger { background: #dc3545; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s; border-left: 4px solid #6f4e37; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon { font-size: 2.5rem; color: #6f4e37; margin-bottom: 15px; }
        .stat-number { font-size: 24px; font-weight: bold; color: #6f4e37; margin-bottom: 5px; }
        .stat-label { font-size: 14px; color: #6c757d; font-weight: 500; }
        .filters-section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: 600; margin-bottom: 8px; color: #495057; }
        .filter-group select, .filter-group input { padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px; }
        .filter-group select:focus, .filter-group input:focus { border-color: #6f4e37; outline: none; }
        .filter-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .table-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #e9ecef; }
        .table td { padding: 15px; border-bottom: 1px solid #e9ecef; }
        .table tr:hover { background: #f8f9fa; }
        .customer-avatar { width: 40px; height: 40px; border-radius: 50%; background: #6f4e37; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px; }
        .customer-info { display: flex; align-items: center; gap: 12px; }
        .customer-details { display: flex; flex-direction: column; }
        .customer-name { font-weight: 600; color: #495057; }
        .customer-email { font-size: 12px; color: #6c757d; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .loyalty-badge { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .amount { font-weight: 600; color: #28a745; }
        .action-buttons { display: flex; gap: 8px; }
        .customer-details-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        .customer-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e9ecef; }
        .customer-profile { display: flex; align-items: center; gap: 20px; }
        .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: #6f4e37; color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; }
        .profile-info h2 { margin: 0 0 5px 0; color: #495057; }
        .profile-info p { margin: 0; color: #6c757d; }
        .customer-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .info-card h4 { margin: 0 0 10px 0; color: #495057; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-card .number { font-size: 24px; font-weight: bold; color: #6f4e37; }
        .tabs { display: flex; border-bottom: 2px solid #e9ecef; margin-bottom: 30px; }
        .tab { padding: 15px 25px; background: none; border: none; cursor: pointer; font-weight: 500; color: #6c757d; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .tab.active { color: #6f4e37; border-bottom-color: #6f4e37; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .orders-list, .transactions-list { margin-bottom: 30px; }
        .order-card, .transaction-card { display: flex; justify-content: space-between; align-items: center; padding: 15px; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 10px; }
        .order-info, .transaction-info { flex: 1; }
        .order-id, .transaction-type { font-weight: 600; color: #495057; }
        .order-date, .transaction-date { font-size: 12px; color: #6c757d; }
        .loyalty-form { background: #f8f9fa; padding: 25px; border-radius: 8px; margin-top: 20px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 8px; color: #495057; }
        .form-group select, .form-group input, .form-group textarea { padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .no-data { text-align: center; padding: 40px 20px; color: #6c757d; }
        .no-data i { font-size: 48px; color: #e9ecef; margin-bottom: 20px; }
        .edit-form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px; }
        .top-customers { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .top-customer-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
        .top-customer-item:last-child { border-bottom: none; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: 500; border-left: 4px solid; }
        .alert.success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert.error { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        @media (max-width: 768px) {
            .content-header { flex-direction: column; align-items: flex-start; }
            .filters-grid { grid-template-columns: 1fr; }
            .table { display: block; overflow-x: auto; }
            .customer-header { flex-direction: column; gap: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .tabs { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-users"></i> Customer Management
            </h1>
            <div class="header-actions">
                <a href="admin_customers.php?export=csv" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <a href="admin_dashboard.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">
                <?php 
                $successMessages = [
                    'customer_updated' => 'Customer updated successfully!',
                    'customer_deleted' => 'Customer deleted successfully!',
                    'customer_deactivated' => 'Customer has been deactivated (cannot delete customers with existing orders).',
                    'points_updated' => 'Loyalty points updated successfully!'
                ];
                echo safe_output($successMessages[$_GET['success']] ?? 'Operation completed successfully!');
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert error">
                <?php 
                $errorMessages = [
                    'update_failed' => 'Failed to update customer.',
                    'delete_failed' => 'Failed to delete customer.',
                    'points_update_failed' => 'Failed to update loyalty points.',
                    'customer_not_found' => 'Customer not found.'
                ];
                echo safe_output($errorMessages[$_GET['error']] ?? 'An error occurred.');
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo safe_output($stats['total_customers']); ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo safe_output($stats['active_customers']); ?></div>
                <div class="stat-label">Active Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-number"><?php echo safe_output($stats['customers_with_orders']); ?></div>
                <div class="stat-label">Customers with Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-number"><?php echo round(safe_output($stats['avg_loyalty_points']), 1); ?></div>
                <div class="stat-label">Avg Loyalty Points</div>
            </div>
        </div>

        <!-- Top Customers -->
        <?php if (!empty($top_customers)): ?>
        <div class="top-customers">
            <h3><i class="fas fa-trophy"></i> Top Customers by Spending</h3>
            <?php foreach ($top_customers as $index => $top_customer): ?>
                <div class="top-customer-item">
                    <div>
                        <strong>#<?php echo $index + 1; ?> <?php echo safe_output($top_customer['name']); ?></strong>
                        <div style="font-size: 12px; color: #6c757d;"><?php echo safe_output($top_customer['email']); ?></div>
                    </div>
                    <div class="amount">₹<?php echo number_format($top_customer['total_spent'] ?? 0, 2); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="admin_customers.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Customers</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                            <option value="with_orders" <?php echo $status_filter === 'with_orders' ? 'selected' : ''; ?>>With Orders</option>
                            <option value="no_orders" <?php echo $status_filter === 'no_orders' ? 'selected' : ''; ?>>No Orders</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Name, Email, Phone..." 
                               value="<?php echo safe_output($search); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="admin_customers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <?php if ($action === 'edit' && $customer): ?>
            <!-- Edit Customer Form -->
            <div class="edit-form-container">
                <h2>Edit Customer</h2>
                
                <form method="POST">
                    <input type="hidden" name="update_customer" value="1">
                    <input type="hidden" name="customer_id" value="<?php echo safe_output($customer['id']); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo safe_output($customer['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo safe_output($customer['email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo safe_output($customer['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address"><?php echo safe_output($customer['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php echo ($customer['is_active'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="is_active">Active Customer</label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin_customers.php?action=view&id=<?php echo safe_output($customer['id']); ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Customer
                        </button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'view' && $customer): ?>
            <!-- Customer Details View -->
            <div class="customer-details-container">
                <div class="customer-header">
                    <div class="customer-profile">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr(safe_output($customer['name']), 0, 1)); ?>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo safe_output($customer['name']); ?></h2>
                            <p><?php echo safe_output($customer['email']); ?></p>
                            <p><?php echo safe_output($customer['phone'] ?? 'No phone number'); ?></p>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a href="admin_customers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <a href="admin_customers.php?action=edit&id=<?php echo safe_output($customer['id']); ?>" class="btn">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <!-- DELETE BUTTON IN CUSTOMER DETAILS VIEW -->
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Are you sure you want to delete customer <?php echo safe_output($customer['name']); ?>? This action cannot be undone.')">
                            <input type="hidden" name="delete_customer" value="1">
                            <input type="hidden" name="id" value="<?php echo safe_output($customer['id']); ?>">
                            <button type="submit" class="btn btn-danger" title="Delete Customer">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>

                <div class="customer-stats">
                    <div class="info-card">
                        <h4>Total Orders</h4>
                        <div class="number"><?php echo count($customer_orders); ?></div>
                    </div>
                    <div class="info-card">
                        <h4>Total Spent</h4>
                        <div class="number">₹<?php echo number_format(array_sum(array_column($customer_orders, 'total_amount')), 2); ?></div>
                    </div>
                    <div class="info-card">
                        <h4>Loyalty Points</h4>
                        <div class="number"><?php echo safe_output($customer['loyalty_points'] ?? 0); ?></div>
                    </div>
                    <div class="info-card">
                        <h4>Member Since</h4>
                        <div class="number"><?php echo date('M Y', strtotime(safe_output($customer['created_at']))); ?></div>
                    </div>
                </div>

                <div class="tabs">
                    <button class="tab active" onclick="switchTab('orders')">Orders</button>
                    <button class="tab" onclick="switchTab('loyalty')">Loyalty Points</button>
                    <button class="tab" onclick="switchTab('details')">Details</button>
                </div>

                <!-- Orders Tab -->
                <div id="orders-tab" class="tab-content active">
                    <div class="orders-list">
                        <h3>Recent Orders</h3>
                        <?php if (empty($customer_orders)): ?>
                            <div class="no-data">
                                <i class="fas fa-shopping-cart"></i>
                                <p>No orders found for this customer.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($customer_orders as $order): ?>
                                <div class="order-card">
                                    <div class="order-info">
                                        <div class="order-id">Order #<?php echo safe_output($order['id']); ?></div>
                                        <div class="order-date"><?php echo date('M j, Y g:i A', strtotime(safe_output($order['created_at']))); ?></div>
                                        <div>Status: <span class="status-badge status-<?php echo safe_output($order['status']); ?>">
                                            <?php echo ucfirst(safe_output($order['status'])); ?>
                                        </span></div>
                                    </div>
                                    <div class="amount">₹<?php echo number_format($order['total_amount'] ?? 0, 2); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Loyalty Points Tab -->
                <div id="loyalty-tab" class="tab-content">
                    <div class="transactions-list">
                        <h3>Loyalty Points: <?php echo safe_output($customer['loyalty_points'] ?? 0); ?></h3>
                        
                        <!-- Add/Remove Points Form -->
                        <form method="POST" class="loyalty-form">
                            <input type="hidden" name="add_loyalty_points" value="1">
                            <input type="hidden" name="customer_id" value="<?php echo safe_output($customer['id']); ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="points">Points</label>
                                    <input type="number" id="points" name="points" required 
                                           placeholder="Positive to add, negative to deduct">
                                </div>
                                
                                <div class="form-group">
                                    <label for="reason">Reason</label>
                                    <input type="text" id="reason" name="reason" required 
                                           placeholder="Reason for points adjustment">
                                </div>
                            </div>
                            
                            <div style="text-align: right;">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus-circle"></i> Update Points
                                </button>
                            </div>
                        </form>

                        <?php if (!empty($loyalty_transactions)): ?>
                            <h4>Recent Transactions</h4>
                            <?php foreach ($loyalty_transactions as $transaction): ?>
                                <div class="transaction-card">
                                    <div class="transaction-info">
                                        <div class="transaction-type">
                                            <?php echo ucfirst(safe_output($transaction['type'])); ?> <?php echo safe_output($transaction['points']); ?> points
                                        </div>
                                        <div class="transaction-date"><?php echo date('M j, Y g:i A', strtotime(safe_output($transaction['created_at']))); ?></div>
                                        <div><?php echo safe_output($transaction['reason']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-star"></i>
                                <p>No loyalty transactions found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Details Tab -->
                <div id="details-tab" class="tab-content">
                    <div class="form-grid">
                        <div class="info-card">
                            <h4>Contact Information</h4>
                            <p><strong>Email:</strong> <?php echo safe_output($customer['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo safe_output($customer['phone'] ?? 'Not provided'); ?></p>
                        </div>
                        
                        <?php if (!empty($customer['address'])): ?>
                        <div class="info-card">
                            <h4>Address</h4>
                            <p><?php echo nl2br(safe_output($customer['address'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-card">
                            <h4>Account Status</h4>
                            <p><strong>Status:</strong> 
                                <span class="status-badge status-<?php echo ($customer['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                                    <?php echo ($customer['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </p>
                            <p><strong>Joined:</strong> <?php echo date('F j, Y', strtotime(safe_output($customer['created_at']))); ?></p>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Customers List -->
            <div class="table-container">
                <?php if (empty($customers)): ?>
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <h3>No Customers Found</h3>
                        <p>There are no customers matching your current filters.</p>
                        <a href="admin_customers.php" class="btn">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Loyalty Points</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-avatar">
                                                <?php echo strtoupper(substr(safe_output($customer['name']), 0, 1)); ?>
                                            </div>
                                            <div class="customer-details">
                                                <span class="customer-name"><?php echo safe_output($customer['name']); ?></span>
                                                <span class="customer-email"><?php echo safe_output($customer['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($customer['phone'])): ?>
                                            <?php echo safe_output($customer['phone']); ?>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-style: italic;">No phone</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo safe_output($customer['total_orders'] ?? 0); ?></td>
                                    <td class="amount">₹<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></td>
                                    <td>
                                        <?php if (($customer['loyalty_points'] ?? 0) > 0): ?>
                                            <span class="loyalty-badge">
                                                <i class="fas fa-star"></i> <?php echo safe_output($customer['loyalty_points']); ?> points
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo ($customer['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                                            <?php echo ($customer['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime(safe_output($customer['created_at']))); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="admin_customers.php?action=view&id=<?php echo safe_output($customer['id']); ?>" 
                                               class="btn btn-small btn-info" title="View Customer">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="admin_customers.php?action=edit&id=<?php echo safe_output($customer['id']); ?>" 
                                               class="btn btn-small" title="Edit Customer">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- CORRECTED DELETE BUTTON IN CUSTOMERS LIST -->
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete customer <?php echo safe_output($customer['name']); ?>? This action cannot be undone.')">
                                                <input type="hidden" name="delete_customer" value="1">
                                                <input type="hidden" name="id" value="<?php echo safe_output($customer['id']); ?>">
                                                <button type="submit" class="btn btn-small btn-danger" title="Delete Customer">
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
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tabs and contents
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show selected tab
            document.querySelector(`.tab:nth-child(${getTabIndex(tabName)})`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        function getTabIndex(tabName) {
            const tabs = {
                'orders': 1,
                'loyalty': 2,
                'details': 3
            };
            return tabs[tabName] || 1;
        }

        // Search functionality
        document.getElementById('search')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Loyalty points validation
        document.querySelector('input[name="points"]')?.addEventListener('change', function() {
            const points = parseInt(this.value);
            if (isNaN(points)) {
                alert('Please enter a valid number for points');
                this.value = '';
            }
        });

        // Auto-focus search on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value === '') {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>