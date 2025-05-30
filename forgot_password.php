<?php
$page_title = "পাসওয়ার্ড পুনরুদ্ধার";
$base_url = ''; // Root directory - ensure this is correct for your setup
require_once 'includes/db_connect.php'; // Session is started here
require_once 'includes/functions.php'; 

$message = "";
$message_type = "info";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Forgot Password POST request received."); // Debug: Start of POST
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "অনুগ্রহ করে আপনার ইমেইল এড্রেস দিন।";
        $message_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "অনুগ্রহ করে একটি সঠিক ইমেইল এড্রেস দিন।";
        $message_type = "danger";
    } else {
        error_log("Email validation passed for: " . $email); // Debug: Email validated
        $sql_user = "SELECT id, name FROM users WHERE email = ?";
        if ($stmt_user = $conn->prepare($sql_user)) {
            $stmt_user->bind_param("s", $email);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();

            if ($user_data = $result_user->fetch_assoc()) {
                $user_id = $user_data['id'];
                $user_name = $user_data['name'];
                error_log("User found: ID " . $user_id . ", Name: " . $user_name); // Debug: User found

                $token = bin2hex(random_bytes(32));
                $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $conn->begin_transaction();
                try {
                    error_log("Starting database transaction for user_id: " . $user_id); // Debug
                    $sql_delete_old_tokens = "DELETE FROM password_resets WHERE user_id = ?";
                    if ($stmt_delete = $conn->prepare($sql_delete_old_tokens)) {
                        $stmt_delete->bind_param("i", $user_id);
                        $stmt_delete->execute();
                        error_log("Old tokens deleted for user_id: " . $user_id . ". Affected rows: " . $stmt_delete->affected_rows); // Debug
                        $stmt_delete->close();
                    } else {
                        throw new Exception("ডাটাবেস সমস্যা (পুরাতন টোকেন মুছতে): " . $conn->error);
                    }

                    $sql_insert_token = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
                    if ($stmt_insert = $conn->prepare($sql_insert_token)) {
                        $stmt_insert->bind_param("iss", $user_id, $hashed_token, $expires_at);
                        if (!$stmt_insert->execute()) {
                            throw new Exception("ডাটাবেস সমস্যা (নতুন টোকেন সংরক্ষণ করতে): " . $stmt_insert->error);
                        }
                        error_log("New token inserted for user_id: " . $user_id); // Debug
                        $stmt_insert->close();
                    } else {
                        throw new Exception("ডাটাবেস সমস্যা (নতুন টোকেন প্রস্তুত করতে): " . $conn->error);
                    }
                    
                    $conn->commit();
                    error_log("Database transaction committed for user_id: " . $user_id); // Debug

                    // Construct full reset link
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $domainName = $_SERVER['HTTP_HOST'];
                    
                    // Assuming reset_password.php is in the same directory as forgot_password.php
                    // If $base_url is empty or '/', this should work for root.
                    // If files are in a subdirectory, $base_url should reflect that, e.g., '/quizapp/'
                    $path_to_reset_script = "reset_password.php"; 
                    if (!empty($base_url) && $base_url !== '/' && $base_url !== './') {
                         $path_to_reset_script = rtrim($base_url, '/') . "/" . $path_to_reset_script;
                    }
                     // Ensure leading slash if not present from base_url
                    if (strpos($path_to_reset_script, '/') !== 0 && !empty($path_to_reset_script)) {
                        $path_to_reset_script = '/' . $path_to_reset_script;
                    }

                    $reset_link = $protocol . $domainName . $path_to_reset_script . "?token=" . urlencode($token);
                    error_log("Generated reset link: " . $reset_link); // Debug: Log the generated link

                    $to = $email;
                    $email_subject = "আপনার পাসওয়ার্ড পুনরুদ্ধার করুন - দ্বীনিলাইফ কুইজ";
                    
                    $email_body = "<html><head><meta charset='UTF-8'></head><body>";
                    $email_body .= "<p>আসসালামু আলাইকুম, " . htmlspecialchars($user_name) . ",</p>";
                    $email_body .= "<p>আপনি আপনার দ্বীনিলাইফ কুইজ একাউন্টের পাসওয়ার্ড পুনরুদ্ধারের জন্য অনুরোধ করেছেন।</p>";
                    $email_body .= "<p>আপনার পাসওয়ার্ড পুনরুদ্ধার করতে, অনুগ্রহ করে নিচের লিঙ্কে ক্লিক করুন:</p>";
                    $email_body .= "<p><a href=\"{$reset_link}\">{$reset_link}</a></p>";
                    $email_body .= "<p>এই লিঙ্কটি ১ ঘন্টার জন্য সক্রিয় থাকবে।</p>";
                    $email_body .= "<p>যদি আপনি এই অনুরোধ না করে থাকেন, তবে এই ইমেইলটি উপেক্ষা করুন।</p>";
                    $email_body .= "<p>শুভেচ্ছান্তে,<br>দ্বীনিলাইফ কুইজ টিম</p>";
                    $email_body .= "</body></html>";

                    $from_email = "admin@deenelife.com"; 
                    $from_name = "দ্বীনিলাইফ কুইজ"; // admin/send_email.php তে "দ্বীনিলাইফ কুইজ এডমিন" ছিল

                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: =?UTF-8?B?".base64_encode($from_name)."?= <" . $from_email . ">" . "\r\n";
                    $headers .= "Reply-To: " . $from_email . "\r\n";
                    // $headers .= "Return-Path: " . $from_email . "\r\n"; // Consider adding this
                    
                    $email_subject_encoded = "=?UTF-8?B?".base64_encode($email_subject)."?=";

                    error_log("--- Forgot Password Email Attempt ---");
                    error_log("To: " . $to);
                    error_log("Subject Encoded: " . $email_subject_encoded);
                    // error_log("Headers: " . $headers); // Headers might be too verbose for general log
                    // error_log("Body: " . $email_body); // Body can be long

                    if (mail($to, $email_subject_encoded, $email_body, $headers)) {
                        error_log("Mail sent successfully to: {$to} via forgot_password.php"); // Debug: Success
                        $message = "যদি আপনার প্রদত্ত ইমেইলটি আমাদের সিস্টেমে রেজিস্টার্ড থাকে, তবে পাসওয়ার্ড পুনরুদ্ধারের জন্য একটি লিঙ্ক আপনার ইমেইলে পাঠানো হয়েছে। লিঙ্কটি ১ ঘণ্টা পর্যন্ত সক্রিয় থাকবে। অনুগ্রহ করে আপনার ইনবক্স এবং স্প্যাম/জাঙ্ক ফোল্ডার চেক করুন।";
                        $message_type = "success";
                    } else {
                        $mail_error = error_get_last();
                        error_log("PHP mail() function FAILED for: {$to} in forgot_password.php. Last error: " . print_r($mail_error, true)); // Debug: Failure
                        $message = "ইমেইল পাঠাতে একটি টেকনিক্যাল সমস্যা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন অথবা সাইট এডমিনের সাথে যোগাযোগ করুন। (Error Ref: FPMAILFAIL)";
                        $message_type = "danger";
                    }

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Password Reset DB/General Error in forgot_password.php: " . $e->getMessage()); // Debug
                    $message = "একটি ডাটাবেস বা সাধারণ সমস্যা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন। (Error Ref: FPDBERR)";
                    $message_type = "danger";
                }

            } else {
                error_log("Email not found in database: " . $email); // Debug: Email not found
                $message = "যদি আপনার প্রদত্ত ইমেইলটি আমাদের সিস্টেমে রেজিস্টার্ড থাকে, তবে পাসওয়ার্ড পুনরুদ্ধারের জন্য একটি লিঙ্ক আপনার ইমেইলে পাঠানো হয়েছে। লিঙ্কটি ১ ঘণ্টা পর্যন্ত সক্রিয় থাকবে। অনুগ্রহ করে আপনার ইনবক্স এবং স্প্যাম/জাঙ্ক ফোল্ডার চেক করুন।";
                $message_type = "info"; 
            }
            $stmt_user->close();
        } else {
            error_log("DB prepare statement failed for user check: " . $conn->error); // Debug
            $message = "ডাটাবেস ইউজার চেক করতে সমস্যা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন। (Error Ref: FPDBPREP)";
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