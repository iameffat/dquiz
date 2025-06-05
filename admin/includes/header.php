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
        .admin-sidebar .nav-link .bi { /* Icon style */
            margin-right: 8px;
            width: 1.1em; /* Consistent icon width */
            height: 1.1em; /* Consistent icon height */
            vertical-align: text-bottom; /* Align icon better with text */
        }
        .admin-sidebar a { 
            color: var(--admin-sidebar-text-color); 
            text-decoration: none; display: block; padding: 10px 15px; 
            transition: color 0.3s ease, background-color 0.3s ease;
            display: flex; /* For aligning icon and text */
            align-items: center; /* For aligning icon and text */
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
            .admin-sidebar { position: static; width: 100%; height: auto; min-height: 0; bottom: auto; /*display: flex;*/ flex-direction: column; padding-bottom: 10px; }
            .admin-sidebar .nav { flex-direction: column; } /* Ensure nav items stack */
            .admin-sidebar h4 { text-align: center; }
            .admin-page-wrapper { margin-left: 0; width: 100%; }
            .admin-header { position: static; }
             /* For mobile, ensure the toggler button is visible if you add one */
            .admin-sidebar-toggler { display: block; /* or flex */ text-align: center; padding: 10px; background-color: #333; color: white; cursor: pointer; }
            /* Hide nav by default on mobile if using a toggler */
            /* .admin-sidebar .nav { display: none; } */
            /* .admin-sidebar.open .nav { display: flex; } */
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
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-door-fill" viewBox="0 0 16 16"><path d="M6.5 14.5v-3.505c0-.245.25-.495.5-.495h2c.25 0 .5.25.5.5v3.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5z"/></svg>
                ড্যাশবোর্ড
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_quizzes.php' || basename($_SERVER['PHP_SELF']) == 'add_quiz.php' || basename($_SERVER['PHP_SELF']) == 'edit_quiz.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_quizzes.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list-check" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M3.854 2.146a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708L2 3.293l1.146-1.147a.5.5 0 0 1 .708 0M2.5 7a.5.5 0 0 0 0 1h.5a.5.5 0 0 0 0-1zM3.854 6.146a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708L2 7.293l1.146-1.147a.5.5 0 0 1 .708 0m0 4a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708L2 11.293l1.146-1.147a.5.5 0 0 1 .708 0"/></svg>
                কুইজ ম্যানেজমেন্ট
            </a>
        </li>
         <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_manual_questions.php' || basename($_SERVER['PHP_SELF']) == 'add_manual_question.php' || basename($_SERVER['PHP_SELF']) == 'edit_manual_question.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_manual_questions.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-patch-question-fill" viewBox="0 0 16 16"><path d="M5.933.87a2.89 2.89 0 0 1 4.134 0l.622.638.89-.011a2.89 2.89 0 0 1 2.924 2.924l-.01.89.638.622a2.89 2.89 0 0 1 0 4.134l-.638.622.011.89a2.89 2.89 0 0 1-2.924 2.924l-.89-.01-.622.638a2.89 2.89 0 0 1-4.134 0l-.622-.638-.89.01a2.89 2.89 0 0 1-2.924-2.924l.01-.89-.638-.622a2.89 2.89 0 0 1 0-4.134l.638-.622-.011-.89a2.89 2.89 0 0 1 2.924-2.924l.89.01zM7.002 11a1 1 0 1 0 2 0 1 1 0 0 0-2 0m1.602-2.027c.04-.534.198-.815.846-1.26.674-.475 1.05-1.09 1.05-1.971 0-.923-.756-1.539-1.691-1.539S6.31 4.23 6.31 5.153h1.021c0-.59.441-1.002 1.123-1.002.65 0 1.002.322 1.002.88 0 .54-.37.91-.984 1.32-.652.433-1.03.938-1.03 1.705z"/></svg>
                ম্যানুয়াল প্রশ্ন
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_categories.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_categories.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tags-fill" viewBox="0 0 16 16"><path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 6.586zm4.5-1a.5.5 0 1 0 0 1 .5.5 0 0 0 0-1"/><path d="M1.293 7.793A1 1 0 0 1 1 7.086V2a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l.043-.043z"/></svg>
                ক্যাটাগরি ম্যানেজমেন্ট
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_study_materials.php' || basename($_SERVER['PHP_SELF']) == 'add_study_material.php' || basename($_SERVER['PHP_SELF']) == 'edit_study_material.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_study_materials.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-book-half" viewBox="0 0 16 16"><path d="M8.5 2.687c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783"/></svg>
                স্টাডি ম্যাটেরিয়ালস
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php' || basename($_SERVER['PHP_SELF']) == 'edit_user.php' || basename($_SERVER['PHP_SELF']) == 'send_email.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>manage_users.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/></svg>
                ইউজার ম্যানেজমেন্ট
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>" href="<?php echo $current_admin_base_url; ?>settings.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gear-fill" viewBox="0 0 16 16"><path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413-1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/></svg>
                সাইট সেটিংস
            </a>
        </li>
        <li class="nav-item mt-auto"> <a class="nav-link" href="<?php echo $current_admin_base_url; ?>../index.php" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>
                সাইট দেখুন
            </a>
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