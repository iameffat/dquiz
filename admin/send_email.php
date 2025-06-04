<?php
$page_title = "ইমেইল পাঠান"; // ডিফল্ট টাইটেল
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$email_target_type = ''; // 'single' or 'all'
$user_email_to_display = ""; 
$user_name_to_display = "";  
$user_id_to = 0;             
$feedback_message = "";
$message_type = "";
$posted_email_subject = 'দ্বীনিলাইফ কুইজ প্রতিযোগিতা প্রসঙ্গে'; 
$default_greeting = "প্রিয় প্রতিযোগী,\n\n";
$posted_email_message = $default_greeting . "আপনার মূল্যবান অংশগ্রহণের জন্য ধন্যবাদ। \n\n"; 

$from_email_address = get_site_setting('site_from_email', 'admin@deenelife.com'); 
$from_name = get_site_setting('site_from_name', 'দ্বীনিলাইফ কুইজ এডমিন'); 


if (isset($_GET['target']) && $_GET['target'] == 'all_users') {
    $email_target_type = 'all';
    $page_title = "সকল ইউজারকে ইমেইল পাঠান";
    $user_email_to_display = "সকল রেজিস্টার্ড ও সক্রিয় ইউজার";
    $posted_email_message = "প্রিয় [user_name],\n\nবিষয়টি সম্পর্কে আপনাকে জানানো যাচ্ছে যে...\n\nশুভেচ্ছান্তে,\n" . $from_name; // সকল ইউজারের জন্য বার্তা টেমপ্লেট

} elseif (isset($_GET['user_id'])) {
    $email_target_type = 'single';
    $user_id_to = intval($_GET['user_id']);
    if ($user_id_to > 0) {
        $sql_user = "SELECT name, email FROM users WHERE id = ?";
        if ($stmt_user = $conn->prepare($sql_user)) {
            $stmt_user->bind_param("i", $user_id_to);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if ($user_data = $result_user->fetch_assoc()) {
                $user_email_to_display = $user_data['email'];
                $user_name_to_display = $user_data['name'];
                $page_title = "ইমেইল পাঠান: " . htmlspecialchars($user_name_to_display);
                $posted_email_message = "প্রিয় " . htmlspecialchars($user_name_to_display) . ",\n\nআপনার মূল্যবান অংশগ্রহণের জন্য ধন্যবাদ। \n\nশুভেচ্ছান্তে,\n" . $from_name; 
            } else {
                $_SESSION['flash_message'] = "ইউজার খুঁজে পাওয়া যায়নি।";
                $_SESSION['flash_message_type'] = "danger";
                header("Location: manage_users.php");
                exit;
            }
            $stmt_user->close();
        } else {
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
    // POST request for sending email
    $email_target_type_from_post = $_POST['email_target_type']; 
    $posted_email_subject = trim($_POST['email_subject']);
    $posted_email_message_template = trim($_POST['email_message']); 

    $errors = [];
    if (empty($posted_email_subject)) {
        $errors[] = "ইমেইলের বিষয় আবশ্যক।";
    }
    if (empty($posted_email_message_template)) {
        $errors[] = "ইমেইলের বার্তা আবশ্যক।";
    }

    if ($email_target_type_from_post === 'single') {
        $user_id_to = intval($_POST['user_id_to']); // POST থেকে আইডি নিন
        $user_email_to_display_post = trim($_POST['user_email_to_display']); // POST থেকে ইমেইল নিন
        $user_name_to_display_post = trim($_POST['user_name_to_display']); // POST থেকে নাম নিন
        if (empty($user_email_to_display_post) || !filter_var($user_email_to_display_post, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "গ্রহীতার ইমেইল সঠিক নয়।";
        }
        // Ensure display variables are set for form re-population on error
        $user_email_to_display = $user_email_to_display_post;
        $user_name_to_display = $user_name_to_display_post;
        $page_title = "ইমেইল পাঠান: " . htmlspecialchars($user_name_to_display);
        $email_target_type = 'single'; // Ensure this is set for form display
    } elseif ($email_target_type_from_post === 'all') {
        $email_target_type = 'all';
        $page_title = "সকল ইউজারকে ইমেইল পাঠান";
        $user_email_to_display = "সকল রেজিস্টার্ড ও সক্রিয় ইউজার";
    }
    $posted_email_message = $posted_email_message_template; // Preserve typed message on error

    if (empty($errors)) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: =?UTF-8?B?".base64_encode($from_name)."?= <" . $from_email_address . ">" . "\r\n";
        $reply_to_email = get_site_setting('site_reply_to_email', $from_email_address);
        $headers .= "Reply-To: " . $reply_to_email . "\r\n";
        $email_subject_encoded = "=?UTF-8?B?".base64_encode($posted_email_subject)."?=";

        if ($email_target_type_from_post === 'single') {
            $final_email_message = "<html><body>";
            $final_email_message .= "<p>আসসালামু আলাইকুম ওয়া রাহমাতুল্লাহ, " . htmlspecialchars($user_name_to_display) . ",</p>";
            $final_email_message .= nl2br(htmlspecialchars($posted_email_message_template));
            // Ensure "শুভেচ্ছান্তে" is not duplicated if already in template
            if (strpos(strtolower($posted_email_message_template), 'শুভেচ্ছান্তে') === false && strpos(strtolower($posted_email_message_template), 'ধন্যবাদান্তে') === false ) {
                $final_email_message .= "<p>শুভেচ্ছান্তে,<br>" . htmlspecialchars($from_name) . "</p>";
            }
            $final_email_message .= "</body></html>";

            if (mail($user_email_to_display, $email_subject_encoded, $final_email_message, $headers)) {
                $_SESSION['flash_message'] = "ইমেইল সফলভাবে " . htmlspecialchars($user_email_to_display) . " এই ঠিকানায় পাঠানো হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
                header("Location: manage_users.php");
                exit;
            } else {
                $feedback_message = "ইমেইল পাঠাতে সমস্যা হয়েছে। সার্ভার কনফিগারেশন অথবা 'From' ইমেইল এড্রেসটি সঠিক নাও হতে পারে।";
                $message_type = "danger";
            }
        } elseif ($email_target_type_from_post === 'all') {
            $sql_all_users = "SELECT name, email FROM users WHERE role = 'user' AND is_banned = 0";
            $result_all_users = $conn->query($sql_all_users);
            $sent_count = 0;
            $failed_count = 0;
            $failed_emails = [];

            if ($result_all_users && $result_all_users->num_rows > 0) {
                while ($user_row = $result_all_users->fetch_assoc()) {
                    $user_specific_name = $user_row['name'];
                    $user_specific_email = $user_row['email'];

                    $personalized_message_body = str_replace('[user_name]', htmlspecialchars($user_specific_name), $posted_email_message_template);
                    
                    $final_email_message = "<html><body style='font-family: Noto Sans Bengali, sans-serif;'>";
                    if (strpos($posted_email_message_template, "[user_name]") === false && strpos($posted_email_message_template, $user_specific_name) === false) {
                         $final_email_message .= "<p>আসসালামু আলাইকুম ওয়া রাহমাতুল্লাহ, " . htmlspecialchars($user_specific_name) . ",</p>";
                    }
                    $final_email_message .= nl2br(htmlspecialchars($personalized_message_body));
                     // Ensure "শুভেচ্ছান্তে" is not duplicated if already in template
                    if (strpos(strtolower($personalized_message_body), 'শুভেচ্ছান্তে') === false && strpos(strtolower($personalized_message_body), 'ধন্যবাদান্তে') === false ) {
                        $final_email_message .= "<p style='margin-top: 20px;'>শুভেচ্ছান্তে,<br>" . htmlspecialchars($from_name) . "</p>";
                    }
                    $final_email_message .= "</body></html>";

                    if (mail($user_specific_email, $email_subject_encoded, $final_email_message, $headers)) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                        $failed_emails[] = $user_specific_email;
                        error_log("Failed to send bulk email to: " . $user_specific_email);
                    }
                    // usleep(500000); // 0.5 সেকেন্ড বিরতি (যদি প্রয়োজন হয়)
                }
                $flash_msg = $sent_count . " টি ইমেইল সফলভাবে পাঠানোর জন্য চেষ্টা করা হয়েছে। ";
                if ($failed_count > 0) {
                    $flash_msg .= $failed_count . "টি পাঠাতে সমস্যা হয়েছে।";
                    // আপনি চাইলে এখানে ব্যর্থ ইমেইলগুলোও দেখাতে পারেন, তবে অনেক বেশি হলে সমস্যা হতে পারে
                    // $flash_msg .= " ব্যর্থ ইমেইল: " . implode(', ', $failed_emails);
                }
                $_SESSION['flash_message'] = $flash_msg;
                $_SESSION['flash_message_type'] = ($failed_count > 0 && $sent_count > 0) ? "warning" : ($failed_count > 0 ? "danger" : "success");
            } else {
                $_SESSION['flash_message'] = "কোনো সক্রিয় ইউজার খুঁজে পাওয়া যায়নি।";
                $_SESSION['flash_message_type'] = "info";
            }
            header("Location: manage_users.php");
            exit;
        }
    } else {
        $feedback_message = "ত্রুটি: <br>" . implode("<br>", $errors);
        $message_type = "danger";
    }

} elseif (empty($email_target_type)) {
    $_SESSION['flash_message'] = "সরাসরি এই পেইজে আসা যাবে না অথবা ডেটা সঠিকভাবে প্রদান করা হয়নি।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: manage_users.php");
    exit;
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo htmlspecialchars($page_title); ?></h1>
    <?php if ($email_target_type === 'single' && (!empty($user_name_to_display) || !empty($user_email_to_display))) : ?>
    <p>ব্যবহারকারী: <strong><?php echo htmlspecialchars($user_name_to_display); ?></strong> (<?php echo htmlspecialchars($user_email_to_display); ?>)</p>
    <?php elseif ($email_target_type === 'all'): ?>
    <p>প্রাপক: <strong><?php echo htmlspecialchars($user_email_to_display); ?></strong></p>
    <div class="alert alert-info small">
        আপনি সকল রেজিস্টার্ড এবং সক্রিয় (নিষিদ্ধ নন এবং অ্যাডমিন নন এমন) ইউজারদের ইমেইল পাঠাচ্ছেন। বার্তার শুরুতে বা মাঝে `[user_name]` ব্যবহার করলে সেখানে প্রতিটি ইউজারের নাম স্বয়ংক্রিয়ভাবে বসে যাবে। যদি আপনি `[user_name]` ব্যবহার না করেন, তাহলে বার্তার শুরুতে স্বয়ংক্রিয়ভাবে "প্রিয় [ইউজারের নাম]," যুক্ত হবে।
    </div>
    <?php endif; ?>


    <?php if (!empty($feedback_message)): ?>
    <div class="alert alert-<?php echo $message_type ?: "danger"; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <?php display_flash_message(); ?>


    <div class="card">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="email_target_type" value="<?php echo $email_target_type; ?>">
                <?php if ($email_target_type === 'single'): ?>
                    <input type="hidden" name="user_id_to" value="<?php echo $user_id_to; ?>">
                    <input type="hidden" name="user_name_to_display" value="<?php echo htmlspecialchars($user_name_to_display); ?>">
                    <div class="mb-3">
                        <label for="recipient_email_display_field" class="form-label">প্রাপক:</label>
                        <input type="email" class="form-control" id="recipient_email_display_field" name="user_email_to_display" value="<?php echo htmlspecialchars($user_email_to_display); ?>" <?php echo ($email_target_type === 'single' && isset($_GET['user_id'])) ? 'readonly' : ''; ?>>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="email_subject" class="form-label">বিষয় <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="email_subject" name="email_subject" value="<?php echo htmlspecialchars($posted_email_subject); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email_message" class="form-label">বার্তা <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="email_message" name="email_message" rows="10" required><?php echo htmlspecialchars($posted_email_message); ?></textarea>
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