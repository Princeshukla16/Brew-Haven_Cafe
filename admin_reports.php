<?php
// admin_reports.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get report parameters with defaults
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales_summary';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Set date range based on selection
$date_conditions = getDateRangeConditions($date_range, $start_date, $end_date);
$date_filter = $date_conditions['condition'];
$date_params = $date_conditions['params'];

// Initialize report data
$report_data = [];
$chart_data = [];
$summary_stats = [];

try {
    switch ($report_type) {
        case 'sales_summary':
            $report_data = getSalesSummaryReport($db, $date_filter, $date_params);
            $chart_data = getSalesChartData($db, $date_filter, $date_params);
            break;
            
        case 'customer_analysis':
            $report_data = getCustomerAnalysisReport($db, $date_filter, $date_params);
            $chart_data = getCustomerChartData($db, $date_filter, $date_params);
            break;
            
        case 'menu_performance':
            $report_data = getMenuPerformanceReport($db, $date_filter, $date_params);
            $chart_data = getMenuChartData($db, $date_filter, $date_params);
            break;
            
        case 'reservation_analysis':
            $report_data = getReservationAnalysisReport($db, $date_filter, $date_params);
            $chart_data = getReservationChartData($db, $date_filter, $date_params);
            break;
            
        case 'review_analysis':
            $report_data = getReviewAnalysisReport($db, $date_filter, $date_params);
            $chart_data = getReviewChartData($db, $date_filter, $date_params);
            break;
    }
    
    // Get summary statistics
    $summary_stats = getSummaryStatistics($db, $date_filter, $date_params);
    
} catch (Exception $e) {
    error_log("Report generation error: " . $e->getMessage());
    $error = "Failed to generate report: " . $e->getMessage();
}

// Handle export
if ($export && !empty($report_data)) {
    exportReport($report_data, $report_type, $date_range, $start_date, $end_date);
    exit;
}

// Report functions
function getDateRangeConditions($date_range, $start_date, $end_date) {
    $condition = "";
    $params = [];
    
    switch ($date_range) {
        case 'today':
            $condition = "DATE(created_at) = CURDATE()";
            break;
        case 'yesterday':
            $condition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $condition = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'last_week':
            $condition = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) - 1";
            break;
        case 'this_month':
            $condition = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
        case 'last_month':
            $condition = "YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            break;
        case 'this_quarter':
            $condition = "YEAR(created_at) = YEAR(CURDATE()) AND QUARTER(created_at) = QUARTER(CURDATE())";
            break;
        case 'this_year':
            $condition = "YEAR(created_at) = YEAR(CURDATE())";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $condition = "DATE(created_at) BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
            }
            break;
        default:
            $condition = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
    }
    
    return ['condition' => $condition, 'params' => $params];
}

function getSalesSummaryReport($db, $date_filter, $date_params) {
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as order_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value,
                COUNT(DISTINCT customer_id) as unique_customers
              FROM orders 
              WHERE status = 'completed'";
    
    if (!empty($date_filter)) {
        $query .= " AND $date_filter";
    }
    
    $query .= " GROUP BY DATE(created_at) ORDER BY date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerAnalysisReport($db, $date_filter, $date_params) {
    $query = "SELECT 
                c.id,
                c.name,
                c.email,
                c.phone,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_spent,
                MAX(o.created_at) as last_order_date,
                c.loyalty_points,
                c.created_at as join_date
              FROM customers c 
              LEFT JOIN orders o ON c.id = o.customer_id AND o.status = 'completed'";
    
    if (!empty($date_filter)) {
        $query .= " AND o.$date_filter";
    }
    
    $query .= " GROUP BY c.id 
                HAVING order_count > 0 
                ORDER BY total_spent DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMenuPerformanceReport($db, $date_filter, $date_params) {
    $query = "SELECT 
                mi.id,
                mi.name,
                mi.category,
                mi.price,
                mi.is_available,
                COUNT(oi.id) as times_ordered,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.quantity * oi.price) as total_revenue,
                AVG(oi.quantity) as avg_quantity_per_order
              FROM menu_items mi 
              LEFT JOIN order_items oi ON mi.id = oi.menu_item_id 
              LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'";
    
    if (!empty($date_filter)) {
        $query .= " AND o.$date_filter";
    }
    
    $query .= " GROUP BY mi.id 
                ORDER BY total_revenue DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getReservationAnalysisReport($db, $date_filter, $date_params) {
    $query = "SELECT 
                reservation_date,
                COUNT(*) as total_reservations,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                AVG(party_size) as avg_party_size
              FROM reservations";
    
    if (!empty($date_filter)) {
        $query .= " WHERE $date_filter";
    }
    
    $query .= " GROUP BY reservation_date 
                ORDER BY reservation_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getReviewAnalysisReport($db, $date_filter, $date_params) {
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_reviews,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured
              FROM reviews";
    
    if (!empty($date_filter)) {
        $query .= " WHERE $date_filter";
    }
    
    $query .= " GROUP BY DATE(created_at) 
                ORDER BY date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Add these chart data functions after the existing report functions

function getCustomerChartData($db, $date_filter, $date_params) {
    $query = "SELECT 
                c.name as customer_name,
                SUM(o.total_amount) as total_spent,
                COUNT(o.id) as order_count
              FROM customers c 
              LEFT JOIN orders o ON c.id = o.customer_id AND o.status = 'completed'";
    
    if (!empty($date_filter)) {
        $query .= " AND o.$date_filter";
    }
    
    $query .= " GROUP BY c.id 
                HAVING total_spent > 0 
                ORDER BY total_spent DESC 
                LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMenuChartData($db, $date_filter, $date_params) {
    $query = "SELECT 
                mi.name as item_name,
                mi.category,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.quantity * oi.price) as total_revenue
              FROM menu_items mi 
              LEFT JOIN order_items oi ON mi.id = oi.menu_item_id 
              LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'";
    
    if (!empty($date_filter)) {
        $query .= " AND o.$date_filter";
    }
    
    $query .= " GROUP BY mi.id 
                HAVING total_revenue > 0 
                ORDER BY total_revenue DESC 
                LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getReservationChartData($db, $date_filter, $date_params) {
    $query = "SELECT 
                reservation_date,
                COUNT(*) as total_reservations,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
              FROM reservations";
    
    if (!empty($date_filter)) {
        $query .= " WHERE $date_filter";
    }
    
    $query .= " GROUP BY reservation_date 
                ORDER BY reservation_date DESC 
                LIMIT 15";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getReviewChartData($db, $date_filter, $date_params) {
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as review_count,
                AVG(rating) as avg_rating
              FROM reviews";
    
    if (!empty($date_filter)) {
        $query .= " WHERE $date_filter";
    }
    
    $query .= " GROUP BY DATE(created_at) 
                ORDER BY date DESC 
                LIMIT 15";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Also update the sales chart data function to be more comprehensive
function getSalesChartData($db, $date_filter, $date_params) {
    $query = "SELECT 
                DATE(created_at) as date,
                SUM(total_amount) as revenue,
                COUNT(*) as order_count,
                COUNT(DISTINCT customer_id) as unique_customers,
                AVG(total_amount) as avg_order_value
              FROM orders 
              WHERE status = 'completed'";
    
    if (!empty($date_filter)) {
        $query .= " AND $date_filter";
    }
    
    $query .= " GROUP BY DATE(created_at) 
                ORDER BY date ASC 
                LIMIT 30";
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSummaryStatistics($db, $date_filter, $date_params) {
    $stats = [];
    
    // Sales stats
    $query = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value,
                COUNT(DISTINCT customer_id) as unique_customers
              FROM orders 
              WHERE status = 'completed'";
    
    if (!empty($date_filter)) {
        $query .= " AND $date_filter";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    $stats = array_merge($stats, $stmt->fetch(PDO::FETCH_ASSOC));
    
    // Customer stats
    $query = "SELECT COUNT(*) as new_customers FROM customers WHERE 1=1";
    if (!empty($date_filter)) {
        $query .= " AND $date_filter";
    }
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    $stats = array_merge($stats, $stmt->fetch(PDO::FETCH_ASSOC));
    
    // Reservation stats
    $query = "SELECT COUNT(*) as total_reservations FROM reservations WHERE 1=1";
    if (!empty($date_filter)) {
        $query .= " AND $date_filter";
    }
    $stmt = $db->prepare($query);
    $stmt->execute($date_params);
    $stats = array_merge($stats, $stmt->fetch(PDO::FETCH_ASSOC));
    
    return $stats;
}

function exportReport($data, $report_type, $date_range, $start_date, $end_date) {
    $filename = "brewhaven_{$report_type}_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add header
    fputcsv($output, ['BrewHaven Cafe - ' . ucfirst(str_replace('_', ' ', $report_type)) . ' Report']);
    fputcsv($output, ['Date Range: ' . $date_range . ($start_date ? " ($start_date to $end_date)" : '')]);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    if (!empty($data)) {
        // Headers
        fputcsv($output, array_keys($data[0]));
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .admin-header {
            background: #6f4e37;
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-nav {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 0;
        }

        .nav-links a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .nav-links a:hover, .nav-links a.active {
            color: #6f4e37;
            border-bottom-color: #6f4e37;
            background: #f8f5f0;
        }

        .main-content {
            max-width: 1400px;
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

        .btn-info {
            background: #17a2b8;
        }

        .btn-danger {
            background: #dc3545;
        }

        /* Report Controls */
        .report-controls {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .control-group {
            display: flex;
            flex-direction: column;
        }

        .control-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }

        .control-group select,
        .control-group input {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
        }

        .control-group select:focus,
        .control-group input:focus {
            border-color: #6f4e37;
            outline: none;
        }

        .control-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #6f4e37;
        }

        .summary-number {
            font-size: 24px;
            font-weight: bold;
            color: #6f4e37;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
        }

        /* Charts */
        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chart-card h3 {
            color: #6f4e37;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .chart-placeholder {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }

        /* Report Table */
        .report-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
        }

        .report-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .report-table tr:hover {
            background: #f8f9fa;
        }

        .number {
            text-align: right;
            font-weight: 600;
        }

        .positive { color: #28a745; }
        .negative { color: #dc3545; }
        .neutral { color: #6c757d; }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .rating-stars {
            color: #ffc107;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-data i {
            font-size: 48px;
            color: #e9ecef;
            margin-bottom: 20px;
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .kpi-value {
            font-size: 20px;
            font-weight: bold;
            color: #6f4e37;
            margin-bottom: 5px;
        }

        .kpi-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .report-table {
                display: block;
                overflow-x: auto;
            }
            
            .nav-links {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-chart-bar"></i> Reports & Analytics
            </h1>
            <div class="header-actions">
                <?php if (!empty($report_data)): ?>
                    <a href="admin_reports.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                <?php endif; ?>
                <a href="admin_dashboard.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Report Controls -->
        <div class="report-controls">
            <form method="GET" action="admin_reports.php">
                <div class="controls-grid">
                    <div class="control-group">
                        <label for="report_type">Report Type</label>
                        <select id="report_type" name="report_type">
                            <option value="sales_summary" <?php echo $report_type === 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                            <option value="customer_analysis" <?php echo $report_type === 'customer_analysis' ? 'selected' : ''; ?>>Customer Analysis</option>
                            <option value="menu_performance" <?php echo $report_type === 'menu_performance' ? 'selected' : ''; ?>>Menu Performance</option>
                            <option value="reservation_analysis" <?php echo $report_type === 'reservation_analysis' ? 'selected' : ''; ?>>Reservation Analysis</option>
                            <option value="review_analysis" <?php echo $report_type === 'review_analysis' ? 'selected' : ''; ?>>Review Analysis</option>
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label for="date_range">Date Range</label>
                        <select id="date_range" name="date_range">
                            <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $date_range === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="this_week" <?php echo $date_range === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="last_week" <?php echo $date_range === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                            <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="this_quarter" <?php echo $date_range === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="this_year" <?php echo $date_range === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="control-group" id="custom_dates" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="control-group" id="custom_dates_end" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                
                <div class="control-actions">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-chart-line"></i> Generate Report
                    </button>
                    <a href="admin_reports.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Summary Statistics -->
        <?php if (!empty($summary_stats)): ?>
        <div class="summary-stats">
            <div class="summary-card">
                <div class="summary-number"><?php echo $summary_stats['total_orders'] ?? 0; ?></div>
                <div class="summary-label">Total Orders</div>
            </div>
            <div class="summary-card">
                <div class="summary-number">₹<?php echo number_format($summary_stats['total_revenue'] ?? 0, 2); ?></div>
                <div class="summary-label">Total Revenue</div>
            </div>
            <div class="summary-card">
                <div class="summary-number">₹<?php echo number_format($summary_stats['avg_order_value'] ?? 0, 2); ?></div>
                <div class="summary-label">Avg Order Value</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $summary_stats['unique_customers'] ?? 0; ?></div>
                <div class="summary-label">Unique Customers</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts -->
        <div class="charts-container">
            <div class="chart-card">
                <h3><?php echo ucfirst(str_replace('_', ' ', $report_type)); ?> Overview</h3>
                <div class="chart-placeholder">
                    <i class="fas fa-chart-bar" style="font-size: 48px; margin-right: 15px;"></i>
                    <div>
                        <div>Chart visualization would be displayed here</div>
                        <small class="neutral">Using Chart.js for interactive charts</small>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>Key Metrics</h3>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-value"><?php echo count($report_data); ?></div>
                        <div class="kpi-label">Records</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">
                            <?php 
                            if ($report_type === 'sales_summary') {
                                echo '₹' . number_format(array_sum(array_column($report_data, 'total_revenue')) ?? 0, 2);
                            } elseif ($report_type === 'review_analysis') {
                                echo number_format(array_sum(array_column($report_data, 'avg_rating')) / count($report_data) ?? 0, 1);
                            } else {
                                echo count($report_data);
                            }
                            ?>
                        </div>
                        <div class="kpi-label">
                            <?php 
                            echo $report_type === 'sales_summary' ? 'Total Revenue' : 
                                 ($report_type === 'review_analysis' ? 'Avg Rating' : 'Total');
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Data Table -->
        <div class="report-table-container">
            <?php if (empty($report_data)): ?>
                <div class="no-data">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Report Data</h3>
                    <p>Select a report type and date range to generate analytics.</p>
                </div>
            <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($report_data[0]) as $column): ?>
                                <th><?php echo ucfirst(str_replace('_', ' ', $column)); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <?php foreach ($row as $key => $value): ?>
                                    <td class="<?php echo is_numeric($value) && !in_array($key, ['id', 'rating']) ? 'number' : ''; ?>">
                                        <?php
                                        switch ($key) {
                                            case 'total_revenue':
                                            case 'total_spent':
                                            case 'price':
                                            case 'avg_order_value':
                                            case 'total_amount':
                                                echo '₹' . number_format($value, 2);
                                                break;
                                            case 'rating':
                                                echo '<span class="rating-stars">';
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $value) {
                                                        echo '<i class="fas fa-star"></i>';
                                                    } else {
                                                        echo '<i class="far fa-star"></i>';
                                                    }
                                                }
                                                echo '</span>';
                                                break;
                                            case 'status':
                                                echo '<span class="status-badge status-' . $value . '">' . ucfirst($value) . '</span>';
                                                break;
                                            case 'is_available':
                                                echo $value ? '<span class="positive">Available</span>' : '<span class="negative">Unavailable</span>';
                                                break;
                                            default:
                                                echo htmlspecialchars($value ?? '');
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Show/hide custom date inputs
        document.getElementById('date_range').addEventListener('change', function() {
            const customDates = document.getElementById('custom_dates');
            const customDatesEnd = document.getElementById('custom_dates_end');
            
            if (this.value === 'custom') {
                customDates.style.display = 'flex';
                customDatesEnd.style.display = 'flex';
            } else {
                customDates.style.display = 'none';
                customDatesEnd.style.display = 'none';
            }
        });

        // Initialize charts (placeholder - you can implement actual Chart.js here)
        function initializeCharts() {
            // This is where you would initialize Chart.js charts
            // Example:
            /*
            const ctx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode(array_column($chart_data, 'revenue')); ?>,
                        borderColor: '#6f4e37',
                        backgroundColor: 'rgba(111, 78, 55, 0.1)'
                    }]
                }
            });
            */
        }

        // Auto-generate report on page load if parameters are set
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.search.includes('report_type')) {
                // Charts would be initialized here
                initializeCharts();
            }
        });

        // Print report
        function printReport() {
            window.print();
        }

        // Export functionality
        document.querySelector('a[href*="export=csv"]')?.addEventListener('click', function(e) {
            if (!confirm('Export this report as CSV?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>