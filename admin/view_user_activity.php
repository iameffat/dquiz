<?php
// admin/view_user_activity.php
$page_title = "ইউজারের কার্যকলাপ";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$user_id_to_view = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$user_info = null;
$user_attempts = [];

if ($user_id_to_view <= 0) {
    $_SESSION['flash_message'] = "অবৈধ ইউজার ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_users.php");
    exit;
}

// Fetch user details
$sql_user = "SELECT id, name, email, mobile_number, registration_ip, last_login_ip, created_at FROM users WHERE id = ?";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $user_id_to_view);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 1) {
        $user_info = $result_user->fetch_assoc();
        $page_title = "কার্যকলাপ: " . htmlspecialchars($user_info['name']);
    } else {
        $_SESSION['flash_message'] = "ইউজার (ID: {$user_id_to_view}) খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: manage_users.php");
        exit;
    }
    $stmt_user->close();
} else {
    $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (ইউজার তথ্য আনতে)।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_users.php");
    exit;
}

// Fetch user's quiz attempts history
$sql_history = "
    SELECT 
        qa.id as attempt_id,
        q.id as quiz_id,
        q.title as quiz_title,
        qa.score,
        qa.attempt_ip,
        qa.time_taken_seconds,
        qa.submitted_at,
        qa.end_time, /* অ্যাটেম্পট সম্পন্ন হয়েছে কিনা তা পরীক্ষা করার জন্য */
        qa.is_cancelled,
        (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as total_questions_in_quiz
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.user_id = ?
    ORDER BY qa.submitted_at DESC
";

if ($stmt_history = $conn->prepare($sql_history)) {
    $stmt_history->bind_param("i", $user_id_to_view);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row = $result_history->fetch_assoc()) {
        $user_attempts[] = $row;
    }
    $stmt_history->close();
}

require_once 'includes/header.php';
?>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1><?php echo $page_title; ?></h1>
        <a href="manage_users.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> সকল ইউজারে ফিরে যান</a>
    </div>

    <?php display_flash_message(); ?>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-user-circle me-2"></i>ইউজারের সাধারণ তথ্য</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong><i class="fas fa-user me-1"></i> নাম:</strong> <?php echo htmlspecialchars($user_info['name']); ?></p>
                    <p><strong><i class="fas fa-envelope me-1"></i> ইমেইল:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
                    <p><strong><i class="fas fa-mobile-alt me-1"></i> মোবাইল:</strong> <?php echo htmlspecialchars($user_info['mobile_number']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong><i class="fas fa-calendar-alt me-1"></i> রেজিস্ট্রেশন তারিখ:</strong> <?php echo format_datetime($user_info['created_at']); ?></p>
                    <p><strong><i class="fas fa-map-marker-alt me-1"></i> রেজিস্ট্রেশন আইপি:</strong> <?php echo htmlspecialchars($user_info['registration_ip'] ?? 'N/A'); ?></p>
                    <p><strong><i class="fas fa-network-wired me-1"></i> সর্বশেষ লগইন আইপি:</strong> <?php echo htmlspecialchars($user_info['last_login_ip'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-history me-2"></i>কুইজ অ্যাটেম্পট হিস্টোরি</div>
        <div class="card-body">
            <?php if (!empty($user_attempts)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>কুইজের নাম</th>
                            <th>অ্যাটেম্পট তারিখ</th>
                            <th>স্কোর</th>
                            <th>সময় লেগেছে</th>
                            <th>অ্যাটেম্পট আইপি</th>
                            <th>স্ট্যাটাস</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_attempts as $index => $attempt): ?>
                        <tr class="<?php echo $attempt['is_cancelled'] ? 'table-danger opacity-75' : ''; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td><a href="edit_quiz.php?id=<?php echo $attempt['quiz_id']; ?>"><?php echo htmlspecialchars($attempt['quiz_title']); ?></a></td>
                            <td><?php echo $attempt['submitted_at'] ? format_datetime($attempt['submitted_at']) : 'N/A (অসম্পূর্ণ)'; ?></td>
                            <td>
                                <?php 
                                if ($attempt['is_cancelled']) {
                                    echo 'N/A (বাতিল)';
                                } elseif ($attempt['score'] !== null && $attempt['end_time'] !== null) {
                                    echo number_format((float)$attempt['score'], 2) . ' / ' . $attempt['total_questions_in_quiz'];
                                } elseif ($attempt['end_time'] === null) {
                                     echo 'অসম্পূর্ণ';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo $attempt['time_taken_seconds'] !== null ? format_seconds_to_hms($attempt['time_taken_seconds']) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($attempt['attempt_ip'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                if ($attempt['is_cancelled']) {
                                    echo '<span class="badge bg-danger">বাতিলকৃত</span>';
                                } elseif ($attempt['end_time'] !== null && $attempt['score'] !== null) {
                                    echo '<span class="badge bg-success">সম্পন্ন</span>';
                                } else {
                                    echo '<span class="badge bg-warning text-dark">অসম্পূর্ণ</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($attempt['end_time'] !== null || $attempt['is_cancelled']): ?>
                                <a href="../results.php?attempt_id=<?php echo $attempt['attempt_id']; ?>&quiz_id=<?php echo $attempt['quiz_id']; ?>" target="_blank" class="btn btn-sm btn-outline-info mb-1" title="ফলাফল ও উত্তর দেখুন"><i class="fas fa-poll-h"></i> ফলাফল</a>
                                <?php else: ?>
                                    <span class="text-muted">ফলাফল নেই</span>
                                <?php endif; ?>
                                <a href="view_quiz_attempts.php?quiz_id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-sm btn-outline-secondary mb-1" title="এই কুইজের সকল ফলাফল দেখুন"><i class="fas fa-list-ol"></i> কুইজের সকল ফলাফল</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center alert alert-info">এই ইউজার এখনও কোনো কুইজে অংশগ্রহণ করেনি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>