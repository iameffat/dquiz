<?php
$page_title = "সকল কুইজ";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 

$user_id_for_check = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

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

// [NEW] Fetch Upcoming Quizzes
$upcoming_quizzes = [];
$sql_upcoming = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime,
                (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                FROM quizzes q 
                WHERE q.status = 'upcoming' 
                AND (q.live_start_datetime IS NULL OR q.live_start_datetime > NOW()) 
                ORDER BY q.live_start_datetime ASC, q.id DESC";
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
             ORDER BY q.live_start_datetime DESC, q.id DESC";
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
                OR (q.status = 'upcoming' AND q.live_start_datetime IS NOT NULL AND q.live_start_datetime <= NOW() AND (q.live_end_datetime IS NULL OR q.live_end_datetime < NOW()) ) /* Expired upcoming becomes archived */
                ORDER BY q.created_at DESC, q.id DESC";
$result_archived = $conn->query($sql_archived);
if ($result_archived && $result_archived->num_rows > 0) {
    while ($row = $result_archived->fetch_assoc()) {
        $is_already_live_or_upcoming = false;
        foreach (array_merge($live_quizzes, $upcoming_quizzes) as $active_quiz) { // Check against both live and upcoming
            if ($active_quiz['id'] == $row['id']) {
                $is_already_live_or_upcoming = true;
                break;
            }
        }
        if (!$is_already_live_or_upcoming) {
            $archived_quizzes[] = $row;
        }
    }
}

// Page specific styles
$page_specific_styles = "
    .quiz-card {
        border: none; 
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .quiz-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    }
    .quiz-card .card-body { display: flex; flex-direction: column; padding: 1.25rem; }
    .quiz-card .card-title { font-size: 1.2rem; font-weight: 700; color: #343a40; margin-bottom: 0.75rem; }
    .quiz-card .card-text { font-size: 0.9rem; color: #555; flex-grow: 1; }
    .quiz-card ul { font-size: 0.85rem; color: #495057; margin-bottom: 1rem; }
    .quiz-card ul li strong { color: #212529; }
    .quiz-card .btn { padding: 0.5rem 1rem; font-size: 0.9rem; border-radius: 0.25rem; }

    /* Upcoming Quiz Card Specific Styling */
    .upcoming-quiz-card {
        background-color: #fff8e1; /* Light Yellow */
        border-left: 5px solid #ffc107; /* Bootstrap Warning Color */
    }
    .upcoming-quiz-card .card-title { color: #856404; /* Darker Yellow */ }
    .upcoming-quiz-card .btn-warning { background-color: #ffc107; border-color: #ffc107; color: #212529; }
    .upcoming-quiz-card .btn-warning:hover { background-color: #e0a800; border-color: #d39e00; }
     .upcoming-quiz-card .badge-warning-light { background-color: #fff3cd; color: #664d03;}


    /* Live Quiz Card Specific Styling */
    .live-quiz-card { background-color: #e6ffed; border-left: 5px solid #28a745; }
    .live-quiz-card .card-title { color: #155724; }
    .live-quiz-card .btn-success { background-color: #28a745; border-color: #28a745; color: #fff; }
    .live-quiz-card .btn-success:hover { background-color: #218838; border-color: #1e7e34; }
    .live-quiz-card .btn-outline-info { color: #17a2b8; border-color: #17a2b8; }
    .live-quiz-card .btn-outline-info:hover { background-color: #17a2b8; color: #fff; }

    /* Archived Quiz Card Styling */
    .archived-quiz-card { background-color: #f8f9fa; border-left: 5px solid #6c757d; }
    .archived-quiz-card .card-title { color: #343a40; }
    .archived-quiz-card .btn-secondary,
    .archived-quiz-card .btn-outline-secondary { border-color: #6c757d; color: #6c757d; }
    .archived-quiz-card .btn-secondary:hover,
    .archived-quiz-card .btn-outline-secondary:hover { background-color: #5a6268; color: #fff; }
    .archived-quiz-card .btn-outline-info { color: #17a2b8; border-color: #17a2b8; }
    .archived-quiz-card .btn-outline-info:hover { background-color: #17a2b8; color: #fff; }

    /* Section Title Styling */
    #upcoming-quizzes h2 { color: #ffc107 !important; font-weight: 600;} /* Orange/Yellow for Upcoming */
    #live-quizzes h2 { color: #28a745 !important; font-weight: 600; }
    #archived-quizzes h2 { color: #6c757d !important; font-weight: 600; }
    .alert-light { background-color: #f8f9fa; border-color: #e9ecef; color: #495057; }
";

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <?php display_flash_message('flash_message', 'flash_message_type'); ?>
    <h1 class="mb-4 text-center">কুইজসমূহ</h1>

    <section id="upcoming-quizzes" class="mb-5">
        <h2 class="mb-3 border-bottom pb-2" style="color: #ffc107;">আপকামিং কুইজ</h2>
        <?php if (!empty($upcoming_quizzes)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($upcoming_quizzes as $quiz): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm quiz-card upcoming-quiz-card">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo escape_html($quiz['title']); ?></h5>
                            <div class="card-text text-muted small quiz-description-display">
                                <?php echo $quiz['description'] ?? ''; ?>
                            </div>
                            <ul class="list-unstyled mt-auto pt-2">
                                <li><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                <li><strong>প্রশ্ন সংখ্যা:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                <?php if ($quiz['live_start_datetime']): ?>
                                    <li><small>শুরু হবে: <span class="badge badge-warning-light"><?php echo format_datetime($quiz['live_start_datetime']); ?></span></small></li>
                                <?php else: ?>
                                     <li><small>শুরুর সময় শীঘ্রই জানানো হবে</small></li>
                                <?php endif; ?>
                                <?php if ($quiz['live_end_datetime']): ?>
                                    <li><small>শেষ হবে: <?php echo format_datetime($quiz['live_end_datetime']); ?></small></li>
                                <?php endif; ?>
                            </ul>
                             <button class="btn btn-warning mt-2" disabled>শীঘ্রই আসছে</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php if (empty($live_quizzes)): // Show this only if no upcoming AND no live quizzes ?>
                <p class="text-center alert alert-light">বর্তমানে কোনো আপকামিং কুইজ নেই।</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>


    <?php if (!empty($live_quizzes)): ?>
    <section id="live-quizzes" class="mb-5">
        <h2 class="mb-3 text-success border-bottom pb-2">লাইভ কুইজ</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($live_quizzes as $quiz): ?>
            <?php 
                list($attempted_live, $attempt_id_live) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
            ?>
            <div class="col">
                <div class="card h-100 shadow-sm quiz-card live-quiz-card">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo escape_html($quiz['title']); ?></h5>
                        <div class="card-text text-muted small quiz-description-display">
                            <?php echo $quiz['description'] ?? ''; ?>
                        </div>
                        <ul class="list-unstyled mt-auto pt-2">
                            <li><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                            <li><strong>প্রশ্ন সংখ্যা:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                            <?php if ($quiz['live_start_datetime']): ?>
                                <li><small>শুরু: <?php echo format_datetime($quiz['live_start_datetime']); ?></small></li>
                            <?php endif; ?>
                            <?php if ($quiz['live_end_datetime']): ?>
                                <li><small>শেষ: <?php echo format_datetime($quiz['live_end_datetime']); ?></small></li>
                            <?php endif; ?>
                        </ul>
                        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                            <?php if ($attempted_live): ?>
                                <a href="results.php?attempt_id=<?php echo $attempt_id_live; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-outline-info mt-2">ফলাফল দেখুন</a>
                                <p class="small text-primary mt-1 mb-0">আপনি এই কুইজে অংশগ্রহণ করেছেন।</p>
                            <?php else: ?>
                                <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-success mt-2">অংশগ্রহণ করুন</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz['id']); ?>" class="btn btn-outline-success mt-2">অংশগ্রহণের জন্য লগইন করুন</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif (empty($upcoming_quizzes) && empty($live_quizzes)): // If no upcoming AND no live, show message for live section as well ?>
        <section id="live-quizzes" class="mb-5">
             <h2 class="mb-3 text-success border-bottom pb-2">লাইভ কুইজ</h2>
            <p class="text-center alert alert-light">বর্তমানে কোনো লাইভ কুইজ নেই। অনুগ্রহ করে পরে আবার দেখুন।</p>
        </section>
    <?php endif; ?>


    <section id="archived-quizzes">
        <h2 class="mb-3 text-secondary border-bottom pb-2">পূর্ববর্তী কুইজ (আর্কাইভ)</h2>
        <?php if (!empty($archived_quizzes)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($archived_quizzes as $quiz): ?>
                <?php 
                    list($attempted_archived, $attempt_id_archived) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm quiz-card archived-quiz-card">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo escape_html($quiz['title']); ?></h5>
                            <div class="card-text text-muted small quiz-description-display">
                                <?php echo $quiz['description'] ?? ''; ?>
                            </div>
                             <ul class="list-unstyled mt-auto pt-2">
                                <li><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                <li><strong>প্রশ্ন সংখ্যা:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                            </ul>
                            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                                <?php if ($attempted_archived): ?>
                                    <a href="results.php?attempt_id=<?php echo $attempt_id_archived; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-outline-info mt-2">ফলাফল দেখুন</a>
                                     <p class="small text-primary mt-1 mb-0">আপনি এই কুইজে অংশগ্রহণ করেছেন।</p>
                                <?php else: ?>
                                    <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-secondary mt-2">অংশগ্রহণ করুন (অনুশীলন)</a>
                                <?php endif; ?>
                            <?php else: ?>
                                 <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz['id']); ?>" class="btn btn-outline-secondary mt-2">অংশগ্রহণের জন্য লগইন করুন</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-center alert alert-light">এখনও কোনো কুইজ আর্কাইভ করা হয়নি।</p>
        <?php endif; ?>
    </section>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>