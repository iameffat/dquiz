<?php
// quizzes.php
$page_title = "সকল কুইজ";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$user_id_for_check = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// hasUserAttemptedQuiz ফাংশন (যদি functions.php তে না থাকে)
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
        if (!$stmt_check->execute()) {
            error_log("Execute failed for hasUserAttemptedQuiz: (" . $stmt_check->errno . ") " . $stmt_check->error);
            $stmt_check->close();
            return [false, null];
        }
        $result_check = $stmt_check->get_result();
        $attempt_info = $result_check->fetch_assoc();
        $stmt_check->close();
        return [$result_check->num_rows > 0, $attempt_info ? $attempt_info['id'] : null];
    }
}

// Fetch Upcoming Quizzes
$upcoming_quizzes = [];
$sql_upcoming = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime,
                 (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                 FROM quizzes q
                 WHERE q.status = 'upcoming'
                 ORDER BY q.live_start_datetime ASC, q.created_at ASC, q.id DESC";
$result_upcoming = $conn->query($sql_upcoming);
if ($result_upcoming && $result_upcoming->num_rows > 0) {
    while ($row = $result_upcoming->fetch_assoc()) {
        $upcoming_quizzes[] = $row;
    }
}

// Fetch Live Quizzes
$live_quizzes = [];
$sql_live = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime,
             (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
             FROM quizzes q
             WHERE q.status = 'live'
             AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW())
             AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())
             ORDER BY q.created_at DESC, q.id DESC";
$result_live = $conn->query($sql_live);
if ($result_live && $result_live->num_rows > 0) {
    while ($row = $result_live->fetch_assoc()) {
        $live_quizzes[] = $row;
    }
}

// Fetch Archived Quizzes
$archived_quizzes = [];
$sql_archived = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime,
                (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                FROM quizzes q
                WHERE q.status = 'archived'
                OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW())
                ORDER BY q.created_at DESC, q.id DESC";
$result_archived = $conn->query($sql_archived);
if ($result_archived && $result_archived->num_rows > 0) {
    while ($row = $result_archived->fetch_assoc()) {
        $is_already_live = false;
        foreach ($live_quizzes as $live_quiz) {
            if ($live_quiz['id'] == $row['id']) {
                $is_already_live = true;
                break;
            }
        }
        if (!$is_already_live) {
            $archived_quizzes[] = $row;
        }
    }
}

$page_specific_styles = "
    .quiz-page-main-title-container {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        padding: 2.5rem 1rem;
        border-radius: 12px;
        margin-bottom: 2.5rem;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .quiz-page-main-title {
        font-weight: 700;
        color: #2c3e50; /* Dark blue-grey */
        margin-bottom: 0.5rem;
    }
    .quiz-page-main-subtitle {
        color: #52575d; /* Medium grey */
        font-size: 1.1rem;
    }

    .nav-pills-custom .nav-link {
        color: #495057;
        background-color: #e9ecef;
        margin-right: 10px;
        border-radius: 50px; /* Pill shape */
        padding: 0.6rem 1.2rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }
    .nav-pills-custom .nav-link:hover {
        background-color: #dde2e6;
    }
    .nav-pills-custom .nav-link.active {
        color: #fff;
        background-color: #007bff; /* Bootstrap primary */
        box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
    }
    .nav-pills-custom .nav-link .badge {
        margin-left: 8px;
        padding: .3em .5em;
        font-size: 0.8em;
    }
    .tab-content > .tab-pane {
        padding: 2rem 0;
    }

    .quiz-item-card {
        background-color: #fff;
        border: 1px solid #e9ecef;
        border-radius: 15px; /* Smoother radius */
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
        padding: 1.25rem;
        border-bottom: 1px solid #f1f1f1;
    }
    .quiz-item-card .card-title-custom {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50; 
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
    .live-quiz .quiz-status-badge-inline { background-color: #28a745; color: white;}
    .upcoming-quiz .quiz-status-badge-inline { background-color: #17a2b8; color: white;}
    .archived-quiz .quiz-status-badge-inline { background-color: #6c757d; color: white;}


    .quiz-item-card .card-body-custom {
        padding: 1.25rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .quiz-item-card .quiz-description-custom {
        font-size: 0.9rem;
        color: #555;
        margin-bottom: 1.25rem;
        line-height: 1.65;
        max-height: calc(1.65em * 3); /* Approximately 3 lines */
        overflow-y: auto; /* Add scroll if content exceeds max-height */
    }
    .quiz-item-card .quiz-description-custom.no-real-description {
        min-height: auto; 
        margin-bottom: 0.5rem; 
        flex-grow: 0; 
        max-height: none; /* Remove max-height for placeholder */
        overflow-y: visible; /* Remove scroll for placeholder */
    }
    .quiz-item-card .quiz-description-custom.no-real-description p.text-muted {
        margin-bottom: 0; 
    }
    /* Styling for HTML content within description */
    .quiz-description-custom p { margin-bottom: 0.5rem; }
    .quiz-description-custom p:last-child { margin-bottom: 0; }
    .quiz-description-custom ul, .quiz-description-custom ol { padding-left: 1.2rem; margin-bottom: 0.5rem; }
    .quiz-description-custom strong, .quiz-description-custom b { font-weight: 600; }
    .quiz-description-custom em, .quiz-description-custom i { font-style: italic; }
    .quiz-description-custom a { color: #007bff; text-decoration: underline; }
    .quiz-description-custom a:hover { color: #0056b3; }


    .quiz-details-custom {
        list-style: none;
        padding-left: 0;
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 1.5rem; 
        margin-top: auto; /* Push details to bottom */
    }
    .quiz-details-custom li {
        margin-bottom: 0.4rem;
        display: flex;
        align-items: center;
    }
    .quiz-details-custom li i { 
        margin-right: 8px;
        color: #007bff;
        width: 16px;
        text-align: center;
    }
    .quiz-details-custom li strong {
        color: #495057;
    }

    .quiz-item-card .btn-action-group { /* Wrapper for buttons */
        display: flex;
        flex-wrap: wrap; /* Allow buttons to wrap */
        gap: 0.5rem; /* Space between buttons */
        margin-top: auto; /* Push to bottom if description is short */
    }
    .quiz-item-card .btn-action {
        font-size: 0.9rem;
        padding: 0.6rem 1.2rem; /* Adjusted for better look with icon */
        border-radius: 50px; 
        font-weight: 500;
        /* align-self: flex-start; Removed to allow flex-wrap to manage alignment */
        transition: all 0.3s ease;
        /* margin-top: auto; Removed as group handles this */
        display: inline-flex; /* For icon alignment */
        align-items: center; /* For icon alignment */
    }
    .quiz-item-card .btn-action svg { /* For share icon */
        margin-right: 0.3rem;
    }
    .quiz-item-card .btn-action:hover {
        transform: scale(1.03); /* Slightly less aggressive hover */
    }
    
    .btn-success-custom { background-color: #28a745; border-color: #28a745; color: white; }
    .btn-success-custom:hover { background-color: #218838; border-color: #1e7e34; }
    .btn-info-custom { background-color: #17a2b8; border-color: #17a2b8; color: white; }
    .btn-info-custom:hover { background-color: #138496; border-color: #117a8b; }
    .btn-secondary-custom { background-color: #6c757d; border-color: #6c757d; color: white; }
    .btn-secondary-custom:hover { background-color: #5a6268; border-color: #545b62; }
    .btn-primary-custom { background-color: #007bff; border-color: #007bff; color: white; }
    .btn-primary-custom:hover { background-color: #0069d9; border-color: #0062cc; }
    .btn-outline-info-custom { border-color: #17a2b8; color: #17a2b8; }
    .btn-outline-info-custom:hover { background-color: #17a2b8; color: white; }
     .btn-outline-secondary-custom { border-color: #6c757d; color: #6c757d; }
    .btn-outline-secondary-custom:hover { background-color: #6c757d; color: white; }


    .alert-no-quizzes {
        background-color: #f8f9fa;
        border: 1px dashed #ced4da;
        color: #495057;
        border-radius: 8px;
        padding: 1.5rem;
        text-align: center;
        font-style: italic;
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <?php display_flash_message('flash_message', 'flash_message_type'); ?>
    
    <div class="quiz-page-main-title-container">
        <h1 class="quiz-page-main-title">আমাদের কুইজসমূহ</h1>
        <p class="quiz-page-main-subtitle">আপনার জ্ঞান পরীক্ষা করুন এবং বৃদ্ধি করুন; ইসলামিক বিভিন্ন বিষয়ে।</p>
    </div>

    <ul class="nav nav-pills nav-pills-custom mb-4 justify-content-center" id="quizTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="live-tab" data-bs-toggle="tab" data-bs-target="#live-quizzes-pane" type="button" role="tab" aria-controls="live-quizzes-pane" aria-selected="true">
                লাইভ 
                <?php if(count($live_quizzes) > 0) echo '<span class="badge bg-danger rounded-pill">'.count($live_quizzes).'</span>'; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming-quizzes-pane" type="button" role="tab" aria-controls="upcoming-quizzes-pane" aria-selected="false">
                আপকামিং 
                <?php if(count($upcoming_quizzes) > 0) echo '<span class="badge bg-warning text-dark rounded-pill">'.count($upcoming_quizzes).'</span>'; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archived-quizzes-pane" type="button" role="tab" aria-controls="archived-quizzes-pane" aria-selected="false">
                আর্কাইভ
            </button>
        </li>
    </ul>

    <div class="tab-content" id="quizTabContent">
        <div class="tab-pane fade show active" id="live-quizzes-pane" role="tabpanel" aria-labelledby="live-tab" tabindex="0">
            <?php if (!empty($live_quizzes)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($live_quizzes as $quiz): ?>
                    <?php 
                        list($attempted_live, $attempt_id_live) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']); 
                        $description_html_live = $quiz['description'] ? trim($quiz['description']) : ''; // Keep HTML
                        $is_description_empty_live = empty(trim(strip_tags($description_html_live))); // Check if visually empty
                        $quiz_page_url_live = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];
                    ?>
                    <div class="col">
                        <div class="card quiz-item-card live-quiz">
                            <div class="card-header-custom">
                                <h5 class="card-title-custom"><?php echo escape_html($quiz['title']); ?></h5>
                                 <span class="quiz-status-badge-inline">লাইভ</span>
                            </div>
                            <div class="card-body-custom">
                                <div class="quiz-description-custom <?php echo $is_description_empty_live ? 'no-real-description' : ''; ?>">
                                    <?php echo $is_description_empty_live ? '<p class="text-muted fst-italic">কোনো বিবরণ নেই।</p>' : $description_html_live; ?>
                                </div>
                                <ul class="quiz-details-custom">
                                    <li><i>🕒</i><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                    <li><i>❓</i><strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                    <?php if ($quiz['live_end_datetime']): ?>
                                        <li><i>📅</i><small>শেষ হবে: <?php echo format_datetime($quiz['live_end_datetime']); ?></small></li>
                                    <?php endif; ?>
                                </ul>
                                <div class="btn-action-group"> {/* Wrapper for buttons */}
                                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                                        <?php if ($attempted_live): ?>
                                            <a href="results.php?attempt_id=<?php echo $attempt_id_live; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-outline-info-custom">ফলাফল দেখুন</a>
                                        <?php else: ?>
                                            <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-success-custom">অংশগ্রহণ করুন</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz['id']); ?>" class="btn btn-action btn-primary-custom">লগইন করে অংশগ্রহণ করুন</a>
                                    <?php endif; ?>
                                    <button class="btn btn-action btn-outline-secondary-custom" 
                                            onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($quiz_page_url_live, ENT_QUOTES); ?>', this)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16">
                                            <path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/>
                                        </svg> শেয়ার
                                    </button>
                                </div>
                                <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && $attempted_live): ?>
                                    <p class="small text-primary mt-2 mb-0">আপনি অংশগ্রহণ করেছেন।</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="alert-no-quizzes">বর্তমানে কোনো লাইভ কুইজ চলছে না।</p>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="upcoming-quizzes-pane" role="tabpanel" aria-labelledby="upcoming-tab" tabindex="0">
            <?php if (!empty($upcoming_quizzes)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($upcoming_quizzes as $quiz): ?>
                    <?php
                        $description_html_upcoming = $quiz['description'] ? trim($quiz['description']) : '';
                        $is_description_empty_upcoming = empty(trim(strip_tags($description_html_upcoming)));
                        $quiz_page_url_upcoming = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];
                    ?>
                    <div class="col">
                        <div class="card quiz-item-card upcoming-quiz">
                             <div class="card-header-custom">
                                <h5 class="card-title-custom"><?php echo escape_html($quiz['title']); ?></h5>
                                 <span class="quiz-status-badge-inline">আপকামিং</span>
                            </div>
                            <div class="card-body-custom">
                                <div class="quiz-description-custom <?php echo $is_description_empty_upcoming ? 'no-real-description' : ''; ?>">
                                     <?php echo $is_description_empty_upcoming ? '<p class="text-muted fst-italic">কোনো বিবরণ নেই।</p>' : $description_html_upcoming; ?>
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
                                            onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($quiz_page_url_upcoming, ENT_QUOTES); ?>', this)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16">
                                            <path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/>
                                        </svg> শেয়ার
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="alert-no-quizzes">আপাতত কোনো আপকামিং কুইজ নেই।</p>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="archived-quizzes-pane" role="tabpanel" aria-labelledby="archived-tab" tabindex="0">
            <?php if (!empty($archived_quizzes)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($archived_quizzes as $quiz): ?>
                    <?php 
                        list($attempted_archived, $attempt_id_archived) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']); 
                        $description_html_archived = $quiz['description'] ? trim($quiz['description']) : '';
                        $is_description_empty_archived = empty(trim(strip_tags($description_html_archived)));
                        $quiz_page_url_archived = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];
                    ?>
                    <div class="col">
                        <div class="card quiz-item-card archived-quiz">
                            <div class="card-header-custom">
                                <h5 class="card-title-custom"><?php echo escape_html($quiz['title']); ?></h5>
                                 <span class="quiz-status-badge-inline">আর্কাইভ</span>
                            </div>
                            <div class="card-body-custom">
                                <div class="quiz-description-custom <?php echo $is_description_empty_archived ? 'no-real-description' : ''; ?>">
                                     <?php echo $is_description_empty_archived ? '<p class="text-muted fst-italic">কোনো বিবরণ নেই।</p>' : $description_html_archived; ?>
                                </div>
                                <ul class="quiz-details-custom">
                                   <li><i>🕒</i><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                   <li><i>❓</i><strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                </ul>
                                <div class="btn-action-group">
                                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                                        <?php if ($attempted_archived): ?>
                                            <a href="results.php?attempt_id=<?php echo $attempt_id_archived; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-outline-info-custom">ফলাফল দেখুন</a>
                                        <?php else: ?>
                                            <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-action btn-secondary-custom">অনুশীলন করুন</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                         <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz['id']); ?>" class="btn btn-action btn-outline-secondary-custom">লগইন করে অনুশীলন করুন</a>
                                    <?php endif; ?>
                                    <button class="btn btn-action btn-outline-secondary-custom" 
                                            onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($quiz_page_url_archived, ENT_QUOTES); ?>', this)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16">
                                            <path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/>
                                        </svg> শেয়ার
                                    </button>
                                </div>
                                 <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && $attempted_archived): ?>
                                    <p class="small text-primary mt-2 mb-0">আপনি এই কুইজে অংশগ্রহণ করেছিলেন।</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="alert-no-quizzes">এখনও কোনো কুইজ আর্কাইভ করা হয়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>