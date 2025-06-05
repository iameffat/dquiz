<?php
// quiz_page.php
$page_title = ""; // $page_title কুইজের বিবরণ আনার পর সেট করা হবে
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$quiz_info_for_display = null;
$questions = [];
$total_questions = 0;
$quiz_duration_seconds = 0;
$attempt_id = null;


if ($quiz_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ কুইজ ID।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: quizzes.php");
    exit;
}

// Fetch basic quiz details for display, regardless of login status (needed early)
$sql_quiz_details_basic = "SELECT q.id, q.title, q.description, q.duration_minutes, q.status, q.live_start_datetime, q.live_end_datetime,
                          (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                          FROM quizzes q WHERE q.id = ?";
$stmt_quiz_details_basic = $conn->prepare($sql_quiz_details_basic);

if ($stmt_quiz_details_basic) {
    $stmt_quiz_details_basic->bind_param("i", $quiz_id);
    $stmt_quiz_details_basic->execute();
    $result_quiz_details_basic = $stmt_quiz_details_basic->get_result();
    if ($result_quiz_details_basic->num_rows === 1) {
        $quiz_info_for_display = $result_quiz_details_basic->fetch_assoc();
        $page_title = escape_html($quiz_info_for_display['title']) . " - কুইজ";
    } else {
        $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id}) খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: quizzes.php");
        exit;
    }
    $stmt_quiz_details_basic->close();
} else {
    error_log("Prepare failed for basic quiz details: (" . $conn->errno . ") " . $conn->error);
    $_SESSION['flash_message'] = "কুইজের তথ্য আনতে ডেটাবেস সমস্যা হয়েছে।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: quizzes.php");
    exit;
}

// --- Logic before any HTML output (including header.php) ---
$can_take_quiz = false;
$access_message = '';
$show_quiz_interface = false; // Flag to indicate if quiz UI should be shown

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // User is NOT logged in.
} else {
    // User IS logged in.
    $user_id = $_SESSION['user_id'];
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
    $quiz = $quiz_info_for_display; 

    if ($user_role !== 'admin') {
        $sql_check_existing_attempt = "SELECT id, score, end_time FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? LIMIT 1";
        $stmt_check_existing_attempt = $conn->prepare($sql_check_existing_attempt);
        
        if (!$stmt_check_existing_attempt) {
            error_log("Prepare failed for existing attempt check: (" . $conn->errno . ") " . $conn->error);
            $_SESSION['flash_message'] = "ডেটাবেস সমস্যা। অনুগ্রহ করে আবার চেষ্টা করুন। (Error: QPEP1)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $stmt_check_existing_attempt->bind_param("ii", $user_id, $quiz_id);
        if (!$stmt_check_existing_attempt->execute()) {
            error_log("Execute failed for existing attempt check: (" . $stmt_check_existing_attempt->errno . ") " . $stmt_check_existing_attempt->error);
            $_SESSION['flash_message'] = "ডেটাবেস সমস্যা। অনুগ্রহ করে আবার চেষ্টা করুন। (Error: QPEE1)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $result_existing_attempt = $stmt_check_existing_attempt->get_result();
        $existing_attempt_data = $result_existing_attempt->fetch_assoc();
        $stmt_check_existing_attempt->close();

        if ($existing_attempt_data) {
            $_SESSION['flash_message'] = ($existing_attempt_data['score'] === null && $existing_attempt_data['end_time'] === null) ?
                                        "আপনি ইতিমধ্যে এই কুইজে একবার প্রবেশ করেছিলেন কিন্তু সম্পন্ন করেননি। আপনার অসমাপ্ত চেষ্টার ফলাফল দেখানো হচ্ছে অথবা কুইজটি নতুন করে শুরু হতে পারে।" :
                                        "আপনি ইতিমধ্যে এই কুইজটি সম্পন্ন করেছেন। নিচে আপনার আগের ফলাফল দেখানো হলো।";
            $_SESSION['flash_message_type'] = ($existing_attempt_data['score'] === null && $existing_attempt_data['end_time'] === null) ? "warning" : "info";
            header("Location: results.php?attempt_id=" . $existing_attempt_data['id'] . "&quiz_id=" . $quiz_id);
            exit; 
        }
    }

    $current_datetime = new DateTime();
    if ($user_role === 'admin') {
        $can_take_quiz = true; 
        $access_message = "আপনি অ্যাডমিন হিসেবে এই কুইজটি দেখছেন/টেস্ট করছেন।";
    } else { 
        if ($quiz['status'] == 'draft') {
            $access_message = "এই কুইজটি এখন অংশগ্রহণের জন্য উপলব্ধ নয় কারণ এটি এখনও ড্রাফট পর্যায়ে রয়েছে।";
        } elseif ($quiz['status'] == 'upcoming') {
            $access_message = "এই কুইজটি এখনও শুরু হয়নি (আপকামিং)।";
            if ($quiz['live_start_datetime']) {
                try {
                    $live_start_dt_check = new DateTime($quiz['live_start_datetime']);
                    if ($current_datetime < $live_start_dt_check) {
                         $access_message .= " সম্ভাব্য শুরু: " . format_datetime($quiz['live_start_datetime']);
                    } else { 
                        $can_take_quiz = true; 
                    }
                } catch (Exception $e) { /* Invalid date format */ }
            }
        } elseif ($quiz['status'] == 'live') {
            $can_take_quiz_live_check = true;
            if ($quiz['live_start_datetime'] !== null) {
                try {
                    $live_start_dt = new DateTime($quiz['live_start_datetime']);
                    if ($current_datetime < $live_start_dt) {
                        $can_take_quiz_live_check = false;
                        $access_message = "এই কুইজটি এখনও শুরু হয়নি। শুরু হওয়ার সময়: " . format_datetime($quiz['live_start_datetime']);
                    }
                } catch (Exception $e) { $can_take_quiz_live_check = false; $access_message = "কুইজের শুরুর সময় নির্ধারণে ত্রুটি।"; }
            }
            if ($can_take_quiz_live_check && $quiz['live_end_datetime'] !== null) {
                try {
                    $live_end_dt = new DateTime($quiz['live_end_datetime']);
                    if ($current_datetime > $live_end_dt) {
                        $can_take_quiz_live_check = false;
                        $access_message = "দুঃখিত, এই কুইজে অংশগ্রহণের সময়সীমা শেষ হয়ে গিয়েছে।";
                    }
                } catch (Exception $e) { $can_take_quiz_live_check = false; $access_message = "কুইজের শেষের সময় নির্ধারণে ত্রুটি।"; }
            }
            if ($can_take_quiz_live_check) {
                $can_take_quiz = true;
            }
        } elseif ($quiz['status'] == 'archived') {
            $can_take_quiz = true; 
        }
    }

    if ($can_take_quiz) {
        $sql_questions = "SELECT id, question_text, image_url FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC";
        $stmt_questions = $conn->prepare($sql_questions);
        if (!$stmt_questions) {
             error_log("Prepare failed for questions fetch: (" . $conn->errno . ") " . $conn->error);
             $_SESSION['flash_message'] = "প্রশ্ন আনতে ডেটাবেস সমস্যা। (Error: QPEP2)"; $_SESSION['flash_message_type'] = "danger";
             header("Location: quizzes.php"); exit;
        }
        $stmt_questions->bind_param("i", $quiz_id);
        if (!$stmt_questions->execute()) {
            error_log("Execute failed for questions fetch: (" . $stmt_questions->errno . ") " . $stmt_questions->error);
             $_SESSION['flash_message'] = "প্রশ্ন আনতে ডেটাবেস সমস্যা। (Error: QPEE2)"; $_SESSION['flash_message_type'] = "danger";
             header("Location: quizzes.php"); exit;
        }
        $result_questions = $stmt_questions->get_result();
        while ($q_row = $result_questions->fetch_assoc()) {
            $options_for_q = [];
            $sql_options = "SELECT id, option_text FROM options WHERE question_id = ?";
            $stmt_options = $conn->prepare($sql_options);
             if (!$stmt_options) { error_log("Prepare failed for options fetch: (" . $conn->errno . ") " . $conn->error); continue; }
            $stmt_options->bind_param("i", $q_row['id']);
            if (!$stmt_options->execute()) { error_log("Execute failed for options fetch: (" . $stmt_options->errno . ") " . $stmt_options->error); $stmt_options->close(); continue;}
            $result_options_data = $stmt_options->get_result();
            while ($opt_row = $result_options_data->fetch_assoc()) {
                $options_for_q[] = $opt_row;
            }
            $stmt_options->close();
            shuffle($options_for_q);
            $q_row['options'] = $options_for_q;
            $questions[] = $q_row;
        }
        $stmt_questions->close();
        
        $total_questions = count($questions);
        $quiz_duration_seconds = $quiz['duration_minutes'] * 60;

        if ($total_questions === 0) {
            if ($user_role !== 'admin' && $quiz['status'] !== 'archived') {
                $can_take_quiz = false; 
                $access_message = "দুঃখিত, এই কুইজে এখনো কোনো প্রশ্ন যোগ করা হয়নি।";
            } elseif ($user_role === 'admin' || $quiz['status'] === 'archived') {
                $access_message = ($user_role === 'admin') ? "অ্যাডমিন ভিউ: এই কুইজে কোনো প্রশ্ন যোগ করা হয়নি।" : "এই কুইজে অনুশীলনের জন্য কোনো প্রশ্ন পাওয়া যায়নি।";
                $can_take_quiz = false; 
            }
        }

        if ($can_take_quiz) { 
            $start_time = date('Y-m-d H:i:s');
            $user_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $user_agent_string = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
            $parsed_ua = parse_user_agent_simple($user_agent_string);
            $browser_name = $parsed_ua['browser'];
            $os_platform = $parsed_ua['os'];

            $sql_start_attempt = "INSERT INTO quiz_attempts (user_id, quiz_id, start_time, ip_address, user_agent, browser_name, os_platform) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_start_attempt = $conn->prepare($sql_start_attempt);
            if (!$stmt_start_attempt) {
                 error_log("Prepare failed for start attempt: (" . $conn->errno . ") " . $conn->error);
                 $_SESSION['flash_message'] = "কুইজ শুরু করতে ডেটাবেস সমস্যা। (Error: QPSA1)"; $_SESSION['flash_message_type'] = "danger";
                 header("Location: quizzes.php"); exit;
            }
            $stmt_start_attempt->bind_param("iisssss", $user_id, $quiz_id, $start_time, $user_ip_address, $user_agent_string, $browser_name, $os_platform);
            if ($stmt_start_attempt->execute()) {
                $attempt_id = $stmt_start_attempt->insert_id;
                $show_quiz_interface = true; 
            } else {
                error_log("Execute failed for start attempt: (" . $stmt_start_attempt->errno . ") " . $stmt_start_attempt->error);
                $_SESSION['flash_message'] = "কুইজ শুরু করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন। (Error: QPSE1)";
                $_SESSION['flash_message_type'] = "danger";
                header("Location: quizzes.php");
                exit;
            }
            $stmt_start_attempt->close();
        }
    }
}
// --- End of logic before HTML output ---

$page_specific_styles = "
    .blur-background { filter: blur(5px); transition: filter 0.3s ease-in-out; }
    #quizContainer.blur-background, .timer-progress-bar.blur-background { /* Specificity to ensure blur applies */ }
    .disable-text-selection { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    .question-image { max-width: 100%; height: auto; max-height: 350px; margin-bottom: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: block; margin-left: auto; margin-right: auto; border: 1px solid var(--border-color); padding: 3px; background-color: var(--body-bg); }
    body.dark-mode .question-image { box-shadow: 0 2px 5px rgba(255,255,255,0.05); border-color: var(--border-color); background-color: var(--body-bg); }
    .timer-progress-bar { position: sticky; top: 0; z-index: 1030; background-color: var(--timer-progress-bar-bg); padding: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-bottom: 1px solid var(--border-color); }
    body.dark-mode .timer-progress-bar { box-shadow: 0 2px 4px rgba(255,255,255,0.1); }
    .timer.critical { color: var(--danger-color) !important; font-weight: bold; }
    
    /* Custom Radio Button Styles */
    .custom-radio-option {
        position: relative; 
    }
    .real-radio-btn { /* Hide the actual radio button */
        opacity: 0;
        position: absolute;
        left: 0; /* Position it behind the custom one or off-screen */
        width: 1.5em; /* Ensure it's clickable over the custom one */
        height: 1.5em;
        z-index: 2; /* Above custom-radio-look but below label text if needed */
        cursor: pointer;
    }
    .custom-radio-label {
        display: flex; 
        align-items: center;
        cursor: pointer;
        padding-left: 0 !important; 
    }
    .custom-radio-look {
        display: inline-flex; 
        align-items: center;
        justify-content: center;
        width: 1.5em;  
        height: 1.5em; 
        border: 2px solid var(--bs-border-color); 
        border-radius: 50%;
        margin-right: 0.75em; 
        transition: border-color 0.15s ease-in-out, background-color 0.15s ease-in-out;
        font-size: 0.9em; 
        font-weight: bold;
        color: var(--body-color); 
        line-height: 1; 
        position: relative;
        z-index: 1;
    }
    .custom-radio-look::before {
        content: attr(data-char);
    }
    .real-radio-btn:checked + .custom-radio-label .custom-radio-look {
        background-color: var(--primary-color); 
        border-color: var(--primary-color); 
        color: #fff; 
    }
    .real-radio-btn:focus + .custom-radio-label .custom-radio-look {
         box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), .25);
    }
    .form-check-input.real-radio-btn:disabled + .custom-radio-label {
        opacity: 0.6;
        cursor: default;
    }
     .form-check-input.real-radio-btn:disabled + .custom-radio-label .custom-radio-look {
        background-color: var(--bs-secondary-bg);
        border-color: var(--bs-border-color);
        color: var(--bs-secondary-color);
    }
    .form-check-input.real-radio-btn:checked:disabled + .custom-radio-label {
         opacity: 1; /* Selected but disabled should still look selected */
    }
     .form-check-input.real-radio-btn:checked:disabled + .custom-radio-label .custom-radio-look {
        background-color: var(--primary-color); /* Keep checked style even if disabled */
        border-color: var(--primary-color);
        color: #fff;
        opacity: 0.7; /* Slightly dim the checked+disabled state */
    }

    .option-text {
        flex-grow: 1;
        z-index: 1; /* Ensure text is clickable */
    }
    body.dark-mode .custom-radio-look {
        border-color: var(--border-color);
        color: var(--body-color);
    }
    body.dark-mode .real-radio-btn:checked + .custom-radio-label .custom-radio-look {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
    }
    body.dark-mode .form-check-input.real-radio-btn:disabled + .custom-radio-label .custom-radio-look {
        background-color: var(--bs-tertiary-bg);
        border-color: var(--bs-border-color);
        color: var(--bs-text-muted-color);
    }
    body.dark-mode .form-check-input.real-radio-btn:checked:disabled + .custom-radio-label .custom-radio-look {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
        opacity:0.7;
    }
";

require_once 'includes/header.php'; 
?>

<?php if ($show_quiz_interface && $attempt_id && $total_questions > 0): ?>
    <div class="modal fade" id="quizWarningModal" tabindex="-1" aria-labelledby="quizWarningModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quizWarningModalLabel">গুরুত্বপূর্ণ নির্দেশাবলী</h5>
                </div>
                <div class="modal-body">
                    <p>অনুগ্রহ করে কুইজ শুরু করার আগে নিচের নিয়মাবলী ভালোভাবে পড়ে নিন:</p>
                    <ul>
                        <li>এই কুইজের জন্য আপনার হাতে মোট <strong><?php echo $quiz_info_for_display['duration_minutes']; ?> মিনিট</strong> সময় থাকবে।</li>
                        <li>প্রতিটি প্রশ্নের জন্য চারটি অপশন থাকবে, যার মধ্যে একটি সঠিক উত্তর।</li>
                        <li>একবার উত্তর নির্বাচন করার পর তা পরিবর্তন করা যাবে না।</li>
                        <li>কোনো প্রকার অসাধু উপায় (যেমন: অন্যের সাহায্য নেওয়া, ইন্টারনেট সার্চ করা, কপি-পেস্ট করা) অবলম্বন করলে সাক্ষী হিসেবে আল্লাহ তায়ালাই যথেষ্ট।</li>
                        <li>সময় শেষ হওয়ার সাথে সাথে আপনার পরীক্ষা স্বয়ংক্রিয়ভাবে সাবমিট হয়ে যাবে।</li>
                         <li>প্রতি ভুল উত্তরের জন্য ০.২০ নম্বর কাটা যাবে।</li>
                    </ul>
                    <p class="text-danger fw-bold">আপনি কি উপরের সকল নিয়মের সাথে একমত এবং কুইজ শুরু করতে প্রস্তুত?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='quizzes.php';">সম্মত নই (ফিরে যান)</button>
                    <button type="button" class="btn btn-primary" id="agreeAndStartQuiz" data-bs-dismiss="modal">সম্মত ও শুরু করুন</button>
                </div>
            </div>
        </div>
    </div>

    <div class="timer-progress-bar py-2 px-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <div id="timer" class="fs-5 fw-bold">সময়: --:--</div>
            <div id="progress_indicator" class="fs-5">উত্তর: 0/<?php echo $total_questions; ?></div>
        </div>
    </div>

    <div class="container" id="quizContainer">
        <h2 class="mb-4 text-center"><?php echo escape_html($quiz_info_for_display['title']); ?></h2>
        <form id="quizForm" action="results.php" method="post">
            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
            <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
            <?php 
            $bengali_options_map = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ']; 
            foreach ($questions as $index => $question): 
            ?>
            <div class="card question-card mb-4 shadow-sm" id="question_<?php echo $question['id']; ?>" data-question-id="<?php echo $question['id']; ?>">
                <div class="card-header">
                    <h5 class="card-title mb-0">প্রশ্ন <?php echo $index + 1; ?>: <?php echo nl2br(escape_html($question['question_text'])); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($question['image_url'])): ?>
                        <div class="mb-3 text-center">
                            <img src="<?php echo $base_url . escape_html($question['image_url']); ?>" alt="প্রশ্ন সম্পর্কিত ছবি" class="img-fluid question-image">
                        </div>
                    <?php endif; ?>
                    <?php foreach ($question['options'] as $opt_index => $option): 
                        $option_char = isset($bengali_options_map[$opt_index]) ? $bengali_options_map[$opt_index] : '';
                    ?>
                    <div class="form-check question-option-wrapper custom-radio-option mb-2">
                        <input class="form-check-input real-radio-btn" type="radio"
                               name="answers[<?php echo $question['id']; ?>]"
                               id="option_<?php echo $option['id']; ?>"
                               value="<?php echo $option['id']; ?>"
                               data-question-id="<?php echo $question['id']; ?>">
                        <label class="form-check-label custom-radio-label w-100 p-2 rounded border" for="option_<?php echo $option['id']; ?>">
                            <span class="custom-radio-look" data-char="<?php echo $option_char; ?>"></span>
                            <span class="option-text"><?php echo escape_html($option['text']); ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-center mt-4">
                <button type="submit" name="submit_quiz" class="btn btn-primary btn-lg">সাবমিট করুন</button>
            </div><br>
        </form>
    </div>
<?php else: ?>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <center><h2 class="mb-0"><?php echo escape_html($quiz_info_for_display['title']); ?></h2></center>
            </div>
            <div class="card-body">
                <h5 class="card-subtitle mb-2 text-muted">কুইজের বিবরণ</h5>
                <div class="quiz-description-display mb-3">
                    <?php echo $quiz_info_for_display['description'] ? $quiz_info_for_display['description'] : '<p>এই কুইজের জন্য কোনো বিস্তারিত বিবরণ দেওয়া হয়নি।</p>'; ?>
                </div>
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item"><strong>কুইজের সময়:</strong> <?php echo $quiz_info_for_display['duration_minutes']; ?> মিনিট</li>
                    <li class="list-group-item"><strong>মোট প্রশ্ন:</strong> <?php echo $quiz_info_for_display['question_count']; ?> টি</li>
                    <?php
                    $status_display_text = ''; $status_text_class = 'text-muted'; $current_datetime_for_status_check = new DateTime();
                    if ($quiz_info_for_display['status'] == 'live') {
                        $is_truly_live_for_display = true;
                        if ($quiz_info_for_display['live_start_datetime'] !== null) {
                            try {
                                $live_start_dt_check = new DateTime($quiz_info_for_display['live_start_datetime']);
                                if ($current_datetime_for_status_check < $live_start_dt_check) {
                                    $is_truly_live_for_display = false;
                                    $status_display_text = 'আপকামিং (শুরু হবে: ' . format_datetime($quiz_info_for_display['live_start_datetime']) . ')';
                                    $status_text_class = 'text-info';
                                }
                            } catch (Exception $e) { $is_truly_live_for_display = false; $status_display_text = 'ত্রুটিপূর্ণ শুরুর তারিখ'; $status_text_class = 'text-danger';}
                        }
                        if ($is_truly_live_for_display && $quiz_info_for_display['live_end_datetime'] !== null) {
                           try {
                                $live_end_dt_check = new DateTime($quiz_info_for_display['live_end_datetime']);
                                if ($current_datetime_for_status_check > $live_end_dt_check) {
                                    $is_truly_live_for_display = false;
                                    $status_display_text = 'শেষ হয়েছে';
                                    $status_text_class = 'text-secondary';
                                }
                            } catch (Exception $e) { $is_truly_live_for_display = false; $status_display_text = 'ত্রুটিপূর্ণ শেষের তারিখ'; $status_text_class = 'text-danger';}
                        }
                        if ($is_truly_live_for_display) { $status_display_text = 'লাইভ'; $status_text_class = 'text-success fw-bold'; }
                    } elseif ($quiz_info_for_display['status'] == 'upcoming') {
                        $status_display_text = 'আপকামিং'; $status_text_class = 'text-info';
                        if ($quiz_info_for_display['live_start_datetime']) { $status_display_text .= ' (শুরু: ' . format_datetime($quiz_info_for_display['live_start_datetime']) . ')';}
                    } elseif ($quiz_info_for_display['status'] == 'archived') {
                        $status_display_text = 'আর্কাইভড';
                         if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && (!isset($user_role) || $user_role !== 'admin')) {
                             $status_display_text .= ' (অনুশীলনের জন্য উপলব্ধ)';
                         }
                        $status_text_class = 'text-secondary';
                    } elseif ($quiz_info_for_display['status'] == 'draft') {
                        $status_display_text = 'ড্রাফট (শীঘ্রই আসছে)'; $status_text_class = 'text-warning';
                    } else { $status_display_text = ucfirst($quiz_info_for_display['status']); }
                    ?>
                    <li class="list-group-item"><strong>স্ট্যাটাস:</strong> <span class="<?php echo $status_text_class; ?>"><?php echo $status_display_text; ?></span></li>
                </ul>
                <hr>
                <?php if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true): ?>
                    <p class="lead text-center">এই কুইজে অংশগ্রহণ করতে অনুগ্রহ করে লগইন করুন।</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz_id); ?>" class="btn btn-primary btn-lg px-4">লগইন করুন</a>
                    </div><br>
                     <p class="lead text-center">রেজিস্টার করা না থাকলে, উপরের <b>লগইন বাটনে</b> ক্লিক করে রেজিস্টেশন করুন!</p>
                <?php elseif (!empty($access_message)): ?>
                     <div class="alert alert-warning text-center"><?php echo $access_message; ?></div>
                <?php endif; ?>
                 <p class="text-center mt-3"><a href="<?php echo $base_url; ?>quizzes.php" class="btn btn-outline-secondary btn-sm">সকল কুইজ দেখুন</a></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quizForm = document.getElementById('quizForm'); 
    const warningModalElement = document.getElementById('quizWarningModal');
    const agreeAndStartButton = document.getElementById('agreeAndStartQuiz');
    const mainQuizContainer = document.getElementById('quizContainer');
    const timerProgressBar = document.querySelector('.timer-progress-bar');
    const totalQuestionsJS = <?php echo $total_questions; ?>; 

    let quizLogicInitialized = false;

    function applyBlurToBackground(shouldBlur) {
        if (mainQuizContainer) mainQuizContainer.classList.toggle('blur-background', shouldBlur);
        if (timerProgressBar) timerProgressBar.classList.toggle('blur-background', shouldBlur);
    }

    function initializeQuizFunctionalities() {
        if (quizLogicInitialized || !quizForm) return; 
        quizLogicInitialized = true;
        applyBlurToBackground(false);

        const bodyElement = document.body;
        bodyElement.classList.add('disable-text-selection');
        bodyElement.addEventListener('copy', function(e) { e.preventDefault(); });
        bodyElement.addEventListener('paste', function(e) { e.preventDefault(); });
        bodyElement.addEventListener('cut', function(e) { e.preventDefault(); });
        bodyElement.addEventListener('contextmenu', function(e) { e.preventDefault(); });

        const timerDisplay = document.getElementById('timer');
        const progressIndicator = document.getElementById('progress_indicator');
        const answeredQuestionLocks = new Set();
        let timeLeft = <?php echo $quiz_duration_seconds; ?>;
        var timerInterval;

        function updateTimerDisplay() {
            if (!timerDisplay) return;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.textContent = `সময়: ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            if (timeLeft <= 60 && timeLeft > 0) { timerDisplay.classList.add('critical'); }
            else if (timeLeft <= 0) {
                timerDisplay.classList.remove('critical');
                timerDisplay.textContent = "সময় শেষ!";
                if (quizForm && !quizForm.dataset.submitted) { 
                    quizForm.dataset.submitted = 'true'; 
                    quizForm.submit();
                }
                if(timerInterval) clearInterval(timerInterval);
            }
             if (timeLeft > 0) timeLeft--; else timeLeft = 0; 
        }
        
        if (totalQuestionsJS > 0 && timeLeft > 0) {
            updateTimerDisplay(); 
            timerInterval = setInterval(updateTimerDisplay, 1000);
        } else if (totalQuestionsJS > 0 && timeLeft <= 0) { 
             if(timerDisplay) timerDisplay.textContent = "সময়:সীমাহীন";
             if(progressIndicator) progressIndicator.textContent = `উত্তর: 0/${totalQuestionsJS}`;
        }
        else { 
            if(timerDisplay) timerDisplay.textContent = "কোনো প্রশ্ন নেই";
            if(progressIndicator) progressIndicator.textContent = "উত্তর: 0/0";
            const submitButton = quizForm ? quizForm.querySelector('button[type="submit"]') : null;
            if(submitButton) { submitButton.disabled = true; submitButton.style.display = 'none'; }
        }


        const questionCards = document.querySelectorAll('.question-card');
        questionCards.forEach(questionCard => {
            const questionId = questionCard.dataset.questionId;
            const radiosInThisGroup = questionCard.querySelectorAll(`.real-radio-btn`); // Target real radio

            radiosInThisGroup.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked && !answeredQuestionLocks.has(questionId)) {
                        // The CSS :checked selector will handle the visual change of the custom radio.
                        // The JS part for .selected-option-display on the main label (for border) can remain.
                        const allMainLabelsInQuestion = questionCard.querySelectorAll('.custom-radio-label');
                        allMainLabelsInQuestion.forEach(lbl => {
                            lbl.classList.remove('selected-option-display', 'border-primary', 'border-2');
                            // Opacity will be handled by :disabled CSS selector now
                        });

                        const parentWrapper = this.closest('.custom-radio-option');
                        if (parentWrapper) {
                            const mainLabelForRadio = parentWrapper.querySelector('.custom-radio-label');
                            if (mainLabelForRadio) {
                                mainLabelForRadio.classList.add('selected-option-display', 'border-primary', 'border-2');
                            }
                        }
                        
                        answeredQuestionLocks.add(questionId);
                        if(progressIndicator) progressIndicator.textContent = `উত্তর: ${answeredQuestionLocks.size}/${totalQuestionsJS}`;

                        radiosInThisGroup.forEach(otherRadioInGroup => {
                            if (otherRadioInGroup !== this) {
                                otherRadioInGroup.disabled = true;
                            }
                            // The cursor and opacity are handled by CSS based on :disabled
                        });
                    }
                });
            });
        });
        if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
    }

    if (warningModalElement && agreeAndStartButton && totalQuestionsJS > 0 && <?php echo json_encode($show_quiz_interface); ?>) {
        const warningModal = new bootstrap.Modal(warningModalElement);
        warningModal.show();
        applyBlurToBackground(true); 

        agreeAndStartButton.addEventListener('click', function() {
            initializeQuizFunctionalities(); 
        });

        warningModalElement.addEventListener('hidden.bs.modal', function (event) {
             applyBlurToBackground(false); 
            if (!quizLogicInitialized) {
                window.location.href = 'quizzes.php'; 
            }
        });
    } else if (totalQuestionsJS > 0 && <?php echo json_encode($show_quiz_interface); ?>) { 
        initializeQuizFunctionalities();
    } else if (<?php echo json_encode($show_quiz_interface); ?>) { 
         applyBlurToBackground(false); 
         const bodyElement = document.body;
         bodyElement.classList.add('disable-text-selection');
         bodyElement.addEventListener('contextmenu', function(e) { e.preventDefault(); });

         if (document.getElementById('timer')) document.getElementById('timer').textContent = "কোনো প্রশ্ন নেই";
         if (document.getElementById('progress_indicator')) document.getElementById('progress_indicator').textContent = "উত্তর: 0/0";
         const submitButton = quizForm ? quizForm.querySelector('button[type="submit"]') : null;
         if(submitButton) {
             submitButton.disabled = true;
             submitButton.textContent = "কোনো প্রশ্ন নেই";
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