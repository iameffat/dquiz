<?php
$page_title = "সাইট সেটিংস"; // Updated page title for broader scope
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php'; // CLOUDFLARE_* constants should be available now
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$feedback_message = "";
$message_type = "";

// Function to purge Cloudflare Cache
// This function can also be moved to includes/functions.php if you prefer
if (!function_exists('purge_cloudflare_cache')) {
    function purge_cloudflare_cache() {
        if (!defined('CLOUDFLARE_ZONE_ID') || 
            (defined('CLOUDFLARE_USE_API_TOKEN') && CLOUDFLARE_USE_API_TOKEN && !defined('CLOUDFLARE_API_TOKEN')) ||
            (defined('CLOUDFLARE_USE_API_TOKEN') && !CLOUDFLARE_USE_API_TOKEN && (!defined('CLOUDFLARE_EMAIL') || !defined('CLOUDFLARE_GLOBAL_API_KEY')))
        ) {
            return ['success' => false, 'message' => 'Cloudflare API credentials সঠিকভাবে কনফিগার করা হয়নি (db_connect.php চেক করুন)।'];
        }

        $zone_id = CLOUDFLARE_ZONE_ID;
        $use_api_token = CLOUDFLARE_USE_API_TOKEN;

        if (empty($zone_id) || $zone_id === 'আপনার_জোনের_আইডি') {
             return ['success' => false, 'message' => 'Cloudflare Zone ID কনফিগার করা হয়নি।'];
        }

        if ($use_api_token) {
            $api_token = CLOUDFLARE_API_TOKEN;
            if (empty($api_token) || $api_token === 'আপনার_এপিআই_টোকেন') {
                return ['success' => false, 'message' => 'Cloudflare API Token সঠিকভাবে কনফিগার করা হয়নি।'];
            }
            $headers = [
                "Authorization: Bearer " . $api_token,
                "Content-Type: application/json"
            ];
        } else {
            $auth_email = CLOUDFLARE_EMAIL;
            $auth_key = CLOUDFLARE_GLOBAL_API_KEY;
            if (empty($auth_email) || empty($auth_key) || $auth_email === 'আপনার_ক্লাউডফ্লেয়ার_ইমেইল' || $auth_key === 'আপনার_গ্লোবাল_এপিআই_কী') {
                return ['success' => false, 'message' => 'Cloudflare Email এবং Global API Key সঠিকভাবে কনফিগার করা হয়নি।'];
            }
            $headers = [
                "X-Auth-Email: " . $auth_email,
                "X-Auth-Key: " . $auth_key,
                "Content-Type: application/json"
            ];
        }

        $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        $data = ["purge_everything" => true];

        if (!function_exists('curl_init')) {
             return ['success' => false, 'message' => "সার্ভারে cURL পিএইচপি এক্সটেনশন সক্রিয় নেই। ক্যাশ পরিষ্কার করা যাবে না।"];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true); // Changed from CUSTOMREQUEST
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds timeout

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("Cloudflare Purge cURL Error: " . $curl_error);
            return ['success' => false, 'message' => "Cloudflare API তে সংযোগ করতে সমস্যা হয়েছে (cURL Error): " . $curl_error];
        }

        $response_data = json_decode($response, true);

        if ($http_code == 200 && isset($response_data['success']) && $response_data['success'] === true) {
            return ['success' => true, 'message' => "Cloudflare ক্যাশ সফলভাবে ক্লিন করার জন্য অনুরোধ পাঠানো হয়েছে।"];
        } else {
            $error_message_detail = "Cloudflare ক্যাশ ক্লিন করতে সমস্যা হয়েছে।";
            if (isset($response_data['errors']) && !empty($response_data['errors'])) {
                $error_details_cf = [];
                foreach ($response_data['errors'] as $error) {
                    $error_details_cf[] = "Error " . (isset($error['code']) ? $error['code'] : 'N/A') . ": " . (isset($error['message']) ? $error['message'] : 'Unknown Cloudflare error');
                }
                $error_message_detail .= " Details: " . implode(", ", $error_details_cf);
            } elseif ($response) {
                $error_message_detail .= " Response: " . htmlentities(substr($response, 0, 200)); // Show part of response
            }
            error_log("Cloudflare Purge API Error (HTTP {$http_code}): " . $response);
            return ['success' => false, 'message' => $error_message_detail . " (HTTP Code: {$http_code})"];
        }
    }
}


// Fetch current homepage settings (existing code)
$current_settings = [];
$sql_fetch = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('upcoming_quiz_enabled', 'upcoming_quiz_title', 'upcoming_quiz_end_date')";
$result_fetch = $conn->query($sql_fetch);
if ($result_fetch && $result_fetch->num_rows > 0) {
    while ($row = $result_fetch->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$upcoming_quiz_enabled = isset($current_settings['upcoming_quiz_enabled']) ? (bool)$current_settings['upcoming_quiz_enabled'] : false;
$upcoming_quiz_title = isset($current_settings['upcoming_quiz_title']) ? $current_settings['upcoming_quiz_title'] : '';
$upcoming_quiz_end_date = isset($current_settings['upcoming_quiz_end_date']) ? $current_settings['upcoming_quiz_end_date'] : '';

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_homepage_settings'])) { // Existing settings form
        $posted_enabled = isset($_POST['upcoming_quiz_enabled']) ? '1' : '0';
        $posted_title = trim($_POST['upcoming_quiz_title']);
        $posted_end_date = trim($_POST['upcoming_quiz_end_date']);

        $errors_homepage = [];
        if (empty($posted_title) && $posted_enabled === '1') { // Title required only if enabled
            $errors_homepage[] = "আপকামিং কুইজের শিরোনাম খালি রাখা যাবে না যখন সেকশনটি সক্রিয় থাকবে।";
        }
        if ($posted_enabled === '1' && empty($posted_end_date)) {
            $errors_homepage[] = "কুইজ সক্রিয় থাকলে শেষের তারিখ আবশ্যক।";
        } elseif (!empty($posted_end_date) && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $posted_end_date)) {
            $errors_homepage[] = "শেষের তারিখ YYYY-MM-DD ফরম্যাটে হতে হবে।";
        }

        if (empty($errors_homepage)) {
            $settings_to_update = [
                'upcoming_quiz_enabled' => $posted_enabled,
                'upcoming_quiz_title' => $posted_title,
                'upcoming_quiz_end_date' => $posted_end_date
            ];

            $all_successful = true;
            foreach ($settings_to_update as $key => $value) {
                $sql_update = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
                if ($stmt = $conn->prepare($sql_update)) {
                    $stmt->bind_param("sss", $key, $value, $value);
                    if (!$stmt->execute()) {
                        $all_successful = false;
                        $errors_homepage[] = "Setting '{$key}' সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $all_successful = false;
                    $errors_homepage[] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
                    break;
                }
            }

            if ($all_successful) {
                $feedback_message = "হোমপেজ সেটিংস সফলভাবে আপডেট করা হয়েছে।";
                $message_type = "success";
                // Re-fetch settings to display updated values
                $upcoming_quiz_enabled = (bool)$posted_enabled;
                $upcoming_quiz_title = $posted_title;
                $upcoming_quiz_end_date = $posted_end_date;
            } else {
                $feedback_message = "হোমপেজ সেটিংস আপডেটে ত্রুটি: <br>" . implode("<br>", $errors_homepage);
                $message_type = "danger";
            }
        } else {
            $feedback_message = "হোমপেজ সেটিংস আপডেটে ত্রুটি: <br>" . implode("<br>", $errors_homepage);
            $message_type = "danger";
        }
    } elseif (isset($_POST['purge_cloudflare_cache_submit'])) { // Cloudflare purge form
        $purge_result = purge_cloudflare_cache();
        $feedback_message = $purge_result['message'];
        $message_type = $purge_result['success'] ? "success" : "danger";
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    
    <?php if ($feedback_message): ?>
    <div class="alert alert-<?php echo $message_type === "success" ? "success" : "danger"; ?> alert-dismissible fade show mt-3" role="alert">
        <?php echo $feedback_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card mt-4">
        <div class="card-header">
            হোমপেজ আপকামিং কুইজ সেকশন
        </div>
        <div class="card-body">
            <p>এখান থেকে আপনি হোমপেজে প্রদর্শিত আপকামিং কুইজ সেকশনটি নিয়ন্ত্রণ করতে পারবেন।</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="upcoming_quiz_enabled" name="upcoming_quiz_enabled" value="1" <?php echo $upcoming_quiz_enabled ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="upcoming_quiz_enabled">আপকামিং কুইজ সেকশনটি হোমপেজে দেখান</label>
                </div>
                <div class="mb-3">
                    <label for="upcoming_quiz_title" class="form-label">আপকামিং কুইজের শিরোনাম</label>
                    <input type="text" class="form-control" id="upcoming_quiz_title" name="upcoming_quiz_title" value="<?php echo htmlspecialchars($upcoming_quiz_title); ?>">
                     <small class="form-text text-muted">এই শিরোনামটি হোমপেজে দেখানো হবে যদি উপরের অপশনটি সক্রিয় থাকে।</small>
                </div>
                <div class="mb-3">
                    <label for="upcoming_quiz_end_date" class="form-label">কুইজের শেষ তারিখ (দিন গণনা এই তারিখ পর্যন্ত হবে)</label>
                    <input type="date" class="form-control" id="upcoming_quiz_end_date" name="upcoming_quiz_end_date" value="<?php echo htmlspecialchars($upcoming_quiz_end_date); ?>" placeholder="YYYY-MM-DD">
                    <small class="form-text text-muted">ফরম্যাট: YYYY-MM-DD. যেমন: <?php echo date("Y-m-d", strtotime("+7 days")); ?>। এই তারিখটি কাউন্টডাউনের জন্য ব্যবহৃত হবে।</small>
                </div>
                <button type="submit" name="save_homepage_settings" class="btn btn-primary">হোমপেজ সেটিংস সংরক্ষণ করুন</button>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            Cloudflare ক্যাশ সেটিংস
        </div>
        <div class="card-body">
            <p>ওয়েবসাইটের সকল কন্টেন্ট নতুন করে দেখানোর জন্য Cloudflare এর সম্পূর্ণ ক্যাশ পরিষ্কার করুন। এটি সাধারণত বড় কোনো পরিবর্তন করার পর প্রয়োজন হতে পারে।</p>
            <?php
            $can_purge = defined('CLOUDFLARE_ZONE_ID') && CLOUDFLARE_ZONE_ID !== 'আপনার_জোনের_আইডি' &&
                         (
                             (defined('CLOUDFLARE_USE_API_TOKEN') && CLOUDFLARE_USE_API_TOKEN && defined('CLOUDFLARE_API_TOKEN') && CLOUDFLARE_API_TOKEN !== 'আপনার_এপিআই_টোকেন') ||
                             (defined('CLOUDFLARE_USE_API_TOKEN') && !CLOUDFLARE_USE_API_TOKEN && defined('CLOUDFLARE_EMAIL') && defined('CLOUDFLARE_GLOBAL_API_KEY') && CLOUDFLARE_EMAIL !== 'আপনার_ক্লাউডফ্লেয়ার_ইমেইল' && CLOUDFLARE_GLOBAL_API_KEY !== 'আপনার_গ্লোবাল_এপিআই_কী')
                         ) && function_exists('curl_init');

            if (!$can_purge):
            ?>
                <div class="alert alert-warning">
                    Cloudflare ক্যাশ পরিষ্কার করার জন্য আপনার `includes/db_connect.php` ফাইলে `CLOUDFLARE_ZONE_ID` এবং `CLOUDFLARE_API_TOKEN` (অথবা `CLOUDFLARE_EMAIL` ও `CLOUDFLARE_GLOBAL_API_KEY`) সঠিকভাবে সেট করা নেই অথবা সার্ভারে cURL এক্সটেনশন সক্রিয় নেই।
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('আপনি কি নিশ্চিতভাবে সম্পূর্ণ ওয়েবসাইটের Cloudflare ক্যাশ ক্লিন করতে চান? এটি কিছুক্ষণের জন্য সাইটের লোডিং টাইম প্রভাবিত করতে পারে।');">
                <button type="submit" name="purge_cloudflare_cache_submit" class="btn btn-danger" <?php if (!$can_purge) echo 'disabled'; ?>>
                    সম্পূর্ণ ওয়েবসাইট ক্যাশ ক্লিন করুন
                </button>
            </form>
            <small class="form-text text-muted mt-2 d-block">
                <strong>সতর্কতা:</strong> এই বাটন চাপলে Cloudflare থেকে আপনার ওয়েবসাইটের সমস্ত ক্যাশ মুছে যাবে। এর ফলে ব্যবহারকারীরা কিছু সময়ের জন্য সাইট লোড হতে একটু বেশি সময় লাগতে পারে, কারণ Cloudflare আবার নতুন করে কন্টেন্ট ক্যাশ করবে। শুধুমাত্র বিশেষ প্রয়োজনে এটি ব্যবহার করুন।
            </small>
        </div>
    </div>

</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>