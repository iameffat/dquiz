<?php
$page_title = "রেজিস্ট্রেশন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$name = $mobile_number = $email = $gender = $address = $password = $confirm_password = "";
$errors = [];
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $name = trim($_POST["name"]);
    $mobile_number = trim($_POST["mobile_number"]);
    $email = trim($_POST["email"]);
    $gender = isset($_POST["gender"]) ? trim($_POST["gender"]) : "";
    $address = trim($_POST["address"]);
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
                    $errors['mobile'] = "এই মোবাইল নম্বরটি 이미 ব্যবহৃত হয়েছে।";
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
                    $errors['email'] = "এই ইমেইলটি 이미 ব্যবহৃত হয়েছে।";
                }
            } else {
                $errors['db'] = "কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            }
            $stmt_check_email->close();
        }
    }
    
    // Gender validation
    if (empty($gender)) {
        $errors['gender'] = "আপনার লিঙ্গ নির্বাচন করুন।";
    }

    // Address validation (optional)
    if (empty($address)) {
        $errors['address'] = "আপনার ঠিকানা লিখুন।";
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

        $sql_insert_user = "INSERT INTO users (name, mobile_number, email, gender, address, password) VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($stmt_insert = $conn->prepare($sql_insert_user)) {
            $stmt_insert->bind_param("ssssss", $param_name, $param_mobile, $param_email, $param_gender, $param_address, $param_password);
            
            $param_name = $name;
            $param_mobile = $mobile_number;
            $param_email = $email;
            $param_gender = $gender;
            $param_address = $address;
            $param_password = $hashed_password;
            
            if ($stmt_insert->execute()) {
                $success_message = "রেজিস্ট্রেশন সফল হয়েছে! আপনি এখন <a href='login.php'>লগইন</a> করতে পারেন।";
                // Clear form fields after successful registration
                $name = $mobile_number = $email = $gender = $address = "";
            } else {
                $errors['db'] = "দুঃখিত! কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            }
            $stmt_insert->close();
        } else {
             $errors['db'] = "ডাটাবেস সমস্যা: " . $conn->error;
        }
    }
}
$conn->close(); // Close connection
require_once 'includes/header.php';
?>

<div class="auth-form">
    <h2 class="text-center mb-4">নতুন একাউন্ট তৈরি করুন</h2>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

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
            <label class="form-label">লিঙ্গ <span class="text-danger">*</span></label>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input <?php echo (!empty($errors['gender'])) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="male" value="পুরুষ" <?php if ($gender == "পুরুষ") echo "checked"; ?> required>
                    <label class="form-check-label" for="male">পুরুষ</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input <?php echo (!empty($errors['gender'])) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="female" value="মহিলা" <?php if ($gender == "মহিলা") echo "checked"; ?> required>
                    <label class="form-check-label" for="female">মহিলা</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input <?php echo (!empty($errors['gender'])) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="other" value="অন্যান্য" <?php if ($gender == "অন্যান্য") echo "checked"; ?> required>
                    <label class="form-check-label" for="other">অন্যান্য</label>
                </div>
            </div>
            <?php if (!empty($errors['gender'])): ?><div class="text-danger small"><?php echo $errors['gender']; ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="address" class="form-label">ঠিকানা <span class="text-danger">*</span></label>
            <textarea name="address" id="address" class="form-control <?php echo (!empty($errors['address'])) ? 'is-invalid' : ''; ?>" rows="3" required><?php echo htmlspecialchars($address); ?></textarea>
            <?php if (!empty($errors['address'])): ?><div class="invalid-feedback"><?php echo $errors['address']; ?></div><?php endif; ?>
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
        <p class="mt-3 text-center">ইতিমধ্যে একাউন্ট আছে? <a href="login.php">লগইন করুন</a></p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>