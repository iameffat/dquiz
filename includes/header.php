<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Ensure session is started
}
// If db_connect.php is not already included, include it.
// This is a safeguard, ideally, it's included in the main page script before the header.
if (!isset($conn)) {
    // Adjust the path based on where header.php is included from.
    // This path assumes header.php is in 'includes/' and the calling script is in the root.
    // For admin pages, the path would need to be '../includes/db_connect.php'
    $db_connect_path = 'includes/db_connect.php';
    if (file_exists($db_connect_path)) {
        require_once $db_connect_path;
    } else if (file_exists('../' . $db_connect_path)) { // For files inside a subfolder like /admin
        require_once '../' . $db_connect_path;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - দ্বীনিলাইফ কুইজ' : 'দ্বীনিলাইফ কুইজ'; ?></title>
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;700&display=swap" rel="stylesheet">
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
            <a class="navbar-brand" href="<?php echo isset($base_url) ? $base_url : ''; ?>index.php">
                <img src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/logo.png" alt="দ্বীনিলাইফ কুইজ লগো" width="30" height="30" class="d-inline-block align-top">
                দ্বীনিলাইফ কুইজ
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($base_url) ? $base_url : ''; ?>index.php">হোম</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($base_url) ? $base_url : ''; ?>quizzes.php">সকল কুইজ</a>
                    </li>
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isset($base_url) ? $base_url : ''; ?>profile.php">প্রোফাইল</a>
                        </li>
                        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isset($base_url) ? $base_url : ''; ?>admin/index.php">অ্যাডমিন ড্যাশবোর্ড</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isset($base_url) ? $base_url : ''; ?>logout.php">লগআউট</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isset($base_url) ? $base_url : ''; ?>login.php">লগইন</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isset($base_url) ? $base_url : ''; ?>register.php">রেজিস্টার</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container mt-4">