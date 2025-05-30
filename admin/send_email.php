<?php
$page_title = "ইমেইল পাঠান";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$user_email_to = "";
$user_name_to = "";
$user_id_to = 0;
$feedback_message = "";
$message_type = "";
$posted_email_subject = 'দ্বীনিলাইফ কুইজ প্রতিযোগিতা প্রসঙ্গে'; // Default subject
$posted_email_message = "প্রিয় প্রতিযোগী,\n\nআপনার অংশগ্রহণের জন্য ধন্যবাদ। \n\n"; // Default message

if (isset($_GET['user_id'])) {
    $user_id_to = intval($_GET['user_id']);
    if ($user_id_to > 0) {
        $sql_user = "SELECT name, email FROM users WHERE id = ?";
        if ($stmt_user = $conn->prepare($sql_user)) {
            $stmt_user->bind_param("i", $user_id_to);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if ($user_data = $result_user->fetch_assoc()) {
                $user_email_to = $user_data['email'];
                $user_name_to = $user_data['name'];
            } else {
                $_SESSION['flash_message'] = "ইউজার খুঁজে পাওয়া যায়নি।";
                $_SESSION['flash_message_type'] = "danger";
                header("Location: manage_users.php");
                exit;
            }
            $stmt_user->close();
        } else {
            // This error will be shown on the page if header.php is already included
            // If not, it might cause a "headers already sent" warning if we try to redirect later.
            // For simplicity here, we'll let it be displayed via the $feedback_message.
            $feedback_message = "ডাটাবেস সমস্যা: ইউজার তথ্য আনতে সমস্যা হয়েছে।";
            $message_type = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "অবৈধ ইউজার আইডি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: manage_users.php");
        exit;
    }
} elseif (isset($_POST['send_actual_email'])) {
    // Process email sending
    $user_id_to = intval($_POST['user_id_to']); // Get user_id from hidden field
    $user_email_to = trim($_POST['user_email_to']);
    $user_name_to = trim($_POST['user_name_to']); // Get name from hidden field
    $posted_email_subject = trim($_POST['email_subject']); // Use posted subject
    $posted_email_message = trim($_POST['email_message']); // Use posted message
    
    // --- Define From email and name ---
    // !!! গুরুত্বপূর্ণ: এই ইমেইল এড্রেসটি আপনার ডোমেইনের এবং হোস্টিং-এ ভেরিফাইড হতে হবে !!!
    $from_email = "admin@deenelife.com"; // আপনার ওয়েবসাইটের অ্যাডমিন ইমেইল দিন
    $from_name = "দ্বীনিলাইফ কুইজ এডমিন";

    $errors = [];
    if (empty($user_email_to) || !filter_var($user_email_to, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "গ্রহীতার ইমেইল সঠিক নয়।";
    }
    if (empty($posted_email_subject)) {
        $errors[] = "ইমেইলের বিষয় আবশ্যক।";
    }
    if (empty($posted_email_message)) {
        $errors[] = "ইমেইলের বার্তা আবশ্যক।";
    }

    if (empty($errors)) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        // From হেডার ( প্রেরকের নাম UTF-8 সাপোর্ট সহ)
        $headers .= "From: =?UTF-8?B?".base64_encode($from_name)."?= <" . $from_email . ">" . "\r\n";
        // Reply-To হেডার
        $headers .= "Reply-To: admin@deenelife.com" . "\r\n";

        // ইমেইলের বিষয় (Subject) UTF-8 সাপোর্ট সহ
        $email_subject_encoded = "=?UTF-8?B?".base64_encode($posted_email_subject)."?=";

        $full_message = "<html><body>";
        $full_message .= "<p>আসসালামু আলাইকুম ওয়া রাহমাতুল্লাহ, " . htmlspecialchars($user_name_to) . ",</p>";
        $full_message .= nl2br(htmlspecialchars($posted_email_message)); // nl2br converts newlines to <br>
        $full_message .= "<p>শুভেচ্ছান্তে,<br>" . htmlspecialchars($from_name) . "</p>";
        $full_message .= "</body></html>";

        if (mail($user_email_to, $email_subject_encoded, $full_message, $headers)) {
            $_SESSION['flash_message'] = "ইমেইল সফলভাবে " . htmlspecialchars($user_email_to) . " এই ঠিকানায় পাঠানো হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
            header("Location: manage_users.php"); // ইমেইল পাঠানোর পর ইউজার ম্যানেজমেন্ট পেইজে ফিরে যান
            exit;
        } else {
            $feedback_message = "ইমেইল পাঠাতে সমস্যা হয়েছে। আপনার সার্ভার সঠিকভাবে কনফিগার করা নাও থাকতে পারে অথবা \"From\" ইমেইল এড্রেসটি আপনার ডোমেইনের জন্য অনুমোদিত নয়।";
            $message_type = "danger";
        }
    } else {
        $feedback_message = "ত্রুটি: <br>" . implode("<br>", $errors);
        $message_type = "danger";
    }

} else {
    // If neither GET with user_id nor POST with send_actual_email, redirect.
    $_SESSION['flash_message'] = "সরাসরি এই পেইজে আসা যাবে না অথবা ডেটা সঠিকভাবে প্রদান করা হয়নি।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: manage_users.php");
    exit;
}


require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4">ব্যবহারকারীকে ইমেইল পাঠান</h1>
    <?php if (!empty($user_name_to) || !empty($user_email_to)) : ?>
    <p>ব্যবহারকারী: <strong><?php echo htmlspecialchars($user_name_to); ?></strong> (<?php echo htmlspecialchars($user_email_to); ?>)</p>
    <?php endif; ?>

    <?php if ($feedback_message): ?>
    <div class="alert alert-<?php echo $message_type === "success" ? "success" : ($message_type ?: "danger"); ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback_message; // feedback_message is already HTML if it contains <br> from errors array, otherwise it's plain text. Both are fine here. ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="user_id_to" value="<?php echo $user_id_to; ?>">
                <input type="hidden" name="user_email_to" value="<?php echo htmlspecialchars($user_email_to); ?>">
                <input type="hidden" name="user_name_to" value="<?php echo htmlspecialchars($user_name_to); ?>">

                <div class="mb-3">
                    <label for="recipient_email_display" class="form-label">প্রাপক:</label>
                    <input type="email" class="form-control" id="recipient_email_display" value="<?php echo htmlspecialchars($user_email_to); ?>" readonly disabled>
                </div>

                <div class="mb-3">
                    <label for="email_subject" class="form-label">বিষয় <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="email_subject" name="email_subject" value="<?php echo htmlspecialchars($posted_email_subject); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email_message" class="form-label">বার্তা <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="email_message" name="email_message" rows="8" required><?php echo htmlspecialchars($posted_email_message); ?></textarea>
                    <small class="form-text text-muted">এই বার্তাটি HTML ফরম্যাটে পাঠানো হবে। আপনি সাধারণ টেক্সট লিখতে পারেন, নতুন লাইন স্বয়ংক্রিয়ভাবে &lt;br&gt; ট্যাগে রূপান্তরিত হবে।</small>
                </div>
                
                <button type="submit" name="send_actual_email" class="btn btn-primary">ইমেইল পাঠান</button>
                <a href="manage_users.php" class="btn btn-secondary">বাতিল করুন</a>
            </form>
        </div>
    </div>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>