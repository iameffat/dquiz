<?php
$page_title = "আমার প্রোফাইল";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php'; // Session is started here
require_once 'includes/functions.php';

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's quiz attempts history
$attempts_history = [];
$sql_history = "
    SELECT 
        qa.id as attempt_id,
        q.id as quiz_id,
        q.title as quiz_title,
        qa.score,
        qa.time_taken_seconds,
        qa.submitted_at,
        (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as total_questions_in_quiz
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.user_id = ? AND qa.end_time IS NOT NULL AND qa.score IS NOT NULL
    ORDER BY qa.submitted_at DESC
";

if ($stmt_history = $conn->prepare($sql_history)) {
    $stmt_history->bind_param("i", $user_id);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row = $result_history->fetch_assoc()) {
        $attempts_history[] = $row;
    }
    $stmt_history->close();
} else {
    error_log("Error preparing statement for quiz history: " . $conn->error);
}

// --- START: Fetching Live, Upcoming, Archived Quizzes for Profile Page ---
$user_id_for_check = $user_id; 

if (!function_exists('hasUserAttemptedQuiz')) {
    function hasUserAttemptedQuiz($conn_func, $user_id_func, $quiz_id_func) {
        if ($user_id_func === null || !$conn_func) {
            return [false, null];
        }
        $sql_check_func = "SELECT id FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? LIMIT 1";
        $stmt_check_func = $conn_func->prepare($sql_check_func);
        if (!$stmt_check_func) {
            error_log("Prepare failed for hasUserAttemptedQuiz (profile.php): (" . $conn_func->errno . ") " . $conn_func->error);
            return [false, null];
        }
        $stmt_check_func->bind_param("ii", $user_id_func, $quiz_id_func);
        if (!$stmt_check_func->execute()) {
            error_log("Execute failed for hasUserAttemptedQuiz (profile.php): (" . $stmt_check_func->errno . ") " . $stmt_check_func->error);
            $stmt_check_func->close();
            return [false, null];
        }
        $result_check_func = $stmt_check_func->get_result();
        $attempt_info_func = $result_check_func->fetch_assoc();
        $stmt_check_func->close();
        return [$result_check_func->num_rows > 0, $attempt_info_func ? $attempt_info_func['id'] : null];
    }
}

$max_quizzes_per_category_profile = 3;

// Fetch Live Quizzes
$live_quizzes_profile_simple = [];
$sql_live_profile_simple = "SELECT q.id, q.title FROM quizzes q
             WHERE q.status = 'live'
             AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW())
             AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())
             ORDER BY q.live_start_datetime DESC, q.created_at DESC, q.id DESC
             LIMIT {$max_quizzes_per_category_profile}";
$result_live_profile_simple = $conn->query($sql_live_profile_simple);
if ($result_live_profile_simple && $result_live_profile_simple->num_rows > 0) {
    while ($row = $result_live_profile_simple->fetch_assoc()) {
        $live_quizzes_profile_simple[] = $row;
    }
}

// Fetch Upcoming Quizzes
$upcoming_quizzes_profile_simple = [];
$sql_upcoming_profile_simple = "SELECT q.id, q.title, q.live_start_datetime FROM quizzes q
                 WHERE q.status = 'upcoming'
                 AND (q.live_start_datetime IS NULL OR q.live_start_datetime > NOW())
                 ORDER BY q.live_start_datetime ASC, q.created_at ASC, q.id DESC
                 LIMIT {$max_quizzes_per_category_profile}";
$result_upcoming_profile_simple = $conn->query($sql_upcoming_profile_simple);
if ($result_upcoming_profile_simple && $result_upcoming_profile_simple->num_rows > 0) {
    while ($row = $result_upcoming_profile_simple->fetch_assoc()) {
        $upcoming_quizzes_profile_simple[] = $row;
    }
}

// Fetch Archived Quizzes
$archived_quizzes_profile_simple = [];
$sql_archived_profile_simple = "SELECT q.id, q.title FROM quizzes q
                WHERE q.status = 'archived'
                OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW())
                ORDER BY q.live_end_datetime DESC, q.created_at DESC, q.id DESC
                LIMIT {$max_quizzes_per_category_profile}";
$result_archived_profile_simple = $conn->query($sql_archived_profile_simple);
if ($result_archived_profile_simple) {
    while ($row = $result_archived_profile_simple->fetch_assoc()) {
        $is_already_live_or_upcoming = false;
        foreach ($live_quizzes_profile_simple as $live_quiz) { if ($live_quiz['id'] == $row['id']) { $is_already_live_or_upcoming = true; break; }}
        if (!$is_already_live_or_upcoming) {
          foreach ($upcoming_quizzes_profile_simple as $upcoming_quiz) { if ($upcoming_quiz['id'] == $row['id']) { $is_already_live_or_upcoming = true; break; }}
        }
        if (!$is_already_live_or_upcoming) {
            $archived_quizzes_profile_simple[] = $row;
        }
    }
}
// --- END: Fetching Quizzes for simple list ---


$page_specific_styles = "
    .simple-quiz-list .list-group-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 1rem; /* Reduced padding */
        font-size: 0.9rem; /* Slightly smaller font */
    }
    .simple-quiz-list .quiz-title {
        flex-grow: 1;
        margin-right: 1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .simple-quiz-list .btn-sm-custom {
        font-size: 0.8rem; /* Smaller button */
        padding: 0.25rem 0.6rem;
    }
    .simple-quiz-list .badge {
        font-size: 0.7rem;
        margin-left: 0.5rem;
    }
    .profile-sidebar-quiz-section {
        background-color: var(--bs-tertiary-bg);
        padding: 1rem;
        border-radius: var(--bs-border-radius);
        border: 1px solid var(--bs-border-color);
    }
    body.dark-mode .profile-sidebar-quiz-section {
         background-color: var(--bs-gray-800);
         border-color: var(--bs-gray-700);
    }
    .profile-sidebar-quiz-section h5 {
        margin-bottom: 0.75rem;
        font-weight: 600;
        color: var(--bs-emphasis-color);
        border-bottom: 1px solid var(--bs-border-color);
        padding-bottom: 0.5rem;
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">আমার কুইজের ইতিহাস</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($attempts_history)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>কুইজের নাম</th>
                                    <th>তারিখ ও সময়</th>
                                    <th>স্কোর</th>
                                    <th>সময় লেগেছে</th>
                                    <th>একশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts_history as $index => $attempt): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                                    <td><?php echo date("d M Y, h:i A", strtotime($attempt['submitted_at'])); ?></td>
                                    <td><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions_in_quiz']; ?></td>
                                    <td><?php echo $attempt['time_taken_seconds'] ? gmdate("H:i:s", $attempt['time_taken_seconds']) : 'N/A'; ?></td>
                                    <td>
                                        <a href="results.php?attempt_id=<?php echo $attempt['attempt_id']; ?>&quiz_id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-sm btn-outline-primary mb-1" title="ফলাফল দেখুন">ফলাফল</a>
                                        <a href="ranking.php?quiz_id=<?php echo $attempt['quiz_id']; ?>&attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-outline-info mb-1" title="র‍্যাংকিং দেখুন">র‍্যাংকিং</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center alert alert-info">আপনি এখনও কোনো কুইজে অংশগ্রহণ করেননি। <a href="quizzes.php">কুইজগুলোতে অংশগ্রহণ করুন!</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">প্রোফাইল তথ্য</h4>
                </div>
                <div class="card-body">
                    <p><strong>নাম:</strong> <?php echo htmlspecialchars($_SESSION["name"]); ?></p>
                    <p><strong>ইমেইল:</strong> <?php echo htmlspecialchars($_SESSION["email"]); ?></p>
                    <p><strong>মোবাইল নম্বর:</strong> <?php echo htmlspecialchars($_SESSION["mobile_number"]); ?></p>
                    
                    <a href="edit_profile.php" class="btn btn-secondary w-100 mb-2">প্রোফাইল এডিট করুন</a>
                    <a href="change_password.php" class="btn btn-warning w-100 mb-2">পাসওয়ার্ড পরিবর্তন করুন</a> <hr>
                    <a href="logout.php" class="btn btn-danger w-100">লগআউট করুন</a>
                    <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin'): ?>
                        <a href="<?php echo $base_url; ?>admin/index.php" class="btn btn-info w-100 mt-2">অ্যাডমিন ড্যাশবোর্ডে যান</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-sidebar-quiz-section">
                <?php if (!empty($live_quizzes_profile_simple)): ?>
                    <h5>সরাসরি কুইজ</h5>
                    <ul class="list-group list-group-flush simple-quiz-list mb-3">
                        <?php foreach ($live_quizzes_profile_simple as $quiz): ?>
                            <?php list($attempted_simple, $attempt_id_simple) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']); ?>
                            <li class="list-group-item">
                                <span class="quiz-title" title="<?php echo escape_html($quiz['title']); ?>"><?php echo escape_html(mb_strimwidth($quiz['title'], 0, 30, "...")); ?></span>
                                <?php if ($attempted_simple): ?>
                                    <a href="results.php?attempt_id=<?php echo $attempt_id_simple; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-outline-info btn-sm-custom">ফলাফল</a>
                                <?php else: ?>
                                    <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-success btn-sm-custom">অংশগ্রহণ</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($upcoming_quizzes_profile_simple)): ?>
                    <h5>আসন্ন কুইজ</h5>
                    <ul class="list-group list-group-flush simple-quiz-list mb-3">
                        <?php foreach ($upcoming_quizzes_profile_simple as $quiz): ?>
                            <li class="list-group-item">
                                <span class="quiz-title" title="<?php echo escape_html($quiz['title']); ?>"><?php echo escape_html(mb_strimwidth($quiz['title'], 0, 25, "...")); ?></span>
                                <button class="btn btn-info btn-sm-custom" disabled>শীঘ্রই আসছে</button>
                                <?php if ($quiz['live_start_datetime']): ?>
                                    <span class="badge bg-light text-dark ms-1" title="শুরুর তারিখ ও সময়"><small><?php echo format_datetime($quiz['live_start_datetime'], "d M, h:i A"); ?></small></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($archived_quizzes_profile_simple)): ?>
                    <h5>আর্কাইভ কুইজ</h5>
                    <ul class="list-group list-group-flush simple-quiz-list mb-0">
                        <?php foreach ($archived_quizzes_profile_simple as $quiz): ?>
                             <?php list($attempted_simple_archived, $attempt_id_simple_archived) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']); ?>
                            <li class="list-group-item">
                                <span class="quiz-title" title="<?php echo escape_html($quiz['title']); ?>"><?php echo escape_html(mb_strimwidth($quiz['title'], 0, 30, "...")); ?></span>
                                 <?php if ($attempted_simple_archived): ?>
                                    <a href="results.php?attempt_id=<?php echo $attempt_id_simple_archived; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-outline-secondary btn-sm-custom">ফলাফল</a>
                                <?php else: ?>
                                <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-secondary btn-sm-custom">অনুশীলন</a>
                                 <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (empty($live_quizzes_profile_simple) && empty($upcoming_quizzes_profile_simple) && empty($archived_quizzes_profile_simple)): ?>
                     <p class="text-muted text-center small">আপাতত কোনো কুইজ নেই।</p>
                <?php endif; ?>
                 <div class="text-center mt-3">
                    <a href="quizzes.php" class="btn btn-outline-primary btn-sm w-100">সকল কুইজ দেখুন</a>
                </div>
            </div>
            </div>
    </div> 
</div>

<?php
if ($conn) { 
    $conn->close();
}
require_once 'includes/footer.php';
?>