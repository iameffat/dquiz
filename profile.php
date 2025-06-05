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
    // This function should be in includes/functions.php
    // For robustness, defining it here if somehow not available (though it should be)
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

$max_quizzes_per_category = 3;

// Fetch Live Quizzes
$live_quizzes_profile = [];
$sql_live_profile = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime, q.status,
             (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
             FROM quizzes q
             WHERE q.status = 'live'
             AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW())
             AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())
             ORDER BY q.live_start_datetime DESC, q.created_at DESC, q.id DESC
             LIMIT {$max_quizzes_per_category}";
$result_live_profile = $conn->query($sql_live_profile);
if ($result_live_profile && $result_live_profile->num_rows > 0) {
    while ($row = $result_live_profile->fetch_assoc()) {
        $live_quizzes_profile[] = $row;
    }
}

// Fetch Upcoming Quizzes
$upcoming_quizzes_profile = [];
$sql_upcoming_profile = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime, q.status,
                 (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                 FROM quizzes q
                 WHERE q.status = 'upcoming'
                 AND (q.live_start_datetime IS NULL OR q.live_start_datetime > NOW())
                 ORDER BY q.live_start_datetime ASC, q.created_at ASC, q.id DESC
                 LIMIT {$max_quizzes_per_category}";
$result_upcoming_profile = $conn->query($sql_upcoming_profile);
if ($result_upcoming_profile && $result_upcoming_profile->num_rows > 0) {
    while ($row = $result_upcoming_profile->fetch_assoc()) {
        $upcoming_quizzes_profile[] = $row;
    }
}

// Fetch Archived Quizzes
$archived_quizzes_profile = [];
$sql_archived_profile = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime, q.status,
                (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                FROM quizzes q
                WHERE q.status = 'archived'
                OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW())
                ORDER BY q.live_end_datetime DESC, q.created_at DESC, q.id DESC
                LIMIT {$max_quizzes_per_category}";

$result_archived_profile = $conn->query($sql_archived_profile);
if ($result_archived_profile) {
    while ($row = $result_archived_profile->fetch_assoc()) {
        $is_already_live_or_upcoming = false;
        foreach ($live_quizzes_profile as $live_quiz) { if ($live_quiz['id'] == $row['id']) { $is_already_live_or_upcoming = true; break; }}
        if (!$is_already_live_or_upcoming) {
          foreach ($upcoming_quizzes_profile as $upcoming_quiz) { if ($upcoming_quiz['id'] == $row['id']) { $is_already_live_or_upcoming = true; break; }}
        }
        if (!$is_already_live_or_upcoming) {
            $archived_quizzes_profile[] = $row;
        }
    }
}
// --- END: Fetching Quizzes ---

$page_specific_styles = "
    .quiz-item-card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color-translucent);
        border-radius: 15px; 
        box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
        display: flex;
        flex-direction: column;
        height: 100%; 
        overflow: hidden; 
        position: relative; 
    }
    .quiz-item-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.12);
    }
    .quiz-item-card .card-header-custom {
        padding: 1rem 1.25rem; /* Slightly adjusted padding */
        border-bottom: 1px solid var(--border-color);
    }
    .quiz-item-card .card-title-custom {
        font-size: 1.1rem; /* Slightly smaller for profile page */
        font-weight: 600;
        color: var(--bs-primary-text-emphasis); 
        margin-bottom: 0.3rem;
    }
     .quiz-item-card .quiz-status-badge-inline {
        font-size: 0.7rem;
        padding: .3em .6em;
        font-weight: 600;
        border-radius: 10px;
        display: inline-block;
        margin-top: 0.25rem;
    }
    .live-quiz .quiz-status-badge-inline { background-color: var(--success-color); color: white;}
    .upcoming-quiz .quiz-status-badge-inline { background-color: var(--info-color); color: white;}
    .archived-quiz .quiz-status-badge-inline { background-color: var(--secondary-color); color: white;}

    .quiz-item-card .card-body-custom {
        padding: 1rem 1.25rem; /* Slightly adjusted padding */
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .quiz-item-card .quiz-description-custom {
        font-size: 0.85rem; /* Smaller font for description */
        color: var(--body-color);
        margin-bottom: 1rem; /* Adjusted margin */
        line-height: 1.6;
        max-height: calc(1.6em * 2); /* Max 2 lines for profile page */
        overflow-y: auto; 
    }
    .quiz-item-card .quiz-description-custom.no-real-description {
        min-height: auto; 
        margin-bottom: 0.5rem; 
        flex-grow: 0; 
        max-height: none; 
        overflow-y: visible; 
    }
    .quiz-item-card .quiz-description-custom.no-real-description p.text-muted {
        margin-bottom: 0; 
    }
    .quiz-description-custom p { margin-bottom: 0.5rem; }
    .quiz-description-custom p:last-child { margin-bottom: 0; }
    
    .quiz-details-custom {
        list-style: none;
        padding-left: 0;
        font-size: 0.8rem; /* Smaller font for details */
        color: var(--text-muted-color);
        margin-bottom: 1rem; /* Adjusted margin */
        margin-top: auto; 
    }
    .quiz-details-custom li {
        margin-bottom: 0.3rem; /* Adjusted margin */
        display: flex;
        align-items: center;
    }
    .quiz-details-custom li i { 
        margin-right: 6px; /* Adjusted margin */
        color: var(--primary-color);
        width: 14px; /* Adjusted size */
        text-align: center;
    }
    .quiz-details-custom li strong {
        color: var(--body-color);
    }
    .quiz-item-card .btn-action-group { 
        display: flex;
        flex-wrap: wrap; 
        gap: 0.5rem; 
        margin-top: auto; 
    }
    .quiz-item-card .btn-action {
        font-size: 0.85rem; /* Smaller button font */
        padding: 0.5rem 1rem; /* Adjusted padding */
        border-radius: 50px; 
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex; 
        align-items: center; 
    }
    .quiz-item-card .btn-action svg { 
        margin-right: 0.3rem;
    }
    .quiz-item-card .btn-action:hover {
        transform: scale(1.03); 
    }
    
    .btn-success-custom { background-color: var(--success-color); border-color: var(--success-color); color: white; }
    .btn-success-custom:hover { filter: brightness(90%); }
    .btn-info-custom { background-color: var(--info-color); border-color: var(--info-color); color: white; }
    .btn-info-custom:hover { filter: brightness(90%); }
    .btn-secondary-custom { background-color: var(--secondary-color); border-color: var(--secondary-color); color: white; }
    .btn-secondary-custom:hover { filter: brightness(90%); }
    .btn-primary-custom { background-color: var(--primary-color); border-color: var(--primary-color); color: white; }
    .btn-primary-custom:hover { filter: brightness(90%); }
    .btn-outline-info-custom { border-color: var(--info-color); color: var(--info-color); }
    .btn-outline-info-custom:hover { background-color: var(--info-color); color: white; }
    .btn-outline-secondary-custom { border-color: var(--secondary-color); color: var(--secondary-color); }
    .btn-outline-secondary-custom:hover { background-color: var(--secondary-color); color: white; }

    .profile-quiz-list-header {
        background: linear-gradient(135deg, var(--secondary-bg-color) 0%, var(--bs-tertiary-bg) 100%);
        padding: 1.5rem 1rem;
        border-radius: .75rem;
        margin-top: 2.5rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    body.dark-mode .profile-quiz-list-header {
         background: linear-gradient(135deg, var(--bs-gray-800) 0%, var(--bs-gray-900) 100%);
    }
    .profile-quiz-list-header h3 {
        color: var(--bs-primary-text-emphasis);
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row">
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
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
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
    </div> <div class="profile-quiz-list-section mt-4">
        <div class="profile-quiz-list-header">
            <h3>অন্যান্য কুইজসমূহ</h3>
        </div>

        <?php if (!empty($live_quizzes_profile)): ?>
            <h4 class="mt-4 mb-3">সরাসরি কুইজ (Live Quizzes)</h4>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($live_quizzes_profile as $quiz): ?>
                    <?php 
                        list($attempted, $attempt_id) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                        $description_html = $quiz['description'] ? trim($quiz['description']) : '';
                        $is_description_empty = empty(trim(strip_tags($description_html)));
                        $quiz_page_url = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];
                    ?>
                    <div class="col">
                        <div class="card quiz-item-card live-quiz">
                            <div class="card-header-custom">
                                <h5 class="card-title-custom"><?php echo escape_html($quiz['title']); ?></h5>
                                <span class="quiz-status-badge-inline">লাইভ</span>
                            </div>
                            <div class="card-body-custom">
                                <div class="quiz-description-custom <?php echo $is_description_empty ? 'no-real-description' : ''; ?>">
                                    <?php echo $is_description_empty ? '<p class="text-muted fst-italic"><em>কোনো বিবরণ নেই।</em></p>' : $description_html; ?>
                                </div>
                                <ul class="quiz-details-custom">
                                    <li><i>🕒</i><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                    <li><i>❓</i><strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                    <?php if ($quiz['live_end_datetime']): ?>
                                        <li><i>📅</i><small>শেষ হবে: <?php echo format_datetime($quiz['live_end_datetime']); ?></small></li>
                                    <?php endif; ?>
                                </ul>
                                <div class="btn-action-group">
                                    <?php if ($attempted): ?>
                                        <a href="results.php?attempt_id=<?php echo $attempt_id; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-outline-info-custom">ফলাফল দেখুন</a>
                                    <?php else: ?>
                                        <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-success-custom">অংশগ্রহণ করুন</a>
                                    <?php endif; ?>
                                    <button class="btn btn-action btn-outline-secondary-custom" 
                                            onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($quiz_page_url, ENT_QUOTES); ?>', this)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16"><path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/></svg> শেয়ার
                                    </button>
                                </div>
                                <?php if ($attempted): ?> <p class="small text-primary mt-2 mb-0">আপনি অংশগ্রহণ করেছেন।</p> <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($upcoming_quizzes_profile)): ?>
            <h4 class="mt-4 mb-3">আসন্ন কুইজ (Upcoming Quizzes)</h4>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($upcoming_quizzes_profile as $quiz): ?>
                    <?php
                        $description_html = $quiz['description'] ? trim($quiz['description']) : '';
                        $is_description_empty = empty(trim(strip_tags($description_html)));
                        $quiz_page_url = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];
                    ?>
                    <div class="col">
                        <div class="card quiz-item-card upcoming-quiz">
                             <div class="card-header-custom">
                                <h5 class="card-title-custom"><?php echo escape_html($quiz['title']); ?></h5>
                                 <span class="quiz-status-badge-inline">আপকামিং</span>
                            </div>
                            <div class="card-body-custom">
                                <div class="quiz-description-custom <?php echo $is_description_empty ? 'no-real-description' : ''; ?>">
                                     <?php echo $is_description_empty ? '<p class="text-muted fst-italic"><em>কোনো বিবরণ নেই।</em></p>' : $description_html; ?>
                                </div>
                                <ul class="quiz-details-custom">
                                    <li><i>🕒</i><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                    <li><i>❓</i><strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                    <?php if ($quiz['live_start_datetime']): ?>
                                        <li><i>📅</i><small>শুরু হবে: <?php echo format_datetime($quiz['live_start_datetime']); ?></small></li>
                                    <?php endif; ?>
                                </ul>
                                <div class="btn-action-group">
                                    <button class="btn btn-action btn-info-custom" disabled>শীঘ্রই আসছে...</button>
                                     <button class="btn btn-action btn-outline-secondary-custom" 
                                            onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($quiz_page_url, ENT_QUOTES); ?>', this)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16"><path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/></svg> শেয়ার
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($archived_quizzes_profile)): ?>
            <h4 class="mt-4 mb-3">আর্কাইভকৃত কুইজ (Archived Quizzes)</h4>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($archived_quizzes_profile as $quiz): ?>
                    <?php 
                        list($attempted, $attempt_id) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                        $description_html = $quiz['description'] ? trim($quiz['description']) : '';
                        $is_description_empty = empty(trim(strip_tags($description_html)));
                        $quiz_page_url = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];
                    ?>
                    <div class="col">
                        <div class="card quiz-item-card archived-quiz">
                            <div class="card-header-custom">
                                <h5 class="card-title-custom"><?php echo escape_html($quiz['title']); ?></h5>
                                 <span class="quiz-status-badge-inline">আর্কাইভ</span>
                            </div>
                            <div class="card-body-custom">
                                 <div class="quiz-description-custom <?php echo $is_description_empty ? 'no-real-description' : ''; ?>">
                                     <?php echo $is_description_empty ? '<p class="text-muted fst-italic"><em>কোনো বিবরণ নেই।</em></p>' : $description_html; ?>
                                </div>
                                <ul class="quiz-details-custom">
                                   <li><i>🕒</i><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                   <li><i>❓</i><strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                </ul>
                                <div class="btn-action-group">
                                    <?php if ($attempted): ?>
                                        <a href="results.php?attempt_id=<?php echo $attempt_id; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-outline-info-custom">ফলাফল দেখুন</a>
                                    <?php else: ?>
                                        <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-secondary-custom">অনুশীলন করুন</a>
                                    <?php endif; ?>
                                     <button class="btn btn-action btn-outline-secondary-custom" 
                                            onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($quiz_page_url, ENT_QUOTES); ?>', this)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16"><path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/></svg> শেয়ার
                                    </button>
                                </div>
                                <?php if ($attempted): ?> <p class="small text-primary mt-2 mb-0">আপনি অংশগ্রহণ করেছেন।</p> <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($live_quizzes_profile) && empty($upcoming_quizzes_profile) && empty($archived_quizzes_profile)): ?>
            <p class="alert alert-light text-center mt-4">এখন কোনো কুইজ উপলব্ধ নেই।</p>
        <?php endif; ?>
         <div class="text-center mt-4">
            <a href="quizzes.php" class="btn btn-outline-primary">সকল কুইজ দেখুন</a>
        </div>
    </div> </div>

<?php
if ($conn) { // Ensure connection is closed only if it's open
    $conn->close();
}
require_once 'includes/footer.php';
?>