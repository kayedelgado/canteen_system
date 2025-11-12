<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

include('header.php');
include('navbar.php');
include('../config/db_connect.php');

$message = ""; // Initialize message variable

// Add Category
if(isset($_POST['add_category'])){
    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $category_name);
        if ($stmt->execute()) {
            $message = "<div class='alert success'><span class='alert-icon'>✅</span> Category added successfully!</div>";
        } else {
            $message = "<div class='alert error'><span class='alert-icon'>❌</span> Error adding category: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert warning'><span class='alert-icon'>⚠️</span> Category name cannot be empty.</div>";
    }
}

// Delete Category
if(isset($_GET['delete'])){
    $category_id = $_GET['delete'];
    
    // Check if any products are linked to this category first (good practice)
    $check_products = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $check_products->bind_param("i", $category_id);
    $check_products->execute();
    $check_products->bind_result($product_count);
    $check_products->fetch();
    $check_products->close();
    
    if ($product_count > 0) {
        $message = "<div class='alert error'><span class='alert-icon'>❌</span> Cannot delete category. **$product_count product(s)** are currently linked to it.</div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE category_id=?");
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $message = "<div class='alert success'><span class='alert-icon'>🗑️</span> Category deleted successfully.</div>";
        } else {
            $message = "<div class='alert error'><span class='alert-icon'>❌</span> Error deleting category: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
    // Clean URL after action to prevent re-deletion on refresh
    // Note: A true redirect (header location) would be more robust, but leaving a simple message here for display
}

// Fetch all categories
$result = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
?>

<div class="container">
    <h2><span style="font-size: 1.2em; vertical-align: middle;">📋</span> Manage Food Categories</h2>
    
    <?php echo $message; // Display status message ?>

    <div class="form-card">
        <h3>Add New Category</h3>
        <!-- Add Category Form -->
        <!-- Updated to use form-group and standard inputs for better styling adherence -->
        <form method="POST" class="add-form">
            <div class="form-group" style="display: flex; gap: 10px;">
                <input type="text" name="category_name" placeholder="E.g., Snacks, Hot Meals, Beverages" required style="flex-grow: 1;">
                <button type="submit" name="add_category" class="btn-primary">
                    <span style="font-size: 1.2em;">➕</span> Add Category
                </button>
            </div>
        </form>
    </div>

    <!-- Categories Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th style="width: 120px; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()){ 
                ?>
                <tr>
                    <td><?php echo $row['category_id']; ?></td>
                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                    <td style="text-align: center;">
                        <!-- Using an 'action-btn' class for visual styling consistency -->
                        <a href="manage_categories.php?delete=<?php echo $row['category_id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete the category: <?php echo htmlspecialchars($row['category_name']); ?>?')"
                           class="action-btn delete-btn">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php 
                    } 
                } else { 
                ?>
                <tr>
                    <td colspan="3" style="text-align: center;">No categories found. Add your first category!</td>
                </tr>
                <?php 
                } 
                ?>
            </tbody>
        </table>
    </div>
</div>
