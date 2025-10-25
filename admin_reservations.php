<?php
// admin_reservations.php
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

// Update database structure if needed
try {
    // Create reservations table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        customer_name VARCHAR(255),
        customer_phone VARCHAR(20),
        customer_email VARCHAR(255),
        reservation_date DATE NOT NULL,
        reservation_time TIME NOT NULL,
        party_size INT NOT NULL,
        special_requests TEXT,
        status ENUM('pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
        table_number VARCHAR(10),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    )");
    
} catch (Exception $e) {
    error_log("Database setup error: " . $e->getMessage());
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Process form submissions
if ($_POST) {
    if (isset($_POST['update_reservation'])) {
        $reservation_id = intval($_POST['reservation_id']);
        $status = $_POST['status'] ?? '';
        $table_number = trim($_POST['table_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $stmt = $db->prepare("UPDATE reservations SET status = ?, table_number = ?, notes = ? WHERE id = ?");
            $stmt->execute([$status, $table_number, $notes, $reservation_id]);
            $message = 'success=reservation_updated';
        } catch (Exception $e) {
            $message = 'error=update_failed';
        }
    }
    elseif (isset($_POST['add_reservation'])) {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $reservation_date = $_POST['reservation_date'] ?? '';
        $reservation_time = $_POST['reservation_time'] ?? '';
        $party_size = intval($_POST['party_size'] ?? 1);
        $special_requests = trim($_POST['special_requests'] ?? '');
        $table_number = trim($_POST['table_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $stmt = $db->prepare("INSERT INTO reservations (customer_name, customer_phone, customer_email, reservation_date, reservation_time, party_size, special_requests, table_number, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");
            $stmt->execute([$customer_name, $customer_phone, $customer_email, $reservation_date, $reservation_time, $party_size, $special_requests, $table_number, $notes]);
            $message = 'success=reservation_added';
        } catch (Exception $e) {
            $message = 'error=add_failed';
        }
    }
   elseif (isset($_POST['delete_reservation'])) {
    $reservation_id = intval($_POST['id']); // ADD THIS LINE to get the ID from POST
    try {
        $stmt = $db->prepare("DELETE FROM reservations WHERE id = ?");
        $stmt->execute([$reservation_id]);
        $message = 'success=reservation_deleted';
        $action = 'list';
    } catch (Exception $e) {
        $message = 'error=delete_failed';
    }
}
    
    header("Location: admin_reservations.php?action=$action&id=$reservation_id&$message");
    exit;
}

// Get filter parameters with null safety
$status_filter = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? (string)$_GET['date'] : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Build query with filters
$query = "SELECT r.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone 
          FROM reservations r 
          LEFT JOIN customers c ON r.customer_id = c.id 
          WHERE 1=1";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND r.reservation_date = ?";
    $params[] = $date_filter;
}

if (!empty($search)) {
    $query .= " AND (r.customer_name LIKE ? OR r.customer_phone LIKE ? OR r.customer_email LIKE ? OR r.table_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY r.reservation_date DESC, r.reservation_time DESC";

// Get reservations
$reservations = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
}

// Get reservation details for view/edit
$reservation = null;
if (($action === 'view' || $action === 'edit') && $reservation_id > 0) {
    try {
        $stmt = $db->prepare("SELECT r.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone 
                             FROM reservations r 
                             LEFT JOIN customers c ON r.customer_id = c.id 
                             WHERE r.id = ?");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            header("Location: admin_reservations.php?error=reservation_not_found");
            exit;
        }
    } catch (Exception $e) {
        error_log("Error fetching reservation details: " . $e->getMessage());
        header("Location: admin_reservations.php?error=reservation_not_found");
        exit;
    }
}

// Get statistics
$stats = [
    'total_reservations' => 0,
    'today_reservations' => 0,
    'pending_reservations' => 0,
    'confirmed_reservations' => 0,
    'today_upcoming' => 0
];

try {
    $stmt = $db->query("SELECT 
        COUNT(*) as total_reservations,
        SUM(CASE WHEN reservation_date = CURDATE() THEN 1 ELSE 0 END) as today_reservations,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reservations,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_reservations,
        SUM(CASE WHEN reservation_date = CURDATE() AND status IN ('confirmed', 'pending') THEN 1 ELSE 0 END) as today_upcoming
        FROM reservations");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = array_map('intval', $result);
    }
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $stats['total_reservations'] = count($reservations);
}

// Get today's reservations for quick view
$today_reservations = [];
try {
    $stmt = $db->prepare("SELECT * FROM reservations WHERE reservation_date = CURDATE() AND status IN ('confirmed', 'pending') ORDER BY reservation_time ASC");
    $stmt->execute();
    $today_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching today's reservations: " . $e->getMessage());
}

// Define available time slots
$time_slots = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '12:00', '12:30', '13:00', '13:30', '14:00', '14:30',
    '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
    '18:00', '18:30', '19:00', '19:30', '20:00', '20:30',
    '21:00', '21:30', '22:00'
];

// Define available tables
$available_tables = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'VIP1', 'VIP2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain exactly the same */
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
        .today-section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .today-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 20px; }
        .today-card { border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; background: #f8f9fa; }
        .today-card.upcoming { border-left: 4px solid #28a745; }
        .today-card.pending { border-left: 4px solid #ffc107; }
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
        .reservation-id { font-weight: 600; color: #6f4e37; }
        .customer-info { display: flex; flex-direction: column; }
        .customer-name { font-weight: 600; color: #495057; }
        .customer-contact { font-size: 12px; color: #6c757d; }
        .datetime-info { display: flex; flex-direction: column; }
        .reservation-date { font-weight: 600; color: #495057; }
        .reservation-time { font-size: 12px; color: #6c757d; }
        .party-size { text-align: center; font-weight: 600; color: #6f4e37; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-seated { background: #d4edda; color: #155724; }
        .status-completed { background: #c3e6cb; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-no_show { background: #e2e3e5; color: #383d41; }
        .table-number { text-align: center; font-weight: 600; color: #17a2b8; }
        .action-buttons { display: flex; gap: 8px; }
        .reservation-details-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        .reservation-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e9ecef; }
        .reservation-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #6f4e37; }
        .info-card h4 { margin: 0 0 10px 0; color: #495057; font-size: 16px; }
        .info-card p { margin: 5px 0; color: #6c757d; }
        .status-form { background: #f8f9fa; padding: 25px; border-radius: 8px; margin-top: 30px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 8px; color: #495057; }
        .form-group select, .form-group input, .form-group textarea { padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .add-form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px; }
        .no-reservations { text-align: center; padding: 60px 20px; color: #6c757d; }
        .no-reservations i { font-size: 48px; color: #e9ecef; margin-bottom: 20px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: 500; border-left: 4px solid; }
        .alert.success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert.error { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        @media (max-width: 768px) {
            .content-header { flex-direction: column; align-items: flex-start; }
            .filters-grid { grid-template-columns: 1fr; }
            .table { display: block; overflow-x: auto; }
            .reservation-header { flex-direction: column; gap: 20px; }
            .form-grid, .form-row { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .today-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-calendar-alt"></i> Reservation Management
            </h1>
            <div class="header-actions">
                <a href="admin_reservations.php?action=add" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Reservation
                </a>
                <a href="admin_reservations.php?export=csv" class="btn btn-secondary">
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
                    'reservation_updated' => 'Reservation updated successfully!',
                    'reservation_added' => 'Reservation added successfully!',
                    'reservation_deleted' => 'Reservation deleted successfully!'
                ];
                echo safe_output($successMessages[$_GET['success']] ?? 'Operation completed successfully!');
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert error">
                <?php 
                $errorMessages = [
                    'update_failed' => 'Failed to update reservation.',
                    'add_failed' => 'Failed to add reservation.',
                    'delete_failed' => 'Failed to delete reservation.',
                    'reservation_not_found' => 'Reservation not found.'
                ];
                echo safe_output($errorMessages[$_GET['error']] ?? 'An error occurred.');
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                <div class="stat-number"><?php echo safe_output($stats['total_reservations']); ?></div>
                <div class="stat-label">Total Reservations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo safe_output($stats['today_reservations']); ?></div>
                <div class="stat-label">Today's Reservations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-number"><?php echo safe_output($stats['pending_reservations']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo safe_output($stats['confirmed_reservations']); ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
        </div>

        <!-- Today's Reservations -->
        <?php if (!empty($today_reservations)): ?>
        <div class="today-section">
            <h3><i class="fas fa-calendar-day"></i> Today's Reservations (<?php echo date('F j, Y'); ?>)</h3>
            <div class="today-grid">
                <?php foreach ($today_reservations as $res): ?>
                    <div class="today-card <?php echo safe_output($res['status']); ?>">
                        <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 10px;">
                            <strong><?php echo safe_output($res['customer_name']); ?></strong>
                            <span class="status-badge status-<?php echo safe_output($res['status']); ?>" style="margin-left: auto;">
                                <?php echo ucfirst(safe_output($res['status'])); ?>
                            </span>
                        </div>
                        <div style="font-size: 14px; color: #6c757d;">
                            <div>Time: <?php echo date('g:i A', strtotime(safe_output($res['reservation_time']))); ?></div>
                            <div>Party: <?php echo safe_output($res['party_size']); ?> people</div>
                            <?php if ($res['table_number']): ?>
                                <div>Table: <?php echo safe_output($res['table_number']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <a href="admin_reservations.php?action=view&id=<?php echo safe_output($res['id']); ?>" class="btn btn-small btn-info">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="admin_reservations.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="seated" <?php echo $status_filter === 'seated' ? 'selected' : ''; ?>>Seated</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" value="<?php echo safe_output($date_filter); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Name, Phone, Email, Table..." 
                               value="<?php echo safe_output($search); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="admin_reservations.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <?php if ($action === 'add'): ?>
            <!-- Add Reservation Form -->
            <div class="add-form-container">
                <h2>Add New Reservation</h2>
                
                <form method="POST" id="addReservationForm">
                    <input type="hidden" name="add_reservation" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Customer Name *</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_phone">Phone Number *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_email">Email Address</label>
                            <input type="email" id="customer_email" name="customer_email">
                        </div>
                        
                        <div class="form-group">
                            <label for="party_size">Party Size *</label>
                            <select id="party_size" name="party_size" required>
                                <option value="">Select party size</option>
                                <?php for ($i = 1; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i === 1 ? 'person' : 'people'; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reservation_date">Date *</label>
                            <input type="date" id="reservation_date" name="reservation_date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="reservation_time">Time *</label>
                            <select id="reservation_time" name="reservation_time" required>
                                <option value="">Select time</option>
                                <?php foreach ($time_slots as $time): ?>
                                    <option value="<?php echo safe_output($time); ?>"><?php echo date('g:i A', strtotime(safe_output($time))); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="table_number">Table Number</label>
                            <select id="table_number" name="table_number">
                                <option value="">No table assigned</option>
                                <?php foreach ($available_tables as $table): ?>
                                    <option value="<?php echo safe_output($table); ?>"><?php echo safe_output($table); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="special_requests">Special Requests</label>
                        <textarea id="special_requests" name="special_requests" placeholder="Any special requests or notes..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Internal Notes</label>
                        <textarea id="notes" name="notes" placeholder="Internal notes for staff..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin_reservations.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-calendar-plus"></i> Create Reservation
                        </button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'view' && $reservation): ?>
            <!-- Reservation Details View -->
            <div class="reservation-details-container">
                <div class="reservation-header">
                    <div>
                        <h2>Reservation #<?php echo safe_output($reservation['id']); ?></h2>
                        <p>Created on <?php echo date('F j, Y g:i A', strtotime(safe_output($reservation['created_at']))); ?></p>
                    </div>
                    <div class="action-buttons">
                        <a href="admin_reservations.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Are you sure you want to delete this reservation? This action cannot be undone.')">
                            <input type="hidden" name="delete_reservation" value="1">
                            <input type="hidden" name="id" value="<?php echo safe_output($reservation['id']); ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>

                <div class="reservation-info">
                    <div class="info-card">
                        <h4>Customer Information</h4>
                        <p><strong>Name:</strong> <?php echo safe_output($reservation['customer_name']); ?></p>
                        <p><strong>Phone:</strong> <?php echo safe_output($reservation['customer_phone']); ?></p>
                        <?php if ($reservation['customer_email']): ?>
                            <p><strong>Email:</strong> <?php echo safe_output($reservation['customer_email']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-card">
                        <h4>Reservation Details</h4>
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime(safe_output($reservation['reservation_date']))); ?></p>
                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime(safe_output($reservation['reservation_time']))); ?></p>
                        <p><strong>Party Size:</strong> <?php echo safe_output($reservation['party_size']); ?> people</p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Status & Assignment</h4>
                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo safe_output($reservation['status']); ?>">
                            <?php echo ucfirst(safe_output($reservation['status'])); ?>
                        </span></p>
                        <?php if ($reservation['table_number']): ?>
                            <p><strong>Table:</strong> <?php echo safe_output($reservation['table_number']); ?></p>
                        <?php else: ?>
                            <p><strong>Table:</strong> Not assigned</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($reservation['special_requests']): ?>
                <div class="info-card">
                    <h4>Special Requests</h4>
                    <p><?php echo nl2br(safe_output($reservation['special_requests'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($reservation['notes']): ?>
                <div class="info-card">
                    <h4>Internal Notes</h4>
                    <p><?php echo nl2br(safe_output($reservation['notes'])); ?></p>
                </div>
                <?php endif; ?>

                <!-- Status Update Form -->
                <form method="POST" class="status-form">
                    <input type="hidden" name="update_reservation" value="1">
                    <input type="hidden" name="reservation_id" value="<?php echo safe_output($reservation['id']); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="status">Update Status</label>
                            <select id="status" name="status" required>
                                <option value="pending" <?php echo $reservation['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $reservation['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="seated" <?php echo $reservation['status'] === 'seated' ? 'selected' : ''; ?>>Seated</option>
                                <option value="completed" <?php echo $reservation['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $reservation['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="no_show" <?php echo $reservation['status'] === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="table_number">Table Number</label>
                            <select id="table_number" name="table_number">
                                <option value="">No table assigned</option>
                                <?php foreach ($available_tables as $table): ?>
                                    <option value="<?php echo safe_output($table); ?>" <?php echo $reservation['table_number'] === $table ? 'selected' : ''; ?>>
                                        <?php echo safe_output($table); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Update Notes</label>
                        <textarea id="notes" name="notes" placeholder="Add or update internal notes..."><?php echo safe_output($reservation['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Reservation
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Reservations List -->
            <div class="table-container">
                <?php if (empty($reservations)): ?>
                    <div class="no-reservations">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Reservations Found</h3>
                        <p>There are no reservations matching your current filters.</p>
                        <a href="admin_reservations.php" class="btn">Clear Filters</a>
                        <a href="admin_reservations.php?action=add" class="btn btn-success" style="margin-left: 10px;">
                            <i class="fas fa-plus"></i> Add First Reservation
                        </a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Date & Time</th>
                                <th>Party</th>
                                <th>Table</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $res): ?>
                                <tr>
                                    <td>
                                        <span class="reservation-id">#<?php echo safe_output($res['id']); ?></span>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <span class="customer-name"><?php echo safe_output($res['customer_name'] ?: 'Guest'); ?></span>
                                            <span class="customer-contact">
                                                <?php echo safe_output($res['customer_phone']); ?>
                                                <?php if ($res['customer_email']): ?>
                                                    • <?php echo safe_output($res['customer_email']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="datetime-info">
                                            <span class="reservation-date"><?php echo date('M j, Y', strtotime(safe_output($res['reservation_date']))); ?></span>
                                            <span class="reservation-time"><?php echo date('g:i A', strtotime(safe_output($res['reservation_time']))); ?></span>
                                        </div>
                                    </td>
                                    <td class="party-size"><?php echo safe_output($res['party_size']); ?></td>
                                    <td class="table-number">
                                        <?php if ($res['table_number']): ?>
                                            <?php echo safe_output($res['table_number']); ?>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo safe_output($res['status']); ?>">
                                            <?php echo ucfirst(safe_output($res['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j', strtotime(safe_output($res['created_at']))); ?></td>
                                    <td>
    <div class="action-buttons">
        <a href="admin_reservations.php?action=view&id=<?php echo safe_output($res['id']); ?>" 
           class="btn btn-small btn-info" title="View Reservation">
            <i class="fas fa-eye"></i>
        </a>
        <!-- CORRECTED DELETE BUTTON -->
        <form method="POST" style="display: inline;" 
              onsubmit="return confirm('Are you sure you want to delete reservation #<?php echo safe_output($res['id']); ?>? This action cannot be undone.')">
            <input type="hidden" name="delete_reservation" value="1">
            <input type="hidden" name="id" value="<?php echo safe_output($res['id']); ?>">
            <button type="submit" class="btn btn-small btn-danger" title="Delete Reservation">
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
        // Auto-set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('reservation_date');
            if (dateInput && !dateInput.value) {
                dateInput.min = new Date().toISOString().split('T')[0];
            }

            // Auto-focus search
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value === '') {
                searchInput.focus();
            }
        });

        // Status change confirmation for cancellations
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                if (this.value === 'cancelled' || this.value === 'no_show') {
                    if (!confirm('Are you sure you want to mark this reservation as ' + this.value + '? This action cannot be undone.')) {
                        this.value = this.defaultValue;
                    }
                }
            });
        });

        // Form validation for add reservation
        document.getElementById('addReservationForm')?.addEventListener('submit', function(e) {
            const date = document.getElementById('reservation_date').value;
            const time = document.getElementById('reservation_time').value;
            const partySize = document.getElementById('party_size').value;
            
            if (!date || !time || !partySize) {
                e.preventDefault();
                alert('Please fill in all required fields: Date, Time, and Party Size.');
                return false;
            }
            
            // Check if date is in the past
            const selectedDate = new Date(date + 'T' + time);
            const now = new Date();
            if (selectedDate < now) {
                e.preventDefault();
                alert('Cannot create reservation for past date/time. Please select a future date and time.');
                return false;
            }
        });
    </script>
</body>
</html>