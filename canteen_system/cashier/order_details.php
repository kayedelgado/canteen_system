<?php
// Start session
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// CRITICAL: Session and Role Check
// NOTE: Assuming this file is accessible by cashiers to view their own orders.
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

// Utility functions
function getSession($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

// Ensure an order_id is provided and the order belongs to the current cashier
if(!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])){
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$cashier_id = $_SESSION['user_id'];
$order_summary = null;
$order_items = [];
$payment_info = null;

// --- 1. Fetch Order Summary Details and enforce cashier ownership ---
$stmt_summary = $conn->prepare("
    SELECT 
        o.order_id, o.total, o.order_date, 
        u.username AS cashier_name, 
        c.customer_id, 
        p.amount_paid, p.payment_method
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.user_id
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN payments p ON o.order_id = p.order_id
    WHERE o.order_id = ? AND o.cashier_id = ?
");
$stmt_summary->bind_param("ii", $order_id, $cashier_id);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();

if ($summary_result->num_rows > 0) {
    $order_summary = $summary_result->fetch_assoc();
} else {
    // Handle case where order is not found or doesn't belong to this cashier
    echo "<div class='container'><p class='alert error'>Order #{$order_id} not found or you do not have permission to view it.</p><a href='orders.php' class='btn btn-back'>Back to Orders</a></div>";
    $conn->close();
    exit();
}

// --- 2. Fetch Order Items (FIXED: Removed 'oi.price') ---
$stmt_items = $conn->prepare("
    SELECT 
        p.product_name, oi.quantity, oi.price_at_sale 
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

if ($items_result->num_rows > 0) {
    while($row = $items_result->fetch_assoc()) {
        $order_items[] = $row;
    }
}
$stmt_items->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order_id; ?> Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Variables: Canteen Colors */
        :root {
            --primary-color: #F97316; /* Warm Orange/Rust */
            --secondary-color: #1F2937; /* Dark Gray/Blue */
            --success-color: #10B981; /* Green */
            --danger-color: #EF4444; /* Red */
            --background-light: #FBFBFB; 
            --color-border: #E5E7EB;
            --color-card-bg: #FFFFFF;
            --font-family: 'Poppins', sans-serif;
            --color-light-blue: #E0F2FE; /* For "You" tag */
            --color-current: #0EA5E9; /* Current User Indicator */
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: var(--font-family);
            background-color: var(--background-light);
            margin: 0;
            color: var(--secondary-color);
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px;
            background: var(--color-card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
            border-bottom: 2px solid var(--color-border);
            padding-bottom: 10px;
            margin-top: 0;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px dashed var(--color-border);
        }
        
        /* General Details Layout */
        .details-layout {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-item {
            padding: 15px;
            background-color: #F8FAFC;
            border: 1px solid var(--color-border);
            border-radius: 8px;
        }
        .detail-item strong {
            display: block;
            font-size: 0.9rem;
            color: #6B7280;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .detail-item span {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--secondary-color);
        }
        .detail-item .total-amount {
            color: var(--success-color);
            font-size: 1.5rem;
        }

        /* Table Styling */
        .data-table-canteen {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .data-table-canteen th, .data-table-canteen td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        .data-table-canteen th {
            background-color: var(--secondary-color);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .data-table-canteen tbody tr:last-child td {
            border-bottom: none;
        }
        .data-table-canteen tbody tr:hover {
            background-color: #F9FAFB;
        }
        
        /* Footer/Total Row */
        .data-table-canteen tfoot td {
            background-color: #E5E7EB;
            border-top: 2px solid var(--secondary-color);
        }

        /* Buttons & Actions */
        .footer-actions {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 30px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.2s, box-shadow 0.2s;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back {
            background-color: #6B7280;
            color: white;
        }
        .btn-back:hover {
            background-color: #4B5563;
        }
        .btn-print {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-print:hover {
            background-color: #E64E00;
        }

        /* Utility */
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            h2 {
                font-size: 1.5rem;
            }
            .section-title {
                font-size: 1.2rem;
            }
            
            /* Table responsiveness (Stacking on small screens) */
            .data-table-canteen thead {
                display: none; /* Hide header row on mobile */
            }
            .data-table-canteen, .data-table-canteen tbody, .data-table-canteen tr, .data-table-canteen td {
                display: block;
                width: 100%;
            }
            .data-table-canteen tr {
                margin-bottom: 10px;
                border: 1px solid var(--color-border);
                border-radius: 6px;
                padding: 5px;
            }
            .data-table-canteen td {
                text-align: right;
                padding-left: 50%;
                position: relative;
                border-bottom: 1px dotted #ccc;
            }
            .data-table-canteen td:before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                width: 45%;
                font-weight: 600;
                color: var(--secondary-color);
                text-align: left;
            }
            .footer-actions {
                flex-direction: column-reverse;
                gap: 15px;
            }
            .btn {
                width: 100%;
            }
        }
        
        /* Print Styles (Hide navigation elements when printing) */
        @media print {
            body {
                background: white;
            }
            .btn-back, .btn-print {
                display: none !important;
            }
            .container {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .details-layout {
                 grid-template-columns: 1fr 1fr; /* Adjust for better paper utilization */
            }
        }
    </style>
</head>
<body>

<div class="container">

    <h2 style="color: var(--primary-color);">
        <span style="font-size: 1.2em; vertical-align: middle;">📄</span> Receipt/Order Details #<?php echo htmlspecialchars($order_summary['order_id']); ?>
    </h2>
    <p style="color: #6B7280; margin-bottom: 30px;">A comprehensive view of the items, totals, and payment for this transaction.</p>

    <!-- Order Summary -->
    <div class="section-title">Order Summary</div>
    <div class="details-layout">
        <div class="detail-item">
            <strong>Order Date</strong>
            <span><?php echo date('M d, Y H:i A', strtotime($order_summary['order_date'])); ?></span>
        </div>
        <div class="detail-item">
            <strong>Cashier</strong>
            <span><?php echo htmlspecialchars($order_summary['cashier_name']); ?></span>
        </div>
        <div class="detail-item">
            <strong>Customer ID</strong>
            <span><?php echo $order_summary['customer_id'] ?: 'N/A'; ?></span>
        </div>
        <div class="detail-item">
            <strong>Payment Method</strong>
            <span><?php echo htmlspecialchars($order_summary['payment_method']); ?></span>
        </div>
        <div class="detail-item" style="grid-column: span 1 / span 1;">
            <strong>Amount Paid</strong>
            <span class="total-amount">₱ <?php echo number_format($order_summary['amount_paid'], 2); ?></span>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="section-title">Items Purchased</div>
    <table class="data-table-canteen">
        <thead>
            <tr>
                <th>Product Name</th>
                <th style="width: 15%; text-align: right;">Unit Price</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 20%; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_items_cost = 0;
            if (!empty($order_items)) {
                foreach($order_items as $item) { 
                    $subtotal = $item['quantity'] * $item['price_at_sale'];
                    $total_items_cost += $subtotal;
            ?>
            <tr>
                <td data-label="Product Name"><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td data-label="Unit Price" style="text-align: right;">₱ <?php echo number_format($item['price_at_sale'], 2); ?></td>
                <td data-label="Quantity" style="text-align: center;"><?php echo $item['quantity']; ?></td>
                <td data-label="Subtotal" style="text-align: right; font-weight: 700;">₱ <?php echo number_format($subtotal, 2); ?></td>
            </tr>
            <?php 
                } 
            } else { 
            ?>
            <tr>
                <td colspan="4" style="text-align: center;">No items found for this order.</td>
            </tr>
            <?php 
            } 
            ?>
        </tbody>
        <tfoot>
             <tr>
                <td colspan="3" style="text-align: right; font-weight: 700; background-color: #F8FAFC;">Order Total:</td>
                <td style="text-align: right; font-weight: 800; background-color: #E5F0E5; color: var(--success-color);">₱ <?php echo number_format($order_summary['total'], 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer-actions">
        <a href="orders.php" class="btn btn-back">
            <span style="font-size: 1.2em;">&larr;</span> Back to Orders
        </a>
        <button onclick="window.print();" class="btn btn-print">
            <span style="font-size: 1.2em;">🖨️</span> Print Receipt
        </button>
    </div>
</div>

</body>
</html>
