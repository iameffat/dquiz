<?php
// practice_results.php
$base_url = '';
require_once 'includes/db_connect.php'; // For functions, if any, but not for DB saving here
require_once 'includes/functions.php';

$category_id = 0;
$category_name = "অনুশীলন কুইজ";
$submitted_answers = [];
$questions_info_from_post = []; // To store question text, options, and correct answer info passed from form
$total_score_practice = 0;
$total_questions_practice = 0;
$review_questions_practice = [];

$correct_answers_count_chart = 0;
$incorrect_answers_count_chart = 0;
$unanswered_questions_chart = 0;


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_practice_quiz'])) {
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $category_name = isset($_POST['category_name']) ? htmlspecialchars($_POST['category_name']) : "অনুশীলন কুইজ";
    $submitted_answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    $questions_info_from_post = isset($_POST['questions_info']) ? $_POST['questions_info'] : [];

    $total_questions_practice = count($questions_info_from_post);

    foreach ($questions_info_from_post as $q_id => $q_info) {
        $user_selected_option_id = isset($submitted_answers[$q_id]) ? intval($submitted_answers[$q_id]) : null;
        $is_correct_answer = 0;
        $correct_option_id_for_this_q = null;

        $options_for_review = [];
        foreach ($q_info['options_data'] as $opt_id_data => $opt_data) {
            $options_for_review[] = [
                'id' => $opt_id_data,
                'text' => $opt_data['text'],
                'is_correct' => $opt_data['is_correct']
            ];
            if ($opt_data['is_correct'] == 1) {
                $correct_option_id_for_this_q = intval($opt_id_data);
            }
        }

        if ($user_selected_option_id !== null) {
            if ($user_selected_option_id == $correct_option_id_for_this_q) {
                $total_score_practice++;
                $is_correct_answer = 1;
                $correct_answers_count_chart++;
            } else {
                $incorrect_answers_count_chart++;
            }
        } else {
            $unanswered_questions_chart++;
        }
        
        $review_questions_practice[] = [
            'question_id' => $q_id,
            'question_text' => $q_info['text'],
            'image_url' => $q_info['image_url'],
            'explanation' => $q_info['explanation'],
            'user_selected_option_id' => $user_selected_option_id,
            'options_list' => $options_for_review,
            'was_correct_by_user' => $is_correct_answer
        ];
    }
} else {
    // If not a POST request or form not submitted correctly, redirect
    $_SESSION['flash_message'] = "অনুশীলন কুইজের ফলাফল দেখার জন্য সঠিকভাবে কুইজ সাবমিট করুন।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: categories.php");
    exit;
}

$page_title = "অনুশীলন ফলাফল: " . $category_name;
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    /* Copy relevant styles from results.php for consistency, or link to a common CSS file */
    .question-image-review { max-width: 100%; height: auto; max-height: 200px; margin-bottom: 10px; border-radius: 4px; display: block; margin-left: auto; margin-right: auto; border:1px solid #eee; padding: 3px; }
    .feedback-message { padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; font-size: 1.1rem; font-weight: bold; }
    .answer-review .list-group-item { padding: 0.75rem 1.25rem; }
    .answer-review .correct-user-answer { background-color: var(--answer-review-correct-user-bg); color: var(--feedback-very-good-color); border-left: 5px solid var(--feedback-very-good-color); }
    .answer-review .incorrect-user-answer { background-color: var(--answer-review-incorrect-user-bg); color: var(--feedback-improve-color); border-left: 5px solid var(--feedback-improve-color); }
    .answer-review .actual-correct-answer { background-color: var(--answer-review-actual-correct-bg); color: var(--feedback-average-color); border-left: 5px solid var(--feedback-average-color); }
    #quizResultChartContainer { max-width: 450px; height: 300px; margin: 20px auto; }

    @media print {
        body * { visibility: hidden; }
        #printableArea, #printableArea * { visibility: visible; }
        #printableArea { position: absolute; left: 0; top: 0; width: 100%; }
        .card-header, .card-body { border: none !important; box-shadow: none !important; }
        .list-group-item { border: 1px solid #eee !important; }
        .badge { border: 1px solid #ccc; padding: 0.3em 0.5em; background-color: #fff !important; color: #000 !important; }
        .text-success-emphasis { color: #0f5132 !important; } .text-danger-emphasis { color: #581c24 !important; } .text-warning-emphasis { color: #664d03 !important; }
        .btn, .alert:not(.print-header-message), footer, header, .navbar, .footer, .text-center.mt-4, #quizResultChartContainer { display: none !important; }
        .print-header { visibility: visible; text-align: center; margin-bottom: 20px; font-size: 1.4rem; font-weight: bold; width: 100%; }
        .print-header-message { visibility: visible; display: block !important; }
        .answer-review { column-count: 2; column-gap: 20px; font-size: 10pt; }
        .question-image-review { max-height: 120px; margin-bottom: 5px; }
        .answer-review .card { page-break-inside: avoid; break-inside: avoid-column; width: 100%; margin-bottom: 15px !important; font-size: inherit; border: 1px solid #ddd !important; }
        .answer-review .card-header, .answer-review .card-body { padding: 0.5rem !important; font-size: inherit; border: none !important; }
    }
</style>

<div class="container mt-5">
    <?php display_flash_message('flash_message', 'flash_message_type'); ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h2 class="text-center mb-0"><?php echo $page_title; ?></h2>
        </div>
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h3>আপনি পেয়েছেন: <strong class="text-primary"><?php echo number_format($total_score_practice, 0); ?> / <?php echo $total_questions_practice; ?></strong></h3>
            </div>
            
            <div id="quizResultChartContainer" class="mb-4">
                <canvas id="quizResultChart"></canvas>
            </div>

            <hr>
            <div id="printableArea">
                <div class="print-header">অনুশীলন কুইজ: <?php echo htmlspecialchars($category_name); ?></div>
                <h3 class="mt-4 mb-3">উত্তর পর্যালোচনা</h3>
                <div class="answer-review">
                    <?php if (!empty($review_questions_practice)): ?>
                        <?php foreach ($review_questions_practice as $index => $question): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <strong>প্রশ্ন <?php echo $index + 1; ?>:</strong> <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($question['image_url'])): ?>
                                    <div class="mb-2 text-center">
                                        <img src="<?php echo $base_url . escape_html($question['image_url']); ?>" alt="প্রশ্ন সম্পর্কিত ছবি" class="img-fluid question-image-review">
                                    </div>
                                <?php endif; ?>
                                <ul class="list-group list-group-flush">
                                    <?php
                                    $correct_opt_id_review = null;
                                    foreach ($question['options_list'] as $opt) {
                                        if ($opt['is_correct'] == 1) {
                                            $correct_opt_id_review = $opt['id'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php foreach ($question['options_list'] as $option): ?>
                                        <?php
                                        $option_class = 'list-group-item';
                                        $option_label = '';
                                        if ($option['id'] == $question['user_selected_option_id']) {
                                            if ($option['is_correct'] == 1) {
                                                $option_class .= ' correct-user-answer'; 
                                                $option_label = ' <span class="badge bg-success-subtle text-success-emphasis rounded-pill">আপনার সঠিক উত্তর</span>';
                                            } else {
                                                $option_class .= ' incorrect-user-answer';
                                                $option_label = ' <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill">আপনার ভুল উত্তর</span>';
                                            }
                                        } elseif ($option['is_correct'] == 1) {
                                            $option_class .= ' actual-correct-answer';
                                            $option_label = ' <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">সঠিক উত্তর</span>';
                                        }
                                        ?>
                                    <li class="<?php echo $option_class; ?>">
                                        <?php echo htmlspecialchars($option['text']); ?>
                                        <?php echo $option_label; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($question['user_selected_option_id'] === null) : ?>
                                     <p class="mt-2 mb-0 text-warning print-header-message">আপনি এই প্রশ্নের উত্তর দেননি।</p>
                                <?php endif; ?>

                                <?php if (!empty($question['explanation'])): ?>
                                <div class="mt-3 p-2 bg-light border rounded">
                                    <strong>ব্যাখ্যা:</strong> <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="alert alert-info print-header-message">এই ফলাফলের জন্য উত্তর পর্যালোচনা উপলব্ধ নয়।</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center mt-4">
                <button onclick="window.print()" class="btn btn-outline-primary">উত্তরপত্র প্রিন্ট করুন</button>
                <a href="categories.php" class="btn btn-secondary">সকল ক্যাটাগরি দেখুন</a>
                <?php if ($category_id > 0): ?>
                <a href="practice_quiz.php?category_id=<?php echo $category_id; ?>" class="btn btn-info">আবার অনুশীলন করুন</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('quizResultChart');
    if (ctx) {
        const correctAnswers = <?php echo $correct_answers_count_chart; ?>;
        const incorrectAnswers = <?php echo $incorrect_answers_count_chart; ?>;
        const unansweredQuestions = <?php echo $unanswered_questions_chart; ?>;
        const totalQuestionsForChart = <?php echo $total_questions_practice; ?>;

        if (totalQuestionsForChart > 0) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['সঠিক উত্তর', 'ভুল উত্তর', 'উত্তর দেননি'],
                    datasets: [{
                        label: 'ফলাফলের পরিসংখ্যান',
                        data: [correctAnswers, incorrectAnswers, unansweredQuestions],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(201, 203, 207, 0.7)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(201, 203, 207, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: { /* options from your results.php chart */
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { font: { family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'}}},
                        title: { display: true, text: 'অনুশীলন কুইজের পরিসংখ্যান', font: { size: 16, family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'}},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || ''; if (label) { label += ': '; }
                                    if (context.parsed !== null) {
                                        label += context.parsed;
                                        if (totalQuestionsForChart > 0) {
                                            const percentage = ((context.parsed / totalQuestionsForChart) * 100).toFixed(1);
                                            label += ` (${percentage}%)`;
                                        }
                                    } return label;
                                }
                            }, bodyFont: { family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'}, titleFont: { family: 'SolaimanLipi, Noto Sans Bengali, sans-serif'}
                        }
                    }
                }
            });
        } else {
            const chartContainer = document.getElementById('quizResultChartContainer');
            if(chartContainer) {
                chartContainer.innerHTML = '<p class="text-muted text-center">ফলাফল পরিসংখ্যান দেখানোর জন্য কোনো প্রশ্ন ছিল না।</p>';
            }
        }
    }
});
</script>

<?php
if ($conn) { $conn->close(); } // Close if not already closed by includes
require_once 'includes/footer.php';
?>