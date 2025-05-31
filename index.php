<?php
$page_title = "DeeneLife Quiz - জ্ঞানার্জনের একটি আনন্দময় মাধ্যম";
$page_description = "দ্বীনিলাইফ কুইজ প্লাটফর্মে ইসলামিক জ্ঞান পরীক্ষা করুন। কুরআন, হাদিস, সীরাত এবং ইসলামিক সাধারণ জ্ঞানের উপর আকর্ষণীয় কুইজে অংশগ্রহণ করুন এবং পুরস্কার জিতুন।";
$page_keywords = "ইসলামিক কুইজ, অনলাইন কুইজ, দ্বীনি কুইজ, বাংলা কুইজ, ইসলামিক জ্ঞান, DeeneLife Quiz, কুরআন কুইজ, হাদিস কুইজ, সীরাত কুইজ";
// $page_og_image = $base_url . 'assets/images/homepage_og.jpg'; // একটি ভিন্ন OG ইমেজ দিতে পারেন হোমপেজের জন্য

$base_url = ''; // Root directory
require_once 'includes/db_connect.php'; // ডাটাবেস কানেকশন ও সেশন শুরু
require_once 'includes/functions.php';   // কমন ফাংশন

// Fetch upcoming quiz settings
$settings = [];
$sql_settings = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('upcoming_quiz_enabled', 'upcoming_quiz_title', 'upcoming_quiz_end_date')";
$result_settings = $conn->query($sql_settings);
if ($result_settings && $result_settings->num_rows > 0) {
    while ($row = $result_settings->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$upcoming_quiz_enabled = isset($settings['upcoming_quiz_enabled']) ? (bool)$settings['upcoming_quiz_enabled'] : false;
$upcoming_quiz_title = isset($settings['upcoming_quiz_title']) ? htmlspecialchars($settings['upcoming_quiz_title'], ENT_QUOTES, 'UTF-8') : "আপকামিং কুইজ";
$upcoming_quiz_date_str = isset($settings['upcoming_quiz_end_date']) ? $settings['upcoming_quiz_end_date'] : null;


// Fetch Recent Live Quizzes (Limit to 3 for homepage)
$recent_live_quizzes = [];
$sql_recent_live = "SELECT q.id, q.title, q.description, q.duration_minutes,
                    (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                    FROM quizzes q
                    WHERE q.status = 'live'
                    AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW())
                    AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())
                    ORDER BY q.created_at DESC, q.id DESC
                    LIMIT 3";
$result_recent_live = $conn->query($sql_recent_live);
if ($result_recent_live && $result_recent_live->num_rows > 0) {
    while ($row = $result_recent_live->fetch_assoc()) {
        $recent_live_quizzes[] = $row;
    }
}

// Fetch Recent Archived Quizzes if no live quizzes or to fill up to 3 slots
$recent_archived_quizzes = [];
$needed_archived = 3 - count($recent_live_quizzes);

if ($needed_archived > 0) {
    $sql_recent_archived = "SELECT q.id, q.title, q.description, q.duration_minutes,
                            (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                            FROM quizzes q
                            WHERE q.status = 'archived'
                            OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW())
                            ORDER BY q.created_at DESC, q.id DESC
                            LIMIT " . $needed_archived;
    $result_recent_archived = $conn->query($sql_recent_archived);
    if ($result_recent_archived && $result_recent_archived->num_rows > 0) {
        while ($row = $result_recent_archived->fetch_assoc()) {
            $is_already_live = false;
            foreach ($recent_live_quizzes as $live_quiz) {
                if ($live_quiz['id'] == $row['id']) {
                    $is_already_live = true;
                    break;
                }
            }
            if (!$is_already_live) {
                $recent_archived_quizzes[] = $row;
            }
        }
    }
}
$recent_quizzes_for_display = array_merge($recent_live_quizzes, $recent_archived_quizzes);


$page_specific_styles = "
    body {
        background-color: #f4f7f6; /* হালকা অফ-হোয়াইট ব্যাকগ্রাউন্ড */
    }
    .hero-section {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        /* আগের স্নো ইফেক্ট এর ক্যানভাস এখানেও কাজ করতে পারে, অথবা ভিন্ন ইফেক্ট */
        padding: 5rem 1.5rem;
        text-align: center;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    #snow-canvas { /* আগের স্নো ক্যানভাস */
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        pointer-events: none;
    }
    .hero-content {
        position: relative;
        z-index: 1;
    }
    .hero-section h1 {
        font-size: 2.8rem;
        font-weight: 700;
        margin-bottom: 1rem;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        animation: fadeInDown 1s ease-out;
    }
    .hero-section p.lead {
        font-size: 1.2rem;
        margin-bottom: 2rem;
        max-width: 750px;
        margin-left: auto;
        margin-right: auto;
        opacity: 0.9;
        animation: fadeInUp 1s ease-out 0.3s;
        animation-fill-mode: backwards;
    }
    .hero-section .upcoming-quiz-box {
        background-color: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        padding: 1.5rem;
        border-radius: 10px;
        margin-top: 2rem;
        margin-bottom: 2.5rem;
        display: inline-block; /* কন্টেন্ট অনুযায়ী সাইজ */
        animation: fadeInUp 1s ease-out 0.5s;
        animation-fill-mode: backwards;
    }
    .hero-section .upcoming-quiz-box h3 {
        font-size: 1.4rem;
        font-weight: 600;
        color: #fff; /* হলুদ বা উজ্জ্বল রঙ ভালো লাগবে */
        margin-bottom: 0.5rem;
    }
    .hero-section .upcoming-quiz-box p {
        font-size: 1.1rem;
        color: #f0f0f0;
        margin-bottom: 0;
    }
    .hero-section .btn-participate {
        background-color: #ffc107; /* হলুদ */
        border-color: #ffc107;
        color: #212529; /* গাঢ় টেক্সট */
        padding: 0.8rem 2rem;
        font-size: 1.15rem;
        font-weight: 600;
        border-radius: 50px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        animation: fadeInUp 1s ease-out 0.7s;
        animation-fill-mode: backwards;
    }
    .hero-section .btn-participate:hover {
        background-color: #e0a800;
        border-color: #d39e00;
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .content-section {
        padding: 3.5rem 0;
    }
    .section-card {
        background-color: #ffffff;
        border: none; /* বর্ডার উঠিয়ে শ্যাডো ব্যবহার */
        border-radius: 12px;
        padding: 2.5rem;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        margin-bottom: 2.5rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .section-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    }
    .section-title {
        color: #343a40;
        margin-bottom: 2rem;
        text-align: center;
        font-weight: 700;
        font-size: 2rem;
        position: relative;
        padding-bottom: 0.75rem;
    }
    .section-title::after { /* টাইটেলের নিচে একটি ছোট আন্ডারলাইন */
        content: '';
        display: block;
        width: 70px;
        height: 3px;
        background-color: #007bff;
        margin: 0.5rem auto 0;
        border-radius: 2px;
    }
     .quiz-rules-list p, .how-to-list li {
        font-size: 1.05rem;
        line-height: 1.8;
        color: #555;
        margin-bottom: 0.8rem;
    }
    .how-to-list {
        list-style: none;
        padding-left: 0;
    }
    .how-to-list li {
        position: relative;
        padding-left: 30px;
    }
    .how-to-list li::before {
        content: '\\27A4'; /* আকর্ষণীয় বুলেট পয়েন্ট */
        color: #007bff;
        font-size: 1.2rem;
        position: absolute;
        left: 0;
        top: 3px;
    }

    .recent-quiz-card {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        transition: transform 0.25s ease-in-out, box-shadow 0.25s ease-in-out;
        background-color: #fff;
    }
    .recent-quiz-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .recent-quiz-card .card-body {
        padding: 1.25rem;
    }
    .recent-quiz-card .card-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: #0056b3; /* গাঢ় নীল */
        margin-bottom: 0.5rem;
    }
    .recent-quiz-card .card-text {
        font-size: 0.9rem;
        color: #5a6268;
        min-height: 60px; /* বিবরণীর জন্য একটি নির্দিষ্ট উচ্চতা */
    }
     .recent-quiz-card ul {
        font-size: 0.85rem;
        color: #495057;
    }
    .recent-quiz-card .btn-view-quiz {
        font-size: 0.9rem;
        padding: 0.4rem 1rem;
        background-color: #28a745; /* সবুজ বাটন */
        border-color: #28a745;
        color:white;
    }
    .recent-quiz-card .btn-view-quiz:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }
    .all-quizzes-link-container {
        margin-top: 2.5rem;
    }
    .all-quizzes-link-container .btn-light {
        border: 1px solid #ccc;
        padding: 0.6rem 1.5rem;
        font-weight: 500;
    }

    /* CSS Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-25px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(25px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .hero-section {
            padding: 4rem 1rem;
        }
        .hero-section h1 {
            font-size: 2.2rem;
        }
        .hero-section p.lead {
            font-size: 1.1rem;
        }
        .hero-section .upcoming-quiz-box {
            padding: 1rem;
            margin-top: 1.5rem;
            margin-bottom: 2rem;
        }
        .hero-section .upcoming-quiz-box h3 {
            font-size: 1.2rem;
        }
        .section-title {
            font-size: 1.7rem;
        }
        .section-card {
            padding: 1.5rem;
        }
    }
";

require_once 'includes/header.php'; // HTML হেডার অংশ
?>

<div class="hero-section">
    <canvas id="snow-canvas"></canvas> <div class="hero-content container">
        <h1>দ্বীনিলাইফ কুইজে আপনাকে স্বাগতম!</h1>
        <p class="lead">জ্ঞানার্জনের একটি আনন্দময় এবং প্রতিযোগিতামূলক পরিবেশে আপনার ইসলামিক জ্ঞানকে আরও সমৃদ্ধ করুন। আমাদের সাথে নিয়মিত কুইজে অংশগ্রহণ করে জিতে নিন মূল্যবান পুরস্কার এবং অর্জন করুন আল্লাহর সন্তুষ্টি।</p>
        
        <?php if ($upcoming_quiz_enabled && $upcoming_quiz_date_str): ?>
        <div class="upcoming-quiz-box">
            <?php
            try {
                $target_date = new DateTime($upcoming_quiz_date_str);
                $current_date = new DateTime();
                $target_date_for_diff = new DateTime($target_date->format('Y-m-d'));
                $current_date_for_diff = new DateTime($current_date->format('Y-m-d'));

                if ($current_date_for_diff > $target_date_for_diff) {
                    echo '<h3>' . $upcoming_quiz_title . '</h3>';
                    echo '<p>এই কুইজটি ইতিমধ্যে শেষ হয়ে গিয়েছে। পরবর্তী কুইজের জন্য আমাদের সাথেই থাকুন!</p>';
                } else {
                    $interval = $current_date_for_diff->diff($target_date_for_diff);
                    $days_left = $interval->days;
                    echo '<h3>' . $upcoming_quiz_title . '</h3>';
                    if ($days_left > 0) {
                        echo '<p>শুরু হতে আর মাত্র <span class="fw-bold fs-4 text-warning">' . $days_left . '</span> দিন বাকি!</p>';
                    } else {
                        echo '<p class="text-warning fw-bold fs-4">আজকেই কুইজ!</p>';
                    }
                }
            } catch (Exception $e) {
                echo '<p class="text-light">আপকামিং কুইজের তথ্য শীঘ্রই আসছে...</p>';
            }
            ?>
        </div>
        <?php elseif($upcoming_quiz_enabled): ?>
         <div class="upcoming-quiz-box">
            <h3><?php echo $upcoming_quiz_title; ?></h3>
            <p>শীঘ্রই আসছে... বিস্তারিত তথ্যের জন্য আমাদের সাথেই থাকুন।</p>
        </div>
        <?php endif; ?>

        <div>
            <a href="quizzes.php" class="btn btn-participate btn-lg" type="button">কুইজে অংশগ্রহণ করুন</a>
        </div>
    </div>
</div>

<div class="container content-section">
    <?php if (!empty($recent_quizzes_for_display)): ?>
    <div class="section-card recent-quizzes-home">
        <h2 class="section-title">সাম্প্রতিক কুইজসমূহ</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($recent_quizzes_for_display as $quiz): ?>
            <div class="col d-flex align-items-stretch">
                <div class="card h-100 recent-quiz-card w-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                        <p class="card-text">
                            <?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 70)) . (strlen($quiz['description'] ?? '') > 70 ? '...' : ''); ?>
                        </p>
                        <ul class="list-unstyled small mt-auto pt-2 mb-2">
                            <li><i class="fas fa-clock me-1 text-primary"></i> <strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                            <li><i class="fas fa-question-circle me-1 text-primary"></i> <strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                        </ul>
                        <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-view-quiz mt-2 align-self-start">অংশগ্রহণ করুন <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center all-quizzes-link-container">
            <a href="quizzes.php" class="btn btn-light shadow-sm">সকল কুইজ দেখুন</a>
        </div>
    </div>
    <?php endif; ?>


    <div class="row">
        <div class="col-lg-7 col-md-6 mb-4 mb-md-0 d-flex">
            <div class="section-card quiz-rules-list w-100">
                <h2 class="section-title">কুইজের নিয়মাবলী</h2>
                <p><i class="fas fa-user-check me-2 text-primary"></i>প্রতিটি কুইজে অংশগ্রহণের জন্য লগইন/রেজিস্ট্রেশন করা আবশ্যক।</p>
                <p><i class="fas fa-list-ol me-2 text-primary"></i>প্রতিটি প্রশ্নের চারটি অপশন থাকবে, যার মধ্যে একটি সঠিক উত্তর নির্বাচন করতে হবে।</p>
                <p><i class="fas fa-undo-alt me-2 text-primary"></i>একবার উত্তর নির্বাচন করার পর তা পরিবর্তন করা যাবে না।</p>
                <p><i class="fas fa-hourglass-half me-2 text-primary"></i>নির্দিষ্ট সময়ের মধ্যে কুইজ সম্পন্ন করতে হবে। সময় শেষ হলে স্বয়ংক্রিয়ভাবে সাবমিট হয়ে যাবে।</p>
                <p><i class="fas fa-trophy me-2 text-primary"></i>ফলাফলের ভিত্তিতে র‍্যাংকিং নির্ধারিত হবে। সর্বোচ্চ স্কোর এবং কম সময়ে সম্পন্নকারীরা তালিকায় উপরে থাকবেন।</p>
                <p><i class="fas fa-times-circle me-2 text-danger"></i>কোনো প্রকার অসদুপায় অবলম্বন করলে অংশগ্রহণ বাতিল বলে গণ্য হবে।</p>
            </div>
        </div>
        <div class="col-lg-5 col-md-6 d-flex">
            <div class="section-card how-to-list w-100">
                <h2 class="section-title">কিভাবে অংশগ্রহণ করবেন</h2>
                <ul class="how-to-list">
                    <li>প্রথমে, সাইটে <a href="register.php">রেজিস্ট্রেশন</a> করুন অথবা <a href="login.php">লগইন</a> করুন।</li>
                    <li>"সকল কুইজ" পেইজ থেকে আপনার পছন্দের কুইজটি নির্বাচন করুন।</li>
                    <li>"অংশগ্রহণ করুন" বাটনে ক্লিক করে কুইজ শুরু করুন।</li>
                    <li>সঠিক উত্তর নির্বাচন করে সময় শেষ হওয়ার আগে সাবমিট করুন।</li>
                    <li>সাবমিট করার পর আপনার ফলাফল এবং সঠিক উত্তরগুলো দেখতে পাবেন।</li>
                    <li>নির্ধারিত কুইজের জন্য "র‍্যাংকিং" পেইজে আপনার অবস্থান দেখুন।</li>
                </ul>
            </div>
        </div>
    </div>
</div>


<?php
// Font Awesome CDN (যদি header.php তে না থাকে)
// echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';

if ($conn) {
    $conn->close();
}
include 'includes/footer.php';
?>

<script>
// আগের জাভাস্ক্রিপ্ট কোড (স্নো ইফেক্টের জন্য) অপরিবর্তিত থাকবে
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('snow-canvas');
    if (!canvas) return;

    const heroSection = document.querySelector('.hero-section'); // পরিবর্তিত ক্লাস নেম
    const ctx = canvas.getContext('2d');
    let particles = [];
    const particleCount = 60; // একটু বাড়িয়েছি

    function resizeCanvas() {
        if(!heroSection) return;
        canvas.width = heroSection.offsetWidth;
        canvas.height = heroSection.offsetHeight;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function Particle(x, y, size, speed, opacity) {
        this.x = x;
        this.y = y;
        this.size = size;
        this.speed = speed;
        this.opacity = opacity;

        this.update = function() {
            this.y += this.speed;
            this.x += Math.sin(this.y / (40 + Math.random()*60)) * 0.4; // সামান্য পরিবর্তন

            if (this.y > canvas.height) {
                this.y = 0 - this.size;
                this.x = Math.random() * canvas.width;
                this.speed = Math.random() * 0.6 + 0.2; // সামান্য পরিবর্তন
                this.size = Math.random() * 2.5 + 0.8; // সামান্য পরিবর্তন
            }
        };

        this.draw = function() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity * 0.7})`; // স্নো এর রঙ সাদাটে, অপাসিটি একটু কমানো
            ctx.fill();
        };
    }

    function initParticles() {
        particles = [];
        for (let i = 0; i < particleCount; i++) {
            const x = Math.random() * canvas.width;
            const y = Math.random() * canvas.height;
            const size = Math.random() * 2 + 0.7; // সাইজ
            const speed = Math.random() * 0.4 + 0.15; // গতি
            const opacity = Math.random() * 0.6 + 0.2; // অপাসিটি
            particles.push(new Particle(x, y, size, speed, opacity));
        }
    }

    function animateParticles() {
        if(!heroSection || !canvas) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(particle => {
            particle.update();
            particle.draw();
        });
        requestAnimationFrame(animateParticles);
    }

    if (heroSection && heroSection.offsetHeight > 0) {
        initParticles();
        animateParticles();
    }
    window.addEventListener('resize', function() {
        resizeCanvas();
        initParticles();
    });
});
</script>