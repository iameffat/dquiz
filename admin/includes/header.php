<?php
// This file assumes auth_check.php has already been included by the calling page.
// $admin_base_url should be set by the calling page (e.g., '' if in admin root, '../' if in admin/something/)
$current_admin_base_url = isset($admin_base_url) ? $admin_base_url : '';

$current_page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Default SEO values (can be overridden by individual pages)
$default_page_title = 'দ্বীনিলাইফ কুইজ';
$default_page_description = 'দ্বীনিলাইফ কুইজে অংশগ্রহণ করে আপনার ইসলামিক জ্ঞান পরীক্ষা করুন এবং বৃদ্ধি করুন। ইসলামিক প্রশ্ন ও উত্তর, প্রতিযোগিতা এবং আরও অনেক কিছু।';
$default_page_keywords = 'ইসলামিক কুইজ, দ্বীনি কুইজ, বাংলা কুইজ, ইসলামিক জ্ঞান, deenilife quiz, dquiz, religious quiz, online quiz';
// UPDATED: Default Open Graph image path
$default_og_image = $base_url . 'assets/images/ogq.jpg'; 

$page_title_to_display = isset($page_title) ? htmlspecialchars($page_title) . ' - ' . $default_page_title : $default_page_title;
$page_description_to_display = isset($page_description) ? htmlspecialchars($page_description) : $default_page_description;
$page_keywords_to_display = isset($page_keywords) ? htmlspecialchars($page_keywords) : $default_page_keywords;
// Allow individual pages to override the OG image, otherwise use the new default
$og_image_to_display = isset($page_og_image) ? htmlspecialchars($page_og_image) : $default_og_image;

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - এডমিন প্যানেল' : 'এডমিন প্যানেল - দ্বীনিলাইফ কুইজ'; ?></title>
    <link href="<?php echo $current_admin_base_url; ?>../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;700&display=swap" rel="stylesheet">
    <link href="<?php echo $current_admin_base_url; ?>../assets/css/style.css" rel="stylesheet"> 
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/icons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/icons/favicon.svg" />
    <link rel="shortcut icon" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/icons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/icons/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Quiz DeeneLife" />
    <link rel="manifest" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/icons/site.webmanifest" />
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RMVK2X0HZJ"></script>

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

<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-RMVK2X0HZJ');
</script>
    <style>
        body { display: flex; min-height: 100vh; flex-direction: column; }
        .admin-sidebar { width: 280px; background-color: #343a40; color: #fff; min-height: 100vh; position:fixed; top:0; left:0; bottom:0; padding-top:15px; }
        .admin-sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 10px 15px; }
        .admin-sidebar a:hover, .admin-sidebar a.active { color: #fff; background-color: #495057; }
        .admin-main-content { margin-left: 280px; padding: 20px; flex-grow: 1; }
        .admin-header { background-color: #fff; padding: 10px 20px; border-bottom: 1px solid #dee2e6; margin-left:280px; }
        .admin-footer { background-color: #f8f9fa; padding:10px 20px; text-align: center; border-top: 1px solid #dee2e6; margin-left:280px; }
    </style>
</head>
<body>

<div class="admin-sidebar">
    <h4 class="text-center p-2">এডমিন প্যানেল</h4>
    <hr style="border-color: #495057;">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>index.php">
                ড্যাশবোর্ড
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_quizzes.php' || basename($_SERVER['PHP_SELF']) == 'add_quiz.php' || basename($_SERVER['PHP_SELF']) == 'edit_quiz.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_quizzes.php">
                কুইজ ম্যানেজমেন্ট
            </a>
        </li>
         <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>settings.php">
                হোমপেজ সেটিংস
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_users.php">
                ইউজার ম্যানেজমেন্ট
            </a>
        </li>
        <li class="nav-item mt-auto">
            <a class="nav-link" href="<?php echo $current_admin_base_url; ?>../index.php" target="_blank">সাইট দেখুন</a>
        </li>
    </ul>
</div>

<div class="admin-header d-flex justify-content-between align-items-center">
    <div>
        <h5><?php echo isset($page_title) ? $page_title : 'ড্যাশবোর্ড'; ?></h5>
    </div>
    <div>
        <span>স্বাগতম, <?php echo htmlspecialchars($_SESSION["name"]); ?>!</span>
        <a href="<?php echo $current_admin_base_url; ?>logout.php" class="btn btn-danger btn-sm ms-2">লগআউট</a>
    </div>
</div>

<main class="admin-main-content">