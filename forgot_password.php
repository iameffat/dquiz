<?php
$page_title = "পাসওয়ার্ড পুনরুদ্ধার";
$base_url = ''; // Root directory
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
        // Check if email exists in the users table
        $sql_user = "SELECT id FROM users WHERE email = ?";
        if ($stmt_user = $conn->prepare($sql_user)) {
            $stmt_user->bind_param("s", $email);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();

            if ($user_data = $result_user->fetch_assoc()) {
                $user_id = $user_data['id'];

                // Generate a unique token
                $token = bin2hex(random_bytes(32)); // Plain token for the URL
                $hashed_token = password_hash($token, PASSWORD_DEFAULT); // Hashed token for DB
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

                // Database operations:
                // 1. Delete any existing old tokens for this user
                // 2. Insert the new token
                // IMPORTANT: Ensure you have a 'password_resets' table as described above.
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

                    // Send email
                    // !!! গুরুত্বপূর্ণ: "yourdomain.com" পরিবর্তন করে আপনার আসল ডোমেইন দিন !!!
                    // !!! এবং $from_email আপনার ডোমেইনের ভেরিফাইড ইমেইল এড্রেস হতে হবে !!!
                    $reset_link = $base_url . "reset_password.php?token=" . urlencode($token);
                    // Ensure $base_url is correctly configured or construct full URL:
                    // $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    // $domainName = $_SERVER['HTTP_HOST'];
                    // $reset_link = $protocol . $domainName . $base_url . "reset_password.php?token=" . urlencode($token);


                    $to = $email;
                    $email_subject = "আপনার পাসওয়ার্ড পুনরুদ্ধার করুন - দ্বীনিলাইফ কুইজ";
                    
                    $email_body = "<html><body>";
                    $email_body .= "<p>আসসালামু আলাইকুম,</p>";
                    $email_body .= "<p>আপনি আপনার দ্বীনিলাইফ কুইজ একাউন্টের পাসওয়ার্ড পুনরুদ্ধারের জন্য অনুরোধ করেছেন।</p>";
                    $email_body .= "<p>আপনার পাসওয়ার্ড পুনরুদ্ধার করতে, অনুগ্রহ করে নিচের লিঙ্কে ক্লিক করুন:</p>";
                    $email_body .= "<p><a href=\"{$reset_link}\">{$reset_link}</a></p>";
                    $email_body .= "<p>এই লিঙ্কটি ১ ঘন্টার জন্য সক্রিয় থাকবে।</p>";
                    $email_body .= "<p>যদি আপনি এই অনুরোধ না করে থাকেন, তবে এই ইমেইলটি উপেক্ষা করুন।</p>";
                    $email_body .= "<p>শুভেচ্ছান্তে,<br>দ্বীনিলাইফ কুইজ টিম</p>";
                    $email_body .= "</body></html>";

                    $from_email = "admin@deenelife.com"; // আপনার ওয়েবসাইটের অ্যাডমিন ইমেইল দিন
                    $from_name = "দ্বীনিলাইফ কুইজ";

                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: =?UTF-8?B?".base64_encode($from_name)."?= <" . $from_email . ">" . "\r\n";
                    $headers .= "Reply-To: " . $from_email . "\r\n";
                    
                    $email_subject_encoded = "=?UTF-8?B?".base64_encode($email_subject)."?=";

                    if (mail($to, $email_subject_encoded, $email_body, $headers)) {
                        $message = "যদি আপনার প্রদত্ত ইমেইলটি আমাদের সিস্টেমে রেজিস্টার্ড থাকে, তবে পাসওয়ার্ড পুনরুদ্ধারের জন্য একটি লিঙ্ক আপনার ইমেইলে পাঠানো হয়েছে। লিঙ্কটি ১ ঘণ্টা পর্যন্ত সক্রিয় থাকবে।";
                        $message_type = "success";
                    } else {
                        error_log("Mail failed to send to: {$to}");
                        $message = "ইমেইল পাঠাতে সমস্যা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন অথবা এডমিনের সাথে যোগাযোগ করুন।";
                        $message_type = "danger";
                    }

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Password Reset Error: " . $e->getMessage());
                    // Show generic message to user even on DB error for security
                    $message = "একটি সমস্যা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।";
                    $message_type = "danger";
                }

            } else {
                // Email not found in DB - show generic message to prevent email enumeration
                 $message = "যদি আপনার প্রদত্ত ইমেইলটি আমাদের সিস্টেমে রেজিস্টার্ড থাকে, তবে পাসওয়ার্ড পুনরুদ্ধারের জন্য একটি লিঙ্ক আপনার ইমেইলে পাঠানো হয়েছে। লিঙ্কটি ১ ঘণ্টা পর্যন্ত সক্রিয় থাকবে।";
                $message_type = "info"; // Or 'success' to be consistent
            }
            $stmt_user->close();
        } else {
            // DB error during user check
            $message = "ডাটাবেস সমস্যা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন।";
            $message_type = "danger";
        }
    }
}
require_once 'includes/header.php'; // Header include
?>

<div class="auth-form">
    <h2 class="text-center mb-4">পাসওয়ার্ড পুনরুদ্ধার করুন</h2>
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">আপনার রেজিস্টার্ড ইমেইল <span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">রিকোয়েস্ট পাঠান</button>
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