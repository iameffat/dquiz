<?php
$page_title = "স্টাডি ম্যাটেরিয়ালস ম্যানেজমেন্ট";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $material_id_to_delete = intval($_GET['id']);
    
    $sql_delete_material = "DELETE FROM study_materials WHERE id = ?";
    if ($stmt_delete = $conn->prepare($sql_delete_material)) {
        $stmt_delete->bind_param("i", $material_id_to_delete);
        if ($stmt_delete->execute()) {
            $_SESSION['flash_message'] = "স্টাডি ম্যাটেরিয়াল (ID: {$material_id_to_delete}) সফলভাবে ডিলিট করা হয়েছে।";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "স্টাডি ম্যাটেরিয়াল ডিলিট করতে সমস্যা হয়েছে: " . $stmt_delete->error;
            $_SESSION['flash_message_type'] = "danger";
        }
        $stmt_delete->close();
    } else {
        $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: manage_study_materials.php");
    exit;
}

require_once 'includes/header.php';

$materials = [];
$sql_materials = "SELECT sm.id, sm.title, sm.google_drive_link, sm.created_at, u.name as uploader_name 
                  FROM study_materials sm 
                  LEFT JOIN users u ON sm.uploaded_by = u.id 
                  ORDER BY sm.created_at DESC";
$result_materials = $conn->query($sql_materials);
if ($result_materials && $result_materials->num_rows > 0) {
    while ($row = $result_materials->fetch_assoc()) {
        $materials[] = $row;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1>স্টাডি ম্যাটেরিয়ালস ম্যানেজমেন্ট</h1>
        <a href="add_study_material.php" class="btn btn-primary">নতুন ম্যাটেরিয়াল যোগ করুন</a>
    </div>

    <?php display_flash_message(); ?>

    <div class="card">
        <div class="card-header">
            সকল স্টাডি ম্যাটেরিয়ালসের তালিকা
        </div>
        <div class="card-body">
            <?php if (!empty($materials)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>শিরোনাম</th>
                            <th>লিংক</th>
                            <th>আপলোডার</th>
                            <th>আপলোডের তারিখ</th>
                            <th>একশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $material): ?>
                        <tr>
                            <td><?php echo $material['id']; ?></td>
                            <td><?php echo htmlspecialchars($material['title']); ?></td>
                            <td><a href="<?php echo htmlspecialchars($material['google_drive_link']); ?>" target="_blank"><?php echo htmlspecialchars(mb_strimwidth($material['google_drive_link'], 0, 50, "...")); ?></a></td>
                            <td><?php echo $material['uploader_name'] ? htmlspecialchars($material['uploader_name']) : 'N/A'; ?></td>
                            <td><?php echo format_datetime($material['created_at']); ?></td>
                            <td>
                                <a href="edit_study_material.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info mb-1">এডিট</a>
                                <a href="manage_study_materials.php?action=delete&id=<?php echo $material['id']; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই স্টাডি ম্যাটেরিয়ালটি ডিলিট করতে চান?');">ডিলিট</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center">এখনও কোনো স্টাডি ম্যাটেরিয়াল যোগ করা হয়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>