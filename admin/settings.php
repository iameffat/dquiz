<?php
$page_title = "হোমপেজ সেটিংস";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$feedback_message = "";
$message_type = "";

// Fetch current settings
$current_settings = [];
$sql_fetch = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('upcoming_quiz_enabled', 'upcoming_quiz_title', 'upcoming_quiz_end_date')";
$result_fetch = $conn->query($sql_fetch);
if ($result_fetch && $result_fetch->num_rows > 0) {
    while ($row = $result_fetch->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$upcoming_quiz_enabled = isset($current_settings['upcoming_quiz_enabled']) ? (bool)$current_settings['upcoming_quiz_enabled'] : false;
$upcoming_quiz_title = isset($current_settings['upcoming_quiz_title']) ? $current_settings['upcoming_quiz_title'] : '';
$upcoming_quiz_end_date = isset($current_settings['upcoming_quiz_end_date']) ? $current_settings['upcoming_quiz_end_date'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $posted_enabled = isset($_POST['upcoming_quiz_enabled']) ? '1' : '0';
    $posted_title = trim($_POST['upcoming_quiz_title']);
    $posted_end_date = trim($_POST['upcoming_quiz_end_date']);

    $errors = [];
    if (empty($posted_title)) {
        $errors[] = "আপকামিং কুইজের শিরোনাম খালি রাখা যাবে না।";
    }
    if ($posted_enabled === '1' && empty($posted_end_date)) {
        $errors[] = "কুইজ সক্রিয় থাকলে শেষের তারিখ আবশ্যক।";
    } elseif (!empty($posted_end_date) && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $posted_end_date)) {
        $errors[] = "শেষের তারিখ YYYY-MM-DD ফরম্যাটে হতে হবে।";
    }

    if (empty($errors)) {
        $settings_to_update = [
            'upcoming_quiz_enabled' => $posted_enabled,
            'upcoming_quiz_title' => $posted_title,
            'upcoming_quiz_end_date' => $posted_end_date
        ];

        $all_successful = true;
        foreach ($settings_to_update as $key => $value) {
            $sql_update = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
            if ($stmt = $conn->prepare($sql_update)) {
                $stmt->bind_param("sss", $key, $value, $value);
                if (!$stmt->execute()) {
                    $all_successful = false;
                    $errors[] = "Setting '{$key}' সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $all_successful = false;
                $errors[] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
                break;
            }
        }

        if ($all_successful) {
            $feedback_message = "সেটিংস সফলভাবে আপডেট করা হয়েছে।";
            $message_type = "success";
            // Re-fetch settings to display updated values
            $upcoming_quiz_enabled = (bool)$posted_enabled;
            $upcoming_quiz_title = $posted_title;
            $upcoming_quiz_end_date = $posted_end_date;
        } else {
            $feedback_message = "ত্রুটি: <br>" . implode("<br>", $errors);
            $message_type = "danger";
        }
    } else {
        $feedback_message = "ত্রুটি: <br>" . implode("<br>", $errors);
        $message_type = "danger";
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4">হোমপেজ আপকামিং কুইজ সেটিংস</h1>
    <p>এখান থেকে আপনি হোমপেজে প্রদর্শিত আপকামিং কুইজ সেকশনটি নিয়ন্ত্রণ করতে পারবেন।</p>

    <?php if ($feedback_message): ?>
    <div class="alert alert-<?php echo $message_type === "success" ? "success" : "danger"; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="upcoming_quiz_enabled" name="upcoming_quiz_enabled" value="1" <?php echo $upcoming_quiz_enabled ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="upcoming_quiz_enabled">আপকামিং কুইজ সেকশনটি হোমপেজে দেখান</label>
                </div>
                <div class="mb-3">
                    <label for="upcoming_quiz_title" class="form-label">আপকামিং কুইজের শিরোনাম</label>
                    <input type="text" class="form-control" id="upcoming_quiz_title" name="upcoming_quiz_title" value="<?php echo htmlspecialchars($upcoming_quiz_title); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="upcoming_quiz_end_date" class="form-label">কুইজের শেষ তারিখ (দিন গণনা এই তারিখ পর্যন্ত হবে)</label>
                    <input type="date" class="form-control" id="upcoming_quiz_end_date" name="upcoming_quiz_end_date" value="<?php echo htmlspecialchars($upcoming_quiz_end_date); ?>" placeholder="YYYY-MM-DD">
                    <small class="form-text text-muted">ফরম্যাট: YYYY-MM-DD. যেমন: <?php echo date("Y-m-d", strtotime("+7 days")); ?></small>
                </div>
                <button type="submit" class="btn btn-primary">সেটিংস সংরক্ষণ করুন</button>
            </form>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>