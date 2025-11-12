<?php
// Start session
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// CRITICAL: Session and Role Check
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier'){
    header("Location: ../login.php"); 
    exit();
}

// Database Connection Logic
$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "canteen_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Utility function (Guarded against redeclaration error)
if (!function_exists('getSession')) {
    function getSession($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
}

$cashier_id = $_SESSION['user_id'];

// Fetch all payment records linked to orders handled by the current cashier
$stmt = $conn->prepare("
    SELECT 
        p.payment_id, 
        p.order_id, 
        p.amount_paid, 
        p.payment_method, 
        p.payment_date,
        u.username AS cashier_name
    FROM payments p
    JOIN orders o ON p.order_id = o.order_id
    JOIN users u ON o.cashier_id = u.user_id
    WHERE o.cashier_id = ? 
    ORDER BY p.payment_date DESC
");

$stmt->bind_param("i", $cashier_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments - Canteen POS</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Variables: Cashier Theme (Blue) */
        :root {
            --primary-color: #3B82F6; /* Blue - Main Action/Accent */
            --secondary-color: #1F2937; /* Dark Gray/Blue - Primary Text & Headings */
            --success-color: #10B981; /* Green - Sales */
            --danger-color: #EF4444; /* Red - Delete/Alerts */
            --background-light: #F8F8F8; 
            --card-bg: #FFFFFF;
            --color-border: #E5E7EB;
            --font-family: 'Poppins', sans-serif;
            --color-info: #3B82F6;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--background-light);
            color: var(--secondary-color);
            margin: 0;
            padding-top: 65px; 
        }

        /* Navbar Styles (Inlined) */
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
        .nav-links a { color: white; text-decoration: none; padding: 5px 10px; border-radius: 6px; font-weight: 500; transition: background-color 0.2s; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(255, 255, 255, 0.15); color: var(--primary-color); font-weight: 700; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-greeting { font-size: 1rem; font-weight: 400; color: #E5E7EB; }
        .user-greeting strong { font-weight: 600; color: white; }
        .logout-btn { padding: 8px 15px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 600; text-decoration: none; transition: background-color 0.2s; }
        .logout-btn:hover { background-color: #2563EB; }
        
        /* Main Content Styles */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        }
        h2 { 
            font-size: 1.75rem; font-weight: 700; color: var(--secondary-color); margin-bottom: 25px; 
            border-bottom: 2px solid var(--color-border); padding-bottom: 10px; 
        }

        /* Table Styles (data-table-canteen) */
        .data-table-canteen {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table-canteen th, .data-table-canteen td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        .data-table-canteen th {
            background-color: #E5E7EB;
            color: var(--secondary-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .data-table-canteen tr:hover {
            background-color: #F8F8F8;
        }
        .view-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .view-link:hover {
            text-decoration: underline;
        }
        .total-amount {
            font-weight: 700;
            color: var(--success-color);
        }
        .method-cash {
             color: var(--success-color);
             font-weight: 600;
        }
        .method-card {
             color: var(--color-info);
             font-weight: 600;
        }
        .method-gcash {
             color: #1A90FF; /* Specific GCash color */
             font-weight: 600;
        }

        /* Responsive Table */
        @media (max-width: 768px) {
            .data-table-canteen thead { display: none; }
            .data-table-canteen, .data-table-canteen tbody, .data-table-canteen tr, .data-table-canteen td { display: block; width: 100%; }
            .data-table-canteen tr { margin-bottom: 10px; border: 1px solid var(--color-border); border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 10px; background: var(--card-bg); }
            .data-table-canteen td { text-align: right; padding-left: 50%; position: relative; border-bottom: 1px dotted #ccc; padding-top: 8px; padding-bottom: 8px; }
            .data-table-canteen td:before { content: attr(data-label); position: absolute; left: 15px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: 600; color: var(--secondary-color); text-align: left; }
            .data-table-canteen td:last-child { border-bottom: none; }
        }
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
            <a href="dashboard.php" class="">Dashboard</a>
            <a href="orders.php" class="">My Orders</a>
            <a href="payments.php" class="active">My Payments</a>
        </div>
    </div>
    <div class="nav-right">
        <span class="user-greeting">Hello, <strong><?php echo htmlspecialchars(getSession('username', 'Cashier')); ?></strong></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
<div class="container">
    <h2><span style="font-size: 1.2em; vertical-align: middle;">💳</span> My Payment Records</h2>
    <p class="section-description">A record of all payments processed for orders you handled.</p>

    <table class="data-table-canteen">
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Order ID</th>
                <th>Payment Date/Time</th>
                <th>Method</th>
                <th>Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()){ 
                    $method = strtolower($row['payment_method']);
                    if ($method == 'cash') {
                         $method_class = 'method-cash';
                    } elseif ($method == 'card') {
                         $method_class = 'method-card';
                    } else {
                         $method_class = 'method-gcash';
                    }
            ?>
            <tr>
                <td data-label="Payment ID">#<?php echo $row['payment_id']; ?></td>
                <td data-label="Order ID">
                    <a href="order_details.php?order_id=<?php echo $row['order_id']; ?>" class="view-link">
                        #<?php echo $row['order_id']; ?>
                    </a>
                </td>
                <td data-label="Payment Date/Time"><?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?></td>
                <td data-label="Method" class="<?php echo $method_class; ?>"><?php echo htmlspecialchars(ucfirst($row['payment_method'])); ?></td>
                <td data-label="Amount Paid" class="total-amount">₱ <?php echo number_format($row['amount_paid'], 2); ?></td>
            </tr>
            <?php 
                } 
            } else { 
            ?>
            <tr>
                <td colspan="5" style="text-align: center;">No payment records found.</td>
            </tr>
            <?php 
            } 
            ?>
        </tbody>
    </table>

</div>
<?php $stmt->close(); $conn->close(); ?>
</body>
</html>



