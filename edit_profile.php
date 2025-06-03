<?php
$page_title = "প্রোফাইল এডিট করুন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['redirect_url_user'] = 'edit_profile.php'; // Redirect back here after login
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$current_email = $_SESSION['email']; // Store current email for unique check
$current_mobile = $_SESSION['mobile_number']; // Store current mobile for unique check
$gender = '';
$address = '';

// Fetch current gender and address from DB as they might not be in session or might be outdated
$sql_fetch_details = "SELECT gender, address FROM users WHERE id = ?";
if($stmt_fetch_details = $conn->prepare($sql_fetch_details)){
    $stmt_fetch_details->bind_param("i", $user_id);
    $stmt_fetch_details->execute();
    $result_details = $stmt_fetch_details->get_result();
    if($user_details = $result_details->fetch_assoc()){
        $gender = $user_details['gender'];
        $address = $user_details['address'];
    }
    $stmt_fetch_details->close();
}


$errors = [];
$success_message = "";

// Populate form fields with current session/DB data for initial display
$form_name = $name;
$form_email = $current_email;
$form_mobile = $current_mobile;
$form_gender = $gender;
$form_address = $address;


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Sanitize and validate inputs
    $form_name = trim($_POST["name"]);
    $form_mobile = trim($_POST["mobile_number"]);
    $form_email = trim($_POST["email"]);
    $form_gender = isset($_POST["gender"]) ? trim($_POST["gender"]) : "";
    $form_address = trim($_POST["address"]);

    // Name validation
    if (empty($form_name)) {
        $errors['name'] = "আপনার নাম লিখুন।";
    }

    // Mobile number validation
    if (empty($form_mobile)) {
        $errors['mobile'] = "আপনার মোবাইল নম্বর লিখুন।";
    } elseif (!preg_match('/^[0-1][0-9]{10}$/', $form_mobile)) {
        $errors['mobile'] = "সঠিক মোবাইল নম্বর লিখুন (যেমন: 01xxxxxxxxx)।";
    } elseif ($form_mobile !== $current_mobile) { // Check only if mobile is changed
        $sql_check_mobile = "SELECT id FROM users WHERE mobile_number = ? AND id != ?";
        if ($stmt_check_mobile = $conn->prepare($sql_check_mobile)) {
            $stmt_check_mobile->bind_param("si", $form_mobile, $user_id);
            if ($stmt_check_mobile->execute()) {
                $stmt_check_mobile->store_result();
                if ($stmt_check_mobile->num_rows > 0) {
                    $errors['mobile'] = "এই মোবাইল নম্বরটি 이미 ব্যবহৃত হয়েছে।";
                }
            } else {
                $errors['db'] = "মোবাইল নম্বর চেক করতে সমস্যা হয়েছে।";
            }
            $stmt_check_mobile->close();
        }
    }

    // Email validation
    if (empty($form_email)) {
        $errors['email'] = "আপনার ইমেইল লিখুন।";
    } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "সঠিক ইমেইল এড্রেস লিখুন।";
    } elseif ($form_email !== $current_email) { // Check only if email is changed
        $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
        if ($stmt_check_email = $conn->prepare($sql_check_email)) {
            $stmt_check_email->bind_param("si", $form_email, $user_id);
            if ($stmt_check_email->execute()) {
                $stmt_check_email->store_result();
                if ($stmt_check_email->num_rows > 0) {
                    $errors['email'] = "এই ইমেইলটি 이미 ব্যবহৃত হয়েছে।";
                }
            } else {
                $errors['db'] = "ইমেইল চেক করতে সমস্যা হয়েছে।";
            }
            $stmt_check_email->close();
        }
    }
    
    if (empty($form_gender)) {
        $errors['gender'] = "আপনার লিঙ্গ নির্বাচন করুন।";
    }
    if (empty($form_address)) {
        $errors['address'] = "আপনার ঠিকানা লিখুন।";
    }

    if (empty($errors)) {
        $sql_update_user = "UPDATE users SET name = ?, mobile_number = ?, email = ?, gender = ?, address = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update_user)) {
            $stmt_update->bind_param("sssssi", $form_name, $form_mobile, $form_email, $form_gender, $form_address, $user_id);
            if ($stmt_update->execute()) {
                // Update session variables
                $_SESSION['name'] = $form_name;
                $_SESSION['email'] = $form_email;
                $_SESSION['mobile_number'] = $form_mobile;
                // Gender and address are not typically stored in session for profile display, but if they were, update them too.

                $_SESSION['flash_message'] = "আপনার প্রোফাইল সফলভাবে আপডেট করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
                header("Location: profile.php"); // Redirect to profile page
                exit;
            } else {
                $errors['db'] = "প্রোফাইল আপডেট করতে সমস্যা হয়েছে: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
             $errors['db'] = "ডাটাবেস সমস্যা (প্রস্তুত): " . $conn->error;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">প্রোফাইল এডিট করুন</h3>
                </div>
                <div class="card-body p-4">
                    <?php display_flash_message(); // General flash messages ?>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors['db'])): ?>
                        <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">নাম <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control <?php echo (!empty($errors['name'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_name); ?>" required>
                            <?php if (!empty($errors['name'])): ?><div class="invalid-feedback"><?php echo $errors['name']; ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="mobile_number" class="form-label">মোবাইল নম্বর <span class="text-danger">*</span></label>
                            <input type="tel" name="mobile_number" id="mobile_number" class="form-control <?php echo (!empty($errors['mobile'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_mobile); ?>" placeholder="01xxxxxxxxx" required>
                            <?php if (!empty($errors['mobile'])): ?><div class="invalid-feedback"><?php echo $errors['mobile']; ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">ইমেইল <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control <?php echo (!empty($errors['email'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_email); ?>" required>
                            <?php if (!empty($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">লিঙ্গ <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input <?php echo (!empty($errors['gender'])) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="male" value="পুরুষ" <?php if ($form_gender == "পুরুষ") echo "checked"; ?> required>
                                    <label class="form-check-label" for="male">পুরুষ</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input <?php echo (!empty($errors['gender'])) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="female" value="মহিলা" <?php if ($form_gender == "মহিলা") echo "checked"; ?> required>
                                    <label class="form-check-label" for="female">মহিলা</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input <?php echo (!empty($errors['gender'])) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="other" value="অন্যান্য" <?php if ($form_gender == "অন্যান্য") echo "checked"; ?> required>
                                    <label class="form-check-label" for="other">অন্যান্য</label>
                                </div>
                            </div>
                            <?php if (!empty($errors['gender'])): ?><div class="text-danger small mt-1"><?php echo $errors['gender']; ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">ঠিকানা <span class="text-danger">*</span></label>
                            <textarea name="address" id="address" class="form-control <?php echo (!empty($errors['address'])) ? 'is-invalid' : ''; ?>" rows="3" required><?php echo htmlspecialchars($form_address); ?></textarea>
                            <?php if (!empty($errors['address'])): ?><div class="invalid-feedback"><?php echo $errors['address']; ?></div><?php endif; ?>
                        </div>
                        
                        <hr>
<p class="text-muted"><small>পাসওয়ার্ড পরিবর্তন করতে, অনুগ্রহ করে "পাসওয়ার্ড পরিবর্তন করুন" পেইজ ব্যবহার করুন।</small></p>
                        <div class="d-grid gap-2">
                            <button type="submit" name="update_profile" class="btn btn-primary">আপডেট করুন</button>
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