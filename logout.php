<?php
// Initialize the session
session_start(); // Or require_once 'includes/db_connect.php'; which also starts session

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
if (session_destroy()) {
    // Redirect to login page or home page
    header("location: index.php");
    exit;
} else {
    // If session destroy failed
    echo "লগআউট ব্যর্থ হয়েছে।";
}
?>