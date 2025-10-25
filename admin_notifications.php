<?php
// admin_notifications.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$notification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Process actions
if ($_POST) {
    if (isset($_POST['mark_all_read'])) {
        try {
            // In a real system, you'd update a notifications table
            // For now, we'll just acknowledge the action
            $message = 'success=all_marked_read';
        } catch (Exception $e) {
            $message = 'error=mark_read_failed';
        }
    }
    elseif (isset($_POST['clear_all'])) {
        try {
            // In a real system, you'd clear notifications from database
            $message = 'success=all_cleared';
        } catch (Exception $e) {
            $message = 'error=clear_failed';
        }
    }
    
    header("Location: admin_notifications.php?$message");
    exit;
}

// Get filter parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get all notifications data
$notifications = [];

// Pending orders notifications
try {
    $stmt = $db->prepare("SELECT 
        o.id,
        'order' as type,
        'Pending Order #' || o.id as title,
        'Order from ' || COALESCE(c.name, 'Guest') || ' for ₹' || o.total_amount as message,
        o.created_at as created_date,
        'unread' as status,
        'high' as priority
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE o.status = 'pending'
    ORDER BY o.created_at DESC");
    $stmt->execute();
    $order_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications = array_merge($notifications, $order_notifications);
} catch (Exception $e) {
    error_log("Order notifications error: " . $e->getMessage());
}

// Pending reservations notifications
try {
    $stmt = $db->prepare("SELECT 
        r.id,
        'reservation' as type,
        'Pending Reservation' as title,
        'Reservation for ' || r.customer_name || ' (' || r.party_size || ' people) on ' || r.reservation_date as message,
        r.created_at as created_date,
        'unread' as status,
        'medium' as priority
    FROM reservations r 
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC");
    $stmt->execute();
    $reservation_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications = array_merge($notifications, $reservation_notifications);
} catch (Exception $e) {
    error_log("Reservation notifications error: " . $e->getMessage());
}

// Pending reviews notifications
try {
    $stmt = $db->prepare("SELECT 
        r.id,
        'review' as type,
        'New Review' as title,
        'Review from ' || r.customer_name || ' - ' || r.rating || ' stars' as message,
        r.created_at as created_date,
        'unread' as status,
        'low' as priority
    FROM reviews r 
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC");
    $stmt->execute();
    $review_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications = array_merge($notifications, $review_notifications);
} catch (Exception $e) {
    error_log("Review notifications error: " . $e->getMessage());
}

// Low stock notifications (if you have inventory system)
try {
    $stmt = $db->prepare("SELECT 
        mi.id,
        'inventory' as type,
        'Low Stock Alert' as title,
        mi.name || ' is running low on stock' as message,
        NOW() as created_date,
        'unread' as status,
        'high' as priority
    FROM menu_items mi 
    WHERE mi.is_available = 1 
    AND mi.stock_quantity < 10
    ORDER BY mi.stock_quantity ASC");
    $stmt->execute();
    $inventory_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications = array_merge($notifications, $inventory_notifications);
} catch (Exception $e) {
    // Ignore if stock_quantity column doesn't exist
}

// Apply filters
$filtered_notifications = array_filter($notifications, function($notification) use ($type_filter, $status_filter, $date_from, $date_to) {
    // Type filter
    if ($type_filter !== 'all' && $notification['type'] !== $type_filter) {
        return false;
    }
    
    // Status filter
    if ($status_filter !== 'all' && $notification['status'] !== $status_filter) {
        return false;
    }
    
    // Date filter
    if ($date_from && strtotime($notification['created_date']) < strtotime($date_from)) {
        return false;
    }
    
    if ($date_to && strtotime($notification['created_date']) > strtotime($date_to . ' 23:59:59')) {
        return false;
    }
    
    return true;
});

// Sort by creation date (newest first)
usort($filtered_notifications, function($a, $b) {
    return strtotime($b['created_date']) - strtotime($a['created_date']);
});

// Get statistics
$total_notifications = count($filtered_notifications);
$unread_count = count(array_filter($filtered_notifications, function($n) { return $n['status'] === 'unread'; }));
$high_priority_count = count(array_filter($filtered_notifications, function($n) { return $n['priority'] === 'high'; }));

// Get counts by type
$order_count = count(array_filter($filtered_notifications, function($n) { return $n['type'] === 'order'; }));
$reservation_count = count(array_filter($filtered_notifications, function($n) { return $n['type'] === 'reservation'; }));
$review_count = count(array_filter($filtered_notifications, function($n) { return $n['type'] === 'review'; }));
$inventory_count = count(array_filter($filtered_notifications, function($n) { return $n['type'] === 'inventory'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BrewHaven Cafe</title>
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

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Statistics Cards */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border-left: 4px solid #6f4e37;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #6f4e37;
            margin-bottom: 15px;
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

        /* Notifications List */
        .notifications-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #f0f7ff;
            border-left: 4px solid #007bff;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }

        .notification-icon.order { background: #28a745; }
        .notification-icon.reservation { background: #17a2b8; }
        .notification-icon.review { background: #ffc107; }
        .notification-icon.inventory { background: #dc3545; }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: 600;
            color: #495057;
            font-size: 16px;
        }

        .notification-time {
            color: #6c757d;
            font-size: 12px;
        }

        .notification-message {
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-low { background: #d1ecf1; color: #0c5460; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-unread { background: #d1ecf1; color: #0c5460; }
        .status-read { background: #e2e3e5; color: #383d41; }

        .no-notifications {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-notifications i {
            font-size: 48px;
            color: #e9ecef;
            margin-bottom: 20px;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bulk-actions-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .select-all {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Type Statistics */
        .type-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .type-stat {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .type-stat.order { border-top: 3px solid #28a745; }
        .type-stat.reservation { border-top: 3px solid #17a2b8; }
        .type-stat.review { border-top: 3px solid #ffc107; }
        .type-stat.inventory { border-top: 3px solid #dc3545; }

        .type-count {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .type-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            
            .notification-item {
                flex-direction: column;
                gap: 10px;
            }
            
            .notification-header {
                flex-direction: column;
                gap: 5px;
            }
            
            .bulk-actions {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-bell"></i> Notifications
            </h1>
            <div class="header-actions">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn btn-success">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all notifications?')">
                    <button type="submit" name="clear_all" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </form>
                <a href="admin_dashboard.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                $successMessages = [
                    'all_marked_read' => 'All notifications marked as read!',
                    'all_cleared' => 'All notifications cleared!'
                ];
                echo $successMessages[$_GET['success']] ?? 'Operation completed successfully!';
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                $errorMessages = [
                    'mark_read_failed' => 'Failed to mark notifications as read.',
                    'clear_failed' => 'Failed to clear notifications.'
                ];
                echo $errorMessages[$_GET['error']] ?? 'An error occurred.';
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-bell"></i></div>
                <div class="stat-number"><?php echo $total_notifications; ?></div>
                <div class="stat-label">Total Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-number"><?php echo $unread_count; ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo $high_priority_count; ?></div>
                <div class="stat-label">High Priority</div>
            </div>
        </div>

        <!-- Type Statistics -->
        <div class="type-stats">
            <div class="type-stat order">
                <div class="type-count"><?php echo $order_count; ?></div>
                <div class="type-label">Orders</div>
            </div>
            <div class="type-stat reservation">
                <div class="type-count"><?php echo $reservation_count; ?></div>
                <div class="type-label">Reservations</div>
            </div>
            <div class="type-stat review">
                <div class="type-count"><?php echo $review_count; ?></div>
                <div class="type-label">Reviews</div>
            </div>
            <div class="type-stat inventory">
                <div class="type-count"><?php echo $inventory_count; ?></div>
                <div class="type-label">Inventory</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="admin_notifications.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="type">Type</label>
                        <select id="type" name="type">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="order" <?php echo $type_filter === 'order' ? 'selected' : ''; ?>>Orders</option>
                            <option value="reservation" <?php echo $type_filter === 'reservation' ? 'selected' : ''; ?>>Reservations</option>
                            <option value="review" <?php echo $type_filter === 'review' ? 'selected' : ''; ?>>Reviews</option>
                            <option value="inventory" <?php echo $type_filter === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                            <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="admin_notifications.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Notifications List -->
        <div class="notifications-container">
            <?php if (empty($filtered_notifications)): ?>
                <div class="no-notifications">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! There are no notifications matching your current filters.</p>
                    <a href="admin_notifications.php" class="btn">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="bulk-actions">
                    <div class="bulk-actions-left">
                        <div class="select-all">
                            <input type="checkbox" id="selectAll">
                            <label for="selectAll">Select All</label>
                        </div>
                        <span><?php echo $total_notifications; ?> notification(s) found</span>
                    </div>
                    <div class="bulk-actions-right">
                        <button class="btn btn-small btn-success" onclick="markSelectedRead()">
                            <i class="fas fa-check"></i> Mark Selected as Read
                        </button>
                    </div>
                </div>

                <?php foreach ($filtered_notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['status'] === 'unread' ? 'unread' : ''; ?>" 
                         onclick="handleNotificationClick('<?php echo $notification['type']; ?>', <?php echo $notification['id']; ?>)">
                        <div class="notification-icon <?php echo $notification['type']; ?>">
                            <?php
                            $icons = [
                                'order' => 'fas fa-shopping-cart',
                                'reservation' => 'fas fa-calendar',
                                'review' => 'fas fa-star',
                                'inventory' => 'fas fa-box'
                            ];
                            echo '<i class="' . ($icons[$notification['type']] ?? 'fas fa-bell') . '"></i>';
                            ?>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-header">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                    <span class="priority-badge priority-<?php echo $notification['priority']; ?>">
                                        <?php echo $notification['priority']; ?> priority
                                    </span>
                                </div>
                                <div class="notification-time">
                                    <?php echo date('M j, g:i A', strtotime($notification['created_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            
                            <div class="notification-actions">
                                <span class="status-badge status-<?php echo $notification['status']; ?>">
                                    <?php echo ucfirst($notification['status']); ?>
                                </span>
                                <button class="btn btn-small" onclick="event.stopPropagation(); markAsRead(<?php echo $notification['id']; ?>, '<?php echo $notification['type']; ?>')">
                                    <i class="fas fa-check"></i> Mark Read
                                </button>
                                <?php if ($notification['type'] === 'order'): ?>
                                    <a href="admin_orders.php?action=view&id=<?php echo $notification['id']; ?>" class="btn btn-small btn-info" onclick="event.stopPropagation();">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php elseif ($notification['type'] === 'reservation'): ?>
                                    <a href="admin_reservations.php?action=view&id=<?php echo $notification['id']; ?>" class="btn btn-small btn-info" onclick="event.stopPropagation();">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php elseif ($notification['type'] === 'review'): ?>
                                    <a href="admin_review.php?action=view&id=<?php echo $notification['id']; ?>" class="btn btn-small btn-info" onclick="event.stopPropagation();">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Handle notification click
        function handleNotificationClick(type, id) {
            switch(type) {
                case 'order':
                    window.location.href = 'admin_orders.php?action=view&id=' + id;
                    break;
                case 'reservation':
                    window.location.href = 'admin_reservations.php?action=view&id=' + id;
                    break;
                case 'review':
                    window.location.href = 'admin_review.php?action=view&id=' + id;
                    break;
                case 'inventory':
                    window.location.href = 'admin_menu.php?action=edit&id=' + id;
                    break;
            }
        }

        // Mark as read
        function markAsRead(id, type) {
            // In a real application, you'd make an AJAX call here
            // For now, just reload the page
            window.location.reload();
        }

        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.notification-item input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Mark selected as read
        function markSelectedRead() {
            const selected = document.querySelectorAll('.notification-item input[type="checkbox"]:checked');
            if (selected.length === 0) {
                alert('Please select at least one notification.');
                return;
            }
            
            if (confirm('Mark ' + selected.length + ' notification(s) as read?')) {
                // In a real application, you'd make an AJAX call here
                window.location.reload();
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            // In a real application, you'd check for new notifications via AJAX
        }, 30000);
    </script>
</body>
</html>