<?php
$page_title = "ক্যাটাগরি ভিত্তিক অনুশীলন";
$base_url = '';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$categories = [];
$sql = "SELECT c.id, c.name, c.description, c.icon_class, COUNT(q.id) as question_count 
        FROM categories c
        LEFT JOIN questions q ON c.id = q.category_id AND q.quiz_id IS NULL -- Only count questions not part of a specific live/archived quiz, or adjust as needed
        GROUP BY c.id, c.name, c.description, c.icon_class
        HAVING question_count > 0 -- Only show categories with questions
        ORDER BY c.name ASC";
// উপরের কুয়েরিতে `q.quiz_id IS NULL` অংশটি পরিবর্তন করা লাগতে পারে। যদি আপনি চান যে আর্কাইভড কুইজের প্রশ্নগুলোও এখানে দেখাক, তাহলে এই শর্তটি বাদ দিতে পারেন বা অন্যভাবে ফিল্টার করতে পারেন।
// একটি সহজ উপায় হলো, questions টেবিলে একটি is_practice_available জাতীয় ফ্ল্যাগ রাখা।
// অথবা, যে প্রশ্নগুলোর category_id আছে, সেগুলোকে অনুশীলনের জন্য উপলব্ধ ধরা।

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
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
        font-size: 3rem;
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
    .page-header-custom h1 {
        color: var(--bs-primary-text-emphasis);
        font-weight: 700;
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="page-header-custom">
        <h1>ক্যাটাগরি ভিত্তিক অনুশীলন</h1>
        <p>আপনার পছন্দের বিষয় নির্বাচন করে জ্ঞান যাচাই করুন ও বাড়ান।</p>
    </div>

    <?php if (!empty($categories)): ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4"> {/* Changed to row-cols-lg-4 for better layout */}
            <?php foreach ($categories as $category): ?>
                <div class="col">
                    <div class="category-card">
                        <?php if (!empty($category['icon_class'])): ?>
                            <div class="card-icon"><i class="<?php echo htmlspecialchars($category['icon_class']); ?>"></i></div>
                        <?php else: ?>
                             <div class="card-icon"><i class="fas fa-tag"></i></div> {/* Default icon */}
                        <?php endif; ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                        <?php if(!empty($category['description'])): ?>
                            <p class="category-description"><?php echo htmlspecialchars(mb_strimwidth($category['description'], 0, 80, "...")); ?></p>
                        <?php endif; ?>
                        <p class="question-count">(<?php echo $category['question_count']; ?> টি প্রশ্ন)</p>
                        <a href="practice_quiz.php?category_id=<?php echo $category['id']; ?>" class="btn btn-primary mt-auto">অনুশীলন শুরু করুন</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            অনুশীলনের জন্য কোনো ক্যাটাগরি বা প্রশ্ন এখনো যোগ করা হয়নি।
        </div>
    <?php endif; ?>
</div>
<?php
// Font Awesome CDN for icons (if not already in main header)
// echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>