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

// Page-specific CSS for Minimal Hero Section, Animations, Snow
$page_specific_styles = "
    body {
        /* overflow-x: hidden; Optional: if animations cause horizontal scroll */
    }
    .minimal-hero-section {
        background-color: #f8f9fa; /* Light grey, very minimal */
        /* Or a subtle gradient: background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%); */
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
    .quiz-rules-minimal {
        background-color: #ffffff; /* White background for rules */
        border: 1px solid #e9ecef; /* Subtle border */
        border-radius: 8px; /* Softer radius */
        padding: 2.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .quiz-rules-minimal h2 {
        color: #343a40;
        margin-bottom: 1.5rem;
        text-align: center;
        font-weight: 600;
    }
    .quiz-rules-minimal p {
        font-size: 1rem;
        line-height: 1.7;
        color: #495057;
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
    }
";

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
            if ($upcoming_quiz_enabled && $upcoming_quiz_date_str) {
                try {
                    $target_date = new DateTime($upcoming_quiz_date_str);
                    $current_date = new DateTime();
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
                        } else {
                            echo '<p class="text-primary fw-bold fs-4">আজকেই কুইজ!</p>';
                        }
                    }
                } catch (Exception $e) {
                    echo '<p class="text-warning">আপকামিং কুইজের তারিখ সঠিকভাবে সেট করা হয়নি।</p>';
                }
            } elseif ($upcoming_quiz_enabled) {
                 // Enabled but date string is missing
                 echo '<p class="fs-5">আপকামিং কুইজের তথ্য শীঘ্রই আপডেট করা হবে।</p>';
            } else {
                 // Section is disabled by admin or no upcoming quiz is set.
                 // কোনো কিছুই প্রিন্ট করা হবে না, ফলে কোনো ফাঁকা জায়গাও তৈরি হবে না।
                 // যদি এই সেকশন সম্পূর্ণ খালি থাকার কারণে "কুইজে অংশগ্রহণ করুন" বাটনটি
                 // বেশি উপরে উঠে যায়, তাহলে বাটনের mt-3 ক্লাসটি স্পেস ঠিক রাখবে।
                 // অথবা, upcoming-quiz-info ডিভটিতে একটি min-height দিতে পারেন যদি কন্টেন্ট না থাকলেও
                 // একটি নির্দিষ্ট উচ্চতা ধরে রাখতে চান, তবে বর্তমান ডিজাইনে সেটির প্রয়োজন নেই।
            }
            ?>
            </div>
            <a href="quizzes.php" class="btn btn-custom-primary btn-lg mt-3" type="button">কুইজে অংশগ্রহণ করুন</a>
        </div>
    </div>
</div>

<div class="container content-section">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="quiz-rules-minimal">
                <h2>কুইজের নিয়মাবলী</h2>
                <p>১. প্রতিটি কুইজে অংশগ্রহণের জন্য লগইন/রেজিস্ট্রেশন করা লাগেবে।</p>
                <p>২. প্রতিটি প্রশ্নের চারটি অপশন থাকবে, যার মধ্যে একটি সঠিক উত্তর নির্বাচন করতে হবে।</p>
                <p>৩. একবার উত্তর নির্বাচন করার পর তা পরিবর্তন করা যাবে না।</p>
                <p>৪. নির্দিষ্ট সময়ের মধ্যে কুইজ সম্পন্ন করতে হবে। সময় শেষ হলে স্বয়ংক্রিয়ভাবে সাবমিট হয়ে যাবে।</p>
                <p>৫. ফলাফলের ভিত্তিতে র‍্যাংকিং নির্ধারিত হবে এবং পুরস্কার প্রদান করা হবে।</p>
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
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(particle => {
            particle.update();
            particle.draw();
        });
        requestAnimationFrame(animateParticles);
    }

    // Only start if hero section is visible (basic check)
    if (heroSection.offsetHeight > 0) {
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