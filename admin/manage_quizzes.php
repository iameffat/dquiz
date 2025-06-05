<?php
$page_title = "কুইজ ম্যানেজমেন্ট";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

define('QUESTION_IMAGE_UPLOAD_DIR_MANAGE', '../uploads/question_images/');

// Handle Delete Action (Your existing delete logic remains here - unchanged)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['quiz_id'])) {
    $quiz_id_to_delete = intval($_GET['quiz_id']);
    
    $conn->begin_transaction();
    try {
        // ... (আপনার ডিলিট কোড যেমন ছিল) ...
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

        $sql_delete_attempts = "DELETE FROM quiz_attempts WHERE quiz_id = ?";
        $stmt_delete_attempts = $conn->prepare($sql_delete_attempts);
        $stmt_delete_attempts->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_attempts->execute()) throw new Exception("কুইজ এটেম্পট ডিলিট করতে সমস্যা: " . $stmt_delete_attempts->error);
        $stmt_delete_attempts->close();

        $sql_delete_quiz = "DELETE FROM quizzes WHERE id = ?";
        $stmt_delete_quiz = $conn->prepare($sql_delete_quiz);
        $stmt_delete_quiz->bind_param("i", $quiz_id_to_delete);
        if (!$stmt_delete_quiz->execute()) {
            throw new Exception("কুইজ ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete_quiz->error);
        }
        $stmt_delete_quiz->close();

        foreach ($images_to_delete as $image_url) {
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
    // Redirect back to the same page with filter preserved
    $redirect_url = "manage_quizzes.php";
    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
        $redirect_url .= "?status_filter=" . urlencode($_GET['status_filter']);
    }
    header("Location: " . $redirect_url);
    exit;
}


require_once 'includes/header.php';

// --- Filter Logic ---
$where_clauses = [];
$params = [];
$types = "";

// Status Filter
$filter_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
if (!empty($filter_status) && in_array($filter_status, ['draft', 'upcoming', 'live', 'archived'])) {
    if ($filter_status === 'archived') {
        $where_clauses[] = "(q.status = ? OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW()))";
        $params[] = 'archived'; // Keep original status for query parameter
        $types .= "s";
    } elseif ($filter_status === 'live') {
        $where_clauses[] = "q.status = ? AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW()) AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())";
        $params[] = 'live';
        $types .= "s";
    } else { // For 'draft' and 'upcoming'
        $where_clauses[] = "q.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
}

// Fetch quizzes with filtering
$quizzes = [];
$sql_quizzes = "SELECT q.id, q.title, q.status, q.duration_minutes, q.live_start_datetime, q.live_end_datetime, q.created_at, COUNT(qs.id) as question_count 
                FROM quizzes q 
                LEFT JOIN questions qs ON q.id = qs.quiz_id";

if (!empty($where_clauses)) {
    $sql_quizzes .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_quizzes .= " GROUP BY q.id ORDER BY q.created_at DESC";

$stmt_quizzes = $conn->prepare($sql_quizzes);

if ($stmt_quizzes) {
    if (!empty($params)) {
        $stmt_quizzes->bind_param($types, ...$params);
    }
    $stmt_quizzes->execute();
    $result_quizzes = $stmt_quizzes->get_result();
    if ($result_quizzes) {
        while ($row = $result_quizzes->fetch_assoc()) {
            $quizzes[] = $row;
        }
    } else {
        echo '<div class="alert alert-danger">কুইজ আনতে সমস্যা হয়েছে: ' . $conn->error . '</div>';
    }
    $stmt_quizzes->close();
} else {
    echo '<div class="alert alert-danger">কুইজ স্টেটমেন্ট প্রস্তুত করতে সমস্যা: ' . $conn->error . '</div>';
}

?>

<div class="container-fluid">
    <div class="d-sm-flex justify-content-between align-items-center mt-4 mb-3">
        <h1>কুইজ ম্যানেজমেন্ট</h1>
        <div class="d-flex align-items-center mt-2 mt-sm-0">
            <form action="manage_quizzes.php" method="get" class="d-flex align-items-center me-3">
                <label for="status_filter" class="form-label me-2 mb-0 text-nowrap">স্ট্যাটাস:</label>
                <select class="form-select form-select-sm me-2" id="status_filter" name="status_filter" onchange="this.form.submit()">
                    <option value="">সকল</option>
                    <option value="draft" <?php echo ($filter_status == 'draft') ? 'selected' : ''; ?>>ড্রাফট</option>
                    <option value="upcoming" <?php echo ($filter_status == 'upcoming') ? 'selected' : ''; ?>>আপকামিং</option>
                    <option value="live" <?php echo ($filter_status == 'live') ? 'selected' : ''; ?>>লাইভ</option>
                    <option value="archived" <?php echo ($filter_status == 'archived') ? 'selected' : ''; ?>>আর্কাইভড</option>
                </select>
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">ফিল্টার</button></noscript>
            </form>
            <a href="add_quiz.php" class="btn btn-primary btn-sm me-2 text-nowrap">নতুন কুইজ</a>
            <a href="import_bulk_questions.php" class="btn btn-info btn-sm text-nowrap">বাল্ক ইম্পোর্ট</a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div class="card">
        <div class="card-header">
            কুইজের তালিকা
            <?php if (!empty($filter_status)): ?>
                (ফিল্টার: <?php echo htmlspecialchars(ucfirst($filter_status)); ?>)
                <a href="manage_quizzes.php" class="ms-2 badge bg-light text-dark text-decoration-none">ফিল্টার মুছুন</a>
            <?php endif; ?>
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
                            <th>প্রশ্ন</th>
                            <th>লাইভ শুরু</th>
                            <th>লাইভ শেষ</th>
                            <th>তৈরি</th>
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
                                    $display_status = $quiz['status'];
                                    $status_class = 'secondary'; 
                                    $status_text = 'ড্রাফট';

                                    if ($quiz['status'] == 'live') {
                                        $now_time = time();
                                        $start_time = !empty($quiz['live_start_datetime']) ? strtotime($quiz['live_start_datetime']) : null;
                                        $end_time = !empty($quiz['live_end_datetime']) ? strtotime($quiz['live_end_datetime']) : null;

                                        if ($end_time && $now_time > $end_time) {
                                            $display_status = 'archived';
                                        } elseif ($start_time && $now_time < $start_time) {
                                            $display_status = 'upcoming';
                                        } else {
                                            $display_status = 'live';
                                        }
                                    }

                                    if ($display_status == 'upcoming') { $status_class = 'info'; $status_text = 'আপকামিং'; }
                                    elseif ($display_status == 'live') { $status_class = 'success'; $status_text = 'লাইভ'; }
                                    elseif ($display_status == 'archived') { $status_class = 'warning text-dark'; $status_text = 'আর্কাইভড'; }
                                    elseif ($display_status == 'draft') { $status_class = 'secondary'; $status_text = 'ড্রাফট';}

                                    echo '<span class="badge bg-' . $status_class . '">' . $status_text . '</span>';
                                ?>
                            </td>
                            <td><?php echo $quiz['duration_minutes']; ?></td>
                            <td><?php echo $quiz['question_count']; ?></td>
                            <td><?php echo $quiz['live_start_datetime'] ? format_datetime($quiz['live_start_datetime'], "d M Y, h:i A") : 'N/A'; ?></td>
                            <td><?php echo $quiz['live_end_datetime'] ? format_datetime($quiz['live_end_datetime'], "d M Y, h:i A") : 'N/A'; ?></td>
                            <td><?php echo format_datetime($quiz['created_at'], "d M Y"); ?></td>
                            <td>
                               <div class="btn-group" role="group" aria-label="Quiz Actions">
    <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info me-1" title="এডিট করুন">এডিট</a>
    <a href="duplicate_quiz.php?quiz_id_to_duplicate=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-warning me-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই কুইজটি ডুপ্লিকেট করতে চান?');" title="ডুপ্লিকেট করুন">ডুপ্লিকেট</a>
    <a href="manage_quizzes.php?action=delete&quiz_id=<?php echo $quiz['id']; ?>&status_filter=<?php echo urlencode($filter_status); ?>" class="btn btn-sm btn-danger me-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই কুইজটি এবং এর সাথে সম্পর্কিত সকল প্রশ্ন, অপশন ও উত্তর ডিলিট করতে চান?');" title="ডিলিট করুন">ডিলিট</a>
    <a href="view_quiz_attempts.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success me-1" title="ফলাফল ও অংশগ্রহণকারী দেখুন">ফলাফল</a>
    <a href="../quiz_page.php?id=<?php echo $quiz['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="কুইজটি দেখুন">দেখুন</a>
    <?php
        $quiz_public_link = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])), '/') . '/quiz_page.php?id=' . $quiz['id'];
    ?>
    <button class="btn btn-sm btn-outline-primary copy-quiz-link-btn" data-link="<?php echo htmlspecialchars($quiz_public_link); ?>" title="কুইজের লিংক কপি করুন">লিংক</button> </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center">
                <?php if (!empty($filter_status)): ?>
                    "<?php echo htmlspecialchars(ucfirst($filter_status)); ?>" স্ট্যাটাসের কোনো কুইজ পাওয়া যায়নি। <a href="manage_quizzes.php">সকল কুইজ দেখুন</a>
                <?php else: ?>
                    এখনও কোনো কুইজ তৈরি করা হয়নি।
                <?php endif; ?>
            </p>
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
                const originalText = this.textContent; // Only text content for simplicity
                this.textContent = 'কপি হয়েছে!';
                const originalClasses = Array.from(this.classList);
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.classList.remove('btn-primary');
                    originalClasses.forEach(cls => { if(cls !== 'copy-quiz-link-btn' && cls !== 'btn' && cls !== 'btn-sm') this.classList.add(cls) });
                     this.classList.add('btn-outline-primary');


                }, 2000);
            }).catch(err => {
                console.error('লিংক কপি করতে সমস্যা হয়েছে: ', err);
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