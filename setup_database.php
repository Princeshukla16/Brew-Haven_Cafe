<?php
// setup_database.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>BrewHaven Cafe - Database Setup</h2>";
echo "<pre>";

try {
    // Create database connection
    $host = "localhost";
    $username = "root";
    $password = "";
    
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS brewhaven_cafe");
    $pdo->exec("USE brewhaven_cafe");
    
    echo "✓ Database created/selected successfully\n";
    
    // Create customers table with proper phone field
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    echo "✓ Customers table created successfully\n";
    
    // Create owners table
    $pdo->exec("CREATE TABLE IF NOT EXISTS owners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        role ENUM('admin', 'manager', 'staff') DEFAULT 'manager',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    echo "✓ Owners table created successfully\n";
    
    // Create menu items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(500),
        category VARCHAR(100),
        is_vegetarian BOOLEAN DEFAULT FALSE,
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create orders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        customer_name VARCHAR(255),
        customer_email VARCHAR(255),
        customer_phone VARCHAR(20),
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'preparing', 'completed', 'cancelled') DEFAULT 'pending',
        order_type ENUM('delivery', 'pickup') DEFAULT 'delivery',
        delivery_address TEXT,
        special_instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    )");
    
    // Create order items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT,
        menu_item_id INT,
        item_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
    )");
    
    // Create reservations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        customer_email VARCHAR(255),
        reservation_date DATE NOT NULL,
        reservation_time TIME NOT NULL,
        party_size INT NOT NULL,
        special_requests TEXT,
        status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    )");
    
    echo "✓ All tables created successfully\n";
    
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM owners WHERE username = 'admin'");
    $stmt->execute();
    $admin_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$admin_exists) {
        // Create admin user with hashed password
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO owners (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@brewhaven.com', $hashed_password, 'BrewHaven Admin', '+919876543210', 'admin']);
        echo "✓ Admin user created successfully\n";
        echo "   Username: admin\n";
        echo "   Password: admin123\n";
    } else {
        echo "✓ Admin user already exists\n";
    }
    
    // Insert sample menu items
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM menu_items");
    $stmt->execute();
    $menu_items_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($menu_items_count == 0) {
        $sample_items = [
            ['South Indian Filter Coffee', 'Traditional coffee with frothed milk', 80.00, 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445', 'Coffee', true],
            ['Masala Chai', 'Spiced Indian tea with ginger and cardamom', 60.00, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd', 'Tea', true],
            ['Samosa', 'Crispy pastry with spiced potatoes and peas', 60.00, 'https://images.unsplash.com/photo-1589647363585-f4a7d3877b10', 'Snacks', true],
            ['Pav Bhaji', 'Spiced vegetable mash with buttered bread', 150.00, 'https://images.unsplash.com/photo-1630918037678-a8f7e0f9c9c6', 'Main Course', true],
            ['Gulab Jamun', 'Sweet milk balls in rose syrup', 100.00, 'https://images.unsplash.com/photo-1586201375761-83865001e31c', 'Dessert', true]
        ];
        
        foreach ($sample_items as $item) {
            $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, image_url, category, is_vegetarian) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute($item);
        }
        echo "✓ Sample menu items added\n";
    }
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "You can now:\n";
    echo "1. Visit main website: <a href='index.php'>index.php</a>\n";
    echo "2. Customer registration: <a href='login.php'>login.php</a>\n";
    echo "3. Owner login: <a href='owner_login.php'>owner_login.php</a>\n";
    echo "4. Owner registration: <a href='owner_register.php'>owner_register.php</a>\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your MySQL configuration and try again.\n";
}

echo "</pre>";
?>