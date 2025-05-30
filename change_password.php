<?php
$page_title = "পাসওয়ার্ড পরিবর্তন করুন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['redirect_url_user'] = 'change_password.php'; // Redirect back here after login
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$current_password = "";
$new_password = "";
$confirm_new_password = "";
$errors = [];
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password_submit'])) {
    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    $confirm_new_password = $_POST["confirm_new_password"];

    // Validate current password
    if (empty($current_password)) {
        $errors['current_password'] = "আপনার বর্তমান পাসওয়ার্ড লিখুন।";
    }

    // Validate new password
    if (empty($new_password)) {
        $errors['new_password'] = "নতুন পাসওয়ার্ড লিখুন।";
    } elseif (strlen($new_password) < 6) {
        $errors['new_password'] = "নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।";
    }

    // Validate confirm new password
    if (empty($confirm_new_password)) {
        $errors['confirm_new_password'] = "নতুন পাসওয়ার্ডটি পুনরায় লিখুন।";
    } elseif ($new_password != $confirm_new_password) {
        $errors['confirm_new_password'] = "নতুন পাসওয়ার্ড দুটি মিলেনি।";
    }
    
    // If new password is same as current password
    if (!empty($new_password) && $new_password === $current_password && empty($errors['new_password'])) { // Check only if new_password itself is valid
        $errors['new_password'] = "নতুন পাসওয়ার্ড বর্তমান পাসওয়ার্ডের থেকে ভিন্ন হতে হবে।";
    }


    if (empty($errors)) {
        // Verify current password from database
        $sql_verify_pass = "SELECT password FROM users WHERE id = ?";
        if ($stmt_verify = $conn->prepare($sql_verify_pass)) {
            $stmt_verify->bind_param("i", $user_id);
            if ($stmt_verify->execute()) {
                $stmt_verify->store_result();
                if ($stmt_verify->num_rows == 1) {
                    $stmt_verify->bind_result($hashed_current_password_db);
                    $stmt_verify->fetch();
                    if (password_verify($current_password, $hashed_current_password_db)) {
                        // Current password is correct, proceed to update
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql_update_pass = "UPDATE users SET password = ? WHERE id = ?";
                        if ($stmt_update = $conn->prepare($sql_update_pass)) {
                            $stmt_update->bind_param("si", $hashed_new_password, $user_id);
                            if ($stmt_update->execute()) {
                                $_SESSION['flash_message'] = "আপনার পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।";
                                $_SESSION['flash_message_type'] = "success";
                                // For security, it's a good practice to log the user out or destroy other sessions.
                                // For simplicity here, we are just redirecting to profile.
                                header("Location: profile.php");
                                exit;
                            } else {
                                $errors['db'] = "পাসওয়ার্ড আপডেট করতে সমস্যা হয়েছে: " . $stmt_update->error;
                            }
                            $stmt_update->close();
                        } else {
                            $errors['db'] = "ডাটাবেস সমস্যা (পাসওয়ার্ড আপডেট প্রস্তুতি): " . $conn->error;
                        }
                    } else {
                        $errors['current_password'] = "আপনার বর্তমান পাসওয়ার্ড সঠিক নয়।";
                    }
                } else {
                    // This should not happen if user is logged in with a valid user_id
                    $errors['db'] = "ইউজার খুঁজে পাওয়া যায়নি।";
                }
            } else {
                $errors['db'] = "বর্তমান পাসওয়ার্ড যাচাই করতে সমস্যা হয়েছে।";
            }
            $stmt_verify->close();
        } else {
            $errors['db'] = "ডাটাবেস সমস্যা (পাসওয়ার্ড যাচাই প্রস্তুতি): " . $conn->error;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h3 class="mb-0">পাসওয়ার্ড পরিবর্তন করুন</h3>
                </div>
                <div class="card-body p-4">
                    <?php display_flash_message(); // For general flash messages, e.g., from redirects ?>
                     <?php if (!empty($success_message)): // Although success leads to redirect, kept for consistency ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors['db'])): ?>
                        <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">বর্তমান পাসওয়ার্ড <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" id="current_password" class="form-control <?php echo (!empty($errors['current_password'])) ? 'is-invalid' : ''; ?>" required>
                            <?php if (!empty($errors['current_password'])): ?><div class="invalid-feedback"><?php echo $errors['current_password']; ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">নতুন পাসওয়ার্ড <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($errors['new_password'])) ? 'is-invalid' : ''; ?>" required>
                            <small class="form-text text-muted">কমপক্ষে ৬ অক্ষরের হতে হবে।</small>
                            <?php if (!empty($errors['new_password'])): ?><div class="invalid-feedback"><?php echo $errors['new_password']; ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_new_password" class="form-label">নতুন পাসওয়ার্ড নিশ্চিত করুন <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control <?php echo (!empty($errors['confirm_new_password'])) ? 'is-invalid' : ''; ?>" required>
                            <?php if (!empty($errors['confirm_new_password'])): ?><div class="invalid-feedback"><?php echo $errors['confirm_new_password']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="change_password_submit" class="btn btn-primary">পাসওয়ার্ড পরিবর্তন করুন</button>
                            <a href="profile.php" class="btn btn-outline-secondary">প্রোফাইলে ফিরে যান</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>