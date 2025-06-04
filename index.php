<?php
// index.php
$page_title = "DeeneLife Quiz";
$base_url = ''; // Root directory
require_once 'includes/db_connect.php'; // ডাটাবেস কানেকশন ও সেশন শুরু
require_once 'includes/functions.php';   // কমন ফাংশন

// hasUserAttemptedQuiz ফাংশন (যদি functions.php তে না থাকে)
if (!function_exists('hasUserAttemptedQuiz')) {
    function hasUserAttemptedQuiz($conn, $user_id, $quiz_id) {
        if ($user_id === null || !$conn) {
            return [false, null];
        }
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
}


$user_id_for_check = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Fetch upcoming quiz settings for hero section
$settings = [];
$sql_settings = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('upcoming_quiz_enabled', 'upcoming_quiz_title', 'upcoming_quiz_end_date')";
$result_settings = $conn->query($sql_settings);
if ($result_settings && $result_settings->num_rows > 0) {
    while ($row = $result_settings->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$upcoming_quiz_enabled = isset($settings['upcoming_quiz_enabled']) ? (bool)$settings['upcoming_quiz_enabled'] : false;
$upcoming_quiz_title_hero = isset($settings['upcoming_quiz_title']) ? htmlspecialchars($settings['upcoming_quiz_title'], ENT_QUOTES, 'UTF-8') : "আপকামিং কুইজ";
$upcoming_quiz_date_str = isset($settings['upcoming_quiz_end_date']) ? $settings['upcoming_quiz_end_date'] : null;


// --- Fetch quizzes for "সাম্প্রতিক কুইজসমূহ" section ---
$recent_quizzes_for_display = [];
$max_recent_quizzes_on_home = 3;

// 1. Fetch Upcoming Quizzes for homepage
$upcoming_quizzes_home = [];
$sql_upcoming_home = "SELECT q.id, q.title, q.description, q.duration_minutes, q.status, q.live_start_datetime,
                    (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                    FROM quizzes q
                    WHERE q.status = 'upcoming'
                    ORDER BY q.live_start_datetime ASC, q.id DESC
                    LIMIT " . $max_recent_quizzes_on_home; 
$result_upcoming_home = $conn->query($sql_upcoming_home);
if ($result_upcoming_home && $result_upcoming_home->num_rows > 0) {
    while ($row = $result_upcoming_home->fetch_assoc()) {
        $upcoming_quizzes_home[] = $row;
    }
}

// 2. Fetch Recent Live Quizzes for homepage
$recent_live_quizzes_home = [];
$needed_live_or_archived = $max_recent_quizzes_on_home - count($upcoming_quizzes_home);

if ($needed_live_or_archived > 0) {
    $sql_recent_live_home = "SELECT q.id, q.title, q.description, q.duration_minutes, q.status, q.live_start_datetime, q.live_end_datetime,
                        (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                        FROM quizzes q
                        WHERE q.status = 'live'
                        AND (q.live_start_datetime IS NULL OR q.live_start_datetime <= NOW())
                        AND (q.live_end_datetime IS NULL OR q.live_end_datetime >= NOW())
                        ORDER BY q.created_at DESC, q.id DESC
                        LIMIT " . $needed_live_or_archived;
    $result_recent_live_home = $conn->query($sql_recent_live_home);
    if ($result_recent_live_home && $result_recent_live_home->num_rows > 0) {
        while ($row = $result_recent_live_home->fetch_assoc()) {
            $recent_live_quizzes_home[] = $row;
        }
    }
}

// Combine upcoming and live
$recent_quizzes_for_display = array_merge($upcoming_quizzes_home, $recent_live_quizzes_home);

// 3. Fetch Recent Archived Quizzes if not enough upcoming/live quizzes
$needed_archived_home = $max_recent_quizzes_on_home - count($recent_quizzes_for_display);
$recent_archived_quizzes_home = [];

if ($needed_archived_home > 0) {
    $sql_recent_archived_home = "SELECT q.id, q.title, q.description, q.duration_minutes, q.status, q.live_start_datetime, q.live_end_datetime,
                            (SELECT COUNT(qs.id) FROM questions qs WHERE qs.quiz_id = q.id) as question_count
                            FROM quizzes q
                            WHERE q.status = 'archived'
                            OR (q.status = 'live' AND q.live_end_datetime IS NOT NULL AND q.live_end_datetime < NOW())
                            ORDER BY q.created_at DESC, q.id DESC
                            LIMIT " . $needed_archived_home;
    $result_recent_archived_home = $conn->query($sql_recent_archived_home);
    if ($result_recent_archived_home && $result_recent_archived_home->num_rows > 0) {
        while ($row = $result_recent_archived_home->fetch_assoc()) {
            $is_already_listed = false;
            foreach ($recent_quizzes_for_display as $listed_quiz) {
                if ($listed_quiz['id'] == $row['id']) {
                    $is_already_listed = true;
                    break;
                }
            }
            if (!$is_already_listed) {
                $recent_archived_quizzes_home[] = $row;
            }
        }
    }
}
// Merge archived quizzes
$recent_quizzes_for_display = array_merge($recent_quizzes_for_display, $recent_archived_quizzes_home);

// Ensure unique quizzes and limit to max_recent_quizzes_on_home
$final_display_ids = [];
$temp_display_quizzes = [];
foreach($recent_quizzes_for_display as $quiz_item) {
    if (!in_array($quiz_item['id'], $final_display_ids) && count($final_display_ids) < $max_recent_quizzes_on_home) {
        $final_display_ids[] = $quiz_item['id'];
         // Add status_display for consistent handling in the template
        if ($quiz_item['status'] === 'live' && !empty($quiz_item['live_end_datetime']) && new DateTime($quiz_item['live_end_datetime']) < new DateTime()) {
            $quiz_item['status_display'] = 'archived';
        } else {
            $quiz_item['status_display'] = $quiz_item['status'];
        }
        $temp_display_quizzes[] = $quiz_item;
    }
}
$recent_quizzes_for_display = $temp_display_quizzes;


// --- Fetch Study Materials for Homepage ---
$study_materials_home = [];
$max_study_materials_on_home = 3; // আপনি কয়টি দেখাতে চান
// Ensure your table uses 'google_drive_link' as per your last request
$sql_study_materials_home = "SELECT id, title, description, google_drive_link 
                             FROM study_materials 
                             ORDER BY created_at DESC 
                             LIMIT " . $max_study_materials_on_home;
$result_study_materials_home = $conn->query($sql_study_materials_home);
if ($result_study_materials_home && $result_study_materials_home->num_rows > 0) {
    while ($row_sm = $result_study_materials_home->fetch_assoc()) {
        $study_materials_home[] = $row_sm;
    }
}


// Page-specific CSS for Minimal Hero Section, Animations, Snow, and new quiz card styles
$page_specific_styles = "
    body {
        /* overflow-x: hidden; Optional: if animations cause horizontal scroll */
    }
    .minimal-hero-section {
        background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
        padding: 6rem 1.5rem;
        text-align: center;
        color: #343a40; 
        position: relative; 
        overflow: hidden; 
        border-bottom: 1px solid #dee2e6;
    }
    #snow-canvas {
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
    .minimal-hero-section h1 {
        font-size: 3rem; 
        font-weight: 600; 
        margin-bottom: 1rem;
        animation: fadeInDown 1s ease-out;
    }
    .minimal-hero-section p.lead {
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        color: #495057; 
        max-width: 700px; 
        margin-left: auto;
        margin-right: auto;
        animation: fadeInUp 1s ease-out 0.3s;
        animation-fill-mode: backwards; 
    }
    .minimal-hero-section .upcoming-quiz-info h3 {
        font-size: 1.5rem;
        font-weight: 500;
        color: #007bff; 
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
        border-radius: 50px; 
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

    .content-section { 
        padding: 3rem 0;
        animation: fadeIn 1.5s ease-out;
    }
    .quiz-rules-minimal, .how-to-participate, .recent-quizzes-section, .recent-study-materials-section {
        background-color: #ffffff; 
        border: 1px solid #e9ecef; 
        border-radius: 12px; 
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
        padding-left: 25px; 
        margin-bottom: 0.75rem;
    }
    .how-to-participate ul li::before {
        content: '\\2713'; 
        color: #28a745; 
        font-weight: bold;
        position: absolute;
        left: 0;
        top: 2px; 
    }

    /* UPDATED Styles for .quiz-card-sm */
    .quiz-card-sm { 
        border: 1px solid #dee2e6;
        border-radius: 8px;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        display: flex; 
        flex-direction: column; 
        height: 100%; 
    }
    .quiz-card-sm:hover {
        transform: translateY(-4px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .quiz-card-sm .card-body {
        display: flex;
        flex-direction: column;
        flex-grow: 1; 
        padding: 1rem; 
    }
    .quiz-card-sm .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem; 
    }
    .quiz-card-sm .quiz-description-display {
        font-size: 0.85rem;
        color: #555; 
        line-height: 1.5; 
        margin-bottom: 0.75rem; 
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 3; /* Limit to 3 lines */
        -webkit-box-orient: vertical;
        min-height: calc(1.5em * 1); 
    }
    .quiz-card-sm .quiz-description-display.no-real-description {
        min-height: auto; 
    }
    .quiz-card-sm .quiz-description-display.no-real-description p {
        margin-bottom: 0 !important; 
    }
    .quiz-card-sm .quiz-description-display p { 
        margin-bottom: 0;
    }


    .quiz-card-sm ul.list-unstyled { 
        margin-top: auto; 
        font-size: 0.8rem;
        color: #6c757d;
        padding-top: 0.5rem; 
        margin-bottom: 0.75rem; 
    }
    .quiz-card-sm ul.list-unstyled li {
        margin-bottom: 0.25rem;
    }
    .quiz-card-sm .btn {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem; 
        /* align-self: flex-start; /* Let buttons align with flex-wrap */
    }
    .quiz-card-sm .additional-info-home {
        font-size: 0.75rem;
        margin-top: 0.5rem;
    }
    
    /* START: Added for button alignment on quiz cards */
    .quiz-card-sm .card-actions-home {
        display: flex;
        flex-wrap: wrap; /* Allow buttons to wrap if space is tight */
        gap: 0.5rem; /* Space between buttons */
        margin-top: auto; /* Push to bottom if description is short */
    }
    /* END: Added for button alignment on quiz cards */
    
    .upcoming-quiz-card-home { background-color: #e0f7fa; border-left: 4px solid #00bcd4; }
    .upcoming-quiz-card-home .card-title { color: #00796b; }
    .live-quiz-card-home { background-color: #e6ffed; border-left: 4px solid #28a745; }
    .live-quiz-card-home .card-title { color: #155724; }
    .archived-quiz-card-home { background-color: #f8f9fa; border-left: 4px solid #6c757d; }
    .archived-quiz-card-home .card-title { color: #343a40; }


    @keyframes fadeInDown { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    @media (max-width: 768px) {
        .minimal-hero-section { padding: 4rem 1rem; }
        .minimal-hero-section h1 { font-size: 2.2rem; }
        .minimal-hero-section p.lead { font-size: 1rem; }
        .minimal-hero-section .upcoming-quiz-info h3 { font-size: 1.3rem; }
        .minimal-hero-section .upcoming-quiz-info p { font-size: 1rem; }
        .section-title { font-size: 1.5rem; }
        .quiz-rules-minimal, .how-to-participate, .recent-quizzes-section, .recent-study-materials-section { padding: 1.5rem; }
    }
"; 

require_once 'includes/header.php'; 
?>

<div class="minimal-hero-section">
    <canvas id="snow-canvas"></canvas>
    <div class="hero-content">
        <div class="container">
            <h1>দ্বীনিলাইফ কুইজে আপনাকে স্বাগতম!</h1>
            <p class="lead">জ্ঞানার্জন ইবাদতের অংশ এবং প্রতিটি মুসলমানের জন্য ফরজ। তাই নিয়মিত দ্বীনিলাইফে আয়োজন হচ্ছে কুইজ প্রতিযোগিতা, যেখানে আপনি ইসলামের মৌলিক জ্ঞানকে যাচাই করতে পারবেন শিক্ষণীয় কুইজের মাধ্যমে।</p>
            
          <div class="upcoming-quiz-info">
            <?php
            if ($upcoming_quiz_enabled && $upcoming_quiz_title_hero) { 
                echo '<h3>' . $upcoming_quiz_title_hero . '</h3>'; 
                if ($upcoming_quiz_date_str) {
                    try {
                        $target_date = new DateTime($upcoming_quiz_date_str);
                        $current_date = new DateTime();
                        $target_date_for_diff = new DateTime($target_date->format('Y-m-d'));
                        $current_date_for_diff = new DateTime($current_date->format('Y-m-d'));

                        if ($current_date_for_diff > $target_date_for_diff) {
                            echo '<p>এই কুইজটি ইতিমধ্যে শেষ হয়ে গিয়েছে। পরবর্তী কুইজের জন্য অপেক্ষা করুন।</p>';
                        } else {
                            $interval = $current_date_for_diff->diff($target_date_for_diff);
                            $days_left = $interval->days;
                            if ($days_left > 0) {
                                echo '<p>আর মাত্র <span class="fw-bold fs-4">' . $days_left . '</span> দিন বাকি</p>';
                            } else { 
                                echo '<p class="text-primary fw-bold fs-4">আজকেই কুইজ!</p>';
                            }
                        }
                    } catch (Exception $e) {
                        echo '<p class="text-warning">আপকামিং কুইজের তারিখ সঠিকভাবে সেট করা হয়নি।</p>';
                    }
                } else {
                     echo '<p class="fs-5">শীঘ্রই আসছে... বিস্তারিত তথ্যের জন্য অপেক্ষা করুন।</p>';
                }
            } elseif ($upcoming_quiz_enabled) { 
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
            <?php
                $card_class_home = 'quiz-card-sm';
                $button_text_home = 'অংশগ্রহণের জন্য লগইন';
                $button_class_home = 'btn-outline-primary';
                $link_href_home = 'quiz_page.php?id=' . $quiz['id'];
                $additional_info_home = '';
                $is_disabled_button = false;
                $display_status_for_card = isset($quiz['status_display']) ? $quiz['status_display'] : $quiz['status'];
                $description_html = $quiz['description'] ? trim($quiz['description']) : '';
                $is_description_empty = empty(trim(strip_tags($description_html)));

                // Prepare quiz URL for sharing
                $quiz_page_url_home = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/quiz_page.php?id=' . $quiz['id'];


                if ($display_status_for_card === 'upcoming') {
                    $card_class_home .= ' upcoming-quiz-card-home';
                    $button_text_home = 'শীঘ্রই আসছে...';
                    $button_class_home = 'btn-info';
                    $is_disabled_button = true; 
                    if ($quiz['live_start_datetime']) {
                        $additional_info_home = '<p class="small text-muted mt-1 mb-0">সম্ভাব্য শুরু: ' . format_datetime($quiz['live_start_datetime']) . '</p>';
                    }
                } elseif ($display_status_for_card === 'live') {
                    $card_class_home .= ' live-quiz-card-home';
                    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && $user_id_for_check) {
                        list($attempted_this_live, $attempt_id_this_live) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                        if ($attempted_this_live) {
                            $button_text_home = 'ফলাফল দেখুন';
                            $link_href_home = 'results.php?attempt_id=' . $attempt_id_this_live . '&quiz_id=' . $quiz['id'];
                            $additional_info_home = '<p class="small text-primary mt-1 mb-0">আপনি অংশগ্রহণ করেছেন।</p>';
                        } else {
                             $button_text_home = 'অংশগ্রহণ করুন';
                        }
                    } else {
                         $link_href_home = 'login.php?redirect=' . urlencode('quiz_page.php?id=' . $quiz['id']); 
                    }
                } elseif ($display_status_for_card === 'archived') {
                    $card_class_home .= ' archived-quiz-card-home';
                    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && $user_id_for_check) {
                        list($attempted_this_archived, $attempt_id_this_archived) = hasUserAttemptedQuiz($conn, $user_id_for_check, $quiz['id']);
                        if ($attempted_this_archived) {
                            $button_text_home = 'ফলাফল দেখুন';
                            $link_href_home = 'results.php?attempt_id=' . $attempt_id_this_archived . '&quiz_id=' . $quiz['id'];
                            $additional_info_home = '<p class="small text-primary mt-1 mb-0">আপনি অংশগ্রহণ করেছেন।</p>';
                        } else {
                            $button_text_home = 'অনুশীলন করুন';
                        }
                    } else {
                        $button_text_home = 'অনুশীলনের জন্য লগইন';
                         $link_href_home = 'login.php?redirect=' . urlencode('quiz_page.php?id=' . $quiz['id']);
                    }
                }
            ?>
            <div class="col">
                <div class="card h-100 <?php echo $card_class_home; ?>">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                       <div class="quiz-description-display <?php echo $is_description_empty ? 'no-real-description' : ''; ?>">
                            <?php echo $is_description_empty ? '<p class="text-muted fst-italic" style="margin-bottom: 0;"><em>কোনো বিবরণ নেই।</em></p>' : $description_html; ?>
                        </div>
                        <ul class="list-unstyled small mt-auto"> 
                            <li><strong>সময়:</strong> <?php echo $quiz['duration_minutes']; ?> মিনিট</li>
                            <li><strong>প্রশ্ন:</strong> <?php echo $quiz['question_count']; ?> টি</li>
                        </ul>
                        <div class="card-actions-home mt-2"> {/* Wrapper for buttons */}
                            <?php if ($is_disabled_button): ?>
                                <button class="btn btn-sm <?php echo $button_class_home; ?>" disabled><?php echo $button_text_home; ?></button>
                            <?php else: ?>
                                <a href="<?php echo $link_href_home; ?>" class="btn btn-sm <?php echo $button_class_home; ?>">
                                    <?php echo $button_text_home; ?>
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary" 
                                    onclick="shareQuiz('<?php echo htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($quiz_page_url_home, ENT_QUOTES); ?>', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-share-fill" viewBox="0 0 16 16">
                                    <path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5"/>
                                </svg> শেয়ার
                            </button>
                        </div>
                        <?php if (!empty($additional_info_home)): ?>
                            <div class="additional-info-home"><?php echo $additional_info_home; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
          <div class="text-center mt-4">
            <a href="quizzes.php" class="btn btn-outline-secondary">সকল কুইজ দেখুন</a>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-light text-center">এখন কোনো সাম্প্রতিক কুইজ নেই।</div>
    <?php endif; ?>

    <?php if (!empty($study_materials_home)): ?>
    <div class="recent-study-materials-section content-section"> <h2 class="section-title">প্রয়োজনীয় স্টাডি ম্যাটেরিয়ালস</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($study_materials_home as $material): ?>
            <div class="col">
                <div class="card h-100 quiz-card-sm"> <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($material['title']); ?></h5>
                        <div class="quiz-description-display"> 
                            <?php 
                            // Since description can be HTML from Quill, we need to be careful.
                            // For a short preview, strip_tags and then truncate.
                            if (!empty($material['description'])) {
                                $desc_plain_sm = strip_tags($material['description']); // Remove HTML for plain text preview
                                echo htmlspecialchars(mb_substr($desc_plain_sm, 0, 100) . (mb_strlen($desc_plain_sm) > 100 ? '...' : ''));
                            } else {
                                echo '<p class="text-muted fst-italic" style="margin-bottom: 0;"><em>কোনো বিবরণ নেই।</em></p>';
                            }
                            ?>
                        </div>
                        <a href="<?php echo htmlspecialchars($material['google_drive_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-auto">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-link-45deg me-2" viewBox="0 0 16 16">
                              <path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/>
                              <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/>
                            </svg>
                            ডাউনলোড
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
         <div class="text-center mt-4">
            <a href="study_materials.php" class="btn btn-outline-secondary">সকল স্টাডি ম্যাটেরিয়ালস দেখুন</a>
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
    if (!canvas) return; 

    const heroSection = document.querySelector('.minimal-hero-section');
    const ctx = canvas.getContext('2d');
    let particles = [];
    const particleCount = 50; 

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
            this.x += Math.sin(this.y / (50 + Math.random()*50)) * 0.3;

            if (this.y > canvas.height) {
                this.y = 0 - this.size; 
                this.x = Math.random() * canvas.width; 
                this.speed = Math.random() * 0.5 + 0.2; 
                this.size = Math.random() * 2 + 1; 
            }
        };

        this.draw = function() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(200, 200, 200, ${this.opacity})`; 
            ctx.fill();
        };
    }

    function initParticles() {
        particles = []; 
        for (let i = 0; i < particleCount; i++) {
            const x = Math.random() * canvas.width;
            const y = Math.random() * canvas.height; 
            const size = Math.random() * 1.5 + 0.5; 
            const speed = Math.random() * 0.3 + 0.1; 
            const opacity = Math.random() * 0.5 + 0.3; 
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