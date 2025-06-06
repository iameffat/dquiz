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

// Fetch quiz details (title, status, quiz_type for ranking logic)
$quiz_info = null;
$sql_quiz_info = "SELECT id, title, status, quiz_type, live_end_datetime FROM quizzes WHERE id = ?";
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

// Initialize page_specific_styles
$page_specific_styles = "
    .rank-gold-row td, body.dark-mode .rank-gold-row td {
        background-color: rgba(255, 215, 0, 0.2) !important;
        color: #856404;
        font-weight: bold;
    }
    body.dark-mode .rank-gold-row td { color: #ffc107; }
    .rank-gold-row .rank-cell { color: #DAA520; }
    body.dark-mode .rank-gold-row .rank-cell { color: #FFD700; }

    .rank-silver-row td, body.dark-mode .rank-silver-row td {
        background-color: rgba(192, 192, 192, 0.25) !important;
        color: #383d41;
        font-weight: bold;
    }
    body.dark-mode .rank-silver-row td { color: #c0c0c0; }
    .rank-silver-row .rank-cell { color: #A9A9A9; }
    body.dark-mode .rank-silver-row .rank-cell { color: #C0C0C0; }

    .rank-bronze-row td, body.dark-mode .rank-bronze-row td {
        background-color: rgba(205, 127, 50, 0.2) !important;
        color: #8B4513;
        font-weight: bold;
    }
    body.dark-mode .rank-bronze-row td { color: #cd7f32; }
    .rank-bronze-row .rank-cell { color: #A0522D; }
    body.dark-mode .rank-bronze-row .rank-cell { color: #CD7F32; }

    .rank-medal { font-size: 1.2em; margin-right: 5px; }
    
    .table-info-user td {
        background-color: var(--bs-table-active-bg) !important; 
        color: var(--bs-table-active-color) !important; 
    }
    body.dark-mode .table-info-user td {
         background-color: var(--bs-info-bg-subtle) !important;
         color: var(--bs-info-text-emphasis) !important;
    }
";

require_once 'includes/header.php';

// Fetch all completed attempts for this quiz
$ranking_data = [];
$sql_ranking = "
    SELECT qa.id as attempt_id, qa.user_id, u.name as user_name, qa.score, qa.time_taken_seconds, qa.submitted_at 
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    WHERE qa.quiz_id = ? AND qa.score IS NOT NULL AND qa.end_time IS NOT NULL
    ORDER BY qa.score DESC, qa.time_taken_seconds ASC, qa.submitted_at ASC
";

$stmt_ranking = $conn->prepare($sql_ranking);
$stmt_ranking->bind_param("i", $quiz_id);
$stmt_ranking->execute();
$result_ranking = $stmt_ranking->get_result();
while ($row = $result_ranking->fetch_assoc()) {
    $ranking_data[] = $row;
}
$stmt_ranking->close();

// Determine current user's rank
$current_user_rank_info = null;
if ($current_user_attempt_id !== null && isset($_SESSION['user_id'])) {
    $temp_last_score = -1;
    $temp_last_time = -1;
    $temp_display_rank = 0;
    $actual_rank_for_user = 0;

    foreach ($ranking_data as $idx => $rank_item_for_user_check) {
        $actual_rank_for_user++;
         if ($rank_item_for_user_check['score'] != $temp_last_score || $rank_item_for_user_check['time_taken_seconds'] != $temp_last_time) {
            $temp_display_rank = $actual_rank_for_user;
            $temp_last_score = $rank_item_for_user_check['score'];
            $temp_last_time = $rank_item_for_user_check['time_taken_seconds'];
        }

        if ($rank_item_for_user_check['attempt_id'] == $current_user_attempt_id && $rank_item_for_user_check['user_id'] == $_SESSION['user_id']) {
            $current_user_rank_info = $rank_item_for_user_check;
            $current_user_rank_info['rank'] = $temp_display_rank;
            break;
        }
    }
}
?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h2 class="text-center mb-0"><?php echo htmlspecialchars($page_title); ?></h2>
        </div>
        <div class="card-body p-4">

            <?php if ($current_user_rank_info): ?>
            <div class="alert alert-info text-center">
                <h4>আপনার ব্যক্তিগত তথ্য</h4>
                <p class="fs-5">
                    এই কুইজে আপনার পজিশন: <strong><?php echo $current_user_rank_info['rank']; ?></strong>,
                    স্কোর: <strong><?php echo number_format($current_user_rank_info['score'], 2); ?></strong>,
                    সময়: <strong><?php echo gmdate("H:i:s", $current_user_rank_info['time_taken_seconds']); ?></strong>
                </p>
            </div>
            <?php endif; ?>

            <?php
            // র‍্যাংকিং তালিকা দেখানোর শর্ত
            $show_ranking_table = false;
            $ranking_unavailable_message = '';

            if ($quiz_info) {
                $quiz_type = $quiz_info['quiz_type'];
                $quiz_status = $quiz_info['status'];
                $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

                if ($is_admin) {
                    $show_ranking_table = true;
                } elseif ($quiz_type === 'weekly') {
                    $show_ranking_table = true;
                } elseif ($quiz_type === 'monthly' || $quiz_type === 'general') {
                    if ($quiz_status === 'archived') {
                        $show_ranking_table = true;
                    } else {
                        $ranking_unavailable_message = "র‍্যাংকিং তালিকা কুইজটি আর্কাইভ হওয়ার পর প্রকাশ করা হবে।";
                    }
                }
            }
            ?>

            <?php if ($show_ranking_table): ?>
                <h3 class="mt-4 mb-3 text-center">সামগ্রিক র‍্যাংকিং তালিকা</h3>
                <?php if (!empty($ranking_data)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col"># র‍্যাংক</th>
                                <th scope="col">অংশগ্রহণকারীর নাম</th>
                                <th scope="col">স্কোর (দশমিক)</th>
                                <th scope="col">সময় লেগেছে</th>
                                <th scope="col">সাবমিটের সময়</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 0;
                            $last_score = -INF;
                            $last_time = -INF;
                            $display_rank = 0;
                            foreach ($ranking_data as $index => $data):
                                $rank++;
                                if ($data['score'] != $last_score || $data['time_taken_seconds'] != $last_time) {
                                    $display_rank = $rank;
                                }
                                $last_score = $data['score'];
                                $last_time = $data['time_taken_seconds'];

                                $row_class = '';
                                $rank_prefix_icon = '';
                                if ($display_rank == 1) {
                                    $row_class = 'rank-gold-row';
                                    $rank_prefix_icon = '<span class="rank-medal">🥇</span>';
                                } elseif ($display_rank == 2) {
                                    $row_class = 'rank-silver-row';
                                    $rank_prefix_icon = '<span class="rank-medal">🥈</span>';
                                } elseif ($display_rank == 3) {
                                    $row_class = 'rank-bronze-row';
                                    $rank_prefix_icon = '<span class="rank-medal">🥉</span>';
                                }

                                if ($current_user_attempt_id && $data['attempt_id'] == $current_user_attempt_id) {
                                    $row_class = trim($row_class . ' table-info-user'); 
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <th scope="row" class="rank-cell"><?php echo $rank_prefix_icon . $display_rank; ?></th>
                                <td><?php echo htmlspecialchars($data['user_name']); ?></td>
                                <td><?php echo number_format($data['score'], 2); ?></td>
                                <td><?php echo gmdate("H:i:s", $data['time_taken_seconds']); ?></td>
                                <td><?php echo date("d M Y, h:i A", strtotime($data['submitted_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center alert alert-warning">এই কুইজের জন্য এখনও কোনো র‍্যাংকিং তৈরি হয়নি অথবা কেউ অংশগ্রহণ করেনি।</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info text-center mt-4">
                    <h5><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-bar-chart-line-fill me-2" viewBox="0 0 16 16"><path d="M11 2a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h1V7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7h1z"/></svg>র‍্যাংকিং অপেক্ষমাণ</h5>
                    <p><?php echo $ranking_unavailable_message; ?></p>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="quizzes.php" class="btn btn-secondary">সকল কুইজে ফিরে যান</a>
                <?php if ($current_user_attempt_id && $quiz_id): ?>
                <a href="results.php?attempt_id=<?php echo $current_user_attempt_id; ?>&quiz_id=<?php echo $quiz_id; ?>" class="btn btn-outline-primary">আমার উত্তর পর্যালোচনা করুন</a>
                <?php endif; ?>
                 <a href="profile.php" class="btn btn-outline-info">আমার প্রোফাইল</a>
            </div>
        </div>
    </div>
</div>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'includes/footer.php';
?>