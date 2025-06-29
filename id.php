

<?php
include 'includes/phpqrcode/qrlib.php';
require_once 'includes/db_connection.php';
// Assume session_start() and db_connection.php (for $conn) are already included.
// Also assume functions.php (for sanitizeOutput) is included.

$current_user_id = null;
$unique_public_id = null;
$error_message_uid = '';

if (isset($_SESSION['user_id'])) {
    $current_user_id = (int)$_SESSION['user_id'];

    if ($current_user_id > 0) {
        $sql = "SELECT unique_public_id FROM users WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $current_user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $unique_public_id = $row['unique_public_id'];
                } else {
                    // User ID found in session, but not in DB (should not happen if session is valid)
                    $error_message_uid = "Could not retrieve user details for ID: " . htmlspecialchars($current_user_id);
                    error_log("Error: User ID $current_user_id from session not found in database.");
                }
            } else {
                $error_message_uid = "Error executing query to fetch unique ID.";
                error_log("Failed to execute statement to get unique_public_id for user ID $current_user_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $error_message_uid = "Database error preparing query for unique ID.";
            error_log("Failed to prepare statement to get unique_public_id: " . $conn->error);
        }
    } else {
        $error_message_uid = "Invalid user ID in session.";
    }
} else {
    // This case should ideally be handled by your enforceRoleAccess() or similar login checks.
    $error_message_uid = "User not logged in or session expired.";
}

// Now you can use the $unique_public_id variable.
// Example:
if ($unique_public_id) {
    echo "User's Unique Public ID: " . htmlspecialchars($unique_public_id);

    // You might store this in the session as well upon login to avoid repeated queries:
    $_SESSION['user_unique_public_id'] = $unique_public_id;
    
} elseif (!empty($error_message_uid)) {
    // echo "Error: " . $error_message_uid;
}
?>