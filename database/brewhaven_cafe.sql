-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 25, 2025 at 11:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `brewhaven_cafe`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `type`, `title`, `message`, `target_url`, `is_read`, `created_by`, `created_at`, `read_at`) VALUES
(1, 'pending_orders', 'Pending Orders', 'You have 4 pending orders waiting for approval', 'admin_orders.php?status=pending', 0, NULL, '2025-10-23 13:33:35', NULL),
(2, 'pending_reservations', 'Pending Reservations', 'You have 5 reservation requests need confirmation', 'admin_reservations.php?status=pending', 0, NULL, '2025-10-23 13:33:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Coffee', 'Traditional Indian coffee varieties'),
(2, 'Tea', 'Various Indian tea preparations'),
(3, 'Indian Snacks', 'Popular Indian snacks and street food'),
(4, 'Sweets', 'Traditional Indian desserts');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `password`, `created_at`, `updated_at`) VALUES
(2, 'Prince Shukla', 'prince.shukla160107@gmail.com', '+918744979546', '$2y$10$9Oj9xRzt3XciFn81Tqq4TekXwuUNiKTJFWnKoTJMumRzP.L/rXyZG', '2025-10-24 10:24:00', '2025-10-24 10:24:00');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_points`
--

CREATE TABLE `loyalty_points` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loyalty_points`
--

INSERT INTO `loyalty_points` (`id`, `customer_id`, `points`, `last_updated`) VALUES
(1, 1, 10, '2025-10-03 08:50:07');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_transactions`
--

CREATE TABLE `loyalty_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `type` enum('added','deducted') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_vegetarian` tinyint(1) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `stock_quantity` int(11) DEFAULT 100,
  `low_stock_threshold` int(11) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `description`, `price`, `image_url`, `category`, `is_vegetarian`, `is_available`, `created_at`, `stock_quantity`, `low_stock_threshold`) VALUES
(1, 'South Indian Filter Coffee', 'Traditional coffee with frothed milk', 80.00, 'https://as1.ftcdn.net/v2/jpg/13/73/51/36/1000_F_1373513606_AZ29QACvzP7PAWcUae5r40GvmbImzaPP.jpg', 'Coffee', 1, 1, '2025-09-30 06:48:22', 100, 10),
(2, 'Masala Chai', 'Spiced Indian tea with ginger and cardamom', 60.00, 'https://as2.ftcdn.net/v2/jpg/16/81/43/61/1000_F_1681436166_TwtyuGBnb4r4IkabKyH1SBHD6Nha5nZQ.jpg', 'Tea', 1, 1, '2025-09-30 06:48:22', 100, 10),
(3, 'Samosa', 'Crispy pastry with spiced potatoes and peas', 60.00, 'https://as2.ftcdn.net/v2/jpg/04/66/42/25/1000_F_466422564_LICnIvfjfGhieSKG4gxU35LirfjrxbOB.jpg', 'Snacks', 1, 1, '2025-09-30 06:48:22', 100, 10),
(4, 'Pav Bhaji', 'Spiced vegetable mash with buttered bread', 150.00, 'https://as2.ftcdn.net/v2/jpg/16/78/39/25/1000_F_1678392531_OQre4w7WXXs7n48bQCwShmvEWJlPiEaQ.jpg', 'Main Course', 1, 1, '2025-09-30 06:48:22', 100, 10),
(5, 'Gulab Jamun', 'Sweet milk balls in rose syrup', 100.00, 'https://as2.ftcdn.net/v2/jpg/10/17/65/75/1000_F_1017657553_BFjfgC9jaR5KFxJKfQZxVySUnYZ211bR.jpg', 'Dessert', 1, 1, '2025-09-30 06:48:22', 100, 10),
(6, 'Mango Cheese cake', 'Sweet and Delicious', 50.00, 'https://as2.ftcdn.net/jpg/12/04/11/49/1000_F_1204114978_1sLx0IVeanguJPjbe4koHKdSTm7uHZ7F.jpg', 'Dessert', 0, 1, '2025-10-02 15:04:21', 100, 10),
(7, 'Ice Cream Cake', 'Delicious chocolate chip ice cream cake', 200.00, 'https://as1.ftcdn.net/v2/jpg/10/75/41/52/1000_F_1075415208_7U35NbjnfF3deSIrqSmHKInvdtjJvn3e.webp', 'Dessert', 0, 1, '2025-10-02 15:07:47', 100, 10),
(8, 'Pizza', 'Delicious vegetarian pizza with champignon mushrooms, tomatoes, mozzarella, peppers and black olives.', 120.00, 'https://as1.ftcdn.net/v2/jpg/03/14/02/34/1000_F_314023460_3MOUMtgUtHYfWOEnE1BR9IcGjvpdMJgF.jpg', 'Snacks', 0, 1, '2025-10-02 15:10:00', 100, 10),
(9, 'Chole Bhature', 'Chole Bhature', 60.00, 'https://as2.ftcdn.net/v2/jpg/16/31/30/39/1000_F_1631303977_D50eKKxO3DEKcLGW5L3MQQH4cUadkXj1.webp', 'Main Course', 0, 1, '2025-10-03 07:23:26', 100, 10);

--
-- Triggers `menu_items`
--
DELIMITER $$
CREATE TRIGGER `before_menu_item_update` BEFORE UPDATE ON `menu_items` FOR EACH ROW BEGIN
    IF NEW.stock_quantity <= NEW.low_stock_threshold AND OLD.stock_quantity > NEW.low_stock_threshold THEN
        INSERT INTO notifications (type, title, message, related_id, priority, action_url)
        VALUES (
            'inventory',
            'Low Stock Alert',
            CONCAT(NEW.name, ' is running low. Current stock: ', NEW.stock_quantity),
            NEW.id,
            'high',
            CONCAT('admin_menu.php?action=edit&id=', NEW.id)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('order','reservation','review','inventory','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('unread','read','archived') DEFAULT 'unread',
  `recipient_id` int(11) DEFAULT NULL,
  `recipient_type` enum('owner','staff','customer') DEFAULT 'owner',
  `action_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `related_id`, `priority`, `status`, `recipient_id`, `recipient_type`, `action_url`, `created_at`, `updated_at`, `expires_at`) VALUES
(1, 'order', 'New Order #1', 'New order from Guest for ₹219.00', 1, 'high', 'unread', NULL, 'owner', 'admin_orders.php?action=view&id=1', '2025-10-03 07:15:14', '2025-10-03 07:15:14', NULL),
(2, 'reservation', 'New Reservation Request', 'Reservation for  (3 people) on 2025-10-04', 1, 'medium', 'unread', NULL, 'owner', 'admin_reservations.php?action=view&id=1', '2025-10-03 07:15:51', '2025-10-03 07:15:51', NULL),
(3, 'order', 'New Order #2', 'New order from Guest for ₹84.00', 2, 'high', 'unread', NULL, 'owner', 'admin_orders.php?action=view&id=2', '2025-10-23 12:28:22', '2025-10-23 12:28:22', NULL),
(4, 'order', 'New Order #3', 'New order from Guest for ₹84.00', 3, 'high', 'unread', NULL, 'owner', 'admin_orders.php?action=view&id=3', '2025-10-23 12:38:20', '2025-10-23 12:38:20', NULL),
(5, 'order', 'New Order #4', 'New order from Guest for ₹114.00', 4, 'high', 'unread', NULL, 'owner', 'admin_orders.php?action=view&id=4', '2025-10-23 12:57:36', '2025-10-23 12:57:36', NULL),
(6, 'order', 'New Order #5', 'New order from Guest for ₹345.00', 5, 'high', 'unread', NULL, 'owner', 'admin_orders.php?action=view&id=5', '2025-10-23 14:25:28', '2025-10-23 14:25:28', NULL),
(7, 'order', 'New Order #6', 'New order from Guest for ₹114.00', 6, 'high', 'unread', NULL, 'owner', 'admin_orders.php?action=view&id=6', '2025-10-23 14:31:21', '2025-10-23 14:31:21', NULL),
(8, 'order', 'New Order #7', 'New order from Prince Shukla for ₹114.00', 7, 'high', 'unread', NULL, 'owner', 'admin_orders.php?action=view&id=7', '2025-10-24 10:24:48', '2025-10-24 10:24:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('owner','staff','customer') NOT NULL,
  `notification_type` enum('order','reservation','review','inventory','system') NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 0,
  `push_notifications` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_preferences`
--

INSERT INTO `notification_preferences` (`id`, `user_id`, `user_type`, `notification_type`, `enabled`, `email_notifications`, `push_notifications`, `created_at`, `updated_at`) VALUES
(1, 1, 'owner', 'order', 1, 1, 0, '2025-10-01 15:27:28', '2025-10-01 15:27:28'),
(2, 1, 'owner', 'reservation', 1, 1, 0, '2025-10-01 15:27:28', '2025-10-01 15:27:28'),
(3, 1, 'owner', 'review', 1, 0, 0, '2025-10-01 15:27:28', '2025-10-01 15:27:28'),
(4, 1, 'owner', 'inventory', 1, 1, 0, '2025-10-01 15:27:28', '2025-10-01 15:27:28'),
(5, 1, 'owner', 'system', 1, 1, 0, '2025-10-01 15:27:28', '2025-10-01 15:27:28');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','completed','cancelled') DEFAULT 'pending',
  `order_type` enum('delivery','pickup') DEFAULT 'delivery',
  `delivery_address` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cod','card','upi','online') DEFAULT 'cod',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `customer_name`, `customer_email`, `customer_phone`, `total_amount`, `status`, `order_type`, `delivery_address`, `special_instructions`, `created_at`, `payment_method`, `payment_status`) VALUES
(7, 2, 'Prince Shukla', 'prince.shukla160107@gmail.com', '+918744979546', 114.00, 'pending', 'delivery', 'noida sector 87', 'carefull', '2025-10-24 10:24:48', 'cod', 'pending');

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_insert` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    IF NEW.status = 'pending' THEN
        INSERT INTO notifications (type, title, message, related_id, priority, action_url)
        VALUES (
            'order',
            CONCAT('New Order #', NEW.id),
            CONCAT('New order from ', COALESCE((SELECT name FROM customers WHERE id = NEW.customer_id), 'Guest'), ' for ₹', NEW.total_amount),
            NEW.id,
            'high',
            CONCAT('admin_orders.php?action=view&id=', NEW.id)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `item_name`, `quantity`, `price`) VALUES
(9, 7, 1, '', 1, 80.00);

-- --------------------------------------------------------

--
-- Table structure for table `owners`
--

CREATE TABLE `owners` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','staff') DEFAULT 'manager',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owners`
--

INSERT INTO `owners` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@brewhaven.com', '$2y$10$Y0PPTZc.ZbZVSIKscwg4L.TFS4O9Qz/0j/c/dGjwcpcScstWRUdg6', 'BrewHaven Admin', '+919876543210', 'admin', 1, '2025-09-30 06:48:22', '2025-09-30 06:48:22');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `party_size` int(11) NOT NULL CHECK (`party_size` >= 1 and `party_size` <= 20),
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','confirmed','seated','completed','cancelled','no_show') DEFAULT 'pending',
  `table_number` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `customer_id`, `customer_name`, `customer_phone`, `customer_email`, `reservation_date`, `reservation_time`, `party_size`, `special_requests`, `status`, `table_number`, `notes`, `created_at`, `updated_at`) VALUES
(7, 2, 'Prince Shukla', '+918744979546', 'prince.shukla160107@gmail.com', '2025-10-28', '13:30:00', 6, 'thank you', 'confirmed', 'T1', '', '2025-10-24 10:25:20', '2025-10-24 10:26:27');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_history`
--

CREATE TABLE `reservation_history` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `action` enum('created','updated','cancelled','status_changed') NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_history`
--

INSERT INTO `reservation_history` (`id`, `reservation_id`, `action`, `old_status`, `new_status`, `changed_by`, `notes`, `created_at`) VALUES
(4, 7, 'created', NULL, 'pending', 'customer', 'Reservation created online', '2025-10-24 10:25:20');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `review_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `customer_id`, `customer_name`, `customer_email`, `menu_item_id`, `rating`, `title`, `comment`, `review_date`, `status`, `admin_notes`, `is_featured`, `created_at`, `updated_at`) VALUES
(1, NULL, 'John Doe', 'john@example.com', NULL, 5, 'Amazing Coffee!', 'The best coffee I\'ve ever had. The atmosphere is wonderful too!', '2024-01-15', 'approved', NULL, 0, '2025-10-03 09:06:24', '2025-10-03 09:06:24'),
(2, NULL, 'Sarah Smith', 'sarah@example.com', NULL, 4, 'Great Service', 'Friendly staff and delicious pastries. Will definitely come back!', '2024-01-14', 'approved', NULL, 0, '2025-10-03 09:06:24', '2025-10-03 09:06:24'),
(3, NULL, 'Mike Johnson', 'mike@example.com', NULL, 3, 'Good but crowded', 'Food was good but the place was too crowded during peak hours.', '2024-01-13', 'pending', NULL, 0, '2025-10-03 09:06:24', '2025-10-03 09:06:24'),
(4, NULL, 'Emily Davis', 'emily@example.com', NULL, 5, 'Perfect Experience', 'Everything was perfect! From the coffee to the service. Highly recommended!', '2024-01-12', 'approved', NULL, 0, '2025-10-03 09:06:24', '2025-10-03 09:06:24'),
(5, NULL, 'David Wilson', 'david@example.com', NULL, 2, 'Disappointing', 'Coffee was cold and service was slow. Expected better.', '2024-01-11', 'pending', NULL, 0, '2025-10-03 09:06:24', '2025-10-03 09:06:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `loyalty_points`
--
ALTER TABLE `loyalty_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_recipient` (`recipient_id`,`recipient_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_preference` (`user_id`,`user_type`,`notification_type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `owners`
--
ALTER TABLE `owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservation_date` (`reservation_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `reservation_history`
--
ALTER TABLE `reservation_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservation_id` (`reservation_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loyalty_points`
--
ALTER TABLE `loyalty_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `owners`
--
ALTER TABLE `owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reservation_history`
--
ALTER TABLE `reservation_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `loyalty_points`
--
ALTER TABLE `loyalty_points`
  ADD CONSTRAINT `loyalty_points_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD CONSTRAINT `loyalty_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reservation_history`
--
ALTER TABLE `reservation_history`
  ADD CONSTRAINT `reservation_history_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
