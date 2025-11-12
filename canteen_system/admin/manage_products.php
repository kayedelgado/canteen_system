<?php
// CRITICAL: All code that sends headers (like session_start(), header()) must be at the very top.
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include('../config/db_connect.php');

$message = ""; // Initialize message variable
$edit_mode = false; // Flag for form display
$product_data = []; // Data for the product being edited

// --- Display Session Message (for redirects like Delete/Successful Edit) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}

// Fetch categories for dropdown (must be done before any POST/GET logic that uses it)
$cat_result = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");

// --- 1. HANDLE ADD PRODUCT ---
if(isset($_POST['add_product'])){
    // Basic Input Sanitization and validation
    $product_name = trim($_POST['product_name']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']); 
    $stock = intval($_POST['stock']);

    if (empty($product_name) || $category_id <= 0 || $price <= 0 || $stock < 0) {
        $message = "<div class='alert error'><span class='alert-icon'>❌</span> All fields are required and must be valid (Name, Category, Price > 0, Stock &ge; 0).</div>";
    } else {
        // Use prepared statements for security
        $stmt = $conn->prepare("INSERT INTO products (product_name, category_id, price, stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sidi", $product_name, $category_id, $price, $stock);
        if ($stmt->execute()) {
            // Set session message and redirect to clear POST data
            $_SESSION['message'] = "<div class='alert success'><span class='alert-icon'>🎉</span> Product '{$product_name}' added successfully!</div>";
            header("Location: manage_products.php");
            exit();
        } else {
            $message = "<div class='alert error'><span class='alert-icon'>❌</span> Error adding product: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}

// --- 2. HANDLE UPDATE PRODUCT ---
if(isset($_POST['edit_product'])){
    $product_id = intval($_POST['product_id']);
    $product_name = trim($_POST['product_name']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    if ($product_id <= 0 || empty($product_name) || $category_id <= 0 || $price <= 0 || $stock < 0) {
        $message = "<div class='alert error'><span class='alert-icon'>❌</span> Invalid product data or missing fields for update.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE products SET product_name = ?, category_id = ?, price = ?, stock = ? WHERE product_id = ?");
        $stmt->bind_param("sidii", $product_name, $category_id, $price, $stock, $product_id);
        
        if ($stmt->execute()) {
            // Use session message and redirect to clear POST/GET state
            $_SESSION['message'] = "<div class='alert success'><span class='alert-icon'>✨</span> Product '{$product_name}' updated successfully!</div>";
            header("Location: manage_products.php");
            exit();
        } else {
            $message = "<div class='alert error'><span class='alert-icon'>❌</span> Error updating product: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}

// --- 3. HANDLE DELETE PRODUCT ---
if(isset($_GET['delete'])){
    $product_id = intval($_GET['delete']);
    
    if ($product_id > 0) {
        // Start transaction for data integrity
        $conn->begin_transaction();
        $success = true;

        // 1. Delete associated order items (due to foreign key constraint)
        $stmt1 = $conn->prepare("DELETE FROM order_items WHERE product_id = ?");
        $stmt1->bind_param("i", $product_id);
        if (!$stmt1->execute()) { $success = false; }
        $stmt1->close();
        
        // 2. Delete the product itself
        if ($success) {
            $stmt2 = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt2->bind_param("i", $product_id);
            if (!$stmt2->execute()) { $success = false; }
            $stmt2->close();
        }

        if ($success) {
            $conn->commit();
            $_SESSION['message'] = "<div class='alert success'><span class='alert-icon'>🗑️</span> Product (ID: {$product_id}) and its historical order items deleted successfully.</div>";
        } else {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert error'><span class='alert-icon'>❌</span> Error deleting product or its related data: " . htmlspecialchars($conn->error) . "</div>";
        }
        
        // Redirect to clear the GET parameter and display session message
        header("Location: manage_products.php");
        exit();
    }
}

// --- 4. FETCH PRODUCT FOR EDITING (GET REQUEST) ---
if(isset($_GET['edit'])){
    $product_id = intval($_GET['edit']);
    
    if ($product_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result_edit = $stmt->get_result();
        
        if ($result_edit->num_rows == 1) {
            $product_data = $result_edit->fetch_assoc();
            $edit_mode = true;
        } else {
            $message = "<div class='alert error'><span class='alert-icon'>⚠️</span> Product not found.</div>";
        }
        $stmt->close();
    }
}

// Fetch all products with their category names for the table display
$result = $conn->query("
    SELECT p.product_id, p.product_name, p.price, p.stock, p.category_id, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id DESC
");

// Pre-fill form values for Edit/Error retention
$form_product_name = $product_data['product_name'] ?? '';
$form_category_id = $product_data['category_id'] ?? 0;
$form_price = $product_data['price'] ?? ''; 
$form_stock = $product_data['stock'] ?? '';

// --- Start HTML Output by including header and navbar ---
include('header.php');
include('navbar.php');
?>

<div class="container">
    <h2><span style="font-size: 1.2em; vertical-align: middle;">🍔</span> Product Management</h2>
    <p class="section-description">Manage your menu items, set prices, and update stock levels.</p>

    <?php 
    // Display the success/error message
    if (!empty($message)) {
        echo $message;
    }
    ?>
    
    <!-- Product ADD/EDIT Form -->
    <div class="form-container card">
        <h3 class="form-title">
            <?php echo $edit_mode ? 
                '<span class="icon">✏️</span> Edit Product (ID: ' . htmlspecialchars($product_data['product_id']) . ')' : 
                '<span class="icon">➕</span> Add New Product'; 
            ?>
        </h3>
        
        <form method="POST" action="manage_products.php" class="responsive-form">
            <?php if ($edit_mode): ?>
                <!-- Hidden fields required for edit mode -->
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_data['product_id']); ?>">
                <input type="hidden" name="edit_product" value="1"> 
            <?php else: ?>
                <!-- Hidden field required for add mode -->
                <input type="hidden" name="add_product" value="1">
            <?php endif; ?>

            <div class="form-group">
                <label for="product_name">Product Name:</label>
                <input type="text" id="product_name" name="product_name" 
                       value="<?php echo htmlspecialchars($form_product_name); ?>" required>
            </div>

            <div class="form-group">
                <label for="category_id">Category:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php 
                    if ($cat_result && $cat_result->num_rows > 0) {
                        $cat_result->data_seek(0);
                        while($cat = $cat_result->fetch_assoc()){
                            // Determine selected status
                            $selected = ($cat['category_id'] == $form_category_id) ? 'selected' : '';
                            echo "<option value=\"{$cat['category_id']}\" {$selected}>" . htmlspecialchars($cat['category_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Price (₱):</label>
                <input type="number" id="price" name="price" step="0.01" min="0.01" 
                       value="<?php echo htmlspecialchars($form_price); ?>" required>
            </div>

            <div class="form-group">
                <label for="stock">Stock Quantity:</label>
                <input type="number" id="stock" name="stock" min="0" 
                       value="<?php echo htmlspecialchars($form_stock); ?>" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? 'Update Product' : 'Add Product'; ?>
                </button>
                
                <?php if ($edit_mode): ?>
                    <!-- Button to switch back to Add mode -->
                    <a href="manage_products.php" class="btn btn-secondary cancel-btn">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Product Listing Table -->
    <h3 class="section-title"><span class="icon">📊</span> Current Menu Items</h3>

    <div class="table-responsive">
        <table class="data-table-canteen">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th style="width: 150px; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()){ 
                ?>
                <tr>
                    <td data-label="ID"><?php echo $row['product_id']; ?></td>
                    <td data-label="Product Name"><?php echo htmlspecialchars($row['product_name']); ?></td>
                    <td data-label="Category"><?php echo htmlspecialchars($row['category_name'] ?: 'N/A'); ?></td>
                    <td data-label="Price">₱ <?php echo number_format($row['price'], 2); ?></td>
                    <td data-label="Stock">
                        <?php echo $row['stock']; ?>
                        <?php 
                            // Highlight low stock items using the danger color
                            if ($row['stock'] <= 5) { 
                                echo ' <span style="color: var(--danger-color); font-weight: 700;">(Low)</span>'; 
                            } 
                        ?> 
                    </td>
                    <td data-label="Action" style="text-align: center;">
                        <!-- Edit Button -->
                        <a href="manage_products.php?edit=<?php echo $row['product_id']; ?>" 
                           class="action-btn edit-btn" 
                           title="Edit Product">
                            Edit
                        </a>
                        
                        <!-- Delete Button with confirmation -->
                        <a href="manage_products.php?delete=<?php echo $row['product_id']; ?>" 
                           onclick="return confirm('WARNING: Are you sure you want to delete the product: <?php echo htmlspecialchars($row['product_name']); ?>? This action will also remove all historical order data associated with it.')"
                           class="action-btn delete-btn"
                           title="Delete Product">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php 
                    } 
                } else { 
                ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No products found. Use the form above to add your first menu item!</td>
                </tr>
                <?php 
                } 
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
$conn->close();
?>
