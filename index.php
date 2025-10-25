<?php
// index.php
require_once 'header.php';
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// Get featured menu items
$query = "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY created_at DESC LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute();
$featured_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Section -->
<section class="hero" id="home">
    <div class="hero-bg"></div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Experience Authentic <span class="highlight">Indian Coffee</span></h1>
                <p class="hero-subtitle">Indulge in our traditional Indian coffees, delicious snacks, and warm hospitality. Perfect for meetings, work, or relaxing with friends.</p>
                <div class="hero-actions">
                    <a href="menu.php" class="btn btn-primary">
                        <i class="fas fa-utensils"></i>
                        View Menu
                    </a>
                    <a href="reservations.php" class="btn btn-secondary">
                        <i class="fas fa-calendar"></i>
                        Book a Table
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Coffee Varieties</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">4.8</div>
                        <div class="stat-label">Customer Rating</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">1000+</div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <div class="floating-elements">
                    <div class="floating coffee-cup">
                        <i class="fas fa-mug-hot"></i>
                    </div>
                    <div class="floating coffee-bean">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="floating pastry">
                        <i class="fas fa-cookie"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features">
    <div class="container">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Quick Service</h3>
                <p>Freshly prepared items served within minutes</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3>Fresh Ingredients</h3>
                <p>Daily sourced organic ingredients for best taste</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3>Free Delivery</h3>
                <p>Free delivery on orders above ₹300</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-award"></i>
                </div>
                <h3>Quality Guarantee</h3>
                <p>100% satisfaction guarantee on all orders</p>
            </div>
        </div>
    </div>
</section>

<!-- Featured Menu Items -->
<section id="featured" class="featured-menu">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Featured Specialties</h2>
            <p class="section-subtitle">Handpicked favorites from our menu</p>
        </div>
        
        <?php if (empty($featured_items)): ?>
            <div class="no-items">
                <i class="fas fa-utensils"></i>
                <h3>Menu Coming Soon</h3>
                <p>We're preparing something delicious for you!</p>
            </div>
        <?php else: ?>
            <div class="menu-grid">
                <?php foreach($featured_items as $item): ?>
                <div class="menu-card">
                    <div class="card-image">
                        <?php if ($item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php else: ?>
                            <div class="image-placeholder">
                                <i class="fas fa-mug-hot"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-overlay">
                            <button class="quick-view" data-item='<?php echo json_encode($item); ?>'>
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="card-header">
                            <h3 class="item-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <span class="item-price">₹<?php echo number_format($item['price'], 2); ?></span>
                        </div>
                        <p class="item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                        <div class="card-actions">
                            <form method="POST" action="cart_handler.php" class="add-to-cart-form">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item['name']); ?>">
                                <input type="hidden" name="item_price" value="<?php echo $item['price']; ?>">
                                <input type="hidden" name="item_image" value="<?php echo htmlspecialchars($item['image_url']); ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn-add-to-cart">
                                    <i class="fas fa-shopping-cart"></i>
                                    Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="view-all-container">
                <a href="menu.php" class="btn btn-outline">
                    Explore Full Menu
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2>Ready to Experience BrewHaven?</h2>
            <p>Join us for an unforgettable coffee experience</p>
            <div class="cta-actions">
                <a href="reservations.php" class="btn btn-primary btn-large">
                    <i class="fas fa-calendar-plus"></i>
                    Reserve Your Table
                </a>
                <a href="tel:+911234567890" class="btn btn-secondary btn-large">
                    <i class="fas fa-phone"></i>
                    Call Us Now
                </a>
            </div>
        </div>
    </div>
</section>

<style>
/* Hero Section */
.hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #6f4e37 0%, #8b6b4d 100%);
    overflow: hidden;
}

.hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%23ffffff10" points="0,1000 1000,0 1000,1000"/></svg>');
    background-size: cover;
}

.hero-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    position: relative;
    z-index: 2;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 700;
    color: white;
    margin-bottom: 1.5rem;
    line-height: 1.2;
}

.highlight {
    color: #ffd700;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.hero-subtitle {
    font-size: 1.2rem;
    color: rgba(255,255,255,0.9);
    margin-bottom: 2.5rem;
    line-height: 1.6;
}

.hero-actions {
    display: flex;
    gap: 20px;
    margin-bottom: 3rem;
}

.btn {
    padding: 15px 30px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: 2px solid transparent;
}

.btn-primary {
    background: #ffd700;
    color: #6f4e37;
}

.btn-primary:hover {
    background: #ffed4a;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(255, 215, 0, 0.3);
}

.btn-secondary {
    background: transparent;
    color: white;
    border-color: white;
}

.btn-secondary:hover {
    background: white;
    color: #6f4e37;
    transform: translateY(-2px);
}

.hero-stats {
    display: flex;
    gap: 40px;
}

.stat {
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #ffd700;
    margin-bottom: 5px;
}

.stat-label {
    color: rgba(255,255,255,0.8);
    font-size: 0.9rem;
}

.hero-image {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
}

.floating-elements {
    position: relative;
    width: 400px;
    height: 400px;
}

.floating {
    position: absolute;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #ffd700;
    animation: float 6s ease-in-out infinite;
}

.floating.coffee-cup {
    width: 80px;
    height: 80px;
    top: 50px;
    left: 50px;
    animation-delay: 0s;
}

.floating.coffee-bean {
    width: 60px;
    height: 60px;
    top: 150px;
    right: 80px;
    animation-delay: 2s;
}

.floating.pastry {
    width: 70px;
    height: 70px;
    bottom: 80px;
    left: 100px;
    animation-delay: 4s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(10deg); }
}

/* Features Section */
.features {
    padding: 80px 0;
    background: #f8f5f0;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.feature-card {
    background: white;
    padding: 40px 30px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.feature-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #6f4e37, #8b6b4d);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 2rem;
}

.feature-card h3 {
    color: #333;
    margin-bottom: 15px;
    font-size: 1.3rem;
}

.feature-card p {
    color: #666;
    line-height: 1.6;
}

/* Featured Menu */
.featured-menu {
    padding: 100px 0;
    background: white;
}

.section-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-title {
    font-size: 2.5rem;
    color: #333;
    margin-bottom: 15px;
    font-weight: 700;
}

.section-subtitle {
    font-size: 1.1rem;
    color: #666;
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.menu-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}

.menu-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.card-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.menu-card:hover .card-image img {
    transform: scale(1.1);
}

.image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f8f5f0, #e8dfd6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #b8a99a;
    font-size: 3rem;
}

.card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(111, 78, 55, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.menu-card:hover .card-overlay {
    opacity: 1;
}

.quick-view {
    background: #ffd700;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    color: #6f4e37;
    font-size: 1.2rem;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.quick-view:hover {
    transform: scale(1.1);
}

.card-content {
    padding: 25px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.item-title {
    font-size: 1.3rem;
    color: #333;
    margin: 0;
    flex: 1;
}

.item-price {
    font-size: 1.3rem;
    font-weight: 700;
    color: #6f4e37;
    margin-left: 15px;
}

.item-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 20px;
    font-size: 0.95rem;
}

.card-actions {
    margin-top: 20px;
}

.btn-add-to-cart {
    width: 100%;
    padding: 12px 20px;
    background: #6f4e37;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-add-to-cart:hover {
    background: #5a3e2c;
    transform: translateY(-2px);
}

.view-all-container {
    text-align: center;
}

.btn-outline {
    background: transparent;
    border: 2px solid #6f4e37;
    color: #6f4e37;
    padding: 15px 40px;
}

.btn-outline:hover {
    background: #6f4e37;
    color: white;
}

.btn-large {
    padding: 18px 40px;
    font-size: 1.1rem;
}

/* CTA Section */
.cta-section {
    padding: 100px 0;
    background: linear-gradient(135deg, #6f4e37 0%, #8b6b4d 100%);
    color: white;
    text-align: center;
}

.cta-content h2 {
    font-size: 2.5rem;
    margin-bottom: 20px;
    font-weight: 700;
}

.cta-content p {
    font-size: 1.2rem;
    margin-bottom: 40px;
    opacity: 0.9;
}

.cta-actions {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.no-items {
    text-align: center;
    padding: 80px 20px;
    color: #666;
}

.no-items i {
    font-size: 4rem;
    color: #e8dfd6;
    margin-bottom: 20px;
}

.no-items h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #333;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-content {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-actions {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .hero-stats {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .floating-elements {
        width: 300px;
        height: 300px;
    }
    
    .cta-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .menu-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .btn {
        padding: 12px 25px;
    }
}
</style>

<script>
// Add to cart animation
document.querySelectorAll('.btn-add-to-cart').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        const form = this.closest('form');
        const originalText = this.innerHTML;
        
        // Add loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        this.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            // Create flying cart animation
            const rect = this.getBoundingClientRect();
            const flyingItem = document.createElement('div');
            flyingItem.innerHTML = '<i class="fas fa-shopping-cart"></i>';
            flyingItem.style.cssText = `
                position: fixed;
                left: ${rect.left + rect.width/2}px;
                top: ${rect.top + rect.height/2}px;
                font-size: 20px;
                color: #6f4e37;
                z-index: 10000;
                pointer-events: none;
                transition: all 0.8s ease;
            `;
            document.body.appendChild(flyingItem);
            
            // Animate to cart
            setTimeout(() => {
                flyingItem.style.left = '90%';
                flyingItem.style.top = '20px';
                flyingItem.style.transform = 'scale(0.5)';
                flyingItem.style.opacity = '0';
            }, 50);
            
            // Remove flying item and submit form
            setTimeout(() => {
                flyingItem.remove();
                form.submit();
            }, 800);
            
        }, 500);
    });
});

// Quick view functionality
document.querySelectorAll('.quick-view').forEach(button => {
    button.addEventListener('click', function() {
        const item = JSON.parse(this.getAttribute('data-item'));
        // You can implement a modal here to show item details
        alert(`Quick view: ${item.name}\nPrice: ₹${item.price}\n${item.description}`);
    });
});
</script>

<?php include 'footer.php'; ?>