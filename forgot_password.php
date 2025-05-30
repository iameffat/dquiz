<?php
$page_title = "পাসওয়ার্ড পুনরুদ্ধার";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php'; // Session is started here
require_once 'includes/functions.php'; 

$message = "";
$message_type = "info";
$show_reset_form = false;
$user_id_to_reset = null;

if (isset($_SESSION['user_id_for_reset'])) {
    // If user_id is in session, it means identity was verified, show password reset form
    $show_reset_form = true;
    $user_id_to_reset = $_SESSION['user_id_for_reset'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['verify_identity'])) {
        // Stage 1: Verify email and mobile number
        $email = trim($_POST['email']);
        $mobile_number = trim($_POST['mobile_number']);

        if (empty($email) || empty($mobile_number)) {
            $message = "অনুগ্রহ করে আপনার ইমেইল এবং মোবাইল নম্বর উভয়ই প্রদান করুন।";
            $message_type = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "অনুগ্রহ করে একটি সঠিক ইমেইল এড্রেস দিন।";
            $message_type = "danger";
        } elseif (!preg_match('/^[0-1][0-9]{10}$/', $mobile_number)) {
            $message = "সঠিক মোবাইল নম্বর লিখুন (যেমন: 01xxxxxxxxx)।";
            $message_type = "danger";
        } else {
            $sql_user = "SELECT id FROM users WHERE email = ? AND mobile_number = ?";
            if ($stmt_user = $conn->prepare($sql_user)) {
                $stmt_user->bind_param("ss", $email, $mobile_number);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();

                if ($user_data = $result_user->fetch_assoc()) {
                    $user_id_to_reset = $user_data['id'];
                    $_SESSION['user_id_for_reset'] = $user_id_to_reset; // Store user_id in session
                    $show_reset_form = true; // Show the password reset form
                    $message = "আপনার তথ্য যাচাই করা হয়েছে। এখন নতুন পাসওয়ার্ড সেট করুন।";
                    $message_type = "success";
                } else {
                    $message = "প্রদত্ত ইমেইল এবং মোবাইল নম্বরের কোনো সমন্বয় খুঁজে পাওয়া যায়নি।";
                    $message_type = "danger";
                }
                $stmt_user->close();
            } else {
                $message = "ডাটাবেস সমস্যা (ব্যবহারকারী যাচাই)। অনুগ্রহ করে পরে আবার চেষ্টা করুন।";
                $message_type = "danger";
            }
        }
    } elseif (isset($_POST['reset_password']) && isset($_SESSION['user_id_for_reset'])) {
        // Stage 2: Reset the password
        $user_id_to_reset = $_SESSION['user_id_for_reset'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_password) || empty($confirm_password)) {
            $message = "অনুগ্রহ করে নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড উভয়ই লিখুন।";
            $message_type = "danger";
            $show_reset_form = true; // Keep showing reset form
        } elseif (strlen($new_password) < 6) {
            $message = "নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।";
            $message_type = "danger";
            $show_reset_form = true; // Keep showing reset form
        } elseif ($new_password !== $confirm_password) {
            $message = "নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলেনি।";
            $message_type = "danger";
            $show_reset_form = true; // Keep showing reset form
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update_pass = "UPDATE users SET password = ? WHERE id = ?";
            if ($stmt_update = $conn->prepare($sql_update_pass)) {
                $stmt_update->bind_param("si", $hashed_password, $user_id_to_reset);
                if ($stmt_update->execute()) {
                    unset($_SESSION['user_id_for_reset']); // Clear session variable
                    $_SESSION['flash_message'] = "আপনার পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে। আপনি এখন লগইন করতে পারেন।";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: login.php");
                    exit;
                } else {
                    $message = "পাসওয়ার্ড আপডেট করতে সমস্যা হয়েছে।";
                    $message_type = "danger";
                    $show_reset_form = true; // Keep showing reset form
                }
                $stmt_update->close();
            } else {
                $message = "ডাটাবেস সমস্যা (পাসওয়ার্ড আপডেট)।";
                $message_type = "danger";
                $show_reset_form = true; // Keep showing reset form
            }
        }
    } else {
         // If user_id_for_reset is not in session but reset_password was attempted (e.g. session expired or direct access)
        if (isset($_POST['reset_password']) && !isset($_SESSION['user_id_for_reset'])) {
            $message = "সেশন মেয়াদ উত্তীর্ণ বা অবৈধ অনুরোধ। অনুগ্রহ করে আবার আপনার তথ্য যাচাই করুন।";
            $message_type = "warning";
            $show_reset_form = false; // Go back to identity verification
            unset($_SESSION['user_id_for_reset']);
        }
    }
}

require_once 'includes/header.php'; 
?>

<div class="auth-form">
    <h2 class="text-center mb-4">পাসওয়ার্ড পুনরুদ্ধার করুন</h2>

    <?php if (!empty($message) && !$show_reset_form && isset($_POST['verify_identity'])): // Show identity verification errors only if reset form is not shown yet ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo $message; ?></div>
    <?php elseif (!empty($message) && $show_reset_form && isset($_POST['reset_password'])): // Show password reset errors ?>
         <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo $message; ?></div>
    <?php elseif (!empty($message) && $show_reset_form && isset($_POST['verify_identity']) && $message_type == 'success'): // Show identity verification success ?>
         <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php display_flash_message(); ?>


    <?php if (!$show_reset_form): ?>
    <p class="text-center text-muted">আপনার অ্যাকাউন্টের সাথে যুক্ত ইমেইল এবং মোবাইল নম্বর প্রদান করুন।</p>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">আপনার রেজিস্টার্ড ইমেইল <span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control" required value="<?php echo isset($_POST['email']) && !$show_reset_form ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <div class="mb-3">
            <label for="mobile_number" class="form-label">আপনার রেজিস্টার্ড মোবাইল নম্বর <span class="text-danger">*</span></label>
            <input type="tel" name="mobile_number" id="mobile_number" class="form-control" placeholder="01xxxxxxxxx" required value="<?php echo isset($_POST['mobile_number']) && !$show_reset_form ? htmlspecialchars($_POST['mobile_number']) : ''; ?>">
        </div>
        <div class="d-grid">
            <button type="submit" name="verify_identity" class="btn btn-primary">তথ্য যাচাই করুন</button>
        </div>
    </form>
    <?php else: ?>
    <p class="text-center text-muted">অনুগ্রহ করে আপনার নতুন পাসওয়ার্ড সেট করুন।</p>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
        <input type="hidden" name="user_id_to_reset" value="<?php echo htmlspecialchars($user_id_to_reset); ?>">
        <div class="mb-3">
            <label for="new_password" class="form-label">নতুন পাসওয়ার্ড <span class="text-danger">*</span></label>
            <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($message) && isset($_POST['reset_password'])) ? 'is-invalid' : ''; ?>" required>
            <small class="form-text text-muted">কমপক্ষে ৬ অক্ষরের হতে হবে।</small>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">নতুন পাসওয়ার্ড নিশ্চিত করুন <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($message) && isset($_POST['reset_password'])) ? 'is-invalid' : ''; ?>" required>
        </div>
        <div class="d-grid">
            <button type="submit" name="reset_password" class="btn btn-success">পাসওয়ার্ড পরিবর্তন করুন</button>
        </div>
    </form>
    <?php endif; ?>

    <p class="mt-3 text-center"><a href="login.php">লগইন পেইজে ফিরে যান</a></p>
</div>

<?php 
if ($conn && $conn->ping()) {
    $conn->close();
}
include 'includes/footer.php'; 
?>