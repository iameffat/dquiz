<?php
$page_title = "প্রশ্ন ও উত্তর";
$base_url = '';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if ($category_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ ক্যাটাগরি ID।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: categories.php");
    exit;
}

// Fetch category details
$category_name = "Unknown";
$sql_cat = "SELECT name FROM categories WHERE id = ?";
$stmt_cat = $conn->prepare($sql_cat);
$stmt_cat->bind_param("i", $category_id);
$stmt_cat->execute();
$result_cat = $stmt_cat->get_result();
if ($cat_data = $result_cat->fetch_assoc()) {
    $category_name = $cat_data['name'];
    $page_title = "প্রশ্নমালা: " . htmlspecialchars($category_name);
} else {
    $_SESSION['flash_message'] = "ক্যাটাগরি খুঁজে পাওয়া যায়নি।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: categories.php");
    exit;
}
$stmt_cat->close();

// Pagination logic
$records_per_page = 30;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Count total questions for pagination
$sql_count = "SELECT COUNT(DISTINCT q.id) as total
              FROM questions q
              INNER JOIN question_categories qc ON q.id = qc.question_id
              LEFT JOIN quizzes qz ON q.quiz_id = qz.id
              WHERE qc.category_id = ? AND (q.quiz_id IS NULL OR qz.status = 'archived')";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $category_id);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();

// Fetch questions for the current page
$questions = [];
$sql_questions = "SELECT q.id, q.question_text, q.image_url, q.explanation
                  FROM questions q
                  INNER JOIN question_categories qc ON q.id = qc.question_id
                  LEFT JOIN quizzes qz ON q.quiz_id = qz.id
                  WHERE qc.category_id = ? AND (q.quiz_id IS NULL OR qz.status = 'archived')
                  GROUP BY q.id
                  ORDER BY q.id DESC
                  LIMIT ? OFFSET ?";
$stmt_questions = $conn->prepare($sql_questions);
$stmt_questions->bind_param("iii", $category_id, $records_per_page, $offset);
$stmt_questions->execute();
$result_questions = $stmt_questions->get_result();

if ($result_questions) {
    while($q_row = $result_questions->fetch_assoc()) {
        $options = [];
        $sql_options = "SELECT id, option_text, is_correct FROM options WHERE question_id = ? ORDER BY RAND()";
        $stmt_options = $conn->prepare($sql_options);
        $stmt_options->bind_param("i", $q_row['id']);
        $stmt_options->execute();
        $result_options = $stmt_options->get_result();
        while($opt_row = $result_options->fetch_assoc()){
            $options[] = $opt_row;
        }
        $q_row['options'] = $options;
        $questions[] = $q_row;
        $stmt_options->close();
    }
}
$stmt_questions->close();

$page_specific_styles = "
    .question-view-card {
        border-color: var(--bs-border-color-translucent);
    }
    .question-view-card .card-header {
        font-weight: 600;
        background-color: var(--bs-tertiary-bg);
    }
    .option-item {
        transition: background-color 0.3s ease;
    }
    .correct-answer-highlight {
        background-color: #d1e7dd !important; /* bs-success-bg-subtle */
        border-left: 5px solid #198754;
        font-weight: bold;
    }
    body.dark-mode .correct-answer-highlight {
        background-color: var(--bs-success-bg-subtle) !important;
        color: var(--bs-success-text-emphasis) !important;
        border-left-color: var(--bs-success-text-emphasis);
    }
    .question-image-view {
        max-width: 100%;
        height: auto;
        max-height: 300px;
        margin-bottom: 1rem;
        border-radius: .375rem;
        border: 1px solid var(--bs-border-color);
        padding: .25rem;
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>বিষয়: <?php echo htmlspecialchars($category_name); ?></h1>
        <a href="categories.php" class="btn btn-outline-secondary">সকল ক্যাটাগরি</a>
    </div>

    <?php if (empty($questions)): ?>
        <div class="alert alert-info">এই ক্যাটাগরিতে কোনো প্রশ্ন পাওয়া যায়নি।</div>
    <?php else: ?>
        <?php foreach ($questions as $index => $question): ?>
            <div class="card mb-3 question-view-card" id="question-card-<?php echo $question['id']; ?>">
                <div class="card-header">
                    প্রশ্ন <?php echo $offset + $index + 1; ?>: <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($question['image_url'])): ?>
                        <div class="text-center">
                            <img src="<?php echo htmlspecialchars($question['image_url']); ?>" alt="প্রশ্ন সম্পর্কিত ছবি" class="question-image-view">
                        </div>
                    <?php endif; ?>
                    <ul class="list-group">
                        <?php foreach ($question['options'] as $option): ?>
                            <li class="list-group-item option-item" 
                                id="option-<?php echo $option['id']; ?>"
                                <?php if($option['is_correct']) echo 'data-is-correct="true"'; ?>>
                                <?php echo htmlspecialchars($option['option_text']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-success show-answer-btn" data-question-id="<?php echo $question['id']; ?>">সঠিক উত্তর দেখুন</button>
                        <button class="btn btn-sm btn-outline-info show-explanation-btn" data-bs-toggle="modal" data-bs-target="#explanationModal" data-explanation="<?php echo htmlspecialchars($question['explanation']); ?>">ব্যাখ্যা দেখুন</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-4">
                <li class="page-item <?php if($current_page <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="?category_id=<?php echo $category_id; ?>&page=<?php echo $current_page - 1; ?>">পূর্ববর্তী</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if($i == $current_page) echo 'active'; ?>">
                        <a class="page-link" href="?category_id=<?php echo $category_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php if($current_page >= $total_pages) echo 'disabled'; ?>">
                    <a class="page-link" href="?category_id=<?php echo $category_id; ?>&page=<?php echo $current_page + 1; ?>">পরবর্তী</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<div class="modal fade" id="explanationModal" tabindex="-1" aria-labelledby="explanationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="explanationModalLabel">প্রশ্নের ব্যাখ্যা</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="explanationModalBody">
        </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show Correct Answer
    document.querySelectorAll('.show-answer-btn').forEach(button => {
        button.addEventListener('click', function() {
            const questionId = this.dataset.questionId;
            const questionCard = document.getElementById('question-card-' + questionId);
            
            // Remove highlight from all options first
            questionCard.querySelectorAll('.option-item').forEach(opt => {
                opt.classList.remove('correct-answer-highlight');
            });

            // Add highlight to the correct option
            const correctOption = questionCard.querySelector('.option-item[data-is-correct="true"]');
            if (correctOption) {
                correctOption.classList.add('correct-answer-highlight');
            }
        });
    });

    // Show Explanation Modal
    const explanationModal = document.getElementById('explanationModal');
    if(explanationModal) {
        explanationModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const explanation = button.getAttribute('data-explanation');
            const modalBody = explanationModal.querySelector('#explanationModalBody');
            
            if (explanation && explanation.trim() !== '') {
                modalBody.innerHTML = nl2br(explanation);
            } else {
                modalBody.innerHTML = '<p class="text-muted">এই প্রশ্নের জন্য কোনো ব্যাখ্যা যুক্ত করা হয়নি।</p>';
            }
        });
    }

    function nl2br (str) {
        if (typeof str === 'undefined' || str === null) {
            return '';
        }
        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
    }
});
</script>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>