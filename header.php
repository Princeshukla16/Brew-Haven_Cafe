<?php
// header.php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrewHaven Cafe - Premium Indian Coffee & Snacks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles here */
        <?php include 'styles.css'; ?>
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
        <div class="container header-container">
            <div class="logo">
                <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MCIgaGVpZ2h0PSI1MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM2ZjRlMzciIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMTggNkg2YTIgMiAwIDAgMC0yIDJ2OGEyIDIgMCAwIDAgMiAyaDExYTIgMiAwIDAgMCAyLTJ2LTVNMTA4aDQiLz48cGF0aCBkPSJNOCAxMnYtMWEyIDIgMCAwIDEgMi0yaDZhMiAyIDAgMCAxIDIgMnYxIi8+PGNpcmNsZSBjeD0iMTYiIGN5PSIxMCIgcj0iMSIvPjwvc3ZnPg==" alt="BrewHaven Logo">
                <h1>BrewHaven</h1>
            </div>
            
            <nav id="mainNav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="menu.php">Menu</a></li>
                    <li><a href="cart.php">Cart (<span id="cartCount"><?php echo getCartItemCount(); ?></span>)</a></li>
                    <li><a href="reservations.php">Reservations</a></li>
                    <li><a href="reviews.php">Review</a></li>
                    
                    <?php if (isCustomerLoggedIn()): ?>
                        <li><a href="my_account.php">My Account (<?php echo getCustomerName(); ?>)</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php elseif (isOwnerLoggedIn()): ?>
                        <li><a href="admin_dashboard.php">Admin Panel</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="customer_login.php">Login</a></li>
                        <li><a href="owner_login.php">Owner Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>