<?php
$page_title = "পাসওয়ার্ড পুনরুদ্ধার";
$base_url = ''; // Root directory - ensure this is correct for your setup
require_once 'includes/db_connect.php'; // Session is started here
require_once 'includes/functions.php'; // For escape_html, display_flash_message

$message = "";
$message_type = "info"; // Default message type

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "অনুগ্রহ করে আপনার ইমেইল এড্রেস দিন।";
        $message_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "অনুগ্রহ করে একটি সঠিক ইমেইল এড্রেস দিন।";
        $message_type = "danger";
    } else {
        $sql_user = "SELECT id, name FROM users WHERE email = ?"; // Fetch name for email personalization
        if ($stmt_user = $conn->prepare($sql_user)) {
            $stmt_user->bind_param("s", $email);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();

            if ($user_data = $result_user->fetch_assoc()) {
                $user_id = $user_data['id'];
                $user_name = $user_data['name']; // Get user's name

                $token = bin2hex(random_bytes(32));
                $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $conn->begin_transaction();
                try {
                    $sql_delete_old_tokens = "DELETE FROM password_resets WHERE user_id = ?";
                    if ($stmt_delete = $conn->prepare($sql_delete_old_tokens)) {
                        $stmt_delete->bind_param("i", $user_id);
                        $stmt_delete->execute();
                        $stmt_delete->close();
                    } else {
                        throw new Exception("ডাটাবেস সমস্যা (পুরাতন টোকেন মুছতে): " . $conn->error);
                    }

                    // IMPORTANT: Ensure 'password_resets' table exists with user_id, token (hashed), expires_at
                    $sql_insert_token = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
                    if ($stmt_insert = $conn->prepare($sql_insert_token)) {
                        $stmt_insert->bind_param("iss", $user_id, $hashed_token, $expires_at);
                        if (!$stmt_insert->execute()) {
                            throw new Exception("ডাটাবেস সমস্যা (নতুন টোকেন সংরক্ষণ করতে): " . $stmt_insert->error);
                        }
                        $stmt_insert->close();
                    } else {
                        throw new Exception("ডাটাবেস সমস্যা (নতুন টোকেন প্রস্তুত করতে): " . $conn->error);
                    }
                    
                    $conn->commit();

                    // Construct full reset link
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $domainName = $_SERVER['HTTP_HOST']; // e.g., yourdomain.com
                    
                    // Adjust if reset_password.php is not in the root
                    // If $base_url is for a subdirectory like '/quiz/', then:
                    // $reset_link_path = rtrim($base_url, '/') . "/reset_password.php?token=" . urlencode($token);
                    // If reset_password.php is in the root and $base_url is also '', then:
                    $reset_link_path = "/reset_password.php?token=" . urlencode($token);
                    if (!empty($base_url) && $base_url !== '/') {
                        $reset_link_path = "/" . trim($base_url, '/') . "/reset_password.php?token=" . urlencode($token);
                    }
                    $reset_link = $protocol . $domainName . $reset_link_path;


                    $to = $email;
                    $email_subject = "আপনার পাসওয়ার্ড পুনরুদ্ধার করুন - দ্বীনিলাইফ কুইজ";
                    
                    $email_body = "<html><head><meta charset='UTF-8'></head><body>"; // Added meta charset
                    $email_body .= "<p>আসসালামু আলাইকুম, " . htmlspecialchars($user_name) . ",</p>";
                    $email_body .= "<p>আপনি আপনার দ্বীনিলাইফ কুইজ একাউন্টের পাসওয়ার্ড পুনরুদ্ধারের জন্য অনুরোধ করেছেন।</p>";
                    $email_body .= "<p>আপনার পাসওয়ার্ড পুনরুদ্ধার করতে, অনুগ্রহ করে নিচের লিঙ্কে ক্লিক করুন:</p>";
                    $email_body .= "<p><a href=\"{$reset_link}\">{$reset_link}</a></p>";
                    $email_body .= "<p>এই লিঙ্কটি ১ ঘন্টার জন্য সক্রিয় থাকবে।</p>";
                    $email_body .= "<p>যদি আপনি এই অনুরোধ না করে থাকেন, তবে এই ইমেইলটি উপেক্ষা করুন।</p>";
                    $email_body .= "<p>শুভেচ্ছান্তে,<br>দ্বীনিলাইফ কুইজ টিম</p>";
                    $email_body .= "</body></html>";

                    // !!! Ensure this email is valid and authorized to send from your server !!!
                    $from_email = "admin@deenelife.com"; 
                    $from_name = "দ্বীনিলাইফ কুইজ";

                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: =?UTF-8?B?".base64_encode($from_name)."?= <" . $from_email . ">" . "\r\n";
                    $headers .= "Reply-To: " . $from_email . "\r\n";
                    // It's often good to set a Return-Path
                    // $headers .= "Return-Path: " . $from_email . "\r\n";
                    
                    $email_subject_encoded = "=?UTF-8?B?".base64_encode($email_subject)."?=";

                   error_log("--- Forgot Password Email Attempt ---");
error_log("To: " . $to);
error_log("Subject Encoded: " . $email_subject_encoded);
// error_log("Body: " . $email_body); // Body can be long, log selectively if needed
error_log("Headers: " . $headers);
error_log("Reset Link Generated: " . $reset_link);

if (mail($to, $email_subject_encoded, $email_body, $headers)) {
    // ... success logic ...
} else {
    $mail_error = error_get_last(); // Get the last error if mail() fails
    error_log("PHP mail() function FAILED for: {$to}. Last error: " . print_r($mail_error, true));
    $message = "ইমেইল পাঠাতে একটি টেকনিক্যাল সমস্যা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন অথবা সাইট এডমিনের সাথে যোগাযোগ করুন। (Error Ref: FPMAIL)";
    $message_type = "danger";
}

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Password Reset DB Error: " . $e->getMessage());
                    $message = "একটি ডাটাবেস সমস্যা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।";
                    $message_type = "danger";
                }

            } else {
                $message = "যদি আপনার প্রদত্ত ইমেইলটি আমাদের সিস্টেমে রেজিস্টার্ড থাকে, তবে পাসওয়ার্ড পুনরুদ্ধারের জন্য একটি লিঙ্ক আপনার ইমেইলে পাঠানো হয়েছে। লিঙ্কটি ১ ঘণ্টা পর্যন্ত সক্রিয় থাকবে। অনুগ্রহ করে আপনার ইনবক্স এবং স্প্যাম/জাঙ্ক ফোল্ডার চেক করুন।";
                $message_type = "info"; 
            }
            $stmt_user->close();
        } else {
            $message = "ডাটাবেস ইউজার চেক করতে সমস্যা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন।";
            $message_type = "danger";
        }
    }
}
require_once 'includes/header.php'; 
?>

<div class="auth-form">
    <h2 class="text-center mb-4">পাসওয়ার্ড পুনরুদ্ধার করুন</h2>
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php display_flash_message(); ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">আপনার রেজিস্টার্ড ইমেইল <span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">রিকোয়েস্ট পাঠান</button>
        </div>
        <p class="mt-3 text-center"><a href="login.php">লগইন পেইজে ফিরে যান</a></p>
    </form>
</div>

<?php 
if ($conn && $conn->ping()) {
    $conn->close();
}
include 'includes/footer.php'; 
?>