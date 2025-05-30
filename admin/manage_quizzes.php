<?php
$page_title = "কুইজ ম্যানেজমেন্ট";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

define('QUESTION_IMAGE_UPLOAD_DIR_MANAGE', '../uploads/question_images/');


// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['quiz_id'])) {
    $quiz_id_to_delete = intval($_GET['quiz_id']);
    
    $conn->begin_transaction();
    try {
        // Fetch image_urls of questions related to this quiz to delete files
        $sql_get_images = "SELECT image_url FROM questions WHERE quiz_id = ?";
        $stmt_get_images = $conn->prepare($sql_get_images);
        $stmt_get_images->bind_param("i", $quiz_id_to_delete);
        $stmt_get_images->execute();
        $images_result = $stmt_get_images->get_result();
        $images_to_delete = [];
        while ($img_row = $images_result->fetch_assoc()) {
            if (!empty($img_row['image_url'])) {
                $images_to_delete[] = $img_row['image_url'];
            }
        }
        $stmt_get_images->close();

        // Note: Assuming ON DELETE CASCADE is set for options related to questions,
        // and for questions related to quizzes in your DB.
        // If not, you'd need to delete options, then questions explicitly before deleting the quiz.
        // For this example, we'll focus on deleting the quiz and its question images.

        // Explicitly delete questions first to trigger any related logic if needed (or rely on CASCADE)
        $sql_delete_questions = "DELETE FROM questions WHERE quiz_id = ?";
        $stmt_delete_qs = $conn->prepare($sql_delete_questions);
        $stmt_delete_qs->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_qs->execute()) throw new Exception("প্রশ্ন ডিলিট করতে সমস্যা: " . $stmt_delete_qs->error);
        $stmt_delete_qs->close();
        
        // Then delete the quiz
        $sql_delete_quiz = "DELETE FROM quizzes WHERE id = ?";
        $stmt_delete_quiz = $conn->prepare($sql_delete_quiz);
        $stmt_delete_quiz->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_quiz->execute()) {
            throw new Exception("কুইজ ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete_quiz->error);
        }
        $stmt_delete_quiz->close();

        // Delete associated image files
        foreach ($images_to_delete as $image_url) {
            $image_path = QUESTION_IMAGE_UPLOAD_DIR_MANAGE . basename($image_url);
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $conn->commit();
        $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id_to_delete}) এবং এর সাথে সম্পর্কিত প্রশ্ন ও ছবি সফলভাবে ডিলিট করা হয়েছে।";
        $_SESSION['flash_message_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = "কুইজ ডিলিট করার সময় ত্রুটি: " . $e->getMessage();
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: manage_quizzes.php");
    exit;
}


require_once 'includes/header.php';

// Fetch all quizzes (same as before)
$quizzes = [];
$sql_quizzes = "SELECT q.id, q.title, q.status, q.duration_minutes, COUNT(qs.id) as question_count 
                FROM quizzes q 
                LEFT JOIN questions qs ON q.id = qs.quiz_id 
                GROUP BY q.id
                ORDER BY q.created_at DESC";
$result_quizzes = $conn->query($sql_quizzes);
if ($result_quizzes && $result_quizzes->num_rows > 0) {
    while ($row = $result_quizzes->fetch_assoc()) {
        $quizzes[] = $row;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1>কুইজ ম্যানেজমেন্ট</h1>
        <a href="add_quiz.php" class="btn btn-primary">নতুন কুইজ যোগ করুন</a>
    </div>

    <?php display_flash_message(); ?>

    <div class="card">
        <div class="card-header">
            সকল কুইজের তালিকা
        </div>
        <div class="card-body">
            <?php if (!empty($quizzes)): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>শিরোনাম</th>
                        <th>স্ট্যাটাস</th>
                        <th>সময় (মিনিট)</th>
                        <th>প্রশ্ন সংখ্যা</th>
                        <th>একশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td><?php echo $quiz['id']; ?></td>
                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                        <td>
                            <?php
                                if ($quiz['status'] == 'draft') echo '<span class="badge bg-secondary">ড্রাফট</span>';
                                elseif ($quiz['status'] == 'live') echo '<span class="badge bg-success">লাইভ</span>';
                                elseif ($quiz['status'] == 'archived') echo '<span class="badge bg-warning text-dark">আর্কাইভড</span>';
                            ?>
                        </td>
                        <td><?php echo $quiz['duration_minutes']; ?></td>
                        <td><?php echo $quiz['question_count']; ?></td>
                        <td>
                            <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info">এডিট</a>
                            <a href="manage_quizzes.php?action=delete&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই কুইজটি ডিলিট করতে চান? এর সাথে সম্পর্কিত সকল প্রশ্ন, উত্তর এবং ছবি মুছে যাবে।');">ডিলিট</a>
                            <a href="../quiz_page.php?id=<?php echo $quiz['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">দেখুন</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-center">এখনও কোনো কুইজ তৈরি করা হয়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>