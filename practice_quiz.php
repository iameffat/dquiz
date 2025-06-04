<?php
// practice_quiz.php
$page_title = "অনুশীলন কুইজ";
$base_url = '';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$category_name = "";
$questions = [];
$total_questions_in_category = 0;
$num_questions_to_show = 20; // ডিফল্ট ২০টি প্রশ্ন, আপনি চাইলে এটি পরিবর্তন করতে পারেন বা ইউজারকে অপশন দিতে পারেন

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
        $_SESSION['flash_message'] = "ক্যাটাগরি খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: categories.php");
        exit;
    }
    $stmt_cat->close();
} else {
    error_log("Failed to prepare category fetch statement: " . $conn->error);
    $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (ক্যাটাগরি তথ্য)।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: categories.php");
    exit;
}


// Fetch total questions in category
$sql_q_count = "SELECT COUNT(id) as total_q FROM questions WHERE category_id = ?";
$stmt_q_count = $conn->prepare($sql_q_count);
if ($stmt_q_count) {
    $stmt_q_count->bind_param("i", $category_id);
    $stmt_q_count->execute();
    $total_questions_in_category = $stmt_q_count->get_result()->fetch_assoc()['total_q'];
    $stmt_q_count->close();
} else {
    error_log("Failed to prepare question count statement: " . $conn->error);
    // Continue, $total_questions_in_category will be 0, handled below.
}


if ($total_questions_in_category == 0) {
    $_SESSION['flash_message'] = "এই ক্যাটাগরিতে কোনো প্রশ্ন পাওয়া যায়নি।";
    $_SESSION['flash_message_type'] = "info";
    header("Location: categories.php");
    exit;
}

$num_questions_to_load = min($num_questions_to_show, $total_questions_in_category);


$sql_questions = "SELECT id, question_text, image_url, explanation FROM questions WHERE category_id = ? ORDER BY RAND() LIMIT ?";
$stmt_questions = $conn->prepare($sql_questions);
if ($stmt_questions) {
    $stmt_questions->bind_param("ii", $category_id, $num_questions_to_load);
    $stmt_questions->execute();
    $result_questions = $stmt_questions->get_result();

    while ($q_row = $result_questions->fetch_assoc()) {
        $options = [];
        $sql_options = "SELECT id, option_text, is_correct FROM options WHERE question_id = ?";
        $stmt_options = $conn->prepare($sql_options);
        if ($stmt_options) {
            $stmt_options->bind_param("i", $q_row['id']);
            $stmt_options->execute();
            $result_options_data = $stmt_options->get_result();
            while ($opt_row = $result_options_data->fetch_assoc()) {
                $options[] = $opt_row;
            }
            $stmt_options->close();
        } else {
            error_log("Failed to prepare options fetch statement: " . $conn->error);
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
    header("Location: categories.php");
    exit;
}


$total_questions_for_display = count($questions);
if ($total_questions_for_display === 0 && $total_questions_in_category > 0) {
    // This might happen if LIMIT is 0 or some other edge case.
    $_SESSION['flash_message'] = "অনুশীলনের জন্য প্রশ্ন লোড করা যায়নি, যদিও ক্যাটাগরিতে প্রশ্ন আছে।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: categories.php");
    exit;
}

require_once 'includes/header.php';
?>
<style>
    /* Styles from quiz_page.php or a common stylesheet should be used */
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
        border: 1px solid var(--border-color);
        padding: 3px;
        background-color: var(--body-bg);
    }
    body.dark-mode .question-image { 
        box-shadow: 0 2px 5px rgba(255,255,255,0.05); 
        border-color: var(--border-color);
        background-color: var(--body-bg);
    }
    .timer-progress-bar { display: none; } 
    
    .question-option-wrapper .form-check-label {
        cursor: pointer;
        transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
    }
    .question-option-wrapper .form-check-label:hover {
        background-color: var(--question-option-hover-bg); /* Defined in style.css */
    }
    .question-option-wrapper label.selected-option-display {
        background-color: var(--primary-color) !important; /* Defined in style.css */
        border-color: var(--primary-color) !important;    /* Defined in style.css */
        color: #fff !important;
        font-weight: bold;
    }
</style>

<div class="container" id="quizContainer">
    <h2 class="mb-4 mt-4 text-center">অনুশীলন কুইজ: <?php echo htmlspecialchars($category_name); ?> (<?php echo $total_questions_for_display; ?> টি প্রশ্ন)</h2>

    <?php if (empty($questions)): ?>
        <div class="alert alert-warning text-center">এই ক্যাটাগরিতে অনুশীলনের জন্য কোনো প্রশ্ন পাওয়া যায়নি।</div>
    <?php else: ?>
        <form id="practiceQuizForm" action="practice_results.php" method="post">
            <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
            <input type="hidden" name="category_name" value="<?php echo htmlspecialchars($category_name); ?>">
            
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
                <button type="submit" name="submit_practice_quiz" class="btn btn-primary btn-lg">ফলাফল দেখুন</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questionCards = document.querySelectorAll('.question-card');
    questionCards.forEach(questionCard => {
        const radiosInThisGroup = questionCard.querySelectorAll('.question-option-radio');
        radiosInThisGroup.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    // Remove selection style from all labels in this question card
                    const allLabelsInQuestion = questionCard.querySelectorAll('.question-option-wrapper label');
                    allLabelsInQuestion.forEach(lbl => {
                        lbl.classList.remove('selected-option-display', 'border-primary', 'border-2');
                    });
                    // Add selection style to the current selection's label
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