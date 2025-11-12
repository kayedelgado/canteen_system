<?php
session_start();
// Include the database connection configuration
include('config/db_connect.php');

$error = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if user exists
    // NOTE: This uses prepared statements which is good practice.
    $stmt = $conn->prepare("SELECT user_id, password, role FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close(); // Close the statement

   // NOTE: This comparison is highly insecure. In a production app, you MUST use password_verify($password, $result['password'])
   // Keeping the original insecure logic for functionality, but flagging for security.
   if ($result && $password == $result['password']) { 
    $_SESSION['user_id'] = $result['user_id'];
    $_SESSION['role'] = $result['role'];
    $_SESSION['username'] = $username; // Store username for personalized greeting
    
    // Redirect based on role
    if ($result['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: cashier/dashboard.php"); // Updated to dashboard.php as the cashier's entry point
    }
    exit();
} else {
    $error = "Invalid username or password.";
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canteen System Login</title>
    
    <!-- Using a modern, easy-to-read font (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* CSS Variables: Replicating Admin Panel Colors */
        :root {
            --primary-color: #F97316; /* Warm Orange/Rust */
            --secondary-color: #1F2937; /* Dark Gray/Blue */
            --danger-color: #EF4444; /* Red */
            --background-light: #FBFBFB;
        }

        /* 1. Body and Background */
        body {
            font-family: 'Poppins', sans-serif;
            /* Subtle radial gradient for depth and interest */
            background: radial-gradient(circle at top right, rgba(249, 115, 22, 0.1) 0%, transparent 40%),
                        radial-gradient(circle at bottom left, rgba(31, 41, 55, 0.1) 0%, transparent 50%),
                        var(--background-light); 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        /* 2. Login Card Container */
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 50px 30px;
            background-color: white;
            border-radius: 16px; /* Slightly more rounded */
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); /* Deeper shadow */
            text-align: center;
            border-top: 5px solid var(--primary-color); /* Thematic top border */
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-3px);
        }

        /* 3. Logo and Header */
        .logo-icon {
            font-size: 3.5rem; /* Larger icon */
            display: block;
            margin-bottom: 10px;
            line-height: 1;
        }

        h2 {
            font-size: 1.8rem;
            color: var(--secondary-color);
            margin-bottom: 30px;
            font-weight: 700;
        }

        /* 4. Form Groups */
        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 0.95rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF; /* Light gray icon */
            font-size: 1.1rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px; /* Added left padding for the icon */
            border: 2px solid #D1D5DB; /* Lighter, defined border */
            border-radius: 8px;
            font-size: 1rem;
            color: var(--secondary-color);
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.3);
            outline: none;
        }

        /* 5. Login Button */
        .login-btn {
            width: 100%;
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
            margin-top: 15px;
        }

        .login-btn:hover {
            background-color: #E0600F; /* Slightly darker orange */
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(249, 115, 22, 0.4);
        }
        
        /* 6. Styled Error Message */
        .alert-error {
            background-color: #FEE2E2;
            color: var(--danger-color);
            padding: 12px 15px;
            margin-bottom: 25px;
            border-radius: 6px;
            border-left: 5px solid var(--danger-color);
            font-weight: 600;
            font-size: 0.95rem;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
    <!-- Font Awesome for icons (if needed, but using emojis for simplicity) -->
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> -->
</head>
<body>
<div class="login-container">
    <span class="logo-icon">🍽️</span> <!-- Changed icon to be more Canteen/Food related -->
    <h2>Canteen System Login</h2>
    
    <?php if($error != '') { ?>
        <div class='alert-error'>
            <span style="font-size: 1.2em;">❌</span> <?php echo $error; ?>
        </div>
    <?php } ?>
    
    <form method="POST">
        <!-- Username Field -->
        <div class="form-group">
            <label for="username">Username</label>
            <div class="input-wrapper">
                <span class="input-icon">👤</span> <!-- User icon -->
                <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
            </div>
        </div>
        
        <!-- Password Field -->
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrapper">
                <span class="input-icon">🔒</span> <!-- Lock icon -->
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>
        </div>
        
        <button type="submit" name="login" class="login-btn">
            Sign In
        </button>
    </form>
</div>
</body>
</html>
