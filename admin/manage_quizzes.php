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

        // It's generally better to delete from child tables (options) first, then questions, then quizzes.
        // Assuming options are deleted via cascade or handled elsewhere if directly linked to quizzes.
        // Delete user_answers first if they exist for this quiz
        // This part needs to be added if user_answers reference quiz_attempts which reference quizzes
        // For now, we are focusing on questions and options related to questions.

        // Delete options associated with questions of this quiz
        $sql_delete_options_indirect = "DELETE o FROM options o JOIN questions q ON o.question_id = q.id WHERE q.quiz_id = ?";
        $stmt_delete_options_indirect = $conn->prepare($sql_delete_options_indirect);
        $stmt_delete_options_indirect->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_options_indirect->execute()) throw new Exception("এই কুইজের প্রশ্নগুলির অপশন ডিলিট করতে সমস্যা: " . $stmt_delete_options_indirect->error);
        $stmt_delete_options_indirect->close();
        
        $sql_delete_questions = "DELETE FROM questions WHERE quiz_id = ?";
        $stmt_delete_qs = $conn->prepare($sql_delete_questions);
        $stmt_delete_qs->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_qs->execute()) throw new Exception("প্রশ্ন ডিলিট করতে সমস্যা: " . $stmt_delete_qs->error);
        $stmt_delete_qs->close();

        // Delete quiz_attempts associated with this quiz
        $sql_delete_attempts = "DELETE FROM quiz_attempts WHERE quiz_id = ?";
        $stmt_delete_attempts = $conn->prepare($sql_delete_attempts);
        $stmt_delete_attempts->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_attempts->execute()) throw new Exception("কুইজ এটেম্পট ডিলিট করতে সমস্যা: " . $stmt_delete_attempts->error);
        $stmt_delete_attempts->close();
        // User_answers are usually linked to attempt_id and question_id, if they are not deleted by cascade from quiz_attempts, 
        // they might need to be handled separately if directly linked or via questions.
        // Given the current structure, deleting questions should ideally cascade or handle related user_answers.
        // If user_answers has a foreign key to questions.id, they would be handled by question deletion.
        // If user_answers has FK to quiz_attempts.id, then deleting attempts should handle them.

        $sql_delete_quiz = "DELETE FROM quizzes WHERE id = ?";
        $stmt_delete_quiz = $conn->prepare($sql_delete_quiz);
        $stmt_delete_quiz->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_quiz->execute()) {
            throw new Exception("কুইজ ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete_quiz->error);
        }
        $stmt_delete_quiz->close();

        foreach ($images_to_delete as $image_url) {
            // Ensure the path is correct relative to this file's location
            $image_path_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_MANAGE . basename($image_url));
            if ($image_path_actual && strpos($image_path_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_MANAGE)) === 0 && file_exists($image_path_actual)) {
                unlink($image_path_actual);
            }
        }
        
        $conn->commit();
        $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id_to_delete}) এবং এর সাথে সম্পর্কিত সকল ডেটা সফলভাবে ডিলিট করা হয়েছে।";
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

// Fetch all quizzes
$quizzes = [];
$sql_quizzes = "SELECT q.id, q.title, q.status, q.duration_minutes, q.live_start_datetime, q.live_end_datetime, COUNT(qs.id) as question_count 
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
        <div>
            <a href="add_quiz.php" class="btn btn-primary">নতুন কুইজ যোগ করুন</a>
            <a href="import_bulk_questions.php" class="btn btn-info">বাল্ক ইম্পোর্ট</a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div class="card">
        <div class="card-header">
            সকল কুইজের তালিকা
        </div>
        <div class="card-body">
            <?php if (!empty($quizzes)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>শিরোনাম</th>
                            <th>স্ট্যাটাস</th>
                            <th>সময় (মিনিট)</th>
                            <th>প্রশ্ন সংখ্যা</th>
                            <th>লাইভ শুরু</th>
                            <th>লাইভ শেষ</th>
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
                                    $status_class = 'secondary'; // Default for draft
                                    $status_text = 'ড্রাফট';
                                    if ($quiz['status'] == 'upcoming') { $status_class = 'info'; $status_text = 'আপকামিং'; }
                                    elseif ($quiz['status'] == 'live') { $status_class = 'success'; $status_text = 'লাইভ'; }
                                    elseif ($quiz['status'] == 'archived') { $status_class = 'warning text-dark'; $status_text = 'আর্কাইভড'; }
                                    echo '<span class="badge bg-' . $status_class . '">' . $status_text . '</span>';
                                ?>
                            </td>
                            <td><?php echo $quiz['duration_minutes']; ?></td>
                            <td><?php echo $quiz['question_count']; ?></td>
                            <td><?php echo $quiz['live_start_datetime'] ? format_datetime($quiz['live_start_datetime']) : 'N/A'; ?></td>
                            <td><?php echo $quiz['live_end_datetime'] ? format_datetime($quiz['live_end_datetime']) : 'N/A'; ?></td>
                            <td>
                                <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info mb-1" title="এডিট করুন">এডিট</a>
                                <a href="duplicate_quiz.php?quiz_id_to_duplicate=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-warning mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই কুইজটি ডুপ্লিকেট করতে চান?');" title="ডুপ্লিকেট করুন">ডুপ্লিকেট</a>
                                <a href="manage_quizzes.php?action=delete&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই কুইজটি এবং এর সাথে সম্পর্কিত সকল প্রশ্ন, অপশন ও উত্তর ডিলিট করতে চান?');" title="ডিলিট করুন">ডিলিট</a>
                                 <a href="view_quiz_attempts.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success mb-1" title="ফলাফল ও অংশগ্রহণকারী দেখুন">ফলাফল </a>
                                <a href="../quiz_page.php?id=<?php echo $quiz['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-1" title="কুইজটি দেখুন">দেখুন</a>
                                
                                <?php
                                    $quiz_public_link = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])), '/') . '/quiz_page.php?id=' . $quiz['id'];
                                ?>
                                <button class="btn btn-sm btn-outline-success mb-1 copy-quiz-link-btn" data-link="<?php echo htmlspecialchars($quiz_public_link); ?>" title="কুইজের লিংক কপি করুন">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-clipboard me-1" viewBox="0 0 16 16">
                                      <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/>
                                      <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/>
                                    </svg>
                                    লিংক
                                </button>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center">এখনও কোনো কুইজ তৈরি করা হয়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const copyButtons = document.querySelectorAll('.copy-quiz-link-btn');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const linkToCopy = this.dataset.link;
            navigator.clipboard.writeText(linkToCopy).then(() => {
                const originalContent = this.innerHTML; // SVG সহ পুরো কনটেন্ট সেভ করা
                this.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-check-lg me-1" viewBox="0 0 16 16">
                      <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                    </svg>
                    কপি হয়েছে!`;
                this.classList.remove('btn-outline-success');
                this.classList.add('btn-success');
                
                setTimeout(() => {
                    this.innerHTML = originalContent; // আগের কনটেন্ট ফিরিয়ে আনা
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-success');
                }, 2000);
            }).catch(err => {
                console.error('লিংক কপি করতে সমস্যা হয়েছে: ', err);
                // Fallback for browsers that might not support clipboard API well or if user denies permission
                prompt("লিংক কপি করা যায়নি। অনুগ্রহ করে ম্যানুয়ালি কপি করুন:", linkToCopy);
            });
        });
    });
});
</script>

<?php
$conn->close();
require_once 'includes/footer.php'; 
?>