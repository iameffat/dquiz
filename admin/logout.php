<?php
session_start();

$_SESSION = array(); // Unset all session variables

if (session_destroy()) {
    header("location: login.php"); // Redirect to admin login page
    exit;
} else {
    echo "লগআউট ব্যর্থ হয়েছে।";
}
?>