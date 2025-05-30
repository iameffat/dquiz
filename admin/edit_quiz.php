<?php
$page_title = "কুইজ এডিট করুন";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$quiz = null;
$questions_data = []; // This will hold existing questions with their options
$errors = [];

if ($quiz_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ কুইজ ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_quizzes.php");
    exit;
}

// --- Action: Delete a specific question from this quiz ---
if (isset($_GET['action']) && $_GET['action'] == 'delete_question' && isset($_GET['question_id'])) {
    $question_id_to_delete = intval($_GET['question_id']);
    
    // Ensure the question belongs to the current quiz for security
    $sql_check_q = "SELECT id FROM questions WHERE id = ? AND quiz_id = ?";
    $stmt_check_q = $conn->prepare($sql_check_q);
    $stmt_check_q->bind_param("ii", $question_id_to_delete, $quiz_id);
    $stmt_check_q->execute();
    if ($stmt_check_q->get_result()->num_rows === 1) {
        // First, delete options related to the question
        $sql_delete_options = "DELETE FROM options WHERE question_id = ?";
        $stmt_delete_options = $conn->prepare($sql_delete_options);
        $stmt_delete_options->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_options->execute()) {
             $_SESSION['flash_message'] = "প্রশ্নের অপশন ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete_options->error;
             $_SESSION['flash_message_type'] = "danger";
             $stmt_delete_options->close();
             $stmt_check_q->close();
             header("Location: edit_quiz.php?id=" . $quiz_id);
             exit;
        }
        $stmt_delete_options->close();

        // Then, delete the question itself
        $sql_delete_q = "DELETE FROM questions WHERE id = ?";
        $stmt_delete_q = $conn->prepare($sql_delete_q);
        $stmt_delete_q->bind_param("i", $question_id_to_delete);
        if ($stmt_delete_q->execute()) {
            $_SESSION['flash_message'] = "প্রশ্ন (ID: {$question_id_to_delete}) সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "প্রশ্ন ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete_q->error;
            $_SESSION['flash_message_type'] = "danger";
        }
        $stmt_delete_q->close();
    } else {
        $_SESSION['flash_message'] = "অবৈধ প্রশ্ন অথবা কুইজের সাথে সম্পর্কযুক্ত নয়।";
        $_SESSION['flash_message_type'] = "warning";
    }
    $stmt_check_q->close();
    header("Location: edit_quiz.php?id=" . $quiz_id); // Refresh page
    exit;
}


// Fetch quiz details
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
}

// Handle Form Submission for updating quiz (metadata, existing questions, new questions)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_full_quiz'])) {
    $conn->begin_transaction();
    try {
        // 1. Update Quiz Metadata
        $quiz_title = trim($_POST['quiz_title']);
        $quiz_description = trim($_POST['quiz_description']);
        $quiz_duration = intval($_POST['quiz_duration']);
        $quiz_status = trim($_POST['quiz_status']);
        $quiz_live_start = !empty($_POST['quiz_live_start']) ? trim($_POST['quiz_live_start']) : NULL;
        $quiz_live_end = !empty($_POST['quiz_live_end']) ? trim($_POST['quiz_live_end']) : NULL;

        if (empty($quiz_title)) $errors[] = "কুইজের শিরোনাম আবশ্যক।";
        if ($quiz_duration <= 0) $errors[] = "কুইজের সময় অবশ্যই ০ মিনিটের বেশি হতে হবে।";
        if (!in_array($quiz_status, ['draft', 'live', 'archived'])) $errors[] = "অবৈধ কুইজ স্ট্যাটাস।";
        if ($quiz_live_start && $quiz_live_end && strtotime($quiz_live_start) >= strtotime($quiz_live_end)) {
            $errors[] = "লাইভ শেষের সময় অবশ্যই শুরুর সময়ের পরে হতে হবে।";
        }


        if (empty($errors)) {
            $sql_update_meta = "UPDATE quizzes SET title = ?, description = ?, duration_minutes = ?, status = ?, live_start_datetime = ?, live_end_datetime = ? WHERE id = ?";
            $stmt_update_meta = $conn->prepare($sql_update_meta);
            $stmt_update_meta->bind_param("ssisssi", $quiz_title, $quiz_description, $quiz_duration, $quiz_status, $quiz_live_start, $quiz_live_end, $quiz_id);
            if (!$stmt_update_meta->execute()) throw new Exception("কুইজের বিবরণ আপডেট করতে সমস্যা: " . $stmt_update_meta->error);
            $stmt_update_meta->close();
        }

        // 2. Update Existing Questions & Options
        if (isset($_POST['existing_questions'])) {
            foreach ($_POST['existing_questions'] as $q_id => $q_data) {
                $q_text = trim($q_data['text']);
                $q_explanation = isset($q_data['explanation']) ? trim($q_data['explanation']) : NULL;
                $q_order = isset($q_data['order_number']) ? intval($q_data['order_number']) : 0;
                
                if (empty($q_text)) { $errors[] = "বিদ্যমান প্রশ্ন (ID: $q_id) এর লেখা খালি রাখা যাবে না।"; continue; }

                $sql_update_q = "UPDATE questions SET question_text = ?, explanation = ?, order_number = ? WHERE id = ? AND quiz_id = ?";
                $stmt_update_q = $conn->prepare($sql_update_q);
                $stmt_update_q->bind_param("ssiii", $q_text, $q_explanation, $q_order, $q_id, $quiz_id);
                if (!$stmt_update_q->execute()) throw new Exception("বিদ্যমান প্রশ্ন (ID: $q_id) আপডেট করতে সমস্যা: " . $stmt_update_q->error);
                $stmt_update_q->close();

                // Update options for this question
                if (isset($q_data['options']) && isset($q_data['correct_option'])) {
                    $correct_option_id_from_post = $q_data['correct_option']; 
                    
                    foreach ($q_data['options'] as $opt_id => $opt_text_val) {
                        $opt_text = trim($opt_text_val);
                        if (empty($opt_text)) { $errors[] = "প্রশ্ন (ID: $q_id) এর অপশন (ID: $opt_id) খালি রাখা যাবে না।"; continue; }
                        
                        $is_correct = ($opt_id == $correct_option_id_from_post) ? 1 : 0;
                        
                        $sql_update_opt = "UPDATE options SET option_text = ?, is_correct = ? WHERE id = ? AND question_id = ?";
                        $stmt_update_opt = $conn->prepare($sql_update_opt);
                        $stmt_update_opt->bind_param("siii", $opt_text, $is_correct, $opt_id, $q_id);
                        if (!$stmt_update_opt->execute()) throw new Exception("অপশন (ID: $opt_id) আপডেট করতে সমস্যা: " . $stmt_update_opt->error);
                        $stmt_update_opt->close();
                    }
                } else {
                     $errors[] = "প্রশ্ন (ID: $q_id) এর জন্য অপশন বা সঠিক উত্তর পাওয়া যায়নি।";
                }
            }
        }
        
        // 3. Add New Questions (if any)
        if (isset($_POST['new_questions'])) {
            foreach ($_POST['new_questions'] as $nq_idx => $nq_data) {
                $nq_text = trim($nq_data['text']);
                $nq_explanation = isset($nq_data['explanation']) ? trim($nq_data['explanation']) : NULL;
                $nq_order = isset($nq_data['order_number']) ? intval($nq_data['order_number']) : 0;
                
                if (empty($nq_text)) { /* errors[] = "নতুন প্রশ্ন #" . ($nq_idx + 1) . " এর লেখা খালি রাখা যাবে না।"; */ continue; } // Skip if no text
                if (empty($nq_data['options']) || count($nq_data['options']) < 2) { $errors[] = "নতুন প্রশ্ন #" . ($nq_idx + 1) . " এর জন্য কমপক্ষে ২টি অপশন দিন।"; continue; }
                if (!isset($nq_data['correct_option_new']) || $nq_data['correct_option_new'] === '') { $errors[] = "নতুন প্রশ্ন #" . ($nq_idx + 1) . " এর সঠিক উত্তর নির্বাচন করুন।"; continue; }

                // Determine order number for new question
                // If order number is provided and not 0, use it. Otherwise, calculate.
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


                $sql_insert_nq = "INSERT INTO questions (quiz_id, question_text, explanation, order_number) VALUES (?, ?, ?, ?)";
                $stmt_insert_nq = $conn->prepare($sql_insert_nq);
                $stmt_insert_nq->bind_param("issi", $quiz_id, $nq_text, $nq_explanation, $new_order_num);
                if (!$stmt_insert_nq->execute()) throw new Exception("নতুন প্রশ্ন যোগ করতে সমস্যা: " . $stmt_insert_nq->error);
                $new_question_id = $stmt_insert_nq->insert_id;
                $stmt_insert_nq->close();

                $correct_new_opt_idx = intval($nq_data['correct_option_new']); 
                foreach ($nq_data['options'] as $nopt_idx => $nopt_text_val) {
                    $nopt_text = trim($nopt_text_val);
                    if (empty($nopt_text)) { /* errors[] = "নতুন প্রশ্ন #" . ($nq_idx + 1) . ", অপশন #" . ($nopt_idx + 1) . " খালি রাখা যাবে না।"; */ continue; } // Skip empty options
                    
                    $is_correct_new = ($nopt_idx == $correct_new_opt_idx) ? 1 : 0;
                    $sql_insert_nopt = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                    $stmt_insert_nopt = $conn->prepare($sql_insert_nopt);
                    $stmt_insert_nopt->bind_param("isi", $new_question_id, $nopt_text, $is_correct_new);
                    if (!$stmt_insert_nopt->execute()) throw new Exception("নতুন অপশন যোগ করতে সমস্যা: " . $stmt_insert_nopt->error);
                    $stmt_insert_nopt->close();
                }
            }
        }

        if (!empty($errors)) {
            throw new Exception("কিছু তথ্যগত ত্রুটি রয়েছে।"); 
        }

        $conn->commit();
        $_SESSION['flash_message'] = "কুইজ সফলভাবে আপডেট করা হয়েছে।";
        $_SESSION['flash_message_type'] = "success";
        header("Location: edit_quiz.php?id=" . $quiz_id); 
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "একটি গুরুতর ত্রুটি ঘটেছে: " . $e->getMessage();
        // To repopulate form with POST data on error:
        $quiz['title'] = $_POST['quiz_title'] ?? $quiz['title'];
        $quiz['description'] = $_POST['quiz_description'] ?? $quiz['description'];
        $quiz['duration_minutes'] = $_POST['quiz_duration'] ?? $quiz['duration_minutes'];
        $quiz['status'] = $_POST['quiz_status'] ?? $quiz['status'];
        $quiz['live_start_datetime'] = $_POST['quiz_live_start'] ?? $quiz['live_start_datetime'];
        $quiz['live_end_datetime'] = $_POST['quiz_live_end'] ?? $quiz['live_end_datetime'];
        // Repopulating questions would be more complex here, usually involves re-fetching or careful handling
    }
}


// Fetch existing questions and their options for this quiz
$questions_data = []; // Re-initialize to ensure fresh data
$sql_questions_load = "SELECT * FROM questions WHERE quiz_id = ? ORDER BY order_number ASC, id ASC";
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
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-3">কুইজ এডিট করুন: <?php echo htmlspecialchars($quiz['title']); ?></h1>

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
    
    <?php
    // Display flash messages
    if (isset($_SESSION['flash_message'])) {
        display_flash_message('flash_message', 'flash_message_type');
    }
    ?>

    <form action="edit_quiz.php?id=<?php echo $quiz_id; ?>" method="post" id="editQuizForm">
        <div class="card mb-4">
            <div class="card-header">কুইজের বিবরণ (এডিট)</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="quiz_title" class="form-label">শিরোনাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="quiz_title" name="quiz_title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="quiz_description" class="form-label">সংক্ষিপ্ত বর্ণনা</label>
                    <textarea class="form-control" id="quiz_description" name="quiz_description" rows="3"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
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
                <div class="card question-block-existing mb-3" id="existing_question_<?php echo $q_item['id']; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>বিদ্যমান প্রশ্ন #<span class="existing-question-number"><?php echo $q_item['order_number']; ?></span> (ID: <?php echo $q_item['id']; ?>)</span>
                        <a href="edit_quiz.php?id=<?php echo $quiz_id; ?>&action=delete_question&question_id=<?php echo $q_item['id']; ?>" 
                           class="btn btn-sm btn-danger remove-existing-question" 
                           onclick="return confirm('আপনি কি নিশ্চিতভাবে এই প্রশ্নটি এবং এর অপশনগুলো ডিলিট করতে চান?');">প্রশ্ন সরান</a>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="existing_questions[<?php echo $q_item['id']; ?>][id]" value="<?php echo $q_item['id']; ?>">
                        <div class="row">
                            <div class="col-md-9 mb-3">
                                <label class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="existing_questions[<?php echo $q_item['id']; ?>][text]" rows="2" required><?php echo htmlspecialchars($q_item['question_text']); ?></textarea>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">প্রশ্নের ক্রম</label>
                                <input type="number" class="form-control" name="existing_questions[<?php echo $q_item['id']; ?>][order_number]" value="<?php echo htmlspecialchars($q_item['order_number']); ?>" min="1">
                            </div>
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
                                           aria-label="সঠিক উত্তর" 
                                           <?php echo ($opt_item['is_correct'] == 1) ? 'checked' : ''; ?> required>
                                </div>
                                <input type="text" class="form-control" 
                                       name="existing_questions[<?php echo $q_item['id']; ?>][options][<?php echo $opt_item['id']; ?>]" 
                                       value="<?php echo htmlspecialchars($opt_item['option_text']); ?>" 
                                       placeholder="অপশন <?php echo $opt_idx + 1; ?>" required>
                            </div>
                            <?php endforeach; ?>
                             <?php if (count($q_item['options']) < 4): 
                                for ($k = count($q_item['options']); $k < 4; $k++): ?>
                                <div class="input-group mb-2">
                                    <div class="input-group-text"><input class="form-check-input mt-0" type="radio" name="<?php echo $radio_group_name; ?>" value="new_<?php echo $k; ?>" disabled></div>
                                    <input type="text" class="form-control" placeholder="অপশন <?php echo $k + 1; ?> (প্রয়োজনে যোগ করুন)" disabled>
                                </div>
                                <?php endfor; endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ব্যাখ্যা (ঐচ্ছিক)</label>
                            <textarea class="form-control" name="existing_questions[<?php echo $q_item['id']; ?>][explanation]" rows="2"><?php echo htmlspecialchars($q_item['explanation']); ?></textarea>
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
                    <button type="button" class="btn btn-sm btn-danger remove-new-question">প্রশ্ন সরান</button>
                </div>
                <div class="card-body">
                     <div class="row">
                        <div class="col-md-9 mb-3">
                            <label class="form-label">প্রশ্নের লেখা <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="new_questions[0][text]" rows="2"></textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">প্রশ্নের ক্রম (ঐচ্ছিক)</label>
                             <input type="number" class="form-control" name="new_questions[0][order_number]" placeholder="যেমন: <?php echo count($questions_data) + 1; ?>" min="1">
                        </div>
                    </div>
                    <div class="options-container mb-3">
                        <label class="form-label">অপশন ও সঠিক উত্তর <span class="text-danger">*</span></label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="new_questions[0][correct_option_new]" value="<?php echo $i; ?>" aria-label="সঠিক উত্তর">
                            </div>
                            <input type="text" class="form-control" name="new_questions[0][options][<?php echo $i; ?>]" placeholder="অপশন <?php echo $i + 1; ?>">
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
        <button type="button" class="btn btn-info mb-3" id="add_new_question_btn">আরও নতুন প্রশ্ন যোগ করুন (+)</button>
        <hr>
        <button type="submit" name="update_full_quiz" class="btn btn-primary btn-lg">সকল পরিবর্তন সংরক্ষণ করুন</button>
        <a href="manage_quizzes.php" class="btn btn-outline-secondary btn-lg">বাতিল</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const newQuestionsContainer = document.getElementById('new_questions_container');
    const addNewQuestionBtn = document.getElementById('add_new_question_btn');
    
    const templateNewQuestionBlock = document.querySelector('.new-question-block[data-new-question-index="0"]');
    if (!templateNewQuestionBlock) {
        console.error("Template for new question block not found!");
        return;
    }
    
    let newQuestionCounter = 0; 

    function updateNewQuestionBlock(block, currentIndex) {
        block.querySelector('.new-question-number').textContent = currentIndex + 1; 
        block.dataset.newQuestionIndex = currentIndex; 

        block.querySelectorAll('[name^="new_questions["]').forEach(input => {
            let oldName = input.getAttribute('name');
            let newName = oldName.replace(/new_questions\[\d+\]/, `new_questions[${currentIndex}]`);
            input.setAttribute('name', newName);
            // For new questions, ensure textarea and text inputs are required only if it's not a dummy template part
            if (input.tagName.toLowerCase() === 'textarea' && newName.includes('[text]')) {
                input.required = true;
            } else if (input.type === 'text' && newName.includes('[options]')) {
                 input.required = true;
            }
        });
        
        const radioGroupName = `new_questions[${currentIndex}][correct_option_new]`;
        let firstRadio = true;
        block.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.setAttribute('name', radioGroupName);
            radio.required = true; // A correct option must be chosen
            if (firstRadio) { // Check the first option by default for new questions
                // radio.checked = true; // Optional: Check first by default
                firstRadio = false;
            }
        });
    }

    addNewQuestionBtn.addEventListener('click', function () {
        const newClonedBlock = templateNewQuestionBlock.cloneNode(true);
        newClonedBlock.style.display = 'block'; 
        
        updateNewQuestionBlock(newClonedBlock, newQuestionCounter);
        newQuestionCounter++;
        
        newClonedBlock.querySelectorAll('textarea, input[type="text"]').forEach(input => input.value = '');
        newClonedBlock.querySelectorAll('input[type="radio"]').forEach(radio => radio.checked = false);
        // Default check first radio of new block
        const firstRadioInClone = newClonedBlock.querySelector('input[type="radio"]');
        if(firstRadioInClone) firstRadioInClone.checked = true;

        newClonedBlock.querySelector('.remove-new-question').addEventListener('click', function () {
            newClonedBlock.remove();
            // Re-number displayed indices of new questions if needed (visual only)
            let displayIdx = 0;
            newQuestionsContainer.querySelectorAll('.new-question-block:not([style*="display:none"])').forEach(visibleBlock => {
                displayIdx++; // This only makes sense if you're adjusting numbers after deletion.
                              // Simpler approach is to let numbers be non-sequential on client after deletion
                              // as backend will handle array keys.
                // visibleBlock.querySelector('.new-question-number').textContent = displayIdx;
            });
             if (newQuestionsContainer.querySelectorAll('.new-question-block:not([style*="display:none"])').length === 0 && document.getElementById('no_existing_questions_message')) {
                 // Show "no existing questions" only if also no new questions are visible
             }


        });
        
        newQuestionsContainer.appendChild(newClonedBlock);
        if (document.getElementById('no_existing_questions_message')) {
             document.getElementById('no_existing_questions_message').style.display = 'none';
        }
    });
});
</script>

<?php
$conn->close();
require_once 'includes/footer.php';
?>