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

// Database Connection Logic (Assuming this is the standard path based on other files)
// In a real setup, you should include('../config/db_connect.php')
$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "canteen_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Utility function for session message display (mimicking the style used in other admin files)
function set_session_message($type, $text) {
    // Determine styles based on type (success, error, warning)
    $style = "";
    $icon = "ℹ️";
    switch ($type) {
        case 'success':
            $style = "background-color: #D1FAE5; color: #065F46; border-left-color: #10B981;"; // Green
            $icon = "✅";
            break;
        case 'error':
            $style = "background-color: #FEE2E2; color: #991B1B; border-left-color: #EF4444;"; // Red
            $icon = "❌";
            break;
        case 'warning':
            $style = "background-color: #FFFBEB; color: #92400E; border-left-color: #F97316;"; // Orange/Yellow
            $icon = "⚠️";
            break;
    }
    $_SESSION['message'] = "<div class='alert' style='{$style} display: flex; gap: 10px; align-items: center; padding: 10px 15px; border-radius: 8px; font-weight: 600; border-left-width: 5px; border-left-style: solid;'>{$icon} {$text}</div>";
}
function getSession($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

$cashier_id = $_SESSION['user_id'];
$cashier_username = $_SESSION['username'] ?? 'Cashier';

// --- ACTION HANDLER: PROCESS ORDER (AJAX endpoint logic) ---
// This part handles the incoming AJAX requests from the JS
if (isset($_POST['action']) && $_POST['action'] == 'process_order') {
    // Header setup to return JSON
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    // Validate inputs
    $total = isset($_POST['total']) ? floatval($_POST['total']) : 0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $items_json = isset($_POST['items']) ? $_POST['items'] : '[]';
    $items = json_decode($items_json, true);
    
    // Basic validation
    if ($total <= 0 || empty($payment_method) || empty($items)) {
        $response['message'] = 'Invalid order details provided.';
        echo json_encode($response);
        $conn->close();
        exit();
    }
    
    // Start transaction for atomicity
    $conn->begin_transaction();
    $order_id = null;
    
    try {
        // 1. Insert into 'orders' table
        $stmt_order = $conn->prepare("INSERT INTO orders (cashier_id, total, order_date) VALUES (?, ?, NOW())");
        $stmt_order->bind_param("id", $cashier_id, $total);
        if (!$stmt_order->execute()) {
            throw new Exception("Order creation failed: " . $stmt_order->error);
        }
        $order_id = $conn->insert_id;
        $stmt_order->close();
        
        // 2. Insert into 'order_items' and update 'products' stock
        foreach ($items as $item) {
            $product_id = intval($item['id']);
            $quantity = intval($item['qty']);
            $price_at_sale = floatval($item['price']);
            
            // Check current stock first
            $stmt_stock = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
            $stmt_stock->bind_param("i", $product_id);
            $stmt_stock->execute();
            $stock_result = $stmt_stock->get_result();
            if ($stock_result->num_rows == 0) {
                 throw new Exception("Product ID {$product_id} not found.");
            }
            $current_stock = $stock_result->fetch_assoc()['stock'];
            $stmt_stock->close();

            if ($current_stock < $quantity) {
                // Not enough stock, rollback transaction
                throw new Exception("Insufficient stock for product ID {$product_id}. Available: {$current_stock}, Requested: {$quantity}.");
            }
            
            // Insert item
            $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_sale) VALUES (?, ?, ?, ?)");
            $stmt_item->bind_param("iiid", $order_id, $product_id, $quantity, $price_at_sale);
            if (!$stmt_item->execute()) {
                throw new Exception("Order item insertion failed: " . $stmt_item->error);
            }
            $stmt_item->close();
            
            // Update stock
            $stmt_stock_update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
            $stmt_stock_update->bind_param("ii", $quantity, $product_id);
            if (!$stmt_stock_update->execute()) {
                throw new Exception("Stock update failed: " . $stmt_stock_update->error);
            }
            $stmt_stock_update->close();
        }

        // 3. Insert into 'payments' table
        $stmt_payment = $conn->prepare("INSERT INTO payments (order_id, amount_paid, payment_method, payment_date) VALUES (?, ?, ?, NOW())");
        $stmt_payment->bind_param("ids", $order_id, $total, $payment_method);
        if (!$stmt_payment->execute()) {
            throw new Exception("Payment insertion failed: " . $stmt_payment->error);
        }
        $stmt_payment->close();
        
        // Commit transaction
        $conn->commit();
        $response['success'] = true;
        $response['order_id'] = $order_id;
        
    } catch (Exception $e) {
        // Rollback on any error
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    $conn->close();
    exit();
}
// --- END ACTION HANDLER ---


// --- ACTION HANDLER: LOAD DATA (AJAX endpoint logic) ---
// This part handles loading categories and products via AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'load_categories') {
        $result = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        array_unshift($categories, ['category_id' => 0, 'category_name' => 'All Items']);
        echo json_encode(['success' => true, 'categories' => $categories]);
        
    } elseif ($_GET['action'] == 'load_products') {
        $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        
        $sql = "SELECT product_id, product_name, price, stock, category_id FROM products ";
        $params = [];
        $types = "";
        
        if ($categoryId > 0) {
            $sql .= "WHERE category_id = ?";
            $params[] = $categoryId;
            $types = "i";
        }
        
        $sql .= " ORDER BY product_name ASC";
        
        $stmt = $conn->prepare($sql);
        if ($categoryId > 0) {
             $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode(['success' => true, 'products' => $products]);
    }
    
    $conn->close();
    exit();
}
// --- END LOAD DATA HANDLER ---

// If not an AJAX request, continue with the HTML output
$message = getSession('message');
unset($_SESSION['message']); // Clear message after display

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier POS - Canteen System</title>
    <!-- Use a modern, easy-to-read font (Poppins for consistency with admin panel) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <style>
        /* CSS Variables: Consistent Canteen Colors */
        :root {
            --primary-color: #F97316; /* Warm Orange/Rust - Accent */
            --secondary-color: #1F2937; /* Dark Gray/Blue - Primary Text & Headings */
            --success-color: #10B981; /* Green - Add/Save actions */
            --danger-color: #EF4444; /* Red - Delete/Alerts */
            --warning-color: #FBBF24; /* Yellow - Low stock */
            --background-light: #FBFBFB; /* Very light background */
            --text-color: #374151; /* Medium Text */
            --color-border: #E5E7EB; /* Light Border */
            --color-card-bg: white; /* Card BG */
            --font-family: 'Poppins', sans-serif;
        }

        /* 1. Global / Reset */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body, html {
            background-color: var(--background-light);
            font-family: var(--font-family);
            color: var(--text-color);
            line-height: 1.6;
            height: 100%;
        }
        .container {
            padding: 20px;
        }
        
        /* 2. Main POS Layout (Desktop) */
        .pos-layout {
            display: grid;
            grid-template-columns: 2fr 1fr; /* 2/3 for menu, 1/3 for cart */
            gap: 20px;
            max-width: 1400px;
            margin: 20px auto;
            align-items: flex-start; /* Prevents stretching */
        }

        /* 3. Menu Panel (Left) */
        .menu-panel {
            background-color: var(--color-card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 20px;
            min-height: 80vh;
        }
        
        .menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--color-border);
        }
        
        /* Category Filters */
        #category-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            border-radius: 8px;
            padding: 10px;
            background-color: #F3F4F6;
        }

        .category-btn {
            background: #D1D5DB;
            color: var(--secondary-color);
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            flex-grow: 1; /* Allows wrapping and filling space */
            text-align: center;
            min-width: 100px;
            border: none;
        }
        .category-btn:hover {
            background: #AAB0B9;
        }
        .category-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 5px rgba(249, 115, 22, 0.5);
            transform: translateY(-1px);
        }

        /* Product Grid */
        #product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .product-card {
            background-color: white;
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 180px;
        }
        .product-card:hover {
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }

        .product-card h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        .product-card .price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        .product-card .stock {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-color);
        }
        .product-card.low-stock .stock {
            color: var(--warning-color);
        }
        .product-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #FEF2F2; /* Light red background */
        }
        .product-card.out-of-stock:hover {
            transform: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-color: var(--color-border);
        }


        /* 4. Cart Panel (Right) */
        .cart-panel {
            background-color: var(--color-card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            padding: 20px;
            position: sticky; /* Keep cart visible */
            top: 20px; /* Offset from top */
            max-height: 90vh; /* Control height */
        }

        .cart-header {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        #cart-items-container {
            flex-grow: 1;
            overflow-y: auto;
            max-height: 60vh; /* Adjust as needed */
            padding-right: 10px; /* Space for scrollbar */
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dotted #ccc;
        }
        .item-info {
            flex-grow: 1;
        }
        .item-name {
            font-weight: 600;
            color: var(--text-color);
            line-height: 1.2;
        }
        .item-price {
            font-size: 0.85rem;
            color: #6B7280;
        }
        .item-qty-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .qty-btn {
            background: #E5E7EB;
            color: var(--secondary-color);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
            transition: background 0.1s;
        }
        .qty-btn:hover {
            background: #D1D5DB;
        }
        .qty-display {
            font-weight: 700;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }
        .item-subtotal {
            width: 80px;
            text-align: right;
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 1rem;
        }
        
        /* 5. Cart Summary & Actions */
        .cart-summary {
            padding-top: 20px;
            border-top: 2px solid var(--color-border);
            margin-top: 15px;
        }
        .cart-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .cart-total-label {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--secondary-color);
        }
        #cart-total-display {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
        }

        /* General Button Styles */
        .btn {
            display: block;
            width: 100%;
            padding: 12px 15px;
            margin-top: 10px;
            border-radius: 8px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
            border: none;
        }
        .btn-checkout {
            background-color: var(--success-color);
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.4);
        }
        .btn-checkout:hover:not(:disabled) {
            background-color: #0c9c6a;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5);
        }
        .btn-checkout:disabled {
            background-color: #B2F5EA;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .btn-clear-cart {
            background-color: #FEE2E2;
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        .btn-clear-cart:hover {
            background-color: #FCA5A5;
            color: white;
        }
        
        /* 6. Header/Navbar (Simple, since we don't have full header.php/navbar.php content) */
        header {
            background-color: var(--secondary-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .header-cashier-info {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-cashier-info a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            padding: 5px 10px;
            border: 1px solid var(--primary-color);
            border-radius: 6px;
        }

        /* 7. Responsive Adjustments */
        @media (max-width: 900px) {
            .pos-layout {
                grid-template-columns: 1fr; /* Stack into a single column */
                margin: 10px;
            }
            
            .cart-panel {
                /* Fix the cart to the bottom of the viewport on mobile */
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                border-radius: 12px 12px 0 0;
                z-index: 1000;
                padding: 15px;
                max-height: 70vh; /* Prevent it from obscuring the whole screen */
                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.15);
            }
            
            #cart-items-container {
                max-height: 40vh; /* Smaller list view on mobile */
            }

            .menu-panel {
                margin-bottom: calc(200px); /* Add margin to prevent cart from overlapping menu items */
                min-height: auto;
            }

            #product-list {
                 grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>

<!-- Simple Header/Navigation for Cashier -->
<header>
    <div class="header-title">
        <span style="font-size: 1.2em; vertical-align: middle;">🛒</span> Cashier Point of Sale
    </div>
    <div class="header-cashier-info">
        <span>Logged in as: **<?php echo htmlspecialchars($cashier_username); ?>** (Cashier)</span>
        <a href="../logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <?php echo $message; // Display session message ?>

    <div class="pos-layout">
        
        <!-- ============================================== -->
        <!-- LEFT COLUMN: MENU & PRODUCT LISTING -->
        <!-- ============================================== -->
        <div class="menu-panel">
            <div class="menu-header">
                <h2>Menu</h2>
                <span class="section-description">Select items to add to the cart.</span>
            </div>

            <!-- Category Filters -->
            <div id="category-filter" aria-label="Product Categories">
                <!-- Categories loaded here by loadCategories() JS -->
            </div>

            <!-- Product Grid -->
            <div id="product-list">
                <!-- Products loaded here by loadProducts() JS -->
                <div style="text-align: center; padding: 50px;">
                    <p style="font-style: italic;">Loading products...</p>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- RIGHT COLUMN: CART & CHECKOUT -->
        <!-- ============================================== -->
        <div class="cart-panel">
            <h3 class="cart-header">Current Order</h3>
            
            <!-- Cart Items List -->
            <div id="cart-items-container">
                <div id="cart-items">
                    <!-- Cart items rendered here by updateCartDOM() JS -->
                    <p style="text-align: center; color: #6B7280; padding-top: 10px;">Cart is empty. Tap an item to begin.</p>
                </div>
            </div>

            <!-- Cart Summary and Actions -->
            <div class="cart-summary">
                <div class="cart-total-row">
                    <span class="cart-total-label">Total:</span>
                    <span id="cart-total-display">₱ 0.00</span>
                </div>

                <!-- Payment Method Selection (Added for completeness) -->
                <div style="margin-bottom: 15px;">
                    <label for="payment-method" style="font-weight: 600; display: block; margin-bottom: 5px;">Payment Method</label>
                    <select id="payment-method" class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--color-border);">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="GCash">GCash</option>
                    </select>
                </div>

                <!-- Checkout Button -->
                <button id="process-order-btn" class="btn btn-checkout" disabled>
                    Process Order (₱ 0.00)
                </button>
                
                <!-- Clear Cart Button -->
                 <button id="clear-cart-btn" class="btn btn-clear-cart" disabled>
                    Clear Cart
                </button>
            </div>
        </div>

    </div>
</div>

<script>
// --- GLOBAL STATE & UTILITIES ---
let cart = {}; // {productId: {id, name, price, qty, stock}}

// Save cart state to browser storage
function saveCart() {
    sessionStorage.setItem('canteenCart', JSON.stringify(cart));
}

// Load cart state from browser storage
function loadCart() {
    const savedCart = sessionStorage.getItem('canteenCart');
    if (savedCart) {
        try {
            cart = JSON.parse(savedCart);
        } catch (e) {
            console.error("Error loading cart from storage:", e);
            cart = {};
        }
    }
}

// Add/Increment item in cart
function addToCart(product) {
    if (product.stock <= 0) {
        console.warn(`${product.product_name} is out of stock.`);
        return; // Cannot add
    }

    if (cart[product.product_id]) {
        // Check if adding one more exceeds stock
        if (cart[product.product_id].qty >= product.stock) {
             console.warn(`Cannot add more. Max stock reached for ${product.product_name}.`);
             return;
        }
        cart[product.product_id].qty++;
    } else {
        cart[product.product_id] = {
            id: product.product_id,
            name: product.product_name,
            price: parseFloat(product.price),
            qty: 1,
            stock: product.stock
        };
    }
    saveCart();
    updateCartDOM();
}

// Remove item completely from cart
function removeFromCart(productId) {
    delete cart[productId];
    saveCart();
    updateCartDOM();
}

// Update quantity directly (e.g., from +/- buttons)
function updateCartItemQuantity(productId, change) {
    if (!cart[productId]) return;

    const newQty = cart[productId].qty + change;
    const maxQty = cart[productId].stock;

    if (newQty <= 0) {
        removeFromCart(productId);
    } else if (newQty > maxQty) {
        console.warn(`Cannot increase quantity above stock limit (${maxQty}).`);
    } else {
        cart[productId].qty = newQty;
        saveCart();
        updateCartDOM();
    }
}

// --- DOM MANIPULATION & RENDERING ---

// Updates the Cart DOM and Total
function updateCartDOM() {
    const $cartItems = $('#cart-items');
    const $processBtn = $('#process-order-btn');
    const $clearBtn = $('#clear-cart-btn');
    let totalAmount = 0;
    let html = '';

    const cartKeys = Object.keys(cart);
    if (cartKeys.length === 0) {
        html = '<p style="text-align: center; color: #6B7280; padding-top: 10px;">Cart is empty. Tap an item to begin.</p>';
        $processBtn.prop('disabled', true);
        $clearBtn.prop('disabled', true);
    } else {
        $processBtn.prop('disabled', false);
        $clearBtn.prop('disabled', false);
        
        cartKeys.forEach(productId => {
            const item = cart[productId];
            const subtotal = item.qty * item.price;
            totalAmount += subtotal;

            html += `
                <div class="cart-item" data-product-id="${item.id}">
                    <div class="item-info">
                        <div class="item-name">${item.name}</div>
                        <div class="item-price">@ ₱ ${item.price.toFixed(2)}</div>
                    </div>
                    <div class="item-qty-controls">
                        <button class="qty-btn decrement-btn" data-id="${item.id}">-</button>
                        <span class="qty-display">${item.qty}</span>
                        <button class="qty-btn increment-btn" data-id="${item.id}" ${item.qty >= item.stock ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="item-subtotal">₱ ${subtotal.toFixed(2)}</div>
                    <!-- Delete button replaced by using '-' button to reduce to zero -->
                </div>
            `;
        });
    }

    $cartItems.html(html);
    $('#cart-total-display').text(`₱ ${totalAmount.toFixed(2)}`);
    $processBtn.text(`Process Order (₱ ${totalAmount.toFixed(2)})`);
}

// Renders the list of categories
function loadCategories() {
    $.getJSON('pos.php?action=load_categories', function(response) {
        if (response.success) {
            let html = '';
            response.categories.forEach(cat => {
                html += `<button class="category-btn" data-category-id="${cat.category_id}">
                            ${cat.category_name}
                        </button>`;
            });
            $('#category-filter').html(html);
            // Manually activate 'All Items' (category_id 0) initially
            $('#category-filter .category-btn[data-category-id="0"]').addClass('active');
        } else {
            $('#category-filter').html('<p style="color: var(--danger-color);">Error loading categories.</p>');
        }
    }).fail(function() {
        $('#category-filter').html('<p style="color: var(--danger-color);">Network error loading categories.</p>');
    });
}

// Renders the list of products for a given category
function loadProducts(categoryId) {
    const $productList = $('#product-list');
    $productList.html('<div style="text-align: center; padding: 50px;"><p>Loading products...</p></div>');

    $.getJSON(`pos.php?action=load_products&category_id=${categoryId}`, function(response) {
        if (response.success) {
            let html = '';
            if (response.products.length === 0) {
                html = '<div style="text-align: center; grid-column: 1 / -1; padding: 50px;"><p style="font-style: italic;">No items found in this category.</p></div>';
            } else {
                response.products.forEach(product => {
                    const isOutOfStock = product.stock <= 0;
                    const isLowStock = product.stock > 0 && product.stock <= 5;
                    const cardClass = isOutOfStock ? 'out-of-stock' : (isLowStock ? 'low-stock' : '');
                    const stockText = isOutOfStock ? 'Out of Stock' : (isLowStock ? `Low Stock (${product.stock})` : `In Stock (${product.stock})`);

                    html += `
                        <div class="product-card ${cardClass}" data-product-id="${product.product_id}" data-stock="${product.stock}" data-price="${product.price}">
                            <div>
                                <h4>${product.product_name}</h4>
                                <div class="price">₱ ${parseFloat(product.price).toFixed(2)}</div>
                            </div>
                            <div class="stock">${stockText}</div>
                        </div>
                    `;
                });
            }
            $productList.html(html);
        } else {
            $productList.html(`<div style="text-align: center; grid-column: 1 / -1; padding: 50px;"><p style="color: var(--danger-color);">Error loading products: ${response.message || 'Unknown error'}</p></div>`);
        }
    }).fail(function() {
        $productList.html('<div style="text-align: center; grid-column: 1 / -1; padding: 50px;"><p style="color: var(--danger-color);">Network error loading products.</p></div>');
    });
}


// --- MAIN EVENT HANDLERS ---

// Process the order via AJAX
function processOrder() {
    const totalAmount = Object.values(cart).reduce((sum, item) => sum + (item.qty * item.price), 0);
    const paymentMethod = $('#payment-method').val();
    
    // Convert cart object to array for server
    const itemsArray = Object.values(cart).map(item => ({
        id: item.id,
        qty: item.qty,
        price: item.price
    }));

    if (itemsArray.length === 0 || totalAmount <= 0) {
        console.error("Cart is empty or total is zero. Cannot process order.");
        return;
    }

    const $btn = $('#process-order-btn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Processing...');

    $.ajax({
        url: 'pos.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'process_order',
            total: totalAmount,
            payment_method: paymentMethod,
            items: JSON.stringify(itemsArray)
        },
        success: function(response) {
            if (response.success) {
                // Success: Redirect to a page that can display the success message
                sessionStorage.removeItem('canteenCart'); // Clear local cart
                // Note: Redirecting to 'orders.php' (assuming it exists) with a URL message param
                window.location.href = `orders.php?message=Order%20ID%20%23${response.order_id}%20completed%20successfully!%20Total:%20%E2%82%B1%20${totalAmount.toFixed(2)}`;
                
            } else {
                // Failure: Log error and re-enable button.
                console.error('Order failed:', response.message);
                
                // Show a non-blocking message to the user
                set_session_message('error', 'Order failed: ' + (response.message || 'Unknown error. Check console for details.'));
                
                // Re-enable button and show original text
                $btn.prop('disabled', false).text(originalText);

                // Re-load products to show the current stock (since the transaction failed, stock shouldn't have changed but reloading is safer)
                loadProducts($('#category-filter .active').data('category-id')); 
            }
        },
        error: function(xhr) {
            // Server/Network error: Log critical error and re-enable button.
            console.error('AJAX Error:', xhr.responseText);
            
            // Show a non-blocking message to the user
            set_session_message('error', 'A critical server error occurred during the transaction. Please check the server connection and logs.');
            
            $btn.prop('disabled', false).text(originalText);
        }
    });
}


// --- INITIALIZATION ---
$(document).ready(function() {
    // 1. Load data from previous session
    loadCart();
    updateCartDOM();
    
    // 2. Load menu data
    loadCategories();
    loadProducts(0); // Load all products initially

    // 3. Category Filter Click Handler
    $('#category-filter').on('click', '.category-btn', function() {
        const categoryId = $(this).data('category-id');
        
        // Update active class
        $('#category-filter .category-btn').removeClass('active');
        $(this).addClass('active');

        // Load products for the selected category
        loadProducts(categoryId);
    });

    // 4. Product Card Click Handler (Add to Cart)
    $('#product-list').on('click', '.product-card', function() {
        if ($(this).hasClass('out-of-stock')) return; // Ignore out-of-stock items

        const productId = $(this).data('product-id');
        const productName = $(this).find('h4').text();
        const price = $(this).data('price');
        const stock = $(this).data('stock');
        
        addToCart({
            product_id: productId,
            product_name: productName,
            price: price,
            stock: stock
        });
    });

    // 5. Cart Quantity Handlers
    $('#cart-items').on('click', '.increment-btn', function() {
        const productId = $(this).data('id');
        updateCartItemQuantity(productId, 1);
    });

    $('#cart-items').on('click', '.decrement-btn', function() {
        const productId = $(this).data('id');
        updateCartItemQuantity(productId, -1);
    });

    // 6. Checkout Handler
    $('#process-order-btn').on('click', processOrder);

    // 7. Clear Cart Handler
    $('#clear-cart-btn').on('click', function() {
        if (confirm('Are you sure you want to clear the entire cart?')) {
            cart = {};
            saveCart();
            updateCartDOM();
        }
    });
});
</script>
</body>
</html>
