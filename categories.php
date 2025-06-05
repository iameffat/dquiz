<?php
// categories.php
$page_title = "ক্যাটাগরি ভিত্তিক অনুশীলন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$categories = [];
// SQL কুয়েরি আপডেট করা হয়েছে: description এবং icon_class বাদ দেওয়া হয়েছে
// এবং প্রশ্ন গণনার জন্য question_categories জাংশন টেবিলও অন্তর্ভুক্ত করা হয়েছে
$sql = "SELECT c.id, c.name, 
               (SELECT COUNT(DISTINCT q.id) 
                FROM questions q 
                LEFT JOIN question_categories qc ON q.id = qc.question_id
                WHERE q.category_id = c.id OR qc.category_id = c.id
               ) as question_count
        FROM categories c
        GROUP BY c.id, c.name  -- Group by c.id and c.name is still needed
        HAVING question_count > 0 
        ORDER BY c.name ASC";

$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
} else {
    // যদি কুয়েরিতে এরর হয়, তাহলে লগ করুন এবং ইউজারকে একটি বার্তা দেখান
    error_log("Error fetching categories: " . $conn->error);
    // একটি সাধারণ এরর মেসেজ দেখানো যেতে পারে, অথবা পেইজটি একটি এরর বার্তা দিয়ে লোড হতে পারে
    // যেমন: echo "ক্যাটাগরি আনতে সমস্যা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন।";
    // exit; // অথবা এরর পেইজে রিডাইরেক্ট করা
}

$page_specific_styles = "
    .home-category-card { 
        background-color: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 0.75rem;
        padding: 1.25rem;
        text-align: center;
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .home-category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
    }
    .home-category-icon {
        width: 60px; 
        height: 60px;
        border-radius: 50%;
        background-color: var(--bs-primary); 
        color: white; 
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem; 
        font-weight: bold;
        margin: 0 auto 1rem auto;
        line-height: 1; 
    }
    .home-category-card .card-title { 
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--bs-emphasis-color);
        margin-bottom: 0.25rem;
    }
    .home-category-card .question-count {
        font-size: 0.85rem;
        color: var(--bs-secondary-color);
        margin-bottom: 1rem;
    }
    .home-category-card .btn { 
        font-size: 0.9rem;
    }

    body.dark-mode .home-category-card {
        background-color: var(--bs-gray-800);
        border-color: var(--bs-gray-700);
    }
    body.dark-mode .home-category-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(255,255,255,0.07);
    }
    body.dark-mode .home-category-icon {
        background-color: var(--bs-primary-text-emphasis);
        color: var(--bs-dark-bg-subtle); 
    }
    body.dark-mode .home-category-card .card-title {
        color: var(--bs-light-text-emphasis);
    }
    body.dark-mode .home-category-card .question-count {
        color: var(--bs-secondary-text-emphasis);
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
    
    @media (max-width: 575.98px) { 
        .home-category-card {
            padding: 1rem;
        }
        .home-category-icon {
            width: 50px; 
            height: 50px;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
        }
        .home-category-card .card-title {
            font-size: 1.05rem; 
        }
        .home-category-card .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
        .page-header-custom {
            padding: 1.5rem 1rem;
            margin-bottom: 1.5rem;
        }
        .page-header-custom h1 {
            font-size: 1.75rem;
        }
        .page-header-custom p {
            font-size: 1rem;
        }
    }
";

require_once 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="page-header-custom">
        <h1>ক্যাটাগরি ভিত্তিক অনুশীলন</h1>
        <p>আপনার পছন্দের বিষয় নির্বাচন করে জ্ঞান যাচাই করুন ও বাড়ান।</p>
    </div>

    <?php display_flash_message(); ?>

    <?php if (!empty($categories)): ?>
        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach ($categories as $index => $category): 
                // নামের প্রথম অক্ষর
                $category_initial = mb_substr(trim($category['name']), 0, 1, "UTF-8");
                if (empty($category_initial) || !preg_match('/\p{L}/u', $category_initial)) {
                    $category_initial = "?";
                }
                // ডাইনামিক সলিড কালার (যদি ব্যবহার করতে চান)
                // $solid_colors অ্যারেটি (যা আগের উত্তরে ছিল) এখানে আবার যোগ করতে পারেন 
                // এবং $current_solid_color = $solid_colors[$index % count($solid_colors)]; ব্যবহার করতে পারেন
                // <div class="home-category-card" style="background-color: <?php echo $current_solid_color;
            ?>
                <div class="col">
                    <div class="home-category-card"> 
                        <div class="home-category-icon">
                            <?php echo htmlspecialchars(strtoupper($category_initial)); ?>
                        </div>
                        <div> 
                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <p class="question-count">(<?php echo $category['question_count']; ?> টি প্রশ্ন)</p>
                        </div>
                        <a href="practice_quiz.php?category_id=<?php echo $category['id']; ?>" class="btn btn-primary btn-sm mt-auto">অনুশীলন করুন</a>
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
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>