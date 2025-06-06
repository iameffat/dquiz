<?php
$page_title = "ইউজার ম্যানেজমেন্ট";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
    $user_id_to_delete = intval($_GET['user_id']);
    $current_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
    $current_search = isset($_GET['search']) ? $_GET['search'] : '';
    
    if ($user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['flash_message'] = "আপনি নিজেকে ডিলিট করতে পারবেন না।";
        $_SESSION['flash_message_type'] = "warning";
    } else {
        $conn->begin_transaction();
        try {
            $sql_delete_attempts = "DELETE FROM quiz_attempts WHERE user_id = ?";
            $stmt_delete_attempts = $conn->prepare($sql_delete_attempts);
            if (!$stmt_delete_attempts) throw new Exception("ডাটাবেস সমস্যা (এটেম্পট ডিলিট প্রস্তুতি): " . $conn->error);
            $stmt_delete_attempts->bind_param("i", $user_id_to_delete);
            if (!$stmt_delete_attempts->execute()) throw new Exception("ইউজারের কুইজ এটেম্পট ডিলিট করতে সমস্যা: " . $stmt_delete_attempts->error);
            $stmt_delete_attempts->close();

            $sql_delete_user = "DELETE FROM users WHERE id = ?";
            $stmt_delete_user = $conn->prepare($sql_delete_user);
            if (!$stmt_delete_user) throw new Exception("ডাটাবেস সমস্যা (ইউজার ডিলিট প্রস্তুতি): " . $conn->error);
            $stmt_delete_user->bind_param("i", $user_id_to_delete);
            if (!$stmt_delete_user->execute()) {
                if ($conn->errno == 1451) {
                     throw new Exception("ইউজার ডিলিট করা যায়নি কারণ এই ইউজারের সাথে অন্যান্য ডেটা (যেমন অ্যাডমিন হিসেবে কুইজ তৈরি) সংযুক্ত রয়েছে।");
                } else {
                    throw new Exception("ইউজার ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete_user->error);
                }
            }
            $stmt_delete_user->close();
            
            $conn->commit();
            $_SESSION['flash_message'] = "ইউজার (ID: {$user_id_to_delete}) এবং তার সম্পর্কিত কুইজ এটেম্পট সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "ইউজার ডিলিট করার সময় ত্রুটি: " . $e->getMessage();
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    $redirect_url = "manage_users.php?status_filter=" . urlencode($current_filter) . "&search=" . urlencode($current_search);
    header("Location: " . $redirect_url);
    exit;
}
// Handle Ban/Unban Action
else if (isset($_GET['action']) && ($_GET['action'] == 'ban_user' || $_GET['action'] == 'unban_user') && isset($_GET['user_id'])) {
    $user_id_to_modify = intval($_GET['user_id']);
    $new_status_is_banned = ($_GET['action'] == 'ban_user') ? 1 : 0;
    $action_text = ($new_status_is_banned == 1) ? 'নিষিদ্ধ (banned)' : 'সক্রিয় (unbanned)';
    $current_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
    $current_search = isset($_GET['search']) ? $_GET['search'] : '';

    if ($user_id_to_modify == $_SESSION['user_id']) {
        $_SESSION['flash_message'] = "আপনি নিজেকে নিষিদ্ধ বা সক্রিয় করতে পারবেন না।";
        $_SESSION['flash_message_type'] = "warning";
    } else {
        $sql_update_status = "UPDATE users SET is_banned = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update_status)) {
            $stmt_update->bind_param("ii", $new_status_is_banned, $user_id_to_modify);
            if ($stmt_update->execute()) {
                $_SESSION['flash_message'] = "ইউজার (ID: {$user_id_to_modify}) সফলভাবে {$action_text} করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "ইউজারের স্ট্যাটাস পরিবর্তন করতে সমস্যা হয়েছে: " . $stmt_update->error;
                $_SESSION['flash_message_type'] = "danger";
            }
            $stmt_update->close();
        } else {
            $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    $redirect_url = "manage_users.php?status_filter=" . urlencode($current_filter) . "&search=" . urlencode($current_search);
    header("Location: " . $redirect_url);
    exit;
}


require_once 'includes/header.php';

// --- Filter & Search Logic ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$users = [];
$sql_base = "SELECT id, name, email, mobile_number, role, created_at, is_banned FROM users";
$where_clauses = [];
$params = [];
$types = "";

// Status Filter
if ($status_filter === 'active') {
    $where_clauses[] = "is_banned = 0";
} elseif ($status_filter === 'banned') {
    $where_clauses[] = "is_banned = 1";
}

// Search Filter
if (!empty($search_term)) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR mobile_number LIKE ?)";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like, $search_like);
    $types .= "sss";
}

$sql_users = $sql_base;
if (!empty($where_clauses)) {
    $sql_users .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_users .= " ORDER BY created_at DESC";

$stmt_users = $conn->prepare($sql_users);

if ($stmt_users) {
    if (!empty($params)) {
        $stmt_users->bind_param($types, ...$params);
    }
    if(!$stmt_users->execute()){
         echo '<div class="alert alert-danger">ইউজার আনতে ডাটাবেস সমস্যা হয়েছে: ' . $stmt_users->error . '</div>';
    }
    $result_users = $stmt_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt_users->close();
} else {
    echo '<div class="alert alert-danger">ইউজার স্টেটমেন্ট প্রস্তুত করতে সমস্যা: ' . $conn->error . '</div>';
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1>ইউজার ম্যানেজমেন্ট</h1>
        <?php if (!empty($users)): ?>
        <button id="copyAllEmailsBtn" class="btn btn-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard-plus me-1" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/>
              <path fill-rule="evenodd" d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zM8 7a.5.5 0 0 1 .5.5V9H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V10H6a.5.5 0 0 1 0-1h1.5V7.5A.5.5 0 0 1 8 7"/>
            </svg>
            সকল ইমেইল কপি করুন
        </button>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            ইউজার ফিল্টার ও সার্চ
        </div>
        <div class="card-body">
            <form action="manage_users.php" method="get" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label">সার্চ করুন (নাম, ইমেইল, মোবাইল)</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-4">
                    <label for="status_filter" class="form-label">স্ট্যাটাস অনুযায়ী ফিল্টার</label>
                    <select name="status_filter" id="status_filter" class="form-select">
                        <option value="all" <?php if ($status_filter == 'all' || $status_filter == '') echo 'selected'; ?>>সকল ইউজার</option>
                        <option value="active" <?php if ($status_filter == 'active') echo 'selected'; ?>>সক্রিয় ইউজার</option>
                        <option value="banned" <?php if ($status_filter == 'banned') echo 'selected'; ?>>নিষিদ্ধ ইউজার</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">ফিল্টার</button>
                </div>
            </form>
             <?php if (!empty($search_term) && empty($users) && $status_filter === ''): ?>
                <p class="mt-3 text-center text-warning">"<?php echo htmlspecialchars($search_term); ?>" এর জন্য কোনো ইউজার খুঁজে পাওয়া যায়নি।</p>
            <?php elseif (!empty($users)): ?>
                 <p class="mt-3 text-muted"><small>মোট <?php echo count($users); ?> টি ফলাফল পাওয়া গেছে। <?php if(!empty($search_term) || !empty($status_filter)) echo '<a href="manage_users.php">ফিল্টার মুছুন</a>'; ?></small></p>
            <?php endif; ?>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div class="card">
        <div class="card-header">
            সকল রেজিস্টার্ড ইউজারের তালিকা
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>নাম</th>
                            <th class="user-email-cell">ইমেইল</th>
                            <th>মোবাইল</th>
                            <th>ভূমিকা</th>
                            <th>স্ট্যাটাস</th>
                            <th>রেজিস্ট্রেশনের তারিখ</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr class="<?php echo $user['is_banned'] ? 'table-secondary text-muted' : ''; ?>">
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td class="user-email-cell"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['mobile_number']); ?></td>
                            <td>
                                <?php 
                                if ($user['role'] == 'admin') echo '<span class="badge bg-danger">এডমিন</span>';
                                else echo '<span class="badge bg-success">ইউজার</span>';
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($user['is_banned'] == 1) echo '<span class="badge bg-warning text-dark">নিষিদ্ধ</span>';
                                else echo '<span class="badge bg-info">সক্রিয়</span>';
                                ?>
                            </td>
                            <td><?php echo date("d M Y, h:i A", strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info mb-1">এডিট</a>
                                <a href="send_email.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-success mb-1" title="এই ইউজারকে ইমেইল করুন">ইমেইল</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <?php 
                                    $filter_query_string = "status_filter=" . urlencode($status_filter) . "&search=" . urlencode($search_term);
                                    if ($user['is_banned'] == 0): 
                                    ?>
                                        <a href="manage_users.php?action=ban_user&user_id=<?php echo $user['id']; ?>&<?php echo $filter_query_string; ?>" class="btn btn-sm btn-warning mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারকে নিষিদ্ধ করতে চান?');">নিষিদ্ধ করুন</a>
                                    <?php else: ?>
                                        <a href="manage_users.php?action=unban_user&user_id=<?php echo $user['id']; ?>&<?php echo $filter_query_string; ?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারের উপর থেকে নিষেধাজ্ঞা তুলে নিতে চান?');">সক্রিয় করুন</a>
                                    <?php endif; ?>
                                    <a href="manage_users.php?action=delete&user_id=<?php echo $user['id']; ?>&<?php echo $filter_query_string; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারকে ডিলিট করতে চান? এই ইউজারের সকল কুইজ এটেম্পট ও ডিলিট হয়ে যাবে।');">ডিলিট</a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">এটি আপনি</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif (empty($search_term) && empty($status_filter)): ?>
                <p class="text-center">এখনও কোনো ইউজার রেজিস্টার করেনি।</p>
            <?php else: ?>
                <p class="text-center">আপনার ফিল্টার অনুযায়ী কোনো ফলাফল পাওয়া যায়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const copyAllEmailsBtn = document.getElementById('copyAllEmailsBtn');
    if (copyAllEmailsBtn) {
        copyAllEmailsBtn.addEventListener('click', function() {
            const emailCells = document.querySelectorAll('#usersTable tbody .user-email-cell');
            let emails = [];
            emailCells.forEach(cell => {
                if (cell.textContent.trim() !== '') {
                    emails.push(cell.textContent.trim());
                }
            });

            if (emails.length > 0) {
                const emailString = emails.join(', ');
                navigator.clipboard.writeText(emailString).then(() => {
                    const originalText = this.innerHTML;
                    this.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard-check-fill me-1" viewBox="0 0 16 16">
                          <path d="M6.5 0A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0zm3 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5z"/>
                          <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1A2.5 2.5 0 0 1 9.5 5h-3A2.5 2.5 0 0 1 4 2.5v-1Zm6.854 7.354-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 0 1 .708-.708L7.5 10.793l2.646-2.647a.5.5 0 0 1 .708.708"/>
                        </svg>
                        ইমেইল কপি হয়েছে!`;
                    this.classList.remove('btn-success');
                    this.classList.add('btn-primary');
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-success');
                    }, 2500);
                }).catch(err => {
                    console.error('ইমেইল কপি করতে সমস্যা হয়েছে: ', err);
                    alert('ইমেইল কপি করা যায়নি। ব্রাউজার কনসোল দেখুন।');
                });
            } else {
                alert('কপি করার জন্য কোনো ইমেইল পাওয়া যায়নি।');
            }
        });
    }
});
</script>
<?php
$conn->close();
require_once 'includes/footer.php';
?>