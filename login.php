<?php
// public_html/login.php

$page_title = "লগইন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php'; // Ensure session is started here
require_once 'includes/functions.php';

// If a redirect GET parameter is present, store it. This takes precedence.
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_url_user'] = urldecode($_GET['redirect']);
}

// If user is already logged in, redirect them
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION['redirect_url_user'])) {
        $redirect_url = $_SESSION['redirect_url_user'];
        unset($_SESSION['redirect_url_user']); // Clear the stored URL
        header("location: " . $redirect_url);
        exit;
    }
    header("location: profile.php"); // Default redirect if already logged in
    exit;
}

$login_identifier = $password = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_identifier = trim($_POST["login_identifier"]); // Can be email or mobile
    $password = $_POST["password"];

    if (empty($login_identifier)) {
        $errors['login_identifier'] = "আপনার ইমেইল অথবা মোবাইল নম্বর লিখুন।";
    }
    if (empty($password)) {
        $errors['password'] = "আপনার পাসওয়ার্ড লিখুন।";
    }

    // CAPTCHA Validation - Placed after basic non-empty checks for required fields.
    if (empty($errors)) { // Only proceed if identifier and password are not empty
        if (isset($_POST['cf-turnstile-response']) && !empty($_POST['cf-turnstile-response'])) {
            $turnstile_token = $_POST['cf-turnstile-response'];
            // গুরুত্বপূর্ণ: আপনার আসল Secret Key এখানে ব্যবহার করুন
            $secret_key = '0x4AAAAAABfuh_4bXftQJeiM0UhI6HVZ8GM'; 
            
            $verification_result = verify_cloudflare_turnstile($turnstile_token, $secret_key);

            if (!$verification_result['success']) {
                $errors['captcha'] = $verification_result['error_message'] ?: "ক্যাপচা যাচাই ব্যর্থ হয়েছে।";
            }
        } else {
            $errors['captcha'] = "অনুগ্রহ করে ক্যাপচাটি সম্পন্ন করুন।";
        }
    }

    if (empty($errors)) { // This now checks all previous errors including captcha
        // Check if identifier is email or mobile
        $field_type = filter_var($login_identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile_number';

        $sql = "SELECT id, name, email, mobile_number, password, role FROM users WHERE $field_type = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_identifier);
            $param_identifier = $login_identifier;

            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $name, $db_email, $db_mobile, $hashed_password, $role);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["name"] = $name;
                            $_SESSION["email"] = $db_email; 
                            $_SESSION["mobile_number"] = $db_mobile; 
                            $_SESSION["role"] = $role;
                            
                            if (isset($_SESSION['redirect_url_user'])) {
                                $redirect_url = $_SESSION['redirect_url_user'];
                                unset($_SESSION['redirect_url_user']); 
                                header("location: " . $redirect_url);
                                exit;
                            } else {
                                header("location: profile.php"); 
                                exit;
                            }
                        } else {
                            $errors['login'] = "পাসওয়ার্ড সঠিক নয়।";
                        }
                    }
                } else {
                    $errors['login'] = "এই ".$field_type." দিয়ে কোনো একাউন্ট খুঁজে পাওয়া যায়নি।";
                }
            } else {
                $errors['login'] = "কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            }
            $stmt->close();
        } else {
             $errors['login'] = "ডাটাবেস সমস্যা: " . $conn->error;
        }
    }
    if ($conn) { 
       // $conn->close(); // Connection will be closed in footer
    }
}

require_once 'includes/header.php';
?>

<div class="auth-form">
    <h2 class="text-center mb-4">লগইন করুন</h2>

    <?php display_flash_message(); ?>

    <?php if (!empty($errors['login'])): ?>
        <div class="alert alert-danger"><?php echo $errors['login']; ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['captcha']) && empty($errors['login']) ): // Show captcha error if no general login error ?>
        <div class="alert alert-danger"><?php echo $errors['captcha']; ?></div>
    <?php endif; ?>


    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
        <div class="mb-3">
            <label for="login_identifier" class="form-label">ইমেইল অথবা মোবাইল নম্বর <span class="text-danger">*</span></label>
            <input type="text" name="login_identifier" id="login_identifier" class="form-control <?php echo (!empty($errors['login_identifier'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($login_identifier); ?>" required>
            <?php if (!empty($errors['login_identifier'])): ?><div class="invalid-feedback"><?php echo $errors['login_identifier']; ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">পাসওয়ার্ড <span class="text-danger">*</span></label>
            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($errors['password'])) ? 'is-invalid' : ''; ?>" required>
            <?php if (!empty($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
        </div>
        
        <div class="mb-3">
            <div class="cf-turnstile" data-sitekey="0x4AAAAAABfuh1aGZng_WR9b" data-theme="auto"></div>

            <?php if (!empty($errors['captcha']) && !empty($errors['login_identifier'].$errors['password']) ): // Show captcha specific error only if other fields might be valid, or it was already handled by general $errors['login'] ?>
                <?php endif; ?>
        </div>

        <div class="mb-3">
            <a href="forgot_password.php">পাসওয়ার্ড ভুলে গেছেন?</a>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">লগইন</button>
        </div>
      <p class="mt-3 text-center">একাউন্ট নেই? <a href="register.php<?php
    $redirect_param_for_register_link = ''; 
    if (isset($_SESSION['redirect_url_user'])) { 
         $redirect_param_for_register_link = '?redirect=' . urlencode($_SESSION['redirect_url_user']);
    }
    echo $redirect_param_for_register_link;
?>">রেজিস্টার করুন</a></p>
<?php 
if ($conn && $conn->ping()) { 
    // $conn->close(); 
}
include 'includes/footer.php'; 
?>