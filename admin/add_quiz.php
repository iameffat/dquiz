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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['prepare_questions_from_bulk'])) {
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
                    'category_id' => null, // Initialize category_id for imported questions
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
            if (empty(trim($question_data['text']))) {
                $errors[] = "প্রশ্ন #" . ($q_idx + 1) . ": প্রশ্নের লেখা খালি রাখা যাবে না।";
            }
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
                $q_category_id = isset($question_data['category_id']) && !empty($question_data['category_id']) ? intval($question_data['category_id']) : NULL;
                $q_image_url = NULL; 

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
                        if (strtolower($file_type) == 'image/jpeg' || strtolower($file_type) == 'image/jpg') {
                            if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
                                $source_image = @imagecreatefromjpeg($upload_path);
                                if ($source_image) { imagejpeg($source_image, $upload_path, 75); imagedestroy($source_image); }
                            }
                        } elseif (strtolower($file_type) == 'image/png') {
                             if (function_exists('imagecreatefrompng') && function_exists('imagepng')) {
                                $source_image = @imagecreatefrompng($upload_path);
                                if ($source_image) { imagealphablending($source_image, false); imagesavealpha($source_image, true); imagepng($source_image, $upload_path, 6); imagedestroy($source_image); }
                            }
                        } elseif (strtolower($file_type) == 'image/webp') {
                            if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
                                $source_image = @imagecreatefromwebp($upload_path);
                                if ($source_image) { imagewebp($source_image, $upload_path, 80); imagedestroy($source_image); }
                            }
                        }
                    } else {
                        throw new Exception("প্রশ্ন #" . ($q_idx + 1) . ": ছবি আপলোড করতে ব্যর্থ। সার্ভার পারমিশন চেক করুন।");
                    }
                }

                $sql_question = "INSERT INTO questions (quiz_id, question_text, image_url, explanation, order_number, category_id) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_question = $conn->prepare($sql_question);
                if (!$stmt_question) throw new Exception("প্রশ্ন স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);

                $order_num = $q_idx + 1; 
                $stmt_question->bind_param("isssii", $quiz_id, $q_text, $q_image_url, $q_explanation, $order_num, $q_category_id);
                
                if (!$stmt_question->execute()) {
                    if($q_image_url && file_exists(QUESTION_IMAGE_UPLOAD_DIR . basename($q_image_url))) {
                        unlink(QUESTION_IMAGE_UPLOAD_DIR . basename($q_image_url));
                    }
                    throw new Exception("প্রশ্ন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_question->error);
                }
                $question_id = $stmt_question->insert_id;
                $stmt_question->close();

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
                    
                    if (!$stmt_option->execute()) {
                        throw new Exception("অপশন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_option->error);
                    }
                    $stmt_option->close();
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
<style>
.suggestions-container {
    border: 1px solid var(--bs-border-color); 
    border-top: none; 
    max-height: 150px;
    overflow-y: auto;
    background-color: var(--bs-body-bg); 
    position: absolute; 
    z-index: 1050; 
    width: 100%; 
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); 
    display: none; 
    border-radius: 0 0 var(--bs-border-radius); 
}
.suggestion-item {
    padding: 0.5rem 0.75rem; 
    cursor: pointer;
    font-size: 0.9rem; 
    color: var(--bs-body-color); 
}
.suggestion-item:hover {
    background-color: var(--bs-tertiary-bg); 
}
.form-control-wrapper {
    position: relative; 
}
.input-group .form-control-wrapper {
    display: flex;
    flex-direction: column;
    flex-grow: 1; 
}
.input-group .form-control-wrapper .suggestions-container {
     width: 100%; 
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
            $question_count_for_form = max(1, count($imported_questions)); // Ensure at least one block if no import
            for ($q_form_idx = 0; $q_form_idx < $question_count_for_form; $q_form_idx++): 
                $current_import_data = $imported_questions[$q_form_idx] ?? null;
                $post_data_q = $_POST['questions'][$q_form_idx] ?? null;
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
                        <label for="question_category_<?php echo $q_form_idx; ?>" class="form-label">ক্যাটাগরি (ঐচ্ছিক)</label>
                        <select class="form-select" id="question_category_<?php echo $q_form_idx; ?>" name="questions[<?php echo $q_form_idx; ?>][category_id]">
                            <option value="">ক্যাটাগরি নির্বাচন করুন</option>
                            <?php if (!empty($categories_list)): ?>
                                <?php foreach ($categories_list as $category_item): 
                                    $selected_cat = '';
                                    if (isset($post_data_q['category_id']) && $post_data_q['category_id'] == $category_item['id']) {
                                        $selected_cat = 'selected';
                                    } elseif (isset($current_import_data['category_id']) && $current_import_data['category_id'] == $category_item['id']) {
                                        $selected_cat = 'selected';
                                    }
                                ?>
                                    <option value="<?php echo $category_item['id']; ?>" <?php echo $selected_cat; ?>><?php echo htmlspecialchars($category_item['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="options-container mb-3">
                        <label class="form-label">অপশন <span class="text-danger">*</span></label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="questions[<?php echo $q_form_idx; ?>][correct_option]" value="<?php echo $i; ?>" aria-label="সঠিক উত্তর <?php echo $i + 1; ?>" required 
                                <?php 
                                    $is_checked = false;
                                    $correct_opt_val = $post_data_q['correct_option'] ?? ($current_import_data['correct_option'] ?? '0'); // Default to '0' if nothing set
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const questionsContainer = document.getElementById('questions_container');
    const addQuestionBtn = document.getElementById('add_question_btn');
    let questionIndex = <?php echo $question_count_for_form; ?>; // Start index from existing blocks

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
        }, 350);
    }

    function displaySuggestions(suggestions, containerEl, inputFieldEl) {
        containerEl.innerHTML = '';
        if (suggestions && suggestions.length > 0) {
            suggestions.forEach(suggestionText => {
                const item = document.createElement('div');
                item.classList.add('suggestion-item');
                item.textContent = suggestionText;
                item.addEventListener('mousedown', function (e) { // Use mousedown to fire before blur
                    e.preventDefault(); // Prevent blur from hiding suggestions before click registers
                    inputFieldEl.value = suggestionText;
                    containerEl.innerHTML = '';
                    containerEl.style.display = 'none';
                    inputFieldEl.focus(); // Re-focus the input field
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
            questionTextarea.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerQuestion.style.display = 'none'; }, 150); }); // Delay to allow click on suggestion
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
            block.dataset.questionIndex = index; // Update the data-attribute itself
            block.querySelector('.question-number').textContent = index + 1;
            
            // Update names and IDs for all inputs, textareas, selects within this block
            block.querySelectorAll('[name^="questions["]').forEach(input => {
                let oldName = input.getAttribute('name');
                let newName = oldName.replace(/questions\[\d+\]/, `questions[${index}]`);
                input.setAttribute('name', newName);
                // Update IDs if they follow a pattern like question_text_0, option_text_0_1 etc.
                if (input.id && input.id.match(/_\d+(_\d+)?$/)) {
                    let oldId = input.id;
                    let newId = oldId.replace(/_\d+(_\d+)?$/, `_${index}$1`); // $1 captures (_optIdx) part if present
                     if(oldId.startsWith('question_text_') || oldId.startsWith('question_image_') || oldId.startsWith('question_explanation_') || oldId.startsWith('question_category_')) {
                        newId = oldId.replace(/_\d+$/, `_${index}`);
                    } else if (oldId.match(/^option_text_\d+_\d+$/)) {
                        newId = oldId.replace(/_\d+_/, `_${index}_`);
                    } else if (oldId.match(/^suggestions_q_\d+_opt_\d+$/)) {
                         newId = oldId.replace(/_\d+_opt_/, `_${index}_opt_`);
                    } else if (oldId.match(/^suggestions_q_\d+$/) && !oldId.includes('_opt_')) {
                         newId = oldId.replace(/_\d+$/, `_${index}`);
                    }
                    input.id = newId;

                    const label = block.querySelector(`label[for="${oldId}"]`);
                    if(label) label.setAttribute('for', newId);
                }
            });
            // Special handling for radio button group name
            block.querySelectorAll('input[type="radio"][name^="questions["]').forEach(radio => {
                 let oldName = radio.getAttribute('name');
                 let newName = oldName.replace(/questions\[\d+\]/, `questions[${index}]`);
                 radio.setAttribute('name', newName);
            });

            const removeBtn = block.querySelector('.remove-question');
            if (removeBtn) {
                removeBtn.style.display = questionBlocks.length > 1 ? 'inline-block' : 'none';
            }
            // Re-setup suggestion listeners for the updated/new block
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
        
        newQuestionBlock.querySelectorAll('textarea, input[type="text"], input[type="file"]').forEach(input => input.value = '');
        newQuestionBlock.querySelectorAll('select').forEach(select => select.selectedIndex = 0); // Reset category
        const radios = newQuestionBlock.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => radio.checked = false);
        if(radios.length > 0) radios[0].checked = true; // Default check first radio

        // Clear any existing suggestions in the cloned block
        newQuestionBlock.querySelectorAll('.suggestions-container').forEach(sc => {
            sc.innerHTML = '';
            sc.style.display = 'none';
        });
        
        questionsContainer.appendChild(newQuestionBlock);
        questionIndex = questionsContainer.querySelectorAll('.question-block').length -1; // Get the actual new index
        newQuestionBlock.dataset.questionIndex = questionIndex; // Set it before updating
        updateQuestionNumbersAndNames(); 
    });

    questionsContainer.addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-question')) {
            if (questionsContainer.querySelectorAll('.question-block').length > 1) {
                event.target.closest('.question-block').remove();
                updateQuestionNumbersAndNames();
            } else {
                alert("কুইজে কমপক্ষে একটি প্রশ্ন থাকতে হবে।");
            }
        }
    });

    // Quill editor for quiz description
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
        // Preserve content if form reloads with an error and content was posted
        <?php if (isset($_POST['quiz_description']) && !empty($errors)): ?>
        quillDescription.root.innerHTML = <?php echo json_encode($_POST['quiz_description']); ?>;
        <?php endif; ?>

        const addQuizForm = document.getElementById('addQuizForm');
        if (addQuizForm) {
            addQuizForm.addEventListener('submit', function() {
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
    
    // Initial setup for existing blocks (especially if populated by PHP e.g. from bulk import)
    document.querySelectorAll('.question-block').forEach(block => {
        setupSuggestionListenersForBlock(block);
    });
    updateQuestionNumbersAndNames(); // Ensure correct numbering and attributes on page load
});
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'includes/footer.php';
?>