<?php
$page_title = "ক্যাটাগরি ভিত্তিক অনুশীলন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php'; //
require_once 'includes/functions.php'; //

$selected_category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$category_name = '';
$questions_for_practice = [];
$num_questions_to_fetch = isset($_GET['num_questions']) ? intval($_GET['num_questions']) : 10; // Default 10 questions, can be configurable

// Fetch all categories for listing
$all_categories = [];
$sql_all_cats = "SELECT c.id, c.name, c.description, COUNT(q.id) as quiz_count
                 FROM categories c
                 LEFT JOIN quizzes q ON c.id = q.category_id AND q.status != 'draft'
                 GROUP BY c.id, c.name, c.description
                 HAVING COUNT(q.id) > 0 -- Only show categories that have non-draft quizzes
                 ORDER BY c.name ASC";
$result_all_cats = $conn->query($sql_all_cats);
if ($result_all_cats && $result_all_cats->num_rows > 0) {
    while($cat_row = $result_all_cats->fetch_assoc()) {
        $all_categories[] = $cat_row;
    }
}

if ($selected_category_id > 0) {
    // Fetch selected category name
    $sql_cat_name = "SELECT name FROM categories WHERE id = ?";
    $stmt_cat_name = $conn->prepare($sql_cat_name);
    if ($stmt_cat_name) {
        $stmt_cat_name->bind_param("i", $selected_category_id);
        $stmt_cat_name->execute();
        $result_cat_name = $stmt_cat_name->get_result();
        if($cat_data = $result_cat_name->fetch_assoc()){
            $category_name = $cat_data['name'];
            $page_title = "অনুশীলন: " . htmlspecialchars($category_name);
        }
        $stmt_cat_name->close();
    }

    // Fetch questions from all quizzes in the selected category
    // Only pick questions from 'live' or 'archived' quizzes for practice
    $sql_questions = "SELECT qst.id, qst.question_text, qst.image_url, qst.explanation
                      FROM questions qst
                      JOIN quizzes q ON qst.quiz_id = q.id
                      WHERE q.category_id = ? AND (q.status = 'live' OR q.status = 'archived')
                      ORDER BY RAND()
                      LIMIT ?"; // Limit the number of questions
    
    $stmt_questions = $conn->prepare($sql_questions);
    if ($stmt_questions) {
        $stmt_questions->bind_param("ii", $selected_category_id, $num_questions_to_fetch);
        $stmt_questions->execute();
        $result_questions = $stmt_questions->get_result();
        while($q_row = $result_questions->fetch_assoc()){
            $options = [];
            $sql_options = "SELECT id, option_text, is_correct FROM options WHERE question_id = ? ORDER BY RAND()";
            $stmt_options = $conn->prepare($sql_options);
            if ($stmt_options) {
                $stmt_options->bind_param("i", $q_row['id']);
                $stmt_options->execute();
                $result_options = $stmt_options->get_result();
                while($opt_row = $result_options->fetch_assoc()){
                    $options[] = $opt_row;
                }
                $stmt_options->close();
            }
            $q_row['options'] = $options;
            $questions_for_practice[] = $q_row;
        }
        $stmt_questions->close();
    }
}

require_once 'includes/header.php'; //
?>
<style>
    .practice-option-radio:disabled + label {
        cursor: default;
        opacity: 0.7;
    }
    .practice-option-radio:disabled:checked + label.bg-success,
    .practice-option-radio:disabled:checked + label.bg-danger {
        opacity: 1; /* Ensure selected color is fully visible */
    }
    .correct-answer-highlight {
        background-color: #d1e7dd !important; /* Light green for correct answer */
        border-color: #a3cfbb !important;
        color: #0a3622 !important;
    }
    body.dark-mode .correct-answer-highlight {
        background-color: var(--bs-success-bg-subtle) !important;
        border-color: var(--bs-success-border-subtle) !important;
        color: var(--bs-success-text-emphasis) !important;
    }
</style>

<div class="container mt-5 mb-5">
    <h1 class="text-center mb-4"><?php echo htmlspecialchars($page_title); ?></h1>
     <?php display_flash_message(); // ?>

    <?php if ($selected_category_id == 0): ?>
        <h3 class="mb-3 text-center">অনুশীলনের জন্য একটি ক্যাটাগরি নির্বাচন করুন:</h3>
        <?php if (!empty($all_categories)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($all_categories as $cat): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <h5 class="card-title"><?php echo htmlspecialchars($cat['name']); ?></h5>
                                <?php if(!empty($cat['description'])): ?>
                                    <p class="card-text small text-muted"><?php echo htmlspecialchars(mb_strimwidth($cat['description'], 0, 100, "...")); ?></p>
                                <?php endif; ?>
                                <p class="card-text"><small class="text-muted">(<?php echo $cat['quiz_count']; ?> টি কুইজ)</small></p>
                                <a href="practice_category.php?category_id=<?php echo $cat['id']; ?>" class="btn btn-primary stretched-link">অনুশীলন শুরু করুন</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="alert alert-info text-center">অনুশীলনের জন্য কোনো ক্যাটাগরি পাওয়া যায়নি। অ্যাডমিন শীঘ্রই ক্যাটাগরি এবং কুইজ যোগ করবেন।</p>
        <?php endif; ?>

    <?php else: // Category is selected, show questions ?>
        <div class="text-center mb-4">
            <a href="practice_category.php" class="btn btn-outline-secondary">&laquo; সকল ক্যাটাগরি দেখুন</a>
        </div>
        <?php if (!empty($questions_for_practice)): ?>
            <div id="practiceForm"> <?php // Changed from form to div to avoid accidental submission ?>
                <?php foreach ($questions_for_practice as $index => $question): ?>
                    <div class="card question-card mb-4 shadow-sm" id="question_block_<?php echo $question['id']; ?>">
                        <div class="card-header">
                            <h5 class="card-title mb-0">প্রশ্ন <?php echo $index + 1; ?>: <?php echo nl2br(htmlspecialchars($question['question_text'])); ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($question['image_url'])): ?>
                                <div class="mb-3 text-center">
                                    <img src="<?php echo $base_url . htmlspecialchars($question['image_url']); ?>" alt="প্রশ্ন সম্পর্কিত ছবি" class="img-fluid question-image" style="max-height: 250px; border-radius: 5px;">
                                </div>
                            <?php endif; ?>

                            <div class="options-wrapper" data-question-id="<?php echo $question['id']; ?>">
                                <?php foreach ($question['options'] as $opt_idx => $option): ?>
                                <div class="form-check question-option-wrapper mb-2">
                                    <input class="form-check-input practice-option-radio" type="radio"
                                           name="answers[<?php echo $question['id']; ?>]"
                                           id="option_<?php echo $option['id']; ?>"
                                           value="<?php echo $option['id']; ?>"
                                           data-is-correct="<?php echo $option['is_correct']; ?>">
                                    <label class="form-check-label w-100 p-2 rounded border" for="option_<?php echo $option['id']; ?>">
                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="practice-feedback mt-2 small" id="feedback_<?php echo $question['id']; ?>" style="display:none;"></div>
                            <?php if (!empty($question['explanation'])): ?>
                                <div class="practice-explanation mt-2 p-2 bg-light border rounded small" id="explanation_<?php echo $question['id']; ?>" style="display:none;">
                                    <strong>ব্যাখ্যা:</strong> <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-4">
                    <button type="button" id="checkAnswersBtnPractice" class="btn btn-primary btn-lg">উত্তর দেখুন</button>
                     <button type="button" id="nextPracticeSetBtn" class="btn btn-info btn-lg" style="display:none;" onclick="window.location.href='practice_category.php?category_id=<?php echo $selected_category_id; ?>&num_questions=<?php echo $num_questions_to_fetch; ?>'">পরবর্তী সেট</button>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const checkAnswersBtnPractice = document.getElementById('checkAnswersBtnPractice');
                const nextPracticeSetBtn = document.getElementById('nextPracticeSetBtn');

                document.querySelectorAll('.options-wrapper').forEach(wrapper => {
                    wrapper.addEventListener('change', function(event) {
                        if (event.target.classList.contains('practice-option-radio')) {
                            const questionId = this.dataset.questionId;
                            const radiosInGroup = this.querySelectorAll(`.practice-option-radio[name="answers[${questionId}]"]`);
                            radiosInGroup.forEach(r => r.disabled = true); // Disable all options in this group once one is selected
                            
                            const selectedRadio = event.target;
                            const isCorrectVal = selectedRadio.dataset.isCorrect;
                            const optionLabel = selectedRadio.closest('.question-option-wrapper').querySelector('label');
                            const feedbackDiv = document.getElementById(`feedback_${questionId}`);
                            const explanationDiv = document.getElementById(`explanation_${questionId}`);

                            optionLabel.classList.remove('bg-success', 'bg-danger', 'text-white', 'text-dark', 'correct-answer-highlight');
                            
                            if (isCorrectVal == '1') {
                                optionLabel.classList.add('bg-success', 'text-white');
                                if(feedbackDiv) {
                                  feedbackDiv.innerHTML = '<span class="badge bg-success fs-6">সঠিক উত্তর!</span>';
                                  feedbackDiv.style.display = 'block';
                                }
                            } else {
                                optionLabel.classList.add('bg-danger', 'text-white');
                                 if(feedbackDiv) {
                                  feedbackDiv.innerHTML = '<span class="badge bg-danger fs-6">ভুল উত্তর</span>';
                                  feedbackDiv.style.display = 'block';
                                }
                                // Highlight the correct answer
                                const correctRadio = this.querySelector(`.practice-option-radio[data-is-correct="1"]`);
                                if (correctRadio) {
                                    correctRadio.closest('.question-option-wrapper').querySelector('label').classList.add('correct-answer-highlight');
                                }
                            }
                            if (explanationDiv) {
                                explanationDiv.style.display = 'block';
                            }
                        }
                    });
                });

                if (checkAnswersBtnPractice) {
                    checkAnswersBtnPractice.addEventListener('click', function() {
                        let allAnswered = true;
                        document.querySelectorAll('.options-wrapper').forEach(wrapper => {
                            const questionId = wrapper.dataset.questionId;
                            const isAnswered = wrapper.querySelector(`.practice-option-radio[name="answers[${questionId}]"]:checked`);
                            if (!isAnswered) {
                                allAnswered = false;
                                const questionBlock = document.getElementById(`question_block_${questionId}`);
                                if(questionBlock) questionBlock.style.borderColor = 'red'; // Highlight unanswered
                            } else {
                                 const questionBlock = document.getElementById(`question_block_${questionId}`);
                                 if(questionBlock) questionBlock.style.borderColor = '';
                            }
                        });

                        if (!allAnswered) {
                            alert("অনুগ্রহ করে সকল প্রশ্নের উত্তর দিন।");
                            return;
                        }
                        
                        // This button's main role is now to reveal the "Next Set" button if all answered.
                        // Individual question feedback is instant upon selection.
                        // However, we can loop again to ensure all disabled and explanations shown if any missed by instant feedback.
                        document.querySelectorAll('.options-wrapper').forEach(wrapper => {
                            const questionId = wrapper.dataset.questionId;
                             wrapper.querySelectorAll('.practice-option-radio').forEach(r => r.disabled = true);
                            const explanationDiv = document.getElementById(`explanation_${questionId}`);
                            if(explanationDiv) explanationDiv.style.display = 'block';

                            // Ensure correct answer is highlighted if a wrong one was chosen
                            const selectedWrong = wrapper.querySelector(`.practice-option-radio:checked[data-is-correct="0"]`);
                            if(selectedWrong){
                                const correctRadio = wrapper.querySelector(`.practice-option-radio[data-is-correct="1"]`);
                                if (correctRadio) {
                                    correctRadio.closest('.question-option-wrapper').querySelector('label').classList.add('correct-answer-highlight');
                                }
                            }
                        });


                        this.style.display = 'none'; // Hide "Check Answers" button
                        if(nextPracticeSetBtn) nextPracticeSetBtn.style.display = 'inline-block';
                    });
                }
            });
            </script>
        <?php else: ?>
            <p class="alert alert-warning text-center">এই ক্যাটাগরিতে অনুশীলনের জন্য কোনো প্রশ্ন পাওয়া যায়নি। অনুগ্রহ করে অন্য ক্যাটাগরি চেষ্টা করুন অথবা অ্যাডমিন শীঘ্রই প্রশ্ন যোগ করবেন।</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php'; //
?>