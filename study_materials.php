<?php
$page_title = "স্টাডি ম্যাটেরিয়ালস";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$materials = [];
// Fetch google_drive_link
$sql = "SELECT id, title, description, google_drive_link, created_at FROM study_materials ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }
}

$page_specific_styles = "
    .study-material-card {
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        transition: box-shadow 0.3s ease-in-out, transform 0.3s ease-in-out;
        background-color: var(--card-bg);
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .study-material-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transform: translateY(-5px);
    }
    .study-material-card .card-body {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        padding: 1.25rem;
    }
    .study-material-card .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--bs-primary-text-emphasis);
        margin-bottom: 0.75rem;
    }
    .study-material-card .material-description {
        font-size: 0.95rem;
        color: var(--body-color);
        line-height: 1.6;
        margin-bottom: 1rem;
        flex-grow: 1; 
    }
    .study-material-card .material-description p:last-child {
        margin-bottom: 0;
    }
    .study-material-card .download-link {
        margin-top: auto; 
        align-self: flex-start;
    }
    .study-material-card .card-footer {
        background-color: var(--card-header-bg);
        border-top: 1px solid var(--border-color);
        font-size: 0.85rem;
        color: var(--text-muted-color);
        padding: 0.75rem 1.25rem;
    }
    .page-header-custom {
        background: linear-gradient(135deg, var(--secondary-bg-color) 0%, var(--tertiary-bg-color) 100%);
        padding: 2rem 1rem;
        border-radius: .75rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    .page-header-custom h1 {
        color: var(--bs-primary-text-emphasis);
        font-weight: 700;
    }
    .page-header-custom p {
        color: var(--bs-secondary-text-emphasis);
        font-size: 1.1rem;
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="page-header-custom">
        <h1>স্টাডি ম্যাটেরিয়ালস</h1>
        <p>পরীক্ষার প্রস্তুতির জন্য প্রয়োজনীয় পিডিএফ ও অন্যান্য উপকরণ।</p>
    </div>

    <?php if (!empty($materials)): ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($materials as $material): ?>
                <div class="col">
                    <div class="card study-material-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($material['title']); ?></h5>
                            <div class="material-description">
                                <?php 
                                if (!empty(trim(strip_tags($material['description'])))) {
                                    echo $material['description']; 
                                } else {
                                    echo '<p class="text-muted fst-italic"><em>কোনো বিবরণ নেই।</em></p>';
                                }
                                ?>
                            </div>
                            <a href="<?php echo htmlspecialchars($material['google_drive_link']); ?>" target="_blank" class="btn btn-primary download-link">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-link-45deg me-2" viewBox="0 0 16 16">
                                  <path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/>
                                  <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/>
                                </svg>
                                ডাউনলোড
                            </a>
                        </div>
                        <div class="card-footer text-muted">
                            প্রকাশিত: <?php echo format_datetime($material['created_at'], "d M Y"); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            এখনও কোনো স্টাডি ম্যাটেরিয়াল যোগ করা হয়নি। অনুগ্রহ করে নতুন ম্যাটেরিয়ালসের জন্য অপেক্ষা করুন।
        </div>
    <?php endif; ?>
</div>

<?php
if ($conn) {
    $conn->close();
}
require_once 'includes/footer.php';
?>