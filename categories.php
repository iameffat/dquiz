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

// কিছু স্যাম্পল গ্র্যাডিয়েন্ট কালার
$gradient_colors = [
    "linear-gradient(135deg, #667eea 0%, #764ba2 100%)", // বেগুনী-নীল
    "linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)", // গোলাপী
    "linear-gradient(135deg, #f6d365 0%, #fda085 100%)", // কমলা-হলুদ
    "linear-gradient(135deg, #5ee7df 0%, #b490ca 100%)", // সায়ান-পার্পল
    "linear-gradient(135deg, #d299c2 0%, #fef9d7 100%)", // হালকা গোলাপী-ক্রিম
    "linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)", // আকাশি নীল
    "linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)", // সবুজ-নীল
    "linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)", // পীচ-পার্পল
    "linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)", // ল্যাভেন্ডার-নীল
    "linear-gradient(135deg, #f093fb 0%, #f5576c 100%)", // ম্যাজেন্টা-লাল
];
$num_colors = count($gradient_colors);

$page_specific_styles = "
    .category-card {
        border: none; /* গ্র্যাডিয়েন্টের জন্য বর্ডার বাদ দেওয়া হলো */
        border-radius: 0.75rem; /* একটু বেশি রাউন্ডেড */
        transition: all 0.3s ease-in-out;
        display: flex;
        flex-direction: column;
        height: 100%;
        text-align: center;
        padding: 1.5rem;
        color: #fff; /* গ্র্যাডিয়েন্টের উপর সাদা টেক্সট */
        overflow: hidden; /* ইনকেস কোনো এলিমেন্ট বাইরে যায় */
        position: relative; /* আফটার এলিমেন্টের জন্য */
    }
    .category-card::before { /* Optional: subtle overlay for better text readability */
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.15); /* হালকা কালো ওভারলে */
        border-radius: 0.75rem;
        z-index: 1;
    }
    .category-card > * { /* Ensure content is above the overlay */
        position: relative;
        z-index: 2;
    }

    .category-card:hover {
        box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.2);
        transform: translateY(-7px) scale(1.02);
    }
    /* ডার্ক মোডে হোভার শ্যাডো পরিবর্তন করা যেতে পারে */
    body.dark-mode .category-card:hover {
        box-shadow: 0 0.75rem 1.5rem rgba(255, 255, 255, 0.15);
    }

    .category-card .card-icon {
        font-size: 3rem; 
        margin-bottom: 1rem;
        /* আইকনের রঙ সরাসরি সাদা বা গ্র্যাডিয়েন্টের সাথে মানানসই */
        color: rgba(255, 255, 255, 0.9); 
    }
   
    .category-card .card-title {
        font-size: 1.35rem; /* টাইটেল একটু বড় করা হলো */
        font-weight: 700;  /* আরও বোল্ড */
        color: #fff; /* টাইটেলের রঙ সাদা */
        margin-bottom: 0.5rem;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.3); /* টেক্সটে হালকা শ্যাডো */
    }
    .category-card .category-description-placeholder {
        margin-bottom: 1rem;
        flex-grow: 1;
        min-height: 20px; 
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.85); /* বিবরণের রঙ */
    }
    .category-card .question-count {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.75); /* প্রশ্ন সংখ্যার রঙ */
        margin-bottom: 1.25rem;
    }
    .category-card .btn-practice { /* কাস্টম বাটন স্টাইল */
        background-color: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.5);
        color: #fff;
        font-weight: 500;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    .category-card .btn-practice:hover {
        background-color: rgba(255,255,255,0.35);
        border-color: rgba(255,255,255,0.75);
        color: #fff;
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
    
    /* মোবাইলের জন্য স্টাইল (আগের মতই) */
    @media (max-width: 575.98px) { /* Extra small devices */
        .category-card {
            padding: 1rem;
        }
        .category-card .card-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }
        .category-card .card-title {
            font-size: 1.15rem; /* মোবাইলের জন্য টাইটেল */
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
                // গ্র্যাডিয়েন্ট কালার নির্ধারণ
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
                            <?php /* বিবরণ এখানে দেখানো হচ্ছে না আপনার অনুরোধ অনুযায়ী */ ?>
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
// Font Awesome এর জন্য CDN লিঙ্ক (যদি হেডার ফাইলে না থাকে)
// echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';
if ($conn) { $conn->close(); }
require_once 'includes/footer.php';
?>