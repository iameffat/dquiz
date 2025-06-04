<?php
$page_title = "এডমিন লগইন";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php'; // Session is started here
require_once '../includes/functions.php';

// If admin is already logged in, redirect to admin dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["role"]) && $_SESSION["role"] === 'admin') {
    header("location: index.php");
    exit;
}

$login_identifier = $password = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_identifier = trim($_POST["login_identifier"]);
    $password = $_POST["password"];

    if (empty($login_identifier)) {
        $errors['login_identifier'] = "আপনার ইমেইল অথবা মোবাইল নম্বর লিখুন।";
    }
    if (empty($password)) {
        $errors['password'] = "আপনার পাসওয়ার্ড লিখুন।";
    }

    if (empty($errors)) {
        $field_type = filter_var($login_identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile_number';
        // Fetch is_banned status
        $sql = "SELECT id, name, email, mobile_number, password, role, is_banned FROM users WHERE $field_type = ? AND role = 'admin'";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_identifier);
            $param_identifier = $login_identifier;

            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $name, $db_email, $db_mobile, $hashed_password, $role, $is_banned); // Added $is_banned
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            if ($is_banned == 1) { // Check if banned
                                $errors['login'] = "এই এডমিন একাউন্টটি নিষিদ্ধ করা হয়েছে।";
                            } else {
                                $_SESSION["loggedin"] = true;
                                $_SESSION["user_id"] = $id;
                                $_SESSION["name"] = $name;
                                $_SESSION["email"] = $db_email;
                                $_SESSION["mobile_number"] = $db_mobile;
                                $_SESSION["role"] = $role;
                                
                                $redirect_url = isset($_SESSION['redirect_url_admin']) ? $_SESSION['redirect_url_admin'] : 'index.php';
                                unset($_SESSION['redirect_url_admin']);
                                header("location: " . $redirect_url);
                                exit;
                            }
                        } else {
                            $errors['login'] = "পাসওয়ার্ড সঠিক নয় অথবা আপনি এডমিন নন।";
                        }
                    }
                } else {
                    $errors['login'] = "এই ".$field_type." দিয়ে কোনো এডমিন একাউন্ট খুঁজে পাওয়া যায়নি।";
                }
            } else {
                $errors['login'] = "কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            }
            $stmt->close();
        } else {
            $errors['login'] = "ডাটাবেস সমস্যা: " . $conn->error;
        }
    }
    // No need to close connection here if it's closed in footer
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - দ্বীনিলাইফ কুইজ</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet"> <style>
        body { background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .admin-login-form { max-width: 400px; width:100%; padding: 25px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="admin-login-form">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="লোগো" width="72">
            <h2 class="mt-2">এডমিন লগইন</h2>
            <p>দ্বীনিলাইফ কুইজ</p>
        </div>

        <?php if (!empty($errors['login'])): ?>
            <div class="alert alert-danger"><?php echo $errors['login']; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
            <div class="mb-3">
                <label for="login_identifier" class="form-label">ইমেইল অথবা মোবাইল নম্বর</label>
                <input type="text" name="login_identifier" id="login_identifier" class="form-control <?php echo (!empty($errors['login_identifier'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($login_identifier); ?>" required>
                <?php if (!empty($errors['login_identifier'])): ?><div class="invalid-feedback"><?php echo $errors['login_identifier']; ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">পাসওয়ার্ড</label>
                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($errors['password'])) ? 'is-invalid' : ''; ?>" required>
                <?php if (!empty($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">লগইন</button>
            </div>
        </form>
    </div>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <?php
     if ($conn) { $conn->close(); } // Close connection if it was opened
    ?>
</body>
</html>