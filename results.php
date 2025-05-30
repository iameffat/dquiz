<?php
// $page_title is set after fetching quiz details
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $redirect_url = 'quizzes.php';
    if (isset($_GET['attempt_id']) && isset($_GET['quiz_id'])) {
        $redirect_url = 'results.php?attempt_id=' . intval($_GET['attempt_id']) . '&quiz_id=' . intval($_GET['quiz_id']);
    } elseif (isset($_POST['attempt_id']) && isset($_POST['quiz_id'])) {
         $redirect_url = 'results.php?attempt_id=' . intval($_POST['attempt_id']) . '&quiz_id=' . intval($_POST['quiz_id']);
    }
    $_SESSION['redirect_url_user'] = $redirect_url;
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id']; // User ID from session
$quiz_id = 0;
$attempt_id = 0;
$total_score = 0;
$total_questions_in_quiz = 0;
$time_taken_seconds = null;
$feedback_message = "";
$feedback_class = "";
$quiz_info_result = null; 
$review_questions = [];
$page_title = "কুইজের ফলাফল"; // Default page title

// Function to fetch and prepare result display data
function prepare_results_data($conn, $current_attempt_id, $current_quiz_id, $current_user_id) {
    $display_data = [
        'success' => false,
        'quiz_id' => $current_quiz_id,
        'attempt_id' => $current_attempt_id,
        'total_score' => 0,
        'total_questions_in_quiz' => 0,
        'time_taken_seconds' => null,
        'feedback_message' => '',
        'feedback_class' => '',
        'quiz_info_result' => null,
        'review_questions' => [],
        'page_title' => "কুইজের ফলাফল"
    ];

    // Fetch attempt details
    $sql_attempt = "SELECT score, time_taken_seconds, user_id FROM quiz_attempts WHERE id = ? AND quiz_id = ?";
    $stmt_attempt = $conn->prepare($sql_attempt);
    if (!$stmt_attempt) {
        $_SESSION['flash_message'] = "ফলাফল দেখাতে ডেটাবেস সমস্যা হয়েছে। (Attempt Prepare)";
        $_SESSION['flash_message_type'] = "danger";
        return $display_data;
    }
    $stmt_attempt->bind_param("ii", $current_attempt_id, $current_quiz_id);
    $stmt_attempt->execute();
    $result_attempt = $stmt_attempt->get_result();

    if ($result_attempt->num_rows === 1) {
        $attempt_data = $result_attempt->fetch_assoc();

        if ($attempt_data['user_id'] != $current_user_id) {
            $_SESSION['flash_message'] = "আপনি এই কুইজের ফলাফল দেখার জন্য অনুমোদিত নন।";
            $_SESSION['flash_message_type'] = "warning";
            return $display_data;
        }

        $display_data['total_score'] = $attempt_data['score'];
        $display_data['time_taken_seconds'] = $attempt_data['time_taken_seconds'];

        $quiz_info_sql = "SELECT q.title, q.status, q.live_end_datetime, COUNT(qs.id) as total_questions
                          FROM quizzes q
                          LEFT JOIN questions qs ON q.id = qs.quiz_id
                          WHERE q.id = ?
                          GROUP BY q.id";
        $stmt_quiz_info = $conn->prepare($quiz_info_sql);
        if (!$stmt_quiz_info) {
             $_SESSION['flash_message'] = "ফলাফল দেখাতে ডেটাবেস সমস্যা হয়েছে। (QuizInfo Prepare)";
             $_SESSION['flash_message_type'] = "danger";
             return $display_data;
        }
        $stmt_quiz_info->bind_param("i", $current_quiz_id);
        $stmt_quiz_info->execute();
        $quiz_info_res = $stmt_quiz_info->get_result()->fetch_assoc();
        $stmt_quiz_info->close();

        if ($quiz_info_res) {
            $display_data['quiz_info_result'] = $quiz_info_res;
            $display_data['page_title'] = "ফলাফল: " . htmlspecialchars($quiz_info_res['title']);
            $display_data['total_questions_in_quiz'] = $quiz_info_res['total_questions'];
        } else {
            $_SESSION['flash_message'] = "কুইজের তথ্য পাওয়া যায়নি।";
            $_SESSION['flash_message_type'] = "warning";
            return $display_data;
        }

        if ($display_data['total_questions_in_quiz'] > 0) {
            $percentage_score = ($display_data['total_score'] / $display_data['total_questions_in_quiz']) * 100;
            if ($percentage_score >= 80) {
                $display_data['feedback_message'] = "খুব ভালো!"; $display_data['feedback_class'] = "very-good";
            } elseif ($percentage_score >= 65) {
                $display_data['feedback_message'] = "ভালো!"; $display_data['feedback_class'] = "good";
            } elseif ($percentage_score >= 50) {
                $display_data['feedback_message'] = "সাধারণ"; $display_data['feedback_class'] = "average";
            } else {
                $display_data['feedback_message'] = "পরের বার আরও ভালো হবে।"; $display_data['feedback_class'] = "improve";
            }
        }

        $sql_review = "
            SELECT 
                q.id AS question_id, q.question_text, q.image_url, q.explanation,
                ua.selected_option_id AS user_selected_option_id,
                (SELECT GROUP_CONCAT(CONCAT(o.id, '::', o.option_text, '::', o.is_correct) SEPARATOR '||') 
                 FROM options o WHERE o.question_id = q.id ORDER BY o.id) AS all_options_details
            FROM questions q
            LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.attempt_id = ?
            WHERE q.quiz_id = ?
            ORDER BY q.order_number ASC, q.id ASC
        ";
        $stmt_review = $conn->prepare($sql_review);
        if (!$stmt_review) {
            $_SESSION['flash_message'] = "ফলাফল পর্যালোচনা প্রস্তুত করতে সমস্যা হয়েছে। (Review Prepare)";
            $_SESSION['flash_message_type'] = "danger";
            return $display_data;
        }
        $stmt_review->bind_param("ii", $current_attempt_id, $current_quiz_id);
        $stmt_review->execute();
        $result_review = $stmt_review->get_result();
        while ($row = $result_review->fetch_assoc()) {
            $options_array = [];
            if (!empty($row['all_options_details'])) {
                $options_parts = explode('||', $row['all_options_details']);
                foreach($options_parts as $opt_part) {
                    list($opt_id, $opt_text, $opt_is_correct) = explode('::', $opt_part, 3);
                    $options_array[] = [
                        'id' => $opt_id,
                        'text' => $opt_text,
                        'is_correct' => $opt_is_correct
                    ];
                }
            }
            $row['options_list'] = $options_array;
            $display_data['review_questions'][] = $row;
        }
        $stmt_review->close();
        $display_data['success'] = true;

    } else {
        $_SESSION['flash_message'] = "কুইজের প্রচেষ্টা খুঁজে পাওয়া যায়নি অথবা এটি আপনার নয়।";
        $_SESSION['flash_message_type'] = "warning";
    }
    $stmt_attempt->close();
    return $display_data;
}


// Handles quiz submission (manual or auto by timer)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quiz_id']) && isset($_POST['attempt_id'])) {
    $quiz_id = intval($_POST['quiz_id']);
    $attempt_id = intval($_POST['attempt_id']);
    $submitted_answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    // $user_id is already set from session at the top

    if ($quiz_id <= 0 || $attempt_id <= 0) {
        $_SESSION['flash_message'] = "ফলাফল দেখাতে সমস্যা হয়েছে। (অবৈধ IDs)";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: quizzes.php");
        exit;
    }

    $end_time_dt = new DateTime();
    $end_time = $end_time_dt->format('Y-m-d H:i:s');
    $time_taken_seconds = null;

    $sql_get_start_time = "SELECT start_time FROM quiz_attempts WHERE id = ? AND user_id = ?";
    $stmt_get_start_time = $conn->prepare($sql_get_start_time);
    $stmt_get_start_time->bind_param("ii", $attempt_id, $user_id);
    $stmt_get_start_time->execute();
    $result_start_time = $stmt_get_start_time->get_result();
    if ($result_start_time->num_rows > 0) {
        $attempt_details_start = $result_start_time->fetch_assoc();
        $start_timestamp_db = new DateTime($attempt_details_start['start_time']);
        $time_taken_seconds = $end_time_dt->getTimestamp() - $start_timestamp_db->getTimestamp();
    }
    $stmt_get_start_time->close();

    $questions_answered_correctly = 0;
    
    $sql_all_q_count = "SELECT COUNT(id) as total_q FROM questions WHERE quiz_id = ?";
    $stmt_all_q_count = $conn->prepare($sql_all_q_count);
    $stmt_all_q_count->bind_param("i", $quiz_id);
    $stmt_all_q_count->execute();
    $total_questions_in_quiz_from_db = $stmt_all_q_count->get_result()->fetch_assoc()['total_q'];
    $stmt_all_q_count->close();
    $total_questions_in_quiz = $total_questions_in_quiz_from_db;


    $conn->begin_transaction();
    try {
        $sql_questions_options = "SELECT q.id as question_id, o.id as option_id, o.is_correct 
                                  FROM questions q 
                                  JOIN options o ON q.id = o.question_id 
                                  WHERE q.quiz_id = ?";
        $stmt_qo = $conn->prepare($sql_questions_options);
        $stmt_qo->bind_param("i", $quiz_id);
        $stmt_qo->execute();
        $qo_results = $stmt_qo->get_result();
        $correct_options_map = []; 
        $all_questions_map = []; 
        while($qo_row = $qo_results->fetch_assoc()){
            $all_questions_map[$qo_row['question_id']] = true; 
            if($qo_row['is_correct'] == 1){
                $correct_options_map[$qo_row['question_id']] = $qo_row['option_id'];
            }
        }
        $stmt_qo->close();

        foreach ($all_questions_map as $question_id_loop => $val) {
            $selected_option_id = isset($submitted_answers[$question_id_loop]) ? intval($submitted_answers[$question_id_loop]) : null;
            $is_correct_answer = 0;

            if ($selected_option_id !== null) { 
                if (isset($correct_options_map[$question_id_loop]) && $correct_options_map[$question_id_loop] == $selected_option_id) {
                    $is_correct_answer = 1;
                    $questions_answered_correctly++;
                }
            }
            
            if ($selected_option_id === null) {
                 $stmt_insert_answer_final = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, NULL, ?)");
                 if(!$stmt_insert_answer_final) throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ स्टेटमेंट প্রস্তুত করতে সমস্যা হয়েছে (NULL): " . $conn->error);
                 $stmt_insert_answer_final->bind_param("iii", $attempt_id, $question_id_loop, $is_correct_answer);
            } else {
                 $stmt_insert_answer_final = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?)");
                 if(!$stmt_insert_answer_final) throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ स्टेटमेंट প্রস্তুত করতে সমস্যা হয়েছে: " . $conn->error);
                 $stmt_insert_answer_final->bind_param("iiii", $attempt_id, $question_id_loop, $selected_option_id, $is_correct_answer);
            }
            if (!$stmt_insert_answer_final->execute()) throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ করতে সমস্যা হয়েছে (প্রশ্ন ID: $question_id_loop): " . $stmt_insert_answer_final->error);
            $stmt_insert_answer_final->close();
        }

        $total_score = $questions_answered_correctly;

        $sql_update_attempt = "UPDATE quiz_attempts SET score = ?, end_time = ?, time_taken_seconds = ?, submitted_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt_update_attempt = $conn->prepare($sql_update_attempt);
        $stmt_update_attempt->bind_param("isiii", $total_score, $end_time, $time_taken_seconds, $attempt_id, $user_id);
        if (!$stmt_update_attempt->execute()) {
             throw new Exception("কুইজের চেষ্টা আপডেট করতে সমস্যা হয়েছে: " . $stmt_update_attempt->error);
        }
        $stmt_update_attempt->close();

        $conn->commit();
        
        $results_display_data = prepare_results_data($conn, $attempt_id, $quiz_id, $user_id);
        if ($results_display_data['success']) {
            extract($results_display_data); 
        } else {
            header("Location: quizzes.php");
            exit;
        }

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = "ফলাফল প্রসেস করার সময় একটি ত্রুটি ঘটেছে: " . $e->getMessage();
        $_SESSION['flash_message_type'] = "danger";
        error_log("Error processing results for attempt ID $attempt_id: " . $e->getMessage());
        header("Location: quizzes.php");
        exit;
    }

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['attempt_id']) && isset($_GET['quiz_id'])) {
    $attempt_id = intval($_GET['attempt_id']);
    $quiz_id = intval($_GET['quiz_id']);

    if ($quiz_id <= 0 || $attempt_id <= 0) {
        $_SESSION['flash_message'] = "অবৈধ আইডি প্রদান করা হয়েছে।";
        $_SESSION['flash_message_type'] = "warning";
        header("Location: quizzes.php");
        exit;
    }
        
    $results_display_data = prepare_results_data($conn, $attempt_id, $quiz_id, $user_id);

    if ($results_display_data['success']) {
        extract($results_display_data); 
    } else {
        header("Location: quizzes.php");
        exit;
    }

} else {
    $_SESSION['flash_message'] = "অবৈধ অনুরোধ।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: quizzes.php");
    exit;
}

require_once 'includes/header.php';
?>

<style>
@media print {
    @page {
        margin: 0!important; 
        size: A4;
    }
    body * {
        visibility: hidden;
    }
    #printableArea, #printableArea * {
        visibility: visible;
    }
    #printableArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .card-header, .card-body {
        border: none !important;
        box-shadow: none !important;
    }
    .list-group-item {
        border: 1px solid #eee !important;
    }
    .badge { 
        border: 1px solid #ccc;
        padding: 0.3em 0.5em;
        background-color: #fff !important; 
        color: #000 !important; 
    }
     .badge.bg-success-subtle, .badge.bg-danger-subtle, .badge.bg-warning-subtle {
        border: 1px solid #ccc !important; 
    }
    .text-success-emphasis { color: #0f5132 !important; }
    .text-danger-emphasis { color: #58151c !important; }
    .text-warning-emphasis { color: #664d03 !important; }

    .btn, .alert:not(.print-header-message), .timer-progress-bar, footer, header, .navbar, .footer, 
    .feedback-message, .text-center.mb-4:has(h3), .text-center.mb-4:has(a.btn-info), 
    hr,
    .card.shadow-sm > .card-header.bg-light, 
    .card.shadow-sm > .card-body.p-4 > hr, 
    .card.shadow-sm > .card-body.p-4 > .text-center.mt-4 
     {
        display: none !important;
    }

    .print-header { 
        visibility: visible;
        text-align: center;
        margin-bottom: 20px;
        font-size: 1.4rem; 
        font-weight: bold;
        width: 100%;
    }
    .print-header-message { 
        visibility: visible;
        display: block !important; 
    }

    .answer-review {
        column-count: 2;
        column-gap: 20px;
        font-size: 10pt;
    }
    .question-image-review { /* Style for images in print review */
        max-height: 120px; /* Adjust as needed for print layout */
        margin-bottom: 5px;
    }

    .answer-review .card {
        page-break-inside: avoid;
        break-inside: avoid-column;
        width: 100%; 
        margin-bottom: 15px !important; 
        font-size: inherit; 
        border: 1px solid #ddd !important; 
    }
    .answer-review .card-header, .answer-review .card-body {
        padding: 0.5rem !important;
        font-size: inherit;
        border: none !important; 
    }
    .answer-review .list-group-item {
        padding: 0.3rem 0.5rem !important;
        font-size: 0.9em; 
        border: 1px solid #eee !important; 
    }
    .answer-review .card-header strong {
        font-size: 1.1em; 
    }
    .answer-review .mt-3.p-2.bg-light.border.rounded { 
        padding: 0.3rem !important;
        font-size: 0.9em;
        margin-top: 0.5rem !important;
        background-color: #f8f9fa !important; 
        border: 1px solid #ddd !important;
    }
    #printableArea > h3.mt-4.mb-3 { 
        display: none !important;
    }
    .container.mt-5 > .card.shadow-sm > .card-body.p-4 > .text-center.my-3:has(button) { 
        display: none !important;
    }
}
.question-image-review { /* General style for images in review on screen */
    max-width: 100%;
    height: auto;
    max-height: 200px; /* Adjust for screen */
    margin-bottom: 10px;
    border-radius: 4px;
    display: block;
    margin-left: auto;
    margin-right: auto;
    border: 1px solid #eee;
    padding: 3px;
}
</style>

<div class="container mt-5">
    <?php display_flash_message('flash_message', 'flash_message_type'); ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h2 class="text-center mb-0"><?php echo $page_title; ?></h2>
        </div>
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h3>আপনি পেয়েছেন: <strong class="text-primary"><?php echo $total_score; ?> / <?php echo $total_questions_in_quiz; ?></strong></h3>
                <p class="fs-5 feedback-message <?php echo $feedback_class; ?>">মন্তব্য: <?php echo $feedback_message; ?></p>
                <?php if ($time_taken_seconds !== null): ?>
                <p class="text-muted">সময় লেগেছে: <?php echo format_seconds_to_hms($time_taken_seconds); ?> (ঘন্টা:মিনিট:সেকেন্ড)</p>
                <?php endif; ?>
            </div>

            <div class="text-center mb-4">
                <?php
                $show_ranking_now = true;
                $ranking_button_text = "আপনার র‍্যাংকিং দেখুন";
                if ($quiz_info_result && $quiz_info_result['status'] == 'live') {
                    if (!empty($quiz_info_result['live_end_datetime'])) {
                        try {
                            $live_end = new DateTime($quiz_info_result['live_end_datetime']);
                            $now = new DateTime();
                            if ($now < $live_end) {
                                $show_ranking_now = false; 
                            }
                        } catch (Exception $e) { /* Invalid date format, show ranking */ }
                    } else {
                         $show_ranking_now = true; 
                    }
                }
                
                if ($show_ranking_now) {
                    echo '<a href="ranking.php?quiz_id=' . $quiz_id . '&attempt_id=' . $attempt_id . '" class="btn btn-info">' . $ranking_button_text . '</a>';
                } else {
                    echo '<button class="btn btn-info" disabled>' . $ranking_button_text . '</button>';
                    echo '<p class="mt-2 text-muted small">লাইভ কুইজের চুড়ান্ত র‍্যাংকিং পরীক্ষার সময়সীমা শেষ হওয়ার পর (সাধারণত ঐদিন রাত ১২টার পর) দেখা যাবে।</p>';
                }
                ?>
            </div>
            <hr>

            <div id="printableArea">
                <div class="print-header">
                    কুইজের নাম: <?php echo isset($quiz_info_result['title']) ? htmlspecialchars($quiz_info_result['title']) : 'কুইজের ফলাফল'; ?>
                </div>
                <h3 class="mt-4 mb-3">উত্তর পর্যালোচনা</h3>
                <div class="answer-review">
                    <?php if (!empty($review_questions)): ?>
                        <?php foreach ($review_questions as $index => $question): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <strong>প্রশ্ন <?php echo $index + 1; ?>:</strong> <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($question['image_url'])): ?>
                                    <div class="mb-2 text-center">
                                        <img src="<?php echo $base_url . escape_html($question['image_url']); ?>" alt="প্রশ্ন সম্পর্কিত ছবি" class="img-fluid question-image-review">
                                    </div>
                                <?php endif; ?>
                                <ul class="list-group list-group-flush">
                                    <?php 
                                    $user_correct_for_this_q = false;
                                    $correct_option_id_for_this_q = null;
                                    foreach ($question['options_list'] as $option) {
                                        if ($option['is_correct'] == 1) $correct_option_id_for_this_q = $option['id'];
                                    }
                                    if ($question['user_selected_option_id'] !== null && $question['user_selected_option_id'] == $correct_option_id_for_this_q) {
                                        $user_correct_for_this_q = true;
                                    }
                                    ?>
                                    <?php foreach ($question['options_list'] as $option): ?>
                                        <?php
                                        $option_class = 'list-group-item';
                                        $option_label = '';
                                        if ($option['id'] == $question['user_selected_option_id']) { 
                                            if ($option['is_correct'] == 1) {
                                                $option_class .= ' correct-user-answer'; 
                                                $option_label = ' <span class="badge bg-success-subtle text-success-emphasis rounded-pill">আপনার সঠিক উত্তর</span>';
                                            } else {
                                                $option_class .= ' incorrect-user-answer'; 
                                                $option_label = ' <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill">আপনার ভুল উত্তর</span>';
                                            }
                                        } elseif ($option['is_correct'] == 1) { 
                                            $option_class .= ' actual-correct-answer'; 
                                            $option_label = ' <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">সঠিক উত্তর</span>';
                                        }
                                        ?>
                                    <li class="<?php echo $option_class; ?>">
                                        <?php echo htmlspecialchars($option['text']); ?>
                                        <?php echo $option_label; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($question['user_selected_option_id'] === null && !$user_correct_for_this_q) : ?>
                                     <p class="mt-2 mb-0 text-warning print-header-message">আপনি এই প্রশ্নের উত্তর দেননি।</p>
                                <?php endif; ?>

                                <?php if (!empty($question['explanation'])): ?>
                                <div class="mt-3 p-2 bg-light border rounded">
                                    <strong>ব্যাখ্যা:</strong> <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="alert alert-info print-header-message">এই ফলাফলের জন্য উত্তর পর্যালোচনা উপলব্ধ নয় অথবা কোনো প্রশ্ন পাওয়া যায়নি।</p>
                    <?php endif; ?>
                </div>
            </div> 
             <div class="text-center mt-4">
                <button onclick="printAnswerSheet()" class="btn btn-outline-primary">উত্তরপত্র প্রিন্ট করুন</button>
                <a href="quizzes.php" class="btn btn-secondary">সকল কুইজে ফিরে যান</a>
                 <a href="profile.php" class="btn btn-outline-primary">আমার প্রোফাইল</a>
            </div>
        </div>
    </div>
</div>

<script>
function printAnswerSheet() {
    window.print();
}
</script>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>