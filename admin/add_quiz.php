<?php
$page_title = "নতুন কুইজ যোগ করুন";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$errors = [];
$success_message = "";

// Define the upload path for question images
define('QUESTION_IMAGE_UPLOAD_DIR', '../uploads/question_images/');
if (!is_dir(QUESTION_IMAGE_UPLOAD_DIR)) {
    mkdir(QUESTION_IMAGE_UPLOAD_DIR, 0777, true);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quiz_title = trim($_POST['quiz_title']);
    $quiz_description = trim($_POST['quiz_description']);
    $quiz_duration = intval($_POST['quiz_duration']);
    $quiz_status = trim($_POST['quiz_status']);
    $quiz_live_start = !empty($_POST['quiz_live_start']) ? trim($_POST['quiz_live_start']) : NULL;
    $quiz_live_end = !empty($_POST['quiz_live_end']) ? trim($_POST['quiz_live_end']) : NULL;

    // Validate quiz details
    if (empty($quiz_title)) $errors[] = "কুইজের শিরোনাম আবশ্যক।";
    if ($quiz_duration <= 0) $errors[] = "কুইজের সময় অবশ্যই ০ মিনিটের বেশি হতে হবে।";
    if (!in_array($quiz_status, ['draft', 'live', 'archived'])) $errors[] = "অবৈধ কুইজ স্ট্যাটাস।";

    // Validate questions (at least one question)
    if (!isset($_POST['questions']) || empty($_POST['questions'])) {
        $errors[] = "কুইজে কমপক্ষে একটি প্রশ্ন থাকতে হবে।";
    } else {
        foreach ($_POST['questions'] as $q_idx => $question_data) {
            if (empty(trim($question_data['text']))) {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": প্রশ্নের লেখা খালি রাখা যাবে না।";
            }
            // Image validation (optional based on your requirements)
            if (isset($_FILES['questions']['name'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] == UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['questions']['tmp_name'][$q_idx]['image_url'];
                $file_name = $_FILES['questions']['name'][$q_idx]['image_url'];
                $file_size = $_FILES['questions']['size'][$q_idx]['image_url'];
                $file_type = $_FILES['questions']['type'][$q_idx]['image_url'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_file_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": অনুমোদিত ছবির ধরণ JPEG, PNG, WEBP বা GIF.";
                }
                if ($file_size > $max_file_size) {
                    $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": ছবির ফাইল সাইজ 5MB এর বেশি হতে পারবে না।";
                }
            } elseif (isset($_FILES['questions']['error'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] != UPLOAD_ERR_NO_FILE) {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": ছবি আপলোড করতে সমস্যা হয়েছে (Error code: ".$_FILES['questions']['error'][$q_idx]['image_url'].")";
            }


            if (empty($question_data['options']) || count($question_data['options']) < 2) {
                 $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": কমপক্ষে দুটি অপশন থাকতে হবে।";
            } else {
                $empty_options = 0;
                foreach ($question_data['options'] as $opt_idx => $opt_text) {
                    if (empty(trim($opt_text))) $empty_options++;
                }
                if ($empty_options > 0) $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": অপশনের লেখা খালি রাখা যাবে না।";
            }
            if (!isset($question_data['correct_option']) || $question_data['correct_option'] === '') {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": সঠিক উত্তর নির্বাচন করা হয়নি।";
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_quiz = "INSERT INTO quizzes (title, description, duration_minutes, status, live_start_datetime, live_end_datetime, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_quiz = $conn->prepare($sql_quiz);
            $created_by_user_id = $_SESSION['user_id'];
            $stmt_quiz->bind_param("ssisssi", $quiz_title, $quiz_description, $quiz_duration, $quiz_status, $quiz_live_start, $quiz_live_end, $created_by_user_id);
            
            if (!$stmt_quiz->execute()) {
                throw new Exception("কুইজ সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_quiz->error);
            }
            $quiz_id = $stmt_quiz->insert_id;
            $stmt_quiz->close();

            foreach ($_POST['questions'] as $q_idx => $question_data) {
                $q_text = trim($question_data['text']);
                $q_explanation = isset($question_data['explanation']) ? trim($question_data['explanation']) : NULL;
                $q_image_url = NULL;

                // Handle image upload for the question
                if (isset($_FILES['questions']['name'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] == UPLOAD_ERR_OK) {
                    $file_tmp_name = $_FILES['questions']['tmp_name'][$q_idx]['image_url'];
                    $file_name = basename($_FILES['questions']['name'][$q_idx]['image_url']);
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_file_name = "q_img_" . $quiz_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
                    $upload_path = QUESTION_IMAGE_UPLOAD_DIR . $new_file_name;

                    if (move_uploaded_file($file_tmp_name, $upload_path)) {
                        $q_image_url = 'uploads/question_images/' . $new_file_name; // Path relative to project root
                    } else {
                        throw new Exception("প্রশ্ন #" . ($q_idx + 1) . ": ছবি আপলোড করতে ব্যর্থ।");
                    }
                }

                $sql_question = "INSERT INTO questions (quiz_id, question_text, image_url, explanation, order_number) VALUES (?, ?, ?, ?, ?)";
                $stmt_question = $conn->prepare($sql_question);
                $order_num = $q_idx + 1;
                $stmt_question->bind_param("isssi", $quiz_id, $q_text, $q_image_url, $q_explanation, $order_num);
                
                if (!$stmt_question->execute()) {
                    throw new Exception("প্রশ্ন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_question->error);
                }
                $question_id = $stmt_question->insert_id;
                $stmt_question->close();

                $correct_option_index = intval($question_data['correct_option']);
                foreach ($question_data['options'] as $opt_idx => $opt_text) {
                    $option_text = trim($opt_text);
                    $is_correct = ($opt_idx == $correct_option_index) ? 1 : 0;

                    $sql_option = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                    $stmt_option = $conn->prepare($sql_option);
                    $stmt_option->bind_param("isi", $question_id, $option_text, $is_correct);
                    
                    if (!$stmt_option->execute()) {
                        throw new Exception("অপশন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_option->error);
                    }
                    $stmt_option->close();
                }
            }

            $conn->commit();
            $_SESSION['flash_message'] = "কুইজ \"" . htmlspecialchars($quiz_title) . "\" সফলভাবে তৈরি করা হয়েছে। পার্মালিঙ্ক: <a href='../quiz_page.php?id={$quiz_id}' target='_blank'>../quiz_page.php?id={$quiz_id}</a>";
            $_SESSION['flash_message_type'] = "success";
            header("Location: manage_quizzes.php");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "একটি ত্রুটি ঘটেছে: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-3">নতুন কুইজ যোগ করুন</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong> অনুগ্রহ করে নিচের সমস্যাগুলো সমাধান করুন:
        <ul>
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="addQuizForm" enctype="multipart/form-data">
        <div class="card mb-4">
            <div class="card-header">কুইজের বিবরণ</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="quiz_title" class="form-label">শিরোনাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="quiz_title" name="quiz_title" value="<?php echo isset($_POST['quiz_title']) ? htmlspecialchars($_POST['quiz_title']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="quiz_description" class="form-label">সংক্ষিপ্ত বর্ণনা (ঐচ্ছিক)</label>
                    <textarea class="form-control" id="quiz_description" name="quiz_description" rows="3"><?php echo isset($_POST['quiz_description']) ? htmlspecialchars($_POST['quiz_description']) : ''; ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="quiz_duration" class="form-label">সময় (মিনিট) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quiz_duration" name="quiz_duration" value="<?php echo isset($_POST['quiz_duration']) ? htmlspecialchars($_POST['quiz_duration']) : '10'; ?>" min="1" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="quiz_status" class="form-label">স্ট্যাটাস <span class="text-danger">*</span></label>
                        <select class="form-select" id="quiz_status" name="quiz_status" required>
                            <option value="draft" <?php echo (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'draft') ? 'selected' : ''; ?>>ড্রাফট</option>
                            <option value="live" <?php echo (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'live') ? 'selected' : ''; ?>>লাইভ</option>
                            <option value="archived" <?php echo (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'archived') ? 'selected' : ''; ?>>আর্কাইভড</option>
                        </select>
                    </div>
                </div>
                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="quiz_live_start" class="form-label">লাইভ শুরু (ঐচ্ছিক)</label>
                        <input type="datetime-local" class="form-control" id="quiz_live_start" name="quiz_live_start" value="<?php echo isset($_POST['quiz_live_start']) ? htmlspecialchars($_POST['quiz_live_start']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="quiz_live_end" class="form-label">লাইভ শেষ (ঐচ্ছিক)</label>
                        <input type="datetime-local" class="form-control" id="quiz_live_end" name="quiz_live_end" value="<?php echo isset($_POST['quiz_live_end']) ? htmlspecialchars($_POST['quiz_live_end']) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>

        <div id="questions_container">
            <div class="card question-block mb-3" data-question-index="0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>প্রশ্ন #<span class="question-number">1</span></span>
                    <button type="button" class="btn btn-sm btn-danger remove-question" style="display:none;">প্রশ্ন সরান</button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="question_text_0" class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="questions[0][text]" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="question_image_0" class="form-label">প্রশ্ন সম্পর্কিত ছবি (ঐচ্ছিক)</label>
                        <input type="file" class="form-control" name="questions[0][image_url]" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="form-text text-muted">অনুমোদিত ছবির ধরণ: JPG, PNG, GIF, WEBP. সর্বোচ্চ সাইজ: 5MB.</small>
                    </div>
                    <div class="options-container mb-3">
                        <label class="form-label">অপশন <span class="text-danger">*</span></label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="questions[0][correct_option]" value="<?php echo $i; ?>" aria-label="সঠিক উত্তর" required>
                            </div>
                            <input type="text" class="form-control" name="questions[0][options][<?php echo $i; ?>]" placeholder="অপশন <?php echo $i + 1; ?>" required>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="mb-3">
                        <label for="question_explanation_0" class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                        <textarea class="form-control" name="questions[0][explanation]" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-secondary mb-3" id="add_question_btn">আরও প্রশ্ন যোগ করুন (+)</button>
        <hr>
        <button type="submit" class="btn btn-primary btn-lg">কুইজ সংরক্ষণ করুন</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const questionsContainer = document.getElementById('questions_container');
    const addQuestionBtn = document.getElementById('add_question_btn');
    let questionIndex = document.querySelectorAll('.question-block').length -1;

    function updateQuestionBlocks() {
        const questionBlocks = document.querySelectorAll('.question-block');
        questionBlocks.forEach((block, index) => {
            block.querySelector('.question-number').textContent = index + 1;
            block.querySelectorAll('[name^="questions["]').forEach(input => {
                const oldName = input.getAttribute('name');
                const newName = oldName.replace(/questions\[\d+\]/, `questions[${index}]`);
                input.setAttribute('name', newName);
                // If it's a file input, ensure its ID is also unique if needed, or that labels point correctly
                if(input.type === 'file') {
                    input.id = `question_image_${index}`; // Example: update ID for label if using 'for'
                }
            });
            const removeBtn = block.querySelector('.remove-question');
            if (removeBtn) {
                removeBtn.style.display = questionBlocks.length > 1 ? 'inline-block' : 'none';
            }
        });
    }
    
    addQuestionBtn.addEventListener('click', function () {
        questionIndex++;
        const firstQuestionBlock = document.querySelector('.question-block');
        const newQuestionBlock = firstQuestionBlock.cloneNode(true);

        newQuestionBlock.dataset.questionIndex = questionIndex;
        
        newQuestionBlock.querySelectorAll('textarea, input[type="text"]').forEach(input => input.value = '');
        // Clear file input specifically
        const fileInput = newQuestionBlock.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.value = null; // Or fileInput.value = '';
        }
        newQuestionBlock.querySelectorAll('input[type="radio"]').forEach(radio => radio.checked = false);

        // Update name attributes and IDs for the new block
        newQuestionBlock.querySelectorAll('[name^="questions["]').forEach(input => {
            const oldName = input.getAttribute('name');
            const newName = oldName.replace(/questions\[\d+\]/, `questions[${questionIndex}]`);
            input.setAttribute('name', newName);

            // Update IDs for labels and inputs if they follow a pattern like name_index
            const oldId = input.getAttribute('id');
            if (oldId) {
                 const newId = oldId.replace(/_\d+$/, `_${questionIndex}`);
                 input.setAttribute('id', newId);
                 const labelFor = newQuestionBlock.querySelector(`label[for="${oldId}"]`);
                 if (labelFor) {
                     labelFor.setAttribute('for', newId);
                 }
            }
        });
        
        const removeBtn = newQuestionBlock.querySelector('.remove-question');
        if (removeBtn) {
            removeBtn.style.display = 'inline-block';
            removeBtn.addEventListener('click', function () {
                newQuestionBlock.remove();
                // After removing, re-calculate questionIndex to prevent gaps if last element is removed
                // and then a new one is added. Or, more simply, just ensure unique indices.
                // For simplicity, current questionIndex will just keep incrementing.
                // Re-numbering and button visibility update:
                updateQuestionBlocks();
            });
        }
        
        questionsContainer.appendChild(newQuestionBlock);
        updateQuestionBlocks(); // Update numbering and remove button visibility
    });

    document.querySelectorAll('.question-block .remove-question').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.question-block').remove();
            updateQuestionBlocks();
        });
    });

    updateQuestionBlocks();
});
</script>

<?php
$conn->close();
require_once 'includes/footer.php';
?>