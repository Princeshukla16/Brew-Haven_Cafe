<?php
// admin_settings.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

// Check if user has admin permissions
if (!hasPermission('admin')) {
    header('Location: admin_dashboard.php?error=access_denied');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Initialize message variables
$message = '';
$message_type = '';

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['update_general_settings'])) {
            // Update general settings
            $business_name = trim($_POST['business_name']);
            $business_email = trim($_POST['business_email']);
            $business_phone = trim($_POST['business_phone']);
            $business_address = trim($_POST['business_address']);
            $currency = trim($_POST['currency']);
            $timezone = trim($_POST['timezone']);
            $date_format = trim($_POST['date_format']);
            
            // Update settings in database
            $settings = [
                'business_name' => $business_name,
                'business_email' => $business_email,
                'business_phone' => $business_phone,
                'business_address' => $business_address,
                'currency' => $currency,
                'timezone' => $timezone,
                'date_format' => $date_format
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = 'General settings updated successfully!';
            $message_type = 'success';
            
        } elseif (isset($_POST['update_business_hours'])) {
            // Update business hours
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($days as $day) {
                $open_time = $_POST[$day . '_open'] ?? '';
                $close_time = $_POST[$day . '_close'] ?? '';
                $is_closed = isset($_POST[$day . '_closed']) ? 1 : 0;
                
                $hours_data = json_encode([
                    'open' => $open_time,
                    'close' => $close_time,
                    'closed' => $is_closed
                ]);
                
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute(["hours_$day", $hours_data, $hours_data]);
            }
            
            $message = 'Business hours updated successfully!';
            $message_type = 'success';
            
        } elseif (isset($_POST['update_notification_settings'])) {
            // Update notification settings
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            $new_order_alerts = isset($_POST['new_order_alerts']) ? 1 : 0;
            $new_reservation_alerts = isset($_POST['new_reservation_alerts']) ? 1 : 0;
            $low_stock_alerts = isset($_POST['low_stock_alerts']) ? 1 : 0;
            
            $notification_settings = [
                'email_notifications' => $email_notifications,
                'sms_notifications' => $sms_notifications,
                'new_order_alerts' => $new_order_alerts,
                'new_reservation_alerts' => $new_reservation_alerts,
                'low_stock_alerts' => $low_stock_alerts
            ];
            
            foreach ($notification_settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = 'Notification settings updated successfully!';
            $message_type = 'success';
            
        } elseif (isset($_POST['update_payment_settings'])) {
            // Update payment settings
            $cash_payment = isset($_POST['cash_payment']) ? 1 : 0;
            $card_payment = isset($_POST['card_payment']) ? 1 : 0;
            $online_payment = isset($_POST['online_payment']) ? 1 : 0;
            $tax_rate = floatval($_POST['tax_rate']);
            $service_charge = floatval($_POST['service_charge']);
            
            $payment_settings = [
                'cash_payment' => $cash_payment,
                'card_payment' => $card_payment,
                'online_payment' => $online_payment,
                'tax_rate' => $tax_rate,
                'service_charge' => $service_charge
            ];
            
            foreach ($payment_settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = 'Payment settings updated successfully!';
            $message_type = 'success';
            
        } elseif (isset($_POST['create_backup'])) {
            // Create database backup
            $backup_result = createDatabaseBackup($db);
            if ($backup_result) {
                $message = 'Database backup created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to create database backup.';
                $message_type = 'error';
            }
        }
        
    } catch (Exception $e) {
        error_log("Settings update error: " . $e->getMessage());
        $message = 'Error updating settings. Please try again.';
        $message_type = 'error';
    }
}

// Function to get setting value
function getSetting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Function to create database backup
function createDatabaseBackup($db) {
    try {
        $backup_dir = __DIR__ . '/backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Get all tables
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $backup_content = "-- BrewHaven Cafe Database Backup\n";
        $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            // Add table structure
            $backup_content .= "--\n-- Table structure for table `$table`\n--\n";
            $create_table = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $backup_content .= $create_table['Create Table'] . ";\n\n";
            
            // Add table data
            $backup_content .= "--\n-- Dumping data for table `$table`\n--\n";
            
            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $backup_content .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES \n";
                
                $values = [];
                foreach ($rows as $row) {
                    $row_values = array_map(function($value) use ($db) {
                        if ($value === null) return 'NULL';
                        return $db->quote($value);
                    }, $row);
                    $values[] = "(" . implode(', ', $row_values) . ")";
                }
                
                $backup_content .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        return file_put_contents($backup_file, $backup_content) !== false;
        
    } catch (Exception $e) {
        error_log("Backup creation error: " . $e->getMessage());
        return false;
    }
}

// Get current settings
$business_name = getSetting($db, 'business_name', 'BrewHaven Cafe');
$business_email = getSetting($db, 'business_email', 'info@brewhaven.com');
$business_phone = getSetting($db, 'business_phone', '+1 (555) 123-4567');
$business_address = getSetting($db, 'business_address', '123 Coffee Street, City, State 12345');
$currency = getSetting($db, 'currency', 'USD');
$timezone = getSetting($db, 'timezone', 'America/New_York');
$date_format = getSetting($db, 'date_format', 'Y-m-d');

// Get business hours
$business_hours = [];
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
foreach ($days as $day) {
    $hours_data = getSetting($db, "hours_$day", '{"open":"09:00","close":"17:00","closed":0}');
    $business_hours[$day] = json_decode($hours_data, true);
}

// Get notification settings
$email_notifications = getSetting($db, 'email_notifications', 1);
$sms_notifications = getSetting($db, 'sms_notifications', 0);
$new_order_alerts = getSetting($db, 'new_order_alerts', 1);
$new_reservation_alerts = getSetting($db, 'new_reservation_alerts', 1);
$low_stock_alerts = getSetting($db, 'low_stock_alerts', 1);

// Get payment settings
$cash_payment = getSetting($db, 'cash_payment', 1);
$card_payment = getSetting($db, 'card_payment', 1);
$online_payment = getSetting($db, 'online_payment', 0);
$tax_rate = getSetting($db, 'tax_rate', 8.5);
$service_charge = getSetting($db, 'service_charge', 0);

// Available time zones
$timezones = [
    'America/New_York' => 'Eastern Time (ET)',
    'America/Chicago' => 'Central Time (CT)',
    'America/Denver' => 'Mountain Time (MT)',
    'America/Los_Angeles' => 'Pacific Time (PT)',
    'America/Anchorage' => 'Alaska Time (AKT)',
    'Pacific/Honolulu' => 'Hawaii Time (HT)',
    'UTC' => 'Coordinated Universal Time (UTC)'
];

// Available currencies
$currencies = [
    'USD' => 'US Dollar ($)',
    'EUR' => 'Euro (€)',
    'GBP' => 'British Pound (£)',
    'CAD' => 'Canadian Dollar (C$)',
    'AUD' => 'Australian Dollar (A$)',
    'JPY' => 'Japanese Yen (¥)',
    'INR' => 'Indian Rupee (₹)'
];

// Available date formats
$date_formats = [
    'Y-m-d' => 'YYYY-MM-DD (2023-12-31)',
    'm/d/Y' => 'MM/DD/YYYY (12/31/2023)',
    'd/m/Y' => 'DD/MM/YYYY (31/12/2023)',
    'F j, Y' => 'Month Day, Year (December 31, 2023)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .settings-header {
            display: flex;
            justify-content: between;
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

        .settings-tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .settings-tab {
            padding: 15px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .settings-tab.active {
            color: #6f4e37;
            border-bottom-color: #6f4e37;
        }

        .settings-tab:hover {
            color: #6f4e37;
            background: #f8f5f0;
        }

        .settings-content {
            display: none;
        }

        .settings-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .settings-card h3 {
            color: #6f4e37;
            margin-bottom: 20px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #6f4e37;
            box-shadow: 0 0 0 3px rgba(111, 78, 55, 0.1);
        }

        .form-text {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 12px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 500;
        }

        .business-hours-grid {
            display: grid;
            gap: 15px;
        }

        .business-hour-row {
            display: grid;
            grid-template-columns: 120px 1fr 1fr 80px;
            gap: 15px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .business-hour-row:last-child {
            border-bottom: none;
        }

        .day-label {
            font-weight: 600;
            color: #495057;
            text-transform: capitalize;
        }

        .time-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .closed-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            padding: 12px 25px;
            background: #6f4e37;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: #5a3e2c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(111, 78, 55, 0.3);
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

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

        .backup-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .backup-info h4 {
            color: #0066cc;
            margin-bottom: 10px;
        }

        .backup-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .backup-info li {
            margin-bottom: 5px;
            color: #666;
        }

        .system-info {
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
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card .value {
            font-size: 18px;
            font-weight: bold;
            color: #6f4e37;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #6f4e37;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .business-hour-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .settings-tabs {
                flex-wrap: wrap;
            }
            
            .settings-tab {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }
            
            .system-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="settings-container">
            <div class="settings-header">
                <h1 class="page-title">
                    <i class="fas fa-cog"></i> System Settings
                </h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="settings-tabs">
                <button class="settings-tab active" onclick="switchTab('general')">
                    <i class="fas fa-sliders-h"></i> General
                </button>
                <button class="settings-tab" onclick="switchTab('business')">
                    <i class="fas fa-clock"></i> Business Hours
                </button>
                <button class="settings-tab" onclick="switchTab('notifications')">
                    <i class="fas fa-bell"></i> Notifications
                </button>
                <button class="settings-tab" onclick="switchTab('payment')">
                    <i class="fas fa-credit-card"></i> Payment
                </button>
                <button class="settings-tab" onclick="switchTab('backup')">
                    <i class="fas fa-database"></i> Backup
                </button>
                <button class="settings-tab" onclick="switchTab('system')">
                    <i class="fas fa-info-circle"></i> System Info
                </button>
            </div>

            <!-- General Settings Tab -->
            <div id="general-tab" class="settings-content active">
                <form method="POST">
                    <input type="hidden" name="update_general_settings" value="1">
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-building"></i> Business Information</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="business_name">Business Name *</label>
                                <input type="text" id="business_name" name="business_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($business_name); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="business_email">Business Email *</label>
                                <input type="email" id="business_email" name="business_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($business_email); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="business_phone">Business Phone</label>
                                <input type="tel" id="business_phone" name="business_phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($business_phone); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select id="currency" name="currency" class="form-control" required>
                                    <?php foreach ($currencies as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo $currency === $code ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="business_address">Business Address</label>
                            <textarea id="business_address" name="business_address" class="form-control" rows="3"><?php echo htmlspecialchars($business_address); ?></textarea>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h3><i class="fas fa-globe"></i> Regional Settings</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="timezone">Time Zone</label>
                                <select id="timezone" name="timezone" class="form-control" required>
                                    <?php foreach ($timezones as $tz => $name): ?>
                                        <option value="<?php echo $tz; ?>" <?php echo $timezone === $tz ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="form-text">This affects all date and time displays in the system</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_format">Date Format</label>
                                <select id="date_format" name="date_format" class="form-control" required>
                                    <?php foreach ($date_formats as $format => $description): ?>
                                        <option value="<?php echo $format; ?>" <?php echo $date_format === $format ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($description); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save General Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Business Hours Tab -->
            <div id="business-tab" class="settings-content">
                <form method="POST">
                    <input type="hidden" name="update_business_hours" value="1">
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-clock"></i> Business Hours</h3>
                        <p>Set your business operating hours for each day of the week.</p>
                        
                        <div class="business-hours-grid">
                            <?php foreach ($days as $day): 
                                $hours = $business_hours[$day];
                            ?>
                            <div class="business-hour-row">
                                <div class="day-label"><?php echo ucfirst($day); ?></div>
                                
                                <div class="time-input-group">
                                    <label>Open:</label>
                                    <input type="time" name="<?php echo $day; ?>_open" class="form-control" 
                                           value="<?php echo htmlspecialchars($hours['open']); ?>"
                                           <?php echo $hours['closed'] ? 'disabled' : ''; ?>>
                                </div>
                                
                                <div class="time-input-group">
                                    <label>Close:</label>
                                    <input type="time" name="<?php echo $day; ?>_close" class="form-control" 
                                           value="<?php echo htmlspecialchars($hours['close']); ?>"
                                           <?php echo $hours['closed'] ? 'disabled' : ''; ?>>
                                </div>
                                
                                <div class="closed-checkbox">
                                    <input type="checkbox" id="<?php echo $day; ?>_closed" name="<?php echo $day; ?>_closed" 
                                           value="1" <?php echo $hours['closed'] ? 'checked' : ''; ?> 
                                           onchange="toggleDayHours('<?php echo $day; ?>')">
                                    <label for="<?php echo $day; ?>_closed">Closed</label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Business Hours
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications-tab" class="settings-content">
                <form method="POST">
                    <input type="hidden" name="update_notification_settings" value="1">
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                        
                        <div class="form-group">
                            <h4>Notification Methods</h4>
                            <div class="checkbox-group">
                                <input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php echo $email_notifications ? 'checked' : ''; ?>>
                                <label for="email_notifications">Email Notifications</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="sms_notifications" name="sms_notifications" value="1" <?php echo $sms_notifications ? 'checked' : ''; ?>>
                                <label for="sms_notifications">SMS Notifications</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <h4>Alert Types</h4>
                            <div class="checkbox-group">
                                <input type="checkbox" id="new_order_alerts" name="new_order_alerts" value="1" <?php echo $new_order_alerts ? 'checked' : ''; ?>>
                                <label for="new_order_alerts">New Order Alerts</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="new_reservation_alerts" name="new_reservation_alerts" value="1" <?php echo $new_reservation_alerts ? 'checked' : ''; ?>>
                                <label for="new_reservation_alerts">New Reservation Alerts</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="low_stock_alerts" name="low_stock_alerts" value="1" <?php echo $low_stock_alerts ? 'checked' : ''; ?>>
                                <label for="low_stock_alerts">Low Stock Alerts</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Notification Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Payment Settings Tab -->
            <div id="payment-tab" class="settings-content">
                <form method="POST">
                    <input type="hidden" name="update_payment_settings" value="1">
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="cash_payment" name="cash_payment" value="1" <?php echo $cash_payment ? 'checked' : ''; ?>>
                                <label for="cash_payment">Cash Payments</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="card_payment" name="card_payment" value="1" <?php echo $card_payment ? 'checked' : ''; ?>>
                                <label for="card_payment">Card Payments</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="online_payment" name="online_payment" value="1" <?php echo $online_payment ? 'checked' : ''; ?>>
                                <label for="online_payment">Online Payments</label>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h3><i class="fas fa-percentage"></i> Fees & Taxes</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="tax_rate">Tax Rate (%)</label>
                                <input type="number" id="tax_rate" name="tax_rate" class="form-control" 
                                       value="<?php echo htmlspecialchars($tax_rate); ?>" step="0.1" min="0" max="50">
                                <span class="form-text">Sales tax rate applied to all orders</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="service_charge">Service Charge (%)</label>
                                <input type="number" id="service_charge" name="service_charge" class="form-control" 
                                       value="<?php echo htmlspecialchars($service_charge); ?>" step="0.1" min="0" max="20">
                                <span class="form-text">Optional service charge</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Payment Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Backup Tab -->
            <div id="backup-tab" class="settings-content">
                <div class="settings-card">
                    <h3><i class="fas fa-database"></i> Database Backup</h3>
                    
                    <div class="backup-info">
                        <h4><i class="fas fa-info-circle"></i> Backup Information</h4>
                        <p>Create a complete backup of your database including:</p>
                        <ul>
                            <li>All customer data and orders</li>
                            <li>Menu items and inventory</li>
                            <li>Reservations and reviews</li>
                            <li>System settings and staff accounts</li>
                        </ul>
                        <p><strong>Backup files are stored in the /backups/ directory</strong></p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="create_backup" value="1">
                        <div class="form-actions">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-download"></i> Create Database Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- System Info Tab -->
            <div id="system-tab" class="settings-content">
                <div class="settings-card">
                    <h3><i class="fas fa-info-circle"></i> System Information</h3>
                    
                    <div class="system-info">
                        <div class="info-card">
                            <h4>PHP Version</h4>
                            <div class="value"><?php echo phpversion(); ?></div>
                        </div>
                        
                        <div class="info-card">
                            <h4>Database Version</h4>
                            <div class="value">MySQL</div>
                        </div>
                        
                        <div class="info-card">
                            <h4>Server Software</h4>
                            <div class="value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                        </div>
                        
                        <div class="info-card">
                            <h4>System Uptime</h4>
                            <div class="value"><?php echo date('Y-m-d H:i:s'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tabs and contents
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.settings-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show selected tab
            document.querySelector(`.settings-tab:nth-child(${getTabIndex(tabName)})`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        function getTabIndex(tabName) {
            const tabs = {
                'general': 1,
                'business': 2,
                'notifications': 3,
                'payment': 4,
                'backup': 5,
                'system': 6
            };
            return tabs[tabName] || 1;
        }

        // Toggle business hours inputs
        function toggleDayHours(day) {
            const openInput = document.querySelector(`input[name="${day}_open"]`);
            const closeInput = document.querySelector(`input[name="${day}_close"]`);
            const checkbox = document.querySelector(`input[name="${day}_closed"]`);
            
            if (checkbox.checked) {
                openInput.disabled = true;
                closeInput.disabled = true;
            } else {
                openInput.disabled = false;
                closeInput.disabled = false;
            }
        }

        // Initialize business hours toggles
        document.addEventListener('DOMContentLoaded', function() {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                toggleDayHours(day);
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#dc3545';
                    } else {
                        field.style.borderColor = '#e9ecef';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>