<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Ensure session is started
}
// If db_connect.php is not already included, include it.
if (!isset($conn)) {
    $db_connect_path = 'includes/db_connect.php';
    if (file_exists($db_connect_path)) {
        require_once $db_connect_path;
    } else if (file_exists('../' . $db_connect_path)) { // For files inside a subfolder like /admin
        require_once '../' . $db_connect_path;
    }
}

// Define base URL if not already defined by the calling page
if (!isset($base_url)) {
    $base_url = ''; // Assuming header.php is included from files in the root
}

// Construct the full current URL for OG tags
$current_page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Default SEO values (can be overridden by individual pages)
$default_page_title = 'দ্বীনিলাইফ কুইজ';
$default_page_description = 'দ্বীনিলাইফ কুইজে অংশগ্রহণ করে আপনার ইসলামিক জ্ঞান পরীক্ষা করুন এবং বৃদ্ধি করুন। ইসলামিক প্রশ্ন ও উত্তর, প্রতিযোগিতা এবং আরও অনেক কিছু।';
$default_page_keywords = 'ইসলামিক কুইজ, দ্বীনি কুইজ, বাংলা কুইজ, ইসলামিক জ্ঞান, deenilife quiz, dquiz, religious quiz, online quiz';
$default_og_image = $base_url . 'assets/images/ogq.jpg'; 

$page_title_to_display = isset($page_title) ? htmlspecialchars($page_title) . ' - ' . $default_page_title : $default_page_title;
$page_description_to_display = isset($page_description) ? htmlspecialchars($page_description) : $default_page_description;
$page_keywords_to_display = isset($page_keywords) ? htmlspecialchars($page_keywords) : $default_page_keywords;
$og_image_to_display = isset($page_og_image) ? htmlspecialchars($page_og_image) : $default_og_image;

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo $page_title_to_display; ?></title>

    <link rel="icon" href="<?php echo $base_url; ?>assets/images/icons/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo $base_url; ?>assets/images/icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url; ?>assets/images/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_url; ?>assets/images/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $base_url; ?>assets/images/icons/favicon-16x16.png">
    <link rel="manifest" href="<?php echo $base_url; ?>assets/images/icons/site.webmanifest"> 
    <meta name="theme-color" content="#ffffff"> 

    <meta name="description" content="<?php echo $page_description_to_display; ?>">
    <meta name="keywords" content="<?php echo $page_keywords_to_display; ?>">
    <meta name="author" content="দ্বীনিলাইফ টিম"> 
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo $current_page_url; ?>" />

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $current_page_url; ?>">
    <meta property="og:title" content="<?php echo $page_title_to_display; ?>">
    <meta property="og:description" content="<?php echo $page_description_to_display; ?>">
    <meta property="og:image" content="<?php echo $og_image_to_display; ?>">
    <meta property="og:image:width" content="1200"> 
    <meta property="og:image:height" content="630"> 
    <meta property="og:site_name" content="দ্বীনিলাইফ কুইজ">

    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo $current_page_url; ?>">
    <meta name="twitter:title" content="<?php echo $page_title_to_display; ?>">
    <meta name="twitter:description" content="<?php echo $page_description_to_display; ?>">
    <meta name="twitter:image" content="<?php echo $og_image_to_display; ?>">
    
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <link href="<?php echo $base_url; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;700&display=swap" rel="stylesheet">
    <link href="<?php echo $base_url; ?>assets/css/style.css" rel="stylesheet">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RMVK2X0HZJ"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-RMVK2X0HZJ');
</script>
     <?php
    // Page-specific styles injected here
    if (isset($page_specific_styles) && !empty($page_specific_styles)) {
        echo "<style>\n" . $page_specific_styles . "\n</style>\n";
    }
    ?>
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $base_url; ?>index.php">
                <img src="<?php echo $base_url; ?>assets/images/logo.png" alt="দ্বীনিলাইফ কুইজ লগো" width="30" height="30" class="d-inline-block align-top">
                দ্বীনিলাইফ কুইজ
            </a>

            <div class="d-flex align-items-center d-lg-none"> 
                <div class="nav-item me-2"> 
                    <button id="themeToggleBtnMobile" class="btn btn-sm btn-outline-secondary" type="button" aria-label="ডার্ক মোডে পরিবর্তন করুন">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars-fill" viewBox="0 0 16 16"><path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/><path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 9.312 6.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097zM13.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 12.312.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097z"/></svg>
                    </button>
                </div>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>index.php">হোম</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>quizzes.php">সকল কুইজ</a>
                    </li>
                    <li class="nav-item">
    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'categories.php' || basename($_SERVER['PHP_SELF']) == 'practice_quiz.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>categories.php">অনুশীলন</a>
</li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>study_materials.php">স্টাডি ম্যাটেরিয়ালস</a>
                    </li>
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>profile.php">প্রোফাইল</a>
                        </li>
                        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>admin/index.php">অ্যাডমিন ড্যাশবোর্ড</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>logout.php">লগআউট</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>login.php">লগইন</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>register.php">রেজিস্টার</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0 d-none d-lg-flex align-items-center"> 
                        <button id="themeToggleBtnDesktop" class="btn btn-sm btn-outline-secondary" type="button" aria-label="ডার্ক মোডে পরিবর্তন করুন">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars-fill" viewBox="0 0 16 16"><path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/><path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 9.312 6.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097zM13.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 12.312.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097z"/></svg>
                            <span class="d-none d-sm-inline">ডার্ক মোড</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container mt-4">