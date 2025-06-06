<?php
// admin/view_quiz_attempts.php

require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$admin_base_url = '';
$current_user_attempt_id = isset($_GET['highlight_attempt']) ? intval($_GET['highlight_attempt']) : null;
$search_term = isset($_GET['search']) ? trim($conn->real_escape_string($_GET['search'])) : '';

if ($quiz_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ কুইজ ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_quizzes.php");
    exit;
}

// Fetch quiz details
$quiz_info = null;
$sql_quiz_info = "SELECT id, title FROM quizzes WHERE id = ?";
$stmt_quiz_info = $conn->prepare($sql_quiz_info);
if (!$stmt_quiz_info) {
    error_log("Quiz info prepare failed: " . $conn->error);
    $_SESSION['flash_message'] = "একটি অপ্রত্যাশিত ত্রুটি ঘটেছে।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_quizzes.php");
    exit;
}
$stmt_quiz_info->bind_param("i", $quiz_id);
$stmt_quiz_info->execute();
$result_quiz_info = $stmt_quiz_info->get_result();
if ($result_quiz_info->num_rows === 1) {
    $quiz_info = $result_quiz_info->fetch_assoc();
    $page_title = "ফলাফল: " . htmlspecialchars($quiz_info['title']);
} else {
    $_SESSION['flash_message'] = "কুইজ (ID: {$quiz_id}) খুঁজে পাওয়া যায়নি।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_quizzes.php");
    exit;
}
$stmt_quiz_info->close();

// Handle All Actions (Cancel/Reinstate/Delete Attempt, Ban/Unban User)
if (isset($_GET['action']) && (isset($_GET['attempt_id']) || isset($_GET['user_id']))) {
    // ... (Action handling code remains unchanged)
}

// Fetch all completed attempts for this quiz
$attempts_data = [];
$base_sql_attempts = "
    SELECT
        qa.id as attempt_id, qa.user_id, u.name as user_name, u.email as user_email,
        u.address as user_address, u.mobile_number as user_mobile, u.is_banned,
        qa.score, qa.time_taken_seconds, qa.submitted_at, qa.is_cancelled,
        qa.ip_address, qa.browser_name, qa.os_platform
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    WHERE qa.quiz_id = ? AND qa.end_time IS NOT NULL
";
$sql_params_array = [$quiz_id];
$sql_types_string = "i";

if (!empty($search_term)) {
    $base_sql_attempts .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.mobile_number LIKE ?)";
    $search_like_term = "%" . $search_term . "%";
    array_push($sql_params_array, $search_like_term, $search_like_term, $search_like_term);
    $sql_types_string .= "sss";
}
$sql_attempts_final = $base_sql_attempts . " ORDER BY qa.is_cancelled ASC, qa.score DESC, qa.time_taken_seconds ASC, qa.submitted_at ASC";

$stmt_attempts = $conn->prepare($sql_attempts_final);
if ($stmt_attempts) {
    $stmt_attempts->bind_param($sql_types_string, ...$sql_params_array);
    $stmt_attempts->execute();
    $result_attempts = $stmt_attempts->get_result();
    while ($row = $result_attempts->fetch_assoc()) {
        $attempts_data[] = $row;
    }
    $stmt_attempts->close();
} else { 
    error_log("Attempts fetch prepare failed: " . $conn->error);
}

// Prepare emails for copy to clipboard
$participant_emails = [];
foreach ($attempts_data as $attempt) {
    if (!$attempt['is_cancelled'] && !empty($attempt['user_email'])) {
        $participant_emails[] = $attempt['user_email'];
    }
}
$unique_emails = array_unique($participant_emails);
$emails_comma_separated = implode(', ', $unique_emails);

require_once 'includes/header.php';
?>

<style>
    /* ... (CSS Styles remain unchanged from previous version) ... */
    @media print {
        body * { visibility: hidden; }
        #printableArea, #printableArea * { visibility: visible; }
        #printableArea { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
        .admin-sidebar, .admin-header, .admin-footer, .no-print, .page-actions-header, .alert:not(.print-this-alert), #copyAllEmailsBtnViewAttempts { display: none !important; }
        .card { border: 1px solid #ccc !important; box-shadow: none !important; margin-bottom: 15px !important; }
        .table { font-size: 10pt; width: 100%; }
        .table th, .table td { border: 1px solid #ddd !important; padding: 5px 8px; }
        .table thead th { background-color: #f0f0f0 !important; color: #000 !important; }
        .badge { border: 1px solid #ccc !important; padding: 0.2em 0.4em !important; font-size: 0.8em !important; background-color: transparent !important; color: #000 !important; font-weight: normal !important; }
        .print-title { visibility: visible !important; display: block !important; text-align: center; font-size: 18pt; margin-bottom: 20px; color: #000; }
        a[href]:after { content: none !important; }
        .print-only-phone, .print-only-name, .attempt-user-email { display: none !important; }
        body.print-privacy .print-only-phone { display: table-cell !important; } 
        body:not(.print-privacy) .print-only-name { display: table-cell !important; }
        tr.cancelled-attempt-for-print { display: none !important; }
        .participant-details-print-hide { display: none !important; }
        .table tbody tr { page-break-inside: avoid; }
        .rank-gold-row td, .rank-silver-row td, .rank-bronze-row td {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        .rank-gold-row td { background-color: rgba(255, 215, 0, 0.4) !important; }
        .rank-silver-row td { background-color: rgba(192, 192, 192, 0.5) !important; }
        .rank-bronze-row td { background-color: rgba(205, 127, 50, 0.4) !important; }
        
        .print-rank-gold { color: #856404 !important; font-weight: bold; }
        .print-rank-silver { color: #383d41 !important; font-weight: bold; }
        .print-rank-bronze { color: #8B4513 !important; font-weight: bold; }
    }
    .print-only-phone { display: none; }
    .ip-alert-icon { cursor: help; }
    .device-details { font-size: 0.8em; color: #555; }
    body.dark-mode .device-details { color: var(--bs-gray-500); }
    .rank-gold-row td, body.dark-mode .rank-gold-row td { background-color: rgba(255, 215, 0, 0.2) !important; color: #856404; font-weight: bold; }
    body.dark-mode .rank-gold-row td { color: #ffc107; }
    .rank-gold-row .rank-cell, .rank-gold-row .print-rank-gold { color: #DAA520; }
    body.dark-mode .rank-gold-row .rank-cell, body.dark-mode .rank-gold-row .print-rank-gold { color: #FFD700; }
    .rank-silver-row td, body.dark-mode .rank-silver-row td { background-color: rgba(192, 192, 192, 0.25) !important; color: #383d41; font-weight: bold; }
    body.dark-mode .rank-silver-row td { color: #c0c0c0; }
    .rank-silver-row .rank-cell, .rank-silver-row .print-rank-silver { color: #A9A9A9; }
    body.dark-mode .rank-silver-row .rank-cell, body.dark-mode .rank-silver-row .print-rank-silver { color: #C0C0C0; }
    .rank-bronze-row td, body.dark-mode .rank-bronze-row td { background-color: rgba(205, 127, 50, 0.2) !important; color: #8B4513; font-weight: bold; }
    body.dark-mode .rank-bronze-row td { color: #cd7f32; }
    .rank-bronze-row .rank-cell, .rank-bronze-row .print-rank-bronze { color: #A0522D; }
    body.dark-mode .rank-bronze-row .rank-cell, body.dark-mode .rank-bronze-row .print-rank-bronze { color: #CD7F32; }
    .rank-medal { font-size: 1.2em; margin-right: 5px; }
    .table-info-user td { background-color: var(--bs-table-active-bg) !important; color: var(--bs-table-active-color) !important; }
    body.dark-mode .table-info-user td { background-color: var(--bs-info-bg-subtle) !important; color: var(--bs-info-text-emphasis) !important; }
</style>

<div class="container-fluid" id="main-content-area">
    <h1 class="print-title" style="display:none;"><?php echo $page_title; ?></h1>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-3 page-actions-header">
        <h1>কুইজের বিস্তারিত ফলাফল</h1>
        <div>
            <button id="copyEmailsBtn" class="btn btn-success" onclick="copyEmailsToClipboard()">কপি ইমেইল</button>
            <button onclick="prepareAndPrint('name');" class="btn btn-info ms-2">প্রিন্ট (নাম সহ)</button>
            <button onclick="prepareAndPrint('phone');" class="btn btn-outline-info ms-2">প্রাইভেসি প্রিন্ট (ফোন)</button>
            <a href="manage_quizzes.php" class="btn btn-outline-secondary ms-2">সকল কুইজে ফিরে যান</a>
        </div>
    </div>
    
    <textarea id="emailsToCopy" style="position: absolute; left: -9999px; top: -9999px;"><?php echo htmlspecialchars($emails_comma_separated); ?></textarea>

    <div class="card my-3 no-print">
        <div class="card-body">
            <form action="view_quiz_attempts.php" method="get" class="row g-2 align-items-center">
                <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                <div class="col-md-10"><input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="অংশগ্রহণকারীর নাম, ইমেইল বা মোবাইল নম্বর দিয়ে খুঁজুন..." value="<?php echo htmlspecialchars($search_term); ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">খুঁজুন</button></div>
            </form>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div id="printableArea">
        <div class="card">
            <div class="card-header">অংশগ্রহণকারীদের তালিকা ও স্কোর (কুইজ: <?php echo htmlspecialchars($quiz_info['title']); ?>)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="quizAttemptsTable">
                        <thead>
                            <tr>
                                <th># র‍্যাংক</th>
                                <th>অংশগ্রহণকারী</th>
                                <th class="no-print">ঠিকানা</th>
                                <th>স্কোর</th>
                                <th>সময় লেগেছে</th>
                                <th class="no-print">ডিভাইস/আইপি</th>
                                <th class="no-print">সাবমিটের সময়</th>
                                <th class="no-print">স্ট্যাটাস</th>
                                <th class="no-print">একশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Table body generation logic remains unchanged
                            $rank = 0;
                            $last_score = -INF;
                            $last_time = -INF;
                            $display_rank = 0;
                            
                            foreach ($attempts_data as $index => $attempt):
                                $rank_prefix_icon = '';
                                $row_class = '';
                                $name_class = '';

                                if (!$attempt['is_cancelled'] && $attempt['score'] !== null) {
                                    $rank++; 
                                    if ($attempt['score'] != $last_score || $attempt['time_taken_seconds'] != $last_time) { $display_rank = $rank; }
                                    $last_score = $attempt['score']; $last_time = $attempt['time_taken_seconds'];

                                    if ($display_rank == 1) {
                                        $row_class = 'rank-gold-row';
                                        $rank_prefix_icon = '<span class="rank-medal">🥇</span>';
                                        $name_class = 'print-rank-gold';
                                    } elseif ($display_rank == 2) {
                                        $row_class = 'rank-silver-row';
                                        $rank_prefix_icon = '<span class="rank-medal">🥈</span>';
                                        $name_class = 'print-rank-silver';
                                    } elseif ($display_rank == 3) {
                                        $row_class = 'rank-bronze-row';
                                        $rank_prefix_icon = '<span class="rank-medal">🥉</span>';
                                        $name_class = 'print-rank-bronze';
                                    }
                                }

                                if($attempt['is_cancelled']) {
                                    $row_class .= ' table-danger opacity-75';
                                }

                                if ($current_user_attempt_id && $attempt['attempt_id'] == $current_user_attempt_id) {
                                    $row_class = trim($row_class . ' table-info-user');
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="rank-cell"><?php echo (!$attempt['is_cancelled'] && $attempt['score'] !== null) ? $rank_prefix_icon . $display_rank : 'N/A'; ?></td>
                                <td>
                                    <span class="<?php echo $name_class; ?>"><?php echo htmlspecialchars($attempt['user_name']); ?></span>
                                    <small class="d-block text-muted no-print"><?php echo htmlspecialchars($attempt['user_email']); ?></small>
                                    <small class="d-block text-muted no-print"><?php echo htmlspecialchars($attempt['user_mobile']); ?></small>
                                </td>
                                <td class="no-print"><?php echo htmlspecialchars($attempt['user_address'] ?: 'N/A'); ?></td>
                                <td><?php echo $attempt['score'] !== null ? number_format($attempt['score'], 2) : 'N/A'; ?></td>
                                <td><?php echo $attempt['time_taken_seconds'] ? format_seconds_to_hms($attempt['time_taken_seconds']) : 'N/A'; ?></td>
                                <td class="no-print device-details">
                                    <?php echo htmlspecialchars($attempt['browser_name'] ?: 'N/A') . ' (' . htmlspecialchars($attempt['os_platform'] ?: 'N/A') . ')'; ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($attempt['ip_address'] ?: 'N/A'); ?></small>
                                </td>
                                <td class="no-print">
                                    <?php echo $attempt['submitted_at'] ? format_datetime($attempt['submitted_at'], "d M Y, h:i A") : 'N/A'; ?>
                                </td>
                                <td class="no-print">
                                    <?php 
                                    if ($attempt['is_cancelled']) {
                                        echo '<span class="badge bg-danger">বাতিলকৃত</span>';
                                    } else {
                                        echo '<span class="badge bg-success">সক্রিয়</span>';
                                    }
                                    if ($attempt['is_banned'] == 1) {
                                        echo ' <span class="badge bg-warning text-dark">নিষিদ্ধ</span>';
                                    }
                                    ?>
                                </td>
                                <td class="no-print">
                                   <a href="../results.php?attempt_id=<?php echo $attempt['attempt_id']; ?>&quiz_id=<?php echo $quiz_id; ?>" target="_blank" class="btn btn-sm btn-outline-info mb-1" title="উত্তর দেখুন">উত্তর</a>
                                    <?php if ($attempt['is_cancelled']): ?>
                                        <a href="view_quiz_attempts.php?quiz_id=<?php echo $quiz_id; ?>&action=reinstate_attempt&attempt_id=<?php echo $attempt['attempt_id']; ?>&search=<?php echo urlencode($search_term);?>" class="btn btn-sm btn-warning mb-1" title="পুনঃবিবেচনা করুন">পুনঃবিবেচনা</a>
                                    <?php else: ?>
                                        <a href="view_quiz_attempts.php?quiz_id=<?php echo $quiz_id; ?>&action=cancel_attempt&attempt_id=<?php echo $attempt['attempt_id']; ?>&search=<?php echo urlencode($search_term);?>" class="btn btn-sm btn-outline-secondary mb-1" title="বাতিল করুন">বাতিল</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['user_id'] != $attempt['user_id']): ?>
                                        <?php if ($attempt['is_banned'] == 0): ?>
                                            <a href="view_quiz_attempts.php?quiz_id=<?php echo $quiz_id; ?>&action=ban_user&user_id=<?php echo $attempt['user_id']; ?>&search=<?php echo urlencode($search_term);?>" class="btn btn-sm btn-warning mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারকে নিষিদ্ধ করতে চান?');">নিষিদ্ধ</a>
                                        <?php else: ?>
                                            <a href="view_quiz_attempts.php?quiz_id=<?php echo $quiz_id; ?>&action=unban_user&user_id=<?php echo $attempt['user_id']; ?>&search=<?php echo urlencode($search_term);?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারের নিষেধাজ্ঞা তুলে নিতে চান?');">সক্রিয়</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <a href="view_quiz_attempts.php?quiz_id=<?php echo $quiz_id; ?>&action=delete_attempt&attempt_id=<?php echo $attempt['attempt_id']; ?>&search=<?php echo urlencode($search_term);?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই অংশগ্রহণ এবং এর সম্পর্কিত সকল উত্তর স্থায়ীভাবে ডিলিট করতে চান?');" title="অংশগ্রহণ ডিলিট করুন">ডিলিট</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function copyEmailsToClipboard() {
        const emailsTextarea = document.getElementById('emailsToCopy');
        const emailsString = emailsTextarea.value;
        const copyBtn = document.getElementById('copyEmailsBtn');

        if (emailsString && navigator.clipboard) {
            navigator.clipboard.writeText(emailsString).then(function() {
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = 'সফলভাবে কপি হয়েছে!';
                copyBtn.classList.remove('btn-success');
                copyBtn.classList.add('btn-secondary');
                
                setTimeout(function() {
                    copyBtn.innerHTML = originalText;
                    copyBtn.classList.remove('btn-secondary');
                    copyBtn.classList.add('btn-success');
                }, 2000);
            }).catch(function(err) {
                alert('ইমেইল কপি করতে সমস্যা হয়েছে।');
                console.error('Could not copy text: ', err);
            });
        } else if (!navigator.clipboard) {
             alert('আপনার ব্রাউজার এই সুবিধা সমর্থন করে না।');
        } 
        else {
            alert('কপি করার জন্য কোনো ইমেইল পাওয়া যায়নি।');
        }
    }

    function prepareAndPrint(printMode){
        const bodyElement = document.body;
        bodyElement.classList.remove('print-privacy'); 
        if (printMode === 'phone') {
            bodyElement.classList.add('print-privacy');
        }
        window.print();
    }
</script>

<?php
if (isset($conn)) { $conn->close(); }
require_once 'includes/footer.php';
?>