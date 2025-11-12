<?php
// Note: Session check is already handled in dashboard.php, but including a safety check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Assume $_SESSION['username'] exists for a personalized greeting
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Cashier';
?>

<!-- Internal CSS for the Navbar -->
<style>
/* Canteen Theme Variables (Copied from former header.php for standalone use) */
:root {
    --color-primary: #E54B4B; /* Appetizing Red/Rust */
    --color-secondary: #332D2D; /* Dark Charcoal - Primary Text & Background Accent */
    --color-success: #4CAF50; /* Green */
    --color-danger: #F44336; /* Bright Red */
    --color-background: #FAF8F1; /* Light Cream/Paper Background */
    --color-card-bg: #FFFFFF;
    --color-text: #332D2D;
    --color-border: #E0E0E0;
    --font-family: 'Poppins', sans-serif;
}

.navbar {
    background-color: var(--color-secondary); /* Dark background for a classic feel */
    padding: 10px 30px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    font-family: var(--font-family);
    min-height: 50px;
    flex-wrap: wrap; /* Ensure responsiveness */
}

.navbar a {
    color: #F8F8F8;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 4px;
    transition: background-color 0.2s, color 0.2s;
    font-weight: 500;
    margin: 0 5px;
}

.navbar a:hover, .navbar a.active {
    background-color: var(--color-primary); /* Use primary color as hover/active highlight */
    color: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.user-info {
    font-weight: 600;
    color: #FFD700; /* Gold accent for user name */
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 768px) {
    .navbar {
        flex-direction: column;
        align-items: stretch;
        padding: 10px 15px;
    }
    .navbar a {
        display: block;
        text-align: center;
        margin: 5px 0;
    }
    .user-info {
        order: -1; /* Move user info to the top */
        margin-bottom: 10px;
        justify-content: center;
    }
}
</style>

    <!-- Canteen Logo/Brand -->
    <a href="dashboard.php" style="font-size: 1.4rem; font-weight: 700; color: white;">
        <span style="font-size: 1.2em; vertical-align: middle;">🍽️</span> Canteen POS
    </a>
    
    <div style="display: flex; flex-wrap: wrap;">
        <!-- Links -->
        <a href="take_order.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'take_order.php') ? 'active' : ''; ?>">
            <span style="margin-right: 5px;">⚡</span> New Order
        </a>
        <a href="orders.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : ''; ?>">
            <span style="margin-right: 5px;">📜</span> My Orders
        </a>
        <a href="payments.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'active' : ''; ?>">
            <span style="margin-right: 5px;">💰</span> Payments
        </a>
    </div>

    <!-- User & Logout -->
    <div style="display: flex; align-items: center;">
        <span class="user-info">
            <span style="font-size: 1.2em;">👤</span> Welcome, <?php echo htmlspecialchars($username); ?>
        </span>
        <a href="../logout.php" style="margin-left: 20px; background-color: var(--color-danger); color: white;">
            Logout
        </a>
    </div>
</nav>