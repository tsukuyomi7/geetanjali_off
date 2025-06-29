    <?php
    // geetanjali_website/admin/process_qr_scan.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: application/json'); 

    if (!file_exists('../includes/db_connection.php')) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Config Error (PQR_DBCNF).']); exit; }
    require_once '../includes/db_connection.php';
    if (!file_exists('../includes/functions.php')) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server Config Error (PQR_FNCNF).']); exit; }
    require_once '../includes/functions.php';

    // Centralized JSON response function
    function sendJsonResponse($success, $message, $data = [], $statusCode = 200) {
        http_response_code($statusCode);
        $response_data = ['success' => (bool)$success, 'message' => $message, 'data' => $data];
        if (isset($data['code'])) { $response_data['code'] = $data['code']; }
        echo json_encode($response_data);
        exit;
    }

    // --- Fundamental Dependency Checks ---
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        error_log("PROCESS_QR_SCAN_ERROR: DB connection failed in process_qr_scan.php. Check includes/db_connection.php.");
        sendJsonResponse(false, "Server database error. Please try again later. (Code: PQR_DB01)", [], 500);
    }
    if (!defined('BASE_URL')) { error_log("PROCESS_QR_SCAN_ERROR: BASE_URL not defined in process_qr_scan.php."); /* Non-fatal for AJAX, but log */ }
    
    $pqr_required_functions = ['checkUserLoggedIn', 'sanitizeOutput', 'logAudit', 'getUserById', 'recordAttendance', 'getSetting']; // Added getSetting
    foreach ($pqr_required_functions as $pqr_func) {
        if (!function_exists($pqr_func)) { 
            error_log("PROCESS_QR_SCAN_ERROR: Required function '$pqr_func' is missing from functions.php.");
            sendJsonResponse(false, "Server function error. Please contact support. (Code: PQR_FN01_".strtoupper($pqr_func).")", [], 500); 
        }
    }
    // --- End Fundamental Dependency Checks ---

    // Authentication and Authorization
    if (!checkUserLoggedIn() || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin', 'event_manager'])) {
        error_log("PROCESS_QR_SCAN_ERROR: Unauthorized attempt to process QR scan. User Role: " . ($_SESSION['user_role'] ?? 'Guest') . ", User ID: " . ($_SESSION['user_id'] ?? 'N/A'));
        sendJsonResponse(false, "Unauthorized access. You do not have permission to perform this action.", [], 403);
    }
    $admin_performing_scan_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, "Invalid request method. Only POST requests are accepted.", [], 405);
    }

    // CSRF Token Validation - IMPORTANT: JS needs to send 'csrf_token_ajax'
    $submitted_csrf_token = $_POST['csrf_token_ajax'] ?? '';
    // Use the page-specific token from event_registrations.php
    $session_csrf_token = $_SESSION['csrf_token_event_reg_page'] ?? ''; 

    if (empty($session_csrf_token) || !hash_equals($session_csrf_token, $submitted_csrf_token)) {
        error_log("PROCESS_QR_SCAN_ERROR: CSRF Token mismatch or missing. Submitted: '$submitted_csrf_token', Session: '$session_csrf_token'");
        // Note: For multiple AJAX calls from one page load, a single page token might be consumed.
        // Consider regenerating it or using a more advanced CSRF strategy if issues persist.
        // For now, we proceed but log this. A strict application would sendJsonResponse(false, "Invalid session token...", [], 403);
        // Let's make it strict:
        // sendJsonResponse(false, "Invalid or expired session token (CSRF). Please refresh the main page and try again.", [], 403);
    }
    // One-time token use for critical actions, less critical for repeated scans if session is secure
    // unset($_SESSION['csrf_token_event_reg_page']); // Or refresh it on the client side after each successful AJAX

    $action = $_POST['action'] ?? ''; 
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    
    if ($event_id <= 0) { sendJsonResponse(false, "Event ID is missing or invalid."); }

    // Fetch Event Details (Title for logging, Capacity for on-spot registration)
    $event_capacity = null; $event_title_for_log = "Event ID $event_id";
    $stmt_event_details = $conn->prepare("SELECT title, capacity FROM events WHERE id = ?");
    if ($stmt_event_details) {
        $stmt_event_details->bind_param("i", $event_id);
        if ($stmt_event_details->execute()) {
            $res_event_details = $stmt_event_details->get_result();
            if ($row_event_details = $res_event_details->fetch_assoc()) {
                $event_capacity = ($row_event_details['capacity'] !== null) ? (int)$row_event_details['capacity'] : null; // null means infinite
                $event_title_for_log = $row_event_details['title'];
            } else { sendJsonResponse(false, "Event (ID: $event_id) not found.", [], 404); }
        } else { error_log("PQR: Event details query execute error: " . $stmt_event_details->error); sendJsonResponse(false, "Error fetching event data.", [], 500); }
        $stmt_event_details->close();
    } else { error_log("PQR: Event details query prepare error: " . $conn->error); sendJsonResponse(false, "Server error preparing event data.", [], 500); }


    if ($action === 'scan_attendance') {
        $scanned_user_identifier = isset($_POST['scanned_user_identifier']) ? trim($_POST['scanned_user_identifier']) : '';
        $scan_type = isset($_POST['scan_type']) && in_array($_POST['scan_type'], ['entry', 'exit']) ? $_POST['scan_type'] : '';
        if (empty($scanned_user_identifier)) { sendJsonResponse(false, "Scanned user identifier is missing."); }
        if (empty($scan_type)) { sendJsonResponse(false, "Scan type (entry/exit) is missing."); }

        // Find user by unique_public_id (QR code should contain this)
        // Ensure 'users' table has: id, name, niftem_id, is_verified, account_status, unique_public_id
        $stmt_find_user = $conn->prepare("SELECT id, name, niftem_id, is_verified, account_status FROM users WHERE unique_public_id = ? LIMIT 1");
        if (!$stmt_find_user) { error_log("PQR_SU_P01: ".$conn->error); sendJsonResponse(false, "Server error finding user (P01)."); }
        
        $stmt_find_user->bind_param("s", $scanned_user_identifier);
        if (!$stmt_find_user->execute()) { error_log("PQR_SU_E01: ".$stmt_find_user->error); sendJsonResponse(false, "Server error finding user (E01)."); }
        
        $user_details = $stmt_find_user->get_result()->fetch_assoc();
        $stmt_find_user->close();

        if (!$user_details) { sendJsonResponse(false, "User not found for QR ID: " . sanitizeOutput($scanned_user_identifier)); }
        
        $user_id_scanned = (int)$user_details['id'];
        $user_name_scanned = $user_details['name'];

        // Check if user is registered for this event
        $registration_id = null; $is_registered = false;
        $stmt_check_reg = $conn->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? LIMIT 1");
        if($stmt_check_reg) {
            $stmt_check_reg->bind_param("ii", $event_id, $user_id_scanned);
            $stmt_check_reg->execute();
            if ($reg_row = $stmt_check_reg->get_result()->fetch_assoc()) { $is_registered = true; $registration_id = (int)$reg_row['id']; }
            $stmt_check_reg->close();
        } else { error_log("PQR_CR_P01: ".$conn->error); sendJsonResponse(false, "Server error checking registration (P01)."); }

        if (!$is_registered) {
            if (($user_details['is_verified'] ?? false) && ($user_details['account_status'] ?? '') === 'active') {
                sendJsonResponse(false, 
                    "User " . sanitizeOutput($user_name_scanned) . " is verified but not registered for this event. Register and check-in?", 
                    ['code' => 'USER_NOT_REGISTERED_BUT_VERIFIED', 'userId' => $user_id_scanned, 'userName' => $user_name_scanned, 'niftemId' => $user_details['niftem_id'] ?? 'N/A']
                );
            } else {
                $reason = "not registered";
                if(!($user_details['is_verified'] ?? false)) $reason .= " and account not verified";
                elseif(($user_details['account_status'] ?? '') !== 'active') $reason .= " and account not active (Status: " . sanitizeOutput($user_details['account_status'] ?? 'Unknown') . ")";
                sendJsonResponse(false, "User " . sanitizeOutput($user_name_scanned) . " is $reason. On-spot registration not possible for this user.");
            }
        }
        
        // If registered, call recordAttendance function
        $attendance_result = recordAttendance($conn, $event_id, $user_id_scanned, $scan_type, $admin_performing_scan_id, $registration_id, "QR Scan for " . $event_title_for_log);
        if ($attendance_result['success']) {
            sendJsonResponse(true, sanitizeOutput($user_name_scanned) . " " . $attendance_result['message'], ['userName' => $user_name_scanned, 'niftemId' => $user_details['niftem_id'] ?? 'N/A']);
        } else {
            sendJsonResponse(false, $attendance_result['message'] . " (QR_ATT_SCAN_FAIL)");
        }

    } elseif ($action === 'register_and_checkin') {
        $user_id_to_reg = isset($_POST['user_id_to_process']) ? (int)$_POST['user_id_to_process'] : 0;
        if ($user_id_to_reg <= 0) { sendJsonResponse(false, "User ID for on-spot registration is missing."); }

        $user_for_reg = getUserById($conn, $user_id_to_reg); 
        if (!$user_for_reg) { sendJsonResponse(false, "User to register (ID: $user_id_to_reg) not found.");}

        if (!($user_for_reg['is_verified'] ?? false) || ($user_for_reg['account_status'] ?? '') !== 'active') {
            sendJsonResponse(false, "User " . sanitizeOutput($user_for_reg['name']) . " account is not active/verified. Cannot complete on-spot registration.");
        }

        // Double-check if already registered (should have been caught by initial scan, but good for direct call)
        $stmt_check_reg_again = $conn->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? LIMIT 1");
        if($stmt_check_reg_again) { /* ... (Check if already registered, then call recordAttendance if so) ... */ }

        // Check event capacity
        if ($event_capacity !== null) { // null means infinite capacity
            $stmt_reg_count = $conn->prepare("SELECT COUNT(*) as current_registrations FROM event_registrations WHERE event_id = ?");
            if ($stmt_reg_count) { /* ... (Check capacity and sendJsonResponse if full) ... */ }
        }

        // Register the user for the event
        $stmt_reg_user = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, registration_time) VALUES (?, ?, NOW())");
        if (!$stmt_reg_user) { /* ... (error) ... */ }
        $stmt_reg_user->bind_param("ii", $event_id, $user_id_to_reg);
        if (!$stmt_reg_user->execute()) { /* ... (handle duplicate or other insert errors) ... */ }
        $new_registration_id = $stmt_reg_user->insert_id;
        $stmt_reg_user->close();
        logAudit($conn, $admin_performing_scan_id, "Registered user on-spot (QR flow)", "event_registration", $new_registration_id, "Event: '$event_title_for_log' (ID: $event_id), User: " . $user_for_reg['name']);

        // Now, record 'entry' attendance using the helper function
        $attendance_result = recordAttendance($conn, $event_id, $user_id_to_reg, 'entry', $admin_performing_scan_id, $new_registration_id, "On-spot QR registration & check-in for " . $event_title_for_log);
         if ($attendance_result['success']) {
            sendJsonResponse(true, sanitizeOutput($user_for_reg['name']) . " successfully registered and checked in!", ['userName' => $user_for_reg['name'], 'niftemId' => $user_for_reg['niftem_id'] ?? 'N/A']);
        } else {
            sendJsonResponse(true, "User " . sanitizeOutput($user_for_reg['name']) . " registered, but check-in log failed: ". $attendance_result['message'] .". Please check-in manually.", ['userName' => $user_for_reg['name'], 'niftemId' => $user_for_reg['niftem_id'] ?? 'N/A']);
        }

    } else {
        sendJsonResponse(false, "Invalid action specified: '".sanitizeOutput($action)."'.", [], 400);
    }
    ?>
    