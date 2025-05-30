<?php
// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u259133487_dlquiz'); // আপনার ডাটাবেস ইউজারনেম
define('DB_PASSWORD', 'lVMXkD|4Ll');     // আপনার ডাটাবেস পাসওয়ার্ড
define('DB_NAME', 'u259133487_dlquiz'); // আপনার ডাটাবেসের নাম

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// Set character set to utf8mb4 for Bengali support
if (!$conn->set_charset("utf8mb4")) {
    printf("Error loading character set utf8mb4: %s\n", $conn->error);
    exit();
}

// Set PHP default timezone
date_default_timezone_set('Asia/Dhaka');

// Set MySQL connection timezone
if (!$conn->query("SET time_zone = '+06:00';")) { // For Asia/Dhaka (UTC+6)
    error_log("Failed to set time_zone for MySQL connection: " . $conn->error);
    // You might want to handle this error more gracefully depending on your application's needs
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>