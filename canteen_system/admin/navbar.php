<?php
// Note: Session check is already handled in header.php, but including a safety check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Assume $_SESSION['username'] or $_SESSION['user_full_name'] exists for a personalized greeting
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
?>

<!-- Internal CSS for the Navbar -->
<!-- We use internal CSS here because the navbar styles are often very specific and sit outside the main .container -->
<style>
/* Use the variables defined in header.php */
nav {
    background-color: var(--secondary-color); /* Dark background for high contrast */
    padding: 10px 30px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    font-family: var(--font-family);
    min-height: 60px; /* Ensure a comfortable height */
}

/* Logo/Brand Name */
.nav-left .logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    margin-right: 30px;
    letter-spacing: 1px;
}

/* Navigation Links Container */
.nav-links {
    display: flex;
    gap: 20px;
}

nav a {
    color: #E5E7EB; /* Light gray text for contrast */
    text-decoration: none;
    font-weight: 500;
    padding: 5px 0;
    position: relative;
    transition: color 0.2s;
}

nav a:hover {
    color: var(--primary-color); /* Highlight with the canteen orange */
}

/* Active Link Indicator (Subtle underline matching the theme) */
/* You will need to dynamically add the 'active' class to the link for the current page */
nav a.active {
    color: var(--primary-color);
    font-weight: 600;
}
nav a.active::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: var(--primary-color);
    border-radius: 2px;
}

/* Right side (User Info and Logout) */
.nav-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.welcome-text {
    color: #D1D5DB; /* Slightly darker than links */
    font-size: 0.95rem;
}

/* Logout Button styling (makes it look like a button) */
.logout-btn {
    background-color: var(--danger-color);
    color: white;
    padding: 8px 15px;
    border-radius: 6px;
    font-weight: 600;
    transition: background-color 0.2s, box-shadow 0.2s;
}
.logout-btn:hover {
    background-color: #DC2626;
    color: white; /* Keep text white on hover */
    text-decoration: none;
}

/* Responsive adjustments (Hide links for small screens) */
@media (max-width: 900px) {
    nav {
        flex-wrap: wrap;
        padding: 10px 15px;
    }
    .nav-left {
        width: 100%;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .nav-links {
        display: none; /* Hide main links on mobile; consider a hamburger menu implementation later */
    }
    .nav-right {
        width: 100%;
        justify-content: flex-end;
        margin-bottom: 0;
    }
    .nav-left .logo {
        margin-right: 0;
    }
}
</style>

<nav>
    <div class="nav-left">
        <a href="dashboard.php" class="logo">
            <span style="font-size: 1.2em; vertical-align: middle;">🧑‍🍳</span> Kaye Delgado
        </a>
        <div class="nav-links">
            <!-- Add 'active' class dynamically if this page matches the link's destination -->
            <a href="dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="manage_users.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'active' : ''; ?>">Users</a>
            <a href="manage_categories.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_categories.php') ? 'active' : ''; ?>">Categories</a>
            <a href="manage_products.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_products.php') ? 'active' : ''; ?>">Products</a>
            <a href="orders.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : ''; ?>">Orders</a>
            <a href="payments.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'active' : ''; ?>">Payments</a>
        </div>
    </div>
    <div class="nav-right">
        <span class="welcome-text">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>