<?php
$page_title = "ইউজার ম্যানেজমেন্ট";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
    $user_id_to_delete = intval($_GET['user_id']);
    
    if ($user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['flash_message'] = "আপনি নিজেকে ডিলিট করতে পারবেন না।";
        $_SESSION['flash_message_type'] = "warning";
    } else {
        $conn->begin_transaction();
        try {
            // ১. এই ইউজারের quiz_attempts থেকে cancelled_by কলাম থেকে এই ইউজার আইডি NULL করুন (যদি তারা অ্যাডমিন হয়ে অন্য কারো অ্যাটেম্পট বাতিল করে থাকে)
            $sql_nullify_cancelled_by = "UPDATE quiz_attempts SET cancelled_by = NULL WHERE cancelled_by = ?";
            $stmt_nullify_cb = $conn->prepare($sql_nullify_cancelled_by);
            $stmt_nullify_cb->bind_param("i", $user_id_to_delete);
            $stmt_nullify_cb->execute();
            $stmt_nullify_cb->close();

            // ২. এই ইউজারের সকল user_answers ডিলিট করুন
            //    (যেহেতু user_answers এর সাথে quiz_attempts.id এর একটি সম্পর্ক আছে, 
            //     তাই quiz_attempts ডিলিট করার আগে এটি করা ভালো, অথবা quiz_attempts ডিলিট করলে ON DELETE CASCADE সেট করতে হবে)
            $sql_delete_user_answers_direct = "DELETE ua FROM user_answers ua JOIN quiz_attempts qa ON ua.attempt_id = qa.id WHERE qa.user_id = ?";
            $stmt_dua_direct = $conn->prepare($sql_delete_user_answers_direct);
            $stmt_dua_direct->bind_param("i", $user_id_to_delete);
            $stmt_dua_direct->execute();
            $stmt_dua_direct->close();
            
            // ৩. এই ইউজারের সকল quiz_attempts ডিলিট করুন
            $sql_delete_attempts = "DELETE FROM quiz_attempts WHERE user_id = ?";
            $stmt_delete_attempts = $conn->prepare($sql_delete_attempts);
            $stmt_delete_attempts->bind_param("i", $user_id_to_delete);
            $stmt_delete_attempts->execute();
            $stmt_delete_attempts->close();

            // ৪. quizzes এবং study_materials থেকে created_by/uploaded_by ফিল্ড NULL করুন
            $sql_nullify_quizzes = "UPDATE quizzes SET created_by = NULL WHERE created_by = ?";
            $stmt_nullify_q = $conn->prepare($sql_nullify_quizzes);
            $stmt_nullify_q->bind_param("i", $user_id_to_delete);
            $stmt_nullify_q->execute();
            $stmt_nullify_q->close();

            $sql_nullify_materials = "UPDATE study_materials SET uploaded_by = NULL WHERE uploaded_by = ?";
            $stmt_nullify_sm = $conn->prepare($sql_nullify_materials);
            $stmt_nullify_sm->bind_param("i", $user_id_to_delete);
            $stmt_nullify_sm->execute();
            $stmt_nullify_sm->close();
            
            // ৫. সবশেষে ইউজারকে users টেবিল থেকে ডিলিট করুন
            $sql_delete_user = "DELETE FROM users WHERE id = ?";
            if ($stmt_delete = $conn->prepare($sql_delete_user)) {
                $stmt_delete->bind_param("i", $user_id_to_delete);
                if (!$stmt_delete->execute()) {
                     throw new Exception("ইউজার ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete->error);
                }
                $stmt_delete->close();
            } else {
                throw new Exception("ডাটাবেস সমস্যা (ইউজার ডিলিট প্রস্তুতি): " . $conn->error);
            }

            $conn->commit();
            $_SESSION['flash_message'] = "ইউজার (ID: {$user_id_to_delete}) এবং তার সম্পর্কিত ডেটা সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "ইউজার ডিলিট করার সময় একটি ত্রুটি ঘটেছে: " . $e->getMessage();
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    header("Location: manage_users.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit;
}

require_once 'includes/header.php';

// --- সার্চ লজিক ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = [];

// নতুন: প্রতি পৃষ্ঠায় কয়টি ইউজার দেখানো হবে
$users_per_page = 20;
// বর্তমান পৃষ্ঠা নম্বর (যদি সেট না থাকে তবে ১)
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
// OFFSET হিসাব
$offset = ($current_page - 1) * $users_per_page;

$total_users_count = 0;

if (!empty($search_term)) {
    // সার্চের জন্য মোট ইউজার সংখ্যা গণনা
    $sql_count_search = "SELECT COUNT(id) as total FROM users 
                         WHERE name LIKE ? OR email LIKE ? OR mobile_number LIKE ? OR registration_ip LIKE ? OR last_login_ip LIKE ?";
    if ($stmt_count_search = $conn->prepare($sql_count_search)) {
        $search_like = "%" . $search_term . "%";
        $stmt_count_search->bind_param("sssss", $search_like, $search_like, $search_like, $search_like, $search_like);
        $stmt_count_search->execute();
        $total_users_count = $stmt_count_search->get_result()->fetch_assoc()['total'];
        $stmt_count_search->close();
    }

    // সার্চ করা ইউজারদের তালিকা (পেজিনেশন সহ)
    $sql_users = "SELECT id, name, email, mobile_number, role, registration_ip, last_login_ip, created_at FROM users 
                  WHERE name LIKE ? OR email LIKE ? OR mobile_number LIKE ? OR registration_ip LIKE ? OR last_login_ip LIKE ?
                  ORDER BY created_at DESC
                  LIMIT ? OFFSET ?";
    if ($stmt_search = $conn->prepare($sql_users)) {
        $search_like = "%" . $search_term . "%";
        $stmt_search->bind_param("sssssii", $search_like, $search_like, $search_like, $search_like, $search_like, $users_per_page, $offset);
        $stmt_search->execute();
        $result_users = $stmt_search->get_result();
        while ($row = $result_users->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt_search->close();
    } else {
        echo '<div class="alert alert-danger">সার্চ করতে ডাটাবেস সমস্যা হয়েছে: ' . $conn->error . '</div>';
    }
} else {
    // সকল ইউজারের মোট সংখ্যা গণনা
    $sql_total_count = "SELECT COUNT(id) as total FROM users";
    $result_total_count = $conn->query($sql_total_count);
    $total_users_count = $result_total_count ? $result_total_count->fetch_assoc()['total'] : 0;

    // সকল ইউজার (পেজিনেশন সহ)
    $sql_users = "SELECT id, name, email, mobile_number, role, registration_ip, last_login_ip, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?";
    if ($stmt_paginate = $conn->prepare($sql_users)) {
        $stmt_paginate->bind_param("ii", $users_per_page, $offset);
        $stmt_paginate->execute();
        $result_users = $stmt_paginate->get_result();
        if ($result_users) {
            while ($row = $result_users->fetch_assoc()) {
                $users[] = $row;
            }
        }
        $stmt_paginate->close();
    }
}

$total_pages = ceil($total_users_count / $users_per_page);

?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1>ইউজার ম্যানেজমেন্ট</h1>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            ইউজার খুঁজুন
        </div>
        <div class="card-body">
            <form action="manage_users.php" method="get" class="row g-3 align-items-center">
                <div class="col-md-10">
                    <label for="search" class="visually-hidden">সার্চ করুন (নাম, ইমেইল, মোবাইল, আইপি)</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="নাম, ইমেইল, মোবাইল নম্বর বা আইপি দিয়ে খুঁজুন..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">খুঁজুন</button>
                </div>
            </form>
             <?php if (!empty($search_term) && empty($users)): ?>
                <p class="mt-3 text-center text-warning">"<?php echo htmlspecialchars($search_term); ?>" এর জন্য কোনো ইউজার খুঁজে পাওয়া যায়নি।</p>
            <?php elseif (!empty($search_term) && !empty($users)): ?>
                 <p class="mt-3 text-muted">"<?php echo htmlspecialchars($search_term); ?>" এর জন্য <?php echo $total_users_count; ?> টি ফলাফল পাওয়া গেছে। <a href="manage_users.php">সকল ইউজার দেখুন</a></p>
            <?php endif; ?>
        </div>
    </div>


    <?php display_flash_message(); ?>

    <div class="card">
        <div class="card-header">
            সকল রেজিস্টার্ড ইউজারের তালিকা (মোট: <?php echo $total_users_count; ?>)
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>নাম</th>
                            <th>ইমেইল</th>
                            <th>মোবাইল</th>
                            <th>ভূমিকা</th>
                            <th>রেজিঃ আইপি</th>
                            <th>শেষ লগইন আইপি</th>
                            <th>রেজিঃ তারিখ</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['mobile_number']); ?></td>
                            <td>
                                <?php 
                                if ($user['role'] == 'admin') echo '<span class="badge bg-danger">এডমিন</span>';
                                else echo '<span class="badge bg-success">ইউজার</span>';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['registration_ip'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['last_login_ip'] ?? 'N/A'); ?></td>
                            <td><?php echo format_datetime($user['created_at']); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info mb-1" title="এডিট করুন"><i class="fas fa-edit"></i> এডিট</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="manage_users.php?action=delete&user_id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?>&page=<?php echo $current_page; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারকে এবং তার সম্পর্কিত সকল ডেটা (যেমন কুইজ অ্যাটেম্পট, উত্তর ইত্যাদি) ডিলিট করতে চান?');" title="ডিলিট করুন"><i class="fas fa-trash"></i> ডিলিট</a>
                                <?php endif; ?>
                                <a href="send_email.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-success mb-1" title="এই ইউজারকে ইমেইল করুন"><i class="fas fa-envelope"></i> ইমেইল</a>
                                <a href="view_user_activity.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning mb-1" title="ইউজারের কার্যকলাপ দেখুন"><i class="fas fa-history"></i> কার্যকলাপ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav aria-label="User pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_term); ?>">পূর্ববর্তী</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">পূর্ববর্তী</span></li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <li class="page-item active" aria-current="page"><span class="page-link"><?php echo $i; ?></span></li>
                        <?php elseif (abs($i - $current_page) < 3 || $i == 1 || $i == $total_pages || ($total_pages > 5 && ($i == 2 && $current_page > 4)) || ($total_pages > 5 && ($i == $total_pages - 1 && $current_page < $total_pages - 3))): // Show first, last, current and nearby pages ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a></li>
                        <?php elseif (($i == 2 && $current_page > 4 && $total_pages > 5) || ($i == $total_pages - 1 && $current_page < $total_pages - 3 && $total_pages > 5)): ?>
                             <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_term); ?>">পরবর্তী</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">পরবর্তী</span></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php elseif (empty($search_term)): ?>
            <p class="text-center">এখনও কোনো ইউজার রেজিস্টার করেনি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> <?php
$conn->close();
require_once 'includes/footer.php';
?>