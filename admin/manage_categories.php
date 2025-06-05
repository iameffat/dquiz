<?php
$page_title = "ক্যাটাগরি ম্যানেজমেন্ট";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$errors = [];
$feedback_message = "";
$feedback_type = "";

// Handle Add/Edit/Delete Actions
$edit_mode = false;
$category_to_edit = ['id' => '', 'name' => '']; // বিবরণ এবং আইকন ক্লাস সরানো হয়েছে

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_category'])) {
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $name = trim($_POST['name']);
        // বিবরণ এবং আইকন ক্লাসের ইনপুট আর নেওয়া হচ্ছে না

        if (empty($name)) {
            $errors[] = "ক্যাটাগরির নাম আবশ্যক।";
        }

        if (empty($errors)) {
            if ($category_id > 0) { // Update existing category
                $sql = "UPDATE categories SET name = ? WHERE id = ?"; // SQL আপডেটেড
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $name, $category_id); // বাইন্ডিং আপডেটেড
                if ($stmt->execute()) {
                    $_SESSION['flash_message'] = "ক্যাটাগরি সফলভাবে আপডেট করা হয়েছে।";
                    $_SESSION['flash_message_type'] = "success";
                } else {
                    $_SESSION['flash_message'] = "ক্যাটাগরি আপডেট করতে সমস্যা হয়েছে: " . $stmt->error;
                    $_SESSION['flash_message_type'] = "danger";
                }
            } else { // Add new category
                $sql = "INSERT INTO categories (name) VALUES (?)"; // SQL আপডেটেড
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $name); // বাইন্ডিং আপডেটেড
                if ($stmt->execute()) {
                    $_SESSION['flash_message'] = "নতুন ক্যাটাগরি \"".htmlspecialchars($name)."\" সফলভাবে যোগ করা হয়েছে।";
                    $_SESSION['flash_message_type'] = "success";
                } else {
                    if ($conn->errno == 1062) { 
                         $_SESSION['flash_message'] = "এই নামের ক্যাটাগরি 이미 আছে।";
                         $_SESSION['flash_message_type'] = "warning";
                    } else {
                        $_SESSION['flash_message'] = "ক্যাটাগরি যোগ করতে সমস্যা হয়েছে: " . $stmt->error;
                        $_SESSION['flash_message_type'] = "danger";
                    }
                }
            }
            $stmt->close();
            header("Location: manage_categories.php");
            exit;
        } else {
            $category_to_edit['id'] = $category_id;
            $category_to_edit['name'] = $name;
            if($category_id > 0) $edit_mode = true;
        }
    }
} elseif (isset($_GET['action'])) {
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $edit_mode = true;
        $category_id_to_fetch = intval($_GET['id']);
        $sql_fetch = "SELECT id, name FROM categories WHERE id = ?"; // বিবরণ এবং আইকন ক্লাস সরানো হয়েছে
        $stmt_fetch = $conn->prepare($sql_fetch);
        $stmt_fetch->bind_param("i", $category_id_to_fetch);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($cat_data = $result_fetch->fetch_assoc()) {
            $category_to_edit = $cat_data;
        } else {
            $_SESSION['flash_message'] = "ক্যাটাগরি খুঁজে পাওয়া যায়নি।";
            $_SESSION['flash_message_type'] = "warning";
            header("Location: manage_categories.php");
            exit;
        }
        $stmt_fetch->close();
    } elseif ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $category_id_to_delete = intval($_GET['id']);
        $sql_delete = "DELETE FROM categories WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $category_id_to_delete);
        if ($stmt_delete->execute()) {
            $_SESSION['flash_message'] = "ক্যাটাগরি সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "ক্যাটাগরি ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete->error . " (সম্ভবত এই ক্যাটাগরির অধীনে প্রশ্ন রয়েছে।)";
            $_SESSION['flash_message_type'] = "danger";
        }
        $stmt_delete->close();
        header("Location: manage_categories.php");
        exit;
    }
}

// Fetch all categories for display
$categories = [];
// Updated SQL to count questions from both questions.category_id and question_categories junction table
$sql_all_categories = "SELECT c.id, c.name, 
                        (
                            SELECT COUNT(DISTINCT q.id) 
                            FROM questions q 
                            LEFT JOIN question_categories qc ON q.id = qc.question_id
                            WHERE q.category_id = c.id OR qc.category_id = c.id
                        ) as question_count
                       FROM categories c
                       ORDER BY c.name ASC";
$result_all_categories = $conn->query($sql_all_categories);
if ($result_all_categories) {
    while ($row = $result_all_categories->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    // Log error if query fails
    error_log("Error fetching categories with updated count: " . $conn->error);
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-3"><?php echo $page_title; ?></h1>

    <?php display_flash_message(); ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $error): echo "<li>" . htmlspecialchars($error) . "</li>"; endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <?php echo $edit_mode ? "ক্যাটাগরি এডিট করুন" : "নতুন ক্যাটাগরি যোগ করুন"; ?>
        </div>
        <div class="card-body">
            <form action="manage_categories.php" method="post">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category_to_edit['id']); ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label for="name" class="form-label">ক্যাটাগরির নাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($category_to_edit['name']); ?>" required>
                </div>
                <?php /* বিবরণ এবং আইকন ক্লাসের ইনপুট ফিল্ডগুলো সরিয়ে ফেলা হয়েছে
                <div class="mb-3">
                    <label for="description" class="form-label">বিবরণ (ঐচ্ছিক)</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($category_to_edit['description']); ?></textarea>
                </div>
                 <div class="mb-3">
                    <label for="icon_class" class="form-label">আইকন ক্লাস (ঐচ্ছিক)</label>
                    <input type="text" class="form-control" id="icon_class" name="icon_class" value="<?php echo htmlspecialchars($category_to_edit['icon_class']); ?>" placeholder="যেমন: fas fa-book">
                    <small class="form-text text-muted">Font Awesome বা অন্য কোনো আইকন লাইব্রেরির ক্লাস এখানে দিতে পারেন। যেমন: `fas fa-star`</small>
                </div>
                */ ?>
                <button type="submit" name="save_category" class="btn btn-primary"><?php echo $edit_mode ? "আপডেট করুন" : "সংরক্ষণ করুন"; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="manage_categories.php" class="btn btn-secondary">বাতিল</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">সকল ক্যাটাগরি</div>
        <div class="card-body">
            <?php if (!empty($categories)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>নাম</th>
                            <?php /*<th>বিবরণ</th> - সরানো হয়েছে */ ?>
                            <?php /*<th>আইকন</th> - সরানো হয়েছে */ ?>
                            <th>প্রশ্ন সংখ্যা</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo $category['id']; ?></td>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <?php /*<td><?php echo htmlspecialchars(mb_strimwidth($category['description'], 0, 70, "...")); ?></td> - সরানো হয়েছে */ ?>
                            <?php /*<td><?php echo !empty($category['icon_class']) ? '<i class="' . htmlspecialchars($category['icon_class']) . '"></i>' : 'N/A'; ?></td> - সরানো হয়েছে */ ?>
                            <td><?php echo $category['question_count']; ?></td>
                            <td>
                                <a href="manage_categories.php?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-info">এডিট</a>
                                <a href="manage_categories.php?action=delete&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ক্যাটাগরি ডিলিট করতে চান?');">ডিলিট</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center">এখনও কোনো ক্যাটাগরি যোগ করা হয়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>