<?php
$page_title = "ম্যানুয়াল প্রশ্ন ম্যানেজমেন্ট";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

// Define QUESTION_IMAGE_BASE_URL if not already defined
if (!defined('QUESTION_IMAGE_BASE_URL_MANAGE')) {
    define('QUESTION_IMAGE_BASE_URL_MANAGE', '../');
}
if (!defined('QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL')) {
    define('QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL', '../uploads/question_images/');
}


// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['question_id'])) {
    $question_id_to_delete = intval($_GET['question_id']);
    
    $conn->begin_transaction();
    try {
        // Fetch image URL to delete the file
        $sql_get_image = "SELECT image_url FROM questions WHERE id = ? AND quiz_id IS NULL";
        $stmt_get_image = $conn->prepare($sql_get_image);
        $stmt_get_image->bind_param("i", $question_id_to_delete);
        $stmt_get_image->execute();
        $image_result = $stmt_get_image->get_result();
        $image_row = $image_result->fetch_assoc();
        $stmt_get_image->close();

        // Delete from question_categories junction table
        $sql_delete_q_categories = "DELETE FROM question_categories WHERE question_id = ?";
        $stmt_delete_q_categories = $conn->prepare($sql_delete_q_categories);
        $stmt_delete_q_categories->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_q_categories->execute()) throw new Exception("প্রশ্ন-ক্যাটাগরি লিংক ডিলিট করতে সমস্যা: " . $stmt_delete_q_categories->error);
        $stmt_delete_q_categories->close();

        // Delete options for the question
        $sql_delete_options = "DELETE FROM options WHERE question_id = ?";
        $stmt_delete_options = $conn->prepare($sql_delete_options);
        $stmt_delete_options->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_options->execute()) throw new Exception("অপশন ডিলিট করতে সমস্যা: " . $stmt_delete_options->error);
        $stmt_delete_options->close();

        // Delete the question itself
        $sql_delete_q = "DELETE FROM questions WHERE id = ? AND quiz_id IS NULL";
        $stmt_delete_q = $conn->prepare($sql_delete_q);
        $stmt_delete_q->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_q->execute()) throw new Exception("প্রশ্ন ডিলিট করতে সমস্যা: " . $stmt_delete_q->error);
        $affected_rows = $stmt_delete_q->affected_rows;
        $stmt_delete_q->close();

        if ($affected_rows > 0 && $image_row && !empty($image_row['image_url'])) {
            $image_file_path_relative = $image_row['image_url'];
            $image_file_path_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL . basename($image_file_path_relative));
            if ($image_file_path_actual && strpos($image_file_path_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL)) === 0 && file_exists($image_file_path_actual)) {
                unlink($image_file_path_actual);
            }
        }
        
        $conn->commit();
        $_SESSION['flash_message'] = "প্রশ্ন (ID: {$question_id_to_delete}) সফলভাবে ডিলিট করা হয়েছে।";
        $_SESSION['flash_message_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = "প্রশ্ন ডিলিট করার সময় ত্রুটি: " . $e->getMessage();
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: manage_manual_questions.php");
    exit;
}


require_once 'includes/header.php';

$manual_questions = [];
// Fetch manual questions (quiz_id IS NULL) along with their categories
$sql_fetch_manual_questions = "
    SELECT q.id, q.question_text, q.explanation, q.image_url, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories_list
    FROM questions q
    LEFT JOIN question_categories qc ON q.id = qc.question_id
    LEFT JOIN categories c ON qc.category_id = c.id
    WHERE q.quiz_id IS NULL
    GROUP BY q.id
    ORDER BY q.id DESC
";
$result_manual_questions = $conn->query($sql_fetch_manual_questions);
if ($result_manual_questions) {
    while ($row = $result_manual_questions->fetch_assoc()) {
        $manual_questions[] = $row;
    }
} else {
    // Handle query error
    $errors[] = "ম্যানুয়াল প্রশ্ন আনতে ডাটাবেস সমস্যা হয়েছে: " . $conn->error;
}

?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1><?php echo $page_title; ?></h1>
        <a href="add_manual_question.php" class="btn btn-primary">নতুন ম্যানুয়াল প্রশ্ন যোগ করুন</a>
    </div>

    <?php display_flash_message(); ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $error): echo "<li>" . htmlspecialchars($error) . "</li>"; endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            ম্যানুয়ালি যোগ করা প্রশ্নসমূহের তালিকা
        </div>
        <div class="card-body">
            <?php if (!empty($manual_questions)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>প্রশ্নের লেখা</th>
                            <th>ক্যাটাগরি</th>
                            <th>ছবি</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manual_questions as $question): ?>
                        <tr>
                            <td><?php echo $question['id']; ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($question['question_text'], 0, 100, "...")); ?></td>
                            <td><?php echo htmlspecialchars($question['categories_list'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($question['image_url'])): ?>
                                    <img src="<?php echo QUESTION_IMAGE_BASE_URL_MANAGE . htmlspecialchars($question['image_url']); ?>" alt="Question Image" style="max-height: 50px; max-width: 70px; cursor: pointer;" onclick="showImageModal('<?php echo QUESTION_IMAGE_BASE_URL_MANAGE . htmlspecialchars($question['image_url']); ?>')">
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit_manual_question.php?question_id=<?php echo $question['id']; ?>" class="btn btn-sm btn-info mb-1">এডিট</a>
                                <a href="manage_manual_questions.php?action=delete&question_id=<?php echo $question['id']; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই প্রশ্নটি এবং এর সাথে সম্পর্কিত সকল ডেটা (অপশন, ক্যাটাগরি লিংক) ডিলিট করতে চান?');">ডিলিট</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center">এখনও কোনো ম্যানুয়াল প্রশ্ন যোগ করা হয়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imagePreviewModalLabel">প্রশ্নের ছবি</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="modalImagePreview" class="img-fluid" alt="Question Image Preview">
      </div>
    </div>
  </div>
</div>

<script>
function showImageModal(imageUrl) {
    document.getElementById('modalImagePreview').src = imageUrl;
    var imageModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    imageModal.show();
}
</script>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>