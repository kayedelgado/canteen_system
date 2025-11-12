<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include('header.php');
include('navbar.php');
include('../config/db_connect.php');

// --- CRITICAL UPDATE: Fetch only payments for orders with 'completed' status ---
$result = $conn->query("
    SELECT 
        p.payment_id, p.amount_paid, p.payment_method, p.payment_date,
        o.order_id, u.username AS cashier_name,
        -- Apply the same simulated status logic used in orders.php
        CASE
            WHEN o.order_id % 5 = 0 THEN 'pending'
            WHEN o.order_id % 4 = 0 THEN 'processing'
            ELSE 'completed'
        END AS status
    FROM payments p
    LEFT JOIN orders o ON p.order_id = o.order_id
    LEFT JOIN users u ON o.cashier_id = u.user_id
    -- Filter to only include payments linked to 'completed' orders
    WHERE 
        CASE
            WHEN o.order_id % 5 = 0 THEN 'pending'
            WHEN o.order_id % 4 = 0 THEN 'processing'
            ELSE 'completed'
        END = 'completed'
    ORDER BY p.payment_date DESC
");

// Function to generate a styled badge for payment method
function getPaymentMethodBadge($method) {
    $method = strtolower($method);
    $class = 'badge-default';
    $icon = '💳'; // Default icon

    switch ($method) {
        case 'cash':
            $class = 'badge-success';
            $icon = '💵';
            break;
        case 'card':
        case 'credit card':
            $class = 'badge-primary';
            $icon = '💳';
            break;
        case 'gcash':
        case 'maya':
            $class = 'badge-warning';
            $icon = '📱'; // Using mobile icon for e-wallets
            break;
    }
    // Note: We use the actual method name as the badge text, e.g., "Cash"
    return "<span class=\"badge {$class}\"><span class=\"badge-icon\">{$icon}</span> " . ucwords($method) . "</span>";
}

// Function to format the date and time
function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}
?>

<div class="container">
    <h2><span style="font-size: 1.2em; vertical-align: middle;">💰</span> Completed Payments History</h2>
    <p class="section-description">This table displays all recorded payments for **completed** orders in the system.</p>

    <!-- Table Container -->
    <div class="table-responsive">
        <table class="data-table-canteen">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order ID</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Cashier</th>
                    <th>Status</th> <!-- New Status Column -->
                    <th>Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()){ 
                ?>
                <tr>
                    <td data-label="Payment ID">#<?php echo $row['payment_id']; ?></td>
                    <td data-label="Order ID">
                        <a href="order_details.php?order_id=<?php echo $row['order_id']; ?>" class="order-link">
                            #<?php echo $row['order_id']; ?>
                        </a>
                    </td>
                    <td data-label="Amount">₱ <?php echo number_format($row['amount_paid'], 2); ?></td>
                    <td data-label="Method"><?php echo getPaymentMethodBadge($row['payment_method']); ?></td>
                    <td data-label="Cashier"><?php echo htmlspecialchars($row['cashier_name'] ?? 'N/A'); ?></td>
                    <!-- Status column will always show completed due to the SQL filter -->
                    <td data-label="Status">
                         <span class="badge badge-success" style="font-size: 12px; font-weight: 700;">✅ Completed</span>
                    </td>
                    <td data-label="Date/Time"><?php echo formatDateTime($row['payment_date']); ?></td>
                </tr>
                <?php 
                    } 
                } else { 
                ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No completed payment records found.</td>
                </tr>
                <?php 
                } 
                ?>
            </tbody>
        </table>
    </div>
</div>


<style>
/* Add styles for a clean, modern table look consistent with other admin files */
.table-responsive {
    overflow-x: auto;
}

.data-table-canteen {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background-color: white;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    border-radius: 8px;
    overflow: hidden;
}

.data-table-canteen th, .data-table-canteen td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0; /* Light divider */
}

.data-table-canteen th {
    background-color: var(--secondary-color);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
}

.data-table-canteen tbody tr:hover {
    background-color: #f9f9f9;
}

.order-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
}
.order-link:hover {
    text-decoration: underline;
}

/* Badge Styling for Payment Methods (Reused from other files or defined here) */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}
.badge-icon {
    font-size: 1em;
    margin-right: 5px;
    line-height: 1;
}

.badge-primary { background-color: #BFDBFE; color: #1D4ED8; } /* Card/Credit */
.badge-success { background-color: #D1FAE5; color: #065F46; } /* Cash/Completed */
.badge-warning { background-color: #FEF3C7; color: #92400E; } /* E-Wallets */
.badge-default { background-color: #E5E7EB; color: #4B5563; } /* Default */

/* Responsive adjustments */
@media (max-width: 768px) {
    .container {
        margin: 10px;
        padding: 15px;
    }

    /* Responsive Table - Stack columns */
    .data-table-canteen thead {
        display: none;
    }
    .data-table-canteen, .data-table-canteen tbody, .data-table-canteen tr, .data-table-canteen td {
        display: block;
        width: 100%;
    }
    .data-table-canteen tr {
        margin-bottom: 15px;
        border: 1px solid var(--color-border);
        border-radius: 8px;
        padding: 10px 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .data-table-canteen td {
        text-align: right;
        padding-left: 50%;
        position: relative;
        border-bottom: 1px dotted #ccc;
    }
    .data-table-canteen td:last-child {
         border-bottom: none;
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
}
</style>
