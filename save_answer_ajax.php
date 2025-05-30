<?php
require_once 'includes/db_connect.php'; // Session and DB connection
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    $selected_option_id = isset($_POST['selected_option_id']) ? intval($_POST['selected_option_id']) : 0;
    $user_id = $_SESSION['user_id'];

    if ($attempt_id > 0 && $question_id > 0 && $selected_option_id > 0) {
        // প্রথমে চেক করুন এই attempt_id এবং user_id ভ্যালিড কিনা
        $sql_check_attempt = "SELECT id FROM quiz_attempts WHERE id = ? AND user_id = ?";
        $stmt_check = $conn->prepare($sql_check_attempt);
        $stmt_check->bind_param("ii", $attempt_id, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 1) {
            // is_correct ভ্যালু সার্ভারে চেক করতে হবে
            $sql_option_check = "SELECT is_correct FROM options WHERE id = ? AND question_id = ?";
            $stmt_opt_check = $conn->prepare($sql_option_check);
            $stmt_opt_check->bind_param("ii", $selected_option_id, $question_id);
            $stmt_opt_check->execute();
            $result_opt_check = $stmt_opt_check->get_result();
            $is_correct_answer = 0;
            if($opt_data = $result_opt_check->fetch_assoc()){
                $is_correct_answer = $opt_data['is_correct'];
            }
            $stmt_opt_check->close();

            // user_answers টেবিলে আপডেট বা ইন্সার্ট
            $sql_upsert = "INSERT INTO user_answers (attempt_id, question_id, selected_option_id, is_correct) 
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE selected_option_id = VALUES(selected_option_id), is_correct = VALUES(is_correct)";

            $stmt_upsert = $conn->prepare($sql_upsert);
            if ($stmt_upsert) {
                $stmt_upsert->bind_param("iiii", $attempt_id, $question_id, $selected_option_id, $is_correct_answer);
                if ($stmt_upsert->execute()) {
                    http_response_code(200);
                    echo json_encode(['status' => 'success', 'message' => 'Answer saved.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to save answer. DB execute error.']);
                }
                $stmt_upsert->close();
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to save answer. DB prepare error.']);
            }
        } else {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Invalid attempt or user.']);
        }
        $stmt_check->close();
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters.']);
    }
} else {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'User not logged in or invalid request method.']);
}
$conn->close();
?>