<?php
// includes/db_connect.php

// Define a constant to allow execution in included files.
define('DQUIZ_EXEC', true);

// Include the configuration file where credentials are stored.
require_once __DIR__ . '/config.php';

// Attempt to connect to MySQL database using defined constants from config.php
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

// Set PHP default timezone using the constant from config.php
date_default_timezone_set(APP_TIMEZONE);

// Set MySQL connection timezone using the constant from config.php
if (!$conn->query("SET time_zone = '" . DB_TIMEZONE . "';")) {
    error_log("Failed to set time_zone for MySQL connection: " . $conn->error);
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

?>