<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include('header.php');
include('navbar.php');
include('../config/db_connect.php');

if(!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])){
    // Redirect if order_id is missing or invalid
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['order_id']); // Ensure it's an integer

// Initialize variables
$order_summary = null;
$order_items = [];
$total_items_cost = 0; // For verification/display

// --- 1. Fetch Order Summary Details ---
$stmt_summary = $conn->prepare("
    SELECT
        o.order_id, o.total, o.order_date, u.username AS cashier_name,
        c.customer_id
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.user_id
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.order_id = ?
");
$stmt_summary->bind_param("i", $order_id);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();

if ($summary_result->num_rows > 0) {
    $order_summary = $summary_result->fetch_assoc();
} else {
    // Handle case where order is not found
    echo "<div class='container'><p class='alert error'>❌ Error: Order ID #{$order_id} not found.</p><a href='orders.php' class='btn btn-primary'>Back to Orders</a></div>";
    include('footer.php');
    exit();
}
$stmt_summary->close();

// --- 2. Fetch Order Items Details (FIXED SQL QUERY BELOW) ---
// This query was causing the 'Unknown column' error
$stmt_items = $conn->prepare("
    SELECT
        oi.item_id,
        oi.quantity,
        oi.price_at_sale,
        p.product_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
    ORDER BY p.product_name ASC
");
// Line 48 is approximately where the SELECT clause for this query starts.
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

if ($items_result->num_rows > 0) {
    while ($row = $items_result->fetch_assoc()) {
        $order_items[] = $row;
    }
}
$stmt_items->close();

// --- 3. Fetch Payment Information (if any) ---
$payment_info = null;
$stmt_payment = $conn->prepare("
    SELECT payment_method, amount_paid, payment_date
    FROM payments
    WHERE order_id = ?
");
$stmt_payment->bind_param("i", $order_id);
$stmt_payment->execute();
$payment_result = $stmt_payment->get_result();
if ($payment_result->num_rows > 0) {
    $payment_info = $payment_result->fetch_assoc();
}
$stmt_payment->close();
$conn->close();
?>

<div class="container">
    <h2><span style="font-size: 1.2em; vertical-align: middle;">🧾</span> Order Details #<?php echo $order_summary['order_id']; ?></h2>
    <p class="section-description">Detailed breakdown of the items and transaction for this order.</p>

    <div class="details-layout">
        <!-- Summary Card -->
        <div class="card card-summary">
            <h3>Order Summary</h3>
            <p><strong>Total:</strong> <span style="font-size: 1.5em; color: var(--success-color);">₱ <?php echo number_format($order_summary['total'], 2); ?></span></p>
            <p><strong>Date & Time:</strong> <?php echo date('M d, Y H:i:s', strtotime($order_summary['order_date'])); ?></p>
            <p><strong>Processed By:</strong> <?php echo htmlspecialchars($order_summary['cashier_name']); ?></p>
            <p><strong>Customer ID:</strong> <?php echo $order_summary['customer_id'] ?: 'N/A'; ?></p>
        </div>

        <!-- Payment Card -->
        <div class="card card-payment">
            <h3>Payment Status</h3>
            <?php if ($payment_info): ?>
                <p><strong>Method:</strong> <span class="badge badge-primary"><?php echo htmlspecialchars($payment_info['payment_method']); ?></span></p>
                <p><strong>Amount Paid:</strong> ₱ <?php echo number_format($payment_info['amount_paid'], 2); ?></p>
                <p><strong>Payment Date:</strong> <?php echo date('M d, Y H:i:s', strtotime($payment_info['payment_date'])); ?></p>
                <p style="color: var(--success-color); font-weight: 700;">✅ Paid in Full</p>
            <?php else: ?>
                <p style="color: var(--danger-color); font-weight: 700;">❌ No Payment Recorded</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Items Table -->
    <h3 style="margin-top: 30px;">Items Purchased</h3>
    <div class="table-responsive">
        <table class="data-table-canteen">
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="width: 15%;">Price @ Sale</th>
                    <th style="width: 10%; text-align: center;">Qty</th>
                    <th style="width: 15%; text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if (!empty($order_items)) {
                foreach($order_items as $item) {
                    $subtotal = $item['quantity'] * $item['price_at_sale'];
                    $total_items_cost += $subtotal;
            ?>
            <tr>
                <td data-label="Product"><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td data-label="Price @ Sale" style="text-align: right;">₱ <?php echo number_format($item['price_at_sale'], 2); ?></td>
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
                <td colspan="3" style="text-align: right; font-weight: 700; background-color: #F8FAFC; border-right: none;">Total Items Cost:</td>
                <td style="text-align: right; font-weight: 800; background-color: #F8FAFC; color: var(--secondary-color);">₱ <?php echo number_format($total_items_cost, 2); ?></td>
            </tr>
            <?php if ($order_summary['total'] != $total_items_cost): ?>
             <tr>
                <td colspan="3" style="text-align: right; font-weight: 700; background-color: #FFFBEB; border-right: none; color: var(--primary-color);">Discounts/Fees/Rounding:</td>
                <td style="text-align: right; font-weight: 700; background-color: #FFFBEB; color: var(--primary-color);">₱ <?php echo number_format($order_summary['total'] - $total_items_cost, 2); ?></td>
            </tr>
            <?php endif; ?>
             <tr>
                <td colspan="3" style="text-align: right; font-weight: 700; background-color: #E0F2F1; border-right: none;">Final Order Total:</td>
                <td style="text-align: right; font-weight: 800; background-color: #E0F2F1; color: var(--success-color); font-size: 1.1em;">₱ <?php echo number_format($order_summary['total'], 2); ?></td>
            </tr>
        </tfoot>
        </table>
    </div>

    <!-- Footer Actions -->
    <div class="flex-container footer-actions" style="margin-top: 30px; justify-content: flex-start; gap: 10px;">
        <a href="orders.php" class="btn btn-secondary btn-back">← Back to All Orders</a>
        <button onclick="window.print()" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2em;">🖨️</span> Print Receipt
        </button>
    </div>
</div>

<style>
/* Additional specific styles for this page */
.details-layout {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card-summary, .card-payment {
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    background-color: white;
    border-left: 5px solid var(--secondary-color);
}
.card-payment {
    border-left-color: var(--primary-color);
}
.card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.3rem;
    color: var(--secondary-color);
}
.card p {
    margin: 5px 0;
    line-height: 1.6;
}
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: 600;
    text-transform: uppercase;
}
.badge-primary {
    background-color: #FEEBC9; /* Light primary color */
    color: var(--primary-color);
}
.footer-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border);
}

/* Responsive Table Adjustments */
@media (max-width: 768px) {
    .data-table-canteen thead {
        display: none;
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
    header, nav, .footer-actions, .btn-back {
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
         grid-template-columns: 1fr; /* Stack summary and payment info for printing */
    }
    .data-table-canteen {
        font-size: 11pt; /* Smaller font for better fit on paper */
    }
    tfoot tr td {
        background-color: #f0f0f0 !important; /* Ensure background prints clearly */
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
    /* Simple receipt format */
    .card {
        border-left: none;
        padding: 10px 0;
        border-bottom: 1px dashed #ccc;
        margin-bottom: 10px;
    }
}
</style>

