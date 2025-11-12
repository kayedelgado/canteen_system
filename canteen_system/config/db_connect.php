<?php
// CRITICAL: Replace these placeholders with your actual database credentials
$servername = "localhost";
$username = "root"; // Update this
$password = "";     // Update this
$dbname = "canteen_db"; // Ensure this matches your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8
$conn->set_charset("utf8mb4");

// Function for safely retrieving session data
function getSession($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

?>
