<?php
// This file assumes auth_check.php has already been included by the calling page.
// $admin_base_url should be set by the calling page (e.g., '' if in admin root, '../' if in admin/something/)
$current_admin_base_url = isset($admin_base_url) ? $admin_base_url : '';

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
        /* CSS for responsive image preview in admin edit quiz page */
        .admin-question-image-preview {
            max-width: 100%; /* Ensures the image is not wider than its container */
            height: auto; /* Maintains aspect ratio */
            max-height: 200px; /* Optional: Limits the maximum display height */
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 4px;
            margin-bottom: 10px; /* Adds some space below the image */
            display: block; /* Can help with layout consistency */
        }
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