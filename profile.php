<?php
$page_title = "আমার প্রোফাইল";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php'; // Session is started here
require_once 'includes/functions.php';

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's quiz attempts history
$attempts_history = [];
$sql_history = "
    SELECT 
        qa.id as attempt_id,
        q.id as quiz_id,
        q.title as quiz_title,
        qa.score,
        qa.time_taken_seconds,
        qa.submitted_at,
        (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as total_questions_in_quiz
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.user_id = ? AND qa.end_time IS NOT NULL AND qa.score IS NOT NULL
    ORDER BY qa.submitted_at DESC
";

if ($stmt_history = $conn->prepare($sql_history)) {
    $stmt_history->bind_param("i", $user_id);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row = $result_history->fetch_assoc()) {
        $attempts_history[] = $row;
    }
    $stmt_history->close();
} else {
    // Handle error if query preparation fails
    error_log("Error preparing statement for quiz history: " . $conn->error);
}


require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">প্রোফাইল তথ্য</h4>
        </div>
        <div class="card-body">
            <p><strong>নাম:</strong> <?php echo htmlspecialchars($_SESSION["name"]); ?></p>
            <p><strong>ইমেইল:</strong> <?php echo htmlspecialchars($_SESSION["email"]); ?></p>
            <p><strong>মোবাইল নম্বর:</strong> <?php echo htmlspecialchars($_SESSION["mobile_number"]); ?></p>
            
            <a href="edit_profile.php" class="btn btn-secondary w-100 mb-2">প্রোফাইল এডিট করুন</a>
            <a href="change_password.php" class="btn btn-warning w-100 mb-2">পাসওয়ার্ড পরিবর্তন করুন</a> <hr>
            <a href="logout.php" class="btn btn-danger w-100">লগআউট করুন</a>
            <?php if ($_SESSION["role"] === 'admin'): ?>
                <a href="<?php echo $base_url; ?>admin/index.php" class="btn btn-info w-100 mt-2">অ্যাডমিন ড্যাশবোর্ডে যান</a>
            <?php endif; ?>
        </div>
    </div>
</div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">আমার কুইজের ইতিহাস</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($attempts_history)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>কুইজের নাম</th>
                                    <th>তারিখ ও সময়</th>
                                    <th>স্কোর</th>
                                    <th>সময় লেগেছে</th>
                                    <th>একশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts_history as $index => $attempt): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                                    <td><?php echo date("d M Y, h:i A", strtotime($attempt['submitted_at'])); ?></td>
                                    <td><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions_in_quiz']; ?></td>
                                    <td><?php echo $attempt['time_taken_seconds'] ? gmdate("H:i:s", $attempt['time_taken_seconds']) : 'N/A'; ?></td>
                                    <td>
                                        <a href="results.php?attempt_id=<?php echo $attempt['attempt_id']; ?>&quiz_id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-sm btn-outline-primary mb-1" title="ফলাফল দেখুন">ফলাফল</a>
                                        <a href="ranking.php?quiz_id=<?php echo $attempt['quiz_id']; ?>&attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-outline-info mb-1" title="র‍্যাংকিং দেখুন">র‍্যাংকিং</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center alert alert-info">আপনি এখনও কোনো কুইজে অংশগ্রহণ করেননি। <a href="quizzes.php">কুইজগুলোতে অংশগ্রহণ করুন!</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>