<?php
// includes/functions.php

if (session_status() == PHP_SESSION_NONE) {
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
        echo $message;
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

/**
 * Verifies a Cloudflare Turnstile CAPTCHA response using cURL.
 *
 * @param string $turnstile_response The token from the cf-turnstile-response POST field.
 * @param string $secret_key Your Cloudflare Turnstile secret key.
 * @return array ['success' => bool, 'error_message' => string|null, 'error_codes' => array|null]
 */
function verify_cloudflare_turnstile(string $turnstile_response, string $secret_key): array {
    $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secret_key,
        'response' => $turnstile_response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] // Optional, but recommended
    ];

    if (!function_exists('curl_init')) {
        error_log("cURL extension is not enabled on the server.");
        return ['success' => false, 'error_message' => "সার্ভারে cURL এক্সটেনশন সক্রিয় নেই। অনুগ্রহ করে অ্যাডমিনের সাথে যোগাযোগ করুন।", 'error_codes' => ['curl-not-enabled']];
    }

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout in seconds
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $verify_result_json = curl_exec($ch);
    $curl_error_no = curl_errno($ch);
    $curl_error_msg = curl_error($ch);
    curl_close($ch);

    if ($curl_error_no) {
        error_log("Cloudflare Turnstile cURL request failed: Error {$curl_error_no} - {$curl_error_msg}");
        $user_facing_error = "ক্যাপচা যাচাইকরণ সার্ভারের সাথে সংযোগ করতে ব্যর্থ হয়েছে।";
        if ($curl_error_no == 6) {
            $user_facing_error .= " (সার্ভার খুঁজে পাওয়া যায়নি)";
        } elseif ($curl_error_no == 7) {
             $user_facing_error .= " (সংযোগ স্থাপন করা যায়নি)";
        } elseif ($curl_error_no == 28) {
             $user_facing_error .= " (সময়সীমা অতিক্রম করেছে)";
        }
        return ['success' => false, 'error_message' => $user_facing_error, 'error_codes' => ['curl-request-failed', 'errno-'.$curl_error_no]];
    }

    if ($verify_result_json === false) {
        return ['success' => false, 'error_message' => "ক্যাপচা যাচাই করতে সার্ভারের সাথে সংযোগ করা যায়নি।", 'error_codes' => ['curl-exec-false']];
    }

    $verify_result = json_decode($verify_result_json);

    if (!$verify_result) {
        return ['success' => false, 'error_message' => "ক্যাপচা যাচাইকরণের প্রতিক্রিয়া বুঝতে সমস্যা হয়েছে।", 'error_codes' => ['json-decode-failed']];
    }

    if ($verify_result->success) {
        return ['success' => true, 'error_message' => null, 'error_codes' => null];
    } else {
        $error_codes = isset($verify_result->{'error-codes'}) ? (array) $verify_result->{'error-codes'} : [];
        if (!empty($error_codes)) {
            error_log("Cloudflare Turnstile verification failed (cURL). Error codes: " . implode(', ', $error_codes));
        }
        $user_error_message = "ক্যাপচা যাচাই ব্যর্থ হয়েছে।";
        if (in_array('timeout-or-duplicate', $error_codes)) {
            $user_error_message = "ক্যাপচা টোকেনটির মেয়াদ শেষ হয়ে গেছে বা এটি ইতিমধ্যে ব্যবহৃত হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
        } elseif (in_array('invalid-input-response', $error_codes)) {
            $user_error_message = "অবৈধ ক্যাপচা টোকেন। অনুগ্রহ করে পৃষ্ঠাটি রিফ্রেশ করে আবার চেষ্টা করুন।";
        }
        elseif (in_array('bad-request', $error_codes) || in_array('invalid-input-secret', $error_codes)) {
            $user_error_message = "ক্যাপচা কনফিগারেশনে সমস্যা। অনুগ্রহ করে সাইট অ্যাডমিনের সাথে যোগাযোগ করুন।";
        }
        return ['success' => false, 'error_message' => $user_error_message, 'error_codes' => $error_codes];
    }
}


/**
 * Parses the User-Agent string to get browser and OS information.
 *
 * @param string $user_agent The User-Agent string.
 * @return array Associative array with 'browser' and 'os'.
 */
function parse_user_agent_simple(string $user_agent): array {
    $browser = "Unknown Browser";
    $os_platform = "Unknown OS Platform";

    // Get Operating System
    if (preg_match('/windows nt 10/i', $user_agent)) {
        $os_platform = 'Windows 10/11';
    } elseif (preg_match('/windows nt 6.3/i', $user_agent)) {
        $os_platform = 'Windows 8.1';
    } elseif (preg_match('/windows nt 6.2/i', $user_agent)) {
        $os_platform = 'Windows 8';
    } elseif (preg_match('/windows nt 6.1/i', $user_agent)) {
        $os_platform = 'Windows 7';
    } elseif (preg_match('/windows nt 5.1/i', $user_agent) || preg_match('/windows xp/i', $user_agent)) {
        $os_platform = 'Windows XP';
    } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
        $os_platform = 'Mac OS X';
        if (preg_match('/mac os x 10_([0-9_]+)/i', $user_agent, $matches)) {
            $os_platform .= " " . str_replace('_', '.', $matches[1]);
        }
    } elseif (preg_match('/iphone os ([0-9_]+)/i', $user_agent, $matches)) {
        $os_platform = 'iOS ' . str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/android ([\d\.]+)/i', $user_agent, $matches)) {
        $os_platform = 'Android ' . $matches[1];
    } elseif (preg_match('/linux/i', $user_agent)) {
        $os_platform = 'Linux';
    } elseif (preg_match('/ubuntu/i', $user_agent)) {
        $os_platform = 'Ubuntu';
    }

    // Get Browser
    if (preg_match('/msie/i', $user_agent) && !preg_match('/opera/i', $user_agent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/trident\/7.0/i', $user_agent)) { // IE11
        $browser = 'Internet Explorer 11';
    } elseif (preg_match('/firefox/i', $user_agent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/chrome/i', $user_agent) && !preg_match('/edg/i', $user_agent) && !preg_match('/opr/i', $user_agent) ) { // Chrome, not Edge Chromium or Opera
        $browser = 'Google Chrome';
    } elseif (preg_match('/safari/i', $user_agent) && !preg_match('/chrome/i', $user_agent) && !preg_match('/edg/i', $user_agent) && !preg_match('/opr/i', $user_agent)) { // Safari, not Chrome, Edge or Opera
        $browser = 'Apple Safari';
    } elseif (preg_match('/opera/i', $user_agent) || preg_match('/opr/i', $user_agent)) { // Opera
        $browser = 'Opera';
    } elseif (preg_match('/edg/i', $user_agent)) { // Edge (Chromium-based)
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/edge/i', $user_agent)) { // Edge (Legacy - very old)
        $browser = 'Microsoft Edge (Legacy)';
    }


    // Extract versions (simple approach)
    // This regex needs to be general enough
    if (preg_match('/(msie |firefox\/|chrome\/|version\/|opera\/|opr\/|edg\/|edge\/)([0-9\.]+)/i', $user_agent, $matches_ver)) {
         if (count($matches_ver) > 2 && !empty(trim($matches_ver[2]))) {
             // Check if the browser name is already set to avoid appending version to "Unknown Browser"
             if ($browser !== "Unknown Browser") {
                 // Remove existing version if any to avoid duplication like "Google Chrome 100.0.0.0 100.0.0.0"
                 $browser = preg_replace('/\s+[0-9\.]+$/', '', $browser);
                 $browser .= ' ' . $matches_ver[2];
             } elseif (stripos($matches_ver[1], 'version') !== false && stripos($user_agent, 'safari') !== false) {
                // This is likely Safari, where version is separate
                $browser = 'Apple Safari ' . $matches_ver[2];
             }
         }
     }

    return ['browser' => $browser, 'os' => $os_platform];
}

// আপনি এখানে আরও প্রয়োজনীয় কমন ফাংশন যোগ করতে পারেন।
?>