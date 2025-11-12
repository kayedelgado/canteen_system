<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

// NOTE: DO NOT MODIFY EXISTING PHP LOGIC. KEEPING THE INCLUDES AS THEY WERE.
include('header.php'); // Includes the initial style block and global setup
include('navbar.php'); // Includes the navigation bar
include('../config/db_connect.php');

// Total Orders Today
$today = date('Y-m-d');
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = '$today'")->fetch_assoc()['count'];

// Total Sales Today
$sales_result = $conn->query("SELECT SUM(total) as sum FROM orders WHERE DATE(order_date) = '$today'")->fetch_assoc()['sum'];
$total_sales = $sales_result ?: 0; // Use 0 if result is null

// Products Low in Stock (stock <= 5)
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock <= 5")->fetch_assoc()['count'];

// Count of Active Cashiers (assuming a simple method or session check)
// For demonstration, let's use the total number of cashier accounts.
$cashier_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'cashier'")->fetch_assoc()['count'];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
/* ---------------------------------------------------- */
/* Canteen Theme Variables (Recopy from header.php for completeness) */
/* ---------------------------------------------------- */
:root {
    --primary-color: #f68aa7ff; /* Warm Orange/Rust - Thematic Accent */
    --secondary-color: #4c1a22ff; /* Dark Gray/Blue - Primary Text & Headings */
    --success-color: #de90e3ff; /* Green - Add/Save actions */
    --danger-color: #EF4444; /* Red - Delete/Alerts */
    --warning-color: #992c54ff; /* Amber/Yellow for warnings */
    --background-light: #de90a1ff; /* Very light background */
    --container-bg: #FFFFFF; /* Crisp White container */
    --border-color: #E5E7EB;
    --text-color: #374151; /* Darker grey for main text */
    --font-family: 'Poppins', sans-serif;
}

/* ---------------------------------------------------- */
/* Global Container & Heading */
/* ---------------------------------------------------- */
body {
    background-color: var(--background-light);
    font-family: var(--font-family);
    color: var(--text-color);
}

.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 30px;
    background-color: var(--container-bg);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.dashboard-header {
    margin-bottom: 40px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-title {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--secondary-color);
    margin: 0;
}

.dashboard-date {
    font-size: 1rem;
    color: var(--text-color);
    font-weight: 600;
    background-color: #F9FAFB;
    padding: 8px 15px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

/* ---------------------------------------------------- */
/* 1. Statistics Grid (KPIs) */
/* ---------------------------------------------------- */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.card {
    background-color: var(--container-bg);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 6px solid;
    display: flex;
    flex-direction: column;
    align-items: flex-start; /* Align content to the left */
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.15);
}

.card h3 {
    margin-top: 10px;
    margin-bottom: 5px;
    font-size: 1rem;
    font-weight: 600;
    color: #6B7280; /* Subdued label color */
}

.card-value {
    font-size: 2.8rem; /* Extra large, easy-to-read metric */
    font-weight: 800;
    margin: 0;
    line-height: 1.1;
}

.card-icon {
    font-size: 2.5rem;
    line-height: 1;
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.6);
}

/* Card Specific Colors */
/* Orders (Warm Red/Orange) */
.card-orders {
    border-color: var(--primary-color);
}
.card-orders .card-icon { color: var(--primary-color); background-color: #FEE7D8; }
.card-orders .card-value { color: var(--secondary-color); }

/* Sales (Success Green) */
.card-sales {
    border-color: var(--success-color);
}
.card-sales .card-icon { color: var(--success-color); background-color: #D1FAE5; }
.card-sales .card-value { color: var(--success-color); }

/* Staff/Users (Secondary Color) */
.card-staff {
    border-color: #3B82F6; /* Clean Blue */
}
.card-staff .card-icon { color: #3B82F6; background-color: #DBEAFE; }
.card-staff .card-value { color: var(--secondary-color); }


/* ---------------------------------------------------- */
/* 2. Low Stock Alert Section */
/* ---------------------------------------------------- */
.alert-section {
    background-color: #FEF3C7; /* Light warning yellow */
    border: 1px solid var(--warning-color);
    padding: 25px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
}

.alert-message {
    display: flex;
    align-items: center;
    gap: 15px;
}

.alert-message i {
    font-size: 2.5rem;
    color: var(--warning-color);
}

.alert-message h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #92400E; /* Dark brown text */
    margin: 0;
}

.alert-message p {
    font-size: 1rem;
    color: #92400E;
    margin: 5px 0 0 0;
}

.alert-action-btn {
    background-color: var(--primary-color);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.2s;
}

.alert-action-btn:hover {
    background-color: #D65A0A;
}

/* ---------------------------------------------------- */
/* 3. Quick Management Links (Secondary Nav) */
/* ---------------------------------------------------- */
.management-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.quick-link-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    text-decoration: none;
    background-color: #F8F9FA;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    color: var(--secondary-color);
    transition: background-color 0.2s, box-shadow 0.2s;
}

.quick-link-card:hover {
    background-color: #EFEFEF;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.quick-link-card i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.quick-link-card h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
}

/* ---------------------------------------------------- */
/* Responsive Adjustments */
/* ---------------------------------------------------- */
@media (max-width: 992px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .dashboard-date {
        margin-top: 10px;
    }
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    .alert-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }
    .alert-action-btn {
        width: 100%;
        text-align: center;
    }
}
@media (max-width: 576px) {
    .container {
        padding: 15px;
        margin: 10px;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .card-value {
        font-size: 2.2rem;
    }
}
</style>
<div class="container">
    <header class="dashboard-header">
        <div>
            <h2 class="welcome-title">
                <i class="fas fa-chart-line" style="color: var(--primary-color);"></i> Kaye Delgado
            </h2>
            <p style="font-size: 1.1rem; color: var(--secondary-color); margin-top: 5px;">
                Welcome Back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>! Here is your daily overview.
            </p>
        </div>
        <div class="dashboard-date">
            <i class="far fa-calendar-alt"></i> Today: <?php echo date('F j, Y'); ?>
        </div>
    </header>

    <div class="stats-grid">
        <div class="card card-orders">
            <div class="card-icon"><i class="fas fa-shopping-basket"></i></div>
            <h3>Total Orders Processed</h3>
            <p class="card-value"><?php echo $total_orders; ?></p>
        </div>

        <div class="card card-sales">
            <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
            <h3>Today's Total Revenue</h3>
            <p class="card-value">₱ <?php echo number_format($total_sales, 2); ?></p>
        </div>

        <div class="card card-staff">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <h3>Cashier Accounts</h3>
            <p class="card-value"><?php echo $cashier_count; ?></p>
        </div>
        
        <div class="card" style="border-color: var(--warning-color); background-color: #FFFBEB;">
            <div class="card-icon" style="color: var(--warning-color);"><i class="fas fa-boxes"></i></div>
            <h3>Low Stock Alerts</h3>
            <p class="card-value" style="color: var(--warning-color);"><?php echo $low_stock_count; ?></p>
            <p style="font-size: 0.9rem; margin-top: 10px; color: #92400E; font-weight: 600;">
                 <i class="fas fa-arrow-right"></i> Requires urgent restock.
            </p>
        </div>
    </div>
    
    <?php if ($low_stock_count > 0): ?>
    <section class="alert-section">
        <div class="alert-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <h4>URGENT ATTENTION REQUIRED!</h4>
                <p>You have **<?php echo $low_stock_count; ?>** product(s) below the minimum stock level.</p>
            </div>
        </div>
        <a href="manage_products.php" class="alert-action-btn">
            <i class="fas fa-cogs"></i> Manage Stock
        </a>
    </section>
    <?php endif; ?>

    <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--secondary-color); margin-bottom: 25px;">
        <i class="fas fa-tools" style="color: var(--primary-color);"></i> Management Hub
    </h2>
    <div class="management-links">
        <a href="manage_products.php" class="quick-link-card">
            <i class="fas fa-hamburger"></i>
            <h4>Products</h4>
        </a>
        <a href="manage_categories.php" class="quick-link-card">
            <i class="fas fa-tags"></i>
            <h4>Categories</h4>
        </a>
        <a href="manage_users.php" class="quick-link-card">
            <i class="fas fa-user-tie"></i>
            <h4>Staff Accounts</h4>
        </a>
        <a href="orders.php" class="quick-link-card">
            <i class="fas fa-file-invoice-dollar"></i>
            <h4>View All Orders</h4>
        </a>
    </div>
</div>