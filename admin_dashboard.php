<?php
// admin_dashboard.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get dashboard statistics
$stats = getDashboardStatistics($db);

// Recent orders
$stmt = $db->query("SELECT o.*, c.name as customer_name 
                   FROM orders o 
                   LEFT JOIN customers c ON o.customer_id = c.id 
                   ORDER BY o.created_at DESC LIMIT 5");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent customers
$stmt = $db->query("SELECT * FROM customers ORDER BY created_at DESC LIMIT 5");
$recent_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .welcome-banner {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #6f4e37;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #6f4e37;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .dashboard-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .section-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-card h3 {
            color: #6f4e37;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-item, .customer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item:last-child, .customer-item:last-child {
            border-bottom: none;
        }

        .order-status, .customer-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .quick-action {
            background: #6f4e37;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }

        .quick-action:hover {
            background: #5a3e2c;
            transform: translateY(-2px);
        }

        .view-all {
            text-align: center;
            margin-top: 15px;
        }

        .view-all a {
            color: #6f4e37;
            text-decoration: none;
            font-weight: 500;
        }

        @media (max-width: 968px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php 
                $successMessages = [
                    'order_updated' => 'Order updated successfully!',
                    'menu_added' => 'Menu item added successfully!',
                    'menu_updated' => 'Menu item updated successfully!',
                    'staff_added' => 'Staff member added successfully!'
                ];
                echo $successMessages[$success] ?? 'Operation completed successfully!';
                ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php 
                $errorMessages = [
                    'access_denied' => 'Access denied. Insufficient permissions.',
                    'order_not_found' => 'Order not found.',
                    'menu_not_found' => 'Menu item not found.'
                ];
                echo $errorMessages[$error] ?? 'An error occurred.';
                ?>
            </div>
        <?php endif; ?>

        <div class="welcome-banner">
            <h2>Dashboard Overview</h2>
            <p>Here's what's happening in your cafe today. <?php echo date('F j, Y'); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-number"><?php echo $stats['today_orders']; ?></div>
                <div class="stat-label">Today's Orders</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                <div class="stat-number">₹<?php echo number_format($stats['today_revenue'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                <div class="stat-number"><?php echo $stats['available_items']; ?>/<?php echo $stats['total_items']; ?></div>
                <div class="stat-label">Menu Items Available</div>
            </div>
        </div>

        <div class="dashboard-sections">
            <div>
                <div class="section-card">
                    <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    <?php if (empty($recent_orders)): ?>
                        <p>No recent orders found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-item">
                                <div>
                                    <strong>Order #<?php echo $order['id']; ?></strong>
                                    <br>
                                    <small>Customer: <?php echo $order['customer_name'] ?: 'Guest'; ?></small>
                                    <br>
                                    <small>Amount: ₹<?php echo number_format($order['total_amount'], 2); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    <br>
                                    <small><?php echo date('M j, g:i A', strtotime($order['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="view-all">
                        <a href="admin_orders.php">View All Orders →</a>
                    </div>
                </div>

                <div class="section-card">
                    <h3><i class="fas fa-plus-circle"></i> Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="admin_menu.php?action=add" class="quick-action">
                            <i class="fas fa-plus"></i><br>Add Menu Item
                        </a>
                        <a href="admin_orders.php" class="quick-action">
                            <i class="fas fa-list"></i><br>View Orders
                        </a>
                        <a href="admin_customers.php" class="quick-action">
                            <i class="fas fa-user-plus"></i><br>Manage Customers
                        </a>
                        <?php if (hasPermission('admin')): ?>
                        <a href="admin_staff.php" class="quick-action">
                            <i class="fas fa-user-shield"></i><br>Manage Staff
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="section-card">
                    <h3><i class="fas fa-user-friends"></i> Recent Customers</h3>
                    <?php if (empty($recent_customers)): ?>
                        <p>No customers yet.</p>
                    <?php else: ?>
                        <?php foreach ($recent_customers as $customer): ?>
                            <div class="customer-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($customer['email']); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <small><?php echo date('M j', strtotime($customer['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="view-all">
                        <a href="admin_customers.php">View All Customers →</a>
                    </div>
                </div>

                <div class="section-card">
                    <h3><i class="fas fa-chart-line"></i> Performance</h3>
                    <div style="text-align: center; padding: 20px 0;">
                        <div style="font-size: 2rem; color: #6f4e37; font-weight: bold;">
                            ₹<?php echo number_format($stats['total_revenue'], 2); ?>
                        </div>
                        <div style="color: #666;">Total Revenue</div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: center;">
                        <div>
                            <div style="font-size: 1.2rem; font-weight: bold; color: #6f4e37;">
                                <?php echo $stats['total_orders']; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #666;">Total Orders</div>
                        </div>
                        <div>
                            <div style="font-size: 1.2rem; font-weight: bold; color: #6f4e37;">
                                <?php echo $stats['total_items']; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #666;">Menu Items</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>