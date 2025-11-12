<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include('header.php');
include('navbar.php');
include('../config/db_connect.php');

// Fetch all orders with cashier and customer info
// NOTE: I've added a dummy 'status' field (defaulting to 'completed') 
// and calculated 'duration' to make the UI demonstration more useful.
$result = $conn->query("
    SELECT 
        o.order_id, 
        o.total, 
        o.order_date, 
        u.username AS cashier_name, 
        c.customer_id,
        CASE
            WHEN o.order_id % 5 = 0 THEN 'pending'
            WHEN o.order_id % 4 = 0 THEN 'processing'
            ELSE 'completed'
        END AS status,
        TIMESTAMPDIFF(MINUTE, o.order_date, NOW()) AS duration_minutes
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.user_id
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    ORDER BY o.order_date DESC
");

// Function to generate a styled badge for order status
// FIX: Wrapped in if (!function_exists) to prevent redeclaration fatal error
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status, $duration_minutes = null) {
        $status = strtolower($status);
        $class = 'badge-default';
        $icon = '⏱️'; // Default icon

        switch ($status) {
            case 'completed':
                $class = 'badge-success';
                $icon = '✅';
                break;
            case 'processing':
                $class = 'badge-primary';
                $icon = '🧑‍🍳';
                break;
            case 'pending':
                $class = 'badge-warning';
                $icon = '⏳';
                break;
            case 'cancelled':
                $class = 'badge-danger';
                $icon = '❌';
                break;
        }

        // Add duration info only for non-completed statuses
        $duration_text = '';
        if ($status != 'completed' && $duration_minutes !== null) {
            // Note: If duration_minutes is 0 or very small, this shows 0 min, which is correct.
            $duration_text = " (".$duration_minutes." min)";
        }

        return "<span class='status-badge {$class}'>{$icon} " . ucfirst($status) . $duration_text . "</span>";
    }
}
?>

<div class="container">
    <h2><span style="font-size: 1.2em; vertical-align: middle;">🧾</span> All Orders</h2>
    <p class="section-description">A complete list of all transactions processed in the canteen. Status is for demonstration purposes.</p>

    <?php 
    // Basic CSS for badges and table styling (added directly here for completeness)
    echo '
    <style>
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .badge-success {
            background-color: #D1FAE5; /* Light green */
            color: #d68aa7ff; /* Dark green text */
            border: 1px solid #d2e547ff;
        }
        .badge-primary {
            background-color: #DBEAFE; /* Light blue */
            color: #cc699cff; /* Dark blue text */
            border: 1px solid #d4e066ff;
        }
        .badge-warning {
            background-color: #FFFBEB; /* Light yellow */
            color: #e4509fff; /* Dark brown text */
            border: 1px solid #d9e62aff;
        }
        .badge-danger {
            background-color: #FEE2E2; /* Light red */
            color: #da34b3ff; /* Dark red text */
            border: 1px solid #5bdcb1ff;
        }
        .action-btn.details-btn {
            background-color: var(--secondary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        .action-btn.details-btn:hover {
            background-color: #374151; /* Darker secondary */
        }
    </style>
    ';
    ?>

    <div class="table-responsive">
        <table class="data-table-canteen">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date & Time</th>
                    <th>Total</th>
                    <th>Cashier</th>
                    <th>Customer ID</th>
                    <th>Status</th>
                    <th style="width: 100px; text-align: center;">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()){ 
                        // Format date to be more readable
                        $formatted_date = date('M d, Y h:i A', strtotime($row['order_date']));
                ?>
                <tr>
                    <td data-label="Order ID">#<?php echo $row['order_id']; ?></td>
                    <td data-label="Date & Time"><?php echo $formatted_date; ?></td>
                    <td data-label="Total" style="font-weight: 700;">₱ <?php echo number_format($row['total'], 2); ?></td>
                    <td data-label="Cashier"><?php echo htmlspecialchars($row['cashier_name'] ?: 'N/A'); ?></td>
                    <td data-label="Customer ID"><?php echo htmlspecialchars($row['customer_id'] ?: 'N/A'); ?></td>
                    <td data-label="Status">
                        <?php echo getStatusBadge($row['status'], $row['duration_minutes']); ?>
                    </td>
                    <td data-label="Action" style="text-align: center;">
                        <a href="order_details.php?order_id=<?php echo $row['order_id']; ?>" 
                           class="action-btn details-btn" title="View Order Details">
                            View
                        </a>
                    </td>
                </tr>
                <?php 
                    } 
                } else { 
                ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No orders found.</td>
                </tr>
                <?php 
                } 
                ?>
            </tbody>
        </table>
    </div>
</div>


<!-- Responsive Table Styling (Stacking on small screens) -->
<style>
    /* Responsive adjustments from your existing code structure */
    @media (max-width: 768px) {
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 10px;
            background: var(--background-light); /* Using the light background variable */
        }
        .data-table-canteen td {
            text-align: right;
            padding-left: 50%;
            position: relative;
            border-bottom: 1px dotted #ccc;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .data-table-canteen td:before {
            content: attr(data-label);
            position: absolute;
            left: 15px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: 600;
            color: var(--secondary-color);
            text-align: left;
        }
        .data-table-canteen td:last-child {
            border-bottom: none;
            padding-right: 15px; /* Adjust padding for the last cell on mobile */
        }
        .action-btn.details-btn {
            width: 100%;
            margin-top: 5px;
            text-align: center;
        }
    }
</style>
