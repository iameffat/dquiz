<?php
$page_title = "র‍্যাংকিং";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$current_user_attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : null; // User's own attempt ID, if they just finished

if ($quiz_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ কুইজ ID।";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: quizzes.php");
    exit;
}

// Fetch quiz details (title and live period for ranking context)
$quiz_info = null;
$sql_quiz_info = "SELECT id, title, status, live_end_datetime FROM quizzes WHERE id = ?";
$stmt_quiz_info = $conn->prepare($sql_quiz_info);
$stmt_quiz_info->bind_param("i", $quiz_id);
$stmt_quiz_info->execute();
$result_quiz_info = $stmt_quiz_info->get_result();
if ($result_quiz_info->num_rows === 1) {
    $quiz_info = $result_quiz_info->fetch_assoc();
    $page_title = "র‍্যাংকিং: " . htmlspecialchars($quiz_info['title']);
} else {
    $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id}) খুঁজে পাওয়া যায়নি।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: quizzes.php");
    exit;
}
$stmt_quiz_info->close();

// Fetch all completed attempts for this quiz, ordered for ranking
// Only include attempts where score is not NULL (meaning they completed)
$ranking_data = [];
$sql_ranking = "
    SELECT 
        qa.id as attempt_id,
        qa.user_id,
        u.name as user_name,
        qa.score,
        qa.time_taken_seconds,
        qa.submitted_at 
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    WHERE qa.quiz_id = ? AND qa.score IS NOT NULL AND qa.end_time IS NOT NULL
    ORDER BY qa.score DESC, qa.time_taken_seconds ASC, qa.submitted_at ASC
";
// Note: submitted_at is used as a final tie-breaker if score and time are identical.

$stmt_ranking = $conn->prepare($sql_ranking);
$stmt_ranking->bind_param("i", $quiz_id);
$stmt_ranking->execute();
$result_ranking = $stmt_ranking->get_result();
while ($row = $result_ranking->fetch_assoc()) {
    $ranking_data[] = $row;
}
$stmt_ranking->close();

// Determine current user's rank and if their attempt was "live"
$current_user_rank_info = null;
if ($current_user_attempt_id !== null && isset($_SESSION['user_id'])) {
    $rank_counter = 0;
    foreach ($ranking_data as $rank_item) {
        $rank_counter++;
        if ($rank_item['attempt_id'] == $current_user_attempt_id && $rank_item['user_id'] == $_SESSION['user_id']) {
            $current_user_rank_info = $rank_item;
            $current_user_rank_info['rank'] = $rank_counter;
            break;
        }
    }
}

// Filter for "Official Live Ranking" based on quiz's live_end_datetime
// Users who took an archived quiz will see their overall rank but might not be in the "official" list.
$official_live_ranking_data = [];
$live_cutoff_time = $quiz_info['live_end_datetime'] ? new DateTime($quiz_info['live_end_datetime']) : null;

// If live_end_datetime is null, all attempts are considered for official ranking.
// Otherwise, only attempts submitted AT or BEFORE live_end_datetime are official.
if ($live_cutoff_time === null && $quiz_info['status'] === 'live') { 
    // If it's a live quiz without a specific end time (e.g. ongoing live quiz)
    // For now, all attempts for such 'live' quizzes without end date contribute to main ranking.
    // This rule can be refined based on how "live until manually archived" is handled.
    $official_live_ranking_data = $ranking_data;
} elseif ($live_cutoff_time !== null) {
    foreach ($ranking_data as $attempt) {
        $submission_time = new DateTime($attempt['submitted_at']);
        if ($submission_time <= $live_cutoff_time) {
            $official_live_ranking_data[] = $attempt;
        }
    }
} else { 
    // If quiz is 'archived' or 'draft' and live_end_datetime was not set (or not relevant for ranking period)
    // then all attempts are essentially for practice/archived viewing.
    // In this case, the main ranking table shows all participants.
    $official_live_ranking_data = $ranking_data;
}


require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h2 class="text-center mb-0"><?php echo htmlspecialchars($page_title); ?></h2>
        </div>
        <div class="card-body p-4">

            <?php if ($current_user_rank_info): ?>
            <div class="alert alert-info text-center">
                <h4>আপনার র‍্যাংকিং</h4>
                <p class="fs-5">
                    এই কুইজে আপনার র‍্যাংক: <strong><?php echo $current_user_rank_info['rank']; ?></strong>,
                    স্কোর: <strong><?php echo $current_user_rank_info['score']; ?></strong>,
                    সময়: <strong><?php echo gmdate("H:i:s", $current_user_rank_info['time_taken_seconds']); ?></strong>
                </p>
                <?php
                $user_in_official_list = false;
                foreach ($official_live_ranking_data as $official_entry) {
                    if ($official_entry['attempt_id'] == $current_user_rank_info['attempt_id']) {
                        $user_in_official_list = true;
                        break;
                    }
                }
                if (!$user_in_official_list && $quiz_info['status'] !== 'live' && $live_cutoff_time !== null) { // Check if it was taken as archived
                     echo '<p class="small text-muted">(আপনি এই কুইজটি আর্কাইভ হওয়ার পর অংশগ্রহণ করেছেন, তাই আপনার নাম মূল লাইভ র‍্যাংকিং তালিকায় দেখানো হবে না, তবে এটি আপনার সম্ভাব্য অবস্থান।)</p>';
                } elseif (!$user_in_official_list && $live_cutoff_time !== null) {
                     echo '<p class="small text-muted">(আপনার অংশগ্রহণটি লাইভ কুইজের নির্ধারিত সময়ের পরে হওয়ায়, এটি মূল র‍্যাংকিং তালিকায় অন্তর্ভুক্ত নাও হতে পারে।)</p>';
                }
                ?>
            </div>
            <?php endif; ?>

            <h3 class="mt-4 mb-3 text-center">
                <?php 
                if ($live_cutoff_time !== null && $quiz_info['status'] === 'live') {
                    echo "লাইভ কুইজের চুড়ান্ত র‍্যাংকিং তালিকা";
                } elseif ($quiz_info['status'] === 'archived' || $live_cutoff_time === null) {
                    echo "সামগ্রিক র‍্যাংকিং তালিকা";
                } else {
                     echo "র‍্যাংকিং তালিকা";
                }
                ?>
            </h3>

            <?php if (!empty($official_live_ranking_data)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col"># র‍্যাংক</th>
                            <th scope="col">অংশগ্রহণকারীর নাম</th>
                            <th scope="col">স্কোর</th>
                            <th scope="col">সময় লেগেছে</th>
                            <th scope="col">সাবমিটের সময়</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 0;
                        $last_score = -1;
                        $last_time = -1;
                        $display_rank = 0;
                        foreach ($official_live_ranking_data as $index => $data):
                            $rank++; // Actual iteration count
                            // For display rank (handles ties correctly)
                            if ($data['score'] != $last_score || $data['time_taken_seconds'] != $last_time) {
                                $display_rank = $rank;
                                $last_score = $data['score'];
                                $last_time = $data['time_taken_seconds'];
                            }
                        ?>
                        <tr class="<?php echo ($current_user_attempt_id && $data['attempt_id'] == $current_user_attempt_id) ? 'table-info' : ''; ?>">
                            <th scope="row"><?php echo $display_rank; ?></th>
                            <td><?php echo htmlspecialchars($data['user_name']); ?></td>
                            <td><?php echo $data['score']; ?></td>
                            <td><?php echo gmdate("H:i:s", $data['time_taken_seconds']); ?></td>
                            <td><?php echo date("d M Y, h:i A", strtotime($data['submitted_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center alert alert-warning">এই কুইজের জন্য এখনও কোনো র‍্যাংকিং তৈরি হয়নি অথবা কেউ লাইভ পিরিয়ডে অংশগ্রহণ করেনি।</p>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="quizzes.php" class="btn btn-secondary">সকল কুইজে ফিরে যান</a>
                <?php if ($current_user_attempt_id): ?>
                <a href="results.php?attempt_id=<?php echo $current_user_attempt_id; ?>&quiz_id=<?php echo $quiz_id; ?>" class="btn btn-outline-primary">আমার উত্তর পর্যালোচনা করুন</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>