<?php
// admin/view_quiz_attempts.php

require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$admin_base_url = '';
$current_user_attempt_id = isset($_GET['highlight_attempt']) ? intval($_GET['highlight_attempt']) : null; // For highlighting the current user's attempt if redirected from results


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

// Handle Cancel/Reinstate/Delete Attempt Action
if (isset($_GET['action']) && isset($_GET['attempt_id'])) {
    $action = $_GET['action'];
    $attempt_id_to_manage = intval($_GET['attempt_id']);
    $admin_user_id = $_SESSION['user_id'];

    if ($action == 'cancel_attempt') {
        $sql_cancel = "UPDATE quiz_attempts SET is_cancelled = 1, score = NULL, cancelled_by = ? WHERE id = ? AND quiz_id = ?";
        $stmt_cancel = $conn->prepare($sql_cancel);
        if ($stmt_cancel) {
            $stmt_cancel->bind_param("iii", $admin_user_id, $attempt_id_to_manage, $quiz_id);
            if ($stmt_cancel->execute()) {
                $_SESSION['flash_message'] = "অংশগ্রহণটি (ID: {$attempt_id_to_manage}) সফলভাবে বাতিল করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "অংশগ্রহণটি বাতিল করতে সমস্যা হয়েছে: " . $stmt_cancel->error;
                $_SESSION['flash_message_type'] = "danger";
            }
            $stmt_cancel->close();
        } else {
            $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (বাতিল প্রস্তুতি)।";
            $_SESSION['flash_message_type'] = "danger";
        }
    } elseif ($action == 'reinstate_attempt') {
        $sql_reinstate = "UPDATE quiz_attempts SET is_cancelled = 0, cancelled_by = NULL WHERE id = ? AND quiz_id = ?";
        // Note: Score is NOT automatically recalculated here. Admin might need to manually adjust or re-evaluate if needed.
        $stmt_reinstate = $conn->prepare($sql_reinstate);
        if ($stmt_reinstate) {
            $stmt_reinstate->bind_param("ii", $attempt_id_to_manage, $quiz_id);
            if ($stmt_reinstate->execute()) {
                $_SESSION['flash_message'] = "অংশগ্রহণটি (ID: {$attempt_id_to_manage}) সফলভাবে পুনরুদ্ধার করা হয়েছে। অনুগ্রহ করে স্কোর অ্যাডজাস্ট করুন যদি প্রয়োজন হয়।";
                $_SESSION['flash_message_type'] = "info";
            } else {
                $_SESSION['flash_message'] = "অংশগ্রহণটি পুনঃবিবেচনা করতে সমস্যা হয়েছে: " . $stmt_reinstate->error;
                $_SESSION['flash_message_type'] = "danger";
            }
            $stmt_reinstate->close();
        } else {
            $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (পুনঃস্থাপন প্রস্তুতি)।";
            $_SESSION['flash_message_type'] = "danger";
        }
    } elseif ($action == 'delete_attempt') {
        $conn->begin_transaction();
        try {
            // Delete from user_answers first
            $sql_delete_answers = "DELETE FROM user_answers WHERE attempt_id = ?";
            $stmt_delete_answers = $conn->prepare($sql_delete_answers);
            if (!$stmt_delete_answers) throw new Exception("ব্যবহারকারীর উত্তর মোছার জন্য প্রস্তুতিতে সমস্যা: " . $conn->error);
            $stmt_delete_answers->bind_param("i", $attempt_id_to_manage);
            if (!$stmt_delete_answers->execute()) throw new Exception("ব্যবহারকারীর উত্তরগুলো মুছতে সমস্যা হয়েছে: " . $stmt_delete_answers->error);
            $stmt_delete_answers->close();

            // Then delete from quiz_attempts
            $sql_delete_attempt_record = "DELETE FROM quiz_attempts WHERE id = ? AND quiz_id = ?";
            $stmt_delete_attempt_record = $conn->prepare($sql_delete_attempt_record);
            if (!$stmt_delete_attempt_record) throw new Exception("অংশগ্রহণের রেকর্ড মোছার জন্য প্রস্তুতিতে সমস্যা: " . $conn->error);
            $stmt_delete_attempt_record->bind_param("ii", $attempt_id_to_manage, $quiz_id);
            if (!$stmt_delete_attempt_record->execute()) throw new Exception("অংশগ্রহণের রেকর্ডটি মুছতে সমস্যা হয়েছে: " . $stmt_delete_attempt_record->error);
            $stmt_delete_attempt_record->close();
            
            $conn->commit();
            $_SESSION['flash_message'] = "অংশগ্রহণটি (ID: {$attempt_id_to_manage}) এবং এর সাথে সম্পর্কিত উত্তরগুলো সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "অংশগ্রহণটি ডিলিট করার সময় একটি ত্রুটি ঘটেছে: " . $e->getMessage();
            $_SESSION['flash_message_type'] = "danger";
            error_log("Attempt deletion error for attempt ID {$attempt_id_to_manage}: " . $e->getMessage());
        }
    }
    header("Location: view_quiz_attempts.php?quiz_id=" . $quiz_id);
    exit;
}


// Fetch all completed attempts for this quiz
$attempts_data = [];
$ip_counts = []; // For tracking IP usage

$sql_attempts = "
    SELECT
        qa.id as attempt_id,
        qa.user_id,
        u.name as user_name,
        u.mobile_number as user_mobile,
        qa.score,
        qa.time_taken_seconds,
        qa.submitted_at,
        qa.is_cancelled,
        qa.ip_address,
        qa.browser_name,
        qa.os_platform
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    WHERE qa.quiz_id = ? AND qa.end_time IS NOT NULL
    ORDER BY qa.is_cancelled ASC, qa.score DESC, qa.time_taken_seconds ASC, qa.submitted_at ASC
";
// Order by is_cancelled ASC to show non-cancelled attempts first.

$stmt_attempts = $conn->prepare($sql_attempts);
if ($stmt_attempts) {
    $stmt_attempts->bind_param("i", $quiz_id);
    $stmt_attempts->execute();
    $result_attempts = $stmt_attempts->get_result();
    while ($row = $result_attempts->fetch_assoc()) {
        $attempts_data[] = $row;
        // Count IP occurrences
        if (!empty($row['ip_address'])) {
            if (!isset($ip_counts[$row['ip_address']])) {
                $ip_counts[$row['ip_address']] = 0;
            }
            $ip_counts[$row['ip_address']]++;
        }
    }
    $stmt_attempts->close();
} else {
    error_log("Attempts fetch prepare failed: " . $conn->error);
    // Optionally set a flash message here if needed
}

// Determine the highest score among non-cancelled attempts
$highest_score = null;
if (!empty($attempts_data)) {
    $non_cancelled_scores = [];
    foreach ($attempts_data as $attempt) {
        if (!$attempt['is_cancelled'] && $attempt['score'] !== null) {
            $non_cancelled_scores[] = $attempt['score'];
        }
    }
    if (!empty($non_cancelled_scores)) {
        $highest_score = max($non_cancelled_scores);
    }
}

// Function to mask phone number for privacy print
function mask_phone_for_print($phone) {
    if (empty($phone)) {
        return 'N/A';
    }
    $phone_len = strlen($phone);
    if ($phone_len == 11) { // Bangladeshi numbers
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    } elseif ($phone_len > 7) { // Generic masking for other numbers
        return substr($phone, 0, 3) . str_repeat('*', $phone_len - 6) . substr($phone, -3);
    } elseif ($phone_len > 3) {
        return substr($phone, 0, 1) . str_repeat('*', $phone_len - 2) . substr($phone, -1);
    } else {
        return str_repeat('*', $phone_len);
    }
}


require_once 'includes/header.php';
?>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #printableArea, #printableArea * {
            visibility: visible;
        }
        #printableArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 20px;
        }
        .admin-sidebar, .admin-header, .admin-footer, .no-print, .page-actions-header, .alert:not(.print-this-alert) {
            display: none !important;
        }
        .card {
            border: 1px solid #ccc !important;
            box-shadow: none !important;
            margin-bottom: 15px !important;
        }
        .table {
            font-size: 10pt;
            width: 100%;
        }
        .table th, .table td {
            border: 1px solid #ddd !important;
            padding: 5px 8px;
        }
        .table thead th {
            background-color: #f0f0f0 !important;
            color: #000 !important;
        }
        .badge {
            border: 1px solid #ccc !important;
            padding: 0.2em 0.4em !important;
            font-size: 0.8em !important;
            background-color: transparent !important;
            color: #000 !important;
            font-weight: normal !important;
        }
        .print-title {
            visibility: visible !important;
            display: block !important;
            text-align: center;
            font-size: 18pt;
            margin-bottom: 20px;
            color: #000;
        }
        a[href]:after {
            content: none !important;
        }
        .print-only-phone, .print-only-name { display: none; } 

        body.print-privacy .print-only-phone { display: table-cell !important; } 
        body:not(.print-privacy) .print-only-name { display: table-cell !important; } 

        body.print-privacy .participant-col-print-name { display: none !important; }
        body:not(.print-privacy) .participant-col-print-phone { display: none !important; }
        
        /* Hide cancelled attempts when printing with names */
        body:not(.print-privacy) tr.cancelled-attempt-for-print {
            display: none !important;
        }

        .table tbody tr {
            page-break-inside: avoid; 
        }
    }
    .ip-alert-icon {
        cursor: help;
    }
    .device-details {
        font-size: 0.8em;
        color: #555;
    }
    body.dark-mode .device-details {
        color: var(--bs-gray-500);
    }
     /* Medal and Highlight Styles */
    .rank-gold-row td, body.dark-mode .rank-gold-row td { background-color: rgba(255, 215, 0, 0.2) !important; color: #856404; font-weight: bold; }
    body.dark-mode .rank-gold-row td { color: #ffc107; }
    .rank-gold-row .rank-cell { color: #DAA520; }
    body.dark-mode .rank-gold-row .rank-cell { color: #FFD700; }

    .rank-silver-row td, body.dark-mode .rank-silver-row td { background-color: rgba(192, 192, 192, 0.25) !important; color: #383d41; font-weight: bold; }
    body.dark-mode .rank-silver-row td { color: #c0c0c0; }
    .rank-silver-row .rank-cell { color: #A9A9A9; }
    body.dark-mode .rank-silver-row .rank-cell { color: #C0C0C0; }

    .rank-bronze-row td, body.dark-mode .rank-bronze-row td { background-color: rgba(205, 127, 50, 0.2) !important; color: #8B4513; font-weight: bold; }
    body.dark-mode .rank-bronze-row td { color: #cd7f32; }
    .rank-bronze-row .rank-cell { color: #A0522D; }
    body.dark-mode .rank-bronze-row .rank-cell { color: #CD7F32; }

    .rank-medal { font-size: 1.2em; margin-right: 5px; }
    
    .table-info-user td { background-color: var(--bs-table-active-bg) !important; color: var(--bs-table-active-color) !important; }
    body.dark-mode .table-info-user td { background-color: var(--bs-info-bg-subtle) !important; color: var(--bs-info-text-emphasis) !important; }
</style>

<div class="container-fluid" id="main-content-area">
    <h1 class="print-title" style="display:none;"><?php echo $page_title; ?></h1>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-3 page-actions-header">
        <h1>কুইজের বিস্তারিত ফলাফল</h1>
        <div>
            <button onclick="prepareAndPrint('name');" class="btn btn-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer-fill" viewBox="0 0 16 16">
                  <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1"/>
                  <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2H2a2 2 0 0 1-2-2zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
                </svg>
                ফলাফল প্রিন্ট করুন (নাম সহ)
            </button>
            <button onclick="prepareAndPrint('phone');" class="btn btn-outline-info ms-2">
                 <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2.5a.5.5 0 0 1-.5-.5V7.5a.5.5 0 0 1 .5-.5H3v-1zM11 4H5v1h6zM2.5 7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2v-1.5a.5.5 0 0 1 .5-.5zm1.498 7.157a.5.5 0 0 1-.707 0l-1.002-1.001a.5.5 0 1 1 .707-.708l1.001 1.001a.5.5 0 0 1 0 .707M11.5 14a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1 0-1h3a.5.5 0 0 1 .5.5"/>
                    <path d="M13.5 9a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-.5.5h-1v-1.335a3.5 3.5 0 0 0-3.5-3.5H5.335v-1H13.5z"/>
                 </svg>
                প্রাইভেসি প্রিন্ট (ফোন নম্বর সহ)
            </button>
            <a href="manage_quizzes.php" class="btn btn-outline-secondary ms-2">সকল কুইজে ফিরে যান</a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div id="printableArea">
        <div class="card">
            <div class="card-header">
                অংশগ্রহণকারীদের তালিকা ও স্কোর (কুইজ: <?php echo htmlspecialchars($quiz_info['title']); ?>)
            </div>
            <div class="card-body">
                <?php
                $duplicate_ips_found = [];
                foreach($ip_counts as $ip => $count) {
                    if ($count > 1) {
                        $duplicate_ips_found[$ip] = $count;
                    }
                }

                if (!empty($duplicate_ips_found)) {
                    echo '<div class="alert alert-warning no-print"><strong>সতর্কতা:</strong> কিছু আইপি অ্যাড্রেস একাধিকবার ব্যবহৃত হয়েছে: ';
                    $ip_messages = [];
                    foreach($duplicate_ips_found as $ip => $count) {
                        $ip_messages[] = htmlspecialchars($ip) . ' (' . $count . ' বার)';
                    }
                    echo implode(', ', $ip_messages);
                    echo '. অনুগ্রহ করে এটি পর্যালোচনা করুন।</div>';
                }
                ?>
                <?php if ($highest_score !== null): ?>
                    <div class="alert alert-info no-print">
                        এই কুইজে সর্বোচ্চ প্রাপ্ত নম্বর: <strong><?php echo number_format($highest_score, 2); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if (!empty($attempts_data)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th># র‍্যাংক</th>
                                <th class="participant-col-print-name">অংশগ্রহণকারীর নাম (ID)</th>
                                <th class="participant-col-print-phone" style="display:none;">অংশগ্রহণকারী (ফোন)</th>
                                <th>স্কোর</th>
                                <th>সময় লেগেছে</th>
                                <th>সাবমিটের সময়</th>
                                <th class="no-print">আইপি অ্যাড্রেস</th>
                                <th class="no-print">ডিভাইস/ব্রাউজার</th>
                                <th class="no-print">স্ট্যাটাস</th>
                                <th class="no-print">একশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 0;
                            $last_score = -INF;
                            $last_time = -INF;
                            $display_rank = 0;
                            
                            foreach ($attempts_data as $index => $attempt):
                                $row_classes_array = []; // Reset for each row

                                if (!$attempt['is_cancelled'] && $attempt['score'] !== null) {
                                    $rank++;
                                    if ($attempt['score'] != $last_score || $attempt['time_taken_seconds'] != $last_time) {
                                        $display_rank = $rank;
                                    }
                                    $last_score = $attempt['score'];
                                    $last_time = $attempt['time_taken_seconds'];

                                    if ($display_rank == 1) {
                                        $row_classes_array[] = 'rank-gold-row';
                                    } elseif ($display_rank == 2) {
                                        $row_classes_array[] = 'rank-silver-row';
                                    } elseif ($display_rank == 3) {
                                        $row_classes_array[] = 'rank-bronze-row';
                                    }
                                    // Highlight highest score even if not top 3 by rank (e.g. if many people have same highest score but different times)
                                    elseif ($attempt['score'] == $highest_score && $highest_score > 0) {
                                         $row_classes_array[] = 'table-success'; // General success highlight
                                    }
                                }
                                
                                $action_buttons_html = ''; 

                                if ($attempt['is_cancelled']) {
                                    $row_classes_array[] = 'table-danger';
                                    $row_classes_array[] = 'opacity-75';
                                    $row_classes_array[] = 'cancelled-attempt-for-print'; // For hiding in name-print
                                    $status_text = '<span class="badge bg-danger">বাতিলকৃত</span>';
                                    $action_buttons_html = '<a href="view_quiz_attempts.php?quiz_id='.$quiz_id.'&action=reinstate_attempt&attempt_id='.$attempt['attempt_id'].'" class="btn btn-sm btn-warning mb-1 no-print" onclick="return confirm(\'আপনি কি নিশ্চিতভাবে এই অংশগ্রহণটি পুনঃবিবেচনা করতে চান?\');" title="পুনঃবিবেচনা করুন">পুনঃবিবেচনা</a>';
                                } else {
                                    $status_text = '<span class="badge bg-success">সক্রিয়</span>';
                                    $action_buttons_html = '<a href="view_quiz_attempts.php?quiz_id='.$quiz_id.'&action=cancel_attempt&attempt_id='.$attempt['attempt_id'].'" class="btn btn-sm btn-danger mb-1 no-print" onclick="return confirm(\'আপনি কি নিশ্চিতভাবে এই অংশগ্রহণটি বাতিল করতে চান? বাতিল করলে স্কোর মুছে যাবে এবং র‍্যাংকিং-এ দেখানো হবে না।\');" title="বাতিল করুন">বাতিল</a>';
                                }
                                
                                // Current user highlighting
                                if ($current_user_attempt_id && $attempt['attempt_id'] == $current_user_attempt_id) {
                                    if (!in_array('rank-gold-row', $row_classes_array) &&
                                        !in_array('rank-silver-row', $row_classes_array) &&
                                        !in_array('rank-bronze-row', $row_classes_array) &&
                                        !in_array('cancelled-attempt-for-print', $row_classes_array)) { // Do not highlight if cancelled
                                        $row_classes_array[] = 'table-info-user';
                                    }
                                }
                                
                                // Duplicate IP warning class
                                if (!empty($attempt['ip_address']) && isset($ip_counts[$attempt['ip_address']]) && $ip_counts[$attempt['ip_address']] > 1) {
                                     if(!in_array('table-warning', $row_classes_array)) { // Avoid duplicate
                                        $row_classes_array[] = 'table-warning';
                                    }
                                }

                                $action_buttons_html .= ' <a href="view_quiz_attempts.php?quiz_id='.$quiz_id.'&action=delete_attempt&attempt_id='.$attempt['attempt_id'].'" class="btn btn-sm btn-outline-danger mb-1 no-print" onclick="return confirm(\'আপনি কি নিশ্চিতভাবে এই অংশগ্রহণ এবং এর সম্পর্কিত সকল উত্তর স্থায়ীভাবে ডিলিট করতে চান? এই কাজটি ফেরানো যাবে না।\');" title="অংশগ্রহণ ডিলিট করুন">ডিলিট</a>';
                                $action_buttons_html .= ' <a href="../results.php?attempt_id='.$attempt['attempt_id'].'&quiz_id='.$quiz_id.'" target="_blank" class="btn btn-sm btn-outline-info mb-1 no-print" title="উত্তর দেখুন">উত্তর দেখুন</a>';

                                $ip_display = !empty($attempt['ip_address']) ? htmlspecialchars($attempt['ip_address']) : 'N/A';
                                $ip_warning_icon = '';
                                if (!empty($attempt['ip_address']) && isset($ip_counts[$attempt['ip_address']]) && $ip_counts[$attempt['ip_address']] > 1) {
                                    $ip_warning_icon = ' <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="orange" class="bi bi-exclamation-triangle-fill ip-alert-icon" viewBox="0 0 16 16" title="এই আইপি থেকে ' . $ip_counts[$attempt['ip_address']] . ' বার পরীক্ষা দেওয়া হয়েছে।">
                                                            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                                                          </svg>';
                                }
                                
                                $device_browser_info_screen = '';
                                if (!empty($attempt['browser_name'])) { $device_browser_info_screen .= htmlspecialchars($attempt['browser_name']); }
                                if (!empty($attempt['os_platform'])) { $device_browser_info_screen .= ($device_browser_info_screen ? ' <small class="text-muted">(' . htmlspecialchars($attempt['os_platform']) . ')</small>' : htmlspecialchars($attempt['os_platform'])); }
                                if (empty($device_browser_info_screen)) { $device_browser_info_screen = 'N/A'; }

                                $final_row_class_string = implode(' ', array_unique($row_classes_array));
                            ?>
                            <tr class="<?php echo trim($final_row_class_string); ?>">
                                <td class="rank-cell"><?php echo (!$attempt['is_cancelled'] && $attempt['score'] !== null) ? ($display_rank == 1 ? '🥇' : ($display_rank == 2 ? '🥈' : ($display_rank == 3 ? '🥉' : ''))) . $display_rank : 'N/A'; ?></td>
                                <td class="participant-col-print-name print-only-name">
                                    <?php echo htmlspecialchars($attempt['user_name']); ?> (ID: <?php echo $attempt['user_id']; ?>)
                                </td>
                                <td class="participant-col-print-phone print-only-phone" style="display:none;">
                                    <?php echo htmlspecialchars(mask_phone_for_print($attempt['user_mobile'])); ?> (ID: <?php echo $attempt['user_id']; ?>)
                                </td>
                                <td><?php echo $attempt['score'] !== null ? number_format($attempt['score'], 2) : 'N/A'; ?></td>
                                <td><?php echo $attempt['time_taken_seconds'] ? format_seconds_to_hms($attempt['time_taken_seconds']) : 'N/A'; ?></td>
                                <td><?php echo format_datetime($attempt['submitted_at']); ?></td>
                                <td class="no-print"><?php echo $ip_display . $ip_warning_icon; ?></td>
                                <td class="no-print device-details"><?php echo $device_browser_info_screen; ?></td>
                                <td class="no-print"><?php echo $status_text; ?></td>
                                <td class="no-print">
                                    <?php echo $action_buttons_html; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center">এই কুইজে এখনও কেউ অংশগ্রহণ করেনি অথবা কোনো ফলাফল পাওয়া যায়নি।</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    function prepareAndPrint(printMode){
        const printTitleElement = document.querySelector('h1.print-title');
        const bodyElement = document.body;

        if (printTitleElement) {
            printTitleElement.style.display = 'block'; // Show title for print
        }

        // Remove existing print-related classes to reset state
        bodyElement.classList.remove('print-privacy'); 
        document.querySelectorAll('.participant-col-print-name').forEach(el => el.style.display = ''); // Reset display
        document.querySelectorAll('.participant-col-print-phone').forEach(el => el.style.display = 'none'); // Ensure phone is hidden by default

        if (printMode === 'phone') {
            bodyElement.classList.add('print-privacy');
            // CSS will handle display via body.print-privacy
        } else { // Default to 'name' print mode
            // CSS will handle display via body:not(.print-privacy)
        }
        
        window.print();

        // Optional: Reset title display after a short delay if needed for screen view
        // However, if this page is only for viewing attempts, title can remain.
        // setTimeout(() => {
        // if (printTitleElement) { printTitleElement.style.display = 'none'; }
        // }, 500); 
    }
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'includes/footer.php';
?>