<?php
$page_title = "ড্যাশবোর্ড";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php'; // Check if admin is logged in
require_once 'includes/header.php';
require_once '../includes/functions.php';

// Fetch existing data
$sql_total_quizzes = "SELECT COUNT(id) as total FROM quizzes";
$result_total_quizzes = $conn->query($sql_total_quizzes);
$total_quizzes = ($result_total_quizzes && $result_total_quizzes->num_rows > 0) ? $result_total_quizzes->fetch_assoc()['total'] : 0;

$sql_total_users = "SELECT COUNT(id) as total FROM users WHERE role='user'";
$result_total_users = $conn->query($sql_total_users);
$total_users = ($result_total_users && $result_total_users->num_rows > 0) ? $result_total_users->fetch_assoc()['total'] : 0;

// 1. Total Quiz Attempts
$sql_total_attempts = "SELECT COUNT(id) as total FROM quiz_attempts WHERE end_time IS NOT NULL";
$result_total_attempts = $conn->query($sql_total_attempts);
$total_attempts = ($result_total_attempts && $result_total_attempts->num_rows > 0) ? $result_total_attempts->fetch_assoc()['total'] : 0;

// 2. Total Questions in the system (all questions)
$sql_total_questions_system = "SELECT COUNT(id) as total FROM questions";
$result_total_questions_system = $conn->query($sql_total_questions_system);
$total_questions_system = ($result_total_questions_system && $result_total_questions_system->num_rows > 0) ? $result_total_questions_system->fetch_assoc()['total'] : 0;

// 3. Number of 'Live' Quizzes
$sql_live_quizzes = "SELECT COUNT(id) as total FROM quizzes WHERE status='live' AND (live_start_datetime IS NULL OR live_start_datetime <= NOW()) AND (live_end_datetime IS NULL OR live_end_datetime >= NOW())";
$result_live_quizzes = $conn->query($sql_live_quizzes);
$live_quizzes_count = ($result_live_quizzes && $result_live_quizzes->num_rows > 0) ? $result_live_quizzes->fetch_assoc()['total'] : 0;

// 4. Number of 'Draft' Quizzes
$sql_draft_quizzes = "SELECT COUNT(id) as total FROM quizzes WHERE status='draft'";
$result_draft_quizzes = $conn->query($sql_draft_quizzes);
$draft_quizzes_count = ($result_draft_quizzes && $result_draft_quizzes->num_rows > 0) ? $result_draft_quizzes->fetch_assoc()['total'] : 0;

// 5. Number of 'Archived' Quizzes
$sql_archived_quizzes = "SELECT COUNT(id) as total FROM quizzes WHERE status='archived' OR (status = 'live' AND live_end_datetime IS NOT NULL AND live_end_datetime < NOW())";
$result_archived_quizzes = $conn->query($sql_archived_quizzes);
$archived_quizzes_count = ($result_archived_quizzes && $result_archived_quizzes->num_rows > 0) ? $result_archived_quizzes->fetch_assoc()['total'] : 0;

// 6. Total Study Materials
$sql_total_study_materials = "SELECT COUNT(id) as total FROM study_materials";
$result_total_study_materials = $conn->query($sql_total_study_materials);
$total_study_materials = ($result_total_study_materials && $result_total_study_materials->num_rows > 0) ? $result_total_study_materials->fetch_assoc()['total'] : 0;

// 7. Total Categories
$sql_total_categories = "SELECT COUNT(id) as total FROM categories";
$result_total_categories = $conn->query($sql_total_categories);
$total_categories = ($result_total_categories && $result_total_categories->num_rows > 0) ? $result_total_categories->fetch_assoc()['total'] : 0;

// 8. Total Manual Questions (quiz_id IS NULL)
$sql_total_manual_questions = "SELECT COUNT(id) as total FROM questions WHERE quiz_id IS NULL";
$result_total_manual_questions = $conn->query($sql_total_manual_questions);
$total_manual_questions = ($result_total_manual_questions && $result_total_manual_questions->num_rows > 0) ? $result_total_manual_questions->fetch_assoc()['total'] : 0;

// ****** আরও নতুন দুটি তথ্য যুক্ত করা হচ্ছে ******
// 9. Total Questions in Quizzes (quiz_id IS NOT NULL)
$sql_total_quiz_questions = "SELECT COUNT(id) as total FROM questions WHERE quiz_id IS NOT NULL";
$result_total_quiz_questions = $conn->query($sql_total_quiz_questions);
$total_quiz_questions = ($result_total_quiz_questions && $result_total_quiz_questions->num_rows > 0) ? $result_total_quiz_questions->fetch_assoc()['total'] : 0;

// 10. Active/Ongoing Quiz Attempts (end_time IS NULL)
$sql_active_attempts = "SELECT COUNT(id) as total FROM quiz_attempts WHERE end_time IS NULL";
$result_active_attempts = $conn->query($sql_active_attempts);
$active_attempts_count = ($result_active_attempts && $result_active_attempts->num_rows > 0) ? $result_active_attempts->fetch_assoc()['total'] : 0;
// ****** আরও নতুন দুটি তথ্য যুক্ত করা শেষ ******

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="mt-4">এডমিন ড্যাশবোর্ড</h1>
            <p>স্বাগতম, <?php echo htmlspecialchars($_SESSION["name"]); ?>! এখান থেকে আপনি কুইজ, ইউজার এবং অন্যান্য সেটিংস পরিচালনা করতে পারবেন।</p>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">মোট কুইজ</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_quizzes; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i> </div>
                    </div>
                     <a href="manage_quizzes.php" class="stretched-link text-decoration-none"><small class="text-muted">বিস্তারিত দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">মোট রেজিস্টার্ড ইউজার</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?> জন</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                     <a href="manage_users.php" class="stretched-link text-decoration-none"><small class="text-muted">বিস্তারিত দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">মোট কুইজ এটেম্পট (সম্পন্ন)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_attempts; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                     <a href="manage_quizzes.php" class="stretched-link text-decoration-none"><small class="text-muted">এটেম্পট বিস্তারিত দেখতে কুইজ ম্যানেজমেন্টে যান</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">মোট প্রশ্ন সংখ্যা (সকল)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_questions_system; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-question-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                     </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">সক্রিয় লাইভ কুইজ</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $live_quizzes_count; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-broadcast-tower fa-2x text-gray-300"></i>
                        </div>
                    </div>
                     <a href="manage_quizzes.php?status_filter=live" class="stretched-link text-decoration-none"><small class="text-muted">লাইভ কুইজ দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">ড্রাফট কুইজ</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $draft_quizzes_count; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-pencil-ruler fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="manage_quizzes.php?status_filter=draft" class="stretched-link text-decoration-none"><small class="text-muted">ড্রাফট দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-dark shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">আর্কাইভড কুইজ</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $archived_quizzes_count; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-archive fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="manage_quizzes.php?status_filter=archived" class="stretched-link text-decoration-none"><small class="text-muted">আর্কাইভ দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2"> <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">মোট স্টাডি ম্যাটেরিয়াল</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_study_materials; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book-open fa-2x text-gray-300"></i> </div>
                    </div>
                    <a href="manage_study_materials.php" class="stretched-link text-decoration-none"><small class="text-muted">বিস্তারিত দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">মোট ক্যাটাগরি</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_categories; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="manage_categories.php" class="stretched-link text-decoration-none"><small class="text-muted">ক্যাটাগরি ম্যানেজ করুন &rarr;</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">ম্যানুয়াল প্রশ্ন</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_manual_questions; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-signature fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="manage_manual_questions.php" class="stretched-link text-decoration-none"><small class="text-muted">ম্যানুয়াল প্রশ্ন দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-purple shadow h-100 py-2"> <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-purple text-uppercase mb-1">কুইজে থাকা মোট প্রশ্ন</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_quiz_questions; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="manage_quizzes.php" class="stretched-link text-decoration-none"><small class="text-muted">কুইজগুলো দেখুন &rarr;</small></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-orange shadow h-100 py-2"> <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-orange text-uppercase mb-1">চলমান কুইজ সেশন</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_attempts_count; ?> টি</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                        </div>
                    </div>
                     <a href="manage_quizzes.php" class="stretched-link text-decoration-none"><small class="text-muted">সেশন বিস্তারিত দেখতে কুইজ ম্যানেজমেন্টে যান</small></a>
                </div>
            </div>
        </div>
        </div>

    <div class="row mt-3">
        <div class="col-lg-12">
            <h4>দ্রুত এক্সেস</h4>
            <div class="list-group">
                <a href="manage_quizzes.php" class="list-group-item list-group-item-action">সকল কুইজ পরিচালনা করুন</a>
                <a href="add_quiz.php" class="list-group-item list-group-item-action">নতুন কুইজ যোগ করুন</a>
                <a href="manage_manual_questions.php" class="list-group-item list-group-item-action">ম্যানুয়াল প্রশ্ন পরিচালনা করুন</a>
                <a href="manage_categories.php" class="list-group-item list-group-item-action">ক্যাটাগরি পরিচালনা করুন</a>
                <a href="manage_study_materials.php" class="list-group-item list-group-item-action">স্টাডি ম্যাটেরিয়ালস পরিচালনা করুন</a>
                <a href="manage_users.php" class="list-group-item list-group-item-action">ইউজার পরিচালনা করুন</a>
                <a href="settings.php" class="list-group-item list-group-item-action">সাইট সেটিংস</a>
            </div>
        </div>
    </div>

</div>
<style>
    /* Custom styles for dashboard cards - can be moved to admin CSS file */
    .card.border-left-primary { border-left: .25rem solid #4e73df!important; }
    .card.border-left-success { border-left: .25rem solid #1cc88a!important; }
    .card.border-left-info { border-left: .25rem solid #36b9cc!important; }
    .card.border-left-warning { border-left: .25rem solid #f6c23e!important; }
    .card.border-left-danger { border-left: .25rem solid #e74a3b!important; }
    .card.border-left-secondary { border-left: .25rem solid #858796!important; }
    .card.border-left-dark { border-left: .25rem solid #5a5c69!important; }
    /* নতুন রঙ যুক্ত করা হলো */
    .card.border-left-purple { border-left: .25rem solid #6f42c1!important; } /* পার্পেল */
    .text-purple { color: #6f42c1!important; }
    .card.border-left-orange { border-left: .25rem solid #fd7e14!important; } /* কমলা */
    .text-orange { color: #fd7e14!important; }


    .text-xs { font-size: .8rem; }
    .text-gray-300 { color: #dddfeb!important; }
    body:not(.dark-mode) .text-gray-800 { color: #5a5c69!important; }
    body.dark-mode .text-gray-800 { color: var(--body-color)!important; }

    .shadow { box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15)!important; }
    .h-100 { height: 100%!important; }
    .py-2 { padding-top: .5rem!important; padding-bottom: .5rem!important; }
    .no-gutters { margin-right: 0; margin-left: 0; }
    .no-gutters>.col, .no-gutters>[class*=col-] { padding-right: 0; padding-left: 0; }
    .align-items-center { align-items: center!important; }
    .mr-2 { margin-right: .5rem!important; }
    .mb-1 { margin-bottom: .25rem!important; }
    .mb-0 { margin-bottom: 0!important; }
    .font-weight-bold { font-weight: 700!important; }
    /* Font Awesome icons */
    /* .fa-list, .fa-users, ..., .fa-tags, .fa-file-signature, .fa-tasks, .fa-hourglass-half */
</style>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'includes/footer.php';
?>