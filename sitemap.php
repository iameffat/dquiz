<?php
// Database connection
require_once 'includes/db_connect.php';

// --- Configuration ---
// ! গুরুত্বপূর্ণ: এই লাইনটি পরিবর্তন করে আপনার ওয়েবসাইটের সঠিক ডোমেইন দিন।
$base_url = "https://quiz.deenelife.com"; 

// --- Do not edit below this line ---

// Set the content type to XML
header("Content-Type: application/xml; charset=utf-8");

// Function to create a URL entry in the sitemap
function create_url_entry($loc, $lastmod = null, $changefreq = 'monthly', $priority = '0.8') {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    if ($lastmod) {
        // Format date to W3C Datetime format
        $formatted_date = date(DATE_ATOM, strtotime($lastmod));
        echo "    <lastmod>" . $formatted_date . "</lastmod>\n";
    }
    echo "    <changefreq>" . $changefreq . "</changefreq>\n";
    echo "    <priority>" . $priority . "</priority>\n";
    echo "  </url>\n";
}

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Static Pages
create_url_entry($base_url . '/index.php', date('Y-m-d'), 'daily', '1.0');
create_url_entry($base_url . '/quizzes.php', date('Y-m-d'), 'daily', '0.9');
create_url_entry($base_url . '/categories.php', date('Y-m-d'), 'weekly', '0.9');
create_url_entry($base_url . '/study_materials.php', date('Y-m-d'), 'weekly', '0.8');

// 2. Archived Quizzes Pages
// কুইজগুলো যেগুলো আর্কাইভ করা হয়েছে বা লাইভ সময় শেষ হয়ে গেছে, সেগুলো ইন্ডেক্স করা হবে
$sql_archived_quizzes = "SELECT id, updated_at FROM quizzes WHERE status = 'archived' OR (status = 'live' AND live_end_datetime IS NOT NULL AND live_end_datetime < NOW())";
$result_archived_quizzes = $conn->query($sql_archived_quizzes);

if ($result_archived_quizzes && $result_archived_quizzes->num_rows > 0) {
    while ($quiz = $result_archived_quizzes->fetch_assoc()) {
        create_url_entry($base_url . '/quiz_page.php?id=' . $quiz['id'], $quiz['updated_at'], 'monthly', '0.7');
    }
}

// 3. Category & Paginated Question View Pages
$sql_categories = "SELECT id, name FROM categories";
$result_categories = $conn->query($sql_categories);

if ($result_categories && $result_categories->num_rows > 0) {
    // Re-using the logic from view_questions.php for counting
    $sql_count_template = "SELECT COUNT(DISTINCT q.id) as total
                           FROM questions q
                           INNER JOIN question_categories qc ON q.id = qc.question_id
                           LEFT JOIN quizzes qz ON q.quiz_id = qz.id
                           WHERE qc.category_id = ? AND (q.quiz_id IS NULL OR qz.status = 'archived')";
    $stmt_count = $conn->prepare($sql_count_template);

    while ($category = $result_categories->fetch_assoc()) {
        // Add the main category page
        create_url_entry($base_url . '/view_questions.php?category_id=' . $category['id'], date('Y-m-d'), 'weekly', '0.8');

        // Add paginated URLs
        $records_per_page = 30; // This value is from view_questions.php
        
        $stmt_count->bind_param("i", $category['id']);
        $stmt_count->execute();
        $count_result = $stmt_count->get_result()->fetch_assoc();
        $total_records = $count_result['total'] ?? 0;

        if ($total_records > 0) {
            $total_pages = ceil($total_records / $records_per_page);
            if ($total_pages > 1) {
                for ($i = 2; $i <= $total_pages; $i++) {
                    create_url_entry($base_url . '/view_questions.php?category_id=' . $category['id'] . '&page=' . $i, date('Y-m-d'), 'weekly', '0.7');
                }
            }
        }
    }
    $stmt_count->close();
}


// End XML output
echo '</urlset>';

// Close the database connection
if ($conn) {
    $conn->close();
}
?>