<?php
// admin/ajax_suggestions.php
require_once '../includes/db_connect.php'; // Adjust path if your file structure is different

header('Content-Type: application/json'); // Set header to return JSON

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : ''; // 'question' or 'option'

$suggestions = [];

// Only proceed if the query is at least 2 characters long and type is valid
if (mb_strlen($query) < 2 || !in_array($type, ['question', 'option'])) {
    echo json_encode($suggestions); // Return empty array
    if ($conn) $conn->close();
    exit;
}

$search_query_like = "%" . $query . "%"; // For LIKE search
$search_query_starts = $query . "%";    // For prioritizing results that start with the query

if ($type === 'question') {
    // SQL to fetch distinct question texts, prioritizing those that start with the query,
    // then those containing the query, and limiting results.
    $sql = "SELECT DISTINCT question_text 
            FROM questions 
            WHERE question_text LIKE ? 
            ORDER BY 
                CASE 
                    WHEN question_text LIKE ? THEN 1  -- Starts with query
                    WHEN question_text LIKE ? THEN 2  -- Query in the middle/end
                    ELSE 3
                END, 
                LENGTH(question_text) ASC 
            LIMIT 7"; // Limit the number of suggestions
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Bind parameters: all three are related to the search query
        $stmt->bind_param("sss", $search_query_like, $search_query_starts, $search_query_like);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['question_text'];
            }
        } else {
            // Optional: Log error for debugging
            // error_log("Error executing question suggestion query: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Optional: Log error for debugging
        // error_log("Error preparing question suggestion query: " . $conn->error);
    }
} elseif ($type === 'option') {
    // Similar SQL for options
    $sql = "SELECT DISTINCT option_text 
            FROM options 
            WHERE option_text LIKE ?
            ORDER BY 
                CASE 
                    WHEN option_text LIKE ? THEN 1
                    WHEN option_text LIKE ? THEN 2
                    ELSE 3
                END, 
                LENGTH(option_text) ASC 
            LIMIT 5"; // Limit for options
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sss", $search_query_like, $search_query_starts, $search_query_like);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['option_text'];
            }
        } else {
            // error_log("Error executing option suggestion query: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // error_log("Error preparing option suggestion query: " . $conn->error);
    }
}

if ($conn) {
    $conn->close();
}
echo json_encode($suggestions); // Output suggestions as JSON
?>