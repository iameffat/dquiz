<?php
$page_title = "স্টাডি ম্যাটেরিয়াল এডিট করুন";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$material_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$material = null;
$errors = [];

if ($material_id <= 0) {
    $_SESSION['flash_message'] = "অবৈধ ম্যাটেরিয়াল ID.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_study_materials.php");
    exit;
}

// Fetch existing material details
$sql_fetch_material = "SELECT id, title, description, google_drive_link FROM study_materials WHERE id = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch_material)) {
    $stmt_fetch->bind_param("i", $material_id);
    $stmt_fetch->execute();
    $result_material = $stmt_fetch->get_result();
    if ($result_material->num_rows === 1) {
        $material = $result_material->fetch_assoc();
        $page_title = "এডিট: " . htmlspecialchars($material['title']);
    } else {
        $_SESSION['flash_message'] = "ম্যাটেরিয়াল (ID: {$material_id}) খুঁজে পাওয়া যায়নি।";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: manage_study_materials.php");
        exit;
    }
    $stmt_fetch->close();
} else {
    $_SESSION['flash_message'] = "ডাটাবেস সমস্যা (ম্যাটেরিয়াল তথ্য আনতে)।";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manage_study_materials.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']); // Quill content
    $google_drive_link = trim($_POST['google_drive_link']);

    if (empty($title)) $errors[] = "শিরোনাম আবশ্যক।";
    if (empty($google_drive_link)) {
        $errors[] = "ডাউনলোড লিংক আবশ্যক।";
    } elseif (!filter_var($google_drive_link, FILTER_VALIDATE_URL)) {
        $errors[] = "একটি সঠিক লিংক প্রদান করুন।";
    }

    if (empty($errors)) {
        // Update the database
        $sql_update = "UPDATE study_materials SET title = ?, description = ?, google_drive_link = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("sssi", $title, $description, $google_drive_link, $material_id);
            if ($stmt_update->execute()) {
                $_SESSION['flash_message'] = "স্টাডি ম্যাটেরিয়াল \"" . htmlspecialchars($title) . "\" সফলভাবে আপডেট করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
                header("Location: manage_study_materials.php");
                exit;
            } else {
                $errors[] = "ম্যাটেরিয়াল আপডেট করতে সমস্যা হয়েছে: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
             $errors[] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
        }
    }
    // If errors, repopulate form with POSTed values
    $material['title'] = $title;
    $material['description'] = $description;
    $material['google_drive_link'] = $google_drive_link;
}

require_once 'includes/header.php';
?>
<div class="container-fluid">
    <h1 class="mt-4 mb-3">স্টাডি ম্যাটেরিয়াল এডিট করুন: <?php echo htmlspecialchars($material['title']); ?></h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong> অনুগ্রহ করে নিচের সমস্যাগুলো সমাধান করুন:
        <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <form action="edit_study_material.php?id=<?php echo $material_id; ?>" method="post" id="editStudyMaterialForm">
        <div class="card mb-4">
            <div class="card-header">ম্যাটেরিয়ালের বিবরণ (এডিট)</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label">শিরোনাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($material['title']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description_editor_sm_edit" class="form-label">সংক্ষিপ্ত বর্ণনা (ঐচ্ছিক)</label>
                    <div id="description_editor_sm_edit"><?php echo $material['description']; // Quill will initialize with this HTML ?></div>
                    <input type="hidden" name="description" id="description_hidden_sm_edit">
                </div>
                <div class="mb-3">
                    <label for="google_drive_link" class="form-label">ডাউনলোড লিংক <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="google_drive_link" name="google_drive_link" value="<?php echo htmlspecialchars($material['google_drive_link']); ?>" placeholder="https://example.com/file.pdf" required>
                     <small class="form-text text-muted">এখানে ফাইলটির সরাসরি ডাউনলোড লিংক দিন।</small>
                </div>
            </div>
        </div>
        <button type="submit" name="update_material" class="btn btn-primary btn-lg">সকল পরিবর্তন সংরক্ষণ করুন</button>
        <a href="manage_study_materials.php" class="btn btn-outline-secondary btn-lg">বাতিল</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('description_editor_sm_edit')) {
        const quillEditDescriptionSM = new Quill('#description_editor_sm_edit', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link'],
                    ['clean']
                ]
            }
        });
        
        const editStudyMaterialForm = document.getElementById('editStudyMaterialForm');
        if (editStudyMaterialForm) {
            editStudyMaterialForm.addEventListener('submit', function() {
                const descriptionHiddenInputSMEdit = document.getElementById('description_hidden_sm_edit');
                if (descriptionHiddenInputSMEdit) {
                    descriptionHiddenInputSMEdit.value = quillEditDescriptionSM.root.innerHTML;
                     if (quillEditDescriptionSM.getText().trim().length === 0 && quillEditDescriptionSM.root.innerHTML === '<p><br></p>') {
                         descriptionHiddenInputSMEdit.value = ''; 
                    }
                }
            });
        }
        <?php if (isset($_POST['description']) && !empty($errors)): ?>
        if(quillEditDescriptionSM) {
            quillEditDescriptionSM.root.innerHTML = <?php echo json_encode($_POST['description']); ?>;
        }
        <?php endif; ?>
    }
});
</script>

<?php
$conn->close();
require_once 'includes/footer.php';
?>