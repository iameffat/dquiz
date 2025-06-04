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

// হালকা সলিড কালারের উদাহরণ (আপনি এগুলো পরিবর্তন করতে পারেন)
$solid_colors = [
    "#e7f5ff", // খুব হালকা নীল
    "#e0f7fa", // খুব হালকা সায়ান
    "#e8f5e9", // খুব হালকা সবুজ
    "#fffde7", // খুব হালকা হলুদ
    "#fce4ec", // খুব হালকা গোলাপী
    "#f3e5f5", // খুব হালকা পার্পল (ল্যাভেন্ডার)
    "#fff8e1", // খুব হালকা কমলা (ক্রিম)
    "#f1f8e9", // আরও একটি হালকা সবুজ
    "#e3f2fd", // আরও একটি হালকা নীল
    "#ffebee", // খুব হালকা লাল/গোলাপী
    "#fafafa", // প্রায় সাদা (হালকা ধূসর)
    "#e0e0e0", // খুব হালকা ধূসর
];
$num_colors = count($solid_colors);

$page_specific_styles = "
    .category-card {
        border: 1px solid var(--bs-border-color-translucent); 
        border-radius: 0.75rem;
        transition: all 0.3s ease-in-out;
        display: flex;
        flex-direction: column;
        height: 100%;
        text-align: center;
        padding: 1.5rem;
        color: var(--bs-body-color); 
        overflow: hidden;
        position: relative;
    }

    .category-card:hover {
        box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.12); /* শ্যাডো সামান্য পরিবর্তন */
        transform: translateY(-6px); /* হোভার ইফেক্ট */
    }
    body.dark-mode .category-card {
        color: var(--bs-body-color); 
        border: 1px solid var(--bs-border-color);
        /* ডার্ক মোডে কার্ডের ব্যাকগ্রাউন্ড কালার CSS ভেরিয়েবল থেকে নেওয়া ভালো, 
           নয়তো PHP থেকে আসা লাইট কালারগুলো ডার্ক মোডে বেমানান লাগতে পারে। 
           আপাতত PHP থেকে আসা কালারই ব্যবহৃত হবে। 
           যদি ডার্ক মোডে ভিন্ন কালার চান, তাহলে CSS এ ক্লাস দিয়ে করা ভালো। */
    }
    body.dark-mode .category-card:hover {
        box-shadow: 0 0.5rem 1.25rem rgba(255, 255, 255, 0.1);
    }

    .category-card .card-icon {
        font-size: 2.8rem; 
        margin-bottom: 1rem;
        color: var(--bs-primary); 
    }
    body.dark-mode .category-card .card-icon {
        color: var(--bs-primary-text-emphasis);
    }
   
    .category-card .card-title {
        font-size: 1.25rem; 
        font-weight: 600; 
        color: var(--bs-emphasis-color); 
        margin-bottom: 0.5rem;
    }
    .category-card .category-description-placeholder {
        margin-bottom: 1rem;
        flex-grow: 1;
        min-height: 20px; 
        font-size: 0.9rem;
        color: var(--bs-secondary-color); 
    }
    .category-card .question-count {
        font-size: 0.9rem;
        color: var(--bs-tertiary-color); 
        margin-bottom: 1.25rem;
    }
    .category-card .btn-practice { 
        background-color: var(--bs-primary); 
        border: none;
        color: #fff; 
        font-weight: 500;
        transition: background-color 0.2s ease, transform 0.2s ease;
    }
    .category-card .btn-practice:hover {
        background-color: var(--bs-link-hover-color); 
        color: #fff;
        transform: translateY(-1px);
    }
    body.dark-mode .category-card .btn-practice {
        background-color: var(--bs-primary); /* ডার্ক মোডেও একই রকম বাটন রাখতে পারেন */
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
                // সলিড কালার নির্ধারণ
                $current_solid_color = $solid_colors[$index % $num_colors];
            ?>
                <div class="col">
                    <div class="category-card" style="background-color: <?php echo $current_solid_color; ?>;">
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