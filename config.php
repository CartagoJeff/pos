<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'root');
define('DB_NAME', 'pos_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Start session
session_start();

// Authentication check function
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

// Authorization check function
function hasRole($requiredRole) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $requiredRole;
}

// Redirect if not authenticated
function redirectIfNotAuthenticated() {
    if (!isAuthenticated()) {
        header("Location: login.php");
        exit();
    }
}

// Redirect if not authorized
function redirectIfNotAuthorized($requiredRole) {
    redirectIfNotAuthenticated();
    if (!hasRole($requiredRole)) {
        header("Location: unauthorized.php");
        exit();
    }
}
?>