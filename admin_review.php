<?php
// admin_review.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Create reviews table if it doesn't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255),
        rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        title VARCHAR(255),
        comment TEXT,
        review_date DATE NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        is_featured BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    )");
    
    // Insert sample reviews for demonstration
    $check_reviews = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    if ($check_reviews == 0) {
        $sample_reviews = [
            ['John Doe', 'john@example.com', 5, 'Amazing Coffee!', 'The best coffee I\'ve ever had. The atmosphere is wonderful too!', '2024-01-15'],
            ['Sarah Smith', 'sarah@example.com', 4, 'Great Service', 'Friendly staff and delicious pastries. Will definitely come back!', '2024-01-14'],
            ['Mike Johnson', 'mike@example.com', 3, 'Good but crowded', 'Food was good but the place was too crowded during peak hours.', '2024-01-13'],
            ['Emily Davis', 'emily@example.com', 5, 'Perfect Experience', 'Everything was perfect! From the coffee to the service. Highly recommended!', '2024-01-12'],
            ['David Wilson', 'david@example.com', 2, 'Disappointing', 'Coffee was cold and service was slow. Expected better.', '2024-01-11']
        ];
        
        $stmt = $db->prepare("INSERT INTO reviews (customer_name, customer_email, rating, title, comment, review_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($sample_reviews as $review) {
            $status = $review[2] >= 4 ? 'approved' : ($review[2] >= 3 ? 'pending' : 'pending');
            $stmt->execute([$review[0], $review[1], $review[2], $review[3], $review[4], $review[5], $status]);
        }
    }
} catch (Exception $e) {
    error_log("Reviews setup error: " . $e->getMessage());
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$review_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Process form submissions
if ($_POST) {
    if (isset($_POST['update_review_status'])) {
        $review_id = intval($_POST['review_id']);
        $status = $_POST['status'];
        $admin_notes = trim($_POST['admin_notes']);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE reviews SET status = ?, admin_notes = ?, is_featured = ? WHERE id = ?");
            $stmt->execute([$status, $admin_notes, $is_featured, $review_id]);
            $message = 'success=review_updated';
        } catch (Exception $e) {
            $message = 'error=update_failed';
        }
    }
    elseif (isset($_POST['delete_review'])) {
        try {
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$review_id]);
            $message = 'success=review_deleted';
            $action = 'list';
        } catch (Exception $e) {
            $message = 'error=delete_failed';
        }
    }
    elseif (isset($_POST['add_review'])) {
        $customer_name = trim($_POST['customer_name']);
        $customer_email = trim($_POST['customer_email']);
        $rating = intval($_POST['rating']);
        $title = trim($_POST['title']);
        $comment = trim($_POST['comment']);
        $review_date = $_POST['review_date'];
        $status = $_POST['status'];
        
        try {
            $stmt = $db->prepare("INSERT INTO reviews (customer_name, customer_email, rating, title, comment, review_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_name, $customer_email, $rating, $title, $comment, $review_date, $status]);
            $message = 'success=review_added';
        } catch (Exception $e) {
            $message = 'error=add_failed';
        }
    }
    
    header("Location: admin_review.php?action=$action&id=$review_id&$message");
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$query = "SELECT * FROM reviews WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if ($rating_filter !== 'all') {
    $query .= " AND rating = ?";
    $params[] = $rating_filter;
}

if (!empty($date_from)) {
    $query .= " AND review_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND review_date <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (customer_name LIKE ? OR title LIKE ? OR comment LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY created_at DESC";

// Get reviews
$stmt = $db->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get review details for view/edit
$review = null;
if ($action === 'view' && $review_id > 0) {
    $stmt = $db->prepare("SELECT * FROM reviews WHERE id = ?");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$review) {
        header("Location: admin_review.php?error=review_not_found");
        exit;
    }
}

// Get statistics
$stmt = $db->query("SELECT 
    COUNT(*) as total_reviews,
    AVG(rating) as avg_rating,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_reviews,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reviews,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reviews,
    SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_reviews
    FROM reviews");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get rating distribution
$stmt = $db->query("SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating ORDER BY rating DESC");
$rating_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent reviews for dashboard
$stmt = $db->query("SELECT * FROM reviews WHERE status = 'approved' ORDER BY created_at DESC LIMIT 3");
$recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Review Specific Styles */
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

        /* Rating Distribution */
        .rating-distribution {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }

        .rating-stars {
            color: #ffc107;
            width: 80px;
        }

        .rating-count {
            width: 40px;
            text-align: right;
            font-weight: 600;
        }

        .rating-progress {
            flex: 1;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            height: 10px;
        }

        .rating-progress-fill {
            height: 100%;
            background: #ffc107;
            border-radius: 10px;
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

        /* Reviews Grid */
        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .review-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.3s;
        }

        .review-card:hover {
            transform: translateY(-5px);
        }

        .review-card.featured {
            border: 2px solid #ffc107;
            background: #fffdf5;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .review-customer {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6f4e37;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .customer-info h4 {
            margin: 0;
            color: #495057;
        }

        .customer-info p {
            margin: 0;
            color: #6c757d;
            font-size: 12px;
        }

        .review-rating {
            color: #ffc107;
            font-size: 18px;
        }

        .review-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .review-comment {
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .review-date {
            color: #6c757d;
            font-size: 12px;
        }

        .review-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .featured-badge {
            background: #ffc107;
            color: #212529;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        /* Review Details View */
        .review-details-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .review-details-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .review-details-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .review-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
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

        .status-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
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
        .form-group input,
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .no-reviews {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-reviews i {
            font-size: 48px;
            color: #e9ecef;
            margin-bottom: 20px;
        }

        /* Star Rating */
        .stars {
            color: #ffc107;
        }

        .stars .far {
            color: #e4e5e9;
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
            
            .reviews-grid {
                grid-template-columns: 1fr;
            }
            
            .review-details-content {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-star"></i> Review Management
            </h1>
            <div class="header-actions">
                <a href="admin_review.php?action=add" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Review
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
                    'review_updated' => 'Review updated successfully!',
                    'review_added' => 'Review added successfully!',
                    'review_deleted' => 'Review deleted successfully!'
                ];
                echo $successMessages[$_GET['success']] ?? 'Operation completed successfully!';
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                $errorMessages = [
                    'update_failed' => 'Failed to update review.',
                    'add_failed' => 'Failed to add review.',
                    'delete_failed' => 'Failed to delete review.',
                    'review_not_found' => 'Review not found.'
                ];
                echo $errorMessages[$_GET['error']] ?? 'An error occurred.';
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-number"><?php echo $stats['total_reviews']; ?></div>
                <div class="stat-label">Total Reviews</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-number"><?php echo number_format($stats['avg_rating'], 1); ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo $stats['approved_reviews']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $stats['pending_reviews']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- Rating Distribution -->
        <div class="rating-distribution">
            <h3>Rating Distribution</h3>
            <?php
            $total_reviews = $stats['total_reviews'];
            foreach ($rating_distribution as $dist): 
                $percentage = $total_reviews > 0 ? ($dist['count'] / $total_reviews) * 100 : 0;
            ?>
                <div class="rating-bar">
                    <div class="rating-stars">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $dist['rating']) {
                                echo '<i class="fas fa-star"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </div>
                    <div class="rating-count"><?php echo $dist['count']; ?></div>
                    <div class="rating-progress">
                        <div class="rating-progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div style="width: 40px; text-align: right; font-size: 12px; color: #6c757d;">
                        <?php echo number_format($percentage, 1); ?>%
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="admin_review.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="rating">Rating</label>
                        <select id="rating" name="rating">
                            <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                            <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                            <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
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
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Customer name, title, comment..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="admin_review.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <?php if ($action === 'add'): ?>
            <!-- Add Review Form -->
            <div class="review-details-container">
                <h2>Add New Review</h2>
                
                <form method="POST">
                    <input type="hidden" name="add_review" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="customer_name">Customer Name *</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_email">Customer Email</label>
                            <input type="email" id="customer_email" name="customer_email">
                        </div>
                        
                        <div class="form-group">
                            <label for="rating">Rating *</label>
                            <select id="rating" name="rating" required>
                                <option value="">Select rating</option>
                                <option value="5">5 Stars - Excellent</option>
                                <option value="4">4 Stars - Very Good</option>
                                <option value="3">3 Stars - Good</option>
                                <option value="2">2 Stars - Fair</option>
                                <option value="1">1 Star - Poor</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="review_date">Review Date *</label>
                            <input type="date" id="review_date" name="review_date" required 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Review Title</label>
                        <input type="text" id="title" name="title" placeholder="Brief title for the review...">
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Review Comment *</label>
                        <textarea id="comment" name="comment" required placeholder="Detailed review comment..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin_review.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Review
                        </button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'view' && $review): ?>
            <!-- Review Details View -->
            <div class="review-details-container">
                <div class="review-details-header">
                    <div>
                        <h2>Review Details</h2>
                        <p>Submitted on <?php echo date('F j, Y', strtotime($review['created_at'])); ?></p>
                    </div>
                    <div class="action-buttons">
                        <a href="admin_review.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.')">
                            <input type="hidden" name="delete_review" value="1">
                            <input type="hidden" name="id" value="<?php echo $review['id']; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>

                <div class="review-details-content">
                    <div class="review-info">
                        <div class="info-card">
                            <h4>Customer Information</h4>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($review['customer_name']); ?></p>
                            <?php if ($review['customer_email']): ?>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($review['customer_email']); ?></p>
                            <?php endif; ?>
                            <p><strong>Review Date:</strong> <?php echo date('F j, Y', strtotime($review['review_date'])); ?></p>
                        </div>
                        
                        <div class="info-card">
                            <h4>Review Content</h4>
                            <p><strong>Rating:</strong> 
                                <span class="stars">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $review['rating']) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </span>
                                (<?php echo $review['rating']; ?>/5)
                            </p>
                            <?php if ($review['title']): ?>
                                <p><strong>Title:</strong> <?php echo htmlspecialchars($review['title']); ?></p>
                            <?php endif; ?>
                            <p><strong>Comment:</strong></p>
                            <p style="background: white; padding: 15px; border-radius: 5px; border-left: 3px solid #6f4e37;">
                                <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Status Update Form -->
                    <form method="POST" class="status-form">
                        <input type="hidden" name="update_review_status" value="1">
                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="pending" <?php echo $review['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $review['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $review['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="is_featured">Featured</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_featured" name="is_featured" value="1" 
                                           <?php echo $review['is_featured'] ? 'checked' : ''; ?>>
                                    <label for="is_featured">Feature this review</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_notes">Admin Notes</label>
                            <textarea id="admin_notes" name="admin_notes" placeholder="Internal notes about this review..."><?php echo htmlspecialchars($review['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- Reviews List -->
            <?php if (empty($reviews)): ?>
                <div class="no-reviews">
                    <i class="fas fa-star"></i>
                    <h3>No Reviews Found</h3>
                    <p>There are no reviews matching your current filters.</p>
                    <a href="admin_review.php" class="btn">Clear Filters</a>
                    <a href="admin_review.php?action=add" class="btn btn-success" style="margin-left: 10px;">
                        <i class="fas fa-plus"></i> Add First Review
                    </a>
                </div>
            <?php else: ?>
                <div class="reviews-grid">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-card <?php echo $rev['is_featured'] ? 'featured' : ''; ?>">
                            <div class="review-header">
                                <div class="review-customer">
                                    <div class="customer-avatar">
                                        <?php echo strtoupper(substr($rev['customer_name'], 0, 1)); ?>
                                    </div>
                                    <div class="customer-info">
                                        <h4><?php echo htmlspecialchars($rev['customer_name']); ?></h4>
                                        <p><?php echo date('M j, Y', strtotime($rev['review_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rev['rating']) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <?php if ($rev['title']): ?>
                                <div class="review-title"><?php echo htmlspecialchars($rev['title']); ?></div>
                            <?php endif; ?>
                            
                            <div class="review-comment">
                                <?php echo htmlspecialchars(substr($rev['comment'], 0, 150)); ?>
                                <?php if (strlen($rev['comment']) > 150): ?>...<?php endif; ?>
                            </div>
                            
                            <div class="review-footer">
                                <div class="review-date">
                                    <?php echo date('M j, Y', strtotime($rev['created_at'])); ?>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <?php if ($rev['is_featured']): ?>
                                        <span class="featured-badge">Featured</span>
                                    <?php endif; ?>
                                    <span class="review-status status-<?php echo $rev['status']; ?>">
                                        <?php echo ucfirst($rev['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="action-buttons" style="margin-top: 15px;">
                                <a href="admin_review.php?action=view&id=<?php echo $rev['id']; ?>" 
                                   class="btn btn-small btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this review?')">
                                    <input type="hidden" name="delete_review" value="1">
                                    <input type="hidden" name="id" value="<?php echo $rev['id']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Auto-focus search on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value === '') {
                searchInput.focus();
            }
        });

        // Date validation
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        
        if (dateFrom && dateTo) {
            dateFrom.addEventListener('change', function() {
                if (dateTo.value && this.value > dateTo.value) {
                    dateTo.value = this.value;
                }
            });
            
            dateTo.addEventListener('change', function() {
                if (dateFrom.value && this.value < dateFrom.value) {
                    dateFrom.value = this.value;
                }
            });
        }

        // Quick status update
        function quickUpdateStatus(reviewId, newStatus) {
            if (confirm('Are you sure you want to update this review status to ' + newStatus + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_review.php';
                
                const reviewIdInput = document.createElement('input');
                reviewIdInput.type = 'hidden';
                reviewIdInput.name = 'review_id';
                reviewIdInput.value = reviewId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = newStatus;
                
                const updateInput = document.createElement('input');
                updateInput.type = 'hidden';
                updateInput.name = 'update_review_status';
                updateInput.value = '1';
                
                form.appendChild(reviewIdInput);
                form.appendChild(statusInput);
                form.appendChild(updateInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Rating preview
        const ratingSelect = document.getElementById('rating');
        const ratingPreview = document.createElement('div');
        ratingPreview.className = 'stars';
        ratingPreview.style.marginTop = '10px';
        
        if (ratingSelect) {
            ratingSelect.parentNode.appendChild(ratingPreview);
            
            function updateRatingPreview() {
                const rating = parseInt(ratingSelect.value) || 0;
                let starsHtml = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= rating) {
                        starsHtml += '<i class="fas fa-star" style="color: #ffc107;"></i>';
                    } else {
                        starsHtml += '<i class="far fa-star" style="color: #e4e5e9;"></i>';
                    }
                }
                ratingPreview.innerHTML = starsHtml;
                ratingPreview.style.display = rating ? 'block' : 'none';
            }
            
            ratingSelect.addEventListener('change', updateRatingPreview);
            updateRatingPreview(); // Initial call
        }
    </script>
</body>
</html>