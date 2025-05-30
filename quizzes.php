<?php
$page_title = "সকল কুইজ";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 
// header.php এখানে include করা হবে না, কারণ আমরা CSS সরাসরি এই ফাইলে দিচ্ছি
// require_once 'includes/header.php'; 

$user_id_for_check = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

/**
 * Checks if a user has any attempt (completed or not) for a given quiz.
 *
 * @param mysqli $conn Database connection object.
 * @param int|null $user_id The ID of the user.
 * @param int $quiz_id The ID of the quiz.
 * @return array [bool $has_attempted, int|null $attempt_id]
 */
function hasUserAttemptedQuiz($conn, $user_id, $quiz_id) {
    if ($user_id === null || !$conn) {
        return [false, null];
    }
    // Checks for any attempt, regardless of completion status (score IS NOT NULL is removed)
    $sql_check = "SELECT id FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        error_log("Prepare failed for hasUserAttemptedQuiz: (" . $conn->errno . ") " . $conn->error);
        return [false, null]; 
    }
    $stmt_check->bind_param("ii", $user_id, $quiz_id);
    if (!$stmt_check->execute()) {
        error_log("Execute failed for hasUserAttemptedQuiz: (" . $stmt_check->errno . ") " . $stmt_check->error);
        $stmt_check->close();
        return [false, null]; 
    }
    $result_check = $stmt_check->get_result();
    $attempt_info = $result_check->fetch_assoc();
    $stmt_check->close();
    return [$result_check->num_rows > 0, $attempt_info ? $attempt_info['id'] : null];
}


// Fetch Live Quizzes
$live_quizzes = [];
$sql_live = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime,
             (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
             FROM quizzes q 
             WHERE q.status = 'live' 
             AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW()) 
             AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())
             ORDER BY q.live_start_datetime DESC, q.id DESC";
$result_live = $conn->query($sql_live);
if ($result_live && $result_live->num_rows > 0) {
    while ($row = $result_live->fetch_assoc()) {
        $live_quizzes[] = $row;
    }
}


// Fetch Archived Quizzes
$archived_quizzes = [];
$sql_archived = "SELECT q.id, q.title, q.description, q.duration_minutes, q.live_start_datetime, q.live_end_datetime,
                (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                FROM quizzes q 
                WHERE q.status = 'archived'
                OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW())
                ORDER BY q.created_at DESC, q.id DESC";
$result_archived = $conn->query($sql_archived);
if ($result_archived && $result_archived->num_rows > 0) {
    while ($row = $result_archived->fetch_assoc()) {
        // Ensure it's not already listed in live_quizzes if it just expired
        $is_already_live = false;
        foreach ($live_quizzes as $live_quiz) {
            if ($live_quiz['id'] == $row['id']) {
                $is_already_live = true;
                break;
            }
        }
        if (!$is_already_live) {
            $archived_quizzes[] = $row;
        }
    }
}

// --- START OF HEADER.PHP CONTENT (MODIFIED) ---
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Ensure session is started
}

// Default SEO values (can be overridden by individual pages)
$default_page_title = 'দ্বীনিলাইফ কুইজ';
$page_title_to_display = isset($page_title) ? htmlspecialchars($page_title) . ' - ' . $default_page_title : $default_page_title;

// Construct the full current URL for OG tags
$current_page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$default_page_description = 'দ্বীনিলাইফ কুইজে অংশগ্রহণ করে আপনার ইসলামিক জ্ঞান পরীক্ষা করুন এবং বৃদ্ধি করুন। ইসলামিক প্রশ্ন ও উত্তর, প্রতিযোগিতা এবং আরও অনেক কিছু।';
$default_page_keywords = 'ইসলামিক কুইজ, দ্বীনি কুইজ, বাংলা কুইজ, ইসলামিক জ্ঞান, deenilife quiz, dquiz, religious quiz, online quiz';
$default_og_image = $base_url . 'assets/images/ogq.jpg'; 

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
    <link rel="manifest" href="<?php echo $base_url; ?>site.webmanifest"> 
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

    <link href="<?php echo $base_url; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;700&display=swap" rel="stylesheet">
    
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RMVK2X0HZJ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-RMVK2X0HZJ');
    </script>

    <style>
        /* General Styles from style.css */
        @font-face {
          font-family: 'SolaimanLipi';
          src: url('https://cdn.jsdelivr.net/gh/iameffat/font@master/solaimanv2.woff2') format('truetype');
          font-weight: normal;
          font-style: normal;
          font-display: swap;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif; /* বাংলা ফন্টের জন্য */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f7f6; /* একটি খুব হালকা ধূসর ব্যাকগ্রাউন্ড পুরো পেইজের জন্য */
            color: #333;
            line-height: 1.6;
        }

        main {
            flex: 1;
        }
        .container {
            max-width: 1140px; 
        }

        .footer {
            background-color: #f8f9fa;
            padding: 20px 0;
            text-align: center;
            margin-top: auto;
        }

        .auth-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        /* Styles for quizzes.php */
        .page-main-title {
            color: #2c3e50; 
            font-weight: 700; 
            padding-bottom: 0.5rem;
            margin-bottom: 2.5rem !important; 
            text-align: center;
            position: relative;
        }

        .page-main-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background-color: #007bff; 
            margin: 0.5rem auto 0; 
            border-radius: 2px;
        }

        .section-title {
            font-weight: 600;
            margin-bottom: 1.5rem !important;
            padding-bottom: 0.5rem !important;
            text-align: left; 
        }

        #live-quizzes .section-title {
            color: #28a745 !important; 
            border-bottom-color: #a1e8b5 !important; 
        }

        #archived-quizzes .section-title {
            color: #6c757d !important; 
            border-bottom-color: #ced4da !important; 
        }

        .quiz-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0; 
            border-radius: 8px; 
            transition: transform 0.25s ease-in-out, box-shadow 0.25s ease-in-out;
            overflow: hidden; 
        }

        .quiz-card:hover {
            transform: translateY(-8px); 
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12), 0 6px 10px rgba(0,0,0,0.08) !important; 
        }

        .quiz-card .card-body {
            padding: 1.5rem; 
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .quiz-card .card-title {
            font-size: 1.25rem; 
            font-weight: 600;   
            margin-bottom: 0.75rem;
            color: #343a40;
        }

        .quiz-card .card-text {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.5;
            flex-grow: 1;
            margin-bottom: 1rem;
        }

        .quiz-card ul {
            font-size: 0.875rem;
            color: #495057;
            margin-bottom: 1.25rem;
            list-style-type: none; 
            padding-left: 0;
        }
        .quiz-card ul li {
            padding-bottom: 0.3rem; 
        }
        .quiz-card ul li strong {
            color: #212529;
            margin-right: 5px; 
        }
        .quiz-card ul li small {
            font-size: 0.8em;
            color: #6c757d;
        }
        .quiz-card .btn {
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }

        .live-quiz-card {
            background-color: #f0fff0; 
            border: 1px solid #b2dfdb; 
        }
        .live-quiz-card .card-title {
            color: #00796b; 
        }
        .live-quiz-card .btn-success {
            background-color: #20c997; 
            border-color: #20c997;
            color: #fff;
        }
        .live-quiz-card .btn-success:hover {
            background-color: #1ba88a;
            border-color: #1a947c;
        }
        .live-quiz-card .btn-outline-info {
            color: #17a2b8;
            border-color: #17a2b8;
        }
        .live-quiz-card .btn-outline-info:hover {
            background-color: #17a2b8;
            color: #fff;
        }
        .live-quiz-card .btn-outline-success { /* Login to participate button */
            color: #28a745;
            border-color: #28a745;
        }
        .live-quiz-card .btn-outline-success:hover {
            background-color: #28a745;
            color: #fff;
        }


        .archived-quiz-card .card-title {
            color: #495057;
        }
        .archived-quiz-card .btn-secondary,
        .archived-quiz-card .btn-outline-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .archived-quiz-card .btn-secondary:hover,
        .archived-quiz-card .btn-outline-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .archived-quiz-card .btn-outline-info { 
            color: #17a2b8;
            border-color: #17a2b8;
        }
        .archived-quiz-card .btn-outline-info:hover {
            background-color: #17a2b8;
            color: #fff;
        }


        .alert-custom-light {
            background-color: #f8f9fa;
            border: 1px dashed #ced4da; 
            color: #6c757d;
            padding: 1.5rem;
            border-radius: 6px;
        }
         /* Flash message styling */
        .alert-dismissible .btn-close {
            padding: 0.75rem 1rem;
        }

    </style>
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
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
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="<?php echo isset($base_url) ? $base_url : ''; ?>index.php">হোম</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'quizzes.php') ? 'active' : ''; ?>" href="<?php echo isset($base_url) ? $base_url : ''; ?>quizzes.php">সকল কুইজ</a>
                    </li>
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>" href="<?php echo isset($base_url) ? $base_url : ''; ?>profile.php">প্রোফাইল</a>
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
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'active' : ''; ?>" href="<?php echo isset($base_url) ? $base_url : ''; ?>login.php">লগইন</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'register.php') ? 'active' : ''; ?>" href="<?php echo isset($base_url) ? $base_url : ''; ?>register.php">রেজিস্টার</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main>
    <div class="container mt-4">
        <?php display_flash_message('flash_message', 'flash_message_type'); ?>
        <h1 class="mb-4 text-center page-main-title">কুইজসমূহ</h1>

        <section id="live-quizzes" class="mb-5">
            <h2 class="mb-3 section-title text-success border-bottom pb-2">লাইভ কুইজ</h2>
            <?php if (!empty($live_quizzes)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($live_quizzes as $quiz): ?>
                    <?php 
                        list($attempted_live, $attempt_id_live) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                    ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm quiz-card live-quiz-card">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo escape_html($quiz['title']); ?></h5>
                                <p class="card-text">
                                    <?php echo escape_html(substr($quiz['description'] ?? '', 0, 100)) . (strlen($quiz['description'] ?? '') > 100 ? '...' : ''); ?>
                                </p>
                                <ul class="mt-auto pt-2">
                                    <li><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                    <li><strong>প্রশ্ন সংখ্যা:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                    <?php if ($quiz['live_start_datetime']): ?>
                                        <li><small>শুরু: <?php echo format_datetime($quiz['live_start_datetime']); ?></small></li>
                                    <?php endif; ?>
                                    <?php if ($quiz['live_end_datetime']): ?>
                                        <li><small>শেষ: <?php echo format_datetime($quiz['live_end_datetime']); ?></small></li>
                                    <?php endif; ?>
                                </ul>
                                <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                                    <?php if ($attempted_live): ?>
                                        <a href="results.php?attempt_id=<?php echo $attempt_id_live; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-outline-info mt-2">ফলাফল দেখুন</a>
                                        <p class="small text-primary mt-1 mb-0">আপনি এই কুইজে অংশগ্রহণ করেছেন।</p>
                                    <?php else: ?>
                                        <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-success mt-2">অংশগ্রহণ করুন</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz['id']); ?>" class="btn btn-outline-success mt-2">অংশগ্রহণের জন্য লগইন করুন</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center alert alert-custom-light">বর্তমানে কোনো লাইভ কুইজ নেই। অনুগ্রহ করে পরে আবার দেখুন।</p>
            <?php endif; ?>
        </section>

        <section id="archived-quizzes">
            <h2 class="mb-3 section-title text-secondary border-bottom pb-2">পূর্ববর্তী কুইজ (আর্কাইভ)</h2>
            <?php if (!empty($archived_quizzes)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($archived_quizzes as $quiz): ?>
                    <?php 
                        list($attempted_archived, $attempt_id_archived) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                    ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm quiz-card archived-quiz-card"> {/* Optionally add archived-quiz-card for specific styling */}
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo escape_html($quiz['title']); ?></h5>
                                <p class="card-text">
                                    <?php echo escape_html(substr($quiz['description'] ?? '', 0, 100)) . (strlen($quiz['description'] ?? '') > 100 ? '...' : ''); ?>
                                </p>
                                 <ul class="mt-auto pt-2">
                                    <li><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                                    <li><strong>প্রশ্ন সংখ্যা:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                                </ul>
                                <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                                    <?php if ($attempted_archived): ?>
                                        <a href="results.php?attempt_id=<?php echo $attempt_id_archived; ?>&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-outline-info mt-2">ফলাফল দেখুন</a>
                                         <p class="small text-primary mt-1 mb-0">আপনি এই কুইজে অংশগ্রহণ করেছেন।</p>
                                    <?php else: ?>
                                        <a href="quiz_page.php?id=<?php echo $quiz['id']; ?>" class="btn btn-secondary mt-2">অংশগ্রহণ করুন (অনুশীলন)</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                     <a href="login.php?redirect=<?php echo urlencode('quiz_page.php?id=' . $quiz['id']); ?>" class="btn btn-outline-secondary mt-2">অংশগ্রহণের জন্য লগইন করুন</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center alert alert-custom-light">এখনও কোনো কুইজ আর্কাইভ করা হয়নি।</p>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php
// --- START OF FOOTER.PHP CONTENT ---
if ($conn) {
    $conn->close();
}
?>
<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">&copy; <?php echo date("Y"); ?> DeeneLife Quiz. כל הזכויות שמורות.</span>
    </div>
</footer>

<script src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php // --- END OF FOOTER.PHP CONTENT --- ?>