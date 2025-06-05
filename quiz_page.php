<?php
// quiz_page.php
$page_title = ""; // $page_title কুইজের বিবরণ আনার পর সেট করা হবে
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$quiz_info_for_display = null;

// Flags to control page content display
$quiz_has_ended_for_logged_in_user = false;
$has_incomplete_attempt = false;
$incomplete_attempt_id = null;
$display_login_prompt_section = false;
$display_quiz_interface_section = false;
$display_general_unavailable_message = false;

// Variables for quiz interface if displayed
$questions = [];
$total_questions = 0;
$quiz_duration_seconds = 0;
$attempt_id = null;
$quiz_is_startable_for_modal = false;


if ($quiz_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ কুইজ ID।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: quizzes.php");
    exit;
}

// Fetch basic quiz details for display, regardless of login status
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

$page_specific_styles = "
    .blur-background {
        filter: blur(5px);
        transition: filter 0.3s ease-in-out;
    }
    #quizContainer.blur-background, .timer-progress-bar.blur-background {
        /* Specificity to ensure blur applies */
    }
    .modal-backdrop.show {
        /* backdrop-filter: blur(3px); */
        /* background-color: rgba(0, 0, 0, 0.3); */
    }
    .disable-text-selection {
        -webkit-user-select: none; /* Safari */
        -moz-user-select: none; /* Firefox */
        -ms-user-select: none; /* Internet Explorer/Edge */
        user-select: none; /* Standard syntax */
    }
     .question-image { max-width: 100%; height: auto; max-height: 350px; margin-bottom: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: block; margin-left: auto; margin-right: auto;}
    .timer-progress-bar {
        position: sticky;
        top: 0; /* Adjust if you have a fixed navbar above it */
        z-index: 1030; /* Ensure it's above other content but below modals/navbar if necessary */
        background-color: var(--timer-progress-bar-bg, var(--bs-light)); /* Fallback to bs-light */
        padding: 10px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-bottom: 1px solid var(--border-color, #dee2e6);
    }
    body.dark-mode .timer-progress-bar {
        box-shadow: 0 2px 4px rgba(255,255,255,0.1);
    }
    .timer-progress-bar #timer.critical {
        color: var(--danger-color, #dc3545);
        font-weight: bold;
    }
    .question-option-wrapper .form-check-label {
        cursor: pointer;
        transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
    }
    .question-option-wrapper .form-check-label:hover {
        background-color: var(--question-option-hover-bg, #f5f5f5);
    }
    .question-option-wrapper label.selected-option-display {
        background-color: var(--primary-color, #0d6efd) !important;
        border-color: var(--primary-color, #0d6efd) !important;
        color: #fff !important;
        font-weight: bold;
    }
";

require_once 'includes/header.php'; // Header included once

// Determine page content based on login status and quiz status
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $display_login_prompt_section = true;
} else {
    // User IS logged in.
    $user_id = $_SESSION['user_id'];
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

    if ($user_role !== 'admin') { // Admin check can be more granular if needed
        $sql_check_existing_attempt = "SELECT id, score, end_time FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? LIMIT 1";
        $stmt_check_existing_attempt = $conn->prepare($sql_check_existing_attempt);

        if (!$stmt_check_existing_attempt) {
            error_log("Prepare failed for checking existing attempt: (" . $conn->errno . ") " . $conn->error);
            $_SESSION['flash_message'] = "একটি অপ্রত্যাশিত সমস্যা হয়েছে। (চেক অ্যাটেম্পট)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $stmt_check_existing_attempt->bind_param("ii", $user_id, $quiz_id);
        if (!$stmt_check_existing_attempt->execute()) {
            error_log("Execute failed for checking existing attempt: (" . $stmt_check_existing_attempt->errno . ") " . $stmt_check_existing_attempt->error);
            $stmt_check_existing_attempt->close();
            $_SESSION['flash_message'] = "একটি অপ্রত্যাশিত সমস্যা হয়েছে। (এক্সিকিউট অ্যাটেম্পট)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $result_existing_attempt = $stmt_check_existing_attempt->get_result();
        $existing_attempt_data = $result_existing_attempt->fetch_assoc();
        $stmt_check_existing_attempt->close();

        if ($existing_attempt_data) {
            if ($existing_attempt_data['score'] !== null) { // Quiz completed by user
                $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজটি সম্পন্ন করেছেন। নিচে আপনার আগের ফলাফল দেখানো হলো।";
                $_SESSION['flash_message_type'] = "info";
                header("Location: results.php?attempt_id=" . $existing_attempt_data['id'] . "&quiz_id=" . $quiz_id);
                exit;
            } else { // Quiz started but not completed (score is null)
                $has_incomplete_attempt = true;
                $incomplete_attempt_id = $existing_attempt_data['id'];
            }
        }
    }

    if (!$has_incomplete_attempt) { // Only proceed if no incomplete attempt by non-admin
        $quiz = $quiz_info_for_display;

        if ($user_role !== 'admin') {
            $current_datetime = new DateTime();
            if ($quiz['status'] == 'live') {
                if ($quiz['live_start_datetime'] !== null) {
                    $live_start_dt = new DateTime($quiz['live_start_datetime']);
                    if ($current_datetime < $live_start_dt) {
                        $_SESSION['flash_message'] = "এই কুইজটি এখনও শুরু হয়নি। শুরু হওয়ার সময়: " . format_datetime($quiz['live_start_datetime']);
                        $_SESSION['flash_message_type'] = "warning";
                        header("Location: quizzes.php");
                        exit;
                    }
                }
                if ($quiz['live_end_datetime'] !== null) {
                    $live_end_dt = new DateTime($quiz['live_end_datetime']);
                    if ($current_datetime > $live_end_dt) {
                        $quiz_has_ended_for_logged_in_user = true;
                    }
                }
            } elseif ($quiz['status'] == 'draft') {
                 $_SESSION['flash_message'] = "এই কুইজটি এখন অংশগ্রহণের জন্য উপলব্ধ নয় (ড্রাফট)।";
                 $_SESSION['flash_message_type'] = "warning";
                 header("Location: quizzes.php");
                 exit;
            } elseif ($quiz['status'] == 'upcoming') {
                $_SESSION['flash_message'] = "এই কুইজটি এখনও শুরু হয়নি (আপকামিং)।";
                if ($quiz['live_start_datetime']) {
                     $_SESSION['flash_message'] .= " সম্ভাব্য শুরু: " . format_datetime($quiz['live_start_datetime']);
                }
                $_SESSION['flash_message_type'] = "info";
                header("Location: quizzes.php");
                exit;
            }
        }

        if ($quiz_has_ended_for_logged_in_user) {
            // Message will be shown in HTML rendering part
        } else {
            // Quiz is startable, load questions and create attempt record
            $sql_questions = "SELECT id, question_text, image_url FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC";
            $stmt_questions = $conn->prepare($sql_questions);
            $stmt_questions->bind_param("i", $quiz_id);
            $stmt_questions->execute();
            $result_questions = $stmt_questions->get_result();
            while ($q_row = $result_questions->fetch_assoc()) {
                $options_for_q = [];
                $sql_options = "SELECT id, option_text FROM options WHERE question_id = ?";
                $stmt_options = $conn->prepare($sql_options);
                $stmt_options->bind_param("i", $q_row['id']);
                $stmt_options->execute();
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

            if ($total_questions === 0 && $user_role !== 'admin' && $quiz['status'] !== 'archived') {
                $_SESSION['flash_message'] = "দুঃখিত, এই কুইজে এখনো কোনো প্রশ্ন যোগ করা হয়নি।";
                $_SESSION['flash_message_type'] = "warning";
                header("Location: quizzes.php");
                exit;
            }
            
            $quiz_duration_seconds = $quiz['duration_minutes'] * 60;
            $quiz_is_startable_for_modal = $total_questions > 0 && ($quiz['status'] === 'live' || $quiz['status'] === 'archived' || $user_role === 'admin');
            
            // Only create attempt if quiz is valid and questions exist (or admin)
            if ($quiz_is_startable_for_modal || ($total_questions === 0 && $user_role === 'admin' && $quiz['status'] !== 'archived')) {
                $start_time = date('Y-m-d H:i:s');
                $user_ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent_string = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
                $parsed_ua = parse_user_agent_simple($user_agent_string);
                $browser_name = $parsed_ua['browser'];
                $os_platform = $parsed_ua['os'];

                $sql_start_attempt = "INSERT INTO quiz_attempts (user_id, quiz_id, start_time, ip_address, user_agent, browser_name, os_platform) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_start_attempt = $conn->prepare($sql_start_attempt);

                if (!$stmt_start_attempt) {
                    error_log("Prepare failed for starting attempt: (" . $conn->errno . ") " . $conn->error);
                    $_SESSION['flash_message'] = "কুইজ শুরু করতে একটি অপ্রত্যাশিত সমস্যা হয়েছে।";
                    $_SESSION['flash_message_type'] = "danger";
                    header("Location: quizzes.php");
                    exit;
                }
                $stmt_start_attempt->bind_param("iisssss", $user_id, $quiz_id, $start_time, $user_ip_address, $user_agent_string, $browser_name, $os_platform);
                if ($stmt_start_attempt->execute()) {
                    $attempt_id = $stmt_start_attempt->insert_id;
                    $display_quiz_interface_section = true;
                } else {
                    error_log("Execute failed for starting attempt: (" . $stmt_start_attempt->errno . ") " . $stmt_start_attempt->error);
                    $stmt_start_attempt->close();
                    $_SESSION['flash_message'] = "কুইজ শুরু করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন। (ত্রুটি কোড: DB_START_FAIL)";
                    $_SESSION['flash_message_type'] = "danger";
                    header("Location: quizzes.php");
                    exit;
                }
                $stmt_start_attempt->close();
            } else {
                $display_general_unavailable_message = true;
            }
        }
    }
}

?>

<?php if ($display_login_prompt_section): ?>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <center><h2 class="mb-0"><?php echo escape_html($quiz_info_for_display['title']); ?></h2> </center>
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
                    $status_display_text = '';
                    $status_text_class = 'text-muted';
                    $current_datetime_for_status_check = new DateTime();

                    if ($quiz_info_for_display['status'] == 'live') {
                        $is_truly_live_for_display = true;
                        if ($quiz_info_for_display['live_start_datetime'] !== null) {
                            $live_start_dt_check = new DateTime($quiz_info_for_display['live_start_datetime']);
                            if ($current_datetime_for_status_check < $live_start_dt_check) {
                                $is_truly_live_for_display = false;
                                $status_display_text = 'আপকামিং (শুরু হবে: ' . format_datetime($quiz_info_for_display['live_start_datetime']) . ')';
                                $status_text_class = 'text-info';
                            }
                        }
                        if ($is_truly_live_for_display && $quiz_info_for_display['live_end_datetime'] !== null) {
                            $live_end_dt_check = new DateTime($quiz_info_for_display['live_end_datetime']);
                            if ($current_datetime_for_status_check > $live_end_dt_check) {
                                $is_truly_live_for_display = false;
                                $status_display_text = 'শেষ হয়েছে';
                                $status_text_class = 'text-secondary';
                            }
                        }
                        if ($is_truly_live_for_display) {
                            $status_display_text = 'লাইভ';
                            $status_text_class = 'text-success fw-bold';
                        }
                    } elseif ($quiz_info_for_display['status'] == 'upcoming') {
                        $status_display_text = 'আপকামিং';
                        $status_text_class = 'text-info';
                        if ($quiz_info_for_display['live_start_datetime']) {
                             $status_display_text .= ' (শুরু: ' . format_datetime($quiz_info_for_display['live_start_datetime']) . ')';
                        }
                    } elseif ($quiz_info_for_display['status'] == 'archived') {
                        $status_display_text = 'আর্কাইভড (অনুশীলনের জন্য উপলব্ধ)';
                        $status_text_class = 'text-secondary';
                    } elseif ($quiz_info_for_display['status'] == 'draft') {
                        $status_display_text = 'ড্রাফট (শীঘ্রই আসছে)';
                        $status_text_class = 'text-warning';
                    } else {
                        $status_display_text = ucfirst($quiz_info_for_display['status']);
                    }
                    ?>
                    <li class="list-group-item"><strong>স্ট্যাটাস:</strong> <span class="<?php echo $status_text_class; ?>"><?php echo $status_display_text; ?></span></li>
                </ul>
                <hr>
                <p class="lead text-center">এই কুইজে অংশগ্রহণ করতে অনুগ্রহ করে লগইন করুন।</p>
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz_id); ?>" class="btn btn-primary btn-lg px-4">লগইন করুন</a>
                </div><br>
                 <p class="lead text-center">রেজিস্টার করা না থাকলে, উপরের <b>লগইন বাটনে</b> ক্লিক করে রেজিস্টেশন করুন!</p>
                 <p class="text-center mt-3"><a href="<?php echo $base_url; ?>quizzes.php" class="btn btn-outline-secondary btn-sm">সকল কুইজ দেখুন</a></p>
            </div>
        </div>
    </div>
<?php elseif ($has_incomplete_attempt): ?>
    <div class="container mt-5">
        <div class="alert alert-warning text-center">
            আপনি ইতিমধ্যে এই কুইজে একবার প্রবেশ করেছিলেন কিন্তু সম্পন্ন করেননি। আপনি এই কুইজটি নতুন করে শুরু করতে পারবেন না।
            <p class="mt-2">আপনার অসমাপ্ত চেষ্টার ফলাফল দেখতে <a href="results.php?attempt_id=<?php echo $incomplete_attempt_id; ?>&quiz_id=<?php echo $quiz_id; ?>" class="btn btn-info btn-sm">এখানে ক্লিক করুন</a> অথবা <a href="quizzes.php" class="btn btn-secondary btn-sm">অন্যান্য কুইজ</a> চেষ্টা করুন।</p>
        </div>
    </div>
<?php elseif ($quiz_has_ended_for_logged_in_user): ?>
    <div class="container mt-5">
        <div class="alert alert-info text-center">
            এই লাইভ কুইজটি ইতিমধ্যে শেষ হয়ে গিয়েছে। আপনি আর অংশগ্রহণ করতে পারবেন না।
            <p class="mt-2"><a href="quizzes.php" class="btn btn-primary">অন্যান্য কুইজ দেখুন</a></p>
        </div>
    </div>
<?php elseif ($display_quiz_interface_section && $attempt_id !== null): ?>
    <?php if ($quiz_is_startable_for_modal): ?>
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
    <?php endif; ?>

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
            <?php if (empty($questions)): ?>
                <div class="alert alert-warning text-center">এই কুইজে এখনো কোনো প্রশ্ন যোগ করা হয়নি।</div>
            <?php else: ?>
                <?php foreach ($questions as $index => $question): ?>
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

                        <?php if (empty($question['options'])): ?>
                            <p>এই প্রশ্নের জন্য কোনো অপশন পাওয়া যায়নি।</p>
                        <?php else: ?>
                            <?php foreach ($question['options'] as $opt_index => $option): ?>
                            <div class="form-check question-option-wrapper mb-2">
                                <input class="form-check-input question-option-radio" type="radio"
                                       name="answers[<?php echo $question['id']; ?>]"
                                       id="option_<?php echo $option['id']; ?>"
                                       value="<?php echo $option['id']; ?>"
                                       data-question-id="<?php echo $question['id']; ?>">
                                <label class="form-check-label w-100 p-2 rounded border" for="option_<?php echo $option['id']; ?>">
                                    <?php echo escape_html($option['text']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="text-center mt-4">
                    <button type="submit" name="submit_quiz" class="btn btn-primary btn-lg">সাবমিট করুন</button>
                </div><br>
            <?php endif; ?>
        </form>
    </div>
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

            if (totalQuestionsJS > 0) {
                updateTimerDisplay();
                timerInterval = setInterval(updateTimerDisplay, 1000);
            } else {
                if(timerDisplay) timerDisplay.textContent = "কোনো প্রশ্ন নেই";
                if(progressIndicator) progressIndicator.textContent = "উত্তর: 0/0";
                const submitButton = quizForm ? quizForm.querySelector('button[type="submit"]') : null;
                if(submitButton) { submitButton.disabled = true; submitButton.style.display = 'none'; }
            }

            const questionCards = document.querySelectorAll('.question-card');
            questionCards.forEach(questionCard => {
                const questionId = questionCard.dataset.questionId;
                const radiosInThisGroup = questionCard.querySelectorAll(`.question-option-radio`);
                radiosInThisGroup.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.checked && !answeredQuestionLocks.has(questionId)) {
                            const allLabelsInQuestion = questionCard.querySelectorAll('.question-option-wrapper label');
                            allLabelsInQuestion.forEach(lbl => {
                                lbl.classList.remove('selected-option-display', 'border-primary', 'border-2');
                                lbl.style.opacity = '1';
                            });
                            radiosInThisGroup.forEach(r => {
                                const parentWrapper = r.closest('.question-option-wrapper');
                                if (parentWrapper) {
                                    const labelForRadio = parentWrapper.querySelector('label');
                                    if (labelForRadio) {
                                        if (r.checked) {
                                            labelForRadio.classList.add('selected-option-display', 'border-primary', 'border-2');
                                            labelForRadio.style.opacity = '1';
                                        } else {
                                            labelForRadio.style.opacity = '0.6';
                                        }
                                    }
                                }
                            });
                            answeredQuestionLocks.add(questionId);
                            if(progressIndicator) progressIndicator.textContent = `উত্তর: ${answeredQuestionLocks.size}/${totalQuestionsJS}`;
                            radiosInThisGroup.forEach(otherRadioInGroup => {
                                const otherLabel = otherRadioInGroup.closest('.question-option-wrapper').querySelector('label');
                                if (otherRadioInGroup !== this) {
                                    otherRadioInGroup.disabled = true;
                                    if(otherLabel) otherLabel.style.cursor = 'default';
                                } else {
                                    if(otherLabel) otherLabel.style.cursor = 'default';
                                }
                            });
                        }
                    });
                });
            });
            if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
        }

        const quizShouldShowModal = <?php echo ($quiz_is_startable_for_modal) ? 'true' : 'false'; ?>;

        if (warningModalElement && agreeAndStartButton && quizShouldShowModal) {
            const warningModal = new bootstrap.Modal(warningModalElement);
            warningModal.show();
            applyBlurToBackground(true);
            agreeAndStartButton.addEventListener('click', function() { initializeQuizFunctionalities(); });
            warningModalElement.addEventListener('hidden.bs.modal', function () {
                applyBlurToBackground(false);
                // Optionally handle if modal is closed without starting
            });
        } else if (quizForm && totalQuestionsJS > 0) { // If no modal but quiz is ready
             initializeQuizFunctionalities();
        } else if (quizForm) { // If form exists but no questions (e.g., admin view of empty quiz)
             applyBlurToBackground(false);
             const bodyElement = document.body;
             bodyElement.classList.add('disable-text-selection');
             bodyElement.addEventListener('contextmenu', function(e) { e.preventDefault(); });
             if (document.getElementById('timer')) document.getElementById('timer').textContent = "কোনো প্রশ্ন নেই";
             if (document.getElementById('progress_indicator')) document.getElementById('progress_indicator').textContent = "উত্তর: 0/0";
             const submitButton = quizForm.querySelector('button[type="submit"]');
             if(submitButton) submitButton.style.display = 'none';
        }
    });
    </script>

<?php elseif ($display_general_unavailable_message): ?>
    <div class="container mt-5">
        <div class="alert alert-warning text-center">
            কুইজটি এই মুহূর্তে উপলব্ধ নয় অথবা কোনো প্রশ্ন পাওয়া যায়নি। অনুগ্রহ করে <a href="quizzes.php">সকল কুইজ</a> পেইজ দেখুন।
        </div>
    </div>
<?php endif; ?>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>