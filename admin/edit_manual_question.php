<?php
$page_title = "ম্যানুয়াল প্রশ্ন এডিট করুন";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

if (!defined('QUESTION_IMAGE_UPLOAD_DIR_EDIT_MANUAL')) {
    define('QUESTION_IMAGE_UPLOAD_DIR_EDIT_MANUAL', '../uploads/question_images/');
}
if (!defined('QUESTION_IMAGE_BASE_URL_EDIT_MANUAL')) {
    define('QUESTION_IMAGE_BASE_URL_EDIT_MANUAL', '../');
}

$question_id_to_edit = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;
$question_data = null;
$options_data = [];
$selected_category_ids_db = [];
$errors = [];

if ($question_id_to_edit <= 0) {
    $_SESSION['flash_message'] = "অবৈধ প্রশ্ন ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_manual_questions.php");
    exit;
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

// Fetch existing question details
$sql_fetch_q = "SELECT * FROM questions WHERE id = ? AND quiz_id IS NULL";
$stmt_fetch_q = $conn->prepare($sql_fetch_q);
$stmt_fetch_q->bind_param("i", $question_id_to_edit);
$stmt_fetch_q->execute();
$result_q = $stmt_fetch_q->get_result();
if ($result_q->num_rows === 1) {
    $question_data = $result_q->fetch_assoc();
    $page_title = "এডিট: " . htmlspecialchars(mb_strimwidth($question_data['question_text'],0, 30, "..."));

    // Fetch existing options
    $sql_fetch_o = "SELECT * FROM options WHERE question_id = ? ORDER BY id ASC";
    $stmt_fetch_o = $conn->prepare($sql_fetch_o);
    $stmt_fetch_o->bind_param("i", $question_id_to_edit);
    $stmt_fetch_o->execute();
    $result_o = $stmt_fetch_o->get_result();
    while ($opt_row = $result_o->fetch_assoc()) {
        $options_data[] = $opt_row;
    }
    $stmt_fetch_o->close();

    // Fetch existing linked categories
    $sql_fetch_qc = "SELECT category_id FROM question_categories WHERE question_id = ?";
    $stmt_fetch_qc = $conn->prepare($sql_fetch_qc);
    $stmt_fetch_qc->bind_param("i", $question_id_to_edit);
    $stmt_fetch_qc->execute();
    $result_qc = $stmt_fetch_qc->get_result();
    while ($qc_row = $result_qc->fetch_assoc()) {
        $selected_category_ids_db[] = $qc_row['category_id'];
    }
    $stmt_fetch_qc->close();

} else {
    $_SESSION['flash_message'] = "প্রশ্ন (ID: {$question_id_to_edit}) খুঁজে পাওয়া যায়নি অথবা এটি কোনো কুইজের অংশ।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_manual_questions.php");
    exit;
}
$stmt_fetch_q->close();


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_manual_question'])) {
    $new_question_text = trim($_POST['question_text']);
    $new_question_explanation = isset($_POST['question_explanation']) ? trim($_POST['question_explanation']) : NULL;
    $new_selected_category_ids = isset($_POST['category_ids']) ? $_POST['category_ids'] : [];
    $new_options_data_form = isset($_POST['options']) ? $_POST['options'] : []; // Format: [option_id => text]
    $new_correct_option_id_form = isset($_POST['correct_option']) ? intval($_POST['correct_option']) : 0; // This is the ID of the correct option

    // Validations
    if (empty($new_question_text)) $errors[] = "প্রশ্নের লেখা খালি রাখা যাবে না।";
    if (empty($new_selected_category_ids)) $errors[] = "কমপক্ষে একটি ক্যাটাগরি নির্বাচন করতে হবে।";
    
    $valid_options_count = 0;
    if (is_array($new_options_data_form)) {
        foreach($new_options_data_form as $opt_text_val) {
            if (!empty(trim($opt_text_val))) {
                $valid_options_count++;
            }
        }
    }
    if ($valid_options_count < 2) $errors[] = "কমপক্ষে দুটি অপশনের লেখা থাকতে হবে।";
    
    // Check if correct_option_id_form is a valid ID among submitted options
    if (!array_key_exists($new_correct_option_id_form, $new_options_data_form) || empty(trim($new_options_data_form[$new_correct_option_id_form])) ) {
        $errors[] = "সঠিক উত্তর নির্বাচন করা হয়নি অথবা নির্বাচিত সঠিক অপশনটি খালি।";
    }


    $current_image_url = $question_data['image_url'];
    $new_image_url_for_db = $current_image_url;

    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if (!empty($current_image_url)) {
            $image_path_to_delete_relative = $current_image_url;
            $image_path_to_delete_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT_MANUAL . basename($image_path_to_delete_relative));
            if ($image_path_to_delete_actual && strpos($image_path_to_delete_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT_MANUAL)) === 0 && file_exists($image_path_to_delete_actual)) {
                unlink($image_path_to_delete_actual);
            }
        }
        $new_image_url_for_db = NULL;
    }

    // Image upload handling
    if (isset($_FILES['question_image_url']['name']) && $_FILES['question_image_url']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['question_image_url']['tmp_name'];
        $file_name = basename($_FILES['question_image_url']['name']);
        $file_type = $_FILES['question_image_url']['type'];
        $file_size = $_FILES['question_image_url']['size'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024;

        if (!in_array(strtolower($file_type), $allowed_types)) $errors[] = "অনুমোদিত ছবির ধরণ JPEG, PNG, WEBP বা GIF.";
        if ($file_size > $max_file_size) $errors[] = "ছবির ফাইল সাইজ 5MB এর বেশি হতে পারবে না।";

        if (empty($errors)) { // Only proceed if no validation errors so far
            // Delete old image if a new one is uploaded and old one exists
            if (!empty($current_image_url) && ($new_image_url_for_db !== NULL || (isset($_POST['remove_image']) && $_POST['remove_image'] != '1'))) {
                 $image_path_to_delete_relative = $current_image_url;
                 $image_path_to_delete_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT_MANUAL . basename($image_path_to_delete_relative));
                 if ($image_path_to_delete_actual && strpos($image_path_to_delete_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT_MANUAL)) === 0 && file_exists($image_path_to_delete_actual)) {
                    unlink($image_path_to_delete_actual);
                 }
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $uploaded_file_name = "manual_q_img_" . $question_id_to_edit . "_" . time() . "." . $file_ext;
            $upload_path = QUESTION_IMAGE_UPLOAD_DIR_EDIT_MANUAL . $uploaded_file_name;

            if (move_uploaded_file($file_tmp_name, $upload_path)) {
                $new_image_url_for_db = 'uploads/question_images/' . $uploaded_file_name;
                // Compress image (same logic as add_manual_question.php)
                if (strtolower($file_type) == 'image/jpeg' || strtolower($file_type) == 'image/jpg') { if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) { $source = @imagecreatefromjpeg($upload_path); if($source) { imagejpeg($source, $upload_path, 75); imagedestroy($source); }}}
                elseif (strtolower($file_type) == 'image/png') { if(function_exists('imagecreatefrompng') && function_exists('imagepng')){ $source = @imagecreatefrompng($upload_path); if($source) { imagealphablending($source, false); imagesavealpha($source, true); imagepng($source, $upload_path, 6); imagedestroy($source); }}}
                elseif (strtolower($file_type) == 'image/webp') { if(function_exists('imagecreatefromwebp') && function_exists('imagewebp')){ $source = @imagecreatefromwebp($upload_path); if($source) { imagewebp($source, $upload_path, 80); imagedestroy($source); }}}
            } else {
                $errors[] = "নতুন ছবি আপলোড করতে ব্যর্থ।";
            }
        }
    }


    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Update question text, explanation, image
            $sql_update_q = "UPDATE questions SET question_text = ?, image_url = ?, explanation = ? WHERE id = ? AND quiz_id IS NULL";
            $stmt_update_q = $conn->prepare($sql_update_q);
            $stmt_update_q->bind_param("sssi", $new_question_text, $new_image_url_for_db, $new_question_explanation, $question_id_to_edit);
            if (!$stmt_update_q->execute()) throw new Exception("প্রশ্ন আপডেট করতে সমস্যা: " . $stmt_update_q->error);
            $stmt_update_q->close();

            // Update options
            // Simple approach: delete existing options and re-insert. More complex would be to update existing, delete removed, add new.
            // For simplicity and given only 4 options usually, delete and re-insert is often easier to manage.
            $sql_delete_opts = "DELETE FROM options WHERE question_id = ?";
            $stmt_del_opts = $conn->prepare($sql_delete_opts);
            $stmt_del_opts->bind_param("i", $question_id_to_edit);
            if (!$stmt_del_opts->execute()) throw new Exception("পুরোনো অপশন ডিলিট করতে সমস্যা: " . $stmt_del_opts->error);
            $stmt_del_opts->close();

            $sql_insert_opt = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
            $stmt_insert_opt = $conn->prepare($sql_insert_opt);
            if (!$stmt_insert_opt) throw new Exception("অপশন ইনসার্ট স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
            
            foreach ($new_options_data_form as $opt_id_key => $opt_text_val) { // $opt_id_key is the original option ID or new index
                $opt_text = trim($opt_text_val);
                if (empty($opt_text)) continue;
                
                // $new_correct_option_id_form holds the ID of the option marked as correct.
                // When re-inserting, we use the $opt_id_key (which was the original option ID).
                $is_correct = ($opt_id_key == $new_correct_option_id_form) ? 1 : 0;
                
                $stmt_insert_opt->bind_param("isi", $question_id_to_edit, $opt_text, $is_correct);
                if (!$stmt_insert_opt->execute()) throw new Exception("নতুন অপশন যোগ করতে সমস্যা: " . $stmt_insert_opt->error);
            }
            $stmt_insert_opt->close();


            // Update categories in question_categories
            // 1. Delete existing links for this question
            $sql_delete_qc = "DELETE FROM question_categories WHERE question_id = ?";
            $stmt_delete_qc = $conn->prepare($sql_delete_qc);
            $stmt_delete_qc->bind_param("i", $question_id_to_edit);
            if (!$stmt_delete_qc->execute()) throw new Exception("পুরোনো ক্যাটাগরি লিংক ডিলিট করতে সমস্যা: " . $stmt_delete_qc->error);
            $stmt_delete_qc->close();

            // 2. Insert new links
            if (!empty($new_selected_category_ids)) {
                $sql_insert_qc = "INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)";
                $stmt_insert_qc = $conn->prepare($sql_insert_qc);
                 if (!$stmt_insert_qc) throw new Exception("নতুন ক্যাটাগরি লিংক স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
                foreach ($new_selected_category_ids as $cat_id_val) {
                    $cat_id = intval($cat_id_val);
                    $stmt_insert_qc->bind_param("ii", $question_id_to_edit, $cat_id);
                    if (!$stmt_insert_qc->execute()) throw new Exception("নতুন ক্যাটাগরি লিংক যোগ করতে সমস্যা (ID: {$cat_id}): " . $stmt_insert_qc->error);
                }
                $stmt_insert_qc->close();
            }

            $conn->commit();
            $_SESSION['flash_message'] = "প্রশ্ন (ID: {$question_id_to_edit}) সফলভাবে আপডেট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
            header("Location: manage_manual_questions.php");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "আপডেট করার সময় একটি ত্রুটি ঘটেছে: " . $e->getMessage();
        }
    }
    // If errors, form will repopulate with POSTed values.
    // We need to update $question_data, $options_data, $selected_category_ids_db for repopulation
    $question_data['question_text'] = $new_question_text;
    $question_data['explanation'] = $new_question_explanation;
    $question_data['image_url'] = $new_image_url_for_db; // Show new/removed image status
    
    $temp_options_data = [];
    foreach($new_options_data_form as $opt_id => $opt_text_val) {
        $temp_options_data[] = [
            'id' => $opt_id, // This is crucial: use the key from form as ID
            'option_text' => $opt_text_val,
            'is_correct' => ($opt_id == $new_correct_option_id_form) ? 1 : 0
        ];
    }
    $options_data = $temp_options_data;
    $selected_category_ids_db = $new_selected_category_ids;


}


require_once 'includes/header.php';
?>
<style>
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered { white-space: normal !important; }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice { margin-top: 0.3rem !important; }
    .select2-container--bootstrap-5 .select2-dropdown { border-color: var(--bs-border-color); background-color: var(--bs-body-bg); }
    .select2-container--bootstrap-5 .select2-results__option { color: var(--bs-body-color); }
    .select2-container--bootstrap-5 .select2-results__option--highlighted { background-color: var(--bs-primary); color: white; }
    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple { background-color: var(--bs-secondary-bg) !important; border-color: var(--bs-border-color) !important; }
    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice { background-color: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color) !important; color: var(--bs-body-color) !important; }
    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove { color: var(--bs-body-color) !important; }
    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover { color: var(--bs-danger) !important; }
    .suggestions-container { border: 1px solid var(--bs-border-color); border-top: none; max-height: 150px; overflow-y: auto; background-color: var(--bs-body-bg); position: absolute; z-index: 1050; width: 100%; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); display: none; border-radius: 0 0 var(--bs-border-radius); }
    .suggestion-item { padding: .5rem .75rem; cursor: pointer; font-size: .9rem; color: var(--bs-body-color); }
    .suggestion-item:hover { background-color: var(--bs-tertiary-bg); }
    .form-control-wrapper { position: relative; }
    .input-group .form-control-wrapper { display: flex; flex-direction: column; flex-grow: 1; }
    .input-group .form-control-wrapper .suggestions-container { width: 100%; }
</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid">
    <h1 class="mt-4 mb-3"><?php echo $page_title; ?> (ID: <?php echo $question_id_to_edit; ?>)</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong> অনুগ্রহ করে নিচের সমস্যাগুলো সমাধান করুন:
        <ul><?php foreach ($errors as $error): echo "<li>" . htmlspecialchars($error) . "</li>"; endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <?php display_flash_message(); ?>

    <form action="edit_manual_question.php?question_id=<?php echo $question_id_to_edit; ?>" method="post" enctype="multipart/form-data">
        <div class="card mb-4">
            <div class="card-header">প্রশ্নের বিবরণ (এডিট)</div>
            <div class="card-body">
                <div class="mb-3 form-control-wrapper">
                    <label for="question_text" class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                    <textarea class="form-control question-input-suggest" id="question_text" name="question_text" rows="3" required><?php echo htmlspecialchars($question_data['question_text']); ?></textarea>
                     <div class="suggestions-container" id="suggestions_q_text_edit"></div>
                </div>

                <div class="mb-3">
                    <label for="question_image_url" class="form-label">প্রশ্ন সম্পর্কিত ছবি (ঐচ্ছিক)</label>
                    <?php if (!empty($question_data['image_url'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo QUESTION_IMAGE_BASE_URL_EDIT_MANUAL . htmlspecialchars($question_data['image_url']); ?>" alt="Current Question Image" class="admin-question-image-preview">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image_q_<?php echo $question_id_to_edit; ?>">
                                <label class="form-check-label" for="remove_image_q_<?php echo $question_id_to_edit; ?>">এই ছবিটি মুছে ফেলুন</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="question_image_url" name="question_image_url" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small class="form-text text-muted">নতুন ছবি আপলোড করলে আগেরটি (যদি থাকে এবং "মুছে ফেলুন" চেক করা না থাকে) প্রতিস্থাপিত হবে। সর্বোচ্চ সাইজ: 5MB.</small>
                </div>

                <div class="mb-3">
                    <label for="category_ids_edit" class="form-label">ক্যাটাগরি(সমূহ) <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_ids_edit" name="category_ids[]" multiple="multiple" required>
                        <?php if (!empty($categories_list)): ?>
                            <?php foreach ($categories_list as $category_item): ?>
                                <option value="<?php echo $category_item['id']; ?>" <?php echo in_array($category_item['id'], $selected_category_ids_db) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category_item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="options-container mb-3">
                    <label class="form-label">অপশন ও সঠিক উত্তর <span class="text-danger">*</span></label>
                    <?php foreach ($options_data as $index => $option): ?>
                    <div class="input-group mb-2">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="radio" name="correct_option" 
                                   value="<?php echo $option['id']; // Use option's actual ID as value ?>" 
                                   id="correct_opt_<?php echo $option['id']; ?>"
                                   aria-label="সঠিক উত্তর <?php echo $index + 1; ?>" required 
                                   <?php if ($option['is_correct'] == 1) echo 'checked'; ?>>
                        </div>
                        <div class="form-control-wrapper flex-grow-1">
                            <input type="text" class="form-control option-input-suggest" 
                                   name="options[<?php echo $option['id']; // Use option's ID as key for POST data ?>]" 
                                   id="option_text_<?php echo $option['id']; ?>"
                                   placeholder="অপশন <?php echo $index + 1; ?>" 
                                   value="<?php echo htmlspecialchars($option['option_text']); ?>" required>
                            <div class="suggestions-container" id="suggestions_opt_edit_<?php echo $option['id']; ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($options_data) < 4): // Allow adding up to 4 options if less exist, though this form focuses on editing existing 4 ?>
                        <?php for ($i = count($options_data); $i < 4; $i++): ?>
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                     <input class="form-check-input mt-0" type="radio" name="correct_option" value="new_<?php echo $i; ?>" aria-label="সঠিক উত্তর <?php echo $i + 1; ?>">
                                </div>
                                <div class="form-control-wrapper flex-grow-1">
                                <input type="text" class="form-control option-input-suggest" name="options[new_<?php echo $i; ?>]" placeholder="নতুন অপশন <?php echo $i + 1; ?>">
                                <div class="suggestions-container" id="suggestions_opt_edit_new_<?php echo $i; ?>"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="question_explanation" class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                    <textarea class="form-control" id="question_explanation" name="question_explanation" rows="2"><?php echo htmlspecialchars($question_data['explanation']); ?></textarea>
                </div>
            </div>
        </div>
        <button type="submit" name="update_manual_question" class="btn btn-primary btn-lg">আপডেট করুন</button>
        <a href="manage_manual_questions.php" class="btn btn-outline-secondary btn-lg">বাতিল</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#category_ids_edit').select2({
        theme: "bootstrap-5",
        placeholder: "ক্যাটাগরি নির্বাচন করুন",
        allowClear: true,
        width: '100%'
    });

    let suggestionDebounceTimeout;
    function fetchSuggestions(inputValue, inputType, suggestionsContainerEl, inputFieldEl) {
        if (inputValue.length < 2) {
            suggestionsContainerEl.innerHTML = ''; suggestionsContainerEl.style.display = 'none'; return;
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
            xhr.onerror = function () { console.error("Suggestion request network error."); suggestionsContainerEl.innerHTML = ''; suggestionsContainerEl.style.display = 'none'; };
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

    const questionTextareaEdit = document.getElementById('question_text');
    const suggestionsContainerQuestionEdit = document.getElementById('suggestions_q_text_edit');
    if (questionTextareaEdit && suggestionsContainerQuestionEdit) {
        questionTextareaEdit.addEventListener('input', function () { fetchSuggestions(this.value, 'question', suggestionsContainerQuestionEdit, this); });
        questionTextareaEdit.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerQuestionEdit.style.display = 'none'; }, 150); });
        questionTextareaEdit.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'question', suggestionsContainerQuestionEdit, this);});
    }

    document.querySelectorAll('.option-input-suggest').forEach(optInput => {
        const optId = optInput.id.replace('option_text_', ''); // e.g., 123 or new_0
        const suggestionsContainerOptionEdit = document.getElementById(`suggestions_opt_edit_${optId}`);
         if (optInput && suggestionsContainerOptionEdit) {
            optInput.addEventListener('input', function () { fetchSuggestions(this.value, 'option', suggestionsContainerOptionEdit, this); });
            optInput.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerOptionEdit.style.display = 'none'; }, 150); });
            optInput.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'option', suggestionsContainerOptionEdit, this);});
        }
    });
});
</script>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>