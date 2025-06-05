<?php
// practice_quiz.php
$page_title = "অনুশীলন কুইজ";
$base_url = '';
// Corrected include paths
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$category_id = isset($_REQUEST['category_id']) ? intval($_REQUEST['category_id']) : 0;
$category_name = "";
$questions = [];
$total_questions_in_category = 0;

// ডিফল্ট মান
$default_num_questions = 10;
$default_duration_minutes = 5; // ০ মানে কোনো সময়সীমা নেই

// প্রথমে POST থেকে মান নেওয়ার চেষ্টা করুন, না পেলে GET, সবশেষে ডিফল্ট
if (isset($_POST['num_questions'])) {
    $num_questions_to_show = intval($_POST['num_questions']);
} elseif (isset($_GET['num_questions'])) {
    $num_questions_to_show = intval($_GET['num_questions']);
} else {
    $num_questions_to_show = $default_num_questions;
}

if (isset($_POST['quiz_duration'])) {
    $quiz_duration_minutes = intval($_POST['quiz_duration']);
} elseif (isset($_GET['quiz_duration'])) {
    $quiz_duration_minutes = intval($_GET['quiz_duration']);
} else {
    $quiz_duration_minutes = $default_duration_minutes;
}

$quiz_duration_seconds = $quiz_duration_minutes * 60;

// ফর্ম সাবমিট হয়েছে কিনা অথবা URL এ প্যারামিটার আছে কিনা
$start_quiz = isset($_POST['start_practice_quiz_submit']) || (isset($_GET['num_questions']) && isset($_GET['quiz_duration']));


if ($category_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ ক্যাটাগরি ID।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: categories.php");
    exit;
}

// Fetch category details
$sql_cat = "SELECT name FROM categories WHERE id = ?";
$stmt_cat = $conn->prepare($sql_cat);
if ($stmt_cat) {
    $stmt_cat->bind_param("i", $category_id);
    $stmt_cat->execute();
    $result_cat = $stmt_cat->get_result();
    if ($result_cat && $cat_data = $result_cat->fetch_assoc()) {
        $category_name = $cat_data['name'];
        $page_title = "অনুশীলন: " . htmlspecialchars($category_name);
    } else {
        $_SESSION['flash_message'] = "ক্যাটাগরি (ID: {$category_id}) খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: categories.php");
        exit;
    }
    $stmt_cat->close();
} else {
    error_log("Practice Quiz: Failed to prepare category fetch statement: " . $conn->error);
    $_SESSION['flash_message'] = "ডাটাবেস সমস্যা: ক্যাটাগরির তথ্য আনতে সমস্যা হয়েছে।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: categories.php");
    exit;
}

// Fetch total number of questions in this category (considering both direct and junction table links)
$sql_q_count = "
    SELECT COUNT(DISTINCT q.id) as total_q
    FROM questions q
    LEFT JOIN question_categories qc ON q.id = qc.question_id
    WHERE (q.category_id = ? AND q.quiz_id IS NOT NULL) OR (qc.category_id = ? AND q.quiz_id IS NULL)
";
$stmt_q_count = $conn->prepare($sql_q_count);
if($stmt_q_count){
    $stmt_q_count->bind_param("ii", $category_id, $category_id);
    if ($stmt_q_count->execute()) {
        $result_q_count_obj = $stmt_q_count->get_result();
        if ($result_q_count_obj) {
            $total_questions_in_category_result = $result_q_count_obj->fetch_assoc();
            $total_questions_in_category = $total_questions_in_category_result ? (int)$total_questions_in_category_result['total_q'] : 0;
        } else {
            error_log("Practice Quiz: Failed to get result for question count: " . $stmt_q_count->error);
            $total_questions_in_category = 0;
        }
    } else {
        error_log("Practice Quiz: Failed to execute question count statement: " . $stmt_q_count->error);
        $total_questions_in_category = 0;
    }
    $stmt_q_count->close();
} else {
    error_log("Practice Quiz: Failed to prepare question count statement: " . $conn->error);
    $total_questions_in_category = 0;
}


if ($total_questions_in_category == 0 && $start_quiz) {
    $_SESSION['flash_message'] = "দুঃখিত, \"" . htmlspecialchars($category_name) . "\" ক্যাটাগরিতে অনুশীলনের জন্য কোনো প্রশ্ন এখনো যোগ করা হয়নি।";
    $_SESSION['flash_message_type'] = "info";
    header("Location: categories.php");
    exit;
}


if ($start_quiz) {
    if ($num_questions_to_show <= 0) {
        $num_questions_to_show = $default_num_questions; 
    }
    if ($num_questions_to_show > $total_questions_in_category && $total_questions_in_category > 0) {
        $num_questions_to_show = $total_questions_in_category;
    }
    
    $num_questions_to_load = $num_questions_to_show;

    $sql_questions = "
        (SELECT q.id, q.question_text, q.image_url, q.explanation
         FROM questions q
         INNER JOIN question_categories qc ON q.id = qc.question_id
         WHERE qc.category_id = ? AND q.quiz_id IS NULL) 
        UNION
        (SELECT q.id, q.question_text, q.image_url, q.explanation
         FROM questions q
         WHERE q.category_id = ? AND q.quiz_id IS NOT NULL)
        ORDER BY RAND()
        LIMIT ?
    ";
    $stmt_questions = $conn->prepare($sql_questions);

    if ($stmt_questions) {
        $stmt_questions->bind_param("iii", $category_id, $category_id, $num_questions_to_load);
        if ($stmt_questions->execute()) {
            $result_questions_obj = $stmt_questions->get_result();
            if ($result_questions_obj) {
                while ($q_row = $result_questions_obj->fetch_assoc()) {
                    $options = []; // Reset options for each question
                    $sql_options_q = "SELECT id, option_text, is_correct FROM options WHERE question_id = ?";
                    $stmt_options_inner = $conn->prepare($sql_options_q);
                    if ($stmt_options_inner) {
                        $stmt_options_inner->bind_param("i", $q_row['id']);
                        if ($stmt_options_inner->execute()) {
                            $result_options_data = $stmt_options_inner->get_result();
                            if ($result_options_data) {
                                while ($opt_row = $result_options_data->fetch_assoc()) {
                                    $options[] = $opt_row;
                                }
                            } else {
                                error_log("Practice Quiz: Failed to get result for options for q_id " . $q_row['id'] . ": " . $stmt_options_inner->error);
                            }
                        } else {
                             error_log("Practice Quiz: Failed to execute options fetch for q_id " . $q_row['id'] . ": " . $stmt_options_inner->error);
                        }
                        $stmt_options_inner->close();
                    } else {
                         error_log("Practice Quiz: Failed to prepare options fetch statement for q_id " . $q_row['id'] . ": " . $conn->error);
                    }
                    shuffle($options);
                    $q_row['options'] = $options;
                    $questions[] = $q_row;
                }
            } else {
                error_log("Practice Quiz: Failed to get result for questions fetch: " . $stmt_questions->error);
            }
        } else {
            error_log("Practice Quiz: Failed to execute questions fetch statement: " . $stmt_questions->error);
        }
        $stmt_questions->close();
    } else {
        error_log("Practice Quiz: Failed to prepare questions fetch statement: " . $conn->error);
        $_SESSION['flash_message'] = "প্রশ্ন আনতে ডাটাবেস সমস্যা হয়েছে।";
        $_SESSION['flash_message_type'] = "danger";
        // Redirect or display error, but avoid header if HTML already started
        // For simplicity, if header.php isn't called yet, this is okay:
        if (!headers_sent()) {
            header("Location: practice_quiz.php?category_id=" . $category_id);
            exit;
        }
    }
}

$total_questions_for_display = count($questions);

$page_specific_styles = "
    .question-image { max-width: 100%; height: auto; max-height: 350px; margin-bottom: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: block; margin-left: auto; margin-right: auto; border: 1px solid var(--border-color); padding: 3px; background-color: var(--body-bg); }
    body.dark-mode .question-image { box-shadow: 0 2px 5px rgba(255,255,255,0.05); border-color: var(--border-color); background-color: var(--body-bg); }
    
    .question-option-wrapper .form-check-label { cursor: pointer; transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out; }
    .question-option-wrapper .form-check-label:hover { background-color: var(--question-option-hover-bg); }
    .question-option-wrapper label.selected-option-display { background-color: var(--primary-color) !important; border-color: var(--primary-color) !important; color: #fff !important; font-weight: bold; }
    
    .practice-mode-notice { font-size: 0.9rem; padding: 0.75rem 1.25rem; margin-bottom: 1.5rem; border: 1px solid var(--bs-info-border-subtle); background-color: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); border-radius: var(--bs-border-radius); text-align: center; }
    body.dark-mode .practice-mode-notice { border-color: var(--bs-info-border-subtle); background-color: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); }

    .timer-display-container { position: sticky; top: 0; background-color: var(--body-bg); padding: 10px 0; z-index: 100; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    body.dark-mode .timer-display-container { background-color: var(--bs-dark-bg-subtle); box-shadow: 0 2px 4px rgba(255,255,255,0.05); }
    .timer-display { font-size: 1.2rem; font-weight: bold; }
    .timer-display.critical { color: var(--bs-danger); }
    .settings-card { max-width: 600px; margin: 2rem auto; }
";
// Corrected include path for header
require_once 'includes/header.php';
?>

<div class="container" id="quizInterfaceContainer">
    <h2 class="mb-3 mt-4 text-center">অনুশীলন কুইজ: <?php echo htmlspecialchars($category_name); ?></h2>

    <?php if (!$start_quiz): ?>
        <div class="card shadow-sm settings-card">
            <div class="card-header">
                <h5 class="mb-0">অনুশীলন সেটিংস</h5>
            </div>
            <div class="card-body">
                <form action="practice_quiz.php" method="post" id="practiceSettingsForm">
                    <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                    <div class="mb-3">
                        <label for="num_questions" class="form-label">কতটি প্রশ্ন দিয়ে অনুশীলন করতে চান?</label>
                        <input type="number" name="num_questions" id="num_questions" class="form-control" 
                               value="<?php echo $default_num_questions; ?>" 
                               min="1" 
                               max="<?php echo $total_questions_in_category > 0 ? $total_questions_in_category : $default_num_questions; ?>" 
                               required>
                        <small class="form-text text-muted">
                            এই ক্যাটাগরিতে মোট <?php echo $total_questions_in_category; ?> টি প্রশ্ন রয়েছে। 
                            আপনি ১ থেকে <?php echo $total_questions_in_category > 0 ? $total_questions_in_category : $default_num_questions; ?> এর মধ্যে যেকোনো সংখ্যা দিতে পারেন।
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="quiz_duration" class="form-label">সময়সীমা (মিনিট):</label>
                        <input type="number" name="quiz_duration" id="quiz_duration" class="form-control" 
                               value="<?php echo $default_duration_minutes; ?>" 
                               min="0">
                        <small class="form-text text-muted">০ দিলে কোনো সময়সীমা থাকবে না।</small>
                    </div>
                    <button type="submit" name="start_practice_quiz_submit" class="btn btn-primary w-100" <?php if ($total_questions_in_category == 0) echo 'disabled'; ?>>
                        অনুশীলন শুরু করুন
                    </button>
                    <?php if ($total_questions_in_category == 0): ?>
                        <p class="text-danger mt-2 text-center">এই ক্যাটাগরিতে কোনো প্রশ্ন না থাকায় অনুশীলন শুরু করা যাচ্ছে না।</p>
                    <?php endif; ?>
                </form>
                <div class="alert alert-warning mt-3" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16" role="img" aria-label="Warning:">
                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                    <strong>গুরুত্বপূর্ণ:</strong> সময় শেষ হওয়ার আগেই আপনার উত্তর সাবমিট করতে ভুলবেন না। সময় শেষ হয়ে গেলে আপনার উত্তর স্বয়ংক্রিয়ভাবে সাবমিট নাও হতে পারে এবং সেক্ষেত্রে আপনার ফলাফল সংরক্ষণ করা হবে না।
                </div>
                <div class="mt-3 practice-mode-notice">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill me-2" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16M8 4a.905.905 0 0 1 .9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995A.905.905 0 0 1 8 4m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                    </svg>
                    এটি একটি অনুশীলন কুইজ। আপনার উত্তর বা ফলাফল সার্ভারে জমা রাখা হবে না।
                </div>
            </div>
        </div>
    <?php else: ?>
        
        <?php if ($quiz_duration_seconds > 0): ?>
        <div class="text-center my-3 timer-display-container">
            <span id="timerDisplayPractice" class="timer-display">সময় বাকি: --:--:--</span>
        </div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <div class="alert alert-warning text-center">
                <?php if ($total_questions_in_category > 0): ?>
                    আপনি যে সংখ্যক প্রশ্ন নির্বাচন করেছেন (<?php echo $num_questions_to_show; ?>), সেই সংখ্যক প্রশ্ন এই ক্যাটাগরিতে পাওয়া যায়নি বা প্রশ্ন লোড করতে সমস্যা হয়েছে।
                <?php else: ?>
                    এই ক্যাটাগরিতে অনুশীলনের জন্য কোনো প্রশ্ন পাওয়া যায়নি।
                <?php endif; ?>
            </div>
             <div class="text-center mt-3">
                <a href="practice_quiz.php?category_id=<?php echo $category_id; ?>" class="btn btn-secondary">সেটিংস পরিবর্তন করুন</a>
                <a href="categories.php" class="btn btn-info">অন্য ক্যাটাগরি দেখুন</a>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">মোট প্রশ্ন: <?php echo $total_questions_for_display; ?>
                <?php if($quiz_duration_minutes > 0) echo " | সময়: " . $quiz_duration_minutes . " মিনিট"; ?>
            </p>
            <form id="practiceQuizForm" action="practice_results.php" method="post">
                <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                <input type="hidden" name="category_name" value="<?php echo htmlspecialchars($category_name); ?>">
                <input type="hidden" name="num_questions_attempted" value="<?php echo $total_questions_for_display; ?>">
                
                <?php foreach ($questions as $index => $question): ?>
                    <input type="hidden" name="questions_info[<?php echo $question['id']; ?>][text]" value="<?php echo htmlspecialchars($question['question_text']); ?>">
                    <input type="hidden" name="questions_info[<?php echo $question['id']; ?>][image_url]" value="<?php echo htmlspecialchars($question['image_url'] ?? ''); ?>">
                    <input type="hidden" name="questions_info[<?php echo $question['id']; ?>][explanation]" value="<?php echo htmlspecialchars($question['explanation'] ?? ''); ?>">
                    <?php if (!empty($question['options'])): ?>
                        <?php foreach ($question['options'] as $opt_idx_hidden => $option_hidden): ?>
                            <input type="hidden" name="questions_info[<?php echo $question['id']; ?>][options_data][<?php echo $option_hidden['id']; ?>][text]" value="<?php echo htmlspecialchars($option_hidden['option_text']); ?>">
                            <input type="hidden" name="questions_info[<?php echo $question['id']; ?>][options_data][<?php echo $option_hidden['id']; ?>][is_correct]" value="<?php echo $option_hidden['is_correct']; ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="card question-card mb-4 shadow-sm" id="question_<?php echo $question['id']; ?>">
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
                                           id="option_<?php echo $option['id']; ?>_q<?php echo $question['id']; ?>"
                                           value="<?php echo $option['id']; ?>"> 
                                    <label class="form-check-label w-100 p-2 rounded border" for="option_<?php echo $option['id']; ?>_q<?php echo $question['id']; ?>">
                                        <?php echo escape_html($option['option_text']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-4 mb-5">
                    <button type="submit" name="submit_practice_quiz" id="submitPracticeQuizBtn" class="btn btn-primary btn-lg">ফলাফল দেখুন</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quizForm = document.getElementById('practiceQuizForm');
    const timerDisplay = document.getElementById('timerDisplayPractice');
    let timeLeft = <?php echo $quiz_duration_seconds > 0 ? $quiz_duration_seconds : -1; ?>;
    let timerInterval;

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        let timeString = "";
        if (h > 0) timeString += String(h).padStart(2, '0') + ":";
        timeString += String(m).padStart(2, '0') + ":" + String(s).padStart(2, '0');
        return timeString;
    }

    function updateTimerDisplay() {
        if (!timerDisplay || timeLeft < 0) return;
        
        timerDisplay.textContent = "সময় বাকি: " + formatTime(timeLeft);

        if (timeLeft <= 60 && timeLeft > 0) { 
            timerDisplay.classList.add('critical'); 
            timerDisplay.classList.remove('text-success');
        } else if (timeLeft > 60) {
            timerDisplay.classList.remove('critical');
        }
        
        if (timeLeft <= 0) {
            timerDisplay.textContent = "সময় শেষ!";
            timerDisplay.classList.remove('critical');
            timerDisplay.classList.add('text-danger');
            if (quizForm && !quizForm.dataset.submitted) {
                quizForm.dataset.submitted = 'true';
                quizForm.submit();
            }
            if(timerInterval) clearInterval(timerInterval);
        }
        if (timeLeft > 0) timeLeft--;
    }

    if (timeLeft >= 0 && document.getElementById('practiceQuizForm')) {
        updateTimerDisplay(); 
        timerInterval = setInterval(updateTimerDisplay, 1000);
    }


    const questionCards = document.querySelectorAll('.question-card');
    questionCards.forEach(questionCard => {
        const radiosInThisGroup = questionCard.querySelectorAll('.question-option-radio');
        radiosInThisGroup.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    const allLabelsInQuestion = questionCard.querySelectorAll('.question-option-wrapper label');
                    allLabelsInQuestion.forEach(lbl => {
                        lbl.classList.remove('selected-option-display', 'border-primary', 'border-2');
                    });
                    const parentWrapper = this.closest('.question-option-wrapper');
                    if (parentWrapper) {
                        const labelForRadio = parentWrapper.querySelector('label');
                        if (labelForRadio) {
                            labelForRadio.classList.add('selected-option-display', 'border-primary', 'border-2');
                        }
                    }
                }
            });
        });
    });

    const practiceSettingsForm = document.getElementById('practiceSettingsForm');
    if (practiceSettingsForm) {
        const numQuestionsInput = document.getElementById('num_questions');
        const totalQuestionsAvailable = <?php echo $total_questions_in_category; ?>;

        numQuestionsInput.addEventListener('input', function() {
            let val = parseInt(this.value);
            if (val < 1) this.value = 1;
            if (totalQuestionsAvailable > 0 && val > totalQuestionsAvailable) {
                this.value = totalQuestionsAvailable;
            }
        });
    }
});
</script>

<?php
if ($conn) { $conn->close(); }
// Corrected include path for footer
require_once 'includes/footer.php';
?>