<?php
// $page_title is set after fetching quiz details
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

require_once 'includes/header.php'; // Include header AFTER fetching quiz details and setting page_title

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
    // User IS logged in. Proceed with existing logic for quiz participation.
    $user_id = $_SESSION['user_id'];
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

    // Check if user has ANY existing attempt (completed or incomplete) for this quiz - FOR NON-ADMINS
    if ($user_role !== 'admin') {
        $sql_check_existing_attempt = "SELECT id, score, end_time FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? LIMIT 1";
        $stmt_check_existing_attempt = $conn->prepare($sql_check_existing_attempt);

        if (!$stmt_check_existing_attempt) {
            error_log("Prepare failed for checking existing attempt: (" . $conn->errno . ") " . $conn->error);
            $_SESSION['flash_message'] = "একটি অপ্রত্যাশিত সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন। (চেক অ্যাটেম্পট)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $stmt_check_existing_attempt->bind_param("ii", $user_id, $quiz_id);
        if (!$stmt_check_existing_attempt->execute()) {
            error_log("Execute failed for checking existing attempt: (" . $stmt_check_existing_attempt->errno . ") " . $stmt_check_existing_attempt->error);
            $stmt_check_existing_attempt->close();
            $_SESSION['flash_message'] = "একটি অপ্রত্যাশিত সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন। (এক্সিকিউট অ্যাটেম্পট)";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
        $result_existing_attempt = $stmt_check_existing_attempt->get_result();
        $existing_attempt_data = $result_existing_attempt->fetch_assoc();
        $stmt_check_existing_attempt->close();

        if ($existing_attempt_data) {
            if ($existing_attempt_data['score'] === null && $existing_attempt_data['end_time'] === null) {
                $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজে একবার প্রবেশ করেছিলেন। আপনার চেষ্টার ফলাফল দেখানো হচ্ছে।";
                $_SESSION['flash_message_type'] = "warning";
            } elseif ($existing_attempt_data['score'] !== null) {
                $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজটি সম্পন্ন করেছেন। নিচে আপনার আগের ফলাফল দেখানো হলো।";
                $_SESSION['flash_message_type'] = "info";
            }
            header("Location: results.php?attempt_id=" . $existing_attempt_data['id'] . "&quiz_id=" . $quiz_id);
            exit;
        }
    }

    $quiz = $quiz_info_for_display; // Use the already fetched details

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
                    $_SESSION['flash_message'] = "এই কুইজটি শেষ হয়ে গিয়েছে।";
                    $_SESSION['flash_message_type'] = "info";
                    header("Location: quizzes.php");
                    exit;
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

    $questions = [];
    $sql_questions = "SELECT id, question_text, image_url FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC";
    $stmt_questions = $conn->prepare($sql_questions);
    $stmt_questions->bind_param("i", $quiz_id);
    $stmt_questions->execute();
    $result_questions = $stmt_questions->get_result();
    while ($q_row = $result_questions->fetch_assoc()) {
        $options = [];
        $sql_options = "SELECT id, option_text FROM options WHERE question_id = ?";
        $stmt_options = $conn->prepare($sql_options);
        $stmt_options->bind_param("i", $q_row['id']);
        $stmt_options->execute();
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
    if ($total_questions === 0 && $user_role !== 'admin') {
        $_SESSION['flash_message'] = "দুঃখিত, এই কুইজে এখনো কোনো প্রশ্ন যোগ করা হয়নি।";
        $_SESSION['flash_message_type'] = "warning";
        header("Location: quizzes.php");
        exit;
    }
    $quiz_duration_seconds = $quiz['duration_minutes'] * 60;

    $attempt_id = null;
    $start_time = date('Y-m-d H:i:s');
    $sql_start_attempt = "INSERT INTO quiz_attempts (user_id, quiz_id, start_time) VALUES (?, ?, ?)";
    $stmt_start_attempt = $conn->prepare($sql_start_attempt);

    if (!$stmt_start_attempt) {
        error_log("Prepare failed for starting attempt: (" . $conn->errno . ") " . $conn->error);
        $_SESSION['flash_message'] = "কুইজ শুরু করতে একটি অপ্রত্যাশিত সমস্যা হয়েছে।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: quizzes.php");
        exit;
    }
    $stmt_start_attempt->bind_param("iis", $user_id, $quiz_id, $start_time);
    if ($stmt_start_attempt->execute()) {
        $attempt_id = $stmt_start_attempt->insert_id;
    } else {
        error_log("Execute failed for starting attempt: (" . $stmt_start_attempt->errno . ") " . $stmt_start_attempt->error);
        $stmt_start_attempt->close();
        if ($conn->errno == 1062) {
             $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজে অংশগ্রহণ করছেন অথবা করেছেন।";
             $_SESSION['flash_message_type'] = "warning";
             $sql_find_attempt = "SELECT id FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? ORDER BY id DESC LIMIT 1";
             $stmt_find = $conn->prepare($sql_find_attempt);
             $stmt_find->bind_param("ii", $user_id, $quiz_id);
             $stmt_find->execute();
             $res_find = $stmt_find->get_result();
             if($existing_att = $res_find->fetch_assoc()){
                header("Location: results.php?attempt_id=" . $existing_att['id'] . "&quiz_id=" . $quiz_id);
             } else {
                header("Location: quizzes.php");
             }
             $stmt_find->close();
             exit;
        } else {
            $_SESSION['flash_message'] = "কুইজ শুরু করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
            $_SESSION['flash_message_type'] = "danger";
            header("Location: quizzes.php");
            exit;
        }
    }
    $stmt_start_attempt->close();
    ?>
    <style>
    .question-image {
        max-width: 100%;
        height: auto;
        max-height: 350px;
        margin-bottom: 15px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        display: block;
        margin-left: auto;
        margin-right: auto;
    }
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
        if (!quizForm) return; // Exit if quizForm is not on the page (e.g., non-logged-in user view)

        const timerDisplay = document.getElementById('timer');
        const progressIndicator = document.getElementById('progress_indicator');
        const totalQuestions = <?php echo $total_questions; ?>;
        const answeredQuestionLocks = new Set();

        let timeLeft = <?php echo $quiz_duration_seconds; ?>;

         function updateTimerDisplay() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.textContent = `সময়: ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            if (timeLeft <= 60 && timeLeft > 0) {
                timerDisplay.classList.add('critical');
            } else if (timeLeft <= 0) {
                timerDisplay.classList.remove('critical');
                timerDisplay.textContent = "সময় শেষ!";
                if (quizForm && !quizForm.dataset.submitted) {
                    quizForm.dataset.submitted = 'true';
                    quizForm.submit();
                }
                clearInterval(timerInterval);
            }
            if (timeLeft > 0) timeLeft--; else timeLeft = 0;
        }

        if (totalQuestions > 0) {
            updateTimerDisplay();
            var timerInterval = setInterval(updateTimerDisplay, 1000);
        } else {
            timerDisplay.textContent = "কোনো প্রশ্ন নেই";
            if(progressIndicator) progressIndicator.textContent = "উত্তর: 0/0";
            const submitButton = quizForm.querySelector('button[type="submit"]');
            if(submitButton) submitButton.disabled = true;
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
                                parentWrapper.classList.add('answered');
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
                        if(progressIndicator) progressIndicator.textContent = `উত্তর: ${answeredQuestionLocks.size}/${totalQuestions}`;

                        radiosInThisGroup.forEach(otherRadioInGroup => {
                            if (otherRadioInGroup !== this) {
                                otherRadioInGroup.addEventListener('click', function preventClickOnOthers(event) {
                                    event.preventDefault();
                                });
                                const otherLabel = otherRadioInGroup.closest('.question-option-wrapper').querySelector('label');
                                if(otherLabel) otherLabel.style.cursor = 'default';
                            }
                        });
                         const selectedLabel = this.closest('.question-option-wrapper').querySelector('label');
                         if(selectedLabel) selectedLabel.style.cursor = 'default';
                    }
                });
            });
        });

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    });
    </script>
    <?php
} // End of else block for logged-in users

if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>