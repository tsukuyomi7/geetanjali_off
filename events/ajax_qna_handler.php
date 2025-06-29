<?php
// geetanjali_website/events/ajax_qna_handler.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkUserLoggedIn()) {
        $response['message'] = 'You must be logged in to ask a question.';
    } elseif (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $response['message'] = 'Invalid security token.';
    } elseif (empty($_POST['event_id']) || empty(trim($_POST['question_text']))) {
        $response['message'] = 'Please provide an event ID and a question.';
    } else {
        $event_id = (int)$_POST['event_id'];
        $user_id = (int)$_SESSION['user_id'];
        $question_text = trim($_POST['question_text']);

        $stmt = $conn->prepare("INSERT INTO event_qna (event_id, user_id, question_text, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $event_id, $user_id, $question_text);

        if ($stmt->execute()) {
            $new_qna_id = $stmt->insert_id;
            $stmt->close();

            // Fetch the newly created question to send back
            $sql_new = "SELECT q.question_text, q.created_at, u.name, u.profile_image_path 
                        FROM event_qna q 
                        JOIN users u ON q.user_id = u.id 
                        WHERE q.id = ?";
            $stmt_new = $conn->prepare($sql_new);
            $stmt_new->bind_param("i", $new_qna_id);
            $stmt_new->execute();
            $new_question = $stmt_new->get_result()->fetch_assoc();

            $response = [
                'status' => 'success',
                'message' => 'Your question has been posted!',
                'question_html' => renderQnaItem($new_question) // Use a helper function to render HTML
            ];
        } else {
            $response['message'] = 'Failed to post your question. Please try again.';
        }
    }
}

echo json_encode($response);
exit;