<?php
$page_title = "পাসওয়ার্ড পুনরুদ্ধার";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Password recovery logic will be implemented here later.
    // This often involves sending an email with a reset link.
    // For now, just a placeholder message.
    $email = trim($_POST['email']);
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $message = "যদি আপনার প্রদত্ত ইমেইলটি আমাদের সিস্টেমে রেজিস্টার্ড থাকে, তবে পাসওয়ার্ড পুনরুদ্ধারের জন্য একটি লিঙ্ক পাঠানো হবে। (এই সুবিধাটি শীঘ্রই চালু হবে)";
    } elseif (empty($email)) {
        $message = "অনুগ্রহ করে আপনার ইমেইল এড্রেস দিন।";
    } else {
        $message = "অনুগ্রহ করে একটি সঠিক ইমেইল এড্রেস দিন।";
    }
}
?>

<div class="auth-form">
    <h2 class="text-center mb-4">পাসওয়ার্ড পুনরুদ্ধার করুন</h2>
    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-3">
            <label for="email" class="form-label">আপনার রেজিস্টার্ড ইমেইল</label>
            <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">রিকোয়েস্ট পাঠান</button>
        </div>
        <p class="mt-3 text-center"><a href="login.php">লগইন পেইজে ফিরে যান</a></p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>