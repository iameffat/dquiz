<?php
$page_title = "নতুন ক্যাটাগরি যোগ করুন";
$admin_base_url = '';
require_once '../includes/db_connect.php'; //
require_once 'includes/auth_check.php'; //
require_once '../includes/functions.php'; //

$errors = [];
$category_name = '';
$category_description = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category_name = trim($_POST['category_name']);
    $category_description = trim($_POST['category_description']);

    if (empty($category_name)) {
        $errors[] = "ক্যাটাগরির নাম আবশ্যক।";
    }

    // Check if category name already exists
    if (empty($errors)) {
        $sql_check_name = "SELECT id FROM categories WHERE name = ?";
        $stmt_check = $conn->prepare($sql_check_name);
        if ($stmt_check) {
            $stmt_check->bind_param("s", $category_name);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "এই নামের ক্যাটাগরি 이미 বিদ্যমান।";
            }
            $stmt_check->close();
        } else {
            $errors[] = "ডাটাবেস সমস্যা (নাম যাচাই): " . $conn->error;
        }
    }


    if (empty($errors)) {
        $sql_insert = "INSERT INTO categories (name, description) VALUES (?, ?)";
        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("ss", $category_name, $category_description);
            if ($stmt_insert->execute()) {
                $_SESSION['flash_message'] = "ক্যাটাগরি \"" . htmlspecialchars($category_name) . "\" সফলভাবে যোগ করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
                header("Location: manage_categories.php");
                exit;
            } else {
                // Error 1062 is for duplicate entry
                if ($conn->errno == 1062) {
                     $errors[] = "এই নামের ক্যাটাগরি 이미 বিদ্যমান।";
                } else {
                    $errors[] = "ক্যাটাগরি সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_insert->error;
                }
            }
            $stmt_insert->close();
        } else {
            $errors[] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
        }
    }
}

require_once 'includes/header.php'; //
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-3"><?php echo $page_title; ?></h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong>
        <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
     <?php display_flash_message(); // ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="card mb-4">
            <div class="card-header">ক্যাটাগরির বিবরণ</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="category_name" class="form-label">ক্যাটাগরির নাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="category_name" name="category_name" value="<?php echo htmlspecialchars($category_name); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="category_description" class="form-label">বিবরণ (ঐচ্ছিক)</label>
                    <textarea class="form-control" id="category_description" name="category_description" rows="3"><?php echo htmlspecialchars($category_description); ?></textarea>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">ক্যাটাগরি সংরক্ষণ করুন</button>
        <a href="manage_categories.php" class="btn btn-outline-secondary">বাতিল</a>
    </form>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php'; //
?>