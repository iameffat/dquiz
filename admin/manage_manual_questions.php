<?php
$page_title = "ম্যানুয়াল প্রশ্ন ম্যানেজমেন্ট";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

// Define QUESTION_IMAGE_BASE_URL if not already defined
if (!defined('QUESTION_IMAGE_BASE_URL_MANAGE')) {
    define('QUESTION_IMAGE_BASE_URL_MANAGE', '../');
}
if (!defined('QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL')) {
    define('QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL', '../uploads/question_images/');
}
if (!is_dir(QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL)) {
    if (!mkdir(QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL, 0777, true) && !is_dir(QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL)) {
        // This error will be caught and displayed later if needed
    }
}


$errors = [];
$feedback_message = "";
$feedback_type = "";

// Fetch categories for the multi-select dropdown
$categories_list_for_form = [];
$sql_cat_list_form = "SELECT id, name FROM categories ORDER BY name ASC";
$result_cat_list_form = $conn->query($sql_cat_list_form);
if ($result_cat_list_form) {
    while ($cat_row_form = $result_cat_list_form->fetch_assoc()) {
        $categories_list_for_form[] = $cat_row_form;
    }
}

// Handle Bulk Manual Question Import
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['prepare_manual_questions_from_bulk'])) {
    $bulk_text = trim($_POST['bulk_manual_questions_text']);
    $selected_category_ids_for_bulk = isset($_POST['bulk_category_ids']) ? $_POST['bulk_category_ids'] : [];

    if (empty($bulk_text)) {
        $errors[] = "ইম্পোর্টের জন্য টেক্সট-এরিয়াতে কোনো লেখা পাওয়া যায়নি।";
    }
    if (empty($selected_category_ids_for_bulk)) {
        $errors[] = "বাল্ক প্রশ্নের জন্য কমপক্ষে একটি ক্যাটাগরি নির্বাচন করতে হবে।";
    }

    if (empty($errors)) {
        $lines = array_map('trim', explode("\n", $bulk_text));
        $parsed_bulk_questions = [];
        $current_q_data = null;

        foreach ($lines as $line_number => $line) {
            $trimmed_line = trim($line);
            if (empty($trimmed_line) && $current_q_data === null) continue;

            if (preg_match('/^\s*(\d+)\.\s*(.+)/', $trimmed_line, $matches_q)) {
                if ($current_q_data !== null && !empty($current_q_data['text']) && count($current_q_data['options']) >= 1) {
                    $parsed_bulk_questions[] = $current_q_data;
                }
                $current_q_data = [
                    'text' => trim($matches_q[2]),
                    'options' => [],
                    'explanation' => '',
                    'correct_option_index' => 0 // Default to first option if not specified
                ];
            } elseif ($current_q_data !== null && preg_match('/^\s*(\*?)\s*([a-zA-Z\p{Bengali}][\p{Bengali}]*|[iIvVxX]+|[A-Za-z])\.\s*(.+)/u', $trimmed_line, $matches_o)) {
                if (count($current_q_data['options']) < 4) { // Assuming max 4 options
                    $is_correct_option_from_bulk = (trim($matches_o[1]) === '*');
                    $option_text = trim($matches_o[3]);
                    $current_q_data['options'][] = $option_text;
                    if ($is_correct_option_from_bulk) {
                        $current_q_data['correct_option_index'] = count($current_q_data['options']) - 1;
                    }
                }
            } elseif ($current_q_data !== null && preg_match('/^\s*=\s*(.+)/', $trimmed_line, $matches_exp)) {
                $current_q_data['explanation'] = trim($matches_exp[1]);
            }
        }
        if ($current_q_data !== null && !empty($current_q_data['text']) && count($current_q_data['options']) >= 1) {
            $parsed_bulk_questions[] = $current_q_data;
        }

        if (empty($parsed_bulk_questions)) {
            $errors[] = "প্রদত্ত টেক্সট থেকে কোনো প্রশ্ন ও অপশন সঠিকভাবে পার্স করা যায়নি। অনুগ্রহ করে ফরম্যাট চেক করুন।";
        } else {
            $conn->begin_transaction();
            try {
                $imported_count = 0;
                foreach ($parsed_bulk_questions as $q_data) {
                    if (empty(trim($q_data['text'])) || count($q_data['options']) < 2) {
                        // Skip invalid question structure from bulk
                        continue;
                    }

                    $sql_question = "INSERT INTO questions (quiz_id, question_text, image_url, explanation, order_number, category_id) VALUES (NULL, ?, NULL, ?, 0, NULL)";
                    $stmt_question = $conn->prepare($sql_question);
                    if (!$stmt_question) throw new Exception("প্রশ্ন স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
                    
                    $stmt_question->bind_param("ss", $q_data['text'], $q_data['explanation']);
                    if (!$stmt_question->execute()) throw new Exception("প্রশ্ন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_question->error);
                    $question_id = $stmt_question->insert_id;
                    $stmt_question->close();

                    // Insert options
                    $sql_option = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                    $stmt_option = $conn->prepare($sql_option);
                    if (!$stmt_option) throw new Exception("অপশন স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);

                    foreach ($q_data['options'] as $opt_idx => $opt_text) {
                        $option_text_trimmed = trim($opt_text);
                        if (empty($option_text_trimmed)) continue;
                        $is_correct = ($opt_idx == $q_data['correct_option_index']) ? 1 : 0;
                        $stmt_option->bind_param("isi", $question_id, $option_text_trimmed, $is_correct);
                        if (!$stmt_option->execute()) throw new Exception("অপশন সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_option->error);
                    }
                    $stmt_option->close();

                    // Insert into question_categories junction table
                    $sql_q_cat = "INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)";
                    $stmt_q_cat = $conn->prepare($sql_q_cat);
                    if (!$stmt_q_cat) throw new Exception("প্রশ্ন-ক্যাটাগরি স্টেটমেন্ট প্রস্তুত করতে সমস্যা: " . $conn->error);
                    
                    foreach ($selected_category_ids_for_bulk as $cat_id_val) {
                        $cat_id = intval($cat_id_val);
                        $stmt_q_cat->bind_param("ii", $question_id, $cat_id);
                        if (!$stmt_q_cat->execute()) {
                            if ($conn->errno != 1062) { 
                                throw new Exception("প্রশ্ন-ক্যাটাগরি লিংক সংরক্ষণ করতে সমস্যা (ক্যাটাগরি ID: {$cat_id}): " . $stmt_q_cat->error);
                            }
                        }
                    }
                    $stmt_q_cat->close();
                    $imported_count++;
                }

                $conn->commit();
                if ($imported_count > 0) {
                    $_SESSION['flash_message'] = $imported_count . " টি প্রশ্ন সফলভাবে ইম্পোর্ট এবং নির্বাচিত ক্যাটাগরিগুলোর সাথে লিঙ্ক করা হয়েছে।";
                    $_SESSION['flash_message_type'] = "success";
                } else {
                     $_SESSION['flash_message'] = "কোনো প্রশ্ন ইম্পোর্ট করা হয়নি। ফরম্যাট বা ইনপুট চেক করুন।";
                     $_SESSION['flash_message_type'] = "warning";
                }
                header("Location: manage_manual_questions.php");
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "বাল্ক প্রশ্ন ইম্পোর্ট করার সময় একটি ত্রুটি ঘটেছে: " . $e->getMessage();
            }
        }
    }
}
// Handle Single Delete Action
else if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['question_id'])) {
    $question_id_to_delete = intval($_GET['question_id']);
    
    $conn->begin_transaction();
    try {
        // Fetch image URL to delete the file
        $sql_get_image = "SELECT image_url FROM questions WHERE id = ? AND quiz_id IS NULL";
        $stmt_get_image = $conn->prepare($sql_get_image);
        $stmt_get_image->bind_param("i", $question_id_to_delete);
        $stmt_get_image->execute();
        $image_result = $stmt_get_image->get_result();
        $image_row = $image_result->fetch_assoc();
        $stmt_get_image->close();

        // Delete from question_categories junction table
        $sql_delete_q_categories = "DELETE FROM question_categories WHERE question_id = ?";
        $stmt_delete_q_categories = $conn->prepare($sql_delete_q_categories);
        $stmt_delete_q_categories->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_q_categories->execute()) throw new Exception("প্রশ্ন-ক্যাটাগরি লিংক ডিলিট করতে সমস্যা: " . $stmt_delete_q_categories->error);
        $stmt_delete_q_categories->close();

        // Delete options for the question
        $sql_delete_options = "DELETE FROM options WHERE question_id = ?";
        $stmt_delete_options = $conn->prepare($sql_delete_options);
        $stmt_delete_options->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_options->execute()) throw new Exception("অপশন ডিলিট করতে সমস্যা: " . $stmt_delete_options->error);
        $stmt_delete_options->close();

        // Delete the question itself
        $sql_delete_q = "DELETE FROM questions WHERE id = ? AND quiz_id IS NULL";
        $stmt_delete_q = $conn->prepare($sql_delete_q);
        $stmt_delete_q->bind_param("i", $question_id_to_delete);
        if (!$stmt_delete_q->execute()) throw new Exception("প্রশ্ন ডিলিট করতে সমস্যা: " . $stmt_delete_q->error);
        $affected_rows = $stmt_delete_q->affected_rows;
        $stmt_delete_q->close();

        if ($affected_rows > 0 && $image_row && !empty($image_row['image_url'])) {
            $image_file_path_relative = $image_row['image_url'];
            $image_file_path_actual = realpath(QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL . basename($image_file_path_relative));
            if ($image_file_path_actual && strpos($image_file_path_actual, realpath(QUESTION_IMAGE_UPLOAD_DIR_MANAGE_MANUAL)) === 0 && file_exists($image_file_path_actual)) {
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
    header("Location: manage_manual_questions.php");
    exit;
}


require_once 'includes/header.php';

$manual_questions = [];
// Fetch manual questions (quiz_id IS NULL) along with their categories
$sql_fetch_manual_questions = "
    SELECT q.id, q.question_text, q.explanation, q.image_url, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories_list
    FROM questions q
    LEFT JOIN question_categories qc ON q.id = qc.question_id
    LEFT JOIN categories c ON qc.category_id = c.id
    WHERE q.quiz_id IS NULL
    GROUP BY q.id
    ORDER BY q.id DESC
";
$result_manual_questions = $conn->query($sql_fetch_manual_questions);
if ($result_manual_questions) {
    while ($row = $result_manual_questions->fetch_assoc()) {
        $manual_questions[] = $row;
    }
} else {
    // Handle query error
    $errors[] = "ম্যানুয়াল প্রশ্ন আনতে ডাটাবেস সমস্যা হয়েছে: " . $conn->error;
}

?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* For multi-select */
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
        white-space: normal !important;
    }
     .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        margin-top: 0.3rem !important; 
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
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1><?php echo $page_title; ?></h1>
        <div>
            <a href="add_manual_question.php" class="btn btn-primary">নতুন একক ম্যানুয়াল প্রশ্ন যোগ করুন</a>
            <button type="button" class="btn btn-info" id="toggleBulkImportFormBtn">বাল্ক ইম্পোর্ট ফর্ম দেখান/লুকান</button>
        </div>
    </div>

    <?php display_flash_message(); ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong> অনুগ্রহ করে নিচের সমস্যাগুলো সমাধান করুন:
        <ul><?php foreach ($errors as $error): echo "<li>" . htmlspecialchars($error) . "</li>"; endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div id="bulkImportManualFormContainer" class="card mb-4" style="display: none;">
        <div class="card-header">বাল্ক ম্যানুয়াল প্রশ্ন ইম্পোর্ট করুন</div>
        <div class="card-body">
            <p>প্রশ্ন এবং অপশনগুলো নিচের ফর্ম্যাটে লিখুন। প্রতিটি প্রশ্ন এবং অপশন নতুন লাইনে লিখুন। যেমন:</p>
            <pre class="bg-light p-2 rounded small">
1. প্রথম প্রশ্ন কোনটি?
a. অপশন ক
b. অপশন খ
*c. অপশন গ (সঠিক উত্তরের আগে * চিহ্ন দিন)
d. অপশন ঘ
=এখানে ব্যাখ্যা যুক্ত হবে। না হলে এই লাইনটি বাদ দিন বা খালি রাখুন।

2. দ্বিতীয় প্রশ্ন কী?
a. অপশন ক
*b. অপশন খ
c. অপশন গ
d. অপশন ঘ
=
            </pre>
            <form action="manage_manual_questions.php" method="post">
                <div class="mb-3">
                    <label for="bulk_category_ids" class="form-label">ক্যাটাগরি(সমূহ) নির্বাচন করুন <span class="text-danger">*</span></label>
                    <select class="form-select" id="bulk_category_ids" name="bulk_category_ids[]" multiple="multiple" required>
                        <?php if (!empty($categories_list_for_form)): ?>
                            <?php foreach ($categories_list_for_form as $category_item): ?>
                                <option value="<?php echo $category_item['id']; ?>"><?php echo htmlspecialchars($category_item['name']); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>কোনো ক্যাটাগরি পাওয়া যায়নি।</option>
                        <?php endif; ?>
                    </select>
                    <small class="form-text text-muted">এখানে নির্বাচিত সকল ক্যাটাগরির সাথে নিচের সকল প্রশ্ন লিঙ্ক করা হবে।</small>
                </div>

                <div class="mb-3">
                    <label for="bulk_manual_questions_text" class="form-label">প্রশ্নগুলো এখানে পেস্ট করুন:</label>
                    <textarea class="form-control" id="bulk_manual_questions_text" name="bulk_manual_questions_text" rows="15" placeholder="উপরের ফরম্যাট অনুযায়ী প্রশ্ন ও অপশন লিখুন..." required></textarea>
                </div>
                <button type="submit" name="prepare_manual_questions_from_bulk" class="btn btn-success">প্রশ্নগুলো ইম্পোর্ট করুন</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            ম্যানুয়ালি যোগ করা প্রশ্নসমূহের তালিকা
        </div>
        <div class="card-body">
            <?php if (!empty($manual_questions)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>প্রশ্নের লেখা</th>
                            <th>ক্যাটাগরি</th>
                            <th>ছবি</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manual_questions as $question): ?>
                        <tr>
                            <td><?php echo $question['id']; ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($question['question_text'], 0, 100, "...")); ?></td>
                            <td><?php echo htmlspecialchars($question['categories_list'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($question['image_url'])): ?>
                                    <img src="<?php echo QUESTION_IMAGE_BASE_URL_MANAGE . htmlspecialchars($question['image_url']); ?>" alt="Question Image" style="max-height: 50px; max-width: 70px; cursor: pointer;" onclick="showImageModal('<?php echo QUESTION_IMAGE_BASE_URL_MANAGE . htmlspecialchars($question['image_url']); ?>')">
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit_manual_question.php?question_id=<?php echo $question['id']; ?>" class="btn btn-sm btn-info mb-1">এডিট</a>
                                <a href="manage_manual_questions.php?action=delete&question_id=<?php echo $question['id']; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই প্রশ্নটি এবং এর সাথে সম্পর্কিত সকল ডেটা (অপশন, ক্যাটাগরি লিংক) ডিলিট করতে চান?');">ডিলিট</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center">এখনও কোনো ম্যানুয়াল প্রশ্ন যোগ করা হয়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imagePreviewModalLabel">প্রশ্নের ছবি</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="modalImagePreview" class="img-fluid" alt="Question Image Preview">
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
function showImageModal(imageUrl) {
    document.getElementById('modalImagePreview').src = imageUrl;
    var imageModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    imageModal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    $('#bulk_category_ids').select2({
        theme: "bootstrap-5",
        placeholder: "এক বা একাধিক ক্যাটাগরি নির্বাচন করুন",
        allowClear: true,
        width: '100%'
    });

    const toggleBulkImportFormBtn = document.getElementById('toggleBulkImportFormBtn');
    const bulkImportFormContainer = document.getElementById('bulkImportManualFormContainer');

    if (toggleBulkImportFormBtn && bulkImportFormContainer) {
        toggleBulkImportFormBtn.addEventListener('click', function() {
            if (bulkImportFormContainer.style.display === 'none') {
                bulkImportFormContainer.style.display = 'block';
                this.textContent = 'বাল্ক ইম্পোর্ট ফর্ম লুকান';
                this.classList.remove('btn-info');
                this.classList.add('btn-warning');
            } else {
                bulkImportFormContainer.style.display = 'none';
                this.textContent = 'বাল্ক ইম্পোর্ট ফর্ম দেখান';
                this.classList.remove('btn-warning');
                this.classList.add('btn-info');
            }
        });
    }
});
</script>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>