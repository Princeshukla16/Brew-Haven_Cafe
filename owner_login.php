<?php
// owner_login.php
require_once 'config.php';
redirectIfOwnerLoggedIn();

$error = '';

if ($_POST && isset($_POST['owner_login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        $database = new Database();
        $db = $database->getConnection();

        try {
            $stmt = $db->prepare("SELECT * FROM owners WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($owner) {
                if (password_verify($password, $owner['password'])) {
                    // Login successful
                    $_SESSION['owner_id'] = $owner['id'];
                    $_SESSION['owner_username'] = $owner['username'];
                    $_SESSION['owner_name'] = $owner['full_name'];
                    $_SESSION['owner_email'] = $owner['email'];
                    $_SESSION['owner_role'] = $owner['role'];

                    // Redirect to dashboard
                    header('Location: admin_dashboard.php');
                    exit;
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "Username not found or account inactive";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Login - BrewHaven Cafe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #6f4e37 0%, #8b6b4d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #6f4e37;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: #6f4e37;
            outline: none;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: #6f4e37;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #5a3e2c;
        }

        .demo-credentials {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
            border-left: 4px solid #6f4e37;
        }

        .demo-credentials h4 {
            margin-bottom: 8px;
            color: #333;
        }

        .links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .links a {
            color: #6f4e37;
            text-decoration: none;
            margin: 0 10px;
            font-size: 14px;
        }

        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>BrewHaven Cafe</h1>
            <p>Owner/Staff Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="owner_login" value="1">
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="admin" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" value="admin123" required>
            </div>

            <button type="submit" class="login-btn">Login to Admin Panel</button>
        </form>

        <div class="demo-credentials">
            <h4>Demo Credentials:</h4>
            <p><strong>Username:</strong> admin</p>
            <p><strong>Password:</strong> admin123</p>
            <p><strong>Role:</strong> Administrator</p>
        </div>

        <div class="links">
            <a href="login.php">Login Portal</a>
            <a href="index.php">Main Website</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const loginBtn = document.querySelector('.login-btn');

            form.addEventListener('submit', function() {
                loginBtn.textContent = 'Logging in...';
                loginBtn.disabled = true;
            });
        });
    </script>
</body>
</html>