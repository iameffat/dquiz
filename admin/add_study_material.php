<?php
$page_title = "নতুন স্টাডি ম্যাটেরিয়াল যোগ করুন";
$admin_base_url = '';
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php';

$errors = [];
// $success_message = ""; // success_message is less useful if we redirect immediately

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']); // Quill content will be HTML
    $google_drive_link = trim($_POST['google_drive_link']);

    if (empty($title)) $errors[] = "শিরোনাম আবশ্যক।";
    if (empty($google_drive_link)) {
        $errors[] = "গুগল ড্রাইভ লিংক আবশ্যক।";
    } elseif (!filter_var($google_drive_link, FILTER_VALIDATE_URL)) {
        $errors[] = "একটি সঠিক গুগল ড্রাইভ লিংক প্রদান করুন।";
    }
    // Basic check if it looks like a Google Drive link (optional, can be more sophisticated)
    elseif (strpos($google_drive_link, 'drive.google.com') === false && strpos($google_drive_link, 'docs.google.com') === false) {
        $errors[] = "লিংকটি গুগল ড্রাইভের লিংক বলে মনে হচ্ছে না।";
    }


    if (empty($errors)) {
        $uploaded_by_user_id = $_SESSION['user_id'];
        // Assuming your table now has 'google_drive_link' instead of file_name and file_path
        $sql_insert = "INSERT INTO study_materials (title, description, google_drive_link, uploaded_by) VALUES (?, ?, ?, ?)";
        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("sssi", $title, $description, $google_drive_link, $uploaded_by_user_id);
            if ($stmt_insert->execute()) {
                $_SESSION['flash_message'] = "স্টাডি ম্যাটেরিয়াল \"" . htmlspecialchars($title) . "\" সফলভাবে যোগ করা হয়েছে।";
                $_SESSION['flash_message_type'] = "success";
                header("Location: manage_study_materials.php");
                exit;
            } else {
                $errors[] = "ম্যাটেরিয়াল সংরক্ষণ করতে সমস্যা হয়েছে: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        } else {
            $errors[] = "ডাটাবেস সমস্যা (prepare): " . $conn->error;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-3">নতুন স্টাডি ম্যাটেরিয়াল যোগ করুন</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>ত্রুটি!</strong> অনুগ্রহ করে নিচের সমস্যাগুলো সমাধান করুন:
        <ul>
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="addStudyMaterialForm">
        <div class="card mb-4">
            <div class="card-header">ম্যাটেরিয়ালের বিবরণ</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label">শিরোনাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description_editor_sm" class="form-label">সংক্ষিপ্ত বর্ণনা (ঐচ্ছিক)</label>
                    <div id="description_editor_sm"><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></div>
                    <input type="hidden" name="description" id="description_hidden_sm">
                </div>
                <div class="mb-3">
                    <label for="google_drive_link" class="form-label">গুগল ড্রাইভ শেয়ারেবল লিংক <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="google_drive_link" name="google_drive_link" value="<?php echo isset($_POST['google_drive_link']) ? htmlspecialchars($_POST['google_drive_link']) : ''; ?>" placeholder="https://docs.google.com/document/d/..." required>
                    <small class="form-text text-muted">আপনার গুগল ড্রাইভে আপলোড করা ফাইলের "Anyone with the link can view" পারমিশনসহ শেয়ারেবল লিংক দিন।</small>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">ম্যাটেরিয়াল সংরক্ষণ করুন</button>
        <a href="manage_study_materials.php" class="btn btn-outline-secondary btn-lg">বাতিল</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('description_editor_sm')) {
        const quillDescriptionSM = new Quill('#description_editor_sm', {
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

        const addStudyMaterialForm = document.getElementById('addStudyMaterialForm');
        if (addStudyMaterialForm) {
            addStudyMaterialForm.addEventListener('submit', function() {
                const descriptionHiddenInputSM = document.getElementById('description_hidden_sm');
                if (descriptionHiddenInputSM) {
                    descriptionHiddenInputSM.value = quillDescriptionSM.root.innerHTML;
                    if (quillDescriptionSM.getText().trim().length === 0 && quillDescriptionSM.root.innerHTML === '<p><br></p>') {
                         descriptionHiddenInputSM.value = ''; 
                    }
                }
            });
        }
         // Preserve content if form reloads with an error
        <?php if (isset($_POST['description']) && !empty($errors)): ?>
        if(quillDescriptionSM) {
            quillDescriptionSM.root.innerHTML = <?php echo json_encode($_POST['description']); ?>;
        }
        <?php endif; ?>
    }
});
</script>

<?php
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>