<?php
// admin/duplicate_quiz.php
$page_title = "কুইজ ডুপ্লিকেট করুন";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

define('QUESTION_IMAGE_UPLOAD_DIR_DUPLICATE', '../uploads/question_images/');

if (!isset($_GET['quiz_id_to_duplicate']) || empty($_GET['quiz_id_to_duplicate'])) {
    $_SESSION['flash_message'] = "ডুপ্লিকেট করার জন্য কোনো কুইজ আইডি পাওয়া যায়নি।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_quizzes.php");
    exit;
}

$original_quiz_id = intval($_GET['quiz_id_to_duplicate']);
$created_by_user_id = $_SESSION['user_id']; 

$conn->begin_transaction();
try {
    // 1. Fetch original quiz
    $sql_original_quiz = "SELECT * FROM quizzes WHERE id = ?";
    $stmt_original_quiz = $conn->prepare($sql_original_quiz);
    if (!$stmt_original_quiz) throw new Exception("আসল কুইজের তথ্য আনতে সমস্যা (prepare): " . $conn->error);
    $stmt_original_quiz->bind_param("i", $original_quiz_id);
    $stmt_original_quiz->execute();
    $result_original_quiz = $stmt_original_quiz->get_result();
    if ($result_original_quiz->num_rows === 0) {
        throw new Exception("আসল কুইজ (ID: {$original_quiz_id}) খুঁজে পাওয়া যায়নি।");
    }
    $original_quiz_data = $result_original_quiz->fetch_assoc();
    $stmt_original_quiz->close();

    // 2. Create new quiz
    $new_quiz_title = "ডুপ্লিকেট - " . $original_quiz_data['title'];
    $new_quiz_status = 'draft'; 
    $new_live_start = null;     
    $new_live_end = null;

    $sql_insert_new_quiz = "INSERT INTO quizzes (title, description, duration_minutes, status, live_start_datetime, live_end_datetime, created_by, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt_new_quiz = $conn->prepare($sql_insert_new_quiz);
    if (!$stmt_new_quiz) throw new Exception("নতুন কুইজ তৈরি করতে সমস্যা (prepare): " . $conn->error);

    $stmt_new_quiz->bind_param(
        "ssisssi",
        $new_quiz_title,
        $original_quiz_data['description'],
        $original_quiz_data['duration_minutes'],
        $new_quiz_status,
        $new_live_start,
        $new_live_end,
        $created_by_user_id
    );

    if (!$stmt_new_quiz->execute()) {
        throw new Exception("নতুন কুইজ সংরক্ষণ করতে সমস্যা: " . $stmt_new_quiz->error);
    }
    $new_quiz_id = $stmt_new_quiz->insert_id;
    $stmt_new_quiz->close();


    // 3. Fetch questions from original quiz
    $sql_original_questions = "SELECT id, question_text, image_url, explanation, order_number FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC";
    $stmt_original_questions = $conn->prepare($sql_original_questions);
    if (!$stmt_original_questions) throw new Exception("আসল প্রশ্ন আনতে সমস্যা (prepare): " . $conn->error);
    $stmt_original_questions->bind_param("i", $original_quiz_id);
    $stmt_original_questions->execute();
    $result_original_questions = $stmt_original_questions->get_result();

    while ($original_question_data = $result_original_questions->fetch_assoc()) {
        $original_question_id = $original_question_data['id'];
        $new_question_image_url = null;

        // 3a. Handle question image duplication
        if (!empty($original_question_data['image_url'])) { 
            $original_image_path_relative = $original_question_data['image_url'];
            $original_image_basename = basename($original_image_path_relative);
            $original_image_full_path = QUESTION_IMAGE_UPLOAD_DIR_DUPLICATE . $original_image_basename;

            if (file_exists($original_image_full_path)) {
                $file_ext = strtolower(pathinfo($original_image_basename, PATHINFO_EXTENSION));
                $new_image_filename = "q_img_" . $new_quiz_id . "_qdup_" . time() . "_" . uniqid("", true) . "." . $file_ext;
                $new_image_full_path = QUESTION_IMAGE_UPLOAD_DIR_DUPLICATE . $new_image_filename;

                if (copy($original_image_full_path, $new_image_full_path)) {
                    $new_question_image_url = 'uploads/question_images/' . $new_image_filename;
                } else {
                    error_log("Failed to copy image during duplication: {$original_image_full_path} to {$new_image_full_path} for new quiz ID {$new_quiz_id}");
                }
            } else {
                error_log("Original image not found for duplication: {$original_image_full_path}");
            }
        }

        // 3b. Insert the new question (set category_id to NULL)
        $sql_insert_new_question = "INSERT INTO questions (quiz_id, question_text, image_url, explanation, order_number, category_id) VALUES (?, ?, ?, ?, ?, NULL)";
        $stmt_new_question = $conn->prepare($sql_insert_new_question);
        if (!$stmt_new_question) throw new Exception("নতুন প্রশ্ন যোগ করতে সমস্যা (prepare): " . $conn->error);

        $stmt_new_question->bind_param(
            "isssi", 
            $new_quiz_id,
            $original_question_data['question_text'],
            $new_question_image_url,
            $original_question_data['explanation'],
            $original_question_data['order_number']
        );

        if (!$stmt_new_question->execute()) {
            throw new Exception("নতুন প্রশ্ন সংরক্ষণ করতে সমস্যা: " . $stmt_new_question->error);
        }
        $new_question_id = $stmt_new_question->insert_id;
        $stmt_new_question->close();

        // 3c. Fetch and duplicate category associations from question_categories
        $sql_original_q_categories = "SELECT category_id FROM question_categories WHERE question_id = ?";
        $stmt_original_q_cat = $conn->prepare($sql_original_q_categories);
        if (!$stmt_original_q_cat) throw new Exception("আসল প্রশ্নের ক্যাটাগরি আনতে সমস্যা (prepare): " . $conn->error);
        $stmt_original_q_cat->bind_param("i", $original_question_id);
        $stmt_original_q_cat->execute();
        $result_original_q_cat = $stmt_original_q_cat->get_result();

        $sql_insert_new_q_cat = "INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)";
        $stmt_new_q_cat = $conn->prepare($sql_insert_new_q_cat);
        if (!$stmt_new_q_cat) throw new Exception("নতুন প্রশ্নের ক্যাটাগরি লিংক স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);

        while ($q_cat_row = $result_original_q_cat->fetch_assoc()) {
            $stmt_new_q_cat->bind_param("ii", $new_question_id, $q_cat_row['category_id']);
            if (!$stmt_new_q_cat->execute()) {
                throw new Exception("নতুন প্রশ্নের ক্যাটাগরি লিংক (ID: ".$q_cat_row['category_id'].") সংরক্ষণ করতে সমস্যা: " . $stmt_new_q_cat->error);
            }
        }
        $stmt_original_q_cat->close();
        $stmt_new_q_cat->close();


        // 4. Fetch and duplicate options
        $sql_original_options = "SELECT * FROM options WHERE question_id = ? ORDER BY id ASC";
        $stmt_original_options = $conn->prepare($sql_original_options);
        if (!$stmt_original_options) throw new Exception("আসল অপশন আনতে সমস্যা (prepare): " . $conn->error);
        $stmt_original_options->bind_param("i", $original_question_id);
        $stmt_original_options->execute();
        $result_original_options = $stmt_original_options->get_result();

        while ($original_option_data = $result_original_options->fetch_assoc()) {
            $sql_insert_new_option = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
            $stmt_new_option = $conn->prepare($sql_insert_new_option);
            if (!$stmt_new_option) throw new Exception("নতুন অপশন যোগ করতে সমস্যা (prepare): " . $conn->error);
            $stmt_new_option->bind_param(
                "isi",
                $new_question_id,
                $original_option_data['option_text'],
                $original_option_data['is_correct']
            );
            if (!$stmt_new_option->execute()) throw new Exception("নতুন অপশন সংরক্ষণ করতে সমস্যা: " . $stmt_new_option->error);
            $stmt_new_option->close();
        }
        $stmt_original_options->close();
    }
    $stmt_original_questions->close();

    $conn->commit();
    $_SESSION['flash_message'] = "কুইজ \"" . htmlspecialchars($original_quiz_data['title']) . "\" সফলভাবে \"" . htmlspecialchars($new_quiz_title) . "\" নামে ডুপ্লিকেট করা হয়েছে।";
    $_SESSION['flash_message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash_message'] = "কুইজ ডুপ্লিকেট করার সময় একটি ত্রুটি ঘটেছে: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "danger";
    error_log("Quiz duplication error for original quiz ID {$original_quiz_id}: " . $e->getMessage());
}

header("Location: manage_quizzes.php");
exit;
?>