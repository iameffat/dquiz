<?php
$page_title = "ইউজার এডিট করুন";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$user_id_to_edit = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_data = null;
$errors = [];
$success_message = "";

if ($user_id_to_edit <= 0) {
    $_SESSION['flash_message'] = "অবৈধ ইউজার ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_users.php");
    exit;
}

// Fetch user details for editing
$sql_fetch_user = "SELECT id, name, email, mobile_number, gender, address, role FROM users WHERE id = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch_user)) {
    $stmt_fetch->bind_param("i", $user_id_to_edit);
    $stmt_fetch->execute();
    $result_user = $stmt_fetch->get_result();
    if ($result_user->num_rows === 1) {
        $user_data = $result_user->fetch_assoc();
    } else {
        $_SESSION['flash_message'] = "ইউজার (ID: {$user_id_to_edit}) খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: manage_users.php");
        exit;
    }
    $stmt_fetch->close();
} else {
    // This should not happen if DB connection is fine
    $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (ইউজার তথ্য আনতে)।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_users.php");
    exit;
}


// Handle Form Submission for updating user details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    // Sanitize and validate inputs
    $name = trim($_POST["name"]);
    $mobile_number = trim($_POST["mobile_number"]);
    $email = trim($_POST["email"]);
    $gender = isset($_POST["gender"]) ? trim($_POST["gender"]) : $user_data['gender']; // Keep old if not set
    $address = trim($_POST["address"]);
    $role = trim($_POST["role"]);

    // Basic Validations
    if (empty($name)) $errors['name'] = "নাম খালি রাখা যাবে না।";
    if (empty($mobile_number)) $errors['mobile'] = "মোবাইল নম্বর খালি রাখা যাবে না।";
    elseif (!preg_match('/^[0-1][0-9]{10}$/', $mobile_number)) $errors['mobile'] = "সঠিক মোবাইল নম্বর লিখুন।";
    
    if (empty($email)) $errors['email'] = "ইমেইল খালি রাখা যাবে না।";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "সঠিক ইমেইল এড্রেস লিখুন।";

    if (!in_array($role, ['user', 'admin'])) $errors['role'] = "অবৈধ ভূমিকা নির্বাচন করা হয়েছে।";
    
    // Check for uniqueness of email and mobile if changed
    if ($email !== $user_data['email']) {
        $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt_check_email = $conn->prepare($sql_check_email);
        $stmt_check_email->bind_param("si", $email, $user_id_to_edit);
        $stmt_check_email->execute();
        if ($stmt_check_email->get_result()->num_rows > 0) {
            $errors['email'] = "এই ইমেইলটি অন্য ইউজার ব্যবহার করছে।";
        }
        $stmt_check_email->close();
    }
    if ($mobile_number !== $user_data['mobile_number']) {
        $sql_check_mobile = "SELECT id FROM users WHERE mobile_number = ? AND id != ?";
        $stmt_check_mobile = $conn->prepare($sql_check_mobile);
        $stmt_check_mobile->bind_param("si", $mobile_number, $user_id_to_edit);
        $stmt_check_mobile->execute();
        if ($stmt_check_mobile->get_result()->num_rows > 0) {
            $errors['mobile'] = "এই মোবাইল নম্বরটি অন্য ইউজার ব্যবহার করছে।";
        }
        $stmt_check_mobile->close();
    }


    if (empty($errors)) {
        $sql_update_user = "UPDATE users SET name = ?, email = ?, mobile_number = ?, gender = ?, address = ?, role = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update_user)) {
            $stmt_update->bind_param("ssssssi", $name, $email, $mobile_number, $gender, $address, $role, $user_id_to_edit);
            if ($stmt_update->execute()) {
                $_SESSION['flash_message'] = "ইউজারের তথ্য (ID: {$user_id_to_edit}) সফলভাবে আপডেট করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
                // Re-fetch data to show updated values in form, or redirect
                header("Location: manage_users.php"); // Or redirect back to edit page
                exit;
            } else {
                $errors['db'] = "ইউজারের তথ্য আপডেট করতে সমস্যা হয়েছে: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors['db'] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
        }
    }
    // If errors, form will re-populate with new (possibly erroneous) values and show errors
    $user_data['name'] = $name; // Update user_data for form repopulation
    $user_data['email'] = $email;
    $user_data['mobile_number'] = $mobile_number;
    $user_data['gender'] = $gender;
    $user_data['address'] = $address;
    $user_data['role'] = $role;
}


require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-3">ইউজার এডিট করুন: <?php echo htmlspecialchars($user_data['name']); ?> (ID: <?php echo $user_data['id']; ?>)</h1>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="edit_user.php?id=<?php echo $user_id_to_edit; ?>" method="post" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">নাম <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control <?php echo (!empty($errors['name'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
                    <?php if (!empty($errors['name'])): ?><div class="invalid-feedback"><?php echo $errors['name']; ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="mobile_number" class="form-label">মোবাইল নম্বর <span class="text-danger">*</span></label>
                    <input type="tel" name="mobile_number" id="mobile_number" class="form-control <?php echo (!empty($errors['mobile'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_data['mobile_number']); ?>" placeholder="01xxxxxxxxx" required>
                    <?php if (!empty($errors['mobile'])): ?><div class="invalid-feedback"><?php echo $errors['mobile']; ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">ইমেইল <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control <?php echo (!empty($errors['email'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    <?php if (!empty($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">লিঙ্গ</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="male" value="পুরুষ" <?php if ($user_data['gender'] == "পুরুষ") echo "checked"; ?>>
                            <label class="form-check-label" for="male">পুরুষ</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="female" value="মহিলা" <?php if ($user_data['gender'] == "মহিলা") echo "checked"; ?>>
                            <label class="form-check-label" for="female">মহিলা</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="other" value="অন্যান্য" <?php if ($user_data['gender'] == "অন্যান্য") echo "checked"; ?>>
                            <label class="form-check-label" for="other">অন্যান্য</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">ঠিকানা</label>
                    <textarea name="address" id="address" class="form-control" rows="3"><?php echo htmlspecialchars($user_data['address']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">ভূমিকা (Role) <span class="text-danger">*</span></label>
                    <select name="role" id="role" class="form-select <?php echo (!empty($errors['role'])) ? 'is-invalid' : ''; ?>" required <?php if ($user_data['id'] == $_SESSION['user_id'] && $user_data['role'] == 'admin') echo 'disabled'; ?>>
                        <option value="user" <?php if ($user_data['role'] == 'user') echo 'selected'; ?>>ইউজার</option>
                        <option value="admin" <?php if ($user_data['role'] == 'admin') echo 'selected'; ?>>এডমিন</option>
                    </select>
                    <?php if ($user_data['id'] == $_SESSION['user_id'] && $user_data['role'] == 'admin'): ?>
                        <small class="form-text text-muted">আপনি নিজের ভূমিকা পরিবর্তন করতে পারবেন না।</small>
                    <?php endif; ?>
                    <?php if (!empty($errors['role'])): ?><div class="invalid-feedback"><?php echo $errors['role']; ?></div><?php endif; ?>
                </div>
                
                <p class="text-muted"><small>নিরাপত্তার কারণে, এই ইন্টারফেস থেকে ইউজারের পাসওয়ার্ড পরিবর্তন করা যাবে না।</small></p>

                <button type="submit" name="update_user" class="btn btn-primary">তথ্য আপডেট করুন</button>
                <a href="manage_users.php" class="btn btn-secondary">বাতিল করুন</a>
            </form>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>