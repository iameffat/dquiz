<?php
// quiz_page.php
$page_title = ""; // $page_title কুইজের বিবরণ আনার পর সেট করা হবে
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$quiz_info_for_display = null;

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
        $page_title = escape_html($quiz_info_for_display['title']) . " - কুইজ"; // এখানে $page_title সেট করা হচ্ছে
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
";

require_once 'includes/header.php'; // header.php এখানে include করা হচ্ছে

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // User is NOT logged in. Display quiz info and login prompt.
    ?>
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
    <?php
} else {
    // User IS logged in.
    $user_id = $_SESSION['user_id'];
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
    
    // Use the already fetched quiz info
    $quiz = $quiz_info_for_display;

    $quiz_can_be_taken = true; 
    $message_for_user_on_page = "";
    $message_type_for_user_on_page = ""; 

    if ($user_role !== 'admin') {
        // Check if user has already attempted this quiz
        $sql_check_existing_attempt = "SELECT id, score, end_time FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? LIMIT 1";
        $stmt_check_existing_attempt = $conn->prepare($sql_check_existing_attempt);

        if (!$stmt_check_existing_attempt) {
            error_log("Prepare failed for checking existing attempt: (" . $conn->errno . ") " . $conn->error);
            // Set a flash message and redirect or display error on page
            $_SESSION['flash_message'] = "একটি অপ্রত্যাশিত সমস্যা হয়েছে। অনুগ্রহ করে অ্যাডমিনের সাথে যোগাযোগ করুন। (Error: E001)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $stmt_check_existing_attempt->bind_param("ii", $user_id, $quiz_id);
        if (!$stmt_check_existing_attempt->execute()) {
            error_log("Execute failed for checking existing attempt: (" . $stmt_check_existing_attempt->errno . ") " . $stmt_check_existing_attempt->error);
            $stmt_check_existing_attempt->close();
            $_SESSION['flash_message'] = "একটি অপ্রত্যাশিত সমস্যা হয়েছে। অনুগ্রহ করে অ্যাডমিনের সাথে যোগাযোগ করুন। (Error: E002)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $result_existing_attempt = $stmt_check_existing_attempt->get_result();
        $existing_attempt_data = $result_existing_attempt->fetch_assoc();
        $stmt_check_existing_attempt->close();

        if ($existing_attempt_data) {
            // User has some record for this quiz
            if ($existing_attempt_data['score'] === null && $existing_attempt_data['end_time'] === null) {
                 // This might be an incomplete attempt. For simplicity, redirecting to results which might handle it or show 0.
                 // Or, you could allow resuming if you implement that logic.
                 $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজে একবার প্রবেশ করেছিলেন কিন্তু সম্পন্ন করেননি। আপনার অসমাপ্ত চেষ্টার ফলাফল দেখানো হচ্ছে।";
                 $_SESSION['flash_message_type'] = "warning";
            } elseif ($existing_attempt_data['score'] !== null) {
                $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজটি সম্পন্ন করেছেন। নিচে আপনার আগের ফলাফল দেখানো হলো।";
                $_SESSION['flash_message_type'] = "info";
            }
            header("Location: results.php?attempt_id=" . $existing_attempt_data['id'] . "&quiz_id=" . $quiz_id);
            exit;
        }

        // Quiz status checks
        $current_datetime_obj = new DateTime();
    
        if ($quiz['status'] == 'live') {
            if ($quiz['live_start_datetime'] !== null) {
                $live_start_dt_obj = new DateTime($quiz['live_start_datetime']);
                if ($current_datetime_obj < $live_start_dt_obj) {
                    $quiz_can_be_taken = false;
                    $message_for_user_on_page = "এই কুইজটি এখনও শুরু হয়নি। শুরু হওয়ার সম্ভাব্য সময়: " . format_datetime($quiz['live_start_datetime']);
                    $message_type_for_user_on_page = "warning";
                }
            }
            if ($quiz_can_be_taken && $quiz['live_end_datetime'] !== null) {
                $live_end_dt_obj = new DateTime($quiz['live_end_datetime']);
                if ($current_datetime_obj > $live_end_dt_obj) {
                    $quiz_can_be_taken = false;
                    $message_for_user_on_page = "দুঃখিত, এই কুইজটির সময়সীমা শেষ হয়ে গিয়েছে। আপনি আর এই কুইজে অংশগ্রহণ করতে পারবেন না।";
                    $message_type_for_user_on_page = "info";
                }
            }
        } elseif ($quiz['status'] == 'draft') {
            $quiz_can_be_taken = false;
            $message_for_user_on_page = "এই কুইজটি এখন অংশগ্রহণের জন্য উপলব্ধ নয় (ড্রাফট)।";
            $message_type_for_user_on_page = "warning";
        } elseif ($quiz['status'] == 'upcoming') {
            $quiz_can_be_taken = false;
            $message_for_user_on_page = "এই কুইজটি এখনও শুরু হয়নি (আপকামিং)।";
            if ($quiz['live_start_datetime']) {
                 $message_for_user_on_page .= " সম্ভাব্য শুরু: " . format_datetime($quiz['live_start_datetime']);
            }
            $message_type_for_user_on_page = "info";
        }
        // For 'archived' status, $quiz_can_be_taken remains true, allowing practice.
    }

    if (!$quiz_can_be_taken) {
        echo '<div class="container mt-5 pt-5">'; 
        echo '  <div class="card shadow-sm">';
        echo '    <div class="card-body text-center p-4">';
        echo '      <div class="alert alert-' . htmlspecialchars($message_type_for_user_on_page) . '" role="alert">';
        echo '        <h4 class="alert-heading">' . ($message_type_for_user_on_page === "info" ? "কুইজ সমাপ্ত" : ($message_type_for_user_on_page === "warning" ? "লক্ষ্য করুন" : "ত্রুটি")) . '</h4>';
        echo '        <p>' . $message_for_user_on_page . '</p>';
        echo '      </div>';
        echo '      <a href="quizzes.php" class="btn btn-primary mt-3">অন্যান্য কুইজ দেখুন</a>';
        echo '      <a href="index.php" class="btn btn-outline-secondary mt-3 ms-2">হোম পেইজে ফিরে যান</a>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
        
        require_once 'includes/footer.php';
        if ($conn) { $conn->close(); }
        exit;
    }

    // ---- If quiz can be taken, proceed to load questions and set up the quiz ----

    $questions = [];
    $sql_questions = "SELECT id, question_text, image_url FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC";
    $stmt_questions = $conn->prepare($sql_questions);
    // Error handling for prepare
    if (!$stmt_questions) {
        error_log("Prepare failed for fetching questions: (" . $conn->errno . ") " . $conn->error);
        $_SESSION['flash_message'] = "কুইজের প্রশ্ন আনতে সমস্যা হয়েছে। (Error: Q001)";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: quizzes.php");
        exit;
    }
    $stmt_questions->bind_param("i", $quiz_id);
    if (!$stmt_questions->execute()) {
        error_log("Execute failed for fetching questions: (" . $stmt_questions->errno . ") " . $stmt_questions->error);
        $stmt_questions->close();
        $_SESSION['flash_message'] = "কুইজের প্রশ্ন আনতে সমস্যা হয়েছে। (Error: Q002)";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: quizzes.php");
        exit;
    }
    $result_questions = $stmt_questions->get_result();
    while ($q_row = $result_questions->fetch_assoc()) {
        $options = [];
        $sql_options = "SELECT id, option_text FROM options WHERE question_id = ?";
        $stmt_options = $conn->prepare($sql_options);
         // Error handling for options prepare
        if (!$stmt_options) {
            error_log("Prepare failed for fetching options (q_id: {$q_row['id']}): (" . $conn->errno . ") " . $conn->error);
            // Potentially skip this question or handle error appropriately
            continue; 
        }
        $stmt_options->bind_param("i", $q_row['id']);
        if (!$stmt_options->execute()) {
            error_log("Execute failed for fetching options (q_id: {$q_row['id']}): (" . $stmt_options->errno . ") " . $stmt_options->error);
            $stmt_options->close();
            continue;
        }
        $result_options_data = $stmt_options->get_result();
        while ($opt_row = $result_options_data->fetch_assoc()) {
            $options[] = $opt_row;
        }
        $stmt_options->close();
        shuffle($options);
        $q_row['options'] = $options;
        $questions[] = $q_row;
    }
    $stmt_questions->close();

    $total_questions = count($questions);
    // For non-admin users, if quiz is not archived and has no questions, redirect.
    // Admins can view empty quizzes. Archived quizzes can be "taken" for practice even if empty (though UI will show no questions).
    if ($total_questions === 0 && $user_role !== 'admin' && $quiz['status'] !== 'archived') {
        $_SESSION['flash_message'] = "দুঃখিত, এই কুইজে এখনো কোনো প্রশ্ন যোগ করা হয়নি।";
        $_SESSION['flash_message_type'] = "warning";
        header("Location: quizzes.php");
        exit;
    }
    $quiz_duration_seconds = $quiz['duration_minutes'] * 60;

    $attempt_id = null;
    $start_time = date('Y-m-d H:i:s');
    $user_ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent_string = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
    $parsed_ua = parse_user_agent_simple($user_agent_string);
    $browser_name = $parsed_ua['browser'];
    $os_platform = $parsed_ua['os'];

    // Only insert attempt if it's not an admin just viewing/testing
    // Or if it's an archived quiz being taken for practice (score won't affect official ranking of live period)
    if ($user_role !== 'admin' || $quiz['status'] === 'archived') {
        $sql_start_attempt = "INSERT INTO quiz_attempts (user_id, quiz_id, start_time, ip_address, user_agent, browser_name, os_platform) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_start_attempt = $conn->prepare($sql_start_attempt);

        if (!$stmt_start_attempt) {
            error_log("Prepare failed for starting attempt: (" . $conn->errno . ") " . $conn->error);
            $_SESSION['flash_message'] = "কুইজ শুরু করতে একটি অপ্রত্যাশিত সমস্যা হয়েছে। (Error: A001)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $stmt_start_attempt->bind_param("iisssss", $user_id, $quiz_id, $start_time, $user_ip_address, $user_agent_string, $browser_name, $os_platform);
        if ($stmt_start_attempt->execute()) {
            $attempt_id = $stmt_start_attempt->insert_id;
        } else {
            error_log("Execute failed for starting attempt: (" . $stmt_start_attempt->errno . ") " . $stmt_start_attempt->error);
            $stmt_start_attempt->close();
            $_SESSION['flash_message'] = "কুইজ শুরু করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন। (Error: A002)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $stmt_start_attempt->close();
    } else {
        // For admin previewing a non-archived quiz, we don't create an attempt record
        $attempt_id = "admin_preview_" . time(); // Dummy attempt_id for form submission if needed
    }


    $quiz_is_startable_for_modal = $total_questions > 0 && ($quiz['status'] === 'live' || $quiz['status'] === 'archived' || $user_role === 'admin');
    if ($quiz_is_startable_for_modal && ($user_role !== 'admin' || $quiz['status'] === 'archived')) { // Show modal for users, or admin taking archived for practice
    ?>
        <div class="modal fade" id="quizWarningModal" tabindex="-1" aria-labelledby="quizWarningModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="quizWarningModalLabel">গুরুত্বপূর্ণ নির্দেশাবলী</h5>
                    </div>
                    <div class="modal-body">
                        <p>অনুগ্রহ করে কুইজ শুরু করার আগে নিচের নিয়মাবলী ভালোভাবে পড়ে নিন:</p>
                        <ul>
                            <li>এই কুইজের জন্য আপনার হাতে মোট <strong><?php echo $quiz['duration_minutes']; ?> মিনিট</strong> সময় থাকবে।</li>
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
    <?php
    }
    ?>
    <style>
    .question-image { max-width: 100%; height: auto; max-height: 350px; margin-bottom: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: block; margin-left: auto; margin-right: auto;}
    </style>
    <div class="timer-progress-bar py-2 px-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <div id="timer" class="fs-5 fw-bold">সময়: --:--</div>
            <div id="progress_indicator" class="fs-5">উত্তর: 0/<?php echo $total_questions; ?></div>
        </div>
    </div>

    <div class="container" id="quizContainer">
        <h2 class="mb-4 text-center"><?php echo escape_html($quiz['title']); ?></h2>

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
                                    <?php echo escape_html($option['option_text']); ?>
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
        const totalQuestionsJS = <?php echo isset($total_questions) ? $total_questions : 0; ?>;
        const userRoleJS = '<?php echo $user_role; ?>';
        const quizStatusJS = '<?php echo $quiz['status']; ?>';


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
            // Only apply restrictions if not admin or if admin is taking an archived quiz (practice mode)
            if (userRoleJS !== 'admin' || (userRoleJS === 'admin' && quizStatusJS === 'archived')) {
                bodyElement.classList.add('disable-text-selection');
                bodyElement.addEventListener('copy', function(e) { e.preventDefault(); });
                bodyElement.addEventListener('paste', function(e) { e.preventDefault(); });
                bodyElement.addEventListener('cut', function(e) { e.preventDefault(); });
                bodyElement.addEventListener('contextmenu', function(e) { e.preventDefault(); });
            }


            const timerDisplay = document.getElementById('timer');
            const progressIndicator = document.getElementById('progress_indicator');
            const answeredQuestionLocks = new Set();
            let timeLeft = <?php echo isset($quiz_duration_seconds) ? $quiz_duration_seconds : 0; ?>;
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
                updateTimerDisplay(); // Initial call to display time immediately
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
                                lbl.style.opacity = '1'; // Reset opacity for all
                            });

                            // Style the selected option's label
                            const parentWrapper = this.closest('.question-option-wrapper');
                            if (parentWrapper) {
                                const labelForRadio = parentWrapper.querySelector('label');
                                if (labelForRadio) {
                                    labelForRadio.classList.add('selected-option-display', 'border-primary', 'border-2');
                                    labelForRadio.style.opacity = '1';
                                }
                            }

                            // Dim other options' labels and disable their radios
                            radiosInThisGroup.forEach(otherRadioInGroup => {
                                const otherLabel = otherRadioInGroup.closest('.question-option-wrapper').querySelector('label');
                                if (otherRadioInGroup !== this) {
                                    otherRadioInGroup.disabled = true;
                                    if(otherLabel) {
                                        otherLabel.style.opacity = '0.6';
                                        otherLabel.style.cursor = 'default';
                                    }
                                } else {
                                     if(otherLabel) otherLabel.style.cursor = 'default'; // Keep selected one's cursor default too
                                }
                            });

                            answeredQuestionLocks.add(questionId);
                            if(progressIndicator) progressIndicator.textContent = `উত্তর: ${answeredQuestionLocks.size}/${totalQuestionsJS}`;
                        }
                    });
                });
            });
            if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
        }

        const quizShouldShowModal = <?php echo (isset($quiz_is_startable_for_modal) && $quiz_is_startable_for_modal && ($user_role !== 'admin' || $quiz['status'] === 'archived')) ? 'true' : 'false'; ?>;

        if (warningModalElement && agreeAndStartButton && quizShouldShowModal) {
            const warningModal = new bootstrap.Modal(warningModalElement);
            warningModal.show();
            applyBlurToBackground(true); // Blur when modal is shown

            agreeAndStartButton.addEventListener('click', function() {
                // Modal is dismissed by data-bs-dismiss, 'hidden.bs.modal' will handle unblur
                initializeQuizFunctionalities();
            });

            warningModalElement.addEventListener('hidden.bs.modal', function (event) {
                applyBlurToBackground(false); // Unblur when modal is hidden
                // If quiz was not started by clicking "Agree", and modal was closed (e.g. by ESC or backdrop click)
                if (!quizLogicInitialized && document.body.contains(warningModalElement)) {
                    // Redirect if they didn't agree, unless they are an admin just previewing
                    if (userRoleJS !== 'admin' || (userRoleJS === 'admin' && quizStatusJS === 'archived')) {
                         window.location.href = 'quizzes.php';
                    } else if (userRoleJS === 'admin' && quizStatusJS !== 'archived') {
                        // Admin previewing non-archived quiz, allow them to see it without modal restrictions
                        initializeQuizFunctionalities();
                    }
                }
            });
        } else {
            // If modal is not supposed to be shown (e.g., admin previewing live/upcoming quiz)
            // or if there are no questions to show for a normal user (already handled by redirect earlier)
            if (quizForm && totalQuestionsJS > 0) {
                 initializeQuizFunctionalities();
            } else if (quizForm) { // No questions but form exists
                 applyBlurToBackground(false);
                 const bodyElement = document.body;
                 bodyElement.classList.add('disable-text-selection');
                 bodyElement.addEventListener('contextmenu', function(e) { e.preventDefault(); });
                 if (document.getElementById('timer')) document.getElementById('timer').textContent = "কোনো প্রশ্ন নেই";
                 if (document.getElementById('progress_indicator')) document.getElementById('progress_indicator').textContent = "উত্তর: 0/0";
                 const submitButton = quizForm.querySelector('button[type="submit"]');
                 if(submitButton) submitButton.style.display = 'none'; // Hide submit button if no questions
            }
        }
    });
    </script>
    <?php
} // End of else block for logged-in user

if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>