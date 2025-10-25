<?php
// admin_header.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

// Function to get user initials for avatar
function getUserInitials($name) {
    $names = explode(' ', $name);
    $initials = '';
    
    foreach ($names as $n) {
        if (!empty(trim($n))) {
            $initials .= strtoupper(substr(trim($n), 0, 1));
        }
    }
    
    return substr($initials, 0, 2);
}

// Function to get pending orders count
function getPendingOrdersCount($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Pending orders count error: " . $e->getMessage());
        return 0;
    }
}

// Function to get pending reservations count
function getPendingReservationsCount($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Pending reservations count error: " . $e->getMessage());
        return 0;
    }
}

// Function to get low stock items count
function getLowStockCount($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM menu_items WHERE stock_quantity > 0 AND stock_quantity <= stock_alert_level");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        // Table might not have stock columns, return 0
        return 0;
    }
}

// Get notification data
$database = new Database();
$db = $database->getConnection();

$pending_orders_count = getPendingOrdersCount($db);
$pending_reservations_count = getPendingReservationsCount($db);
$low_stock_count = getLowStockCount($db);
$user_initials = getUserInitials($_SESSION['owner_name']);

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Header Styles */
        .admin-header {
            background: linear-gradient(135deg, #6f4e37 0%, #8b6b4d 100%);
            color: white;
            padding: 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .brand-text h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .brand-text p {
            font-size: 14px;
            opacity: 0.9;
            margin: 2px 0 0 0;
            color: #f0f0f0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .user-details:hover {
            background: rgba(255,255,255,0.2);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
        }

        .user-text {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
            text-transform: capitalize;
        }

        .user-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-badge.admin {
            background: linear-gradient(45deg, #ff9ff3, #f368e0);
        }

        .user-badge.manager {
            background: linear-gradient(45deg, #54a0ff, #2e86de);
        }

        .user-badge.staff {
            background: linear-gradient(45deg, #1dd1a1, #10ac84);
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-left: 20px;
        }

        .quick-action-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .quick-action-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }

        /* Time Display */
        .time-display {
            font-size: 12px;
            opacity: 0.8;
            margin-right: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Mobile Menu */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
        }

        /* Admin Navigation Styles */
        .admin-nav {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-bottom: 3px solid #6f4e37;
            position: sticky;
            top: 80px;
            z-index: 999;
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 0;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 25px;
            color: #555;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
        }

        .nav-links a:hover {
            color: #6f4e37;
            background: #f8f5f0;
        }

        .nav-links a.active {
            color: #6f4e37;
            border-bottom-color: #6f4e37;
            background: linear-gradient(to bottom, #f8f5f0, #fff);
        }

        .nav-links a i {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .nav-links a .nav-text {
            font-size: 14px;
        }

        .nav-links a .badge {
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }

        .nav-links a .badge.warning {
            background: #ffa502;
        }

        .nav-links a .badge.info {
            background: #2ed573;
        }

        /* Dropdown Menu */
        .nav-dropdown {
            position: relative;
        }

        .nav-dropdown > a::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 10px;
            margin-left: 5px;
            transition: transform 0.3s ease;
        }

        .nav-dropdown:hover > a::after {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            display: none;
            z-index: 1000;
            padding: 8px 0;
        }

        .nav-dropdown:hover .dropdown-menu {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-menu a {
            padding: 12px 20px;
            border-bottom: none;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .dropdown-menu a:hover {
            background: #f8f5f0;
            color: #6f4e37;
        }

        .dropdown-menu a i {
            width: 16px;
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .nav-links a {
                padding: 15px 20px;
                font-size: 13px;
            }
            
            .nav-links a .nav-text {
                font-size: 13px;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .header-content {
                flex-wrap: wrap;
                gap: 15px;
                padding: 10px 20px;
            }
            
            .quick-actions {
                display: none;
            }
            
            .nav-content {
                padding: 0 20px;
            }
            
            .nav-links {
                flex-direction: column;
                display: none;
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .nav-links a {
                padding: 15px 20px;
                border-bottom: 1px solid #f0f0f0;
                border-left: 3px solid transparent;
            }
            
            .nav-links a.active {
                border-left-color: #6f4e37;
                border-bottom-color: #f0f0f0;
            }
            
            .dropdown-menu {
                position: static;
                box-shadow: none;
                border: none;
                background: #f8f9fa;
                border-radius: 0;
                padding: 0;
            }
            
            .dropdown-menu a {
                padding-left: 40px;
            }
        }

        /* Notification Styles */
        .notification-container {
            position: relative;
            margin-right: 15px;
        }

        .notification-bell {
            position: relative;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.2);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 2px solid #6f4e37;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .notification-count {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="header-left">
                <div class="logo-container">
                    <div class="logo">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <div class="brand-text">
                        <h1>BrewHaven Admin</h1>
                        <p>Management Dashboard</p>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="admin_dashboard.php" class="quick-action-btn">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="admin_orders.php" class="quick-action-btn">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
                <a href="admin_menu.php?action=add" class="quick-action-btn">
                    <i class="fas fa-plus"></i> Add Item
                </a>
            </div>

            <div class="user-info">
                <div class="time-display">
                    <i class="fas fa-clock"></i>
                    <span id="currentTime"><?php echo date('h:i A'); ?></span>
                </div>

                <div class="user-details">
                    <div class="user-avatar">
                        <?php echo $user_initials; ?>
                    </div>
                    <div class="user-text">
                        <div class="user-name"><?php echo $_SESSION['owner_name']; ?></div>
                        <div class="user-role"><?php echo $_SESSION['owner_role']; ?></div>
                    </div>
                    <span class="user-badge <?php echo $_SESSION['owner_role']; ?>">
                        <?php echo $_SESSION['owner_role']; ?>
                    </span>
                </div>

                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <nav class="admin-nav">
        <div class="nav-content">
            <ul class="nav-links" id="navLinks">
                <!-- Dashboard -->
                <li>
                    <a href="admin_dashboard.php" class="<?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <!-- Orders -->
                <li class="nav-dropdown">
                    <a href="admin_orders.php" class="<?php echo in_array($current_page, ['admin_orders.php', 'admin_order_details.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="nav-text">Orders</span>
                        <?php if ($pending_orders_count > 0): ?>
                            <span class="badge"><?php echo $pending_orders_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu">
                        <a href="admin_orders.php">
                            <i class="fas fa-list"></i> All Orders
                        </a>
                        <a href="admin_orders.php?status=pending">
                            <i class="fas fa-clock"></i> Pending Orders
                            <?php if ($pending_orders_count > 0): ?>
                                <span class="badge" style="margin-left: auto;"><?php echo $pending_orders_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="admin_orders.php?status=completed">
                            <i class="fas fa-check-circle"></i> Completed Orders
                        </a>
                        <a href="admin_orders.php?status=cancelled">
                            <i class="fas fa-times-circle"></i> Cancelled Orders
                        </a>
                    </div>
                </li>

                <!-- Menu Management -->
                <li class="nav-dropdown">
                    <a href="admin_menu.php" class="<?php echo in_array($current_page, ['admin_menu.php', 'admin_menu_edit.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-utensils"></i>
                        <span class="nav-text">Menu</span>
                        <?php if ($low_stock_count > 0): ?>
                            <span class="badge warning"><?php echo $low_stock_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu">
                        <a href="admin_menu.php">
                            <i class="fas fa-list"></i> All Items
                        </a>
                        <a href="admin_menu.php?action=add">
                            <i class="fas fa-plus"></i> Add New Item
                        </a>
                        <a href="admin_menu.php?category=coffee">
                            <i class="fas fa-coffee"></i> Coffee Items
                        </a>
                        <a href="admin_menu.php?category=tea">
                            <i class="fas fa-mug-hot"></i> Tea Items
                        </a>
                        <a href="admin_menu.php?category=pastry">
                            <i class="fas fa-cookie"></i> Pastries
                        </a>
                        <?php if ($low_stock_count > 0): ?>
                            <a href="admin_menu.php?stock=low">
                                <i class="fas fa-exclamation-triangle"></i> Low Stock
                                <span class="badge warning" style="margin-left: auto;"><?php echo $low_stock_count; ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </li>

                <!-- Customers -->
                <li>
                    <a href="admin_customers.php" class="<?php echo $current_page === 'admin_customers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Customers</span>
                    </a>
                </li>

                <!-- Reservations -->
                <li class="nav-dropdown">
                    <a href="admin_reservations.php" class="<?php echo in_array($current_page, ['admin_reservations.php', 'admin_reservation_details.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i>
                        <span class="nav-text">Reservations</span>
                        <?php if ($pending_reservations_count > 0): ?>
                            <span class="badge"><?php echo $pending_reservations_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu">
                        <a href="admin_reservations.php">
                            <i class="fas fa-list"></i> All Reservations
                        </a>
                        <a href="admin_reservations.php?status=pending">
                            <i class="fas fa-clock"></i> Pending
                            <?php if ($pending_reservations_count > 0): ?>
                                <span class="badge" style="margin-left: auto;"><?php echo $pending_reservations_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="admin_reservations.php?status=confirmed">
                            <i class="fas fa-check"></i> Confirmed
                        </a>
                        <a href="admin_reservations.php?action=add">
                            <i class="fas fa-plus"></i> Add Reservation
                        </a>
                        <a href="admin_reservations.php?date=<?php echo date('Y-m-d'); ?>">
                            <i class="fas fa-calendar-day"></i> Today's Bookings
                        </a>
                    </div>
                </li>

                <!-- Reviews -->
                <li>
                    <a href="admin_review.php" class="<?php echo $current_page === 'admin_review.php' ? 'active' : ''; ?>">
                        <i class="fas fa-star"></i>
                        <span class="nav-text">Reviews</span>
                    </a>
                </li>

                <!-- Reports & Analytics -->
                <?php if (hasPermission('manager')): ?>
                <li class="nav-dropdown">
                    <a href="admin_reports.php" class="<?php echo in_array($current_page, ['admin_reports.php', 'admin_analytics.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="admin_reports.php">
                            <i class="fas fa-chart-line"></i> Sales Reports
                        </a>
                        <a href="admin_reports.php?type=customer">
                            <i class="fas fa-users"></i> Customer Reports
                        </a>
                        <a href="admin_reports.php?type=inventory">
                            <i class="fas fa-boxes"></i> Inventory Reports
                        </a>
                        <a href="admin_analytics.php">
                            <i class="fas fa-chart-pie"></i> Analytics
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Staff Management -->
                <?php if (hasPermission('admin')): ?>
                <li class="nav-dropdown">
                    <a href="admin_staff.php" class="<?php echo in_array($current_page, ['admin_staff.php', 'admin_staff_add.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield"></i>
                        <span class="nav-text">Staff</span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="admin_staff.php">
                            <i class="fas fa-list"></i> All Staff
                        </a>
                        <a href="admin_staff.php?action=add">
                            <i class="fas fa-user-plus"></i> Add Staff
                        </a>
                        <a href="admin_staff.php?role=manager">
                            <i class="fas fa-user-tie"></i> Managers
                        </a>
                        <a href="admin_staff.php?role=staff">
                            <i class="fas fa-user"></i> Staff Members
                        </a>
                        <a href="admin_staff_schedule.php">
                            <i class="fas fa-calendar-alt"></i> Schedules
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Settings -->
                <?php if (hasPermission('admin')): ?>
                <li class="nav-dropdown">
                    <a href="admin_settings.php" class="<?php echo in_array($current_page, ['admin_settings.php', 'admin_settings_general.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="admin_settings_general.php">
                            <i class="fas fa-sliders-h"></i> General Settings
                        </a>
                        <a href="admin_settings_business.php">
                            <i class="fas fa-building"></i> Business Info
                        </a>
                        <a href="admin_settings_notifications.php">
                            <i class="fas fa-bell"></i> Notifications
                        </a>
                        <a href="admin_settings_backup.php">
                            <i class="fas fa-database"></i> Backup & Restore
                        </a>
                    </div>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
            this.innerHTML = navLinks.classList.contains('active') ? 
                '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });

        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Update time immediately and then every minute
        updateTime();
        setInterval(updateTime, 60000);

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const navLinks = document.getElementById('navLinks');
            const mobileToggle = document.getElementById('mobileMenuToggle');
            
            if (!navLinks.contains(e.target) && !mobileToggle.contains(e.target) && navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });

        // Auto-refresh notification counts every 30 seconds
        setInterval(() => {
            // In a real application, you'd make an AJAX call here
            // to update notification counts without refreshing the page
        }, 30000);
    </script>
</body>
</html>