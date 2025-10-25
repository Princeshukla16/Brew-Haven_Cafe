<?php
// menu.php
require_once 'header.php';
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// Get unique categories from menu_items
$query = "SELECT DISTINCT category FROM menu_items WHERE category IS NOT NULL AND category != '' AND is_available = 1 ORDER BY category";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get menu items
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';

if ($category_filter == 'all') {
    $query = "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY category, name";
    $stmt = $db->prepare($query);
    $stmt->execute();
} else {
    $query = "SELECT * FROM menu_items WHERE category = ? AND is_available = 1 ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute([$category_filter]);
}

$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section id="menu">
    <div class="container">
        <div class="section-title">
            <h2>Our Menu</h2>
            <p>Discover our delicious offerings</p>
        </div>
        
        <!-- Category Filters -->
        <div class="menu-filters">
            <a href="menu.php?category=all" class="filter-btn <?php echo $category_filter == 'all' ? 'active' : ''; ?>">All Items</a>
            <?php foreach($categories as $category): ?>
                <a href="menu.php?category=<?php echo urlencode($category); ?>" class="filter-btn <?php echo $category_filter == $category ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($category); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Menu Items Grid -->
        <div class="menu-grid">
            <?php if (empty($menu_items)): ?>
                <div class="no-items">
                    <p>No menu items found in this category.</p>
                </div>
            <?php else: ?>
                <?php 
                // Group items by category for better organization
                $grouped_items = [];
                foreach ($menu_items as $item) {
                    $category = $item['category'] ?: 'Other';
                    $grouped_items[$category][] = $item;
                }
                ?>
                
                <?php foreach($grouped_items as $category_name => $items): ?>
                    <?php if ($category_filter == 'all'): ?>
                        <div class="category-section">
                            <h3 class="category-title"><?php echo htmlspecialchars($category_name); ?></h3>
                            <div class="category-items">
                    <?php endif; ?>
                    
                    <?php foreach($items as $item): ?>
                        <div class="menu-item">
                            <div class="menu-item-image">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="menu-item-img">
                                <?php else: ?>
                                    <div class="menu-item-placeholder">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="menu-item-content">
                                <div class="menu-item-header">
                                    <h3 class="menu-item-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <span class="menu-item-price">₹<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                                
                                <p class="menu-item-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                                
                                <div class="menu-item-badges">
                                    <?php if(isset($item['is_vegetarian']) && $item['is_vegetarian']): ?>
                                        <span class="menu-item-badge veg">Vegetarian</span>
                                    <?php endif; ?>
                                    <?php if(isset($item['is_vegan']) && $item['is_vegan']): ?>
                                        <span class="menu-item-badge vegan">Vegan</span>
                                    <?php endif; ?>
                                    <?php if(isset($item['is_spicy']) && $item['is_spicy']): ?>
                                        <span class="menu-item-badge spicy">Spicy</span>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" action="cart_handler.php" class="add-to-cart-form">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item['name']); ?>">
                                    <input type="hidden" name="item_price" value="<?php echo $item['price']; ?>">
                                    <input type="hidden" name="item_image" value="<?php echo htmlspecialchars($item['image_url']); ?>">
                                    <input type="hidden" name="action" value="add">
                                    
                                    <div class="quantity-selector">
                                        <button type="button" class="qty-btn minus" onclick="decrementQuantity(this)">-</button>
                                        <input type="number" name="quantity" value="1" min="1" max="10" class="qty-input" readonly>
                                        <button type="button" class="qty-btn plus" onclick="incrementQuantity(this)">+</button>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary add-to-cart-btn">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($category_filter == 'all'): ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.menu-filters {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 40px;
}

.filter-btn {
    padding: 10px 20px;
    background: #f8f9fa;
    color: #6f4e37;
    text-decoration: none;
    border-radius: 25px;
    border: 2px solid #6f4e37;
    transition: all 0.3s ease;
    font-weight: 500;
}

.filter-btn:hover,
.filter-btn.active {
    background: #6f4e37;
    color: white;
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

.category-section {
    grid-column: 1 / -1;
    margin-bottom: 40px;
}

.category-title {
    color: #6f4e37;
    font-size: 24px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.category-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

.menu-item {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.menu-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 25px rgba(0,0,0,0.15);
}

.menu-item-image {
    height: 200px;
    overflow: hidden;
}

.menu-item-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.menu-item:hover .menu-item-img {
    transform: scale(1.05);
}

.menu-item-placeholder {
    width: 100%;
    height: 100%;
    background: #f8f5f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #d4c1a8;
    font-size: 48px;
}

.menu-item-content {
    padding: 20px;
}

.menu-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.menu-item-title {
    color: #333;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    flex: 1;
}

.menu-item-price {
    color: #6f4e37;
    font-size: 18px;
    font-weight: bold;
    margin-left: 15px;
}

.menu-item-desc {
    color: #666;
    line-height: 1.5;
    margin-bottom: 15px;
    font-size: 14px;
}

.menu-item-badges {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.menu-item-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.menu-item-badge.veg {
    background: #d4edda;
    color: #155724;
}

.menu-item-badge.vegan {
    background: #e2e3e5;
    color: #383d41;
}

.menu-item-badge.spicy {
    background: #f8d7da;
    color: #721c24;
}

.add-to-cart-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.quantity-selector {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.qty-btn {
    background: #f8f9fa;
    border: none;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s ease;
}

.qty-btn:hover {
    background: #e9ecef;
}

.qty-input {
    width: 40px;
    border: none;
    text-align: center;
    background: white;
    font-weight: bold;
}

.add-to-cart-btn {
    flex: 1;
    white-space: nowrap;
}

.no-items {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px;
    color: #666;
}

@media (max-width: 768px) {
    .menu-grid {
        grid-template-columns: 1fr;
    }
    
    .category-items {
        grid-template-columns: 1fr;
    }
    
    .add-to-cart-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .quantity-selector {
        justify-content: center;
    }
}
</style>

<script>
function incrementQuantity(btn) {
    const input = btn.parentElement.querySelector('.qty-input');
    if (input.value < 10) {
        input.value = parseInt(input.value) + 1;
    }
}

function decrementQuantity(btn) {
    const input = btn.parentElement.querySelector('.qty-input');
    if (input.value > 1) {
        input.value = parseInt(input.value) - 1;
    }
}

// Add to cart with animation
document.querySelectorAll('.add-to-cart-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = this.querySelector('.add-to-cart-btn');
        const originalText = btn.innerHTML;
        
        // Add loading state
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            // Submit the form
            this.submit();
            
            // Reset button (in case form doesn't redirect)
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        }, 500);
    });
});
</script>

<?php include 'footer.php'; ?>