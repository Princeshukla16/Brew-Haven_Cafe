<?php
// login.php
require_once 'config.php';

// Redirect if already logged in as customer
if (isCustomerLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle customer login
if ($_POST && isset($_POST['customer_login'])) {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $db = getDBConnection();
        
        $query = "SELECT * FROM customers WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer && password_verify($password, $customer['password'])) {
            // Login successful
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            $_SESSION['customer_email'] = $customer['email'];
            $_SESSION['customer_phone'] = $customer['phone'];
            $_SESSION['user_type'] = 'customer';
            
            header('Location: index.php');
            exit;
        } else {
            $error = "Invalid email or password";
        }
    }
}

// Handle customer registration
if ($_POST && isset($_POST['customer_register'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    
    if (($nameError = validateName($name)) !== null) $errors['name'] = $nameError;
    if (($emailError = validateEmail($email)) !== null) $errors['email'] = $emailError;
    if (($phoneError = validatePhone($phone)) !== null) $errors['phone'] = $phoneError;
    if (($passwordError = validatePassword($password)) !== null) $errors['password'] = $passwordError;
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        $db = getDBConnection();
        
        try {
            // Check if email already exists
            $check_query = "SELECT id FROM customers WHERE email = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$email]);
            
            if ($check_stmt->fetch()) {
                $errors['email'] = "Email already registered";
            } else {
                // Check if phone already exists
                $check_query = "SELECT id FROM customers WHERE phone = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([formatPhone($phone)]);
                
                if ($check_stmt->fetch()) {
                    $errors['phone'] = "Phone number already registered";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $formatted_phone = formatPhone($phone);
                    
                    $query = "INSERT INTO customers (name, email, phone, password) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$name, $email, $formatted_phone, $hashed_password])) {
                        $success = "Registration successful! Please login.";
                        // Clear form
                        $_POST = array();
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Please correct the following errors:";
        foreach ($errors as $fieldError) {
            $error .= "<br>• " . $fieldError;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - BrewHaven Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... (keep the same CSS styles as before) ... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #f8f5f0 0%, #e3cda5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #6f4e37;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .logo p {
            color: #666;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert.error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert.success {
            background: #efe;
            color: #363;
            border-left: 4px solid #363;
        }

        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
            color: #666;
        }

        .tab.active {
            color: #6f4e37;
            border-bottom-color: #6f4e37;
        }

        .form-container {
            display: none;
        }

        .form-container.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: #6f4e37;
            outline: none;
        }

        .phone-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #6f4e37;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #5a3e2c;
        }

        .switch-text {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .switch-text a {
            color: #6f4e37;
            text-decoration: none;
            font-weight: 500;
        }

        .switch-text a:hover {
            text-decoration: underline;
        }

        .owner-login-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .owner-login-link a {
            color: #6f4e37;
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>BrewHaven Cafe</h1>
            <p>Customer Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="showTab('login')">Login</div>
            <div class="tab" onclick="showTab('register')">Register</div>
        </div>

        <!-- Login Form -->
        <form method="POST" class="form-container active" id="loginForm">
            <input type="hidden" name="customer_login" value="1">
            
            <div class="form-group">
                <label for="loginEmail">Email Address</label>
                <input type="email" id="loginEmail" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="loginPassword">Password</label>
                <input type="password" id="loginPassword" name="password" required>
            </div>
            
            <button type="submit" class="btn">Login to Your Account</button>
            
            <div class="switch-text">
                Don't have an account? <a href="#" onclick="showTab('register')">Register here</a>
            </div>
        </form>

        <!-- Registration Form -->
        <form method="POST" class="form-container" id="registerForm">
            <input type="hidden" name="customer_register" value="1">
            
            <div class="form-group">
                <label for="regName">Full Name *</label>
                <input type="text" id="regName" name="name" required 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                       placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label for="regEmail">Email Address *</label>
                <input type="email" id="regEmail" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="your@email.com">
            </div>
            
            <div class="form-group">
                <label for="regPhone">Phone Number *</label>
                <input type="tel" id="regPhone" name="phone" required 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                       placeholder="+91 9876543210 or 9876543210">
                <div class="phone-hint">Enter 10-digit mobile number with or without country code</div>
            </div>
            
            <div class="form-group">
                <label for="regPassword">Password *</label>
                <input type="password" id="regPassword" name="password" required 
                       placeholder="Minimum 6 characters">
            </div>
            
            <div class="form-group">
                <label for="regConfirmPassword">Confirm Password *</label>
                <input type="password" id="regConfirmPassword" name="confirm_password" required 
                       placeholder="Re-enter your password">
            </div>
            
            <button type="submit" class="btn">Create Account</button>
            
            <div class="switch-text">
                Already have an account? <a href="#" onclick="showTab('login')">Login here</a>
            </div>
        </form>

        <div class="owner-login-link">
            <p>Are you an owner or staff member? <a href="owner_login.php">Login to admin panel</a></p>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.form-container').forEach(form => {
                form.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            if (tabName === 'login') {
                document.getElementById('loginForm').classList.add('active');
                document.querySelectorAll('.tab')[0].classList.add('active');
            } else {
                document.getElementById('registerForm').classList.add('active');
                document.querySelectorAll('.tab')[1].classList.add('active');
            }
        }
        
        <?php if ($_POST && isset($_POST['customer_register'])): ?>
            showTab('register');
        <?php endif; ?>

        // Phone number formatting
        document.getElementById('regPhone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // If it starts with 91 and has more than 10 digits, keep it as international
            if (value.startsWith('91') && value.length > 10) {
                value = '+' + value;
            }
            // If it's 10 digits, format as Indian number
            else if (value.length === 10) {
                value = '+91' + value;
            }
            
            e.target.value = value;
        });

        // Password confirmation validation
        const confirmPassword = document.getElementById('regConfirmPassword');
        const password = document.getElementById('regPassword');
        
        if (confirmPassword && password) {
            confirmPassword.addEventListener('input', function() {
                if (this.value !== password.value) {
                    this.style.borderColor = '#c33';
                } else {
                    this.style.borderColor = '#363';
                }
            });
        }

        // Form submission handling
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('.btn');
                    submitBtn.innerHTML = this.id === 'loginForm' 
                        ? '<i class="fas fa-spinner fa-spin"></i> Logging in...' 
                        : '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
                    submitBtn.disabled = true;
                });
            });
        });
    </script>
</body>
</html>