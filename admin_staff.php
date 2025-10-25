<?php
// admin_staff.php
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

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Process form submissions
if ($_POST) {
    if (isset($_POST['add_staff'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $role = $_POST['role'];
        
        // Validation
        $errors = [];
        
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $errors[] = "All required fields must be filled";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
        
        if (empty($errors)) {
            try {
                // Check if username already exists
                $stmt = $db->prepare("SELECT id FROM owners WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $errors[] = "Username already exists";
                }
                
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM owners WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "Email already exists";
                }
                
                if (empty($errors)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("INSERT INTO owners (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $role]);
                    
                    $message = 'success=staff_added';
                }
            } catch (Exception $e) {
                $message = 'error=add_failed';
            }
        } else {
            $message = 'error=' . urlencode(implode(', ', $errors));
        }
    }
    elseif (isset($_POST['update_staff'])) {
        $staff_id = intval($_POST['staff_id']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check if password is being updated
        $update_password = !empty($_POST['password']);
        
        try {
            // Check for duplicate username (excluding current staff)
            $stmt = $db->prepare("SELECT id FROM owners WHERE username = ? AND id != ?");
            $stmt->execute([$username, $staff_id]);
            if ($stmt->fetch()) {
                $message = 'error=username_exists';
            } else {
                // Check for duplicate email (excluding current staff)
                $stmt = $db->prepare("SELECT id FROM owners WHERE email = ? AND id != ?");
                $stmt->execute([$email, $staff_id]);
                if ($stmt->fetch()) {
                    $message = 'error=email_exists';
                } else {
                    if ($update_password) {
                        $password = $_POST['password'];
                        $confirm_password = $_POST['confirm_password'];
                        
                        if ($password !== $confirm_password) {
                            $message = 'error=password_mismatch';
                        } elseif (strlen($password) < 6) {
                            $message = 'error=password_short';
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("UPDATE owners SET username = ?, email = ?, password = ?, full_name = ?, phone = ?, role = ?, is_active = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $role, $is_active, $staff_id]);
                            $message = 'success=staff_updated';
                        }
                    } else {
                        $stmt = $db->prepare("UPDATE owners SET username = ?, email = ?, full_name = ?, phone = ?, role = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $full_name, $phone, $role, $is_active, $staff_id]);
                        $message = 'success=staff_updated';
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'error=update_failed';
        }
    }
    elseif (isset($_POST['delete_staff'])) {
        try {
            // Prevent deleting yourself
            if ($staff_id == $_SESSION['owner_id']) {
                $message = 'error=cannot_delete_self';
            } else {
                $stmt = $db->prepare("DELETE FROM owners WHERE id = ?");
                $stmt->execute([$staff_id]);
                $message = 'success=staff_deleted';
                $action = 'list';
            }
        } catch (Exception $e) {
            $message = 'error=delete_failed';
        }
    }
    
    header("Location: admin_staff.php?action=$action&id=$staff_id&$message");
    exit;
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$query = "SELECT * FROM owners WHERE id != ?"; // Exclude current user from listing
$params = [$_SESSION['owner_id']];

if ($role_filter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $query .= " AND is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND is_active = 0";
    }
}

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY role, full_name";

// Get staff members
$stmt = $db->prepare($query);
$stmt->execute($params);
$staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff details for view/edit
$staff = null;
if (($action === 'view' || $action === 'edit') && $staff_id > 0) {
    $stmt = $db->prepare("SELECT * FROM owners WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        header("Location: admin_staff.php?error=staff_not_found");
        exit;
    }
}

// Get statistics
try {
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_staff,
        SUM(is_active) as active_staff,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) as manager_count,
        SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count
        FROM owners WHERE id != ?");
    $stmt->execute([$_SESSION['owner_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_staff' => count($staff_members),
        'active_staff' => count($staff_members),
        'admin_count' => 0,
        'manager_count' => 0,
        'staff_count' => count($staff_members)
    ];
}

// Define roles and their permissions
$roles = [
    'admin' => [
        'name' => 'Administrator',
        'description' => 'Full access to all features and settings',
        'permissions' => ['dashboard', 'orders', 'menu', 'customers', 'reservations', 'staff', 'reports', 'settings']
    ],
    'manager' => [
        'name' => 'Manager',
        'description' => 'Access to most features except staff management',
        'permissions' => ['dashboard', 'orders', 'menu', 'customers', 'reservations', 'reports']
    ],
    'staff' => [
        'name' => 'Staff',
        'description' => 'Limited access for daily operations',
        'permissions' => ['dashboard', 'orders', 'menu', 'reservations']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Staff Management Specific Styles */
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

        .btn-info {
            background: #17a2b8;
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

        /* Role Cards */
        .roles-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .role-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9fa;
        }

        .role-card.admin {
            border-left: 4px solid #dc3545;
        }

        .role-card.manager {
            border-left: 4px solid #ffc107;
        }

        .role-card.staff {
            border-left: 4px solid #17a2b8;
        }

        .role-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }

        .role-name {
            font-weight: 600;
            font-size: 18px;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-admin { background: #dc3545; color: white; }
        .badge-manager { background: #ffc107; color: #212529; }
        .badge-staff { background: #17a2b8; color: white; }

        .permissions-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .permissions-list li {
            padding: 5px 0;
            font-size: 14px;
            color: #495057;
        }

        .permissions-list li:before {
            content: '✓';
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
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

        /* Staff Table */
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

        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6f4e37;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .staff-details {
            display: flex;
            flex-direction: column;
        }

        .staff-name {
            font-weight: 600;
            color: #495057;
        }

        .staff-username {
            font-size: 12px;
            color: #6c757d;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        .role-badge-table {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        /* Staff Details View */
        .staff-details-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .staff-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #6f4e37;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
        }

        .profile-info h2 {
            margin: 0 0 5px 0;
            color: #495057;
        }

        .profile-info p {
            margin: 0;
            color: #6c757d;
        }

        .staff-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .info-card h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #6f4e37;
        }

        /* Add/Edit Form */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #6f4e37;
            outline: none;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .password-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .no-staff {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-staff i {
            font-size: 48px;
            color: #e9ecef;
            margin-bottom: 20px;
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
            
            .staff-header {
                flex-direction: column;
                gap: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .roles-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-user-shield"></i> Staff Management
            </h1>
            <div class="header-actions">
                <a href="admin_staff.php?action=add" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Add Staff
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
                    'staff_added' => 'Staff member added successfully!',
                    'staff_updated' => 'Staff member updated successfully!',
                    'staff_deleted' => 'Staff member deleted successfully!'
                ];
                echo $successMessages[$_GET['success']] ?? 'Operation completed successfully!';
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                $errorMessages = [
                    'add_failed' => 'Failed to add staff member.',
                    'update_failed' => 'Failed to update staff member.',
                    'delete_failed' => 'Failed to delete staff member.',
                    'username_exists' => 'Username already exists.',
                    'email_exists' => 'Email already exists.',
                    'password_mismatch' => 'Passwords do not match.',
                    'password_short' => 'Password must be at least 6 characters.',
                    'cannot_delete_self' => 'You cannot delete your own account.',
                    'staff_not_found' => 'Staff member not found.',
                    'access_denied' => 'Access denied. Admin permissions required.'
                ];
                $error = $_GET['error'];
                if (strpos($error, ',') !== false) {
                    echo implode('<br>', explode(',', $error));
                } else {
                    echo $errorMessages[$error] ?? 'An error occurred.';
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $stats['total_staff']; ?></div>
                <div class="stat-label">Total Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo $stats['active_staff']; ?></div>
                <div class="stat-label">Active Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stat-number"><?php echo $stats['admin_count']; ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo $stats['manager_count']; ?></div>
                <div class="stat-label">Managers</div>
            </div>
        </div>

        <!-- Roles Overview -->
        <div class="roles-section">
            <h3><i class="fas fa-layer-group"></i> Role Permissions</h3>
            <div class="roles-grid">
                <?php foreach ($roles as $role_key => $role): ?>
                    <div class="role-card <?php echo $role_key; ?>">
                        <div class="role-header">
                            <div class="role-name"><?php echo $role['name']; ?></div>
                            <span class="role-badge badge-<?php echo $role_key; ?>"><?php echo $role_key; ?></span>
                        </div>
                        <p style="color: #6c757d; margin-bottom: 15px; font-size: 14px;"><?php echo $role['description']; ?></p>
                        <ul class="permissions-list">
                            <?php foreach ($role['permissions'] as $permission): ?>
                                <li><?php echo ucfirst($permission); ?> Management</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="admin_staff.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            <option value="manager" <?php echo $role_filter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Name, Username, Email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="admin_staff.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <?php if ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Staff Form -->
            <div class="form-container">
                <h2><?php echo $action === 'add' ? 'Add New Staff Member' : 'Edit Staff Member'; ?></h2>
                
                <form method="POST" id="staffForm">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="update_staff" value="1">
                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="add_staff" value="1">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo htmlspecialchars($staff['username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required 
                                   value="<?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin" <?php echo ($staff['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                <option value="manager" <?php echo ($staff['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="staff" <?php echo ($staff['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            </select>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                        <div class="form-group">
                            <label for="is_active">Status</label>
                            <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                                <input type="checkbox" id="is_active" name="is_active" value="1" 
                                       <?php echo ($staff['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="is_active" style="margin: 0; font-weight: normal;">Active Account</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="password-section">
                        <h4 style="margin-top: 0;">
                            <i class="fas fa-lock"></i> 
                            <?php echo $action === 'add' ? 'Set Password' : 'Change Password'; ?>
                            <?php if ($action === 'edit'): ?>
                                <small style="font-weight: normal; color: #6c757d;">(Leave blank to keep current password)</small>
                            <?php endif; ?>
                        </h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password <?php echo $action === 'add' ? '*' : ''; ?></label>
                                <input type="password" id="password" name="password" 
                                       <?php echo $action === 'add' ? 'required' : ''; ?>
                                       placeholder="<?php echo $action === 'edit' ? 'Leave blank to keep current' : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <?php echo $action === 'add' ? '*' : ''; ?></label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       <?php echo $action === 'add' ? 'required' : ''; ?>
                                       placeholder="<?php echo $action === 'edit' ? 'Leave blank to keep current' : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin_staff.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> 
                            <?php echo $action === 'add' ? 'Add Staff Member' : 'Update Staff Member'; ?>
                        </button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'view' && $staff): ?>
            <!-- Staff Details View -->
            <div class="staff-details-container">
                <div class="staff-header">
                    <div class="staff-profile">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($staff['full_name']); ?></h2>
                            <p><?php echo htmlspecialchars($staff['email']); ?></p>
                            <p>@<?php echo htmlspecialchars($staff['username']); ?></p>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a href="admin_staff.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <a href="admin_staff.php?action=edit&id=<?php echo $staff['id']; ?>" class="btn">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php if ($staff['id'] != $_SESSION['owner_id']): ?>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Are you sure you want to delete this staff member? This action cannot be undone.')">
                            <input type="hidden" name="delete_staff" value="1">
                            <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="staff-stats">
                    <div class="info-card">
                        <h4>Role</h4>
                        <div class="number">
                            <span class="role-badge-table badge-<?php echo $staff['role']; ?>" style="font-size: 14px; padding: 6px 12px;">
                                <?php echo ucfirst($staff['role']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-card">
                        <h4>Status</h4>
                        <div class="number">
                            <span class="status-badge status-<?php echo $staff['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-card">
                        <h4>Member Since</h4>
                        <div class="number" style="font-size: 18px;">
                            <?php echo date('M j, Y', strtotime($staff['created_at'])); ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <h4>Last Updated</h4>
                        <div class="number" style="font-size: 18px;">
                            <?php echo date('M j, Y', strtotime($staff['updated_at'])); ?>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="info-card">
                        <h4>Contact Information</h4>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($staff['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
                        <?php if ($staff['phone']): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone']); ?></p>
                        <?php else: ?>
                            <p><strong>Phone:</strong> <span style="color: #6c757d;">Not provided</span></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-card">
                        <h4>Role Permissions</h4>
                        <ul class="permissions-list">
                            <?php foreach ($roles[$staff['role']]['permissions'] as $permission): ?>
                                <li><?php echo ucfirst($permission); ?> Management</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Staff List -->
            <div class="table-container">
                <?php if (empty($staff_members)): ?>
                    <div class="no-staff">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Staff Members Found</h3>
                        <p>There are no staff members matching your current filters.</p>
                        <a href="admin_staff.php" class="btn">Clear Filters</a>
                        <a href="admin_staff.php?action=add" class="btn btn-success" style="margin-left: 10px;">
                            <i class="fas fa-user-plus"></i> Add First Staff Member
                        </a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_members as $staff): ?>
                                <tr>
                                    <td>
                                        <div class="staff-info">
                                            <div class="staff-avatar">
                                                <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                                            </div>
                                            <div class="staff-details">
                                                <span class="staff-name"><?php echo htmlspecialchars($staff['full_name']); ?></span>
                                                <span class="staff-username">@<?php echo htmlspecialchars($staff['username']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($staff['email']); ?>
                                        <?php if ($staff['phone']): ?>
                                            <br><small style="color: #6c757d;"><?php echo htmlspecialchars($staff['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="role-badge-table badge-<?php echo $staff['role']; ?>">
                                            <?php echo ucfirst($staff['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $staff['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($staff['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="admin_staff.php?action=view&id=<?php echo $staff['id']; ?>" 
                                               class="btn btn-small btn-info" title="View Staff">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="admin_staff.php?action=edit&id=<?php echo $staff['id']; ?>" 
                                               class="btn btn-small" title="Edit Staff">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($staff['id'] != $_SESSION['owner_id']): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this staff member? This action cannot be undone.')">
                                                <input type="hidden" name="delete_staff" value="1">
                                                <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                                                <button type="submit" class="btn btn-small btn-danger" title="Delete Staff">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
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
        // Form validation
        document.getElementById('staffForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const isEdit = <?php echo $action === 'edit' ? 'true' : 'false'; ?>;
            
            // For new staff, password is required
            if (!isEdit && (!password || !confirmPassword)) {
                e.preventDefault();
                alert('Please fill in both password fields for new staff members.');
                return false;
            }
            
            // If password is provided in edit mode, validate it
            if (isEdit && password) {
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please make sure both password fields are identical.');
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return false;
                }
            }
            
            // For new staff, validate password strength
            if (!isEdit) {
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please make sure both password fields are identical.');
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return false;
                }
            }
        });

        // Username availability check
        document.getElementById('username')?.addEventListener('blur', function() {
            const username = this.value;
            if (username.length < 3) {
                this.style.borderColor = '#dc3545';
                alert('Username must be at least 3 characters long.');
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });

        // Email validation
        document.getElementById('email')?.addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#dc3545';
                alert('Please enter a valid email address.');
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });

        // Auto-focus search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value === '') {
                searchInput.focus();
            }
        });

        // Quick status toggle
        function toggleStaffStatus(staffId, currentStatus) {
            if (confirm('Are you sure you want to ' + (currentStatus ? 'deactivate' : 'activate') + ' this staff member?')) {
                // This would typically be an AJAX call in a real application
                alert('Status toggle functionality would be implemented here.');
            }
        }

        // Role change confirmation for admin role
        document.getElementById('role')?.addEventListener('change', function() {
            if (this.value === 'admin') {
                if (!confirm('Are you sure you want to assign Administrator role? This will give full system access to this user.')) {
                    this.value = this.defaultValue;
                }
            }
        });
    </script>
</body>
</html>