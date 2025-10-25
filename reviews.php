<?php
// review.php
require_once 'header.php';
require_once 'config.php';

// Safe output function to handle null values
function safe_output($value, $default = '') {
    if ($value === null) {
        return htmlspecialchars($default);
    }
    return htmlspecialchars((string)$value);
}

// Safe substring function
function safe_substr($value, $start, $length, $default = '') {
    if ($value === null) {
        return htmlspecialchars($default);
    }
    return htmlspecialchars(substr($value, $start, $length));
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$rating = 0;

// Handle review submission
if ($_POST && isset($_POST['submit_review'])) {
    // Check if user is logged in
    if (!isset($_SESSION['customer_id'])) {
        $message = '<div class="alert error">Please log in to submit a review.</div>';
    } else {
        $customer_id = $_SESSION['customer_id'];
        $menu_item_id = $_POST['menu_item_id'];
        $rating = $_POST['rating'];
        $comment = $_POST['comment'];
        $title = !empty($_POST['title']) ? $_POST['title'] : null;
        
        // Get customer details
        $customer_query = "SELECT name, email FROM customers WHERE id = ?";
        $customer_stmt = $db->prepare($customer_query);
        $customer_stmt->execute([$customer_id]);
        $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            // Check if user has already reviewed this item
            $check_query = "SELECT * FROM reviews WHERE customer_id = ? AND menu_item_id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$customer_id, $menu_item_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $message = '<div class="alert error">You have already reviewed this item.</div>';
            } else {
                // Insert new review with all required fields
                $query = "INSERT INTO reviews (customer_id, customer_name, customer_email, menu_item_id, rating, title, comment, review_date) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                $review_date = date('Y-m-d');
                
                if ($stmt->execute([$customer_id, $customer['name'], $customer['email'], $menu_item_id, $rating, $title, $comment, $review_date])) {
                    $message = '<div class="alert success">Thank you for your review! It will be visible after approval.</div>';
                    
                    // Update loyalty points (10 points per review)
                    $points_query = "INSERT INTO loyalty_points (customer_id, points) VALUES (?, 10) 
                                    ON DUPLICATE KEY UPDATE points = points + 10";
                    $points_stmt = $db->prepare($points_query);
                    $points_stmt->execute([$customer_id]);
                    
                    // Clear form
                    $rating = 0;
                    $_POST = array();
                } else {
                    $message = '<div class="alert error">Error submitting review. Please try again.</div>';
                }
            }
        } else {
            $message = '<div class="alert error">Customer not found.</div>';
        }
    }
}

// Get all menu items for the review form
$menu_query = "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY name";
$menu_stmt = $db->prepare($menu_query);
$menu_stmt->execute();
$menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved reviews with customer and menu item information
$reviews_query = "SELECT r.*, c.name as customer_name, m.name as menu_item_name, m.image_url as menu_item_image
                  FROM reviews r 
                  LEFT JOIN customers c ON r.customer_id = c.id 
                  LEFT JOIN menu_items m ON r.menu_item_id = m.id 
                  WHERE r.status = 'approved'
                  ORDER BY r.is_featured DESC, r.created_at DESC";
$reviews_stmt = $db->prepare($reviews_query);
$reviews_stmt->execute();
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average ratings for each menu item (only approved reviews)
$avg_ratings_query = "SELECT menu_item_id, AVG(rating) as avg_rating, COUNT(*) as review_count 
                      FROM reviews 
                      WHERE status = 'approved'
                      GROUP BY menu_item_id";
$avg_ratings_stmt = $db->prepare($avg_ratings_query);
$avg_ratings_stmt->execute();
$avg_ratings = $avg_ratings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a lookup array for average ratings
$avg_ratings_lookup = [];
foreach ($avg_ratings as $avg) {
    $avg_ratings_lookup[$avg['menu_item_id']] = [
        'avg_rating' => round($avg['avg_rating'], 1),
        'review_count' => $avg['review_count']
    ];
}

// Get featured reviews for the sidebar
$featured_reviews_query = "SELECT r.*, m.name as menu_item_name 
                          FROM reviews r 
                          LEFT JOIN menu_items m ON r.menu_item_id = m.id 
                          WHERE r.status = 'approved' AND r.is_featured = 1 
                          ORDER BY r.created_at DESC 
                          LIMIT 3";
$featured_reviews_stmt = $db->prepare($featured_reviews_query);
$featured_reviews_stmt->execute();
$featured_reviews = $featured_reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="reviews-section">
    <div class="container">
        <div class="section-title">
            <h2>Customer Reviews</h2>
            <p>See what our customers are saying about our menu items</p>
        </div>

        <?php echo $message; ?>

        <div class="reviews-layout">
            <!-- Main Content -->
            <div class="reviews-main">
                <!-- Review Form -->
                <div class="review-form-container">
                    <h3><i class="fas fa-edit"></i> Write a Review</h3>
                    <form method="POST" class="review-form">
                        <div class="form-group">
                            <label for="menu_item">Select Menu Item *</label>
                            <select id="menu_item" name="menu_item_id" class="form-control" required>
                                <option value="">Choose a menu item...</option>
                                <?php foreach($menu_items as $item): ?>
                                    <option value="<?php echo safe_output($item['id']); ?>" <?php echo isset($_POST['menu_item_id']) && $_POST['menu_item_id'] == $item['id'] ? 'selected' : ''; ?>>
                                        <?php echo safe_output($item['name']); ?> - ₹<?php echo safe_output($item['price'] ?? '0.00'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="title">Review Title (Optional)</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   placeholder="Brief title for your review..."
                                   value="<?php echo isset($_POST['title']) ? safe_output($_POST['title']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>Your Rating *</label>
                            <div class="star-rating" id="starRating">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="far fa-star" data-rating="<?php echo $i; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" value="<?php echo isset($_POST['rating']) ? $_POST['rating'] : '0'; ?>" required>
                            <span id="ratingText" class="rating-text">
                                <?php echo isset($_POST['rating']) ? 
                                    ['0' => 'Select a rating', '1' => 'Poor', '2' => 'Fair', '3' => 'Good', '4' => 'Very Good', '5' => 'Excellent'][$_POST['rating']] : 
                                    'Select a rating'; ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="comment">Your Review *</label>
                            <textarea id="comment" name="comment" class="form-control" rows="5" 
                                      placeholder="Share your detailed experience with this menu item..." required><?php echo isset($_POST['comment']) ? safe_output($_POST['comment']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <button type="submit" name="submit_review" class="btn btn-primary btn-block">
                                <i class="fas fa-paper-plane"></i> 
                                <?php echo isset($_SESSION['customer_id']) ? 'Submit Review' : 'Login to Review'; ?>
                            </button>
                            <?php if(!isset($_SESSION['customer_id'])): ?>
                                <p class="login-prompt">
                                    <a href="login.php">Login</a> or <a href="register.php">register</a> to submit a review
                                </p>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- All Reviews -->
                <div class="all-reviews">
                    <h3><i class="fas fa-comments"></i> Customer Reviews</h3>
                    
                    <?php if (empty($reviews)): ?>
                        <div class="no-reviews">
                            <i class="fas fa-comment-slash"></i>
                            <h4>No Reviews Yet</h4>
                            <p>Be the first to review our menu items!</p>
                        </div>
                    <?php else: ?>
                        <div class="reviews-list">
                            <?php foreach($reviews as $review): ?>
                                <div class="review-card <?php echo $review['is_featured'] ? 'featured' : ''; ?>">
                                    <?php if($review['is_featured']): ?>
                                        <div class="featured-badge">
                                            <i class="fas fa-star"></i> Featured Review
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar">
                                                <?php echo strtoupper(substr($review['customer_name'] ?? 'A', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="reviewer-name"><?php echo safe_output($review['customer_name'], 'Anonymous'); ?></div>
                                                <div class="review-date">
                                                    <?php echo date('M j, Y', strtotime($review['review_date'] ?? 'now')); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="review-rating">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= ($review['rating'] ?? 0) ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="rating-number">(<?php echo safe_output($review['rating'] ?? '0'); ?>.0)</span>
                                        </div>
                                    </div>
                                    
                                    <?php if(!empty($review['title'])): ?>
                                        <h4 class="review-title"><?php echo safe_output($review['title']); ?></h4>
                                    <?php endif; ?>
                                    
                                    <div class="review-item">
                                        <i class="fas fa-utensils"></i>
                                        Reviewed: <strong><?php echo safe_output($review['menu_item_name'], 'Menu Item'); ?></strong>
                                    </div>
                                    
                                    <div class="review-comment">
                                        <?php echo nl2br(safe_output($review['comment'] ?? '')); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="reviews-sidebar">
                <!-- Featured Reviews -->
                <?php if(!empty($featured_reviews)): ?>
                    <div class="sidebar-widget">
                        <h4><i class="fas fa-star"></i> Featured Reviews</h4>
                        <div class="featured-reviews-list">
                            <?php foreach($featured_reviews as $featured): ?>
                                <div class="featured-review-item">
                                    <div class="featured-review-header">
                                        <div class="featured-reviewer">
                                            <?php echo safe_output($featured['customer_name'], 'Anonymous'); ?>
                                        </div>
                                        <div class="featured-rating">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= ($featured['rating'] ?? 0) ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="featured-item">
                                        <?php echo safe_output($featured['menu_item_name'], 'Menu Item'); ?>
                                    </div>
                                    <div class="featured-comment">
                                        "<?php echo safe_substr($featured['comment'] ?? '', 0, 100, 'No comment provided...'); ?>..."
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Menu Items with Ratings -->
                <div class="sidebar-widget">
                    <h4><i class="fas fa-chart-bar"></i> Top Rated Items</h4>
                    <div class="top-rated-items">
                        <?php 
                        $top_rated = [];
                        foreach($menu_items as $item): 
                            $item_id = $item['id'];
                            $has_rating = isset($avg_ratings_lookup[$item_id]);
                            $avg_rating = $has_rating ? $avg_ratings_lookup[$item_id]['avg_rating'] : 0;
                            $review_count = $has_rating ? $avg_ratings_lookup[$item_id]['review_count'] : 0;
                            
                            if($avg_rating >= 4.0): 
                        ?>
                            <div class="top-rated-item">
                                <div class="item-image">
                                    <img src="<?php echo !empty($item['image_url']) ? safe_output($item['image_url']) : '/images/placeholder.jpg'; ?>" alt="<?php echo safe_output($item['name']); ?>">
                                </div>
                                <div class="item-details">
                                    <h5><?php echo safe_output($item['name']); ?></h5>
                                    <div class="item-rating">
                                        <div class="stars">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= round($avg_rating) ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-value"><?php echo safe_output($avg_rating); ?></span>
                                    </div>
                                    <div class="item-price">₹<?php echo safe_output($item['price'] ?? '0.00'); ?></div>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        
                        if(empty($top_rated)): 
                        ?>
                            <p class="no-ratings">No highly rated items yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Stats -->
                <div class="sidebar-widget">
                    <h4><i class="fas fa-chart-pie"></i> Review Statistics</h4>
                    <div class="review-stats">
                        <?php
                        $total_reviews = count($reviews);
                        $average_rating = 0;
                        if ($total_reviews > 0) {
                            $total_rating = 0;
                            foreach($reviews as $review) {
                                $total_rating += ($review['rating'] ?? 0);
                            }
                            $average_rating = round($total_rating / $total_reviews, 1);
                        }
                        $featured_count = count($featured_reviews ?? []);
                        ?>
                        <div class="stat-item">
                            <span class="stat-label">Total Reviews:</span>
                            <span class="stat-value"><?php echo safe_output($total_reviews); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Average Rating:</span>
                            <span class="stat-value"><?php echo safe_output($average_rating); ?>/5</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Featured Reviews:</span>
                            <span class="stat-value"><?php echo safe_output($featured_count); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Rest of your CSS and JavaScript remains the same -->
<style>
/* Reviews Page Styles */
:root {
    --primary: #8B4513;
    --primary-light: #A0522D;
    --secondary: #D2691E;
    --accent: #CD853F;
    --warning: #FFD700;
    --success: #28a745;
    --danger: #dc3545;
    --light: #F5F5DC;
    --dark: #2C1810;
    --gray: #6c757d;
    --white: #ffffff;
    --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
    --transition: all 0.3s ease;
}

.reviews-section {
    background: linear-gradient(135deg, #FDF5E6 0%, #FFEBCD 100%);
    min-height: 100vh;
    padding: 40px 0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.section-title {
    text-align: center;
    margin-bottom: 50px;
}

.section-title h2 {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 10px;
    font-weight: 700;
}

.section-title p {
    font-size: 1.1rem;
    color: var(--gray);
    max-width: 600px;
    margin: 0 auto;
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    font-weight: 500;
    border-left: 4px solid;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border-left-color: var(--success);
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: var(--danger);
}

/* Layout */
.reviews-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 40px;
    margin-bottom: 60px;
}

.reviews-main {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.reviews-sidebar {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

/* Review Form */
.review-form-container {
    background: var(--white);
    padding: 40px;
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    border: 1px solid #e8d6c5;
    position: relative;
    overflow: hidden;
}

.review-form-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.review-form-container h3 {
    margin-bottom: 30px;
    color: var(--primary);
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
}

.review-form-container h3 i {
    color: var(--secondary);
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
    font-size: 1rem;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e8d6c5;
    border-radius: 10px;
    font-size: 1rem;
    transition: var(--transition);
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
}

.form-control::placeholder {
    color: #a8a8a8;
}

/* Star Rating */
.star-rating {
    display: flex;
    gap: 8px;
    margin: 15px 0;
}

.star-rating i {
    font-size: 2.2rem;
    color: #e0e0e0;
    cursor: pointer;
    transition: var(--transition);
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.star-rating i:hover,
.star-rating i.active {
    color: var(--warning);
    transform: scale(1.15);
    filter: drop-shadow(0 2px 4px rgba(255, 215, 0, 0.3));
}

.rating-text {
    display: block;
    margin-top: 10px;
    font-size: 0.95rem;
    color: var(--gray);
    font-weight: 500;
    font-style: italic;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: var(--transition);
    text-align: center;
    justify-content: center;
}

.btn-block {
    width: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: var(--white);
    box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
}

.login-prompt {
    text-align: center;
    margin-top: 15px;
    font-size: 0.9rem;
    color: var(--gray);
}

.login-prompt a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.login-prompt a:hover {
    color: var(--primary-light);
    text-decoration: underline;
}

/* All Reviews Section */
.all-reviews {
    background: var(--white);
    padding: 40px;
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    border: 1px solid #e8d6c5;
}

.all-reviews h3 {
    margin-bottom: 30px;
    color: var(--primary);
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
}

.all-reviews h3 i {
    color: var(--secondary);
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* Review Card */
.review-card {
    background: var(--white);
    padding: 30px;
    border-radius: 15px;
    border-left: 5px solid var(--primary);
    transition: var(--transition);
    position: relative;
    box-shadow: var(--shadow);
    border: 1px solid #f0e6d6;
}

.review-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.review-card.featured {
    background: linear-gradient(135deg, #FFF9E6 0%, #FFF 100%);
    border-left: 5px solid var(--warning);
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.2);
}

.featured-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, var(--warning), #FFC107);
    color: #8B6500;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.reviewer-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.reviewer-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
}

.reviewer-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
}

.review-date {
    font-size: 0.85rem;
    color: var(--gray);
    margin-top: 4px;
}

.review-rating {
    color: var(--warning);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.1rem;
}

.rating-number {
    font-size: 0.9rem;
    color: var(--gray);
    font-weight: 600;
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 6px;
}

.review-title {
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 15px;
    font-size: 1.3rem;
    line-height: 1.4;
}

.review-item {
    margin-bottom: 15px;
    font-size: 0.95rem;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    border-left: 3px solid var(--accent);
}

.review-item i {
    color: var(--accent);
}

.review-comment {
    line-height: 1.7;
    color: var(--dark);
    font-size: 1rem;
    background: #fafafa;
    padding: 20px;
    border-radius: 10px;
    border-left: 3px solid #e8d6c5;
}

/* Sidebar Widgets */
.sidebar-widget {
    background: var(--white);
    padding: 30px;
    border-radius: 15px;
    box-shadow: var(--shadow);
    border: 1px solid #e8d6c5;
    transition: var(--transition);
}

.sidebar-widget:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.sidebar-widget h4 {
    margin-bottom: 20px;
    color: var(--primary);
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    border-bottom: 2px solid #f0e6d6;
    padding-bottom: 10px;
}

.sidebar-widget h4 i {
    color: var(--secondary);
}

/* Featured Reviews */
.featured-reviews-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.featured-review-item {
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 12px;
    border-left: 4px solid var(--warning);
    transition: var(--transition);
    border: 1px solid #f0e6d6;
}

.featured-review-item:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow);
}

.featured-review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.featured-reviewer {
    font-weight: 600;
    color: var(--primary);
    font-size: 0.95rem;
}

.featured-rating {
    color: var(--warning);
    font-size: 0.9rem;
}

.featured-item {
    font-size: 0.85rem;
    color: var(--gray);
    margin-bottom: 8px;
    font-weight: 500;
}

.featured-comment {
    font-size: 0.85rem;
    color: var(--dark);
    font-style: italic;
    line-height: 1.5;
    background: var(--white);
    padding: 12px;
    border-radius: 8px;
    border-left: 2px solid var(--accent);
}

/* Top Rated Items */
.top-rated-items {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.top-rated-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 12px;
    transition: var(--transition);
    border: 1px solid #f0e6d6;
}

.top-rated-item:hover {
    transform: translateX(8px);
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: var(--shadow);
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid var(--accent);
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details {
    flex: 1;
}

.item-details h5 {
    margin: 0 0 8px 0;
    color: var(--primary);
    font-size: 1rem;
    font-weight: 600;
}

.item-rating {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}

.item-rating .stars {
    color: var(--warning);
    font-size: 0.9rem;
}

.rating-value {
    font-size: 0.85rem;
    color: var(--gray);
    font-weight: 600;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 4px;
}

.item-price {
    font-weight: 700;
    color: var(--success);
    font-size: 1rem;
}

/* Review Stats */
.review-stats {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0e6d6;
    transition: var(--transition);
}

.stat-item:hover {
    background: #fafafa;
    padding: 12px 15px;
    border-radius: 8px;
    transform: translateX(5px);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: var(--gray);
    font-size: 0.95rem;
    font-weight: 500;
}

.stat-value {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* No Reviews/No Ratings */
.no-reviews, .no-ratings {
    text-align: center;
    padding: 50px 30px;
    color: var(--gray);
}

.no-reviews i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #e8d6c5;
    opacity: 0.7;
}

.no-reviews h4 {
    margin-bottom: 15px;
    color: var(--gray);
    font-size: 1.5rem;
    font-weight: 600;
}

.no-reviews p {
    font-size: 1.1rem;
    margin-bottom: 0;
}

.no-ratings {
    padding: 30px 20px;
    font-style: italic;
    color: var(--gray);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .reviews-layout {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .reviews-sidebar {
        order: -1;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 0 15px;
    }
    
    .section-title h2 {
        font-size: 2rem;
    }
    
    .review-form-container,
    .all-reviews,
    .sidebar-widget {
        padding: 25px;
    }
    
    .review-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .reviewer-info {
        width: 100%;
    }
    
    .star-rating i {
        font-size: 1.8rem;
    }
    
    .featured-badge {
        position: static;
        margin-bottom: 15px;
        align-self: flex-start;
    }
}

@media (max-width: 480px) {
    .reviewer-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .reviewer-avatar {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
    
    .review-card {
        padding: 20px;
    }
    
    .review-form-container h3,
    .all-reviews h3 {
        font-size: 1.5rem;
    }
    
    .top-rated-item {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
    
    .item-image {
        width: 80px;
        height: 80px;
    }
}

/* Animation for new reviews */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.review-card {
    animation: slideInUp 0.5s ease-out;
}

/* Loading state for form submission */
.review-form.loading {
    opacity: 0.7;
    pointer-events: none;
}

.review-form.loading::after {
    content: 'Submitting...';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: var(--primary);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    z-index: 10;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Star rating functionality
    const stars = document.querySelectorAll('#starRating i');
    const ratingValue = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');
    
    const ratingTexts = {
        0: 'Select a rating',
        1: 'Poor - Very disappointed',
        2: 'Fair - Could be better', 
        3: 'Good - Met expectations',
        4: 'Very Good - Above expectations',
        5: 'Excellent - Outstanding experience'
    };
    
    // Initialize with current rating if exists
    const currentRating = parseInt(ratingValue.value);
    if (currentRating > 0) {
        updateStars(currentRating);
        ratingText.textContent = ratingTexts[currentRating];
    }
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            ratingValue.value = rating;
            ratingText.textContent = ratingTexts[rating];
            updateStars(rating);
            
            // Add click animation
            this.style.transform = 'scale(1.3)';
            setTimeout(() => {
                this.style.transform = 'scale(1.15)';
            }, 150);
        });
        
        star.addEventListener('mouseover', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            updateStars(rating);
        });
    });
    
    // Reset stars when mouse leaves the rating container
    document.getElementById('starRating').addEventListener('mouseleave', function() {
        const currentRating = parseInt(ratingValue.value);
        updateStars(currentRating);
    });
    
    function updateStars(rating) {
        stars.forEach((star, index) => {
            if (index < rating) {
                star.className = 'fas fa-star active';
                star.style.animation = `pulse ${0.3 + (index * 0.1)}s ease-out`;
            } else {
                star.className = 'far fa-star';
                star.style.animation = '';
            }
        });
    }

    // Form validation and submission
    const reviewForm = document.querySelector('.review-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            const rating = parseInt(ratingValue.value);
            const menuItem = document.getElementById('menu_item');
            const comment = document.getElementById('comment');
            
            let isValid = true;
            let errorMessage = '';
            
            // Validate rating
            if (rating < 1 || rating > 5) {
                isValid = false;
                errorMessage = 'Please select a rating between 1 and 5 stars.';
                highlightError(stars[0].parentElement);
            }
            
            // Validate menu item
            if (!menuItem.value) {
                isValid = false;
                errorMessage = 'Please select a menu item to review.';
                highlightError(menuItem);
            }
            
            // Validate comment
            if (!comment.value.trim()) {
                isValid = false;
                errorMessage = 'Please write a review comment.';
                highlightError(comment);
            }
            
            if (!isValid) {
                e.preventDefault();
                showAlert(errorMessage, 'error');
                return false;
            }
            
            // Show loading state
            showLoadingState();
        });
    }

    // Auto-expand textarea as user types
    const commentTextarea = document.getElementById('comment');
    if (commentTextarea) {
        commentTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Trigger initial resize
        setTimeout(() => {
            commentTextarea.style.height = (commentTextarea.scrollHeight) + 'px';
        }, 100);
    }

    // Menu item search/filter functionality
    const menuItemSelect = document.getElementById('menu_item');
    if (menuItemSelect) {
        const menuItems = Array.from(menuItemSelect.options).slice(1); // Exclude first option
        
        // Create search input
        const searchWrapper = document.createElement('div');
        searchWrapper.className = 'search-wrapper';
        searchWrapper.innerHTML = `
            <input type="text" id="menuSearch" placeholder="Search menu items..." class="form-control">
            <i class="fas fa-search search-icon"></i>
        `;
        menuItemSelect.parentNode.insertBefore(searchWrapper, menuItemSelect);
        
        const searchInput = document.getElementById('menuSearch');
        const searchIcon = searchWrapper.querySelector('.search-icon');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            // Show/hide options based on search
            menuItems.forEach(option => {
                const text = option.text.toLowerCase();
                option.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
            
            // Show first matching option
            const firstVisible = menuItems.find(option => option.style.display !== 'none');
            if (firstVisible) {
                menuItemSelect.value = firstVisible.value;
            }
        });
        
        searchIcon.addEventListener('click', function() {
            searchInput.focus();
        });
    }

    // Review filtering and sorting
    const reviewsList = document.querySelector('.reviews-list');
    if (reviewsList) {
        const reviewCards = Array.from(reviewsList.querySelectorAll('.review-card'));
        
        // Add filter buttons
        const filterContainer = document.createElement('div');
        filterContainer.className = 'review-filters';
        filterContainer.innerHTML = `
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All Reviews</button>
                <button class="filter-btn" data-filter="featured">Featured</button>
                <button class="filter-btn" data-filter="5">5 Stars</button>
                <button class="filter-btn" data-filter="4">4+ Stars</button>
                <button class="filter-btn" data-filter="3">3+ Stars</button>
            </div>
            <div class="sort-options">
                <select class="sort-select form-control">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="highest">Highest Rated</option>
                    <option value="lowest">Lowest Rated</option>
                </select>
            </div>
        `;
        
        reviewsList.parentNode.insertBefore(filterContainer, reviewsList);
        
        // Filter functionality
        const filterButtons = filterContainer.querySelectorAll('.filter-btn');
        const sortSelect = filterContainer.querySelector('.sort-select');
        
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active state
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                filterReviews(filter);
            });
        });
        
        // Sort functionality
        sortSelect.addEventListener('change', function() {
            sortReviews(this.value);
        });
        
        function filterReviews(filter) {
            reviewCards.forEach(card => {
                const rating = parseInt(card.querySelector('.rating-number').textContent);
                const isFeatured = card.classList.contains('featured');
                
                let shouldShow = true;
                
                switch(filter) {
                    case 'featured':
                        shouldShow = isFeatured;
                        break;
                    case '5':
                        shouldShow = rating === 5;
                        break;
                    case '4':
                        shouldShow = rating >= 4;
                        break;
                    case '3':
                        shouldShow = rating >= 3;
                        break;
                    default:
                        shouldShow = true;
                }
                
                if (shouldShow) {
                    card.style.display = 'block';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 300);
                }
            });
        }
        
        function sortReviews(sortBy) {
            const container = reviewsList;
            const cards = Array.from(container.querySelectorAll('.review-card:not([style*="display: none"])'));
            
            cards.sort((a, b) => {
                const dateA = new Date(a.querySelector('.review-date').textContent);
                const dateB = new Date(b.querySelector('.review-date').textContent);
                const ratingA = parseInt(a.querySelector('.rating-number').textContent);
                const ratingB = parseInt(b.querySelector('.rating-number').textContent);
                
                switch(sortBy) {
                    case 'newest':
                        return dateB - dateA;
                    case 'oldest':
                        return dateA - dateB;
                    case 'highest':
                        return ratingB - ratingA;
                    case 'lowest':
                        return ratingA - ratingB;
                    default:
                        return 0;
                }
            });
            
            // Animate reordering
            cards.forEach((card, index) => {
                setTimeout(() => {
                    container.appendChild(card);
                    card.style.animation = `slideInUp 0.5s ease-out ${index * 0.1}s both`;
                }, index * 50);
            });
        }
    }

    // Like functionality for reviews
    const reviewCards = document.querySelectorAll('.review-card');
    reviewCards.forEach(card => {
        const likeBtn = document.createElement('button');
        likeBtn.className = 'like-btn';
        likeBtn.innerHTML = '<i class="far fa-heart"></i> <span class="like-count">0</span>';
        likeBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const count = this.querySelector('.like-count');
            
            if (icon.classList.contains('fas')) {
                // Unlike
                icon.className = 'far fa-heart';
                count.textContent = parseInt(count.textContent) - 1;
                this.classList.remove('liked');
            } else {
                // Like
                icon.className = 'fas fa-heart';
                count.textContent = parseInt(count.textContent) + 1;
                this.classList.add('liked');
                
                // Add pulse animation
                this.style.animation = 'pulse 0.6s ease-out';
                setTimeout(() => {
                    this.style.animation = '';
                }, 600);
            }
        });
        
        const cardFooter = card.querySelector('.review-comment').parentNode;
        cardFooter.appendChild(likeBtn);
    });

    // Share review functionality
    const shareButtons = document.querySelectorAll('.review-card');
    shareButtons.forEach(card => {
        const shareBtn = document.createElement('button');
        shareBtn.className = 'share-btn';
        shareBtn.innerHTML = '<i class="fas fa-share-alt"></i> Share';
        shareBtn.addEventListener('click', function() {
            const reviewText = card.querySelector('.review-comment').textContent;
            const reviewer = card.querySelector('.reviewer-name').textContent;
            const rating = card.querySelector('.rating-number').textContent;
            
            const shareText = `Check out this ${rating}-star review from ${reviewer}: "${reviewText.substring(0, 100)}..."`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Coffee Shop Review',
                    text: shareText,
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(shareText).then(() => {
                    showAlert('Review text copied to clipboard!', 'success');
                });
            }
        });
        
        const cardFooter = card.querySelector('.review-comment').parentNode;
        cardFooter.appendChild(shareBtn);
    });

    // Infinite scroll for reviews
    let isLoading = false;
    let page = 1;
    
    window.addEventListener('scroll', function() {
        if (isLoading) return;
        
        const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
        
        if (scrollTop + clientHeight >= scrollHeight - 100) {
            loadMoreReviews();
        }
    });
    
    async function loadMoreReviews() {
        isLoading = true;
        showLoadingIndicator();
        
        try {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // In a real app, you would fetch more reviews from the server
            // const response = await fetch(`/api/reviews?page=${page}`);
            // const newReviews = await response.json();
            
            // For demo, we'll just duplicate existing reviews
            const existingReviews = document.querySelectorAll('.review-card');
            const reviewsList = document.querySelector('.reviews-list');
            
            existingReviews.forEach((review, index) => {
                if (index < 3) { // Only clone first 3 for demo
                    const clone = review.cloneNode(true);
                    clone.style.animation = 'slideInUp 0.5s ease-out';
                    reviewsList.appendChild(clone);
                }
            });
            
            page++;
            hideLoadingIndicator();
        } catch (error) {
            console.error('Error loading more reviews:', error);
            hideLoadingIndicator();
            showAlert('Error loading more reviews. Please try again.', 'error');
        } finally {
            isLoading = false;
        }
    }

    // Helper functions
    function highlightError(element) {
        element.style.borderColor = 'var(--danger)';
        element.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
        
        setTimeout(() => {
            element.style.borderColor = '';
            element.style.boxShadow = '';
        }, 3000);
    }
    
    function showAlert(message, type) {
        // Remove existing alerts
        const existingAlert = document.querySelector('.custom-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        const alert = document.createElement('div');
        alert.className = `custom-alert ${type}`;
        alert.innerHTML = `
            <div class="alert-content">
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
                <span>${message}</span>
                <button class="alert-close">&times;</button>
            </div>
        `;
        
        document.body.appendChild(alert);
        
        // Show alert
        setTimeout(() => {
            alert.classList.add('show');
        }, 100);
        
        // Auto hide after 5 seconds
        const autoHide = setTimeout(() => {
            hideAlert(alert);
        }, 5000);
        
        // Close button
        alert.querySelector('.alert-close').addEventListener('click', () => {
            clearTimeout(autoHide);
            hideAlert(alert);
        });
    }
    
    function hideAlert(alert) {
        alert.classList.remove('show');
        setTimeout(() => {
            alert.remove();
        }, 300);
    }
    
    function showLoadingState() {
        const submitBtn = reviewForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitBtn.disabled = true;
        reviewForm.classList.add('loading');
        
        // Revert after 3 seconds (in case form doesn't submit)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            reviewForm.classList.remove('loading');
        }, 3000);
    }
    
    function showLoadingIndicator() {
        const loader = document.createElement('div');
        loader.className = 'loading-indicator';
        loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading more reviews...';
        document.querySelector('.reviews-list').appendChild(loader);
    }
    
    function hideLoadingIndicator() {
        const loader = document.querySelector('.loading-indicator');
        if (loader) {
            loader.remove();
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter to submit form
        if (e.ctrlKey && e.key === 'Enter' && reviewForm) {
            reviewForm.dispatchEvent(new Event('submit'));
        }
        
        // Escape to clear form
        if (e.key === 'Escape') {
            ratingValue.value = '0';
            updateStars(0);
            ratingText.textContent = ratingTexts[0];
        }
    });

    // Print reviews functionality
    const printBtn = document.createElement('button');
    printBtn.className = 'btn btn-secondary print-btn';
    printBtn.innerHTML = '<i class="fas fa-print"></i> Print Reviews';
    printBtn.addEventListener('click', function() {
        window.print();
    });
    
    document.querySelector('.all-reviews h3').appendChild(printBtn);

    // Add CSS for new elements
    const dynamicStyles = `
        <style>
            /* Search wrapper */
            .search-wrapper {
                position: relative;
                margin-bottom: 15px;
            }
            
            .search-wrapper .search-icon {
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--gray);
            }
            
            /* Review filters */
            .review-filters {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .filter-buttons {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .filter-btn {
                padding: 8px 16px;
                border: 2px solid var(--accent);
                background: var(--white);
                color: var(--primary);
                border-radius: 20px;
                cursor: pointer;
                transition: var(--transition);
                font-weight: 500;
            }
            
            .filter-btn.active,
            .filter-btn:hover {
                background: var(--primary);
                color: var(--white);
                border-color: var(--primary);
            }
            
            .sort-options {
                min-width: 150px;
            }
            
            /* Like and Share buttons */
            .like-btn, .share-btn {
                padding: 6px 12px;
                border: 1px solid #e8d6c5;
                background: var(--white);
                color: var(--gray);
                border-radius: 6px;
                cursor: pointer;
                transition: var(--transition);
                font-size: 0.85rem;
                margin-right: 8px;
                margin-top: 10px;
            }
            
            .like-btn:hover, .share-btn:hover {
                background: #f8f9fa;
                border-color: var(--accent);
            }
            
            .like-btn.liked {
                color: var(--danger);
                border-color: var(--danger);
            }
            
            .like-btn.liked i {
                color: var(--danger);
            }
            
            /* Custom alerts */
            .custom-alert {
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--white);
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: var(--shadow-lg);
                border-left: 4px solid;
                z-index: 1000;
                transform: translateX(400px);
                transition: transform 0.3s ease;
                max-width: 400px;
            }
            
            .custom-alert.show {
                transform: translateX(0);
            }
            
            .custom-alert.success {
                border-left-color: var(--success);
            }
            
            .custom-alert.error {
                border-left-color: var(--danger);
            }
            
            .alert-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .alert-close {
                background: none;
                border: none;
                font-size: 1.2rem;
                cursor: pointer;
                color: var(--gray);
                margin-left: auto;
            }
            
            /* Loading indicator */
            .loading-indicator {
                text-align: center;
                padding: 20px;
                color: var(--gray);
            }
            
            /* Print button */
            .print-btn {
                margin-left: 15px;
                padding: 6px 12px;
                font-size: 0.9rem;
            }
            
            /* Animations */
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1.15); }
            }
            
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Print styles */
            @media print {
                .review-form-container,
                .reviews-sidebar,
                .review-filters,
                .print-btn {
                    display: none !important;
                }
                
                .reviews-layout {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>
    `;
    
    document.head.insertAdjacentHTML('beforeend', dynamicStyles);
});

// Service Worker for offline functionality (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').then(function(registration) {
            console.log('ServiceWorker registration successful');
        }, function(err) {
            console.log('ServiceWorker registration failed: ', err);
        });
    });
}
</script>

<?php include 'footer.php'; ?>