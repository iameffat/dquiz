<?php
$page_title = "DeeneLife Quiz";
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
                    LIMIT 3"; //
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
                            LIMIT " . $needed_archived; //
    $result_recent_archived = $conn->query($sql_recent_archived);
    if ($result_recent_archived && $result_recent_archived->num_rows > 0) {
        while ($row = $result_recent_archived->fetch_assoc()) {
            // Ensure it's not already listed in live_quizzes
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


// Page-specific CSS for Minimal Hero Section, Animations, Snow
$page_specific_styles = "
    body {
        /* overflow-x: hidden; Optional: if animations cause horizontal scroll */
    }
    .minimal-hero-section {
        /* MODIFIED: Changed background-color to a subtle white gradient */
        background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
        padding: 6rem 1.5rem;
        text-align: center;
        color: #343a40; /* Darker text for contrast on light background */
        position: relative; /* For snow canvas positioning */
        overflow: hidden; /* To contain snow particles if they go slightly out */
        border-bottom: 1px solid #dee2e6;
    }
    #snow-canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0; /* Behind the content */
        pointer-events: none; /* Canvas should not intercept mouse events */
    }
    .hero-content {
        position: relative; /* To ensure content is above the snow canvas */
        z-index: 1;
    }
    .minimal-hero-section h1 {
        font-size: 3rem; /* Slightly smaller than before, but impactful */
        font-weight: 600; /* Lighter than 700 for a softer feel */
        margin-bottom: 1rem;
        animation: fadeInDown 1s ease-out;
    }
    .minimal-hero-section p.lead {
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        color: #495057; /* Slightly lighter than main text */
        max-width: 700px; /* Limit width for readability */
        margin-left: auto;
        margin-right: auto;
        animation: fadeInUp 1s ease-out 0.3s;
        animation-fill-mode: backwards; /* Start animation from hidden state */
    }
    .minimal-hero-section .upcoming-quiz-info h3 {
        font-size: 1.5rem;
        font-weight: 500;
        color: #007bff; /* Bootstrap primary color */
        margin-top: 1.5rem;
        animation: fadeInUp 1s ease-out 0.5s;
        animation-fill-mode: backwards;
    }
    .minimal-hero-section .upcoming-quiz-info p {
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
        animation: fadeInUp 1s ease-out 0.7s;
        animation-fill-mode: backwards;
    }
    .minimal-hero-section .btn-custom-primary {
        background-color: #007bff;
        border-color: #007bff;
        color: white;
        padding: 0.75rem 1.8rem;
        font-size: 1.1rem;
        border-radius: 50px; /* Pill-shaped button */
        transition: all 0.3s ease;
        animation: fadeInUp 1s ease-out 0.9s;
        animation-fill-mode: backwards;
    }
    .minimal-hero-section .btn-custom-primary:hover {
        background-color: #0056b3;
        border-color: #0056b3;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
    }

    .content-section { /* For rules section and future sections */
        padding: 3rem 0;
        animation: fadeIn 1.5s ease-out;
    }
    .quiz-rules-minimal, .how-to-participate, .recent-quizzes-section {
        background-color: #ffffff; /* White background for rules */
        border: 1px solid #e9ecef; /* Subtle border */
        border-radius: 12px; /* Softer radius */
        padding: 2.5rem;
        box-shadow: 0 6px 18px rgba(0,0,0,0.07);
        margin-bottom: 2.5rem;
    }
    .section-title {
        color: #343a40;
        margin-bottom: 1.5rem;
        text-align: center;
        font-weight: 600;
        font-size: 1.8rem;
    }
     .quiz-rules-minimal p, .how-to-participate ul li {
        font-size: 1rem;
        line-height: 1.7;
        color: #495057;
    }
    .how-to-participate ul {
        list-style: none;
        padding-left: 0;
    }
    .how-to-participate ul li {
        position: relative;
        padding-left: 25px; /* Space for custom bullet */
        margin-bottom: 0.75rem;
    }
    .how-to-participate ul li::before {
        content: '\\2713'; /* Checkmark unicode */
        color: #28a745; /* Bootstrap success color */
        font-weight: bold;
        position: absolute;
        left: 0;
        top: 2px; /* Adjust vertical alignment */
    }

    .quiz-card-sm { /* For recent quizzes on homepage */
        border: 1px solid #dee2e6;
        border-radius: 8px;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .quiz-card-sm:hover {
        transform: translateY(-4px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .quiz-card-sm .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #007bff;
    }
    .quiz-card-sm .card-text {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .quiz-card-sm .btn-outline-primary {
        font-size: 0.85rem;
        padding: 0.3rem 0.75rem;
    }


    /* CSS Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .minimal-hero-section {
            padding: 4rem 1rem;
        }
        .minimal-hero-section h1 {
            font-size: 2.2rem;
        }
        .minimal-hero-section p.lead {
            font-size: 1rem;
        }
        .minimal-hero-section .upcoming-quiz-info h3 {
            font-size: 1.3rem;
        }
        .minimal-hero-section .upcoming-quiz-info p {
            font-size: 1rem;
        }
        .section-title {
            font-size: 1.5rem;
        }
        .quiz-rules-minimal, .how-to-participate, .recent-quizzes-section {
            padding: 1.5rem;
        }
    }
"; //

require_once 'includes/header.php'; // HTML হেডার অংশ
?>

<div class="minimal-hero-section">
    <canvas id="snow-canvas"></canvas>
    <div class="hero-content">
        <div class="container">
            <h1>দ্বীনিলাইফ কুইজে আপনাকে স্বাগতম!</h1>
            <p class="lead">জ্ঞানার্জন ইবাদতের অংশ এবং প্রতিটি মুসলমানের জন্য ফরজ। তাই নিয়মিত দ্বীনিলাইফে আয়োজন হচ্ছে কুইজ প্রতিযোগিতা, যেখানে আপনি ইসলামের মৌলিক জ্ঞানকে যাচাই করতে পারবেন শিক্ষণীয় কুইজের মাধ্যমে।</p>
            
          <div class="upcoming-quiz-info">
            <?php
            if ($upcoming_quiz_enabled && $upcoming_quiz_date_str) { //
                try {
                    $target_date = new DateTime($upcoming_quiz_date_str);
                    $current_date = new DateTime();
                    // Compare only dates, not time.
                    $target_date_for_diff = new DateTime($target_date->format('Y-m-d'));
                    $current_date_for_diff = new DateTime($current_date->format('Y-m-d'));


                    if ($current_date_for_diff > $target_date_for_diff) {
                        echo '<h3>' . $upcoming_quiz_title . '</h3>';
                        echo '<p>এই কুইজটি ইতিমধ্যে শেষ হয়ে গিয়েছে। পরবর্তী কুইজের জন্য অপেক্ষা করুন।</p>';
                    } else {
                        $interval = $current_date_for_diff->diff($target_date_for_diff);
                        $days_left = $interval->days;
                        echo '<h3>' . $upcoming_quiz_title . '</h3>';
                        if ($days_left > 0) {
                            echo '<p>আর মাত্র <span class="fw-bold fs-4">' . $days_left . '</span> দিন বাকি</p>';
                        } else { // days_left is 0, means today
                            echo '<p class="text-primary fw-bold fs-4">আজকেই কুইজ!</p>';
                        }
                    }
                } catch (Exception $e) {
                    // Log error if needed: error_log("Error parsing upcoming quiz date: " . $e->getMessage());
                    echo '<p class="text-warning">আপকামিং কুইজের তারিখ সঠিকভাবে সেট করা হয়নি।</p>';
                }
            } elseif ($upcoming_quiz_enabled) { //
                 // Enabled but date string is missing
                 echo '<p class="fs-5">আপকামিং কুইজের তথ্য শীঘ্রই আপডেট করা হবে।</p>';
            }
            ?>
            </div>
            <a href="quizzes.php" class="btn btn-custom-primary btn-lg mt-3" type="button">কুইজে অংশগ্রহণ করুন</a>
        </div>
    </div>
</div>

<div class="container content-section">
    <?php if (!empty($recent_quizzes_for_display)): ?>
    <div class="recent-quizzes-section">
        <h2 class="section-title">সাম্প্রতিক কুইজসমূহ</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($recent_quizzes_for_display as $quiz): ?>
            <div class="col">
                <div class="card h-100 quiz-card-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                        <p class="card-text text-muted small">
                            <?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 80)) . (strlen($quiz['description'] ?? '') > 80 ? '...' : ''); ?>
                        </p>
                        <ul class="list-unstyled small mt-auto pt-2 mb-2">
                            <li><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                            <li><strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                        </ul>
                        <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-outline-primary align-self-start">অংশগ্রহণ করুন</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
         <div class="text-center mt-4">
            <a href="quizzes.php" class="btn btn-light">সকল কুইজ দেখুন</a>
        </div>
    </div>
    <?php endif; ?>


    <div class="row">
        <div class="col-lg-7 col-md-6 mb-4 mb-md-0">
            <div class="quiz-rules-minimal h-100">
                <h2 class="section-title">কুইজের নিয়মাবলী</h2>
                <p>১. প্রতিটি কুইজে অংশগ্রহণের জন্য লগইন/রেজিস্ট্রেশন করা আবশ্যক।</p>
                <p>২. প্রতিটি প্রশ্নের চারটি অপশন থাকবে, যার মধ্যে একটি সঠিক উত্তর নির্বাচন করতে হবে।</p>
                <p>৩. একবার উত্তর নির্বাচন করার পর তা পরিবর্তন করা যাবে না।</p>
                <p>৪. নির্দিষ্ট সময়ের মধ্যে কুইজ সম্পন্ন করতে হবে। সময় শেষ হলে স্বয়ংক্রিয়ভাবে সাবমিট হয়ে যাবে।</p>
                <p>৫. ফলাফলের ভিত্তিতে র‍্যাংকিং নির্ধারিত হবে। সর্বোচ্চ স্কোর এবং কম সময়ে সম্পন্নকারীরা তালিকায় উপরে থাকবেন।</p>
                <p>৬. কোনো প্রকার অসদুপায় অবলম্বন করলে অংশগ্রহণ বাতিল বলে গণ্য হবে।</p>
            </div>
        </div>
        <div class="col-lg-5 col-md-6">
            <div class="how-to-participate h-100">
                <h2 class="section-title">কিভাবে অংশগ্রহণ করবেন</h2>
                <ul>
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
if ($conn) {
    $conn->close();
}
include 'includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('snow-canvas');
    if (!canvas) return; // Exit if canvas not found

    const heroSection = document.querySelector('.minimal-hero-section');
    const ctx = canvas.getContext('2d');
    let particles = [];
    const particleCount = 50; // Keep it low for minimal effect

    function resizeCanvas() {
        if(!heroSection) return;
        canvas.width = heroSection.offsetWidth;
        canvas.height = heroSection.offsetHeight;
    }

    // Initialize and resize canvas
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Particle object
    function Particle(x, y, size, speed, opacity) {
        this.x = x;
        this.y = y;
        this.size = size;
        this.speed = speed;
        this.opacity = opacity;

        this.update = function() {
            this.y += this.speed;
            // Add a little horizontal sway
            this.x += Math.sin(this.y / (50 + Math.random()*50)) * 0.3;


            if (this.y > canvas.height) {
                this.y = 0 - this.size; // Reset to top
                this.x = Math.random() * canvas.width; // Random x position
                this.speed = Math.random() * 0.5 + 0.2; // Random speed
                this.size = Math.random() * 2 + 1; // Random size
            }
        };

        this.draw = function() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(200, 200, 200, ${this.opacity})`; // Light greyish snow
            ctx.fill();
        };
    }

    // Create particles
    function initParticles() {
        particles = []; // Clear existing particles on resize/re-init
        for (let i = 0; i < particleCount; i++) {
            const x = Math.random() * canvas.width;
            const y = Math.random() * canvas.height; // Start at random y positions
            const size = Math.random() * 1.5 + 0.5; // Smaller particles: 0.5 to 2px
            const speed = Math.random() * 0.3 + 0.1; // Slower speed: 0.1 to 0.4
            const opacity = Math.random() * 0.5 + 0.3; // More transparent: 0.3 to 0.8
            particles.push(new Particle(x, y, size, speed, opacity));
        }
    }

    // Animation loop
    function animateParticles() {
        if(!heroSection || !canvas) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(particle => {
            particle.update();
            particle.draw();
        });
        requestAnimationFrame(animateParticles);
    }

    // Only start if hero section is visible (basic check)
    if (heroSection && heroSection.offsetHeight > 0) {
        initParticles();
        animateParticles();
    }
    // Re-initialize particles on resize to adapt to new canvas size
    window.addEventListener('resize', function() {
        resizeCanvas();
        initParticles(); // Re-create particles for new dimensions
    });
});
</script>