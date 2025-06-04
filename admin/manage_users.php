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
        // ... (আপনার বাকি ডিলিট লজিক অপরিবর্তিত থাকবে) ...
        $sql_delete_user = "DELETE FROM users WHERE id = ?";
        if ($stmt_delete = $conn->prepare($sql_delete_user)) {
            $stmt_delete->bind_param("i", $user_id_to_delete);
            if ($stmt_delete->execute()) {
                $_SESSION['flash_message'] = "ইউজার (ID: {$user_id_to_delete}) সফলভাবে ডিলিট করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "ইউজার ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete->error . " (সম্ভবত এই ইউজারের সাথে সম্পর্কিত ডেটা রয়েছে যেমন কুইজ এটেম্পট)।";
                $_SESSION['flash_message_type'] = "danger";
            }
            $stmt_delete->close();
        } else {
            $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    header("Location: manage_users.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : '')); // Redirect back to search results if any
    exit;
}

require_once 'includes/header.php';

// --- সার্চ লজিক ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = [];

if (!empty($search_term)) {
    $sql_users = "SELECT id, name, email, mobile_number, role, created_at FROM users 
                  WHERE name LIKE ? OR email LIKE ? OR mobile_number LIKE ?
                  ORDER BY created_at DESC";
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
    // Fetch all users (you might want to paginate this for many users)
    $sql_users = "SELECT id, name, email, mobile_number, role, created_at FROM users ORDER BY created_at DESC";
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


    <?php
    if (isset($_SESSION['flash_message'])) {
        echo '<div class="alert alert-' . $_SESSION['flash_message_type'] . ' alert-dismissible fade show" role="alert">';
        echo $_SESSION['flash_message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_message_type']);
    }
    ?>

    <div class="card">
        <div class="card-header">
            সকল রেজিস্টার্ড ইউজারের তালিকা
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
                            <th>মোবাইল নম্বর</th>
                            <th>ভূমিকা (Role)</th>
                            <th>রেজিস্ট্রেশনের তারিখ</th>
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
                            <td><?php echo date("d M Y, h:i A", strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info mb-1">এডিট</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): // Admin cannot delete themselves from this interface ?>
                                <a href="manage_users.php?action=delete&user_id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই ইউজারকে ডিলিট করতে চান?');">ডিলিট</a>
                                <?php endif; ?>
                                <a href="send_email.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-success mb-1" title="এই ইউজারকে ইমেইল করুন">ইমেইল পাঠান</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif (empty($search_term)): ?>
            <p class="text-center">এখনও কোনো ইউজার রেজিস্টার করেনি।</p>
            <?php endif; // সার্চের ফলাফল খালি থাকলে সার্চ বক্সের নিচে মেসেজ দেখানো হয়েছে ?>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>