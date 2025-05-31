<?php
// $page_title is set after fetching quiz details
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // For escape_html or other functions if needed

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $quiz_id_redirect = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $redirect_target = 'quizzes.php'; // Default redirect if quiz ID is not present
    if ($quiz_id_redirect > 0) {
        $redirect_target = 'quiz_page.php?id=' . $quiz_id_redirect;
    }
    // Store the intended URL in a session variable
    $_SESSION['redirect_url_user'] = $redirect_target;
    header("location: login.php"); // Redirect to login page
    exit;
}

$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($quiz_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ কুইজ ID।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: quizzes.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user'; // Get user role

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
        // If attempt exists, redirect to results page with appropriate message
        if ($existing_attempt_data['score'] === null && $existing_attempt_data['end_time'] === null) {
            // Incomplete attempt (e.g., browser closed, timer ran out before explicit submit but after attempt record created)
            // The results.php page will handle displaying whatever was submitted or calculating score if time ran out implicitly
            $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজে একবার প্রবেশ করেছিলেন। আপনার চেষ্টার ফলাফল দেখানো হচ্ছে।";
            $_SESSION['flash_message_type'] = "warning";
        } elseif ($existing_attempt_data['score'] !== null) { 
            // Completed attempt
            $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজটি সম্পন্ন করেছেন। নিচে আপনার আগের ফলাফল দেখানো হলো।";
            $_SESSION['flash_message_type'] = "info";
        }
        header("Location: results.php?attempt_id=" . $existing_attempt_data['id'] . "&quiz_id=" . $quiz_id);
        exit;
    }
}

$quiz = null;
// Fetch quiz details (title, duration, status, live times)
$sql_quiz = "SELECT id, title, duration_minutes, status, live_start_datetime, live_end_datetime FROM quizzes WHERE id = ?";
if ($user_role !== 'admin') {
    $sql_quiz .= " AND (status = 'live' OR status = 'archived')"; // Non-admins can only access live or archived
}

$stmt_quiz = $conn->prepare($sql_quiz);
$stmt_quiz->bind_param("i", $quiz_id);
$stmt_quiz->execute();
$result_quiz = $stmt_quiz->get_result();

if ($result_quiz->num_rows === 1) {
    $quiz = $result_quiz->fetch_assoc();
    $page_title = escape_html($quiz['title']) . " - কুইজ পরীক্ষা"; 

    // Status and time checks for NON-ADMIN users
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
                    header("Location: quizzes.php"); // Or redirect to results page if an attempt was made but time passed
                    exit;
                }
            }
        } elseif ($quiz['status'] == 'draft') { // Non-admin should not reach here due to SQL filter, but as a safeguard
             $_SESSION['flash_message'] = "এই কুইজটি এখন অংশগ্রহণের জন্য উপলব্ধ নয় (ড্রাফট)।";
             $_SESSION['flash_message_type'] = "warning";
             header("Location: quizzes.php");
             exit;
        }
        // 'archived' quizzes are allowed for a first attempt by non-admins (if SQL filter allows).
        // The existing attempt check above handles re-attempts for archived quizzes.
    }

} else {
    $stmt_quiz->close();
    $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id}) খুঁজে পাওয়া যায়নি অথবা এটি এখন অংশগ্রহণের জন্য উপলব্ধ নয়।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: quizzes.php");
    exit;
}
$stmt_quiz->close();

// Fetch questions and options (including image_url and question_info_text)
$questions = [];
// ডেটাবেস থেকে question_info_text কলামটিও সিলেক্ট করা হচ্ছে
$sql_questions = "SELECT id, question_text, question_info_text, image_url FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC"; // [MODIFIED]
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

// Start a new quiz attempt
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
    // Check for duplicate entry error if user somehow bypassed the earlier attempt check (e.g. race condition)
    // This might indicate an issue with unique constraints or logic flow if it happens frequently.
    if ($conn->errno == 1062) { // 1062 is MySQL error code for duplicate entry
         $_SESSION['flash_message'] = "আপনি ইতিমধ্যে এই কুইজে অংশগ্রহণ করছেন অথবা করেছেন।";
         $_SESSION['flash_message_type'] = "warning";
         // Try to find the existing attempt ID to redirect
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

// Include HTML header
require_once 'includes/header.php';
?>
<style>
.question-image {
    max-width: 100%;
    height: auto;
    max-height: 350px; /* You can adjust this */
    margin-bottom: 15px; 
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    display: block; 
    margin-left: auto;
    margin-right: auto;
}
/* প্রশ্নপত্রের তথ্যের জন্য স্টাইল */
.question-info-text {
    background-color: #f0f8ff; /* AliceBlue, or choose another light, distinct color */
    border-left: 4px solid #17a2b8; /* Bootstrap info color for border */
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
    font-size: 0.95em; /* Adjust as needed */
    line-height: 1.6; /* For better readability */
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
                    <?php if (!empty($question['question_info_text'])): ?>
                        <div class="question-info-text">
                            <?php echo nl2br(escape_html($question['question_info_text'])); // [MODIFIED] Displaying info text ?>
                        </div>
                    <?php endif; ?>
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

    if (totalQuestions > 0) { // Only start timer if there are questions
        updateTimerDisplay(); 
        var timerInterval = setInterval(updateTimerDisplay, 1000);
    } else {
        timerDisplay.textContent = "কোনো প্রশ্ন নেই";
        progressIndicator.textContent = "উত্তর: 0/0";
        const submitButton = quizForm.querySelector('button[type="submit"]');
        if(submitButton) submitButton.disabled = true; // Disable submit if no questions
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
                    progressIndicator.textContent = `উত্তর: ${answeredQuestionLocks.size}/${totalQuestions}`;

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
                } else if (this.checked && answeredQuestionLocks.has(questionId)) {
                    // This case should ideally not be reached if the click prevention on other radios works.
                }
            });
        });
    });

    // Prevent form resubmission on page refresh/back
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});
</script>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>