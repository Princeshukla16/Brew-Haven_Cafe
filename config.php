<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Database {
    private $host = "localhost";
    private $db_name = "brewhaven_cafe";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            die("Database connection failed: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Utility functions for customer authentication
function isCustomerLoggedIn() {
    return isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
}

function getCustomerId() {
    return $_SESSION['customer_id'] ?? null;
}

function getCustomerName() {
    return $_SESSION['customer_name'] ?? 'Guest';
}

function getCustomerEmail() {
    return $_SESSION['customer_email'] ?? '';
}

function redirectIfCustomerLoggedIn($url = 'index.php') {
    if (isCustomerLoggedIn()) {
        header('Location: ' . $url);
        exit;
    }
}

function requireCustomerLogin($redirect_url = 'login.php') {
    if (!isCustomerLoggedIn()) {
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Utility functions for owner authentication
function isOwnerLoggedIn() {
    return isset($_SESSION['owner_id']) && !empty($_SESSION['owner_id']);
}

function redirectIfOwnerLoggedIn($url = 'admin_dashboard.php') {
    if (isOwnerLoggedIn()) {
        header('Location: ' . $url);
        exit;
    }
}

function requireOwnerLogin($redirect_url = 'owner_login.php') {
    if (!isOwnerLoggedIn()) {
        header('Location: ' . $redirect_url);
        exit;
    }
}

// General utility functions
function logoutUser() {
    session_unset();
    session_destroy();
    session_start();
}

// Cart management functions
function getCartItemCount() {
    return isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
}

function getCartTotal() {
    $total = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
    }
    return $total;
}

// Enhanced Input validation functions
function validateEmail($email) {
    if (empty($email)) {
        return "Email is required";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address";
    }
    return null;
}

function validatePassword($password) {
    if (empty($password)) {
        return "Password is required";
    }
    if (strlen($password) < 6) {
        return "Password must be at least 6 characters long";
    }
    return null;
}

function validatePhone($phone) {
    if (empty($phone)) {
        return "Phone number is required";
    }
    
    // Remove any non-digit characters except +
    $clean_phone = preg_replace('/[^\d+]/', '', $phone);
    
    // Check if it's a valid Indian mobile number (10 digits) or international format
    if (preg_match('/^\+?[0-9]{10,15}$/', $clean_phone)) {
        return null; // Valid phone number
    }
    
    return "Please enter a valid phone number (10-15 digits, + allowed for international)";
}

function validateName($name) {
    if (empty($name)) {
        return "Name is required";
    }
    if (strlen($name) < 2) {
        return "Name must be at least 2 characters long";
    }
    if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
        return "Name can only contain letters and spaces";
    }
    return null;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatPhone($phone) {
    // Remove all non-digit characters except +
    $clean = preg_replace('/[^\d+]/', '', $phone);
    
    // If it starts with 91 (India code) without +, add it
    if (preg_match('/^91\d{10}$/', $clean)) {
        return '+' . $clean;
    }
    
    // If it's 10 digits, assume it's Indian number and add +91
    if (preg_match('/^\d{10}$/', $clean)) {
        return '+91' . $clean;
    }
    
    return $clean;
}

// Database connection helper
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

// config.php - Add these functions to the existing config.php

// Add these functions to the existing Utility functions section

function getOwnerRole() {
    return isset($_SESSION['owner_role']) ? $_SESSION['owner_role'] : null;
}

function hasPermission($requiredRole) {
    $userRole = getOwnerRole();
    $roleHierarchy = ['staff' => 1, 'manager' => 2, 'admin' => 3];
    
    if (!isset($roleHierarchy[$userRole]) || !isset($roleHierarchy[$requiredRole])) {
        return false;
    }
    
    return $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}

function redirectIfNoPermission($requiredRole) {
    if (!hasPermission($requiredRole)) {
        header('Location: admin_dashboard.php?error=access_denied');
        exit;
    }
}

// Statistics functions
function getDashboardStatistics($db) {
    $stats = [];
    
    try {
        // Today's orders
        $stmt = $db->query("SELECT COUNT(*) as today_orders FROM orders WHERE DATE(created_at) = CURDATE()");
        $stats['today_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_orders'] ?? 0;
        
        // Today's revenue
        $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as today_revenue FROM orders WHERE status = 'completed' AND DATE(created_at) = CURDATE()");
        $stats['today_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_revenue'] ?? 0;
        
        // Total orders
        $stmt = $db->query("SELECT COUNT(*) as total_orders FROM orders");
        $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;
        
        // Total revenue
        $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders WHERE status = 'completed'");
        $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
        
        // Total customers
        $stmt = $db->query("SELECT COUNT(*) as total_customers FROM customers");
        $stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_customers'] ?? 0;
        
        // Total menu items
        $stmt = $db->query("SELECT COUNT(*) as total_items FROM menu_items");
        $stats['total_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_items'] ?? 0;
        
        // Available menu items
        $stmt = $db->query("SELECT COUNT(*) as available_items FROM menu_items WHERE is_available = 1");
        $stats['available_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['available_items'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Dashboard statistics error: " . $e->getMessage());
        // Set default values if there's an error
        $stats = [
            'today_orders' => 0,
            'today_revenue' => 0,
            'total_orders' => 0,
            'total_revenue' => 0,
            'total_customers' => 0,
            'total_items' => 0,
            'available_items' => 0
        ];
    }
    
    return $stats;
}
?>
