<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gamesphere_db');

// Error reporting (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_errno) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log to file
    error_log(date('[Y-m-d H:i:s] ') . $e->getMessage() . "\n", 3, "db_errors.log");
    
    // Generic user message
    die("System maintenance in progress. Please try again shortly.");
}
?>