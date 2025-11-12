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
$current_user_id = $_SESSION['user_id'] ?? null; // Get the ID of the current logged-in user

// --- POST/GET HANDLERS ---

// Add User
if(isset($_POST['add_user'])){
    $username = trim($_POST['username']);
    $password = $_POST['password']; 
    $role = $_POST['role'];
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $message = "<div class='alert error'>❌ Username and Password are required.</div>";
    } else {
        // NOTE: In a production app, always use password_hash() here for security!
        $hashed_password = $password; 

        // Use prepared statements
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $message = "<div class='alert success'>🎉 Staff account '{$username}' added successfully as " . ucfirst($role) . "!</div>";
        } else {
            $message = "<div class='alert error'>❌ Error adding user: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Delete User
if(isset($_GET['delete'])){
    $user_id_to_delete = $_GET['delete'];
    
    // Prevent admin from deleting themselves
    if ($user_id_to_delete == $current_user_id) {
        $message = "<div class='alert warning'>⚠️ You cannot delete your own account!</div>";
    } else {
        // Check if the user is linked to any orders (good practice for FK constraint)
        $check_orders = $conn->query("SELECT COUNT(*) AS count FROM orders WHERE cashier_id = '$user_id_to_delete'")->fetch_assoc()['count'];

        if ($check_orders > 0) {
             $message = "<div class='alert warning'>⚠️ Cannot delete user. This user is linked to {$check_orders} existing orders.</div>";
        } else {
            // Use prepared statement for deletion
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id_to_delete);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>✅ User ID {$user_id_to_delete} deleted successfully.</div>";
            } else {
                $message = "<div class='alert error'>❌ Error deleting user: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}

// Fetch all users
// REMOVED 'last_login' as it appears to be an unknown column
$result = $conn->query("SELECT user_id, username, role FROM users ORDER BY user_id ASC");
?>

<div class="container">
    <h2><span style="font-size: 1.2em; vertical-align: middle;">👥</span> Manage Staff Accounts</h2>
    <p class="section-description">Add, view, and manage admin and cashier accounts for the Canteen System.</p>

    <?php echo $message; // Display messages ?>

    <div class="card-form-container">
        <h3>Add New Staff Account</h3>
        <form action="manage_users.php" method="POST" class="user-form">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="cashier">Cashier</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn btn-primary">Add Staff</button>
        </form>
    </div>

    <!-- User List Table -->
    <div class="table-container">
        <h3>Current Staff List</h3>
        <table class="data-table-canteen">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <!-- REMOVED: <th>Last Login</th> -->
                    <th style="width: 150px; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()){ 
                ?>
                <tr>
                    <td data-label="ID"><?php echo $row['user_id']; ?></td>
                    <td data-label="Username" class="<?php echo ($row['user_id'] == $current_user_id) ? 'current-user-row' : ''; ?>">
                        <?php echo htmlspecialchars($row['username']); ?>
                        <?php if ($row['user_id'] == $current_user_id) { echo ' <span style="font-size: 0.8em; color: var(--color-current);">(You)</span>'; } ?>
                    </td>
                    <td data-label="Role">
                        <span class="role-tag <?php echo ($row['role'] == 'admin') ? 'admin-tag' : 'cashier-tag'; ?>">
                            <?php echo ucfirst($row['role']); ?>
                        </span>
                    </td>
                    <!-- REMOVED: <td data-label="Last Login"><?php echo $row['last_login'] ?? 'N/A'; ?></td> -->
                    <td data-label="Action" style="text-align: center;">
                        <?php if ($row['user_id'] != $current_user_id) { ?>
                            <a href="manage_users.php?delete=<?php echo $row['user_id']; ?>" 
                               onclick="return confirm('WARNING: Are you sure you want to delete the user: <?php echo htmlspecialchars($row['username']); ?>? This action is not reversible and may be blocked if the user has existing orders.')"
                               class="action-btn delete-btn">
                                Delete
                            </a>
                        <?php } else { ?>
                            <span style="color: #6B7280; font-style: italic;">Cannot Delete</span>
                        <?php } ?>
                    </td>
                </tr>
                <?php 
                    } 
                } else { 
                ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No users found. Add your first staff account!</td>
                </tr>
                <?php 
                } 
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// Close database connection
if ($conn) {
    $conn->close();
}
?>

<style>
/* CSS additions for this page */

/* Specific Card-Form for user management */
.card-form-container {
    background-color: var(--color-card-bg);
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.card-form-container h3 {
    font-size: 1.25rem;
    margin-top: 0;
    margin-bottom: 20px;
    color: var(--secondary-color);
    border-bottom: 2px solid var(--color-border);
    padding-bottom: 10px;
}

.user-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    align-items: end; /* Aligns button to the bottom */
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--secondary-color);
}

.form-group input,
.form-group select {
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 1rem;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary-color);
    outline: none;
}

/* Current User Highlight */
.current-user-row {
    background-color: var(--color-current-bg) !important; /* Light Blue for 'You' */
    font-weight: 600;
    border-left: 5px solid var(--color-current);
}

/* Role Tags */
.role-tag {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}
.admin-tag {
    background-color: var(--primary-color);
    color: white;
}
.cashier-tag {
    background-color: var(--secondary-color);
    color: white;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .user-form {
        grid-template-columns: 1fr; /* Stack columns on mobile */
    }
    
    .btn-primary {
        margin-top: 10px; /* Space the button out if stacked */
    }
    
    /* Responsive Table Styles (Defined in header.php for .data-table-canteen) */
    /* Ensure the table is still readable on mobile */
}
</style>

