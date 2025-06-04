<?php
$page_title = "ক্যাটাগরি এডিট করুন";
$admin_base_url = '';
require_once '../includes/db_connect.php'; //
require_once 'includes/auth_check.php'; //
require_once '../includes/functions.php'; //

$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$category_name = '';
$category_description = '';
$current_category_name = '';

if ($category_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ ক্যাটাগরি ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_categories.php");
    exit;
}

// Fetch existing category details
$sql_fetch = "SELECT name, description FROM categories WHERE id = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $category_id);
    $stmt_fetch->execute();
    $result_cat = $stmt_fetch->get_result();
    if ($cat_data = $result_cat->fetch_assoc()) {
        $category_name = $cat_data['name'];
        $current_category_name = $cat_data['name']; // Store for unique check
        $category_description = $cat_data['description'];
        $page_title = "এডিট: " . htmlspecialchars($category_name);
    } else {
        $_SESSION['flash_message'] = "ক্যাটাগরি (ID: {$category_id}) খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: manage_categories.php");
        exit;
    }
    $stmt_fetch->close();
} else {
    $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (ক্যাটাগরি তথ্য আনতে)।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_categories.php");
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category_name = trim($_POST['category_name']);
    $category_description = trim($_POST['category_description']);

    if (empty($category_name)) {
        $errors[] = "ক্যাটাগরির নাম আবশ্যক।";
    }

    // Check if category name already exists (and is not the current category's name)
    if (empty($errors) && strtolower($category_name) !== strtolower($current_category_name)) {
        $sql_check_name = "SELECT id FROM categories WHERE name = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check_name);
        if ($stmt_check) {
            $stmt_check->bind_param("si", $category_name, $category_id);
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
        $sql_update = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssi", $category_name, $category_description, $category_id);
            if ($stmt_update->execute()) {
                $_SESSION['flash_message'] = "ক্যাটাগরি \"" . htmlspecialchars($category_name) . "\" সফলভাবে আপডেট করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
                header("Location: manage_categories.php");
                exit;
            } else {
                if ($conn->errno == 1062) {
                     $errors[] = "এই নামের ক্যাটাগরি 이미 বিদ্যমান।";
                } else {
                    $errors[] = "ক্যাটাগরি আপডেট করতে সমস্যা হয়েছে: " . $stmt_update->error;
                }
            }
            $stmt_update->close();
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


    <form action="edit_category.php?id=<?php echo $category_id; ?>" method="post">
        <div class="card mb-4">
            <div class="card-header">ক্যাটাগরির বিবরণ (এডিট)</div>
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
        <button type="submit" class="btn btn-primary">আপডেট করুন</button>
        <a href="manage_categories.php" class="btn btn-outline-secondary">বাতিল</a>
    </form>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php'; //
?>