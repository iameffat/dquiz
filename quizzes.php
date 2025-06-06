<?php
// quizzes.php
$page_title = "সকল কুইজ";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$user_id_for_check = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Function to check if a user has attempted a quiz
if (!function_exists('hasUserAttemptedQuiz')) {
    function hasUserAttemptedQuiz($conn, $user_id, $quiz_id) {
        if ($user_id === null || !$conn) {
            return [false, null];
        }
        $sql_check = "SELECT id FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? LIMIT 1";
        $stmt_check = $conn->prepare($sql_check);
        if (!$stmt_check) {
            error_log("Prepare failed for hasUserAttemptedQuiz: (" . $conn->errno . ") " . $conn->error);
            return [false, null];
        }
        $stmt_check->bind_param("ii", $user_id, $quiz_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $attempt_info = $result_check->fetch_assoc();
        $stmt_check->close();
        return [$result_check->num_rows > 0, $attempt_info ? $attempt_info['id'] : null];
    }
}

// Filter logic
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$where_clause = '';
$params = [];
$types = '';

if (in_array($status_filter, ['live', 'upcoming', 'archived', 'draft'])) {
    if ($status_filter === 'archived') {
        $where_clause = "WHERE (q.status = 'archived' OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW()))";
    } elseif ($status_filter === 'live') {
        $where_clause = "WHERE q.status = 'live' AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW()) AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())";
    } else {
        $where_clause = "WHERE q.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
}

// Fetch all quizzes based on filter
$all_quizzes = [];
$sql_all = "SELECT q.id, q.title, q.description, q.duration_minutes, q.status, q.live_start_datetime, q.live_end_datetime,
            (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
            FROM quizzes q
            $where_clause
            ORDER BY 
                CASE
                    WHEN q.status = 'live' THEN 1
                    WHEN q.status = 'upcoming' THEN 2
                    WHEN q.status = 'draft' THEN 3
                    WHEN q.status = 'archived' THEN 4
                    ELSE 5
                END,
                q.created_at DESC";

$stmt_all = $conn->prepare($sql_all);
if ($stmt_all) {
    if (!empty($params)) {
        $stmt_all->bind_param($types, ...$params);
    }
    $stmt_all->execute();
    $result_all = $stmt_all->get_result();
    if ($result_all) {
        while ($row = $result_all->fetch_assoc()) {
            $all_quizzes[] = $row;
        }
    }
    $stmt_all->close();
}


$page_specific_styles = "
    .quiz-page-header {
        background: linear-gradient(135deg, var(--secondary-bg-color) 0%, var(--tertiary-bg-color) 100%);
        padding: 2.5rem 1rem;
        border-radius: .75rem;
        margin-bottom: 2.5rem;
        text-align: center;
    }
    .filter-bar {
        margin-bottom: 2rem;
    }
    .quiz-card {
        border: 1px solid var(--border-color);
        border-left-width: 4px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative; 
        overflow: hidden; 
    }
    .quiz-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
    }
    .quiz-card.status-live { 
        border-left-color: var(--bs-success); 
        background-color: var(--bs-success-bg-subtle);
    }
    .quiz-card.status-upcoming { 
        border-left-color: var(--bs-info); 
        background-color: var(--bs-info-bg-subtle);
    }
    .quiz-card.status-archived { border-left-color: var(--bs-secondary); }
    .quiz-card.status-draft { border-left-color: var(--bs-warning); }

    .quiz-title { font-weight: 600; font-size: 1.1rem; }
    .quiz-meta { font-size: 0.85rem; color: var(--text-muted-color); }
    .quiz-meta-item { display: inline-flex; align-items: center; margin-right: 1rem; }
    .quiz-meta-item svg { margin-right: 0.3rem; }
    .status-badge-corner {
        position: absolute;
        top: -1px;
        right: -1px;
        font-size: 0.7rem;
        font-weight: 700;
        padding: .4em .8em;
        border-bottom-left-radius: .5rem;
    }
    .action-buttons .btn {
        margin-right: 0.5rem;
    }
    .action-buttons .btn:last-child {
        margin-right: 0;
    }
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }

    /* Compact styles for mobile devices */
    @media (max-width: 575.98px) {
        .quiz-card .card-body {
            padding: 0.8rem;
        }
        .quiz-title {
            font-size: 1rem;
        }
        .quiz-meta {
            font-size: 0.75rem;
        }
        .quiz-meta-item {
            margin-right: 0.75rem;
        }
        .action-buttons .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.6rem;
            margin-right: 0.25rem;
        }
        .d-md-flex > div:first-child {
            margin-bottom: 0.75rem !important;
        }
        /* Mobile specific status badge position */
        .status-badge-corner {
            position: static;
            display: inline-block;
            margin-bottom: 0.5rem;
            border-radius: var(--bs-border-radius-pill);
        }
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    
    <div class="quiz-page-header">
        <h1 class="display-5">আমাদের সকল কুইজ</h1>
        <p class="lead">আপনার জ্ঞান পরীক্ষা করুন এবং বৃদ্ধি করুন ইসলামিক বিভিন্ন বিষয়ে।</p>
    </div>

    <?php display_flash_message('flash_message', 'flash_message_type'); ?>
    
    <div class="filter-bar text-center">
        <div class="btn-group" role="group" aria-label="Quiz Status Filter">
            <a href="?status=all" class="btn <?php echo ($status_filter == 'all') ? 'btn-primary' : 'btn-outline-primary'; ?>">সকল</a>
            <a href="?status=live" class="btn <?php echo ($status_filter == 'live') ? 'btn-primary' : 'btn-outline-primary'; ?>">লাইভ</a>
            <a href="?status=upcoming" class="btn <?php echo ($status_filter == 'upcoming') ? 'btn-primary' : 'btn-outline-primary'; ?>">আপকামিং</a>
            <a href="?status=archived" class="btn <?php echo ($status_filter == 'archived') ? 'btn-primary' : 'btn-outline-primary'; ?>">আর্কাইভ</a>
        </div>
    </div>

    <div class="row g-3">
        <?php if (!empty($all_quizzes)): ?>
            <?php foreach ($all_quizzes as $quiz): ?>
                <?php
                    list($attempted, $attempt_id) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                    
                    $effective_status = $quiz['status'];
                    if ($quiz['status'] == 'live') {
                        $now = new DateTime();
                        $start = $quiz['live_start_datetime'] ? new DateTime($quiz['live_start_datetime']) : null;
                        $end = $quiz['live_end_datetime'] ? new DateTime($quiz['live_end_datetime']) : null;
                        if ($end && $now > $end) {
                            $effective_status = 'archived';
                        } elseif ($start && $now < $start) {
                            $effective_status = 'upcoming';
                        }
                    }

                    $status_text = '';
                    $status_class = '';
                    switch ($effective_status) {
                        case 'live': $status_text = 'লাইভ'; $status_class = 'success'; break;
                        case 'upcoming': $status_text = 'আপকামিং'; $status_class = 'info'; break;
                        case 'archived': $status_text = 'আর্কাইভ'; $status_class = 'secondary'; break;
                        case 'draft': $status_text = 'ড্রাফট'; $status_class = 'warning'; break;
                    }
                    
                    $quiz_page_url = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];
                ?>
                <div class="col-12">
                    <div class="card quiz-card status-<?php echo $effective_status; ?>">
                        <span class="d-none d-md-block badge text-bg-<?php echo $status_class; ?> status-badge-corner"><?php echo $status_text; ?></span>
                        <div class="card-body">
                            <div class="d-md-flex justify-content-between align-items-center">
                                <div class="mb-2 mb-md-0">
                                    <span class="d-md-none badge rounded-pill text-bg-<?php echo $status_class; ?> status-badge mb-2"><?php echo $status_text; ?></span>
                                    <h5 class="card-title quiz-title mb-1"><?php echo escape_html($quiz['title']); ?></h5>
                                    <div class="quiz-meta">
                                        <span class="quiz-meta-item">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/></svg>
                                            <?php echo $quiz['duration_minutes']; ?> মিনিট
                                        </span>
                                        <span class="quiz-meta-item">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-patch-question" viewBox="0 0 16 16"><path d="M8.05 9.6c.336 0 .504-.24.554-.627.04-.534.198-.815.846-1.26.674-.475 1.05-1.09 1.05-1.971 0-.923-.756-1.539-1.691-1.539S6.31 4.23 6.31 5.153h1.021c0-.59.441-1.002 1.123-1.002.65 0 1.002.322 1.002.88 0 .54-.37.91-.984 1.32-.652.433-1.03.938-1.03 1.705z"/><path d="M10.283 4.002a2.89 2.89 0 0 1 2.924 2.924l.004.132a1.103 1.103 0 0 0 1.096 1.096l.132.004a2.89 2.89 0 0 1 2.924 2.924l-.004.132a1.103 1.103 0 0 0-1.096 1.096l-.132.004a2.89 2.89 0 0 1-2.924 2.924l-.132-.004a1.103 1.103 0 0 0-1.096-1.096l-.004-.132a2.89 2.89 0 0 1-2.924-2.924l.004-.132a1.103 1.103 0 0 0 1.096-1.096l.132-.004a2.89 2.89 0 0 1 2.924-2.924l.132.004a1.103 1.103 0 0 0 1.096-1.096zM8.5 11.5a1 1 0 1 0-2 0 1 1 0 0 0 2 0"/></svg>
                                            <?php echo $quiz['question_count']; ?> টি প্রশ্ন
                                        </span>
                                    </div>
                                </div>
                                <div class="text-md-end mt-2 mt-md-0">
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-outline-dark" onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo $quiz_page_url; ?>', this)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16"><path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/></svg>
                                            শেয়ার
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary syllabus-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#syllabusModal"
                                                data-quiz-title="<?php echo escape_html($quiz['title']); ?>"
                                                data-quiz-description="<?php echo escape_html($quiz['description']); ?>">
                                            সিলেবাস
                                        </button>
                                        <?php if ($effective_status == 'draft' || $effective_status == 'upcoming'): ?>
                                            <button class="btn btn-sm btn-secondary" disabled>অংশগ্রহণ</button>
                                        <?php else: ?>
                                            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                                                <?php if ($attempted): ?>
                                                    <a href="results.php?attempt_id=<?php echo $attempt_id; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info">ফলাফল</a>
                                                <?php else: ?>
                                                    <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success">অংশগ্রহণ</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz['id']); ?>" class="btn btn-sm btn-primary">অংশগ্রহণের জন্য লগইন</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning text-center">
                    এই ফিল্টার অনুযায়ী কোনো কুইজ পাওয়া যায়নি। <a href="quizzes.php">সকল কুইজ দেখুন।</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<div class="modal fade" id="syllabusModal" tabindex="-1" aria-labelledby="syllabusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="syllabusModalLabel">কুইজের সিলেবাস</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="syllabusModalBody">
        </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const syllabusModal = document.getElementById('syllabusModal');
    if (syllabusModal) {
        syllabusModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget; // Button that triggered the modal
            const quizTitle = button.getAttribute('data-quiz-title');
            let quizDescription = button.getAttribute('data-quiz-description');

            const modalTitle = syllabusModal.querySelector('.modal-title');
            const modalBody = syllabusModal.querySelector('.modal-body');

            modalTitle.textContent = 'সিলেবাস: ' + quizTitle;

            // Check if description is empty or just contains empty HTML tags
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = quizDescription;
            if (!quizDescription || tempDiv.textContent.trim() === '') {
                quizDescription = '<p class="text-muted">এই কুইজের জন্য কোনো সিলেবাস বা বিবরণ যোগ করা হয়নি।</p>';
            }
            
            modalBody.innerHTML = quizDescription;
        });
    }
});
</script>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>