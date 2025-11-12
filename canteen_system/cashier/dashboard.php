<?php
// Start session
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// CRITICAL: Session and Role Check (Header Logic)
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier'){
    header("Location: ../login.php"); 
    exit();
}

// Database Connection Logic (config/db_connect.php Logic)
// CRITICAL: Replace these placeholders with your actual database credentials
$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "canteen_db"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Utility function for safely retrieving session data
function getSession($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

// --- Dashboard Logic ---
$cashier_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// 1. Total Orders Today (by this Cashier)
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE cashier_id = '$cashier_id' AND DATE(order_date) = '$today'")->fetch_assoc()['count'];

// 2. Total Sales Today (by this Cashier)
$sales_result = $conn->query("SELECT SUM(total) as sum FROM orders WHERE cashier_id = '$cashier_id' AND DATE(order_date) = '$today'")->fetch_assoc()['sum'];
$total_sales = $sales_result ?: 0; 

// 3. Products Low in Stock (Global Alert for Cashier)
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock <= 5")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Canteen System POS</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Variables: Cashier Theme (Blue) */
        :root {
            --primary-color: #3B82F6; /* Blue - Main Action/Accent */
            --secondary-color: #1F2937; /* Dark Gray/Blue - Primary Text & Headings */
            --success-color: #10B981; /* Green - Sales */
            --danger-color: #EF4444; /* Red - Low Stock */
            --background-light: #F8F8F8; 
            --card-bg: #FFFFFF;
            --color-border: #E5E7EB;
            --font-family: 'Poppins', sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--background-light);
            color: var(--secondary-color);
            margin: 0;
            padding-top: 65px; /* Space for the fixed navbar */
        }
        
        /* Navbar (cashier/navbar.php Logic) */
        nav {
            background-color: var(--secondary-color); 
            padding: 10px 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            min-height: 50px; 
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 500;
            box-sizing: border-box;
        }
        .nav-left .logo { font-size: 1.25rem; font-weight: 700; color: white; text-decoration: none; }
        .nav-links { display: flex; gap: 15px; align-items: center; margin-left: 20px; }
        .nav-links a { color: white; text-decoration: none; padding: 5px 10px; border-radius: 6px; font-weight: 500; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(255, 255, 255, 0.15); color: var(--primary-color); font-weight: 700; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-greeting { font-size: 1rem; font-weight: 400; color: #E5E7EB; }
        .user-greeting strong { font-weight: 600; color: white; }
        .logout-btn { padding: 8px 15px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 600; text-decoration: none; transition: background-color 0.2s; }
        .logout-btn:hover { background-color: #2563EB; }

        /* Dashboard Content Styles */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        }
        h2 { font-size: 1.75rem; font-weight: 700; color: var(--secondary-color); margin-bottom: 25px; border-bottom: 2px solid var(--color-border); padding-bottom: 10px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        .card {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 6px solid var(--primary-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1); }
        .card h3 { margin-top: 10px; margin-bottom: 15px; font-size: 1.15rem; font-weight: 600; color: var(--secondary-color); }
        .card p { font-size: 2.8rem; font-weight: 800; margin: 0; color: var(--primary-color); }
        .card-icon { font-size: 2.5rem; line-height: 1; margin-bottom: 10px; }
        .card-low-stock { border-left-color: var(--danger-color); }
        .card-low-stock p { color: var(--danger-color); }
        .card-sales { border-left-color: var(--success-color); }
        .card-sales p { color: var(--success-color); }
        
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; text-align: center; transition: background-color 0.2s; cursor: pointer; border: none; font-size: 1rem; }
        .btn-primary { background-color: var(--primary-color); color: white; }
    </style>
</head>
<body>

<nav>
    <div class="nav-left">
        <a href="pos.php" class="logo">
            <span style="font-size: 1.2em; vertical-align: middle;">💰</span> Cashier POS
        </a>
        <div class="nav-links">
            <a href="pos.php" class="">Point of Sale</a>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="orders.php" class="">My Orders</a>
            <a href="payments.php" class="">My Payments</a>
        </div>
    </div>
    <div class="nav-right">
        <span class="user-greeting">Hello, <strong><?php echo htmlspecialchars(getSession('username', 'Cashier')); ?></strong></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="container">
    <h2><span class="card-icon">📊</span> Cashier Dashboard - Today's Summary</h2>

    <div class="stats-grid">
        <div class="card card-orders">
            <div class="card-icon" style="color: var(--primary-color);">🛒</div>
            <h3>Total Orders Handled</h3>
            <p><?php echo number_format($total_orders); ?></p>
        </div>

        <div class="card card-sales">
            <div class="card-icon" style="color: var(--success-color);">💵</div>
            <h3>Total Sales Amount</h3>
            <p>₱ <?php echo number_format($total_sales, 2); ?></p>
        </div>

        <div class="card card-low-stock">
            <div class="card-icon" style="color: var(--danger-color);">🚨</div>
            <h3>Products Low Stock</h3>
            <p><?php echo number_format($low_stock_count); ?></p>
        </div>
    </div>
    
    <div style="margin-top: 40px; text-align: center;">
        <a href="pos.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 15px 30px;">
            Start New Transaction <span style="margin-left: 10px;">▶️</span>
        </a>
    </div>
</div>
</body>
</html>
