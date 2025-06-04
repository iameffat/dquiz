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
    
    <link rel="icon" type="image/png" href="<?php echo $current_admin_base_url; ?>../assets/images/icons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="<?php echo $current_admin_base_url; ?>../assets/images/icons/favicon.svg" />
    <link rel="shortcut icon" href="<?php echo $current_admin_base_url; ?>../assets/images/icons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $current_admin_base_url; ?>../assets/images/icons/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Quiz DeeneLife" />
    <link rel="manifest" href="<?php echo $current_admin_base_url; ?>../assets/images/icons/site.webmanifest" />

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />

    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RMVK2X0HZJ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
    
      gtag('config', 'G-RMVK2X0HZJ');
    </script>
    <style>
        /* Admin-specific styles updated to use CSS variables */
        body {
            display: flex;
            min-height: 100vh;
            background-color: var(--body-bg); /* Uses variable from style.css */
            color: var(--body-color); /* Uses variable from style.css */
        }
        .admin-sidebar {
            width: 280px;
            background-color: var(--admin-sidebar-bg);
            color: var(--admin-sidebar-text-color);
            position: fixed; 
            top: 0;
            left: 0;
            bottom: 0;
            padding-top: 15px;
            z-index: 1031; 
            overflow-y: auto;
            transition: width 0.3s ease, left 0.3s ease, background-color 0.3s ease, color 0.3s ease;
        }
        .admin-sidebar a { 
            color: var(--admin-sidebar-text-color); 
            text-decoration: none; display: block; padding: 10px 15px; 
            transition: color 0.3s ease, background-color 0.3s ease;
        }
        .admin-sidebar a:hover, .admin-sidebar a.active { 
            color: var(--admin-sidebar-hover-text-color); 
            background-color: var(--admin-sidebar-hover-bg); 
        }
        .admin-sidebar h4 {
            color: var(--admin-sidebar-hover-text-color); /* Ensure title is visible */
        }

        .admin-page-wrapper {
            margin-left: 280px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            width: calc(100% - 280px);
            transition: margin-left 0.3s ease, width 0.3s ease, background-color 0.3s ease, color 0.3s ease;
            background-color: var(--body-bg); 
            color: var(--body-color);
        }

        .admin-header {
            background-color: var(--admin-header-bg);
            padding: 10px 20px;
            border-bottom: 1px solid var(--admin-header-border);
            position: sticky; 
            top: 0;
            z-index: 1020; 
            color: var(--body-color); /* Ensure text is readable */
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }
        .admin-main-content {
            padding: 20px;
            flex-grow: 1;
        }
        .admin-footer {
            background-color: var(--admin-footer-bg);
            padding:10px 20px;
            text-align: center;
            border-top: 1px solid var(--admin-header-border);
            color: var(--text-muted-color);
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .admin-sidebar { width: 220px; }
            .admin-page-wrapper { margin-left: 220px; width: calc(100% - 220px); }
        }

        @media (max-width: 767.98px) {
            body { display: block; }
            .admin-sidebar { position: static; width: 100%; height: auto; min-height: 0; bottom: auto; display: flex; flex-direction: column; padding-bottom: 10px; }
            .admin-sidebar .nav { flex-direction: column; }
            .admin-sidebar h4 { text-align: center; }
            .admin-page-wrapper { margin-left: 0; width: 100%; }
            .admin-header { position: static; }
        }

        .admin-question-image-preview {
            max-width: 100%;
            height: auto;
            max-height: 200px;
            border: 1px solid var(--border-color);
            padding: 5px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: block;
            background-color: var(--body-bg);
        }
        .ql-container.ql-snow {
            min-height: 150px;
            background-color: var(--quill-container-bg) !important;
            border-color: var(--quill-container-border) !important;
        }
        .ql-editor{
            color: var(--quill-container-text) !important;
        }
        .ql-editor.ql-blank::before{
            color: rgba(var(--body-color-rgb), 0.6) !important;
        }
        .ql-toolbar.ql-snow {
            background-color: var(--quill-toolbar-bg) !important;
            border-color: var(--quill-toolbar-border) !important;
        }
        .ql-toolbar.ql-snow .ql-stroke { stroke: var(--quill-toolbar-icon) !important; }
        .ql-toolbar.ql-snow .ql-fill { fill: var(--quill-toolbar-icon) !important; }
        .ql-toolbar.ql-snow .ql-picker-label { color: var(--quill-toolbar-icon) !important; }
        
        .ql-toolbar.ql-snow button:hover,
        .ql-toolbar.ql-snow .ql-picker-label:hover,
        .ql-toolbar.ql-snow .ql-picker-item:hover {
            background-color: var(--tertiary-bg-color) !important;
        }
        .ql-toolbar.ql-snow .ql-active .ql-stroke,
        .ql-toolbar.ql-snow button:hover .ql-stroke,
        .ql-toolbar.ql-snow .ql-picker-label:hover .ql-stroke,
        .ql-toolbar.ql-snow .ql-picker-item:hover .ql-stroke {
            stroke: var(--link-hover-color) !important;
        }
         .ql-toolbar.ql-snow .ql-active .ql-fill,
        .ql-toolbar.ql-snow button:hover .ql-fill,
        .ql-toolbar.ql-snow .ql-picker-label:hover .ql-fill,
        .ql-toolbar.ql-snow .ql-picker-item:hover .ql-fill {
             fill: var(--link-hover-color) !important;
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
    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_categories.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_categories.php">
        ক্যাটাগরি ম্যানেজমেন্ট
    </a>
</li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_study_materials.php' || basename($_SERVER['PHP_SELF']) == 'add_study_material.php' || basename($_SERVER['PHP_SELF']) == 'edit_study_material.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_study_materials.php">
                স্টাডি ম্যাটেরিয়ালস
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php' || basename($_SERVER['PHP_SELF']) == 'edit_user.php' || basename($_SERVER['PHP_SELF']) == 'send_email.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_users.php">
                ইউজার ম্যানেজমেন্ট
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>settings.php">
                সাইট সেটিংস
            </a>
        </li>
        <li class="nav-item mt-auto">
            <a class="nav-link" href="<?php echo $current_admin_base_url; ?>../index.php" target="_blank">সাইট দেখুন</a>
        </li>
    </ul>
</div>

<div class="admin-page-wrapper">

    <div class="admin-header d-flex justify-content-between align-items-center">
        <div>
            <h5><?php echo isset($page_title) ? $page_title : 'ড্যাশবোর্ড'; ?></h5>
        </div>
        <div class="d-flex align-items-center">
            <span>স্বাগতম, <?php echo htmlspecialchars($_SESSION["name"]); ?>!</span>
            <button id="themeToggleBtnDesktop" class="btn btn-sm btn-outline-secondary ms-2" type="button" aria-label="ডার্ক মোডে পরিবর্তন করুন">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars-fill" viewBox="0 0 16 16"><path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/><path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 9.312 6.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097zM13.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 12.312.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097z"/></svg>
                <span class="d-none d-sm-inline">ডার্ক মোড</span>
            </button>
            <a href="<?php echo $current_admin_base_url; ?>logout.php" class="btn btn-danger btn-sm ms-2">লগআউট</a>
        </div>
    </div>

    <main class="admin-main-content">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>