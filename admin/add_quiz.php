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
    if (!mkdir(QUESTION_IMAGE_UPLOAD_DIR, 0777, true) && !is_dir(QUESTION_IMAGE_UPLOAD_DIR)) {
        $errors[] = "ছবি আপলোডের জন্য ডিরেক্টরি তৈরি করা যায়নি: " . QUESTION_IMAGE_UPLOAD_DIR;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quiz_title = trim($_POST['quiz_title']);
    $quiz_description = trim($_POST['quiz_description']);
    $quiz_duration = intval($_POST['quiz_duration']);
    $quiz_status = trim($_POST['quiz_status']);
    $quiz_live_start = !empty($_POST['quiz_live_start']) ? trim($_POST['quiz_live_start']) : NULL;
    $quiz_live_end = !empty($_POST['quiz_live_end']) ? trim($_POST['quiz_live_end']) : NULL;

    if (empty($quiz_title)) $errors[] = "কুইজের শিরোনাম আবশ্যক।";
    if ($quiz_duration <= 0) $errors[] = "কুইজের সময় অবশ্যই ০ মিনিটের বেশি হতে হবে।";
    // Updated status validation
    if (!in_array($quiz_status, ['draft', 'live', 'archived', 'upcoming'])) $errors[] = "অবৈধ কুইজ স্ট্যাটাস।";

    if ($quiz_live_start && $quiz_live_end && strtotime($quiz_live_start) >= strtotime($quiz_live_end)) {
        $errors[] = "লাইভ শেষের সময় অবশ্যই শুরুর সময়ের পরে হতে হবে।";
    }

    if (!isset($_POST['questions']) || empty($_POST['questions'])) {
        $errors[] = "কুইজে কমপক্ষে একটি প্রশ্ন থাকতে হবে।";
    } else {
        foreach ($_POST['questions'] as $q_idx => $question_data) {
            if (empty(trim($question_data['text']))) {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": প্রশ্নের লেখা খালি রাখা যাবে না।";
            }
            
            if (isset($_FILES['questions']['name'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] == UPLOAD_ERR_OK) {
                $file_name_check = $_FILES['questions']['name'][$q_idx]['image_url'];
                $file_size_check = $_FILES['questions']['size'][$q_idx]['image_url'];
                $file_type_check = $_FILES['questions']['type'][$q_idx]['image_url'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_file_size = 5 * 1024 * 1024; // 5MB

                if (!in_array(strtolower($file_type_check), $allowed_types)) {
                    $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": অনুমোদিত ছবির ধরণ JPEG, PNG, WEBP বা GIF. আপনি দিয়েছেন: " . $file_type_check;
                }
                if ($file_size_check > $max_file_size) {
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

                if (isset($_FILES['questions']['name'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] == UPLOAD_ERR_OK) {
                    $file_tmp_name = $_FILES['questions']['tmp_name'][$q_idx]['image_url'];
                    $file_name = basename($_FILES['questions']['name'][$q_idx]['image_url']);
                    $file_type = $_FILES['questions']['type'][$q_idx]['image_url'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    $safe_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($file_ext, $safe_extensions)) {
                        throw new Exception("প্রশ্ন #" . ($q_idx + 1) . ": অবৈধ ফাইল এক্সটেনশন।");
                    }

                    $new_file_name = "q_img_" . $quiz_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
                    $upload_path = QUESTION_IMAGE_UPLOAD_DIR . $new_file_name;

                    if (move_uploaded_file($file_tmp_name, $upload_path)) {
                        $q_image_url = 'uploads/question_images/' . $new_file_name;

                        if (strtolower($file_type) == 'image/jpeg' || strtolower($file_type) == 'image/jpg') {
                            if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
                                $source_image = imagecreatefromjpeg($upload_path);
                                if ($source_image) {
                                    imagejpeg($source_image, $upload_path, 75);
                                    imagedestroy($source_image);
                                }
                            }
                        } elseif (strtolower($file_type) == 'image/png') {
                             if (function_exists('imagecreatefrompng') && function_exists('imagepng')) {
                                $source_image = imagecreatefrompng($upload_path);
                                if ($source_image) {
                                    imagealphablending($source_image, false); 
                                    imagesavealpha($source_image, true);    
                                    imagepng($source_image, $upload_path, 6); 
                                    imagedestroy($source_image);
                                }
                            }
                        } elseif (strtolower($file_type) == 'image/webp') {
                            if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
                                $source_image = imagecreatefromwebp($upload_path);
                                if ($source_image) {
                                    imagewebp($source_image, $upload_path, 80); 
                                    imagedestroy($source_image);
                                }
                            }
                        }
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
<style>
/* Styles for suggestion box */
.suggestions-container {
    border: 1px solid #ced4da; /* Bootstrap's default border color for forms */
    border-top: none; /* Avoid double border with the input field */
    max-height: 150px;
    overflow-y: auto;
    background-color: #fff;
    position: absolute; /* Position it relative to the wrapper */
    z-index: 1050; /* Ensure it's above other elements, like Bootstrap's dropdown z-index */
    width: 100%; /* Take full width of its parent wrapper */
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); /* Soft shadow like Bootstrap's dropdown */
    display: none; /* Hidden by default */
    border-radius: 0 0 0.25rem 0.25rem; /* Rounded bottom corners */
}
.suggestion-item {
    padding: 0.5rem 0.75rem; /* Consistent padding */
    cursor: pointer;
    font-size: 0.9rem; /* Slightly smaller font for suggestions */
}
.suggestion-item:hover {
    background-color: #e9ecef; /* Bootstrap's light hover color */
}
/* Wrapper div to position suggestion box correctly */
.form-control-wrapper {
    position: relative; /* This is key for absolute positioning of suggestions-container */
}
.input-group .form-control-wrapper {
    /* For options inside input-group, make wrapper flexible */
    display: flex;
    flex-direction: column;
    flex-grow: 1; /* Allow it to take up space */
}
.input-group .form-control-wrapper .suggestions-container {
    /* Adjust width if input-group styling messes it up, or use JS to set width */
     width: 100%; /* This might need tweaking depending on input-group structure */
}

</style>

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
                    <label for="quiz_description_editor" class="form-label">সংক্ষিপ্ত বর্ণনা (ঐচ্ছিক)</label>
                    <div id="quiz_description_editor"><?php echo isset($_POST['quiz_description']) ? $_POST['quiz_description'] : ''; ?></div>
                    <input type="hidden" name="quiz_description" id="quiz_description_hidden">
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
                            <option value="upcoming" <?php echo (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'upcoming') ? 'selected' : ''; ?>>আপকামিং</option>
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
            <div class="card question-block mb-3" data-question-index="0"> <div class="card-header d-flex justify-content-between align-items-center">
                    <span>প্রশ্ন #<span class="question-number">1</span></span>
                    <button type="button" class="btn btn-sm btn-danger remove-question" style="display:none;">প্রশ্ন সরান</button>
                </div>
                <div class="card-body">
                    <div class="mb-3 form-control-wrapper"> <label for="question_text_0" class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                        <textarea class="form-control question-input-suggest" id="question_text_0" name="questions[0][text]" rows="2" required></textarea>
                        <div class="suggestions-container" id="suggestions_q_0"></div> </div>
                    <div class="mb-3">
                        <label for="question_image_0" class="form-label">প্রশ্ন সম্পর্কিত ছবি (ঐচ্ছিক)</label>
                        <input type="file" class="form-control" id="question_image_0" name="questions[0][image_url]" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="form-text text-muted">অনুমোদিত ছবির ধরণ: JPG, PNG, GIF, WEBP. সর্বোচ্চ সাইজ: 5MB.</small>
                    </div>
                    <div class="options-container mb-3">
                        <label class="form-label">অপশন <span class="text-danger">*</span></label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2"> <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="questions[0][correct_option]" value="<?php echo $i; ?>" aria-label="সঠিক উত্তর <?php echo $i + 1; ?>" required <?php if($i==0) echo 'checked';?>>
                            </div>
                             <div class="form-control-wrapper flex-grow-1">
                                <input type="text" class="form-control option-input-suggest" name="questions[0][options][<?php echo $i; ?>]" placeholder="অপশন <?php echo $i + 1; ?>" id="option_text_0_<?php echo $i; ?>">
                                <div class="suggestions-container" id="suggestions_q_0_opt_<?php echo $i; ?>"></div> </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="mb-3">
                        <label for="question_explanation_0" class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                        <textarea class="form-control" id="question_explanation_0" name="questions[0][explanation]" rows="2"></textarea>
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
    
    let suggestionDebounceTimeout;

    function fetchSuggestions(inputValue, inputType, suggestionsContainerEl, inputFieldEl) {
        if (inputValue.length < 2) {
            suggestionsContainerEl.innerHTML = '';
            suggestionsContainerEl.style.display = 'none';
            return;
        }
        clearTimeout(suggestionDebounceTimeout);
        suggestionDebounceTimeout = setTimeout(() => {
            const xhr = new XMLHttpRequest();
            const encodedQuery = encodeURIComponent(inputValue);
            xhr.open('GET', `ajax_suggestions.php?query=${encodedQuery}&type=${inputType}`, true);
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const suggestions = JSON.parse(xhr.responseText);
                        displaySuggestions(suggestions, suggestionsContainerEl, inputFieldEl);
                    } catch (e) {
                        console.error("Error parsing suggestions JSON:", e, xhr.responseText);
                        suggestionsContainerEl.innerHTML = '';
                        suggestionsContainerEl.style.display = 'none';
                    }
                } else {
                    console.error("Suggestion request failed:", xhr.status, xhr.statusText);
                    suggestionsContainerEl.innerHTML = '';
                    suggestionsContainerEl.style.display = 'none';
                }
            };
            xhr.onerror = function () {
                console.error("Suggestion request network error.");
                suggestionsContainerEl.innerHTML = '';
                suggestionsContainerEl.style.display = 'none';
            };
            xhr.send();
        }, 350); // Debounce time
    }

    function displaySuggestions(suggestions, containerEl, inputFieldEl) {
        containerEl.innerHTML = '';
        if (suggestions && suggestions.length > 0) {
            suggestions.forEach(suggestionText => {
                const item = document.createElement('div');
                item.classList.add('suggestion-item');
                item.textContent = suggestionText;
                item.addEventListener('mousedown', function (e) { // Use mousedown to fire before blur
                    e.preventDefault(); // Prevent blur if any
                    inputFieldEl.value = suggestionText;
                    containerEl.innerHTML = '';
                    containerEl.style.display = 'none';
                });
                containerEl.appendChild(item);
            });
            containerEl.style.display = 'block';
        } else {
            containerEl.style.display = 'none';
        }
    }
    
    function setupSuggestionListenersForBlock(questionBlock) {
        // For question textarea
        const questionTextarea = questionBlock.querySelector('.question-input-suggest');
        if (questionTextarea) {
            const qIndex = questionBlock.dataset.questionIndex;
            const suggestionsContainerQuestion = questionBlock.querySelector(`#suggestions_q_${qIndex}`);
            if (suggestionsContainerQuestion) {
                questionTextarea.addEventListener('input', function () {
                    fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this);
                });
                questionTextarea.addEventListener('blur', function () {
                    setTimeout(() => {
                        suggestionsContainerQuestion.innerHTML = '';
                        suggestionsContainerQuestion.style.display = 'none';
                    }, 150); // Delay to allow click on suggestion
                });
                 questionTextarea.addEventListener('focus', function () { // Reshow if there's content
                    if(this.value.length >=2) fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this);
                });
            }
        }

         // Initialize Quill editor for quiz description
    if (document.getElementById('quiz_description_editor')) {
        const quillDescription = new Quill('#quiz_description_editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'], // 'image' can be added if you want image uploads within description
                    ['clean']
                ]
            }
        });

        // On form submission, update the hidden input with Quill's HTML content
        const addQuizForm = document.getElementById('addQuizForm');
        if (addQuizForm) {
            addQuizForm.addEventListener('submit', function() {
                const descriptionHiddenInput = document.getElementById('quiz_description_hidden');
                if (descriptionHiddenInput) {
                    descriptionHiddenInput.value = quillDescription.root.innerHTML;
                }
            });
        }
        
        // Preserve content if form reloads with an error (Quill initializes with the div's content)
        <?php if (isset($_POST['quiz_description'])): ?>
        // The content is already set in the div's HTML by PHP.
        // If the content was complex HTML, you might need:
        // quillDescription.root.innerHTML = <?php echo json_encode($_POST['quiz_description']); ?>;
        <?php endif; ?>
    }
        // For option inputs
        const optionInputs = questionBlock.querySelectorAll('.option-input-suggest');
        optionInputs.forEach((optInput, optIndex) => {
            const qIndex = questionBlock.dataset.questionIndex;
            const suggestionsContainerOption = questionBlock.querySelector(`#suggestions_q_${qIndex}_opt_${optIndex}`);
            if (suggestionsContainerOption) {
                optInput.addEventListener('input', function () {
                    fetchSuggestions(this.value, 'option', suggestionsContainerOption, this);
                });
                optInput.addEventListener('blur', function () {
                     setTimeout(() => {
                        suggestionsContainerOption.innerHTML = '';
                        suggestionsContainerOption.style.display = 'none';
                    }, 150);
                });
                optInput.addEventListener('focus', function () {
                     if(this.value.length >=2) fetchSuggestions(this.value, 'option', suggestionsContainerOption, this);
                });
            }
        });
    }

    function updateQuestionBlocks() {
        const questionBlocks = document.querySelectorAll('.question-block');
        questionBlocks.forEach((block, index) => {
            block.dataset.questionIndex = index; // Set/Update the question index on the block itself
            block.querySelector('.question-number').textContent = index + 1;

            // Update IDs and names for question text, image, explanation
            const qTextarea = block.querySelector('textarea[name^="questions["][name$="[text]"]');
            if(qTextarea) {
                qTextarea.name = `questions[${index}][text]`;
                qTextarea.id = `question_text_${index}`;
                block.querySelector(`label[for^="question_text_"]`).setAttribute('for', `question_text_${index}`);
                const suggestionsContainerQ = block.querySelector('.suggestions-container[id^="suggestions_q_"]');
                 if (suggestionsContainerQ) suggestionsContainerQ.id = `suggestions_q_${index}`;
            }
            
            const qImage = block.querySelector('input[type="file"][name^="questions["][name$="[image_url]"]');
             if(qImage) {
                qImage.name = `questions[${index}][image_url]`;
                qImage.id = `question_image_${index}`;
                block.querySelector(`label[for^="question_image_"]`).setAttribute('for', `question_image_${index}`);
            }

            const qExplanation = block.querySelector('textarea[name^="questions["][name$="[explanation]"]');
            if(qExplanation){
                qExplanation.name = `questions[${index}][explanation]`;
                qExplanation.id = `question_explanation_${index}`;
                block.querySelector(`label[for^="question_explanation_"]`).setAttribute('for', `question_explanation_${index}`);
            }
            
            // Update options
            const optionGroups = block.querySelectorAll('.options-container .input-group');
            optionGroups.forEach((optGroup, optIdx) => {
                const radio = optGroup.querySelector('input[type="radio"]');
                if(radio) radio.name = `questions[${index}][correct_option]`;
                
                const textInput = optGroup.querySelector('input[type="text"].option-input-suggest');
                if(textInput) {
                    textInput.name = `questions[${index}][options][${optIdx}]`;
                    textInput.id = `option_text_${index}_${optIdx}`; // Unique ID for option text input
                    // Update suggestion container ID for this option
                    const optSuggestionContainer = optGroup.querySelector('.suggestions-container[id^="suggestions_q_"]');
                    if(optSuggestionContainer) optSuggestionContainer.id = `suggestions_q_${index}_opt_${optIdx}`;
                }
            });
            
            const removeBtn = block.querySelector('.remove-question');
            if (removeBtn) {
                removeBtn.style.display = questionBlocks.length > 1 ? 'inline-block' : 'none';
            }
            // After re-indexing, ensure suggestion listeners are correctly set up for this block
            setupSuggestionListenersForBlock(block);
        });
    }
    
    addQuestionBtn.addEventListener('click', function () {
        const currentBlocks = document.querySelectorAll('.question-block');
        const nextIndex = currentBlocks.length; // This will be the index for the new block

        const firstQuestionBlock = document.querySelector('.question-block');
        if (!firstQuestionBlock) return; // Should not happen
        const newQuestionBlock = firstQuestionBlock.cloneNode(true);

        // Set the data-question-index immediately for the new block
        newQuestionBlock.dataset.questionIndex = nextIndex; 

        // Clear input values and checks
        newQuestionBlock.querySelectorAll('textarea, input[type="text"], input[type="file"]').forEach(input => input.value = '');
        newQuestionBlock.querySelectorAll('input[type="radio"]').forEach(radio => radio.checked = false);
        
        const firstRadioInNew = newQuestionBlock.querySelector('input[type="radio"][value="0"]');
        if (firstRadioInNew) firstRadioInNew.checked = true;

        // Clear any cloned suggestion items from the template
        newQuestionBlock.querySelectorAll('.suggestions-container').forEach(sc => {
            sc.innerHTML = '';
            sc.style.display = 'none';
        });
        
        questionsContainer.appendChild(newQuestionBlock);
        updateQuestionBlocks(); 
    });

    questionsContainer.addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-question')) {
            event.target.closest('.question-block').remove();
            updateQuestionBlocks();
        }
    });

    // Initial setup for the first block
    updateQuestionBlocks(); 
});
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'includes/footer.php';
?>