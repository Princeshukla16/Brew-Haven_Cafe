<?php
// admin_menu.php
require_once 'config.php';

if (!isOwnerLoggedIn()) {
    header('Location: owner_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_menu_item'])) {
        // Add new menu item
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $image_url = trim($_POST['image_url']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $category = trim($_POST['category']);
        
        try {
            $stmt = $db->prepare("INSERT INTO menu_items (name, description, price, image_url, is_available, category) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $image_url, $is_available, $category]);
            $message = 'success=menu_added';
        } catch (Exception $e) {
            $message = 'error=menu_add_failed';
        }
    }
    elseif (isset($_POST['update_menu_item'])) {
        // Update menu item
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $image_url = trim($_POST['image_url']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $category = trim($_POST['category']);
        
        try {
            $stmt = $db->prepare("UPDATE menu_items SET name=?, description=?, price=?, image_url=?, is_available=?, category=? 
                                 WHERE id=?");
            $stmt->execute([$name, $description, $price, $image_url, $is_available, $category, $id]);
            $message = 'success=menu_updated';
        } catch (Exception $e) {
            $message = 'error=menu_update_failed';
        }
    }
    elseif (isset($_POST['delete_menu_item'])) {
        // Delete menu item
        try {
            $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'success=menu_deleted';
            $action = 'list';
        } catch (Exception $e) {
            $message = 'error=menu_delete_failed';
        }
    }
    
    header("Location: admin_menu.php?action=$action&$message");
    exit;
}

// Get menu item for editing
$menu_item = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    $menu_item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$menu_item) {
        header("Location: admin_menu.php?error=menu_not_found");
        exit;
    }
}

// Get all menu items for listing
$menu_items = [];
if ($action === 'list') {
    $stmt = $db->query("SELECT * FROM menu_items ORDER BY category, name");
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get unique categories
$stmt = $db->query("SELECT DISTINCT category FROM menu_items WHERE category IS NOT NULL AND category != ''");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add these styles to the existing CSS */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #6f4e37;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5a3e2c;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table th, .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f5f0;
            font-weight: 600;
            color: #6f4e37;
        }
        
        .table img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .status-available { color: #28a745; }
        .status-unavailable { color: #dc3545; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #6f4e37;
            outline: none;
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
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .category-badge {
            background: #e7f3ff;
            color: #0066cc;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h2><i class="fas fa-utensils"></i> Menu Management</h2>
            <?php if ($action === 'list'): ?>
                <a href="admin_menu.php?action=add" class="btn">
                    <i class="fas fa-plus"></i> Add Menu Item
                </a>
            <?php else: ?>
                <a href="admin_menu.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                $successMessages = [
                    'menu_added' => 'Menu item added successfully!',
                    'menu_updated' => 'Menu item updated successfully!',
                    'menu_deleted' => 'Menu item deleted successfully!'
                ];
                echo $successMessages[$_GET['success']] ?? 'Operation completed successfully!';
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                $errorMessages = [
                    'menu_not_found' => 'Menu item not found.',
                    'menu_add_failed' => 'Failed to add menu item.',
                    'menu_update_failed' => 'Failed to update menu item.',
                    'menu_delete_failed' => 'Failed to delete menu item.'
                ];
                echo $errorMessages[$_GET['error']] ?? 'An error occurred.';
                ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Menu Items List -->
            <div class="section-card">
                <h3>Menu Items (<?php echo count($menu_items); ?>)</h3>
                <?php if (empty($menu_items)): ?>
                    <p>No menu items found. <a href="admin_menu.php?action=add">Add your first menu item</a>.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menu_items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-utensils" style="color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?>...
                                    </td>
                                    <td>
                                        <span class="category-badge"><?php echo htmlspecialchars($item['category'] ?: 'Uncategorized'); ?></span>
                                    </td>
                                    <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <?php if ($item['is_available']): ?>
                                            <span class="status-available"><i class="fas fa-check-circle"></i> Available</span>
                                        <?php else: ?>
                                            <span class="status-unavailable"><i class="fas fa-times-circle"></i> Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="admin_menu.php?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-small">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this menu item?')">
                                                <input type="hidden" name="delete_menu_item" value="1">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-small btn-danger">
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

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Menu Item Form -->
            <div class="form-container">
                <h3><?php echo $action === 'add' ? 'Add New Menu Item' : 'Edit Menu Item'; ?></h3>
                
                <form method="POST">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="update_menu_item" value="1">
                        <input type="hidden" name="id" value="<?php echo $menu_item['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="add_menu_item" value="1">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Item Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($menu_item['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required><?php echo htmlspecialchars($menu_item['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (₹) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required 
                               value="<?php echo htmlspecialchars($menu_item['price'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" 
                               value="<?php echo htmlspecialchars($menu_item['category'] ?? ''); ?>"
                               list="categories">
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" 
                               value="<?php echo htmlspecialchars($menu_item['image_url'] ?? ''); ?>"
                               placeholder="https://example.com/image.jpg">
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_available" name="is_available" value="1" 
                                   <?php echo ($menu_item['is_available'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="is_available">Available for ordering</label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin_menu.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">
                            <?php echo $action === 'add' ? 'Add Menu Item' : 'Update Menu Item'; ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Image preview
        const imageUrlInput = document.getElementById('image_url');
        const imagePreview = document.createElement('img');
        imagePreview.style.maxWidth = '200px';
        imagePreview.style.marginTop = '10px';
        imagePreview.style.display = 'none';
        
        if (imageUrlInput) {
            imageUrlInput.parentNode.appendChild(imagePreview);
            
            imageUrlInput.addEventListener('change', function() {
                if (this.value) {
                    imagePreview.src = this.value;
                    imagePreview.style.display = 'block';
                } else {
                    imagePreview.style.display = 'none';
                }
            });
            
            // Trigger change on page load if there's already a value
            if (imageUrlInput.value) {
                imageUrlInput.dispatchEvent(new Event('change'));
            }
        }
    </script>
</body>
</html>