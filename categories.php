<?php
// categories.php
$page_title = "ক্যাটাগরি ভিত্তিক অনুশীলন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$categories = [];
// Updated SQL query: Shows categories if they have questions associated with them.
// The previous condition `q.quiz_id IS NULL` has been removed to allow questions from quizzes
// (that have a category_id set) to also count towards category questions for practice.
$sql = "SELECT c.id, c.name, c.description, c.icon_class, COUNT(q.id) as question_count 
        FROM categories c
        LEFT JOIN questions q ON c.id = q.category_id
        GROUP BY c.id, c.name, c.description, c.icon_class
        HAVING question_count > 0 -- Only display categories that have at least one question
        ORDER BY c.name ASC";

$result = $conn->query($sql);
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
} else {
    // Log error or display a user-friendly message if query fails
    error_log("Error fetching categories: " . $conn->error);
    // You could set a flash message here if you have a system for it
    // $_SESSION['flash_message'] = "ক্যাটাগরি আনতে সমস্যা হয়েছে।";
    // $_SESSION['flash_message_type'] = "danger";
}

$page_specific_styles = "
    .category-card {
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        transition: all 0.3s ease-in-out;
        background-color: var(--card-bg);
        display: flex;
        flex-direction: column;
        height: 100%;
        text-align: center;
        padding: 1.5rem;
    }
    .category-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transform: translateY(-5px);
    }
    body.dark-mode .category-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(255, 255, 255, 0.1);
    }
    .category-card .card-icon {
        font-size: 3rem; /* Increased icon size */
        margin-bottom: 1rem;
        color: var(--bs-primary);
    }
    body.dark-mode .category-card .card-icon {
        color: var(--bs-primary-text-emphasis);
    }
    .category-card .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--bs-primary-text-emphasis);
        margin-bottom: 0.5rem;
    }
    .category-card .category-description {
        font-size: 0.9rem;
        color: var(--bs-secondary-text-emphasis);
        margin-bottom: 1rem;
        flex-grow: 1;
        min-height: 40px; /* Ensure a minimum height for description area */
    }
    .category-card .question-count {
        font-size: 0.85rem;
        color: var(--text-muted-color);
        margin-bottom: 1rem;
    }
    .page-header-custom {
        background: linear-gradient(135deg, var(--secondary-bg-color) 0%, var(--tertiary-bg-color) 100%);
        padding: 2rem 1rem;
        border-radius: .75rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    body.dark-mode .page-header-custom {
         background: linear-gradient(135deg, var(--bs-gray-800) 0%, var(--bs-gray-900) 100%);
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
        <h1>ক্যাটাগরি ভিত্তিক অনুশীলন</h1>
        <p>আপনার পছন্দের বিষয় নির্বাচন করে জ্ঞান যাচাই করুন ও বাড়ান।</p>
    </div>

    <?php display_flash_message(); // To show any messages from redirects ?>

    <?php if (!empty($categories)): ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach ($categories as $category): ?>
                <div class="col">
                    <div class="category-card">
                        <?php if (!empty($category['icon_class'])): ?>
                            <div class="card-icon"><i class="<?php echo htmlspecialchars($category['icon_class']); ?>"></i></div>
                        <?php else: ?>
                             <div class="card-icon"><i class="fas fa-tags"></i></div> {/* Default icon: Make sure Font Awesome is linked */}
                        <?php endif; ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                        <?php if(!empty($category['description'])): ?>
                            <p class="category-description"><?php echo htmlspecialchars(mb_strimwidth($category['description'], 0, 80, "...")); ?></p>
                        <?php else: ?>
                            <p class="category-description text-muted fst-italic"><em>কোনো বিবরণ নেই।</em></p>
                        <?php endif; ?>
                        <p class="question-count">(<?php echo $category['question_count']; ?> টি প্রশ্ন)</p>
                        <a href="practice_quiz.php?category_id=<?php echo $category['id']; ?>" class="btn btn-primary mt-auto">অনুশীলন শুরু করুন</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center mt-4">
            অনুশীলনের জন্য কোনো ক্যাটাগরি বা প্রশ্ন এখনো যোগ করা হয়নি। অনুগ্রহ করে অ্যাডমিন প্যানেল থেকে ক্যাটাগরি এবং প্রশ্ন যোগ করুন।
        </div>
    <?php endif; ?>
</div>
<?php
// Font Awesome CDN for icons (if not already in main header.php, which it should be for consistency)
// Ensure your main `includes/header.php` has Font Awesome, or add it here or in `includes/footer.php`.
// Example: echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />';

if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>