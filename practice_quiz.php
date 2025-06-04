<?php
// practice_quiz.php
$page_title = "অনুশীলন কুইজ";
$base_url = '';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$category_id = isset($_REQUEST['category_id']) ? intval($_REQUEST['category_id']) : 0; // GET or POST
$category_name = "";
$questions = [];
$total_questions_in_category = 0;

// ডিফল্ট মান
$default_num_questions = 10; // আগের ২০ থেকে কমিয়ে ১০ করা হলো ডিফল্ট
$default_duration_minutes = 0; // ০ মানে কোনো সময়সীমা নেই

$num_questions_to_show = isset($_POST['num_questions']) ? intval($_POST['num_questions']) : $default_num_questions;
$quiz_duration_minutes = isset($_POST['quiz_duration']) ? intval($_POST['quiz_duration']) : $default_duration_minutes;
$quiz_duration_seconds = $quiz_duration_minutes * 60;

$start_quiz = isset($_POST['start_practice_quiz_submit']); // ফর্ম সাবমিট হয়েছে কিনা

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
    if ($cat_data = $result_cat->fetch_assoc()) {
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
    error_log("Failed to prepare category fetch statement: " . $conn->error);
    $_SESSION['flash_message'] = "ডাটাবেস সমস্যা: ক্যাটাগরির তথ্য আনতে সমস্যা হয়েছে।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: categories.php");
    exit;
}

// Fetch total number of questions in this category
$sql_q_count = "SELECT COUNT(id) as total_q FROM questions WHERE category_id = ?";
$stmt_q_count = $conn->prepare($sql_q_count);
if($stmt_q_count){
    $stmt_q_count->bind_param("i", $category_id);
    $stmt_q_count->execute();
    $total_questions_in_category = $stmt_q_count->get_result()->fetch_assoc()['total_q'];
    $stmt_q_count->close();
} else {
    error_log("Failed to prepare question count statement: " . $conn->error);
}

if ($total_questions_in_category == 0 && $start_quiz) { // Check only if trying to start
    $_SESSION['flash_message'] = "দুঃখিত, \"" . htmlspecialchars($category_name) . "\" ক্যাটাগরিতে অনুশীলনের জন্য কোনো প্রশ্ন এখনো যোগ করা হয়নি।";
    $_SESSION['flash_message_type'] = "info";
    header("Location: categories.php"); // Redirect back or to categories.php
    exit;
}


// যদি ফর্ম সাবমিট করা হয় এবং প্রশ্ন সংখ্যা ভ্যালিড হয়, তাহলে প্রশ্ন লোড করুন
if ($start_quiz) {
    if ($num_questions_to_show <= 0) {
        // যদি ইউজার ০ বা তার কম প্রশ্ন সিলেক্ট করে, একটি ডিফল্ট সংখ্যা ব্যবহার করুন বা এরর দেখান
        // আপাতত ডিফল্ট ১০টি প্রশ্ন লোড করা হচ্ছে।
        $num_questions_to_show = $default_num_questions; 
        // আপনি চাইলে এখানে একটি এরর মেসেজও সেট করতে পারেন
        // $_SESSION['flash_message'] = "অনুগ্রহ করে কমপক্ষে ১টি প্রশ্ন নির্বাচন করুন।";
        // $_SESSION['flash_message_type'] = "warning";
    }
    $num_questions_to_load = min($num_questions_to_show, $total_questions_in_category);
    if($num_questions_to_load <=0 && $total_questions_in_category > 0) $num_questions_to_load = $total_questions_in_category;


    $sql_questions = "SELECT id, question_text, image_url, explanation FROM questions WHERE category_id = ? ORDER BY RAND() LIMIT ?";
    $stmt_questions = $conn->prepare($sql_questions);
    if ($stmt_questions) {
        $stmt_questions->bind_param("ii", $category_id, $num_questions_to_load);
        $stmt_questions->execute();
        $result_questions = $stmt_questions->get_result();

        while ($q_row = $result_questions->fetch_assoc()) {
            $options = [];
            $sql_options = "SELECT id, option_text, is_correct FROM options WHERE question_id = ?";
            $stmt_options_inner = $conn->prepare($sql_options);
            if ($stmt_options_inner) {
                $stmt_options_inner->bind_param("i", $q_row['id']);
                $stmt_options_inner->execute();
                $result_options_data = $stmt_options_inner->get_result();
                while ($opt_row = $result_options_data->fetch_assoc()) {
                    $options[] = $opt_row;
                }
                $stmt_options_inner->close();
            } else {
                error_log("Failed to prepare options fetch statement for question ID " . $q_row['id'] . ": " . $conn->error);
            }
            shuffle($options);
            $q_row['options'] = $options;
            $questions[] = $q_row;
        }
        $stmt_questions->close();
    } else {
        error_log("Failed to prepare questions fetch statement: " . $conn->error);
        $_SESSION['flash_message'] = "প্রশ্ন আনতে ডাটাবেস সমস্যা হয়েছে।";
        $_SESSION['flash_message_type'] = "danger";
        // Redirect or handle error display
        header("Location: practice_quiz.php?category_id=" . $category_id);
        exit;
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

    .timer-display { font-size: 1.2rem; font-weight: bold; }
    .timer-display.critical { color: var(--bs-danger); }
    .settings-card { max-width: 600px; margin: 2rem auto; }
";
require_once 'includes/header.php';
?>

<div class="container" id="quizInterfaceContainer">
    <h2 class="mb-3 mt-4 text-center">অনুশীলন কুইজ: <?php echo htmlspecialchars($category_name); ?></h2>

    <?php if (!$start_quiz): // যদি কুইজ শুরু না হয়ে থাকে, সেটিংস ফর্ম দেখান ?>
        <div class="card shadow-sm settings-card">
            <div class="card-header">
                <h5 class="mb-0">অনুশীলন সেটিংস</h5>
            </div>
            <div class="card-body">
                <form action="practice_quiz.php" method="post" id="practiceSettingsForm">
                    <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                    <div class="mb-3">
                        <label for="num_questions" class="form-label">কতটি প্রশ্ন দিয়ে অনুশীলন করতে চান?</label>
                        <select name="num_questions" id="num_questions" class="form-select">
                            <?php
                                $options_q_count = [5, 10, 15, 20, 25, 30, 50];
                                if ($total_questions_in_category > 0) {
                                    echo "<option value='{$total_questions_in_category}'>সবগুলো ({$total_questions_in_category}টি)</option>";
                                }
                                foreach($options_q_count as $count_opt){
                                    if($count_opt < $total_questions_in_category){
                                        echo "<option value='{$count_opt}' ".($count_opt == $default_num_questions ? 'selected' : '').">{$count_opt} টি</option>";
                                    }
                                }
                                if ($total_questions_in_category > 0 && !in_array($total_questions_in_category, $options_q_count) && $total_questions_in_category <= max($options_q_count) && $total_questions_in_category > min($options_q_count)) {
                                    // Add if total is unique and within reasonable range
                                } elseif ($total_questions_in_category == 0 && empty($options_q_count)) {
                                     echo "<option value='10' selected>১০ টি (ডিফল্ট)</option>"; // Fallback if no questions
                                }
                            ?>
                        </select>
                         <small class="form-text text-muted">এই ক্যাটাগরিতে মোট <?php echo $total_questions_in_category; ?> টি প্রশ্ন রয়েছে।</small>
                    </div>
                    <div class="mb-3">
                        <label for="quiz_duration" class="form-label">সময়সীমা (মিনিট):</label>
                        <input type="number" name="quiz_duration" id="quiz_duration" class="form-control" value="<?php echo $default_duration_minutes; ?>" min="0">
                        <small class="form-text text-muted">০ দিলে কোনো সময়সীমা থাকবে না।</small>
                    </div>
                    <button type="submit" name="start_practice_quiz_submit" class="btn btn-primary w-100">অনুশীলন শুরু করুন</button>
                </form>
                 <div class="mt-3 practice-mode-notice">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill me-2" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16M8 4a.905.905 0 0 1 .9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995A.905.905 0 0 1 8 4m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                    </svg>
                    এটি একটি অনুশীলন কুইজ। আপনার উত্তর বা ফলাফল সার্ভারে জমা রাখা হবে না।
                </div>
            </div>
        </div>
    <?php else: // কুইজ শুরু হয়েছে, প্রশ্ন দেখান ?>
        
        <?php if ($quiz_duration_seconds > 0): ?>
        <div class="text-center my-3 timer-display-container">
            <span id="timerDisplayPractice" class="timer-display">সময় বাকি: <?php echo gmdate("H:i:s", $quiz_duration_seconds); ?></span>
        </div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <div class="alert alert-warning text-center">এই ক্যাটাগরি বা নির্বাচিত সংখ্যক প্রশ্নের জন্য কোনো প্রশ্ন পাওয়া যায়নি।</div>
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
                                           value="<?php echo $option['id']; ?>" required>
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
    let timeLeft = <?php echo $quiz_duration_seconds > 0 ? $quiz_duration_seconds : -1; ?>; // -1 if no timer
    let timerInterval;

    function updateTimerDisplay() {
        if (!timerDisplay || timeLeft < 0) return;
        const hours = Math.floor(timeLeft / 3600);
        const minutes = Math.floor((timeLeft % 3600) / 60);
        const seconds = timeLeft % 60;
        
        let displayString = "সময় বাকি: ";
        if (hours > 0) displayString += String(hours).padStart(2, '0') + ":";
        displayString += String(minutes).padStart(2, '0') + ":" + String(seconds).padStart(2, '0');
        
        timerDisplay.textContent = displayString;

        if (timeLeft <= 60 && timeLeft > 0) { 
            timerDisplay.classList.add('critical'); 
            timerDisplay.classList.remove('text-success'); // Example: remove other colors
        } else if (timeLeft > 60) {
            timerDisplay.classList.remove('critical');
             // timerDisplay.classList.add('text-success'); // Example: green for ample time
        }
        
        if (timeLeft <= 0) {
            timerDisplay.textContent = "সময় শেষ!";
            timerDisplay.classList.remove('critical');
            timerDisplay.classList.add('text-danger');
            if (quizForm && !quizForm.dataset.submitted) {
                quizForm.dataset.submitted = 'true';
                // Check if all questions have an answer before submitting
                let allAnswered = true;
                const questionCards = document.querySelectorAll('.question-card');
                questionCards.forEach(card => {
                    const questionId = card.id.split('_')[1];
                    if (!document.querySelector(`input[name="answers[${questionId}]"]:checked`)) {
                        // If an answer is required even on timeout, you can alert the user or handle it.
                        // For practice, it might be okay to submit as is.
                    }
                });
                quizForm.submit();
            }
            if(timerInterval) clearInterval(timerInterval);
        }
        if (timeLeft > 0) timeLeft--;
    }

    if (timeLeft > 0) { // Start timer only if duration is set
        updateTimerDisplay(); // Initial display
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
});
</script>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>