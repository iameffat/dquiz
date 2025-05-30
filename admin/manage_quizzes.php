<?php
$page_title = "কুইজ ম্যানেজমেন্ট";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';
// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['quiz_id'])) {
    $quiz_id_to_delete = intval($_GET['quiz_id']);
    // First, delete related options and questions to maintain referential integrity if ON DELETE CASCADE is not set for all FKs
    // Assuming ON DELETE CASCADE is set for questions->options and quizzes->questions

    $sql_delete_quiz = "DELETE FROM quizzes WHERE id = ?";
    if ($stmt_delete = $conn->prepare($sql_delete_quiz)) {
        $stmt_delete->bind_param("i", $quiz_id_to_delete);
        if ($stmt_delete->execute()) {
            $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id_to_delete}) সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "কুইজ ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete->error;
            $_SESSION['flash_message_type'] = "danger";
        }
        $stmt_delete->close();
    } else {
        $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: manage_quizzes.php"); // Redirect to refresh the page
    exit;
}


require_once 'includes/header.php';

// Fetch all quizzes
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

    <?php
    if (isset($_SESSION['flash_message'])) {
        echo '<div class="alert alert-' . $_SESSION['flash_message_type'] . ' alert-dismissible fade show" role="alert">';
        echo $_SESSION['flash_message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_message_type']);
    }
    ?>

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
                            <a href="manage_quizzes.php?action=delete&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই কুইজটি ডিলিট করতে চান? এর সাথে সম্পর্কিত সকল প্রশ্ন ও উত্তর মুছে যাবে।');">ডিলিট</a>
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