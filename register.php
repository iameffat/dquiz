<?php
// register.php

// If a redirect GET parameter is present, store it in a temporary session variable
// This is useful if the user was trying to access a specific page before being sent to register
if (isset($_GET['redirect'])) {
    // Store the original redirect target that came with the link to register page
    $_SESSION['redirect_url_on_register_init'] = urldecode($_GET['redirect']);
}

$page_title = "রেজিস্ট্রেশন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';


$name = $mobile_number = $email = $district_name = $password = $confirm_password = "";
$errors = [];
// $success_message = ""; // success_message is less useful if we redirect immediately

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $name = trim($_POST["name"]);
    $mobile_number = trim($_POST["mobile_number"]);
    $email = trim($_POST["email"]);
    $district_name = trim($_POST["district_name"]); // Changed from address
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Name validation
    if (empty($name)) {
        $errors['name'] = "আপনার নাম লিখুন।";
    }

    // Mobile number validation
    if (empty($mobile_number)) {
        $errors['mobile'] = "আপনার মোবাইল নম্বর লিখুন।";
    } elseif (!preg_match('/^[0-1][0-9]{10}$/', $mobile_number)) {
        $errors['mobile'] = "সঠিক মোবাইল নম্বর লিখুন (যেমন: 01xxxxxxxxx)।";
    } else {
        // Check if mobile number already exists
        $sql_check_mobile = "SELECT id FROM users WHERE mobile_number = ?";
        if ($stmt_check_mobile = $conn->prepare($sql_check_mobile)) {
            $stmt_check_mobile->bind_param("s", $param_mobile);
            $param_mobile = $mobile_number;
            if ($stmt_check_mobile->execute()) {
                $stmt_check_mobile->store_result();
                if ($stmt_check_mobile->num_rows > 0) {
                    $errors['mobile'] = "এই মোবাইল নম্বরটি ইতিমধ্যে ব্যবহৃত হয়েছে।";
                }
            } else {
                $errors['db'] = "কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            }
            $stmt_check_mobile->close();
        }
    }

    // Email validation
    if (empty($email)) {
        $errors['email'] = "আপনার ইমেইল লিখুন।";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "সঠিক ইমেইল এড্রেস লিখুন।";
    } else {
        // Check if email already exists
        $sql_check_email = "SELECT id FROM users WHERE email = ?";
        if ($stmt_check_email = $conn->prepare($sql_check_email)) {
            $stmt_check_email->bind_param("s", $param_email);
            $param_email = $email;
            if ($stmt_check_email->execute()) {
                $stmt_check_email->store_result();
                if ($stmt_check_email->num_rows > 0) {
                    $errors['email'] = "এই ইমেইলটি ইতিমধ্যে ব্যবহৃত হয়েছে।";
                }
            } else {
                $errors['db'] = "কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            }
            $stmt_check_email->close();
        }
    }
    
    // District Name validation (formerly Address)
    if (empty($district_name)) {
        $errors['district_name'] = "আপনার জেলার নাম লিখুন।";
    }

    // Password validation
    if (empty($password)) {
        $errors['password'] = "একটি পাসওয়ার্ড লিখুন।";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।";
    }

    // Confirm password validation
    if (empty($confirm_password)) {
        $errors['confirm_password'] = "পাসওয়ার্ডটি পুনরায় লিখুন।";
    } elseif ($password != $confirm_password) {
        $errors['confirm_password'] = "পাসওয়ার্ড দুটি মিলেনি।";
    }

    // If no errors, proceed to insert into database
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hash the password

        // Gender column removed from SQL query and bind_param
        $sql_insert_user = "INSERT INTO users (name, mobile_number, email, address, password) VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt_insert = $conn->prepare($sql_insert_user)) {
            // sssss instead of ssssss, $param_gender removed
            $stmt_insert->bind_param("sssss", $param_name, $param_mobile, $param_email, $param_district_name, $param_password);
            
            $param_name = $name;
            $param_mobile = $mobile_number;
            $param_email = $email;
            $param_district_name = $district_name; // Use district name for address column
            $param_password = $hashed_password;
            
            if ($stmt_insert->execute()) {
                // Set flash message for login page
                $_SESSION['flash_message'] = "রেজিস্ট্রেশন সফল হয়েছে! অনুগ্রহ করে লগইন করুন।";
                $_SESSION['flash_message_type'] = "success";

                // Prepare redirect to login page, preserving the original intended redirect if any
                $login_redirect_param = '';
                // Check if a redirect URL was stored when register.php was initially loaded
                if (isset($_SESSION['redirect_url_on_register_init'])) {
                    // Pass this redirect to the login page
                    $login_redirect_param = '?redirect=' . urlencode($_SESSION['redirect_url_on_register_init']);
                    // Important: Set this to the main session redirect variable that login.php uses
                    $_SESSION['redirect_url_user'] = $_SESSION['redirect_url_on_register_init'];
                    unset($_SESSION['redirect_url_on_register_init']); // Clear the temporary one
                }
                header("Location: login.php" . $login_redirect_param);
                exit;

            } else {
                $errors['db'] = "দুঃখিত! কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            }
            $stmt_insert->close();
        } else {
             $errors['db'] = "ডাটাবেস সমস্যা: " . $conn->error;
        }
    }
}
// $conn->close(); // Connection will be closed in footer.php
require_once 'includes/header.php';
?>

<div class="auth-form">
    <h2 class="text-center mb-4">নতুন একাউন্ট তৈরি করুন</h2>

    <?php display_flash_message(); // This will display messages set before redirect (e.g. if user is already logged in and tries to access register.php) ?>


    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
        <div class="mb-3">
            <label for="name" class="form-label">নাম <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control <?php echo (!empty($errors['name'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" required>
            <?php if (!empty($errors['name'])): ?><div class="invalid-feedback"><?php echo $errors['name']; ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="mobile_number" class="form-label">মোবাইল নম্বর <span class="text-danger">*</span></label>
            <input type="tel" name="mobile_number" id="mobile_number" class="form-control <?php echo (!empty($errors['mobile'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($mobile_number); ?>" placeholder="01xxxxxxxxx" required>
            <?php if (!empty($errors['mobile'])): ?><div class="invalid-feedback"><?php echo $errors['mobile']; ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">ইমেইল <span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control <?php echo (!empty($errors['email'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
            <?php if (!empty($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
        </div>
        
        <div class="mb-3">
            <label for="district_name" class="form-label">জেলার নাম <span class="text-danger">*</span></label>
            <input type="text" name="district_name" id="district_name" class="form-control <?php echo (!empty($errors['district_name'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($district_name); ?>" required>
            <?php if (!empty($errors['district_name'])): ?><div class="invalid-feedback"><?php echo $errors['district_name']; ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">পাসওয়ার্ড <span class="text-danger">*</span></label>
            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($errors['password'])) ? 'is-invalid' : ''; ?>" required>
            <?php if (!empty($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="confirm_password" class="form-label">পাসওয়ার্ড নিশ্চিত করুন <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($errors['confirm_password'])) ? 'is-invalid' : ''; ?>" required>
            <?php if (!empty($errors['confirm_password'])): ?><div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div><?php endif; ?>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">রেজিস্টার করুন</button>
        </div>
        <p class="mt-3 text-center">ইতিমধ্যে একাউন্ট আছে? <a href="login.php<?php
            $login_link_redirect_param = '';
            // If a redirect target was stored when register.php was loaded, pass it to the login link
            if (isset($_SESSION['redirect_url_on_register_init'])) {
                $login_link_redirect_param = '?redirect=' . urlencode($_SESSION['redirect_url_on_register_init']);
            }
            echo $login_link_redirect_param;
        ?>">লগইন করুন</a></p>
    </form>
</div>

<?php 
if ($conn && $conn->ping()) {
    $conn->close(); 
}
include 'includes/footer.php'; 
?>