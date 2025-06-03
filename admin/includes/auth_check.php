<?php
// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is an admin. Otherwise, redirect to admin login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    // Store the attempted URL to redirect after login
    $_SESSION['redirect_url_admin'] = $_SERVER['REQUEST_URI'];
    header("location: " . (isset($admin_base_url) ? $admin_base_url : '') . "login.php");
    exit;
}

// Optional: Database connection if not already included
// Usually, db_connect.php from the root would be included by the calling admin page.
// Example path for db_connect.php from admin pages: ../includes/db_connect.php
?>