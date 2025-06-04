<?php
$page_title = "কুইজ এডিট করুন";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

define('QUESTION_IMAGE_UPLOAD_DIR_EDIT', '../uploads/question_images/');
define('QUESTION_IMAGE_BASE_URL_EDIT', '../'); // Base URL to access images from admin folder

$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$quiz = null;
$questions_data = [];
$errors = [];

if ($quiz_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ কুইজ ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_quizzes.php");
    exit;
}

// Fetch categories for dropdown
$categories_list = [];
$sql_cat_list = "SELECT id, name FROM categories ORDER BY name ASC";
$result_cat_list = $conn->query($sql_cat_list);
if ($result_cat_list) {
    while ($cat_row = $result_cat_list->fetch_assoc()) {
        $categories_list[] = $cat_row;
    }
}


if (isset($_GET['action']) && $_GET['action'] == 'delete_question' && isset($_GET['question_id'])) {
    $question_id_to_delete = intval($_GET['question_id']);
    
    $conn->begin_transaction();
    try {
        $sql_get_image = "SELECT image_url FROM questions WHERE id = ? AND quiz_id = ?";
        $stmt_get_image = $conn->prepare($sql_get_image);
        $stmt_get_image->bind_param("ii", $question_id_to_delete, $quiz_id);
        $stmt_get_image->execute();
        $image_result = $stmt_get_image->get_result();
        $image_row = $image_result->fetch_assoc();
        $stmt_get_image->close();

        if (!$image_row && $image_result->num_rows === 0) { 
            throw new Exception("প্রশ্নটি এই কুইজের নয় অথবা খুঁজে পাওয়া যায়নি।");
        }

        $sql_delete_options = "DELETE FROM options WHERE question_id = ?";
        $stmt_delete_options = $conn->prepare($sql_delete_options);
        $stmt_delete_options->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_options->execute()) throw new Exception("অপশন ডিলিট করতে সমস্যা: " . $stmt_delete_options->error);
        $stmt_delete_options->close();

        $sql_delete_q = "DELETE FROM questions WHERE id = ?";
        $stmt_delete_q = $conn->prepare($sql_delete_q);
        $stmt_delete_q->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_q->execute()) throw new Exception("প্রশ্ন ডিলিট করতে সমস্যা: " . $stmt_delete_q->error);
        $stmt_delete_q->close();
        
        if ($image_row && !empty($image_row['image_url'])) {
            $image_file_path_relative = $image_row['image_url'];
            // Construct absolute path carefully: QUESTION_IMAGE_UPLOAD_DIR_EDIT is relative to admin folder
            $image_file_path_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT . basename($image_file_path_relative));

            // Security check: ensure the path is within the intended upload directory
            if ($image_file_path_actual && strpos($image_file_path_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT)) === 0 && file_exists($image_file_path_actual)) {
                 unlink($image_file_path_actual);
            }
        }
        
        $conn->commit();
        $_SESSION['flash_message'] = "প্রশ্ন (ID: {$question_id_to_delete}) সফলভাবে ডিলিট করা হয়েছে।";
        $_SESSION['flash_message_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = "প্রশ্ন ডিলিট করার সময় ত্রুটি: " . $e->getMessage();
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: edit_quiz.php?id=" . $quiz_id);
    exit;
}

$sql_quiz = "SELECT * FROM quizzes WHERE id = ?";
if ($stmt_quiz_load = $conn->prepare($sql_quiz)) {
    $stmt_quiz_load->bind_param("i", $quiz_id);
    $stmt_quiz_load->execute();
    $result_quiz = $stmt_quiz_load->get_result();
    if ($result_quiz->num_rows === 1) {
        $quiz = $result_quiz->fetch_assoc();
        $page_title = "এডিট: " . htmlspecialchars($quiz['title']);
    } else {
        $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id}) খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: manage_quizzes.php");
        exit;
    }
    $stmt_quiz_load->close();
} else {
    // Handle error if statement preparation fails
    error_log("Failed to prepare quiz load statement: " . $conn->error);
    $_SESSION['flash_message'] = "ডাটাবেস ত্রুটি: কুইজের তথ্য লোড করা যায়নি।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_quizzes.php");
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_full_quiz'])) {
    $conn->begin_transaction();
    try {
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

        if (empty($errors)) {
            $sql_update_meta = "UPDATE quizzes SET title = ?, description = ?, duration_minutes = ?, status = ?, live_start_datetime = ?, live_end_datetime = ?, updated_at = NOW() WHERE id = ?";
            $stmt_update_meta = $conn->prepare($sql_update_meta);
            $stmt_update_meta->bind_param("ssisssi", $quiz_title, $quiz_description, $quiz_duration, $quiz_status, $quiz_live_start, $quiz_live_end, $quiz_id);
            if (!$stmt_update_meta->execute()) throw new Exception("কুইজের বিবরণ আপডেট করতে সমস্যা: " . $stmt_update_meta->error);
            $stmt_update_meta->close();
        } else {
             throw new Exception(implode("<br>", $errors));
        }

        // Handle existing questions
        if (isset($_POST['existing_questions'])) {
            foreach ($_POST['existing_questions'] as $q_id_form => $q_data) {
                $q_id = intval($q_id_form); // Ensure it's an integer
                $q_text = trim($q_data['text']);
                $q_explanation = isset($q_data['explanation']) ? trim($q_data['explanation']) : NULL;
                $q_order = isset($q_data['order_number']) ? intval($q_data['order_number']) : 0;
                $q_category_id = isset($q_data['category_id']) && !empty($q_data['category_id']) ? intval($q_data['category_id']) : NULL;
                $current_image_url = isset($q_data['current_image_url']) ? $q_data['current_image_url'] : null;
                $new_image_url_for_db = $current_image_url; 

                if (empty($q_text)) { $errors[] = "বিদ্যমান প্রশ্ন (ID: $q_id) এর লেখা খালি রাখা যাবে না।"; continue; }

                if (isset($q_data['remove_image']) && $q_data['remove_image'] == '1') {
                    if (!empty($current_image_url)) {
                        $image_path_to_delete_relative = $current_image_url;
                        $image_path_to_delete_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT . basename($image_path_to_delete_relative));
                        if ($image_path_to_delete_actual && strpos($image_path_to_delete_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT)) === 0 && file_exists($image_path_to_delete_actual)) {
                             unlink($image_path_to_delete_actual);
                        }
                    }
                    $new_image_url_for_db = NULL;
                }

                if (isset($_FILES['existing_questions_files']['name'][$q_id]['image_url']) && $_FILES['existing_questions_files']['error'][$q_id]['image_url'] == UPLOAD_ERR_OK) {
                    $file_tmp_name = $_FILES['existing_questions_files']['tmp_name'][$q_id]['image_url'];
                    $file_name = basename($_FILES['existing_questions_files']['name'][$q_id]['image_url']);
                    $file_type = $_FILES['existing_questions_files']['type'][$q_id]['image_url'];
                    $file_size = $_FILES['existing_questions_files']['size'][$q_id]['image_url'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_file_size = 5 * 1024 * 1024; 

                    if (!in_array(strtolower($file_type), $allowed_types)) {
                         throw new Exception("প্রশ্ন (ID: $q_id): অনুমোদিত ছবির ধরণ JPEG, PNG, WEBP বা GIF.");
                    }
                    if ($file_size > $max_file_size) {
                        throw new Exception("প্রশ্ন (ID: $q_id): ছবির ফাইল সাইজ 5MB এর বেশি হতে পারবে না।");
                    }
                    
                    if (!empty($current_image_url) && $new_image_url_for_db !== NULL) { // Only delete if not already marked for removal
                        $image_path_to_delete_relative = $current_image_url;
                        $image_path_to_delete_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT . basename($image_path_to_delete_relative));
                        if ($image_path_to_delete_actual && strpos($image_path_to_delete_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_EDIT)) === 0 && file_exists($image_path_to_delete_actual)) {
                            unlink($image_path_to_delete_actual);
                        }
                    }
                    
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $uploaded_file_name = "q_img_" . $quiz_id . "_" . $q_id . "_" . time() . "." . $file_ext;
                    $upload_path = QUESTION_IMAGE_UPLOAD_DIR_EDIT . $uploaded_file_name;

                    if (move_uploaded_file($file_tmp_name, $upload_path)) {
                        $new_image_url_for_db = 'uploads/question_images/' . $uploaded_file_name;
                        if (strtolower($file_type) == 'image/jpeg' || strtolower($file_type) == 'image/jpg') { if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) { $source = @imagecreatefromjpeg($upload_path); if($source) { imagejpeg($source, $upload_path, 75); imagedestroy($source); }}}
                        elseif (strtolower($file_type) == 'image/png') { if(function_exists('imagecreatefrompng') && function_exists('imagepng')){ $source = @imagecreatefrompng($upload_path); if($source) { imagealphablending($source, false); imagesavealpha($source, true); imagepng($source, $upload_path, 6); imagedestroy($source); }}}
                        elseif (strtolower($file_type) == 'image/webp') { if(function_exists('imagecreatefromwebp') && function_exists('imagewebp')){ $source = @imagecreatefromwebp($upload_path); if($source) { imagewebp($source, $upload_path, 80); imagedestroy($source); }}}
                    } else {
                        throw new Exception("প্রশ্ন (ID: $q_id): নতুন ছবি আপলোড করতে ব্যর্থ।");
                    }
                }
                
                $sql_update_q = "UPDATE questions SET question_text = ?, image_url = ?, explanation = ?, order_number = ?, category_id = ? WHERE id = ? AND quiz_id = ?";
                $stmt_update_q = $conn->prepare($sql_update_q);
                $stmt_update_q->bind_param("sssiiii", $q_text, $new_image_url_for_db, $q_explanation, $q_order, $q_category_id, $q_id, $quiz_id);
                if (!$stmt_update_q->execute()) throw new Exception("বিদ্যমান প্রশ্ন (ID: $q_id) আপডেট করতে সমস্যা: " . $stmt_update_q->error);
                $stmt_update_q->close();

                if (isset($q_data['options']) && isset($q_data['correct_option'])) {
                    $correct_option_id_from_post = intval($q_data['correct_option']); 
                    foreach ($q_data['options'] as $opt_id_form => $opt_text_val) {
                        $opt_id = intval($opt_id_form);
                        $opt_text = trim($opt_text_val);
                        if (empty($opt_text)) { $errors[] = "প্রশ্ন (ID: $q_id) এর অপশন (ID: $opt_id) খালি রাখা যাবে না।"; continue; }
                        $is_correct = ($opt_id == $correct_option_id_from_post) ? 1 : 0;
                        $sql_update_opt = "UPDATE options SET option_text = ?, is_correct = ? WHERE id = ? AND question_id = ?";
                        $stmt_update_opt = $conn->prepare($sql_update_opt);
                        $stmt_update_opt->bind_param("siii", $opt_text, $is_correct, $opt_id, $q_id);
                        if (!$stmt_update_opt->execute()) throw new Exception("অপশন (ID: $opt_id) আপডেট করতে সমস্যা: " . $stmt_update_opt->error);
                        $stmt_update_opt->close();
                    }
                } else { $errors[] = "প্রশ্ন (ID: $q_id) এর জন্য অপশন বা সঠিক উত্তর পাওয়া যায়নি।"; }
            }
        }
        
        // Handle new questions
         if (isset($_POST['new_questions'])) {
            foreach ($_POST['new_questions'] as $nq_idx => $nq_data) {
                $nq_text = trim($nq_data['text']);
                if (empty($nq_text)) continue; 

                $nq_explanation = isset($nq_data['explanation']) ? trim($nq_data['explanation']) : NULL;
                $nq_order = isset($nq_data['order_number']) ? intval($nq_data['order_number']) : 0;
                $nq_category_id = isset($nq_data['category_id']) && !empty($nq_data['category_id']) ? intval($nq_data['category_id']) : NULL;
                $nq_image_url_for_db = NULL;

                if (empty($nq_data['options']) || count($nq_data['options']) < 2) { $errors[] = "নতুন প্রশ্ন #" . ($nq_idx + 1) . " এর জন্য কমপক্ষে ২টি অপশন দিন।"; continue; }
                if (!isset($nq_data['correct_option_new']) || $nq_data['correct_option_new'] === '') { $errors[] = "নতুন প্রশ্ন #" . ($nq_idx + 1) . " এর সঠিক উত্তর নির্বাচন করুন।"; continue; }

                if (isset($_FILES['new_questions_files']['name'][$nq_idx]['image_url']) && $_FILES['new_questions_files']['error'][$nq_idx]['image_url'] == UPLOAD_ERR_OK) {
                    $file_tmp_name = $_FILES['new_questions_files']['tmp_name'][$nq_idx]['image_url'];
                    $file_name = basename($_FILES['new_questions_files']['name'][$nq_idx]['image_url']);
                    $file_type = $_FILES['new_questions_files']['type'][$nq_idx]['image_url'];
                    $file_size = $_FILES['new_questions_files']['size'][$nq_idx]['image_url'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_file_size = 5 * 1024 * 1024; 

                    if (!in_array(strtolower($file_type), $allowed_types)) {
                        throw new Exception("নতুন প্রশ্ন #" . ($nq_idx + 1) . ": অনুমোদিত ছবির ধরণ JPEG, PNG, WEBP বা GIF.");
                    }
                    if ($file_size > $max_file_size) {
                         throw new Exception("নতুন প্রশ্ন #" . ($nq_idx + 1) . ": ছবির ফাইল সাইজ 5MB এর বেশি হতে পারবে না।");
                    }

                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_q_file_name = "q_img_" . $quiz_id . "_new_" . time() . "_" . uniqid('', true) . "." . $file_ext;
                    $upload_path = QUESTION_IMAGE_UPLOAD_DIR_EDIT . $new_q_file_name;

                    if (move_uploaded_file($file_tmp_name, $upload_path)) {
                        $nq_image_url_for_db = 'uploads/question_images/' . $new_q_file_name;
                        if (strtolower($file_type) == 'image/jpeg' || strtolower($file_type) == 'image/jpg') { if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) { $source = @imagecreatefromjpeg($upload_path); if($source) { imagejpeg($source, $upload_path, 75); imagedestroy($source); }}}
                        elseif (strtolower($file_type) == 'image/png') { if(function_exists('imagecreatefrompng') && function_exists('imagepng')){ $source = @imagecreatefrompng($upload_path); if($source) { imagealphablending($source, false); imagesavealpha($source, true); imagepng($source, $upload_path, 6); imagedestroy($source); }}}
                        elseif (strtolower($file_type) == 'image/webp') { if(function_exists('imagecreatefromwebp') && function_exists('imagewebp')){ $source = @imagecreatefromwebp($upload_path); if($source) { imagewebp($source, $upload_path, 80); imagedestroy($source); }}}
                    } else {
                        throw new Exception("নতুন প্রশ্ন #" . ($nq_idx + 1) . ": ছবি আপলোড করতে ব্যর্থ।");
                    }
                }

                $new_order_num = $nq_order;
                if ($nq_order == 0) { 
                    $sql_max_order = "SELECT MAX(order_number) as max_o FROM questions WHERE quiz_id = ?";
                    $stmt_max_order = $conn->prepare($sql_max_order);
                    $stmt_max_order->bind_param("i", $quiz_id);
                    $stmt_max_order->execute();
                    $max_o_res = $stmt_max_order->get_result()->fetch_assoc();
                    $new_order_num = ($max_o_res && $max_o_res['max_o'] !== null) ? $max_o_res['max_o'] + 1 : 1;
                    $stmt_max_order->close();
                }

                $sql_insert_nq = "INSERT INTO questions (quiz_id, question_text, image_url, explanation, order_number, category_id) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert_nq = $conn->prepare($sql_insert_nq);
                $stmt_insert_nq->bind_param("isssii", $quiz_id, $nq_text, $nq_image_url_for_db, $nq_explanation, $new_order_num, $nq_category_id);
                if (!$stmt_insert_nq->execute()) throw new Exception("নতুন প্রশ্ন যোগ করতে সমস্যা: " . $stmt_insert_nq->error);
                $new_question_id = $stmt_insert_nq->insert_id;
                $stmt_insert_nq->close();

                $correct_new_opt_idx = intval($nq_data['correct_option_new']); 
                foreach ($nq_data['options'] as $nopt_idx => $nopt_text_val) {
                    $nopt_text = trim($nopt_text_val);
                    if (empty($nopt_text)) continue;
                    $is_correct_new = ($nopt_idx == $correct_new_opt_idx) ? 1 : 0;
                    $sql_insert_nopt = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                    $stmt_insert_nopt = $conn->prepare($sql_insert_nopt);
                    $stmt_insert_nopt->bind_param("isi", $new_question_id, $nopt_text, $is_correct_new);
                    if (!$stmt_insert_nopt->execute()) throw new Exception("নতুন অপশন যোগ করতে সমস্যা: " . $stmt_insert_nopt->error);
                    $stmt_insert_nopt->close();
                }
            }
        }

        if (!empty($errors)) throw new Exception("কিছু তথ্যগত ত্রুটি রয়েছে: <br>" . implode("<br>", $errors)); 

        $conn->commit();
        $_SESSION['flash_message'] = "কুইজ সফলভাবে আপডেট করা হয়েছে।";
        $_SESSION['flash_message_type'] = "success";
        header("Location: edit_quiz.php?id=" . $quiz_id); 
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        // Add error to errors array to be displayed on the page
        // The $errors array from the beginning of the script will be used
        // If $e->getMessage() contains HTML, ensure it's handled safely or stripped.
        $current_errors_str = empty($errors) ? "" : implode("<br>", $errors) . "<br>";
        $_SESSION['flash_message'] = "<strong>একটি গুরুতর ত্রুটি ঘটেছে:</strong><br>" . $current_errors_str . $e->getMessage();
        $_SESSION['flash_message_type'] = "danger";
        
        // Preserve form data on error by repopulating $quiz array if it's from quiz meta
        $quiz['title'] = $_POST['quiz_title'] ?? $quiz['title'];
        $quiz['description'] = $_POST['quiz_description'] ?? $quiz['description'];
        $quiz['duration_minutes'] = $_POST['quiz_duration'] ?? $quiz['duration_minutes'];
        $quiz['status'] = $_POST['quiz_status'] ?? $quiz['status'];
        $quiz['live_start_datetime'] = $_POST['quiz_live_start'] ?? $quiz['live_start_datetime'];
        $quiz['live_end_datetime'] = $_POST['quiz_live_end'] ?? $quiz['live_end_datetime'];
        // Repopulating questions and options would be more complex here and might be better handled
        // by letting the page re-fetch or by passing the $_POST data back to the form fields,
        // which the current HTML structure with PHP echo for values attempts to do.
    }
}

// Fetch questions and options for the quiz for display
$questions_data = []; // Reset for fresh load
$sql_questions_load = "SELECT id, question_text, image_url, explanation, order_number, category_id FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC";
if ($stmt_q_load = $conn->prepare($sql_questions_load)) {
    $stmt_q_load->bind_param("i", $quiz_id);
    $stmt_q_load->execute();
    $result_q = $stmt_q_load->get_result();
    while ($question_row = $result_q->fetch_assoc()) {
        $current_question = $question_row;
        $current_question['options'] = [];
        
        $sql_options_load = "SELECT * FROM options WHERE question_id = ? ORDER BY id ASC"; 
        if ($stmt_o_load = $conn->prepare($sql_options_load)) {
            $stmt_o_load->bind_param("i", $question_row['id']);
            $stmt_o_load->execute();
            $result_o = $stmt_o_load->get_result();
            while ($option_row = $result_o->fetch_assoc()) {
                $current_question['options'][] = $option_row;
            }
            $stmt_o_load->close();
        }
        $questions_data[] = $current_question;
    }
    $stmt_q_load->close();
} else {
    // Handle error if statement preparation fails
    error_log("Failed to prepare questions load statement: " . $conn->error);
    $errors[] = "ডাটাবেস ত্রুটি: প্রশ্নাবলী লোড করা যায়নি।";
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
    <h1 class="mt-4 mb-3">কুইজ এডিট করুন: <?php echo htmlspecialchars($quiz['title']); ?> (ID: <?php echo $quiz_id; ?>)</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong> অনুগ্রহ করে নিচের সমস্যাগুলো সমাধান করুন:
        <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php display_flash_message('flash_message', 'flash_message_type'); ?>

    <form action="edit_quiz.php?id=<?php echo $quiz_id; ?>" method="post" id="editQuizForm" enctype="multipart/form-data">
        <div class="card mb-4">
            <div class="card-header">কুইজের বিবরণ (এডিট)</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="quiz_title" class="form-label">শিরোনাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="quiz_title" name="quiz_title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="quiz_description_editor_edit" class="form-label">সংক্ষিপ্ত বর্ণনা</label>
                    <div id="quiz_description_editor_edit"><?php echo $quiz['description']; ?></div>
                    <input type="hidden" name="quiz_description" id="quiz_description_hidden_edit">
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="quiz_duration" class="form-label">সময় (মিনিট) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quiz_duration" name="quiz_duration" value="<?php echo htmlspecialchars($quiz['duration_minutes']); ?>" min="1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="quiz_status" class="form-label">স্ট্যাটাস <span class="text-danger">*</span></label>
                        <select class="form-select" id="quiz_status" name="quiz_status" required>
                            <option value="draft" <?php echo ($quiz['status'] == 'draft') ? 'selected' : ''; ?>>ড্রাফট</option>
                            <option value="upcoming" <?php echo ($quiz['status'] == 'upcoming') ? 'selected' : ''; ?>>আপকামিং</option>
                            <option value="live" <?php echo ($quiz['status'] == 'live') ? 'selected' : ''; ?>>লাইভ</option>
                            <option value="archived" <?php echo ($quiz['status'] == 'archived') ? 'selected' : ''; ?>>আর্কাইভড</option>
                        </select>
                    </div>
                     <div class="col-md-4 mb-3">
                        <label for="quiz_live_start" class="form-label">লাইভ শুরু (ঐচ্ছিক)</label>
                        <input type="datetime-local" class="form-control" id="quiz_live_start" name="quiz_live_start" value="<?php echo !empty($quiz['live_start_datetime']) ? date('Y-m-d\TH:i', strtotime($quiz['live_start_datetime'])) : ''; ?>">
                    </div>
                </div>
                 <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="quiz_live_end" class="form-label">লাইভ শেষ (ঐচ্ছিক)</label>
                        <input type="datetime-local" class="form-control" id="quiz_live_end" name="quiz_live_end" value="<?php echo !empty($quiz['live_end_datetime']) ? date('Y-m-d\TH:i', strtotime($quiz['live_end_datetime'])) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>

        <h3 class="mt-4">বিদ্যমান প্রশ্নাবলী (এডিট)</h3>
        <div id="existing_questions_container">
            <?php if (!empty($questions_data)): ?>
                <?php foreach($questions_data as $q_idx => $q_item): ?>
                <div class="card question-block-existing mb-3" id="existing_question_<?php echo $q_item['id']; ?>" data-question-id="<?php echo $q_item['id']; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>বিদ্যমান প্রশ্ন #<span class="existing-question-number"><?php echo htmlspecialchars($q_item['order_number']); ?></span> (ID: <?php echo $q_item['id']; ?>)</span>
                        <a href="edit_quiz.php?id=<?php echo $quiz_id; ?>&action=delete_question&question_id=<?php echo $q_item['id']; ?>" 
                           class="btn btn-sm btn-danger remove-existing-question-btn" 
                           onclick="return confirm('আপনি কি নিশ্চিতভাবে এই প্রশ্নটি এবং এর অপশনগুলো ডিলিট করতে চান? যদি ছবির সাথে যুক্ত থাকে, সেটিও ডিলিট হয়ে যাবে।');">প্রশ্ন সরান</a>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="existing_questions[<?php echo $q_item['id']; ?>][id]" value="<?php echo $q_item['id']; ?>">
                        <input type="hidden" name="existing_questions[<?php echo $q_item['id']; ?>][current_image_url]" value="<?php echo htmlspecialchars($q_item['image_url']); ?>">

                        <div class="row">
                            <div class="col-md-9 mb-3 form-control-wrapper">
                                <label for="existing_q_text_<?php echo $q_item['id']; ?>" class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                                <textarea class="form-control question-input-suggest" name="existing_questions[<?php echo $q_item['id']; ?>][text]" id="existing_q_text_<?php echo $q_item['id']; ?>" rows="2" required><?php echo htmlspecialchars($q_item['question_text']); ?></textarea>
                                <div class="suggestions-container" id="suggestions_ex_q_<?php echo $q_item['id']; ?>"></div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="existing_q_order_<?php echo $q_item['id']; ?>" class="form-label">প্রশ্নের ক্রম</label>
                                <input type="number" class="form-control" name="existing_questions[<?php echo $q_item['id']; ?>][order_number]" id="existing_q_order_<?php echo $q_item['id']; ?>" value="<?php echo htmlspecialchars($q_item['order_number']); ?>" min="1">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="existing_q_img_<?php echo $q_item['id']; ?>" class="form-label">প্রশ্ন সম্পর্কিত ছবি (ঐচ্ছিক)</label>
                            <?php if (!empty($q_item['image_url'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo QUESTION_IMAGE_BASE_URL_EDIT . htmlspecialchars($q_item['image_url']); ?>" alt="Question Image" class="admin-question-image-preview">
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="existing_questions[<?php echo $q_item['id']; ?>][remove_image]" value="1" id="remove_image_<?php echo $q_item['id']; ?>">
                                        <label class="form-check-label" for="remove_image_<?php echo $q_item['id']; ?>">এই ছবিটি মুছে ফেলুন</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="existing_questions_files[<?php echo $q_item['id']; ?>][image_url]" id="existing_q_img_<?php echo $q_item['id']; ?>" accept="image/jpeg,image/png,image/gif,image/webp">
                             <small class="form-text text-muted">নতুন ছবি আপলোড করলে আগেরটি (যদি থাকে) প্রতিস্থাপিত হবে। ছবি মুছতে উপরের চেকবক্সটি সিলেক্ট করুন। সর্বোচ্চ সাইজ: 5MB.</small>
                        </div>
                        <div class="mb-3">
                            <label for="existing_q_category_<?php echo $q_item['id']; ?>" class="form-label">ক্যাটাগরি (ঐচ্ছিক)</label>
                            <select class="form-select" name="existing_questions[<?php echo $q_item['id']; ?>][category_id]" id="existing_q_category_<?php echo $q_item['id']; ?>">
                                <option value="">ক্যাটাগরি নির্বাচন করুন</option>
                                <?php foreach ($categories_list as $category_item): ?>
                                    <option value="<?php echo $category_item['id']; ?>" <?php echo ($q_item['category_id'] == $category_item['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category_item['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="options-container mb-3">
                             <label class="form-label">অপশন ও সঠিক উত্তর <span class="text-danger">*</span></label>
                            <?php 
                            $radio_group_name = "existing_questions[{$q_item['id']}][correct_option]";
                            foreach($q_item['options'] as $opt_idx => $opt_item): ?>
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0" type="radio" 
                                           name="<?php echo $radio_group_name; ?>" 
                                           value="<?php echo $opt_item['id']; ?>" 
                                           aria-label="সঠিক উত্তর <?php echo $opt_idx + 1; ?>" 
                                           <?php echo ($opt_item['is_correct'] == 1) ? 'checked' : ''; ?> required>
                                </div>
                                <div class="form-control-wrapper flex-grow-1">
                                    <input type="text" class="form-control option-input-suggest" 
                                        name="existing_questions[<?php echo $q_item['id']; ?>][options][<?php echo $opt_item['id']; ?>]" 
                                        id="existing_opt_text_<?php echo $q_item['id']; ?>_<?php echo $opt_item['id']; ?>"
                                        value="<?php echo htmlspecialchars($opt_item['option_text']); ?>" 
                                        placeholder="অপশন <?php echo $opt_idx + 1; ?>" required>
                                    <div class="suggestions-container" id="suggestions_ex_q_<?php echo $q_item['id']; ?>_opt_<?php echo $opt_item['id']; ?>"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mb-3">
                            <label for="existing_q_explanation_<?php echo $q_item['id']; ?>" class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                            <textarea class="form-control" name="existing_questions[<?php echo $q_item['id']; ?>][explanation]" id="existing_q_explanation_<?php echo $q_item['id']; ?>" rows="2"><?php echo htmlspecialchars($q_item['explanation']); ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <p id="no_existing_questions_message" class="alert alert-info">এই কুইজে কোনো প্রশ্ন পাওয়া যায়নি। আপনি নিচে নতুন প্রশ্ন যোগ করতে পারেন।</p>
            <?php endif; ?>
        </div>

        <h3 class="mt-4">নতুন প্রশ্ন যোগ করুন</h3>
        <div id="new_questions_container">
            <div class="card new-question-block mb-3" data-new-question-index="0" style="display:none;"> 
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>নতুন প্রশ্ন #<span class="new-question-number">1</span></span>
                    <button type="button" class="btn btn-sm btn-danger remove-new-question-btn">প্রশ্ন সরান</button>
                </div>
                <div class="card-body">
                     <div class="row">
                        <div class="col-md-9 mb-3 form-control-wrapper">
                            <label class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                            <textarea class="form-control question-input-suggest" name="new_questions[0][text]" rows="2"></textarea>
                             <div class="suggestions-container"></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">প্রশ্নের ক্রম (ঐচ্ছিক)</label>
                             <input type="number" class="form-control" name="new_questions[0][order_number]" placeholder="যেমন: <?php echo count($questions_data) + 1; ?>" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">প্রশ্ন সম্পর্কিত ছবি (ঐচ্ছিক)</label>
                        <input type="file" class="form-control" name="new_questions_files[0][image_url]" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="form-text text-muted">অনুমোদিত ছবির ধরণ: JPG, PNG, GIF, WEBP. সর্বোচ্চ সাইজ: 5MB.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ক্যাটাগরি (ঐচ্ছিক)</label>
                        <select class="form-select" name="new_questions[0][category_id]">
                            <option value="">ক্যাটাগরি নির্বাচন করুন</option>
                            <?php foreach ($categories_list as $category_item): ?>
                                <option value="<?php echo $category_item['id']; ?>"><?php echo htmlspecialchars($category_item['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="options-container mb-3">
                        <label class="form-label">অপশন ও সঠিক উত্তর <span class="text-danger">*</span></label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="new_questions[0][correct_option_new]" value="<?php echo $i; ?>" aria-label="সঠিক উত্তর <?php echo $i + 1; ?>" <?php if($i==0) echo 'checked'; ?>>
                            </div>
                            <div class="form-control-wrapper flex-grow-1">
                                <input type="text" class="form-control option-input-suggest" name="new_questions[0][options][<?php echo $i; ?>]" placeholder="অপশন <?php echo $i + 1; ?>">
                                <div class="suggestions-container"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                        <textarea class="form-control" name="new_questions[0][explanation]" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-info mb-3" id="add_new_question_btn_edit">আরও নতুন প্রশ্ন যোগ করুন (+)</button>
        <hr>
        <button type="submit" name="update_full_quiz" class="btn btn-primary btn-lg">সকল পরিবর্তন সংরক্ষণ করুন</button>
        <a href="manage_quizzes.php" class="btn btn-outline-secondary btn-lg">বাতিল</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const newQuestionsContainer = document.getElementById('new_questions_container');
    const addNewQuestionBtn = document.getElementById('add_new_question_btn_edit');
    const templateNewQuestionBlock = document.querySelector('.new-question-block[data-new-question-index="0"]');
    
    let newQuestionGlobalIndex = 0; // Used to ensure unique array keys for new questions in POST
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
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault(); 
                    inputFieldEl.value = suggestionText;
                    containerEl.innerHTML = '';
                    containerEl.style.display = 'none';
                    inputFieldEl.focus();
                });
                containerEl.appendChild(item);
            });
            containerEl.style.display = 'block';
        } else {
            containerEl.style.display = 'none';
        }
    }

    function setupSuggestionListenersForExistingBlock(questionBlock) {
        const qId = questionBlock.dataset.questionId;

        const questionTextarea = questionBlock.querySelector(`#existing_q_text_${qId}`);
        const suggestionsContainerQuestion = questionBlock.querySelector(`#suggestions_ex_q_${qId}`);
        if (questionTextarea && suggestionsContainerQuestion) {
            questionTextarea.addEventListener('input', function () { fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this); });
            questionTextarea.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerQuestion.style.display = 'none'; }, 150); });
            questionTextarea.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'question', suggestionsContainerQuestion, this);});
        }

        const optionInputs = questionBlock.querySelectorAll('.option-input-suggest');
        optionInputs.forEach((optInput) => {
            const optIdFull = optInput.id; // e.g., existing_opt_text_QUESTIONID_OPTIONID
            const suggestionsContainerOption = questionBlock.querySelector(`#suggestions_ex_q_${qId}_opt_${optInput.name.match(/\[options\]\[(\d+)\]/)[1]}`); // This might need adjustment if opt_id isn't simple index
            // A better way to get a unique ID for suggestion container for existing options:
            // const suggestionsContainerOption = optInput.closest('.form-control-wrapper').querySelector('.suggestions-container');
            const optActualId = optInput.name.match(/\[(\d+)\]$/)[1]; // Extracts the actual option ID
            const suggestionsContainerForOption = questionBlock.querySelector(`#suggestions_ex_q_${qId}_opt_${optActualId}`);

            if (suggestionsContainerForOption) {
                optInput.addEventListener('input', function () { fetchSuggestions(this.value, 'option', suggestionsContainerForOption, this); });
                optInput.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerForOption.style.display = 'none'; }, 150); });
                optInput.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'option', suggestionsContainerForOption, this);});
            }
        });
    }
    
    function setupSuggestionListenersForNewBlock(newQuestionBlock, uniqueIdx) {
        const questionTextareaNew = newQuestionBlock.querySelector(`textarea[name="new_questions[${uniqueIdx}][text]"]`);
        const suggestionsContainerQuestionNew = newQuestionBlock.querySelector(`textarea[name="new_questions[${uniqueIdx}][text]"]`).nextElementSibling; // Assuming it's immediately after
        if(questionTextareaNew && suggestionsContainerQuestionNew && suggestionsContainerQuestionNew.classList.contains('suggestions-container')) {
            questionTextareaNew.addEventListener('input', function () { fetchSuggestions(this.value, 'question', suggestionsContainerQuestionNew, this); });
            questionTextareaNew.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerQuestionNew.style.display = 'none'; }, 150); });
            questionTextareaNew.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'question', suggestionsContainerQuestionNew, this);});
        }

        newQuestionBlock.querySelectorAll('input[type="text"][name^="new_questions["][name*="[options]"]').forEach((optInputNew, optInputIdx) => {
            const suggestionsContainerOptionNew = optInputNew.nextElementSibling;
             if(suggestionsContainerOptionNew && suggestionsContainerOptionNew.classList.contains('suggestions-container')) {
                optInputNew.addEventListener('input', function () { fetchSuggestions(this.value, 'option', suggestionsContainerOptionNew, this); });
                optInputNew.addEventListener('blur', function () { setTimeout(() => { suggestionsContainerOptionNew.style.display = 'none'; }, 150); });
                optInputNew.addEventListener('focus', function () { if(this.value.length >=2) fetchSuggestions(this.value, 'option', suggestionsContainerOptionNew, this);});
            }
        });
    }


    if (templateNewQuestionBlock) { // Ensure template exists
        addNewQuestionBtn.addEventListener('click', function () {
            const newClonedBlock = templateNewQuestionBlock.cloneNode(true);
            newClonedBlock.style.display = 'block'; 
            
            newClonedBlock.dataset.newQuestionIndex = newQuestionGlobalIndex; // Set unique index for this new block

            newClonedBlock.querySelectorAll('textarea, input[type="text"], input[type="file"]').forEach(input => input.value = '');
            newClonedBlock.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            const radiosCloned = newClonedBlock.querySelectorAll('input[type="radio"]');
            radiosCloned.forEach(radio => radio.checked = false);
            if(radiosCloned.length > 0) radiosCloned[0].checked = true;

            // Update names and IDs within the cloned block
            newClonedBlock.querySelector('.new-question-number').textContent = newQuestionsContainer.querySelectorAll('.new-question-block:not([style*="display:none"])').length + document.querySelectorAll('.question-block-existing').length +1; 
            
            newClonedBlock.querySelectorAll('[name^="new_questions[0]"]').forEach(input => {
                let oldName = input.getAttribute('name');
                let newName = oldName.replace(/new_questions\[0\]/, `new_questions[${newQuestionGlobalIndex}]`);
                input.setAttribute('name', newName);
                if (input.tagName.toLowerCase() === 'textarea' && newName.includes('[text]')) input.required = true;
                else if (input.type === 'text' && newName.includes('[options]')) input.required = true;
            });
             const fileInputCloned = newClonedBlock.querySelector('input[type="file"][name^="new_questions_files[0]"]');
            if (fileInputCloned) {
                fileInputCloned.setAttribute('name', `new_questions_files[${newQuestionGlobalIndex}][image_url]`);
            }
            const radioGroupNameCloned = `new_questions[${newQuestionGlobalIndex}][correct_option_new]`;
            newClonedBlock.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.setAttribute('name', radioGroupNameCloned);
                radio.required = true; // Ensure radios in new blocks are required
            });
             newClonedBlock.querySelectorAll('.suggestions-container').forEach(sc => {
                sc.innerHTML = ''; sc.style.display = 'none';
            });

            setupSuggestionListenersForNewBlock(newClonedBlock, newQuestionGlobalIndex);
            
            newClonedBlock.querySelector('.remove-new-question-btn').addEventListener('click', function () {
                newClonedBlock.remove();
                // Renumbering might be complex if mixing existing and new. For simplicity, new questions just get removed.
                // If strict numbering is needed after removal, a more comprehensive renumbering function is required.
            });
            
            newQuestionsContainer.appendChild(newClonedBlock);
            if (document.getElementById('no_existing_questions_message')) {
                document.getElementById('no_existing_questions_message').style.display = 'none';
            }
            newQuestionGlobalIndex++; // Increment for the next new question
        });
    }


    if (document.getElementById('quiz_description_editor_edit')) {
        const quillEditDescription = new Quill('#quiz_description_editor_edit', {
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
        
        const editQuizForm = document.getElementById('editQuizForm');
        if (editQuizForm) {
            editQuizForm.addEventListener('submit', function() {
                const descriptionHiddenInputEdit = document.getElementById('quiz_description_hidden_edit');
                if (descriptionHiddenInputEdit) {
                    descriptionHiddenInputEdit.value = quillEditDescription.root.innerHTML;
                     if (quillEditDescription.getText().trim().length === 0 && quillEditDescription.root.innerHTML === '<p><br></p>') {
                         descriptionHiddenInputEdit.value = ''; 
                    }
                }
            });
        }
        // Preserve content on form error reload by PHP
        <?php if (isset($_POST['quiz_description']) && !empty($errors)): ?>
        if(quillEditDescription && typeof quillEditDescription.setContents === 'function') {
            // Assuming PHP echos the raw HTML correctly for Quill.
            // If it's delta, then parse and setContents.
            // For direct HTML:
             quillEditDescription.root.innerHTML = <?php echo json_encode($_POST['quiz_description']); ?>;
        }
        <?php endif; ?>
    }
    
    // Setup suggestions for initially loaded existing questions
    document.querySelectorAll('.question-block-existing').forEach(block => {
        setupSuggestionListenersForExistingBlock(block);
    });
});
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) { // Check if $conn is set and is a mysqli object
    $conn->close();
}
require_once 'includes/footer.php';
?>