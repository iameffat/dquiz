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

// Cloudflare API Configuration
// !! গুরুত্বপূর্ণ: নিচের মানগুলো আপনার আসল Cloudflare তথ্য দিয়ে পরিবর্তন করুন !!
// !! প্রোডাকশনের জন্য এই তথ্যগুলো এনভায়রনমেন্ট ভ্যারিয়েবল বা অন্য কোনো নিরাপদ উপায়ে সংরক্ষণ করুন !!
define('CLOUDFLARE_ZONE_ID', 'de81d047d212760f1c53492a2bc1a4fc'); // আপনার Cloudflare Zone ID এখানে দিন
define('CLOUDFLARE_API_TOKEN', 'ssLJZCntDPfF5plkElUlMw2RFv5x5K3kslrDATd8'); // আপনার Cloudflare API Token এখানে দিন (প্রস্তাবিত)
/*
// অথবা যদি Global API Key ব্যবহার করতে চান (কম নিরাপদ):
define('CLOUDFLARE_EMAIL', 'আপনার_ক্লাউডফ্লেয়ার_ইমেইল');
define('CLOUDFLARE_GLOBAL_API_KEY', 'আপনার_গ্লোবাল_এপিআই_কী');
define('CLOUDFLARE_USE_API_TOKEN', false); // Global API Key ব্যবহার করলে এটিকে false করুন
*/
if (!defined('CLOUDFLARE_USE_API_TOKEN')) {
    define('CLOUDFLARE_USE_API_TOKEN', true); // ডিফল্টভাবে API Token ব্যবহার করা হবে
}
if (CLOUDFLARE_USE_API_TOKEN && !defined('CLOUDFLARE_EMAIL')) { // API Token ব্যবহার করলে এগুলোর প্রয়োজন নেই
    define('CLOUDFLARE_EMAIL', '');
    define('CLOUDFLARE_GLOBAL_API_KEY', '');
}

?>