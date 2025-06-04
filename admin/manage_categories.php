<?php
$page_title = "ক্যাটাগরি ম্যানেজমেন্ট";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $category_id_to_delete = intval($_GET['id']);
    // Note: Quizzes in this category will have their category_id set to NULL due to ON DELETE SET NULL
    $sql_delete_category = "DELETE FROM categories WHERE id = ?";
    if ($stmt_delete = $conn->prepare($sql_delete_category)) {
        $stmt_delete->bind_param("i", $category_id_to_delete);
        if ($stmt_delete->execute()) {
            $_SESSION['flash_message'] = "ক্যাটাগরি (ID: {$category_id_to_delete}) সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "ক্যাটাগরি ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete->error;
            $_SESSION['flash_message_type'] = "danger";
        }
        $stmt_delete->close();
    } else {
        $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: manage_categories.php");
    exit;
}

require_once 'includes/header.php'; //

$categories = [];
// Fetch categories with quiz count
$sql_categories = "SELECT c.id, c.name, c.description, c.created_at, COUNT(q.id) as quiz_count
                   FROM categories c
                   LEFT JOIN quizzes q ON c.id = q.category_id
                   GROUP BY c.id, c.name, c.description, c.created_at
                   ORDER BY c.name ASC";
$result_categories = $conn->query($sql_categories);
if ($result_categories && $result_categories->num_rows > 0) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1><?php echo $page_title; ?></h1>
        <a href="add_category.php" class="btn btn-primary">নতুন ক্যাটাগরি যোগ করুন</a>
    </div>

    <?php display_flash_message(); // ?>

    <div class="card">
        <div class="card-header">সকল ক্যাটাগরির তালিকা</div>
        <div class="card-body">
            <?php if (!empty($categories)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>নাম</th>
                            <th>বিবরণ</th>
                            <th>কুইজের সংখ্যা</th>
                            <th>তৈরির তারিখ</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo $category['id']; ?></td>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($category['description'] ?? '', 0, 70, "...")); ?></td>
                            <td><?php echo $category['quiz_count']; ?></td>
                            <td><?php echo format_datetime($category['created_at'], "d M Y"); // ?></td>
                            <td>
                                <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-info mb-1">এডিট</a>
                                <a href="manage_categories.php?action=delete&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ক্যাটাগরিটি ডিলিট করতে চান? এই ক্যাটাগরির কুইজগুলো থেকে ক্যাটাগরি মুছে যাবে।');">ডিলিট</a>
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
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php'; //
?>