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

// হালকা এবং সফট্ গ্র্যাডিয়েন্ট কালারের উদাহরণ
$gradient_colors = [
    "linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)", // ল্যাভেন্ডার থেকে আকাশি
    "linear-gradient(135deg, #fddb92 0%, #d1fdff 100%)", // হালকা পীচ থেকে হালকা সায়ান
    "linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)", // আকাশি থেকে আরও হালকা আকাশি
    "linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)", // খুব হালকা পীচ থেকে স্যালমন
    "linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%)", // হালকা সবুজ থেকে সবুজ
    "linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)", // অফ-হোয়াইট থেকে হালকা ধূসর-নীল
    "linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%)", // প্রায় সাদা
    "linear-gradient(135deg, #fff1eb 0%, #ace0f9 100%)", // হালকা কমলা আভা থেকে হালকা নীল
    "linear-gradient(135deg, #ede574 0%, #e1f5c4 100%)", // হালকা হলুদ থেকে হালকা সবুজ-হলুদ
    "linear-gradient(135deg, #f8f9d2 0%, #e2d1c3 100%)", // হালকা ক্রিম থেকে ফ্যাকাশে বাদামী
    "linear-gradient(135deg, #c1dfc4 0%, #deecdd 100%)", // ফ্যাকাশে সবুজ
    "linear-gradient(135deg, #f3e7e9 0%, #e3eeff 100%)"  // খুব হালকা গোলাপী থেকে খুব হালকা নীল
];
$num_colors = count($gradient_colors);

$page_specific_styles = "
    .category-card {
        border: 1px solid var(--bs-border-color-translucent); /* হালকা বর্ডার যোগ করা হলো */
        border-radius: 0.75rem;
        transition: all 0.3s ease-in-out;
        display: flex;
        flex-direction: column;
        height: 100%;
        text-align: center;
        padding: 1.5rem;
        color: var(--bs-body-color); /* টেক্সটের রঙ পরিবর্তন করে ডিফল্ট করা হলো */
        overflow: hidden;
        position: relative;
    }
    /* হালকা গ্র্যাডিয়েন্টের জন্য ::before ওভারলে বাদ দেওয়া যেতে পারে বা আরও হালকা করা যেতে পারে */
    /* .category-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0,0,0,0.03); 
        border-radius: 0.75rem;
        z-index: 1;
    } */
    .category-card > * {
        position: relative;
        z-index: 2;
    }

    .category-card:hover {
        box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1); /* শ্যাডো একটু কমানো হলো */
        transform: translateY(-5px) scale(1.01); /* হোভার ইফেক্ট একটু কমানো হলো */
    }
    body.dark-mode .category-card {
        color: var(--bs-body-color); /* ডার্ক মোডেও টেক্সটের রঙ পরিবর্তন */
        border: 1px solid var(--bs-border-color);
    }
    body.dark-mode .category-card:hover {
        box-shadow: 0 0.75rem 1.5rem rgba(255, 255, 255, 0.08);
    }

    .category-card .card-icon {
        font-size: 2.8rem; /* আইকন সাইজ সামান্য কমানো */
        margin-bottom: 1rem;
        color: var(--bs-primary); /* আইকনের রঙ প্রাইমারি রাখা হলো */
    }
    body.dark-mode .category-card .card-icon {
        color: var(--bs-primary-text-emphasis);
    }
   
    .category-card .card-title {
        font-size: 1.25rem; /* টাইটেল সাইজ আগের মতো */
        font-weight: 600; 
        color: var(--bs-emphasis-color); /* টেক্সটের রঙ পরিবর্তন */
        margin-bottom: 0.5rem;
        text-shadow: none; /* টেক্সট শ্যাডো বাদ দেওয়া হলো */
    }
    .category-card .category-description-placeholder {
        margin-bottom: 1rem;
        flex-grow: 1;
        min-height: 20px; 
        font-size: 0.9rem;
        color: var(--bs-secondary-color); /* বিবরণের রঙ পরিবর্তন */
    }
    .category-card .question-count {
        font-size: 0.9rem;
        color: var(--bs-tertiary-color); /* প্রশ্ন সংখ্যার রঙ পরিবর্তন */
        margin-bottom: 1.25rem;
    }
    .category-card .btn-practice { 
        background-color: var(--bs-primary); /* বাটনকে প্রাইমারি বাটন করা হলো */
        border: none;
        color: #fff; /* সাদা টেক্সট */
        font-weight: 500;
        transition: background-color 0.2s ease, transform 0.2s ease;
    }
    .category-card .btn-practice:hover {
        background-color: var(--bs-link-hover-color); /* Bootstrap এর ডিফল্ট hover color */
        color: #fff;
        transform: translateY(-1px);
    }
    body.dark-mode .category-card .btn-practice {
        background-color: var(--bs-primary);
        color: #fff;
    }
    body.dark-mode .category-card .btn-practice:hover {
        background-color: var(--bs-link-hover-color);
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
        .category-card {
            padding: 1rem;
        }
        .category-card .card-icon {
            font-size: 2.2rem;
            margin-bottom: 0.75rem;
        }
        .category-card .card-title {
            font-size: 1.1rem; 
        }
        .category-card .btn-practice {
            font-size: 0.9rem;
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
                $current_gradient = $gradient_colors[$index % $num_colors];
            ?>
                <div class="col">
                    <div class="category-card" style="background: <?php echo $current_gradient; ?>;">
                        <?php if (!empty($category['icon_class'])): ?>
                            <div class="card-icon"><i class="<?php echo htmlspecialchars($category['icon_class']); ?>"></i></div>
                        <?php else: ?>
                             <div class="card-icon"><i class="fas fa-tags"></i></div>
                        <?php endif; ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                        
                        <div class="category-description-placeholder">
                            <?php /* বিবরণ এখানে দেখানো হচ্ছে না */ ?>
                        </div>

                        <p class="question-count">(<?php echo $category['question_count']; ?> টি প্রশ্ন)</p>
                        <a href="practice_quiz.php?category_id=<?php echo $category['id']; ?>" class="btn btn-practice mt-auto">অনুশীলন শুরু করুন</a>
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