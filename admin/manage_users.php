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
        // First, delete related records from quiz_attempts
        // This is important to avoid foreign key constraint errors if they exist
        // Note: user_answers are typically linked to quiz_attempts or questions,
        // so deleting attempts or questions (if quiz is deleted) should handle them.
        // If user_answers are directly linked to users.id without ON DELETE CASCADE,
        // you might need to delete from user_answers first.
        
        $conn->begin_transaction();
        try {
            // Delete user_answers related to attempts by this user
            // This might be complex depending on your exact schema.
            // A simpler approach if direct user_id link exists in user_answers:
            // $sql_delete_user_answers_direct = "DELETE FROM user_answers WHERE attempt_id IN (SELECT id FROM quiz_attempts WHERE user_id = ?)";
            // If user_answers are only linked via question_id and attempt_id, deleting attempts covers it.

            // Delete from quiz_attempts
            $sql_delete_attempts = "DELETE FROM quiz_attempts WHERE user_id = ?";
            $stmt_delete_attempts = $conn->prepare($sql_delete_attempts);
            if (!$stmt_delete_attempts) throw new Exception("ডাটাবেস সমস্যা (এটেম্পট ডিলিট প্রস্তুতি): " . $conn->error);
            $stmt_delete_attempts->bind_param("i", $user_id_to_delete);
            if (!$stmt_delete_attempts->execute()) throw new Exception("ইউজারের কুইজ এটেম্পট ডিলিট করতে সমস্যা: " . $stmt_delete_attempts->error);
            $stmt_delete_attempts->close();

            // Now delete the user
            $sql_delete_user = "DELETE FROM users WHERE id = ?";
            $stmt_delete_user = $conn->prepare($sql_delete_user);
            if (!$stmt_delete_user) throw new Exception("ডাটাবেস সমস্যা (ইউজার ডিলিট প্রস্তুতি): " . $conn->error);
            $stmt_delete_user->bind_param("i", $user_id_to_delete);
            if (!$stmt_delete_user->execute()) {
                 // Check for specific foreign key error (e.g., MySQL error code 1451)
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
    header("Location: manage_users.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit;
}
// Handle Ban/Unban Action
else if (isset($_GET['action']) && ($_GET['action'] == 'ban_user' || $_GET['action'] == 'unban_user') && isset($_GET['user_id'])) {
    $user_id_to_modify = intval($_GET['user_id']);
    $new_status_is_banned = ($_GET['action'] == 'ban_user') ? 1 : 0; // 1 for banned, 0 for not banned
    $action_text = ($new_status_is_banned == 1) ? 'নিষিদ্ধ (banned)' : 'সক্রিয় (unbanned)';

    if ($user_id_to_modify == $_SESSION['user_id']) {
        $_SESSION['flash_message'] = "আপনি নিজেকে নিষিদ্ধ বা সক্রিয় করতে পারবেন না।";
        $_SESSION['flash_message_type'] = "warning";
    } else {
        // Check current role of the user to be banned/unbanned
        $sql_check_role = "SELECT role FROM users WHERE id = ?";
        $stmt_check_role = $conn->prepare($sql_check_role);
        $stmt_check_role->bind_param("i", $user_id_to_modify);
        $stmt_check_role->execute();
        $result_role = $stmt_check_role->get_result();
        $user_to_modify_data = $result_role->fetch_assoc();
        $stmt_check_role->close();

        if ($user_to_modify_data && $user_to_modify_data['role'] === 'admin' && $new_status_is_banned == 1) {
            // Optional: Add an extra confirmation or prevent banning other admins directly if needed.
            // For now, allowing admin to ban other admins (except self).
            // $_SESSION['flash_message'] = "এডমিন ইউজারকে নিষিদ্ধ করা যাবে না। প্রথমে ভূমিকা পরিবর্তন করুন।";
            // $_SESSION['flash_message_type'] = "warning";
        }
        // Proceed with ban/unban
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
    header("Location: manage_users.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit;
}


require_once 'includes/header.php';

// --- সার্চ লজিক ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = [];
$sql_base = "SELECT id, name, email, mobile_number, role, created_at, is_banned FROM users"; // Added is_banned
$params = [];
$types = "";

if (!empty($search_term)) {
    $sql_users = $sql_base . " WHERE name LIKE ? OR email LIKE ? OR mobile_number LIKE ? ORDER BY created_at DESC";
    if ($stmt_search = $conn->prepare($sql_users)) {
        $search_like = "%" . $search_term . "%";
        $stmt_search->bind_param("sss", $search_like, $search_like, $search_like);
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
    $sql_users = $sql_base . " ORDER BY created_at DESC";
    $result_users = $conn->query($sql_users);
    if ($result_users && $result_users->num_rows > 0) {
        while ($row = $result_users->fetch_assoc()) {
            $users[] = $row;
        }
    }
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
            ইউজার খুঁজুন
        </div>
        <div class="card-body">
            <form action="manage_users.php" method="get" class="row g-3 align-items-center">
                <div class="col-md-10">
                    <label for="search" class="visually-hidden">সার্চ করুন (নাম, ইমেইল, মোবাইল)</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="নাম, ইমেইল বা মোবাইল নম্বর দিয়ে খুঁজুন..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">খুঁজুন</button>
                </div>
            </form>
             <?php if (!empty($search_term) && empty($users)): ?>
                <p class="mt-3 text-center text-warning">"<?php echo htmlspecialchars($search_term); ?>" এর জন্য কোনো ইউজার খুঁজে পাওয়া যায়নি।</p>
            <?php elseif (!empty($search_term) && !empty($users)): ?>
                 <p class="mt-3 text-muted">"<?php echo htmlspecialchars($search_term); ?>" এর জন্য <?php echo count($users); ?> টি ফলাফল পাওয়া গেছে। <a href="manage_users.php">সকল ইউজার দেখুন</a></p>
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
                                    <?php if ($user['is_banned'] == 0): ?>
                                        <a href="manage_users.php?action=ban_user&user_id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?>" class="btn btn-sm btn-warning mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারকে নিষিদ্ধ করতে চান?');">নিষিদ্ধ করুন</a>
                                    <?php else: ?>
                                        <a href="manage_users.php?action=unban_user&user_id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারের উপর থেকে নিষেধাজ্ঞা তুলে নিতে চান?');">সক্রিয় করুন</a>
                                    <?php endif; ?>
                                    <a href="manage_users.php?action=delete&user_id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারকে ডিলিট করতে চান? এই ইউজারের সকল কুইজ এটেম্পট ও ডিলিট হয়ে যাবে।');">ডিলিট</a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">এটি আপনি</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif (empty($search_term)): ?>
            <p class="text-center">এখনও কোনো ইউজার রেজিস্টার করেনি।</p>
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
?>a