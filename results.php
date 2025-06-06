<?php
// results.php
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
$time_taken_seconds = null; // Initialize
$feedback_message = "";
$feedback_class = "";
$quiz_info_result = null;
$review_questions = [];
$page_title = "কুইজের ফলাফল";

// New variables for chart data
$correct_answers_count_for_chart = 0;
$incorrect_answers_count_for_chart = 0;
$unanswered_questions_count_for_chart = 0;


// Function to fetch and prepare result display data
function prepare_results_data($conn, $current_attempt_id, $current_quiz_id, $current_user_id) {
    global $correct_answers_count_for_chart, $incorrect_answers_count_for_chart, $unanswered_questions_count_for_chart; // Make global to set them

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
        'page_title' => "কুইজের ফলাফল",
        'correct_answers_count' => 0, // For chart
        'incorrect_answers_count_raw' => 0, // For chart
        'unanswered_questions_count' => 0 // For chart
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

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            // Admin can view any result
        } elseif ($attempt_data['user_id'] != $current_user_id) {
            $_SESSION['flash_message'] = "আপনি এই কুইজের ফলাফল দেখার জন্য অনুমোদিত নন।";
            $_SESSION['flash_message_type'] = "warning";
            header("Location: quizzes.php");
            exit;
        }

        $display_data['total_score'] = $attempt_data['score'];
        $display_data['time_taken_seconds'] = $attempt_data['time_taken_seconds'];

        $quiz_info_sql = "SELECT q.title, q.status, q.quiz_type, q.live_end_datetime, COUNT(qs.id) as total_questions
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

        // Fetch answer stats for chart
        $sql_answer_stats = "SELECT
                                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                                SUM(CASE WHEN is_correct = 0 AND selected_option_id IS NOT NULL THEN 1 ELSE 0 END) as incorrect_count,
                                SUM(CASE WHEN selected_option_id IS NULL THEN 1 ELSE 0 END) as unanswered_count
                             FROM user_answers
                             WHERE attempt_id = ?";
        $stmt_answer_stats = $conn->prepare($sql_answer_stats);
        if ($stmt_answer_stats) {
            $stmt_answer_stats->bind_param("i", $current_attempt_id);
            $stmt_answer_stats->execute();
            $result_answer_stats = $stmt_answer_stats->get_result()->fetch_assoc();
            $stmt_answer_stats->close();

            $display_data['correct_answers_count'] = intval($result_answer_stats['correct_count'] ?? 0);
            $display_data['incorrect_answers_count_raw'] = intval($result_answer_stats['incorrect_count'] ?? 0);
            
            // Ensure total consistency for unanswered
            $answered_count = $display_data['correct_answers_count'] + $display_data['incorrect_answers_count_raw'];
            $display_data['unanswered_questions_count'] = $display_data['total_questions_in_quiz'] - $answered_count;
            if ($display_data['unanswered_questions_count'] < 0) {
                $display_data['unanswered_questions_count'] = 0; // Safety check
            }

            // Set global variables for chart (needed for cases where extract might not cover them before script)
            $correct_answers_count_for_chart = $display_data['correct_answers_count'];
            $incorrect_answers_count_for_chart = $display_data['incorrect_answers_count_raw'];
            $unanswered_questions_count_for_chart = $display_data['unanswered_questions_count'];

        } else {
            // Error preparing statement, initialize to 0 or handle
            $display_data['correct_answers_count'] = 0;
            $display_data['incorrect_answers_count_raw'] = 0;
            $display_data['unanswered_questions_count'] = $display_data['total_questions_in_quiz'];
        }


        if ($display_data['total_questions_in_quiz'] > 0 && $display_data['total_score'] !== null) { // Check total_score not null
            $percentage_score = ($display_data['total_score'] / $display_data['total_questions_in_quiz']) * 100;
            if ($percentage_score >= 80) {
                $display_data['feedback_message'] = "খুব ভালো!"; $display_data['feedback_class'] = "very-good";
            } elseif ($percentage_score >= 65) {
                $display_data['feedback_message'] = "ভালো!"; $display_data['feedback_class'] = "good";
            } elseif ($percentage_score >= 50) {
                $display_data['feedback_message'] = "সাধারণ"; $display_data['feedback_class'] = "average";
            } else {
                $display_data['feedback_message'] = "খারাপ (ইনশাআল্লাহ, পরের বার আরও ভালো হবে।)"; $display_data['feedback_class'] = "improve";
            }
        } elseif ($display_data['total_score'] === null) { // Handle case where score is null (e.g. incomplete attempt)
             $display_data['feedback_message'] = "ফলাফল প্রক্রিয়াধীন অথবা কুইজটি সম্পন্ন হয়নি।";
             $display_data['feedback_class'] = "average"; // Or some other neutral class
        }


        // FIX: The SQL query for review is updated to prevent duplicate question rows.
        $sql_review = "
            SELECT
                q.id AS question_id, q.question_text, q.image_url, q.explanation,
                (SELECT ua.selected_option_id FROM user_answers ua WHERE ua.question_id = q.id AND ua.attempt_id = ? ORDER BY ua.id DESC LIMIT 1) AS user_selected_option_id,
                (SELECT GROUP_CONCAT(CONCAT(o.id, '::', o.option_text, '::', o.is_correct) SEPARATOR '||')
                 FROM options o WHERE o.question_id = q.id ORDER BY o.id) AS all_options_details
            FROM questions q
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
        $_SESSION['flash_message'] = "কুইজের প্রচেষ্টা খুঁজে পাওয়া যায়নি ।";
        $_SESSION['flash_message_type'] = "warning";
    }
    $stmt_attempt->close();
    return $display_data;
}


// Handles quiz submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quiz_id']) && isset($_POST['attempt_id'])) {
    $quiz_id = intval($_POST['quiz_id']);
    $attempt_id = intval($_POST['attempt_id']);

    // Check if this attempt has already been scored to prevent re-processing.
    $sql_check_score = "SELECT score FROM quiz_attempts WHERE id = ?";
    $stmt_check_score = $conn->prepare($sql_check_score);
    $stmt_check_score->bind_param("i", $attempt_id);
    $stmt_check_score->execute();
    $result_check_score = $stmt_check_score->get_result();
    $attempt_score_data = $result_check_score->fetch_assoc();
    $stmt_check_score->close();

    if ($attempt_score_data && $attempt_score_data['score'] !== null) {
        // This attempt has already been processed. Redirect to the GET view.
        header("Location: results.php?attempt_id=" . $attempt_id . "&quiz_id=" . $quiz_id);
        exit;
    }

    // Calculate time taken
    $end_time_dt = new DateTime();
    $end_time = $end_time_dt->format('Y-m-d H:i:s');
    
    // *** START: MODIFIED time_taken_seconds CALCULATION ***
    $time_taken_seconds = 0; // Default to 0 to ensure it's always an integer

    $sql_get_start_time = "SELECT start_time FROM quiz_attempts WHERE id = ? AND user_id = ?";
    $stmt_get_start_time = $conn->prepare($sql_get_start_time);
    if (!$stmt_get_start_time) {
        error_log("Failed to prepare statement to get start time for attempt ID $attempt_id: " . $conn->error);
        // time_taken_seconds remains 0
    } else {
        $stmt_get_start_time->bind_param("ii", $attempt_id, $user_id);
        if (!$stmt_get_start_time->execute()) {
            error_log("Failed to execute statement to get start time for attempt ID $attempt_id: " . $stmt_get_start_time->error);
            // time_taken_seconds remains 0
        } else {
            $result_start_time = $stmt_get_start_time->get_result();
            if ($result_start_time->num_rows > 0) {
                $attempt_details_start = $result_start_time->fetch_assoc();
                if (!empty($attempt_details_start['start_time'])) {
                    try {
                        $start_timestamp_db = new DateTime($attempt_details_start['start_time']);
                        $time_taken_seconds = $end_time_dt->getTimestamp() - $start_timestamp_db->getTimestamp();
                        if ($time_taken_seconds < 0) $time_taken_seconds = 0; // Sanity check for clock issues
                    } catch (Exception $dateEx) {
                        error_log("Error creating DateTime from start_time for attempt ID $attempt_id: " . $dateEx->getMessage());
                        // time_taken_seconds remains 0
                    }
                } else {
                     error_log("start_time was empty for attempt_id: {$attempt_id}, user_id: {$user_id}. Using default 0 for time_taken.");
                }
            } else {
                error_log("Could not find start_time for attempt_id: {$attempt_id}, user_id: {$user_id}. Using default 0 for time_taken.");
                // time_taken_seconds remains 0
            }
        }
        $stmt_get_start_time->close();
    }
    // Now $time_taken_seconds is guaranteed to be an integer.
    // *** END: MODIFIED time_taken_seconds CALCULATION ***


    $questions_answered_correctly_post = 0;
    $incorrect_answers_count_post = 0;

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

        $submitted_answers = isset($_POST['answers']) ? $_POST['answers'] : [];
        foreach ($all_questions_map as $question_id_loop => $val) {
            $selected_option_id = isset($submitted_answers[$question_id_loop]) ? intval($submitted_answers[$question_id_loop]) : null;
            $is_correct_answer_for_db = 0;

            if ($selected_option_id !== null) {
                if (isset($correct_options_map[$question_id_loop]) && $correct_options_map[$question_id_loop] == $selected_option_id) {
                    $questions_answered_correctly_post++;
                    $is_correct_answer_for_db = 1;
                } else {
                    $incorrect_answers_count_post++;
                    $is_correct_answer_for_db = 0;
                }
            }
            
            if ($selected_option_id === null) {
                 $stmt_insert_answer_final = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, NULL, ?)");
                 if(!$stmt_insert_answer_final) throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ स्टेटमेंट প্রস্তুত করতে সমস্যা হয়েছে (NULL): " . $conn->error);
                 $stmt_insert_answer_final->bind_param("iii", $attempt_id, $question_id_loop, $is_correct_answer_for_db);
            } else {
                 $stmt_insert_answer_final = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?)");
                 if(!$stmt_insert_answer_final) throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ स्टेटमेंट প্রস্তুত করতে সমস্যা হয়েছে: " . $conn->error);
                 $stmt_insert_answer_final->bind_param("iiii", $attempt_id, $question_id_loop, $selected_option_id, $is_correct_answer_for_db);
            }
            if (!$stmt_insert_answer_final->execute()) {
                throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ করতে সমস্যা হয়েছে (প্রশ্ন ID: $question_id_loop): " . $stmt_insert_answer_final->error);
            }
            $stmt_insert_answer_final->close();
        }

        $penalty = $incorrect_answers_count_post * 0.20;
        $final_raw_score = $questions_answered_correctly_post - $penalty;
        $total_score = max(0, $final_raw_score); // Ensure score is not negative

        $sql_update_attempt = "UPDATE quiz_attempts SET score = ?, end_time = ?, time_taken_seconds = ?, submitted_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt_update_attempt = $conn->prepare($sql_update_attempt);
        if (!$stmt_update_attempt) throw new Exception("কুইজের চেষ্টা আপডেট স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
        
        $stmt_update_attempt->bind_param("dsiii", $total_score, $end_time, $time_taken_seconds, $attempt_id, $user_id);
        if (!$stmt_update_attempt->execute()) {
             throw new Exception("কুইজের চেষ্টা আপডেট করতে সমস্যা হয়েছে: " . $stmt_update_attempt->error);
        }
        $stmt_update_attempt->close();

        $conn->commit();

        $results_display_data = prepare_results_data($conn, $attempt_id, $quiz_id, $user_id);
        if ($results_display_data['success']) {
            extract($results_display_data);
            $correct_answers_count_for_chart = $results_display_data['correct_answers_count'];
            $incorrect_answers_count_for_chart = $results_display_data['incorrect_answers_count_raw'];
            $unanswered_questions_count_for_chart = $results_display_data['unanswered_questions_count'];
        } else {
            // If prepare_results_data fails, it already sets a flash message.
            // Redirect to prevent further issues on this page.
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
        // Global chart variables are already set inside prepare_results_data
    } else {
        // If prepare_results_data fails, it might have set a flash message.
        // If not, set a generic one.
        if (!isset($_SESSION['flash_message'])) {
             $_SESSION['flash_message'] = "ফলাফল দেখাতে একটি সমস্যা হয়েছে।";
             $_SESSION['flash_message_type'] = "danger";
        }
        header("Location: quizzes.php");
        exit;
    }

} else {
    $_SESSION['flash_message'] = "অবৈধ অনুরোধ।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: quizzes.php");
    exit;
}

// header.php must be included AFTER $page_title is set
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <?php // Chart.js CDN ?>

<style>
/* Print specific styles - unchanged */
@media print {
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
        border: 1px solid #eee !important; /* Add border for clarity in print */
    }
     .badge { /* Basic badge styling for print */
        border: 1px solid #ccc;
        padding: 0.3em 0.5em;
        background-color: #fff !important; /* Remove background colors for print */
        color: #000 !important; /* Ensure text is black */
    }
     .badge.bg-success-subtle, .badge.bg-danger-subtle, .badge.bg-warning-subtle {
        border: 1px solid #ccc !important; /* Consistent border */
    }
    .text-success-emphasis { color: #0f5132 !important; }
    .text-danger-emphasis { color: #581c24 !important; }
    .text-warning-emphasis { color: #664d03 !important; }

    .btn, .alert:not(.print-header-message), .timer-progress-bar, footer, header, .navbar, .footer,
    .feedback-message,
    .text-center.mb-4:has(h3),
    .text-center.mb-4:has(a.btn-info),
    hr,
    .card.shadow-sm > .card-header.bg-light,
    .card.shadow-sm > .card-body.p-4 > hr,
    .card.shadow-sm > .card-body.p-4 > .text-center.mt-4,
    #quizResultChartContainer /* Hide chart container in print */
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
    .question-image-review {
        max-height: 120px;
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
.question-image-review {
    max-width: 100%;
    height: auto;
    max-height: 200px;
    margin-bottom: 10px;
    border-radius: 4px;
    display: block;
    margin-left: auto;
    margin-right: auto;
    border: 1px solid #eee;
    padding: 3px;
}
/* Style for chart container */
#quizResultChartContainer {
    max-width: 450px; /* Adjust as needed */
    height: 300px;    /* Adjust as needed */
    margin: 20px auto;
}
/* Feedback message classes */
.feedback-message.very-good { background-color: var(--feedback-very-good-bg); color: var(--feedback-very-good-color); border-left: 5px solid var(--feedback-very-good-color); }
.feedback-message.good { background-color: var(--feedback-good-bg); color: var(--feedback-good-color); border-left: 5px solid var(--feedback-good-color); }
.feedback-message.average { background-color: var(--feedback-average-bg); color: var(--feedback-average-color); border-left: 5px solid var(--feedback-average-color); }
.feedback-message.improve { background-color: var(--feedback-improve-bg); color: var(--feedback-improve-color); border-left: 5px solid var(--feedback-improve-color); }

</style>

<div class="container mt-5">
    <?php display_flash_message('flash_message', 'flash_message_type'); ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h2 class="text-center mb-0"><?php echo $page_title; ?></h2>
        </div>
        <div class="card-body p-4">
            <div class="text-center mb-4">
                 <h3>আপনি পেয়েছেন: <strong class="text-primary">
                    <?php echo ($total_score !== null) ? number_format($total_score, 2) : 'N/A'; ?> / <?php echo $total_questions_in_quiz; ?>
                 </strong></h3>
                <p class="feedback-message <?php echo $feedback_class; ?>"><?php echo $feedback_message; // feedback_message itself will be set if score is null by prepare_results_data ?></p>
                <?php if ($time_taken_seconds !== null): ?>
                <p class="text-muted">সময় লেগেছে: <?php echo format_seconds_to_hms($time_taken_seconds); ?> (ঘন্টা:মিনিট:সেকেন্ড)</p>
                <?php else: ?>
                <p class="text-muted">সময় লেগেছে: N/A</p>
                <?php endif; ?>
            </div>

            <div id="quizResultChartContainer" class="mb-4">
                <canvas id="quizResultChart"></canvas>
            </div>
            <div class="text-center mb-4">
                <?php
                // র‍্যাংকিং বাটন দেখানোর শর্ত
                $show_ranking_button = false;
                $ranking_button_disabled = false;
                $ranking_unavailable_message_btn = '';

                if ($quiz_info_result) {
                    $quiz_type = $quiz_info_result['quiz_type'];
                    $quiz_status = $quiz_info_result['status'];
                    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

                    if ($is_admin || $quiz_type === 'weekly') {
                        $show_ranking_button = true;
                    } elseif ($quiz_type === 'monthly' || $quiz_type === 'general') {
                        $show_ranking_button = true;
                        if ($quiz_status !== 'archived') {
                            $ranking_button_disabled = true;
                            $ranking_unavailable_message_btn = ($quiz_type === 'monthly')
                                ? 'মাসিক পরীক্ষার র‍্যাংকিং তালিকা কুইজটি আর্কাইভ হওয়ার পর প্রকাশ করা হবে।'
                                : 'সাধারণ কুইজের র‍্যাংকিং তালিকা কুইজটি আর্কাইভ হওয়ার পর প্রকাশ করা হবে।';
                        }
                    }
                }

                if ($show_ranking_button) {
                    if ($ranking_button_disabled) {
                        echo '<button class="btn btn-info" disabled>আপনার র‍্যাংকিং দেখুন</button>';
                        echo '<p class="mt-2 text-muted small">' . $ranking_unavailable_message_btn . '</p>';
                    } else {
                        echo '<a href="ranking.php?quiz_id=' . $quiz_id . '&attempt_id=' . $attempt_id . '" class="btn btn-info">আপনার র‍্যাংকিং দেখুন</a>';
                    }
                }
                ?>
            </div>

            <?php
            // ফলাফল পর্যালোচনা দেখানোর শর্ত
            $show_review_section = false;
            $review_unavailable_message = '';

            if ($quiz_info_result) {
                $quiz_type = $quiz_info_result['quiz_type'];
                $quiz_status = $quiz_info_result['status'];
                $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

                if ($is_admin) {
                    $show_review_section = true;
                } elseif ($quiz_type === 'weekly' || $quiz_type === 'general') {
                    $show_review_section = true;
                } elseif ($quiz_type === 'monthly') {
                    if ($quiz_status === 'archived') {
                        $show_review_section = true;
                    } else {
                        $review_unavailable_message = "এই কুইজের ফলাফল ও উত্তর পর্যালোচনা তালিকাটি কুইজটি আর্কাইভ হওয়ার পর প্রকাশ করা হবে।";
                    }
                }
            }
            ?>

            <?php if ($show_review_section): ?>
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
                                            if ($option['is_correct'] == 1) {
                                                $correct_option_id_for_this_q = $option['id'];
                                                break;
                                            }
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
                    <a href="profile.php" class="btn btn-outline-info">আমার প্রোফাইল</a>
                </div>
            <?php else: ?>
                <hr>
                <div class="alert alert-info text-center mt-4">
                    <h5><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-hourglass-split me-2" viewBox="0 0 16 16"><path d="M2.5 15a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.138.443-.377.443-.64V4.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v.218c0 .263.153.502.443.64A4.5 4.5 0 0 1 12.5 9h1v1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1a.5.5 0 0 1-.5-.5h-1a.5.5 0 0 1-.5.5v1a.5.5 0 0 1-1 0v-1a.5.5 0 0 1-.5-.5h-1a.5.5 0 0 1-.5.5v1a.5.5 0 0 1-1 0zM12 1v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0V3a.5.5 0 0 1-.5-.5h-2a.5.5 0 0 1-.5.5v1a.5.5 0 0 1-1 0V3a.5.5 0 0 1-.5-.5h-1a.5.5 0 0 1-.5.5v1a.5.5 0 0 1-1 0V2h-1a.5.5 0 0 1 0-1z"/></svg>ফলাফল অপেক্ষমাণ</h5>
                    <p><?php echo $review_unavailable_message; ?></p>
                    <a href="quizzes.php" class="btn btn-secondary mt-2">অন্যান্য কুইজ দেখুন</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function printAnswerSheet() {
    window.print();
}

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('quizResultChart');
    if (ctx) {
        const correctAnswers = <?php echo $correct_answers_count_for_chart; ?>;
        const incorrectAnswers = <?php echo $incorrect_answers_count_for_chart; ?>;
        const unansweredQuestions = <?php echo $unanswered_questions_count_for_chart; ?>;
        const totalQuestionsForChart = <?php echo $total_questions_in_quiz; ?>;

        let finalUnanswered = unansweredQuestions;
         // Ensure sum of parts equals total for chart display
        if (totalQuestionsForChart > 0 && (correctAnswers + incorrectAnswers + unansweredQuestions) !== totalQuestionsForChart) {
            finalUnanswered = totalQuestionsForChart - (correctAnswers + incorrectAnswers);
            if (finalUnanswered < 0) finalUnanswered = 0; // Ensure non-negative
        }
        
        // Ensure chart is only rendered if there are questions
        if (totalQuestionsForChart > 0) {
            new Chart(ctx, {
                type: 'pie', // আপনি 'bar' ও ব্যবহার করতে পারেন
                data: {
                    labels: ['সঠিক উত্তর', 'ভুল উত্তর', 'উত্তর দেননি'],
                    datasets: [{
                        label: 'ফলাফলের পরিসংখ্যান',
                        data: [correctAnswers, incorrectAnswers, finalUnanswered],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',  // সবুজ (সঠিক)
                            'rgba(255, 99, 132, 0.7)',   // লাল (ভুল)
                            'rgba(201, 203, 207, 0.7)'   // ধূসর (উত্তর দেননি)
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(201, 203, 207, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                             labels: {
                                font: {
                                    family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'কুইজের পরিসংখ্যান',
                            font: {
                                size: 16,
                                family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed;
                                        if (totalQuestionsForChart > 0) { // Check to prevent division by zero
                                            const percentage = ((context.parsed / totalQuestionsForChart) * 100).toFixed(1);
                                            label += ` (${percentage}%)`;
                                        }
                                    }
                                    return label;
                                }
                            },
                             bodyFont: {
                                family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'
                            },
                            titleFont: {
                                 family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'
                            }
                        }
                    }
                }
            });
        } else {
            // যদি কোনো প্রশ্ন না থাকে, তাহলে চার্ট কন্টেইনার বা ক্যানভাস হাইড করুন
            const chartContainer = document.getElementById('quizResultChartContainer');
            if(chartContainer) {
                chartContainer.innerHTML = '<p class="text-muted text-center">ফলাফল পরিসংখ্যান দেখানোর জন্য কোনো প্রশ্ন ছিল না।</p>';
            }
        }
    }
});
</script>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>