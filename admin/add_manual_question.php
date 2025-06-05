<?php
$page_title = "ম্যানুয়ালি প্রশ্ন যোগ করুন";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$errors = [];
$success_message = "";

// Define QUESTION_IMAGE_UPLOAD_DIR if not already defined (it's in add_quiz.php)
if (!defined('QUESTION_IMAGE_UPLOAD_DIR')) {
    define('QUESTION_IMAGE_UPLOAD_DIR', '../uploads/question_images/');
}
if (!is_dir(QUESTION_IMAGE_UPLOAD_DIR)) {
    if (!mkdir(QUESTION_IMAGE_UPLOAD_DIR, 0777, true) && !is_dir(QUESTION_IMAGE_UPLOAD_DIR)) {
        $errors[] = "ছবি আপলোডের জন্য ডিরেক্টরি তৈরি করা যায়নি: " . QUESTION_IMAGE_UPLOAD_DIR;
    }
}

// Fetch categories for the multi-select dropdown
$categories_list = [];
$sql_cat_list = "SELECT id, name FROM categories ORDER BY name ASC";
$result_cat_list = $conn->query($sql_cat_list);
if ($result_cat_list) {
    while ($cat_row = $result_cat_list->fetch_assoc()) {
        $categories_list[] = $cat_row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_manual_question'])) {
    $question_text = trim($_POST['question_text']);
    $question_explanation = isset($_POST['question_explanation']) ? trim($_POST['question_explanation']) : NULL;
    $selected_category_ids = isset($_POST['category_ids']) ? $_POST['category_ids'] : []; // Array of selected category IDs
    $options_texts_from_form = isset($_POST['options']) ? $_POST['options'] : [];
    $correct_option_form_index = isset($_POST['correct_option']) ? intval($_POST['correct_option']) : -1;

    // --- Validations ---
    if (empty($question_text)) {
        $errors[] = "প্রশ্নের লেখা খালি রাখা যাবে না।";
    }
    if (empty($selected_category_ids)) {
        $errors[] = "কমপক্ষে একটি ক্যাটাগরি নির্বাচন করতে হবে।";
    }
    if (count(array_filter(array_map('trim', $options_texts_from_form))) < 2) {
        $errors[] = "কমপক্ষে দুটি অপশনের লেখা থাকতে হবে।";
    }
    if ($correct_option_form_index < 0 || $correct_option_form_index >= count($options_texts_from_form) || empty(trim($options_texts_from_form[$correct_option_form_index])) ) {
        $errors[] = "সঠিক উত্তর নির্বাচন করা হয়নি অথবা নির্বাচিত সঠিক অপশনটি খালি।";
    }
    
    $q_image_url_for_db = NULL;
    // Image upload validation (similar to add_quiz.php)
    if (isset($_FILES['question_image_url']['name']) && $_FILES['question_image_url']['error'] == UPLOAD_ERR_OK) {
        $file_name_check = $_FILES['question_image_url']['name'];
        $file_size_check = $_FILES['question_image_url']['size'];
        $file_type_check = $_FILES['question_image_url']['type'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array(strtolower($file_type_check), $allowed_mime_types)) {
            $errors[] = "অনুমোদিত ছবির ধরণ JPEG, PNG, WEBP বা GIF. আপনি দিয়েছেন: " . htmlspecialchars($file_type_check);
        }
        if ($file_size_check > $max_file_size) {
            $errors[] = "ছবির ফাইল সাইজ 5MB এর বেশি হতে পারবে না।";
        }
    } elseif (isset($_FILES['question_image_url']['error']) && $_FILES['question_image_url']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors[] = "ছবি আপলোড করতে সমস্যা হয়েছে (Error code: ".$_FILES['question_image_url']['error'].")";
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Handle image upload
            if (isset($_FILES['question_image_url']['name']) && $_FILES['question_image_url']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['question_image_url']['tmp_name'];
                $file_name = basename($_FILES['question_image_url']['name']);
                $file_type = $_FILES['question_image_url']['type'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $safe_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($file_ext, $safe_extensions)) {
                    throw new Exception("অবৈধ ফাইল এক্সটেনশন (" . htmlspecialchars($file_ext) . ")।");
                }
                // Using time() and uniqid() for image name to avoid conflicts, similar to add_quiz.php but without quiz_id
                $new_file_name = "manual_q_img_" . time() . "_" . uniqid('', true) . "." . $file_ext;
                $upload_path = QUESTION_IMAGE_UPLOAD_DIR . $new_file_name;

                if (move_uploaded_file($file_tmp_name, $upload_path)) {
                    $q_image_url_for_db = 'uploads/question_images/' . $new_file_name;
                    // Image compression logic (copied from add_quiz.php)
                    if (strtolower($file_type) == 'image/jpeg' || strtolower($file_type) == 'image/jpg') { if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) { $source = @imagecreatefromjpeg($upload_path); if($source) { imagejpeg($source, $upload_path, 75); imagedestroy($source); }}}
                    elseif (strtolower($file_type) == 'image/png') { if(function_exists('imagecreatefrompng') && function_exists('imagepng')){ $source = @imagecreatefrompng($upload_path); if($source) { imagealphablending($source, false); imagesavealpha($source, true); imagepng($source, $upload_path, 6); imagedestroy($source); }}}
                    elseif (strtolower($file_type) == 'image/webp') { if(function_exists('imagecreatefromwebp') && function_exists('imagewebp')){ $source = @imagecreatefromwebp($upload_path); if($source) { imagewebp($source, $upload_path, 80); imagedestroy($source); }}}
                } else {
                    throw new Exception("ছবি আপলোড করতে ব্যর্থ। সার্ভার পারমিশন চেক করুন।");
                }
            }

            // Insert into questions table (quiz_id is NULL, order_number can be 0 or 1 for manual questions)
            // For manual questions, category_id in the `questions` table itself will be NULL,
            // as categories are handled by the junction table `question_categories`.
            $sql_question = "INSERT INTO questions (quiz_id, question_text, image_url, explanation, order_number, category_id) VALUES (NULL, ?, ?, ?, 0, NULL)";
            $stmt_question = $conn->prepare($sql_question);
            if (!$stmt_question) throw new Exception("প্রশ্ন স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
            
            $stmt_question->bind_param("sss", $question_text, $q_image_url_for_db, $question_explanation);
            if (!$stmt_question->execute()) {
                if($q_image_url_for_db && file_exists(QUESTION_IMAGE_UPLOAD_DIR . basename($q_image_url_for_db))) {
                    unlink(QUESTION_IMAGE_UPLOAD_DIR . basename($q_image_url_for_db)); // Delete uploaded image if question save fails
                }
                throw new Exception("প্রশ্ন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_question->error);
            }
            $question_id = $stmt_question->insert_id;
            $stmt_question->close();

            // Insert options
            foreach ($options_texts_from_form as $opt_form_idx => $opt_text) {
                $option_text_trimmed = trim($opt_text);
                if (empty($option_text_trimmed)) continue;
                $is_correct = ($opt_form_idx == $correct_option_form_index) ? 1 : 0;

                $sql_option = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                $stmt_option = $conn->prepare($sql_option);
                if (!$stmt_option) throw new Exception("অপশন স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
                $stmt_option->bind_param("isi", $question_id, $option_text_trimmed, $is_correct);
                if (!$stmt_option->execute()) {
                    throw new Exception("অপশন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_option->error);
                }
                $stmt_option->close();
            }

            // Insert into question_categories junction table
            if (!empty($selected_category_ids)) {
                $sql_q_cat = "INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)";
                $stmt_q_cat = $conn->prepare($sql_q_cat);
                if (!$stmt_q_cat) throw new Exception("প্রশ্ন-ক্যাটাগরি স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
                
                foreach ($selected_category_ids as $cat_id) {
                    $stmt_q_cat->bind_param("ii", $question_id, $cat_id);
                    if (!$stmt_q_cat->execute()) {
                        // If a specific category link fails, maybe log it or add to a partial success message
                        // For simplicity, we'll let it throw an exception to rollback all if one fails
                         throw new Exception("প্রশ্ন-ক্যাটাগরি লিংক সংরক্ষণ করতে সমস্যা (ID: {$cat_id}): " . $stmt_q_cat->error);
                    }
                }
                $stmt_q_cat->close();
            }

            $conn->commit();
            $_SESSION['flash_message'] = "প্রশ্ন সফলভাবে সংরক্ষণ করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
            header("Location: add_manual_question.php"); // Redirect to same page to add more or show success
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
    /* For multi-select */
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
        white-space: normal !important;
    }
    .select2-container--bootstrap-5 .select2-dropdown {
        border-color: var(--bs-border-color);
        background-color: var(--bs-body-bg);
    }
    .select2-container--bootstrap-5 .select2-results__option {
        color: var(--bs-body-color);
    }
    .select2-container--bootstrap-5 .select2-results__option--highlighted {
        background-color: var(--bs-primary);
        color: white;
    }
    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple {
        background-color: var(--bs-tertiary-bg-rgb) !important;
        border-color: var(--bs-border-color) !important;
    }
    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: var(--bs-secondary-bg-subtle) !important;
        border-color: var(--bs-border-color) !important;
        color: var(--bs-body-color) !important;
    }
    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--bs-body-color) !important;
    }
    /* Suggestions Box Styles from add_quiz.php */
    .suggestions-container {
        border: 1px solid var(--bs-border-color); border-top: none; max-height: 150px;
        overflow-y: auto; background-color: var(--bs-body-bg); position: absolute;
        z-index: 1050; width: 100%; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        display: none; border-radius: 0 0 var(--bs-border-radius);
    }
    .suggestion-item { padding: 0.5rem 0.75rem; cursor: pointer; font-size: 0.9rem; color: var(--bs-body-color); }
    .suggestion-item:hover { background-color: var(--bs-tertiary-bg); }
    .form-control-wrapper { position: relative; }
    .input-group .form-control-wrapper { display: flex; flex-direction: column; flex-grow: 1; }
    .input-group .form-control-wrapper .suggestions-container { width: 100%; }
</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid">
    <h1 class="mt-4 mb-3"><?php echo $page_title; ?></h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong> অনুগ্রহ করে নিচের সমস্যাগুলো সমাধান করুন:
        <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <?php display_flash_message(); ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        <div class="card mb-4">
            <div class="card-header">প্রশ্নের বিবরণ</div>
            <div class="card-body">
                <div class="mb-3 form-control-wrapper">
                    <label for="question_text" class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                    <textarea class="form-control question-input-suggest" id="question_text" name="question_text" rows="3" required><?php echo isset($_POST['question_text']) ? htmlspecialchars($_POST['question_text']) : ''; ?></textarea>
                    <div class="suggestions-container" id="suggestions_q_text"></div>
                </div>

                <div class="mb-3">
                    <label for="question_image_url" class="form-label">প্রশ্ন সম্পর্কিত ছবি (ঐচ্ছিক)</label>
                    <input type="file" class="form-control" id="question_image_url" name="question_image_url" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small class="form-text text-muted">অনুমোদিত ছবির ধরণ: JPG, PNG, GIF, WEBP. সর্বোচ্চ সাইজ: 5MB.</small>
                </div>

                <div class="mb-3">
                    <label for="category_ids" class="form-label">ক্যাটাগরি(সমূহ) <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_ids" name="category_ids[]" multiple="multiple" required>
                        <?php if (!empty($categories_list)): ?>
                            <?php foreach ($categories_list as $category_item): ?>
                                <?php 
                                $selected = '';
                                if (isset($_POST['category_ids']) && is_array($_POST['category_ids']) && in_array($category_item['id'], $_POST['category_ids'])) {
                                    $selected = 'selected';
                                }
                                ?>
                                <option value="<?php echo $category_item['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($category_item['name']); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>কোনো ক্যাটাগরি পাওয়া যায়নি। অনুগ্রহ করে প্রথমে ক্যাটাগরি যোগ করুন।</option>
                        <?php endif; ?>
                    </select>
                    <small class="form-text text-muted">প্রশ্নটি এক বা একাধিক ক্যাটাগরির সাথে যুক্ত করতে পারেন।</small>
                </div>

                <div class="options-container mb-3">
                    <label class="form-label">অপশন ও সঠিক উত্তর <span class="text-danger">*</span></label>
                    <?php for ($i = 0; $i < 4; $i++): 
                        $option_value = isset($_POST['options'][$i]) ? htmlspecialchars($_POST['options'][$i]) : '';
                        $is_checked = (isset($_POST['correct_option']) && $_POST['correct_option'] == $i);
                    ?>
                    <div class="input-group mb-2">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="radio" name="correct_option" value="<?php echo $i; ?>" aria-label="সঠিক উত্তর <?php echo $i + 1; ?>" required <?php if($is_checked) echo 'checked'; ?>>
                        </div>
                        <div class="form-control-wrapper flex-grow-1">
                             <input type="text" class="form-control option-input-suggest" name="options[<?php echo $i; ?>]" placeholder="অপশন <?php echo $i + 1; ?>" value="<?php echo $option_value; ?>" required>
                             <div class="suggestions-container" id="suggestions_opt_<?php echo $i; ?>"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="mb-3">
                    <label for="question_explanation" class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                    <textarea class="form-control" id="question_explanation" name="question_explanation" rows="2"><?php echo isset($_POST['question_explanation']) ? htmlspecialchars($_POST['question_explanation']) : ''; ?></textarea>
                </div>
            </div>
        </div>
        <button type="submit" name="save_manual_question" class="btn btn-primary btn-lg">প্রশ্ন সংরক্ষণ করুন</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#category_ids').select2({
        theme: "bootstrap-5",
        placeholder: "ক্যাটাগরি নির্বাচন করুন",
        allowClear: true,
        width: '100%'
    });

    // Suggestion logic (copied from add_quiz.php)
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
            const ajaxUrl = `ajax_suggestions.php?query=${encodeURIComponent(inputValue)}&type=${inputType}`;
            xhr.open('GET', ajaxUrl, true);
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const suggestions = JSON.parse(xhr.responseText);
                        displaySuggestions(suggestions, suggestionsContainerEl, inputFieldEl);
                    } catch (e) {
                        console.error("Error parsing suggestions JSON:", e, xhr.responseText);
                        suggestionsContainerEl.innerHTML = ''; suggestionsContainerEl.style.display = 'none';
                    }
                } else {
                    console.error("Suggestion request failed:", xhr.status, xhr.statusText);
                    suggestionsContainerEl.innerHTML = ''; suggestionsContainerEl.style.display = 'none';
                }
            };
            xhr.onerror = function () {
                console.error("Suggestion request network error.");
                suggestionsContainerEl.innerHTML = ''; suggestionsContainerEl.style.display = 'none';
            };
            xhr.send();
        }, 350);
    }

    function displaySuggestions(suggestions, containerEl, inputFieldEl) {
        containerEl.innerHTML = '';
        if (suggestions && suggestions.length > 0) {
            suggestions.forEach(suggestionText => {
                const item = document.createElement('div');
                item.classList.add('suggestion-item');
                item.textContent = suggestionText;
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    inputFieldEl.value = suggestionText;
                    containerEl.innerHTML = ''; containerEl.style.display = 'none';
                    inputFieldEl.focus();
                });
                containerEl.appendChild(item);
            });
            containerEl.style.display = 'block';
        } else {
            containerEl.style.display = 'none';
        }
    }

    const questionTextarea = document.getElementById('question_text');
    const suggestionsContainerQuestion = document.getElementById('suggestions_q_text');
    if (questionTextarea && suggestionsContainerQuestion) {
        questionTextarea.addEventListener('input', function () { fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this); });
        questionTextarea.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerQuestion.style.display = 'none'; }, 150); });
        questionTextarea.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this);});
    }

    const optionInputs = document.querySelectorAll('.option-input-suggest');
    optionInputs.forEach((optInput, index) => {
        const suggestionsContainerOption = document.getElementById(`suggestions_opt_${index}`);
        if (suggestionsContainerOption) {
            optInput.addEventListener('input', function () { fetchSuggestions(this.value, 'option', suggestionsContainerOption, this); });
            optInput.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerOption.style.display = 'none'; }, 150); });
            optInput.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'option', suggestionsContainerOption, this);});
        }
    });
});
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'includes/footer.php';
?>