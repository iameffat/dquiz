<?php
// categories.php
$page_title = "ক্যাটাগরি ভিত্তিক অনুশীলন";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$categories = [];
$sql = "SELECT c.id, c.name, c.description, c.icon_class, COUNT(q.id) as question_count 
        FROM categories c
        LEFT JOIN questions q ON c.id = q.category_id
        GROUP BY c.id, c.name, c.description, c.icon_class
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
    error_log("Error fetching categories: " . $conn->error);
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
        padding: 1rem; /* মোবাইলের জন্য প্যাডিং কমানো হলো */
    }
    .category-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transform: translateY(-5px);
    }
    body.dark-mode .category-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(255, 255, 255, 0.1);
    }
    .category-card .card-icon {
        font-size: 2.5rem; /* মোবাইলের জন্য আইকন সাইজ একটু কমানো */
        margin-bottom: 0.75rem; /* মোবাইলের জন্য মার্জিন কমানো */
        color: var(--bs-primary);
    }
    body.dark-mode .category-card .card-icon {
        color: var(--bs-primary-text-emphasis);
    }
    .category-card .card-title {
        font-size: 1.1rem; /* মোবাইলের জন্য টাইটেল সাইজ একটু কমানো */
        font-weight: 600;
        color: var(--bs-primary-text-emphasis);
        margin-bottom: 0.25rem;
    }
    .category-card .category-description-placeholder {
        margin-bottom: 0.75rem; /* মোবাইলের জন্য মার্জিন কমানো */
        flex-grow: 1;
        min-height: 10px; /* মোবাইলের জন্য মিনিমাম উচ্চতা কমানো */
        font-size: 0.85rem; /* মোবাইলের জন্য বিবরণ ফন্ট সাইজ */
    }
    .category-card .question-count {
        font-size: 0.8rem; /* মোবাইলের জন্য প্রশ্ন সংখ্যা ফন্ট সাইজ */
        color: var(--text-muted-color);
        margin-bottom: 0.75rem;
    }
    .category-card .btn { /* মোবাইলের জন্য বাটন সাইজ */
        font-size: 0.85rem;
        padding: 0.375rem 0.75rem;
    }
    .page-header-custom {
        background: linear-gradient(135deg, var(--secondary-bg-color) 0%, var(--tertiary-bg-color) 100%);
        padding: 1.5rem 1rem; /* মোবাইলের জন্য হেডার প্যাডিং */
        border-radius: .75rem;
        margin-bottom: 1.5rem; /* মোবাইলের জন্য হেডার মার্জিন */
        text-align: center;
    }
    body.dark-mode .page-header-custom {
         background: linear-gradient(135deg, var(--bs-gray-800) 0%, var(--bs-gray-900) 100%);
    }
    .page-header-custom h1 {
        color: var(--bs-primary-text-emphasis);
        font-weight: 700;
        font-size: 1.75rem; /* মোবাইলের জন্য হেডার টাইটেল সাইজ */
    }
    .page-header-custom p {
        color: var(--bs-secondary-text-emphasis);
        font-size: 1rem; /* মোবাইলের জন্য হেডার সাবটাইটেল সাইজ */
    }
    @media (min-width: 576px) { /* Small devices (sm) and up */
        .category-card {
            padding: 1.5rem; /* বড় স্ক্রিনের জন্য প্যাডিং আগের মতো */
        }
        .category-card .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .category-card .card-title {
            font-size: 1.25rem;
        }
        .category-card .category-description-placeholder {
             min-height: 20px;
        }
        .category-card .question-count {
            font-size: 0.85rem;
        }
         .category-card .btn {
            font-size: 0.9rem; /* Ensure button size is appropriate for larger screens */
            padding: 0.5rem 1rem;
        }
        .page-header-custom {
            padding: 2rem 1rem;
            margin-bottom: 2rem;
        }
        .page-header-custom h1 {
            font-size: 2.25rem; 
        }
        .page-header-custom p {
            font-size: 1.1rem;
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
        <?php // পরিবর্তন এখানে: row-cols-2 mobiles (default), row-cols-sm-2 for sm, row-cols-md-3 for md, row-cols-lg-4 for lg and up ?>
        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3"> {/* g-3 for slightly less gap on mobile */}
            <?php foreach ($categories as $category): ?>
                <div class="col">
                    <div class="category-card">
                        <?php if (!empty($category['icon_class'])): ?>
                            <div class="card-icon"><i class="<?php echo htmlspecialchars($category['icon_class']); ?>"></i></div>
                        <?php else: ?>
                             <div class="card-icon"><i class="fas fa-tags"></i></div>
                        <?php endif; ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                        
                        <div class="category-description-placeholder"></div>

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
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>