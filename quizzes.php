<?php
$page_title = "সকল কুইজ";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 
// header.php এখানে include করার আগে $page_specific_styles ভ্যারিয়েবল সেট করতে হবে
// তাই header.php এর include আপাতত নিচে সরিয়ে দিচ্ছি।

$user_id_for_check = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

/**
 * Checks if a user has any attempt (completed or not) for a given quiz.
 *
 * @param mysqli $conn Database connection object.
 * @param int|null $user_id The ID of the user.
 * @param int $quiz_id The ID of the quiz.
 * @return array [bool $has_attempted, int|null $attempt_id]
 */
function hasUserAttemptedQuiz($conn, $user_id, $quiz_id) {
    if ($user_id === null || !$conn) {
        return [false, null];
    }
    // Checks for any attempt, regardless of completion status (score IS NOT NULL is removed)
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
                ORDER BY q.created_at DESC, q.id DESC";
$result_archived = $conn->query($sql_archived);
if ($result_archived && $result_archived->num_rows > 0) {
    while ($row = $result_archived->fetch_assoc()) {
        // Ensure it's not already listed in live_quizzes if it just expired
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

// Define page specific styles
$page_specific_styles = "
    .quiz-card {
        border: none; 
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .quiz-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    }

    .quiz-card .card-body {
        display: flex;
        flex-direction: column;
        padding: 1.25rem; 
    }

    .quiz-card .card-title {
        font-size: 1.2rem; 
        font-weight: 700; 
        color: #343a40; 
        margin-bottom: 0.75rem;
    }

    .quiz-card .card-text {
        font-size: 0.9rem;
        color: #555; 
        flex-grow: 1; 
        margin-bottom: 1rem;
    }

    .quiz-card ul {
        font-size: 0.85rem;
        color: #495057;
        margin-bottom: 1rem; 
    }
    .quiz-card ul li strong {
        color: #212529; 
    }

    .quiz-card .btn {
        padding: 0.5rem 1rem; 
        font-size: 0.9rem;
        border-radius: 0.25rem; 
    }

    /* Live Quiz Card Specific Styling */
    .live-quiz-card {
        background-color: #e6ffed; 
        border-left: 5px solid #28a745; 
    }

    .live-quiz-card .card-title {
        color: #155724; 
    }

    .live-quiz-card .btn-success {
        background-color: #28a745;
        border-color: #28a745;
        color: #fff;
    }
    .live-quiz-card .btn-success:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }

    .live-quiz-card .btn-outline-info {
        color: #17a2b8;
        border-color: #17a2b8;
    }
    .live-quiz-card .btn-outline-info:hover {
        background-color: #17a2b8;
        color: #fff;
    }

    /* Archived Quiz Card Styling */
    .archived-quiz-card { /* এই ক্লাসটি আর্কাইভ কুইজের কার্ডে যোগ করতে হবে যদি ভিন্ন স্টাইল চান */
        background-color: #f8f9fa; 
        border-left: 5px solid #6c757d; 
    }

    .archived-quiz-card .card-title {
        color: #343a40;
    }

    .archived-quiz-card .btn-secondary,
    .archived-quiz-card .btn-outline-secondary {
        border-color: #6c757d;
        color: #6c757d;
    }
    .archived-quiz-card .btn-secondary:hover,
    .archived-quiz-card .btn-outline-secondary:hover {
        background-color: #5a6268;
        color: #fff;
    }
    .archived-quiz-card .btn-outline-info {
         color: #17a2b8;
        border-color: #17a2b8;
    }
    .archived-quiz-card .btn-outline-info:hover {
        background-color: #17a2b8;
        color: #fff;
    }

    /* Section Title Styling */
    #live-quizzes h2 {
        color: #28a745 !important;
        font-weight: 600;
    }

    #archived-quizzes h2 {
        color: #6c757d !important;
        font-weight: 600;
    }

    .alert-light { /* \"কোনো কুইজ নেই\" বার্তার জন্য */
        background-color: #f8f9fa;
        border-color: #e9ecef;
        color: #495057;
    }
";

require_once 'includes/header.php'; // header.php এখন $page_specific_styles পাবে
?>

<div class="container mt-4">
    <?php display_flash_message('flash_message', 'flash_message_type'); ?>
    <h1 class="mb-4 text-center">কুইজসমূহ</h1>

    <section id="live-quizzes" class="mb-5">
        <h2 class="mb-3 text-success border-bottom pb-2">লাইভ কুইজ</h2>
        <?php if (!empty($live_quizzes)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($live_quizzes as $quiz): ?>
                <?php 
                    list($attempted_live, $attempt_id_live) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm quiz-card live-quiz-card">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo escape_html($quiz['title']); ?></h5>
                            <p class="card-text text-muted small">
    <?php 
        $plain_description = strip_tags($quiz['description'] ?? ''); // Remove HTML tags
        echo htmlspecialchars(mb_substr($plain_description, 0, 100)) . (mb_strlen($plain_description) > 100 ? '...' : ''); // Use mb_substr
    ?>
</p>
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
        <?php else: ?>
            <p class="text-center alert alert-light">বর্তমানে কোনো লাইভ কুইজ নেই। অনুগ্রহ করে পরে আবার দেখুন।</p>
        <?php endif; ?>
    </section>

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
    <?php
        // Ensure $quiz['description'] is not null
        $description_html = $quiz['description'] ?? '';
        // Directly echo the HTML content.
        echo $description_html;
    ?>
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