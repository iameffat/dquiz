<?php
$page_title = "নতুন কুইজ যোগ করুন";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$errors = [];

define('QUESTION_IMAGE_UPLOAD_DIR', '../uploads/question_images/');
if (!is_dir(QUESTION_IMAGE_UPLOAD_DIR)) {
    if (!mkdir(QUESTION_IMAGE_UPLOAD_DIR, 0777, true) && !is_dir(QUESTION_IMAGE_UPLOAD_DIR)) {
        $errors[] = "ছবি আপলোডের জন্য ডিরেক্টরি তৈরি করা যায়নি: " . QUESTION_IMAGE_UPLOAD_DIR;
    }
}

$categories_list = [];
$sql_cat_list = "SELECT id, name FROM categories ORDER BY name ASC";
$result_cat_list = $conn->query($sql_cat_list);
if ($result_cat_list) {
    while ($cat_row = $result_cat_list->fetch_assoc()) {
        $categories_list[] = $cat_row;
    }
}

$imported_questions = [];
// Store posted category_ids for repopulation on error
$posted_category_ids_for_questions = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['prepare_questions_from_bulk'])) {
    // --- BULK IMPORT LOGIC ---
    $bulk_text = trim($_POST['bulk_questions_text_import']);
    if (!empty($bulk_text)) {
        $lines = array_map('trim', explode("\n", $bulk_text));
        $current_q_data = null;

        foreach ($lines as $line_number => $line) {
            $trimmed_line = trim($line);
            if (empty($trimmed_line) && $current_q_data === null) continue;

            if (preg_match('/^\s*(\d+)\.\s*(.+)/', $trimmed_line, $matches_q)) {
                if ($current_q_data !== null && !empty($current_q_data['text']) && count($current_q_data['options']) >= 1) {
                    $imported_questions[] = $current_q_data;
                }
                $current_q_data = [
                    'text' => trim($matches_q[2]),
                    'options' => [],
                    'explanation' => '',
                    'image_url' => null,
                    'category_ids' => [], // Initialize for multi-select for imported questions
                    'correct_option' => 0
                ];
            } elseif ($current_q_data !== null && preg_match('/^\s*(\*?)\s*([a-zA-Z\p{Bengali}][\p{Bengali}]*|[iIvVxX]+|[A-Za-z])\.\s*(.+)/u', $trimmed_line, $matches_o)) {
                if (count($current_q_data['options']) < 4) {
                    $is_correct_option_from_bulk = (trim($matches_o[1]) === '*');
                    $option_text = trim($matches_o[3]);
                    $current_q_data['options'][] = $option_text;
                    if ($is_correct_option_from_bulk) {
                        $current_q_data['correct_option'] = count($current_q_data['options']) - 1;
                    }
                }
            } elseif ($current_q_data !== null && preg_match('/^\s*=\s*(.+)/', $trimmed_line, $matches_exp)) {
                $current_q_data['explanation'] = trim($matches_exp[1]);
            }
        }
        if ($current_q_data !== null && !empty($current_q_data['text']) && count($current_q_data['options']) >= 1) {
            $imported_questions[] = $current_q_data;
        }
        if (empty($imported_questions) && !empty($bulk_text)) {
             $errors[] = "প্রদত্ত টেক্সট থেকে কোনো প্রশ্ন ও অপশন সঠিকভাবে পার্স করা যায়নি। অনুগ্রহ করে ফরম্যাট চেক করুন।";
        }
    } else {
        $errors[] = "ইম্পোর্টের জন্য টেক্সট-এরিয়াতে কোনো লেখা পাওয়া যায়নি।";
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['prepare_questions_from_bulk'])) {
    $quiz_title = trim($_POST['quiz_title']);
    $quiz_description = trim($_POST['quiz_description']);
    $quiz_duration = intval($_POST['quiz_duration']);
    $quiz_status = trim($_POST['quiz_status']);
    $quiz_live_start = !empty($_POST['quiz_live_start']) ? trim($_POST['quiz_live_start']) : NULL;
    $quiz_live_end = !empty($_POST['quiz_live_end']) ? trim($_POST['quiz_live_end']) : NULL;

    if (empty($quiz_title)) $errors[] = "কুইজের শিরোনাম আবশ্যক।";
    if ($quiz_duration <= 0) $errors[] = "কুইজের সময় অবশ্যই ০ মিনিটের বেশি হতে হবে।";
    if (!in_array($quiz_status, ['draft', 'live', 'archived', 'upcoming'])) $errors[] = "অবৈধ কুইজ স্ট্যাটাস।";

    if ($quiz_live_start && $quiz_live_end && strtotime($quiz_live_start) >= strtotime($quiz_live_end)) {
        $errors[] = "লাইভ শেষের সময় অবশ্যই শুরুর সময়ের পরে হতে হবে।";
    }

    if (!isset($_POST['questions']) || empty($_POST['questions'])) {
        $errors[] = "কুইজে কমপক্ষে একটি প্রশ্ন থাকতে হবে।";
    } else {
        foreach ($_POST['questions'] as $q_idx => $question_data) {
            $posted_category_ids_for_questions[$q_idx] = $question_data['category_ids'] ?? []; // Store for repopulation

            if (empty(trim($question_data['text']))) {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": প্রশ্নের লেখা খালি রাখা যাবে না।";
            }
            // Category is now optional, so no validation needed for its presence
            // if (!isset($question_data['category_ids']) || empty($question_data['category_ids'])) {
            // $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": কমপক্ষে একটি ক্যাটাগরি নির্বাচন করতে হবে।";
            // }

            // Image validation
            if (isset($_FILES['questions']['name'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] == UPLOAD_ERR_OK) {
                $file_name_check = $_FILES['questions']['name'][$q_idx]['image_url'];
                $file_size_check = $_FILES['questions']['size'][$q_idx]['image_url'];
                $file_type_check = $_FILES['questions']['type'][$q_idx]['image_url'];
                $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_file_size = 5 * 1024 * 1024; // 5MB

                if (!in_array(strtolower($file_type_check), $allowed_mime_types)) {
                    $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": অনুমোদিত ছবির ধরণ JPEG, PNG, WEBP বা GIF. আপনি দিয়েছেন: " . htmlspecialchars($file_type_check);
                }
                if ($file_size_check > $max_file_size) {
                    $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": ছবির ফাইল সাইজ 5MB এর বেশি হতে পারবে না।";
                }
            } elseif (isset($_FILES['questions']['error'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] != UPLOAD_ERR_NO_FILE) {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": ছবি আপলোড করতে সমস্যা হয়েছে (Error code: ".$_FILES['questions']['error'][$q_idx]['image_url'].")";
            }
            // Options validation
            if (empty($question_data['options'])) {
                 $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": কমপক্ষে দুটি অপশন থাকতে হবে।";
            } else {
                $actual_options_provided = 0;
                foreach ($question_data['options'] as $opt_text) {
                    if (!empty(trim($opt_text))) {
                        $actual_options_provided++;
                    }
                }
                if ($actual_options_provided < 2) {
                     $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": কমপক্ষে দুটি অপশনের লেখা থাকতে হবে।";
                }
            }
            if (!isset($question_data['correct_option']) || $question_data['correct_option'] === '' || empty(trim($question_data['options'][intval($question_data['correct_option'])])) ) {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": সঠিক উত্তর নির্বাচন করা হয়নি অথবা নির্বাচিত সঠিক অপশনটি খালি।";
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_quiz = "INSERT INTO quizzes (title, description, duration_minutes, status, live_start_datetime, live_end_datetime, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_quiz = $conn->prepare($sql_quiz);
            if (!$stmt_quiz) throw new Exception("কুইজ স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
            
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
                $selected_category_ids_for_q = isset($question_data['category_ids']) ? $question_data['category_ids'] : []; 
                $q_image_url = NULL; 

                // Image upload
                if (isset($_FILES['questions']['name'][$q_idx]['image_url']) && $_FILES['questions']['error'][$q_idx]['image_url'] == UPLOAD_ERR_OK) {
                    $file_tmp_name = $_FILES['questions']['tmp_name'][$q_idx]['image_url'];
                    $file_name = basename($_FILES['questions']['name'][$q_idx]['image_url']);
                    $file_type = $_FILES['questions']['type'][$q_idx]['image_url']; 
                    
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $safe_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($file_ext, $safe_extensions)) {
                        throw new Exception("প্রশ্ন #" . ($q_idx + 1) . ": অবৈধ ফাইল এক্সটেনশন (" . htmlspecialchars($file_ext) . ")।");
                    }

                    $new_file_name = "q_img_" . $quiz_id . "_" . time() . "_" . uniqid('', true) . "." . $file_ext;
                    $upload_path = QUESTION_IMAGE_UPLOAD_DIR . $new_file_name;

                    if (move_uploaded_file($file_tmp_name, $upload_path)) {
                        $q_image_url = 'uploads/question_images/' . $new_file_name; 
                        // Image compression
                        if (strtolower($file_type) == 'image/jpeg' || strtolower($file_type) == 'image/jpg') { if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) { $source_image = @imagecreatefromjpeg($upload_path); if ($source_image) { imagejpeg($source_image, $upload_path, 75); imagedestroy($source_image); }}}
                        elseif (strtolower($file_type) == 'image/png') { if (function_exists('imagecreatefrompng') && function_exists('imagepng')) { $source_image = @imagecreatefrompng($upload_path); if ($source_image) { imagealphablending($source_image, false); imagesavealpha($source_image, true); imagepng($source_image, $upload_path, 6); imagedestroy($source_image); }}}
                        elseif (strtolower($file_type) == 'image/webp') { if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) { $source_image = @imagecreatefromwebp($upload_path); if ($source_image) { imagewebp($source_image, $upload_path, 80); imagedestroy($source_image); }}}
                    } else {
                        throw new Exception("প্রশ্ন #" . ($q_idx + 1) . ": ছবি আপলোড করতে ব্যর্থ। সার্ভার পারমিশন চেক করুন।");
                    }
                }

                // Set category_id to NULL in questions table for quiz questions
                $sql_question = "INSERT INTO questions (quiz_id, question_text, image_url, explanation, order_number, category_id) VALUES (?, ?, ?, ?, ?, NULL)";
                $stmt_question = $conn->prepare($sql_question);
                if (!$stmt_question) throw new Exception("প্রশ্ন স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);

                $order_num = $q_idx + 1; 
                $stmt_question->bind_param("isssi", $quiz_id, $q_text, $q_image_url, $q_explanation, $order_num);
                
                if (!$stmt_question->execute()) {
                    if($q_image_url && file_exists(QUESTION_IMAGE_UPLOAD_DIR . basename($q_image_url))) {
                        unlink(QUESTION_IMAGE_UPLOAD_DIR . basename($q_image_url));
                    }
                    throw new Exception("প্রশ্ন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_question->error);
                }
                $question_id = $stmt_question->insert_id;
                $stmt_question->close();

                // Insert options
                $correct_option_form_index = intval($question_data['correct_option']);
                $options_texts_from_form = $question_data['options'];
                if (count(array_filter(array_map('trim', $options_texts_from_form))) < 2) {
                     throw new Exception("প্রশ্ন #" . ($q_idx + 1) . " এর জন্য কমপক্ষে দুটি অপশনের লেখা থাকতে হবে।");
                }
                foreach ($options_texts_from_form as $opt_form_idx => $opt_text) {
                    $option_text_trimmed = trim($opt_text);
                    if (empty($option_text_trimmed)) continue; 
                    $is_correct = ($opt_form_idx == $correct_option_form_index) ? 1 : 0;
                    $sql_option = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                    $stmt_option = $conn->prepare($sql_option);
                    if (!$stmt_option) throw new Exception("অপশন স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
                    $stmt_option->bind_param("isi", $question_id, $option_text_trimmed, $is_correct);
                    if (!$stmt_option->execute()) throw new Exception("অপশন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_option->error);
                    $stmt_option->close();
                }

                // Insert into question_categories junction table
                if (!empty($selected_category_ids_for_q)) {
                    $sql_q_cat = "INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)";
                    $stmt_q_cat = $conn->prepare($sql_q_cat);
                    if (!$stmt_q_cat) throw new Exception("প্রশ্ন-ক্যাটাগরি স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
                    
                    foreach ($selected_category_ids_for_q as $cat_id_val) {
                        $cat_id_int = intval($cat_id_val);
                        $stmt_q_cat->bind_param("ii", $question_id, $cat_id_int);
                        if (!$stmt_q_cat->execute()) {
                             throw new Exception("প্রশ্ন #" . ($q_idx + 1) . " এর জন্য ক্যাটাগরি লিংক (ID: {$cat_id_int}) সংরক্ষণ করতে সমস্যা: " . $stmt_q_cat->error);
                        }
                    }
                    $stmt_q_cat->close();
                }
            }

            $conn->commit();
            $_SESSION['flash_message'] = "কুইজ \"" . htmlspecialchars($quiz_title) . "\" সফলভাবে তৈরি করা হয়েছে।";
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
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
/* Styles for suggestions and Select2 */
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

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
    white-space: normal !important;
}
.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
    margin-top: 0.3rem !important;
    font-size: 0.85rem; 
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
    background-color: var(--bs-secondary-bg) !important;
    border-color: var(--bs-border-color) !important;
}
body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
    background-color: var(--bs-tertiary-bg) !important;
    border-color: var(--bs-border-color) !important;
    color: var(--bs-body-color) !important;
}
body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
    color: var(--bs-body-color) !important;
}
body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: var(--bs-danger) !important;
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
    
    <?php display_flash_message(); ?>


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
                    <div class="col-md-4 mb-3">
                        <label for="quiz_duration" class="form-label">সময় (মিনিট) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quiz_duration" name="quiz_duration" value="<?php echo isset($_POST['quiz_duration']) ? htmlspecialchars($_POST['quiz_duration']) : '10'; ?>" min="1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="quiz_status" class="form-label">স্ট্যাটাস <span class="text-danger">*</span></label>
                        <select class="form-select" id="quiz_status" name="quiz_status" required>
                            <option value="draft" <?php echo (!isset($_POST['quiz_status']) || (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'draft')) ? 'selected' : ''; ?>>ড্রাফট</option>
                            <option value="upcoming" <?php echo (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'upcoming') ? 'selected' : ''; ?>>আপকামিং</option>
                            <option value="live" <?php echo (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'live') ? 'selected' : ''; ?>>লাইভ</option>
                            <option value="archived" <?php echo (isset($_POST['quiz_status']) && $_POST['quiz_status'] == 'archived') ? 'selected' : ''; ?>>আর্কাইভড</option>
                        </select>
                    </div>
                     <div class="col-md-4 mb-3">
                        <label for="quiz_live_start" class="form-label">লাইভ শুরু (ঐচ্ছিক)</label>
                        <input type="datetime-local" class="form-control" id="quiz_live_start" name="quiz_live_start" value="<?php echo isset($_POST['quiz_live_start']) ? htmlspecialchars($_POST['quiz_live_start']) : ''; ?>">
                    </div>
                </div>
                 <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="quiz_live_end" class="form-label">লাইভ শেষ (ঐচ্ছিক)</label>
                        <input type="datetime-local" class="form-control" id="quiz_live_end" name="quiz_live_end" value="<?php echo isset($_POST['quiz_live_end']) ? htmlspecialchars($_POST['quiz_live_end']) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>

        <div id="questions_container">
            <?php 
            $question_count_for_form = max(1, count($imported_questions)); 
            for ($q_form_idx = 0; $q_form_idx < $question_count_for_form; $q_form_idx++): 
                $current_import_data = $imported_questions[$q_form_idx] ?? null;
                $post_data_q = $_POST['questions'][$q_form_idx] ?? null;
                $current_question_cat_ids = $posted_category_ids_for_questions[$q_form_idx] ?? ($current_import_data['category_ids'] ?? []);
            ?>
            <div class="card question-block mb-3" data-question-index="<?php echo $q_form_idx; ?>">
                 <div class="card-header d-flex justify-content-between align-items-center">
                    <span>প্রশ্ন #<span class="question-number"><?php echo $q_form_idx + 1; ?></span></span>
                    <button type="button" class="btn btn-sm btn-danger remove-question" style="<?php echo $q_form_idx == 0 && $question_count_for_form == 1 ? 'display:none;' : ''; ?>">প্রশ্ন সরান</button>
                </div>
                <div class="card-body">
                    <div class="mb-3 form-control-wrapper">
                        <label for="question_text_<?php echo $q_form_idx; ?>" class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                        <textarea class="form-control question-input-suggest" id="question_text_<?php echo $q_form_idx; ?>" name="questions[<?php echo $q_form_idx; ?>][text]" rows="2" required><?php echo htmlspecialchars($post_data_q['text'] ?? ($current_import_data['text'] ?? '')); ?></textarea>
                        <div class="suggestions-container" id="suggestions_q_<?php echo $q_form_idx; ?>"></div>
                    </div>
                    <div class="mb-3">
                        <label for="question_image_<?php echo $q_form_idx; ?>" class="form-label">প্রশ্ন সম্পর্কিত ছবি (ঐচ্ছিক)</label>
                        <input type="file" class="form-control" id="question_image_<?php echo $q_form_idx; ?>" name="questions[<?php echo $q_form_idx; ?>][image_url]" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="form-text text-muted">অনুমোদিত ছবির ধরণ: JPG, PNG, GIF, WEBP. সর্বোচ্চ সাইজ: 5MB.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_categories_<?php echo $q_form_idx; ?>" class="form-label">ক্যাটাগরি(সমূহ)</label>
                        <select class="form-select question-categories-select" id="question_categories_<?php echo $q_form_idx; ?>" name="questions[<?php echo $q_form_idx; ?>][category_ids][]" multiple="multiple">
                            <?php if (!empty($categories_list)): ?>
                                <?php foreach ($categories_list as $category_item): 
                                    $selected_cat = in_array($category_item['id'], $current_question_cat_ids) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $category_item['id']; ?>" <?php echo $selected_cat; ?>><?php echo htmlspecialchars($category_item['name']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>কোনো ক্যাটাগরি পাওয়া যায়নি।</option>
                            <?php endif; ?>
                        </select>
                        <small class="form-text text-muted">প্রশ্নটি এক বা একাধিক ক্যাটাগরির সাথে যুক্ত করতে পারেন। (ঐচ্ছিক)</small>
                    </div>

                    <div class="options-container mb-3">
                        <label class="form-label">অপশন ও সঠিক উত্তর <span class="text-danger">*</span></label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="questions[<?php echo $q_form_idx; ?>][correct_option]" value="<?php echo $i; ?>" aria-label="সঠিক উত্তর <?php echo $i + 1; ?>" required 
                                <?php 
                                    $is_checked = false;
                                    $correct_opt_val = $post_data_q['correct_option'] ?? ($current_import_data['correct_option'] ?? '0');
                                    if ($correct_opt_val == $i) {
                                        $is_checked = true;
                                    }
                                    if ($is_checked) echo 'checked';
                                ?>>
                            </div>
                            <div class="form-control-wrapper flex-grow-1">
                                <input type="text" class="form-control option-input-suggest" name="questions[<?php echo $q_form_idx; ?>][options][<?php echo $i; ?>]" placeholder="অপশন <?php echo $i + 1; ?>" id="option_text_<?php echo $q_form_idx; ?>_<?php echo $i; ?>" required value="<?php echo htmlspecialchars($post_data_q['options'][$i] ?? ($current_import_data['options'][$i] ?? '')); ?>">
                                <div class="suggestions-container" id="suggestions_q_<?php echo $q_form_idx; ?>_opt_<?php echo $i; ?>"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="mb-3">
                        <label for="question_explanation_<?php echo $q_form_idx; ?>" class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                        <textarea class="form-control" id="question_explanation_<?php echo $q_form_idx; ?>" name="questions[<?php echo $q_form_idx; ?>][explanation]" rows="2"><?php echo htmlspecialchars($post_data_q['explanation'] ?? ($current_import_data['explanation'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <button type="button" class="btn btn-secondary mb-3" id="add_question_btn">আরও প্রশ্ন যোগ করুন (+)</button>
        <hr>
        <button type="submit" class="btn btn-primary btn-lg">কুইজ সংরক্ষণ করুন</button>
         <a href="import_bulk_questions.php" class="btn btn-info btn-lg">বাল্ক ইম্পোর্ট পেইজে যান</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const questionsContainer = document.getElementById('questions_container');
    const addQuestionBtn = document.getElementById('add_question_btn');
    let questionIndex = <?php echo $question_count_for_form; ?>; 

    function initializeSelect2(element) {
        $(element).select2({
            theme: "bootstrap-5",
            placeholder: "ক্যাটাগরি(সমূহ) নির্বাচন করুন (ঐচ্ছিক)", // Placeholder updated
            allowClear: true,
            width: '100%'
        });
    }
    
    document.querySelectorAll('.question-categories-select').forEach(selectElement => {
        initializeSelect2(selectElement);
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
    
    function setupSuggestionListenersForBlock(questionBlock) {
        const qIndex = questionBlock.dataset.questionIndex;

        const questionTextarea = questionBlock.querySelector(`#question_text_${qIndex}`);
        const suggestionsContainerQuestion = questionBlock.querySelector(`#suggestions_q_${qIndex}`);
        if (questionTextarea && suggestionsContainerQuestion) {
            questionTextarea.addEventListener('input', function () { fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this); });
            questionTextarea.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerQuestion.style.display = 'none'; }, 150); });
            questionTextarea.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this);});
        }

        const optionInputs = questionBlock.querySelectorAll('.option-input-suggest');
        optionInputs.forEach((optInput) => {
            const optIdParts = optInput.id.split('_');
            const optIndexSpecific = optIdParts[optIdParts.length -1];
            const suggestionsContainerOption = questionBlock.querySelector(`#suggestions_q_${qIndex}_opt_${optIndexSpecific}`);
            if (suggestionsContainerOption) {
                optInput.addEventListener('input', function () { fetchSuggestions(this.value, 'option', suggestionsContainerOption, this); });
                optInput.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerOption.style.display = 'none'; }, 150); });
                optInput.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'option', suggestionsContainerOption, this);});
            }
        });
    }

    function updateQuestionNumbersAndNames() {
        const questionBlocks = questionsContainer.querySelectorAll('.question-block');
        questionBlocks.forEach((block, index) => {
            block.dataset.questionIndex = index; 
            block.querySelector('.question-number').textContent = index + 1;
            
            block.querySelectorAll('[name^="questions["]').forEach(input => {
                let oldName = input.getAttribute('name');
                let newName = oldName.replace(/questions\[\d+\]/, `questions[${index}]`);
                input.setAttribute('name', newName);

                if (input.id && input.id.match(/_\d+/)) {
                    let oldId = input.id;
                    let newId = oldId.replace(/_\d+/, `_${index}`); 
                    
                    if (oldId.match(/^question_text_/) || oldId.match(/^question_image_/) || oldId.match(/^question_explanation_/) || oldId.match(/^suggestions_q_/) && !oldId.includes('_opt_')) {
                        newId = oldId.replace(/_\d+$/, `_${index}`);
                    } else if (oldId.match(/^question_categories_/)) {
                        newId = `question_categories_${index}`;
                    } else if (oldId.match(/^option_text_\d+_\d+$/)) {
                        newId = oldId.replace(/_\d+_/, `_${index}_`);
                    } else if (oldId.match(/^suggestions_q_\d+_opt_\d+$/)) {
                         newId = oldId.replace(/_\d+_opt_/, `_${index}_opt_`);
                    }
                    input.id = newId;

                    const label = document.querySelector(`label[for="${oldId}"]`);
                    if(label) label.setAttribute('for', newId);
                    
                    // Update suggestion container IDs
                     if(oldId.startsWith('question_text_')) {
                        const oldSuggestionsId = `suggestions_q_${oldId.split('_').pop()}`;
                        const newSuggestionsId = `suggestions_q_${index}`;
                        const sgContainer = document.getElementById(oldSuggestionsId);
                        if(sgContainer) sgContainer.id = newSuggestionsId;
                     } else if (oldId.startsWith('option_text_')) {
                        const parts = oldId.split('_');
                        const oldQSuggestionsId = `suggestions_q_${parts[2]}_opt_${parts[3]}`;
                        const newQSuggestionsId = `suggestions_q_${index}_opt_${parts[3]}`;
                         const sgContainer = document.getElementById(oldQSuggestionsId);
                        if(sgContainer) sgContainer.id = newQSuggestionsId;
                     }
                }
            });
            block.querySelectorAll('input[type="radio"][name^="questions["]').forEach(radio => {
                 let oldName = radio.getAttribute('name');
                 let newName = oldName.replace(/questions\[\d+\]/, `questions[${index}]`);
                 radio.setAttribute('name', newName);
            });

            const removeBtn = block.querySelector('.remove-question');
            if (removeBtn) {
                removeBtn.style.display = questionBlocks.length > 1 ? 'inline-block' : 'none';
            }
            setupSuggestionListenersForBlock(block);
        });
    }
    
    addQuestionBtn.addEventListener('click', function () {
        const firstQuestionBlock = document.querySelector('.question-block');
        if (!firstQuestionBlock) { 
            console.error("Template question block not found!");
            return;
        }
        const newQuestionBlock = firstQuestionBlock.cloneNode(true);
        const newQuestionIndex = questionsContainer.querySelectorAll('.question-block').length;
        newQuestionBlock.dataset.questionIndex = newQuestionIndex;
        
        newQuestionBlock.querySelectorAll('textarea, input[type="text"], input[type="file"]').forEach(input => input.value = '');
        
        const selectElement = newQuestionBlock.querySelector('.question-categories-select');
        if (selectElement) {
            $(selectElement).val(null).trigger('change'); 
            $(selectElement).select2('destroy'); 
            selectElement.id = `question_categories_${newQuestionIndex}`;
        }
        
        const radios = newQuestionBlock.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => radio.checked = false);
        if(radios.length > 0) radios[0].checked = true;

        newQuestionBlock.querySelectorAll('.suggestions-container').forEach(sc => {
            sc.innerHTML = '';
            sc.style.display = 'none';
        });
        
        questionsContainer.appendChild(newQuestionBlock);
        updateQuestionNumbersAndNames(); 

        if (selectElement) {
             initializeSelect2(selectElement);
        }
    });

    questionsContainer.addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-question')) {
            if (questionsContainer.querySelectorAll('.question-block').length > 1) {
                const blockToRemove = event.target.closest('.question-block');
                const selectElement = blockToRemove.querySelector('.question-categories-select');
                if (selectElement && $(selectElement).data('select2')) {
                    $(selectElement).select2('destroy');
                }
                blockToRemove.remove();
                updateQuestionNumbersAndNames();
            } else {
                alert("কুইজে কমপক্ষে একটি প্রশ্ন থাকতে হবে।");
            }
        }
    });

    if (document.getElementById('quiz_description_editor')) {
        const quillDescription = new Quill('#quiz_description_editor', { 
             theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'], 
                    ['clean']
                ]
            }
        });
        <?php if (isset($_POST['quiz_description']) && !empty($errors)): ?>
        quillDescription.root.innerHTML = <?php echo json_encode($_POST['quiz_description']); ?>;
        <?php endif; ?>
        const addQuizFormElem = document.getElementById('addQuizForm');
        if (addQuizFormElem) {
            addQuizFormElem.addEventListener('submit', function() {
                const descriptionHiddenInput = document.getElementById('quiz_description_hidden');
                if (descriptionHiddenInput) {
                    descriptionHiddenInput.value = quillDescription.root.innerHTML;
                     if (quillDescription.getText().trim().length === 0 && quillDescription.root.innerHTML === '<p><br></p>') {
                         descriptionHiddenInput.value = ''; 
                    }
                }
            });
        }
    }
    
    document.querySelectorAll('.question-block').forEach(block => {
        setupSuggestionListenersForBlock(block);
    });
    updateQuestionNumbersAndNames(); 
});
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'includes/footer.php';
?>