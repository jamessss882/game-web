<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Redirect to home if already authenticated
function require_guest() {
    if (isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
}
?>
