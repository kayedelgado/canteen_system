<?php
// Start session if not already started
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Redirect if not admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Canteen System</title>

    <!-- Use a modern, easy-to-read font (Inter/Poppins are popular for UI) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* CSS Variables: Canteen Colors (Aligned with Dashboard) */
        :root {
            --primary-color: #f916eeff; /* Warm Orange/Rust - Thematic Accent */
            --secondary-color: #28271aff; /* Dark Gray/Blue - Primary Text & Headings */
            --success-color: #db6f8eff; /* Green - Add/Save actions */
            --danger-color: #EF4444; /* Red - Delete/Alerts */
            --background-light: #e49bafff; /* Very light background */
            --container-bg: #FFFFFF; /* Crisp White container */
            --border-color: #E5E7EB; /* Light border */
            --table-header-bg: #FFFBEB; /* Light Creamy Yellow - Canteen Tablecloth effect */
            --font-family: 'Poppins', sans-serif;
            --shadow-subtle: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        /* 1. BASE STYLES & TYPOGRAPHY */
        body {
            font-family: var(--font-family);
            background: var(--background-light);
            margin: 0;
            padding: 0;
            color: var(--secondary-color);
            line-height: 1.6;
        }

        /* 2. CONTAINER & LAYOUT */
        .container {
            max-width: 1200px;
            margin: 40px auto; /* Increased margin for breathing room */
            padding: 30px;
            background: var(--container-bg);
            border-radius: 12px; /* Smoother, modern corners */
            box-shadow: var(--shadow-medium);
            min-height: 85vh;
        }

        h2 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--border-color); /* Subtle underline */
            font-weight: 700;
        }

        /* 3. CARD STYLING (For forms, lists, tables) */
        .card, .form-card, .table-card {
            background: var(--container-bg);
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow-subtle);
            margin-bottom: 20px;
        }

        /* 4. FORM ELEMENTS */
        input[type="text"], input[type="number"], input[type="password"], select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #D1D5DB; /* Light gray border */
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
            margin-bottom: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2); /* Orange focus ring */
            outline: none;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--secondary-color);
        }
        .form-group {
            margin-bottom: 20px;
        }


        /* 5. BUTTON STYLING */
        button, .action-btn, .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.2s, box-shadow 0.2s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1rem;
            line-height: 1.25;
        }
        button:active, .action-btn:active, .btn:active {
            transform: scale(0.99);
        }

        /* Primary Button (Add/Save) */
        .btn-primary, button[name^="add_"], button[name^="update_"] {
            background-color: var(--success-color);
            color: white;
        }
        .btn-primary:hover, button[name^="add_"]:hover, button[name^="update_"]:hover {
            background-color: #059669; /* Darker Green */
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.4);
        }

        /* Secondary Button (Edit/View) */
        .btn-secondary, .edit-btn {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-secondary:hover, .edit-btn:hover {
            background-color: #EA580C; /* Darker Orange */
            box-shadow: 0 2px 4px rgba(249, 115, 22, 0.4);
        }

        /* Danger Button (Delete) */
        .btn-danger, .delete-btn {
            background-color: var(--danger-color);
            color: white;
        }
        .btn-danger:hover, .delete-btn:hover {
            background-color: #DC2626; /* Darker Red */
        }

        /* Neutral Button (Cancel) */
        .btn-neutral, .cancel-btn {
            background-color: #E5E7EB;
            color: var(--secondary-color);
        }
        .btn-neutral:hover, .cancel-btn:hover {
            background-color: #D1D5DB;
        }

        /* 6. TABLE STYLING */
        .table-responsive {
            overflow-x: auto; /* Ensure tables are responsive on small screens */
            box-shadow: var(--shadow-subtle);
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: separate; /* Use separate for border-radius on cells */
            border-spacing: 0;
            margin-top: 0;
            background: var(--container-bg);
            /* border: 1px solid var(--border-color); -- Removed border for cleaner look */
        }
        table th, table td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }
        table th {
            background-color: var(--table-header-bg); /* Thematic creamy yellow header */
            color: var(--secondary-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        /* Style for the first and last column of the table */
        table tr th:first-child, table tr td:first-child { border-left: none; }
        table tr th:last-child, table tr td:last-child { border-right: none; }

        table tbody tr:hover {
            background-color: #F8F8F8; /* Subtle hover effect */
        }

        /* 7. ALERTS AND MESSAGES */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: 600;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-icon {
            font-size: 1.25rem;
        }
        .alert.success {
            background-color: #D1FAE5; /* Very light green */
            color: #065F46; /* Dark green text */
            border-left-color: var(--success-color);
        }
        .alert.error, .alert.danger {
            background-color: #FEE2E2; /* Very light red */
            color: #991B1B; /* Dark red text */
            border-left-color: var(--danger-color);
        }
        .alert.warning {
            background-color: #FFFBEB; /* Very light yellow */
            color: #92400E; /* Dark brown/orange text */
            border-left-color: var(--primary-color);
        }

        /* 8. Utility Classes for Spacing/Layout */
        .flex-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
                padding: 15px;
            }
            table th, table td {
                padding: 10px 15px;
            }
            h2 {
                font-size: 1.5rem;
            }
        }

    </style>
</head>
<body>