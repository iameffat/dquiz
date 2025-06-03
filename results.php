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
$total_score = 0; // এটি চূড়ান্ত স্কোর সংরক্ষণ করবে
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

        // Verify user ID
        if ($attempt_data['user_id'] != $current_user_id) {
            $_SESSION['flash_message'] = "আপনি এই কুইজের ফলাফল দেখার জন্য অনুমোদিত নন।";
            $_SESSION['flash_message_type'] = "warning";
            return $display_data; // Important: stop further processing
        }


        $display_data['total_score'] = $attempt_data['score']; // This will be the final score with negative marking
        $display_data['time_taken_seconds'] = $attempt_data['time_taken_seconds'];

        // Fetch quiz info (title, total questions)
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


        // Feedback message based on score
        if ($display_data['total_questions_in_quiz'] > 0) {
            // Calculate percentage based on correct answers before negative marking for feedback categories if desired
            // Or, base it on the final score. Let's use final score for now.
            // If max possible score is total_questions (e.g., 1 per correct, 0 for wrong/unanswered before penalty)
            // Then percentage would be (final_score / total_questions) * 100
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
        }


        // Fetch questions and user's answers for review
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
            $row['options_list'] = $options_array; // Add parsed options to the row
            $display_data['review_questions'][] = $row;
        }
        $stmt_review->close();
        $display_data['success'] = true; // Mark data preparation as successful

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

    // Calculate time taken
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

    // Initialize scoring variables
    $questions_answered_correctly = 0;
    $incorrect_answers_count = 0; // <--- নতুন সংযোজন: ভুল উত্তরের সংখ্যা

    // Get total questions in the quiz (for display consistency)
    $sql_all_q_count = "SELECT COUNT(id) as total_q FROM questions WHERE quiz_id = ?";
    $stmt_all_q_count = $conn->prepare($sql_all_q_count);
    $stmt_all_q_count->bind_param("i", $quiz_id);
    $stmt_all_q_count->execute();
    $total_questions_in_quiz_from_db = $stmt_all_q_count->get_result()->fetch_assoc()['total_q'];
    $stmt_all_q_count->close();
    $total_questions_in_quiz = $total_questions_in_quiz_from_db; // prepare_results_data তেও ব্যবহৃত হয়


    $conn->begin_transaction();
    try {
        // Fetch all questions and their correct options for the quiz
        $sql_questions_options = "SELECT q.id as question_id, o.id as option_id, o.is_correct
                                  FROM questions q
                                  JOIN options o ON q.id = o.question_id
                                  WHERE q.quiz_id = ?";
        $stmt_qo = $conn->prepare($sql_questions_options);
        $stmt_qo->bind_param("i", $quiz_id);
        $stmt_qo->execute();
        $qo_results = $stmt_qo->get_result();
        $correct_options_map = []; // Map [question_id => correct_option_id]
        $all_questions_map = [];   // Map [question_id => true] to iterate all questions
        while($qo_row = $qo_results->fetch_assoc()){
            $all_questions_map[$qo_row['question_id']] = true; // Mark question as existing
            if($qo_row['is_correct'] == 1){
                $correct_options_map[$qo_row['question_id']] = $qo_row['option_id'];
            }
        }
        $stmt_qo->close();

        // Process each question
        foreach ($all_questions_map as $question_id_loop => $val) {
            $selected_option_id = isset($submitted_answers[$question_id_loop]) ? intval($submitted_answers[$question_id_loop]) : null;
            $is_correct_answer_for_db = 0; // For user_answers table

            if ($selected_option_id !== null) { // User answered this question
                if (isset($correct_options_map[$question_id_loop]) && $correct_options_map[$question_id_loop] == $selected_option_id) {
                    $questions_answered_correctly++;
                    $is_correct_answer_for_db = 1;
                } else {
                    $incorrect_answers_count++; // <--- ভুল উত্তর গণনা করুন
                    $is_correct_answer_for_db = 0;
                }
            } else {
                // User did not answer this question, no penalty, is_correct_answer_for_db remains 0
            }

            // Save user's answer (or lack thereof)
            if ($selected_option_id === null) { // If not answered
                 $stmt_insert_answer_final = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, NULL, ?)");
                 if(!$stmt_insert_answer_final) throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ स्टेटमेंट প্রস্তুত করতে সমস্যা হয়েছে (NULLสำหรับ): " . $conn->error);
                 $stmt_insert_answer_final->bind_param("iii", $attempt_id, $question_id_loop, $is_correct_answer_for_db);
            } else { // If answered
                 $stmt_insert_answer_final = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?)");
                 if(!$stmt_insert_answer_final) throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ स्टेटमेंट প্রস্তুত করতে সমস্যা হয়েছে: " . $conn->error);
                 $stmt_insert_answer_final->bind_param("iiii", $attempt_id, $question_id_loop, $selected_option_id, $is_correct_answer_for_db);
            }
            if (!$stmt_insert_answer_final->execute()) {
                throw new Exception("ব্যবহারকারীর উত্তর সংরক্ষণ করতে সমস্যা হয়েছে (প্রশ্ন ID: $question_id_loop): " . $stmt_insert_answer_final->error);
            }
            $stmt_insert_answer_final->close();
        }

        // Calculate final score with negative marking
        $penalty = $incorrect_answers_count * 0.20; // <--- পেনাল্টি হিসাব করুন
        $final_raw_score = $questions_answered_correctly - $penalty; // <--- পেনাল্টি বাদ দিন
        $total_score = max(0, $final_raw_score); // <--- স্কোর যেন ০ এর নিচে না যায়

        // Update quiz_attempts table
        $sql_update_attempt = "UPDATE quiz_attempts SET score = ?, end_time = ?, time_taken_seconds = ?, submitted_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt_update_attempt = $conn->prepare($sql_update_attempt);
        // Use 'd' for double since score can be decimal now
        $stmt_update_attempt->bind_param("dsiii", $total_score, $end_time, $time_taken_seconds, $attempt_id, $user_id);
        if (!$stmt_update_attempt->execute()) {
             throw new Exception("কুইজের চেষ্টা আপডেট করতে সমস্যা হয়েছে: " . $stmt_update_attempt->error);
        }
        $stmt_update_attempt->close();

        $conn->commit();

        // Prepare data for display using the common function
        $results_display_data = prepare_results_data($conn, $attempt_id, $quiz_id, $user_id);
        if ($results_display_data['success']) {
            extract($results_display_data); // Extracts variables for the view
        } else {
            // If prepare_results_data fails (e.g., flash message already set by it)
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
    // This part is for viewing results of a previously completed quiz
    $attempt_id = intval($_GET['attempt_id']);
    $quiz_id = intval($_GET['quiz_id']);

    if ($quiz_id <= 0 || $attempt_id <= 0) {
        $_SESSION['flash_message'] = "অবৈধ আইডি প্রদান করা হয়েছে।";
        $_SESSION['flash_message_type'] = "warning";
        header("Location: quizzes.php");
        exit;
    }

    // Prepare data for display using the common function
    $results_display_data = prepare_results_data($conn, $attempt_id, $quiz_id, $user_id);

    if ($results_display_data['success']) {
        extract($results_display_data); // Extracts variables like $page_title, $total_score etc.
    } else {
        // If prepare_results_data fails (e.g., flash message already set by it)
        header("Location: quizzes.php"); // Redirect or handle error
        exit;
    }

} else {
    $_SESSION['flash_message'] = "অবৈধ অনুরোধ।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: quizzes.php");
    exit;
}

// header.php must be included AFTER $page_title is set
require_once 'includes/header.php'; //
?>

<style>
/* Print specific styles */
@media print {
    /* ... (আপনার বর্তমান প্রিন্ট স্টাইল অপরিবর্তিত থাকবে) ... */
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
    /* Ensure text colors for badges are distinguishable in print if needed */
    .text-success-emphasis { color: #0f5132 !important; }
    .text-danger-emphasis { color: #581c24 !important; }
    .text-warning-emphasis { color: #664d03 !important; }


    /* Hide non-essential elements for print */
    .btn, .alert:not(.print-header-message), .timer-progress-bar, footer, header, .navbar, .footer,
    .feedback-message, /* Hide the colorful feedback message */
    .text-center.mb-4:has(h3), /* Hide the main score display */
    .text-center.mb-4:has(a.btn-info), /* Hide ranking button container */
    hr,
    .card.shadow-sm > .card-header.bg-light, /* Hide the main card header */
    .card.shadow-sm > .card-body.p-4 > hr, /* Hide hr in main card body */
    .card.shadow-sm > .card-body.p-4 > .text-center.mt-4 /* Hide bottom buttons container */
     {
        display: none !important;
    }

    .print-header { /* Header for print */
        visibility: visible;
        text-align: center;
        margin-bottom: 20px;
        font-size: 1.4rem; /* Adjust as needed */
        font-weight: bold;
        width: 100%;
    }
    .print-header-message { /* For messages like "আপনি এই প্রশ্নের উত্তর দেননি।" */
        visibility: visible;
        display: block !important; /* Ensure it's displayed */
    }

    /* Layout for answer review in print */
    .answer-review {
        column-count: 2;
        column-gap: 20px;
        font-size: 10pt; /* Smaller font for print */
    }
    .question-image-review { /* Style for images in print review */
        max-height: 120px; /* Adjust as needed for print layout */
        margin-bottom: 5px;
    }

    .answer-review .card {
        page-break-inside: avoid;
        break-inside: avoid-column;
        width: 100%; /* Ensure full width within column */
        margin-bottom: 15px !important; /* Space between cards in print */
        font-size: inherit; /* Inherit from .answer-review */
        border: 1px solid #ddd !important; /* Add a light border to cards in print */
    }
    .answer-review .card-header, .answer-review .card-body {
        padding: 0.5rem !important;
        font-size: inherit;
        border: none !important; /* Remove internal borders of card parts for cleaner print */
    }
    .answer-review .list-group-item {
        padding: 0.3rem 0.5rem !important;
        font-size: 0.9em; /* Slightly smaller options text */
        border: 1px solid #eee !important; /* Border for options in print */
    }
    .answer-review .card-header strong {
        font-size: 1.1em; /* Slightly larger question number */
    }
    .answer-review .mt-3.p-2.bg-light.border.rounded { /* Explanation box */
        padding: 0.3rem !important;
        font-size: 0.9em;
        margin-top: 0.5rem !important;
        background-color: #f8f9fa !important; /* Light background for explanation */
        border: 1px solid #ddd !important;
    }
    /* Hide the on-screen "উত্তর পর্যালোচনা" heading for print as we have .print-header */
    #printableArea > h3.mt-4.mb-3 { /* Specifically target the H3 for review section title */
        display: none !important;
    }
    /* Ensure "Print" and "Back to Quizzes" buttons are hidden in print */
    .container.mt-5 > .card.shadow-sm > .card-body.p-4 > .text-center.my-3:has(button) { /* Target the specific div holding the print button */
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
                <h3>আপনি পেয়েছেন: <strong class="text-primary"><?php echo number_format($total_score, 2); // দশমিক সহ স্কোর দেখান ?> / <?php echo $total_questions_in_quiz; ?></strong></h3>
                <p class="feedback-message <?php echo $feedback_class; ?>">এই পরীক্ষায় আপনার প্রস্তুতি ছিল: <?php echo $feedback_message; ?></p>
                <?php if ($time_taken_seconds !== null): ?>
                <p class="text-muted">সময় লেগেছে: <?php echo format_seconds_to_hms($time_taken_seconds); ?> (ঘন্টা:মিনিট:সেকেন্ড)</p>
                <?php endif; ?>
            </div>

            <div class="text-center mb-4">
                <?php
                // Determine if ranking should be shown immediately
                $show_ranking_now = true;
                $ranking_button_text = "আপনার র‍্যাংকিং দেখুন"; // Default text

                // If quiz_info_result is available and the quiz is live
                if ($quiz_info_result && $quiz_info_result['status'] == 'live') {
                    // If there's a specific live end time
                    if (!empty($quiz_info_result['live_end_datetime'])) {
                        try {
                            $live_end = new DateTime($quiz_info_result['live_end_datetime']);
                            $now = new DateTime();
                            if ($now < $live_end) {
                                // If current time is before live end time, ranking is not final
                                $show_ranking_now = false;
                                // $ranking_button_text = "র‍্যাংকিং দেখুন (লাইভ চলছে)"; // Optional: change text
                            }
                        } catch (Exception $e) {
                            // Invalid date format, assume ranking can be shown
                        }
                    } else {
                        // If live quiz has no end time (e.g., ongoing indefinitely until manually archived)
                        // Ranking can be shown but it's not "final" in the sense of a timed competition
                         $show_ranking_now = true; // Or set to false if you want to delay for such quizzes too
                         // $ranking_button_text = "বর্তমান র‍্যাংকিং দেখুন";
                    }
                }
                // For archived, upcoming, draft quizzes, ranking (if available) can be shown.

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
                                    // Determine the correct option ID for this question
                                    foreach ($question['options_list'] as $option) {
                                        if ($option['is_correct'] == 1) {
                                            $correct_option_id_for_this_q = $option['id'];
                                            break;
                                        }
                                    }
                                    // Check if user's selected option (if any) was the correct one
                                    if ($question['user_selected_option_id'] !== null && $question['user_selected_option_id'] == $correct_option_id_for_this_q) {
                                        $user_correct_for_this_q = true;
                                    }
                                    ?>
                                    <?php foreach ($question['options_list'] as $option): ?>
                                        <?php
                                        $option_class = 'list-group-item';
                                        $option_label = '';
                                        // Check if this option was selected by the user
                                        if ($option['id'] == $question['user_selected_option_id']) {
                                            if ($option['is_correct'] == 1) {
                                                $option_class .= ' correct-user-answer'; // User selected the correct answer
                                                $option_label = ' <span class="badge bg-success-subtle text-success-emphasis rounded-pill">আপনার সঠিক উত্তর</span>';
                                            } else {
                                                $option_class .= ' incorrect-user-answer'; // User selected an incorrect answer
                                                $option_label = ' <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill">আপনার ভুল উত্তর</span>';
                                            }
                                        } elseif ($option['is_correct'] == 1) {
                                            // This is the correct answer, but user didn't select it (or selected something else)
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
                                <?php if ($question['user_selected_option_id'] === null && !$user_correct_for_this_q) : // User did not answer and it was not correct (implicitly) ?>
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
            </div> <div class="text-center mt-4">
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
require_once 'includes/footer.php'; //
?>