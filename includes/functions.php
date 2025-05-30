<?php
// includes/functions.php

if (session_status() == PHP_SESSION_NONE) {
    // এটি db_connect.php তেও আছে, তবে এখানে একটি অতিরিক্ত সুরক্ষা হিসেবে রাখা যেতে পারে
    // যদি functions.php সরাসরি db_connect.php ছাড়া কোথাও include করা হয় (যদিও উচিত না)
    session_start();
}

/**
 * Redirects to a specific page.
 *
 * @param string $url The URL to redirect to.
 * @return void
 */
function redirect_to(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Sanitizes output for HTML display.
 * A wrapper for htmlspecialchars.
 *
 * @param string|null $string The string to sanitize.
 * @return string The sanitized string.
 */
function escape_html(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Displays a flash message if one is set in the session.
 * Call this function where you want flash messages to appear.
 *
 * @param string $message_key The session key for the message.
 * @param string $type_key The session key for the message type (e.g., 'success', 'danger').
 * @return void
 */
function display_flash_message(string $message_key = 'flash_message', string $type_key = 'flash_message_type'): void {
    if (isset($_SESSION[$message_key])) {
        $message = $_SESSION[$message_key];
        $type = $_SESSION[$type_key] ?? 'info'; // Default to 'info' if type not set

        echo '<div class="alert alert-' . escape_html($type) . ' alert-dismissible fade show" role="alert">';
        echo escape_html($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';

        // Clear the flash message from session after displaying
        unset($_SESSION[$message_key]);
        unset($_SESSION[$type_key]);
    }
}

/**
 * Formats a datetime string into a more readable format.
 * Example: d M Y, h:i A  (e.g., 30 May 2025, 01:45 AM)
 *
 * @param string|null $datetime_string The datetime string to format.
 * @param string $format The desired output format (php.net/date for options).
 * @return string The formatted date string, or 'N/A' if input is invalid.
 */
function format_datetime(?string $datetime_string, string $format = "d M Y, h:i A"): string {
    if (empty($datetime_string)) {
        return 'N/A';
    }
    try {
        $date = new DateTime($datetime_string);
        return $date->format($format);
    } catch (Exception $e) {
        return 'N/A'; // Return N/A or the original string if formatting fails
    }
}

/**
 * Formats seconds into H:i:s (Hours:Minutes:Seconds) format.
 *
 * @param int|null $total_seconds The total seconds.
 * @return string The formatted time string, or 'N/A'.
 */
function format_seconds_to_hms(?int $total_seconds): string {
    if ($total_seconds === null || $total_seconds < 0) {
        return 'N/A';
    }
    return gmdate("H:i:s", $total_seconds);
}


/**
 * Gets a specific setting value from the site_settings table.
 * Note: This requires the $conn (database connection) to be available in the global scope
 * or passed as a parameter. For simplicity, assuming global $conn from db_connect.php.
 *
 * @param string $setting_key The key of the setting to fetch.
 * @param mixed $default_value The value to return if the setting is not found.
 * @return mixed The setting value or the default value.
 */
function get_site_setting(string $setting_key, $default_value = null) {
    global $conn; // Assumes $conn is global from db_connect.php

    if (!$conn) {
        // Consider logging this error or handling it more gracefully
        // For now, returning default if DB connection is not available.
        return $default_value;
    }

    $sql = "SELECT setting_value FROM site_settings WHERE setting_key = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $setting_key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['setting_value'];
        }
        $stmt->close();
    }
    return $default_value;
}

// আপনি এখানে আরও প্রয়োজনীয় কমন ফাংশন যোগ করতে পারেন।
?>