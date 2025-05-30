<?php
$page_title = "ড্যাশবোর্ড";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php'; // Check if admin is logged in
require_once 'includes/header.php';
require_once '../includes/functions.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="mt-4">এডমিন ড্যাশবোর্ড</h1>
            <p>স্বাগতম, <?php echo htmlspecialchars($_SESSION["name"]); ?>! এখান থেকে আপনি কুইজ, ইউজার এবং অন্যান্য সেটিংস পরিচালনা করতে পারবেন।</p>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">মোট কুইজ</div>
                <div class="card-body">
                    <?php
                        $sql_total_quizzes = "SELECT COUNT(id) as total FROM quizzes";
                        $result = $conn->query($sql_total_quizzes);
                        $total_quizzes = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
                    ?>
                    <h5 class="card-title"><?php echo $total_quizzes; ?> টি</h5>
                    <a href="manage_quizzes.php" class="text-white">বিস্তারিত দেখুন &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">মোট রেজিস্টার্ড ইউজার</div>
                <div class="card-body">
                     <?php
                        $sql_total_users = "SELECT COUNT(id) as total FROM users WHERE role='user'";
                        $result_users = $conn->query($sql_total_users);
                        $total_users = ($result_users && $result_users->num_rows > 0) ? $result_users->fetch_assoc()['total'] : 0;
                    ?>
                    <h5 class="card-title"><?php echo $total_users; ?> জন</h5>
                    <a href="manage_users.php" class="text-white">বিস্তারিত দেখুন &rarr;</a>
                </div>
            </div>
        </div>
        </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>