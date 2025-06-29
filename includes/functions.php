    <?php
    // geetanjali_website/includes/functions.php
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }

    // It's assumed that db_connection.php (which defines $conn and BASE_URL)
    // is included by the script that calls functions from this file.
    // If functions in this file need $conn, it must be passed as a parameter or made global (less recommended for global).

    /**
     * Sanitizes output to prevent XSS.
     * @param mixed $data The data to sanitize.
     * @return string Sanitized data.
     */
    function sanitizeOutput($data): string {
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Basic JS output sanitization for use within <script> tags if echoing PHP variables directly.
     * Use with caution. It's better to pass data to JS via JSON or data attributes.
     */
    function sanitizeOutputJS($data): string {
        if ($data === null || $data === undefined) return '';
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }


    /**
     * Checks if a user is currently logged in.
     * @return bool True if logged in, false otherwise.
     */
    function checkUserLoggedIn(): bool {
        return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["user_id"]);
    }

    /**
     * Enforces role-based access control for pages.
     * Redirects if user is not logged in, does not have the required role, or account is inactive/blocked.
     *
     * @param array $requiredRoles An array of roles that are allowed to access the page.
     * @param string $redirect_url_if_not_logged_in Optional. URL to redirect to if not logged in.
     * @param string $redirect_url_if_unauthorized Optional. URL to redirect to if logged in but not authorized.
     */
    function enforceRoleAccess(array $requiredRoles, string $redirect_url_if_not_logged_in = '', string $redirect_url_if_unauthorized = '') {
        if (!defined('BASE_URL')) {
            error_log("FATAL: BASE_URL not defined when enforceRoleAccess called. Check include order of db_connection.php.");
            die("Critical Configuration Error (BASE_URL). Contact Support. Code: EAFN_BU01");
        }

        $login_page_url = BASE_URL . 'login.php';
        $unauthorized_page_url = BASE_URL . 'index.php?auth_error=unauthorized_access'; 

        if (empty($redirect_url_if_not_logged_in)) {
            $redirect_url_if_not_logged_in = $login_page_url . '?auth_required=1&redirect=' . urlencode($_SERVER['REQUEST_URI']);
        }
        if (empty($redirect_url_if_unauthorized)) {
            $redirect_url_if_unauthorized = $unauthorized_page_url;
        }

        if (!checkUserLoggedIn()) {
            header("Location: " . $redirect_url_if_not_logged_in);
            exit;
        }

        // Check account status from session (should be set during login by auth.php)
        if (!isset($_SESSION['user_account_status'])) {
            error_log("User ID " . ($_SESSION['user_id'] ?? 'Unknown') . " accessed protected page without account_status in session. Forcing logout.");
            if (function_exists('logoutUser')) { logoutUser(); } else { session_destroy(); } // Fallback
            header("Location: " . $login_page_url . "?status=session_error_status_missing");
            exit;
        }
        
        if ($_SESSION['user_account_status'] === 'blocked') {
            if (function_exists('logoutUser')) { logoutUser(); } else { session_destroy(); }
            header("Location: " . $login_page_url . "?status=account_blocked");
            exit;
        }
        if ($_SESSION['user_account_status'] === 'deactivated') {
            if (function_exists('logoutUser')) { logoutUser(); } else { session_destroy(); }
            header("Location: " . $login_page_url . "?status=account_deactivated");
            exit;
        }
        // Allow 'pending_verification' if is_verified is true (edge case) or if the specific page allows it (not handled here).
        // Generally, if status is 'pending_verification' AND user is not verified, login should be blocked by loginUser().
        // If they somehow bypass that, this role check will still apply.

        if (!isset($_SESSION["user_role"]) || !in_array($_SESSION["user_role"], $requiredRoles)) {
            // User is logged in but does not have the required role
            $_SESSION['error_message_permission'] = "You do not have permission to access this page.";
            header("Location: " . $redirect_url_if_unauthorized); // Redirect to a generic unauthorized page or dashboard
            exit;
        }
        
        return true; // User has access
    }


    /**
     * Logs an action to the audit_log table.
     * @param mysqli $conn The database connection object.
     * @param int $user_id The ID of the user performing the action.
     * @param string $action A description of the action performed.
     * @param string|null $target_type The type of entity affected (e.g., 'user', 'event').
     * @param int|null $target_id The ID of the entity affected.
     * @param string|null $details Additional details about the action.
     * @param string|null $ip_address Overridden IP address (optional).
     * @param string|null $user_agent Overridden User Agent (optional).
     * @return bool True on success, false on failure.
     */
    function logAudit(mysqli $conn, int $user_id, string $action, ?string $target_type = null, ?int $target_id = null, ?string $details = null, ?string $ip_address = null, ?string $user_agent = null): bool {
        $ip_address_to_log = $ip_address ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $user_agent_to_log = $user_agent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $target_id_to_log = ($target_id === 0 || $target_id === '' || $target_id === null) ? null : (int)$target_id;

        $sql = "INSERT INTO audit_log (user_id, action, target_type, target_id, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Functions.php - logAudit - Prepare failed: " . $conn->error . " SQL: " . $sql);
            return false;
        }
        $stmt->bind_param("ississs", $user_id, $action, $target_type, $target_id_to_log, $details, $ip_address_to_log, $user_agent_to_log);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Functions.php - logAudit - Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    /**
     * Fetches all users from the database with essential details for management.
     * Ensure your 'users' table has all columns selected here.
     */
    function getAllUsers(mysqli $conn): array {
        $users = [];
        $sql = "SELECT id, niftem_id, name, email, role, is_verified, account_status, registration_date, unique_public_id, profile_visibility, profile_picture 
                FROM users ORDER BY registration_date DESC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            mysqli_free_result($result);
        } else {
            error_log("Functions.php - getAllUsers - Error fetching users: " . $conn->error);
        }
        return $users;
    }
    
    /**
     * Fetches all defined roles from the ENUM type in the 'users' table 'role' column.
     */
    function getDefinedRoles(mysqli $conn): array {
        $roles = ['student', 'admin', 'super_admin', 'moderator', 'blog_editor', 'event_manager']; // Fallback
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($result && $row = $result->fetch_assoc()) {
            $type = $row['Type']; 
            preg_match_all("/'([^']+)'/", $type, $matches);
            if (!empty($matches[1])) {
                $roles = $matches[1];
            }
            mysqli_free_result($result);
        } else {
            error_log("Functions.php - getDefinedRoles - Error fetching roles enum: " . $conn->error . ". Using hardcoded fallback.");
        }
        return $roles;
    }

// in includes/functions.php

// ... other functions like checkUserLoggedIn() ...

// MODIFIED: Use 'event_registrations' table
function checkIfUserRegistered($conn, $user_id, $event_id) {
    // Use the correct table name: event_registrations
    $stmt = $conn->prepare("SELECT id FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->num_rows > 0;
}

// MODIFIED: Use 'event_registrations' table
function getRegistrationCount($conn, $event_id) {
    // Use the correct table name: event_registrations
    $stmt = $conn->prepare("SELECT COUNT(id) as count FROM event_registrations WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] ?? 0;
}

// ... other functions like create_excerpt() ...

    /**
     * Fetches a single user by ID, including all profile-related fields.
     * Ensure your 'users' table schema matches these columns.
     */
    function getUserById(mysqli $conn, int $user_id) {
        $sql = "SELECT id, niftem_id, name, email, password, department, profile_picture, role, 
                       is_verified, account_status, unique_public_id, registration_date, last_login,
                       bio, pronouns, interests_list, skills_list, 
                       social_linkedin, social_twitter, social_portfolio, profile_visibility,
                       reset_token, reset_token_expiry 
                FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if(!$stmt) { 
            error_log("Functions.php - getUserById - Prepare failed for user ID $user_id: " . $conn->error); 
            return null; 
        }
        $stmt->bind_param("i", $user_id);
        if(!$stmt->execute()) { 
            error_log("Functions.php - getUserById - Execute failed for user ID $user_id: " . $stmt->error); 
            $stmt->close(); 
            return null; 
        }
        $result = $stmt->get_result();
        $user = $result->fetch_assoc(); 
        $stmt->close();
        return $user;
    }

    /**
     * Fetches a setting value from the 'settings' table.
     */
    function getSetting(mysqli $conn, string $setting_key, $default_value = null) {
        static $settings_cache = []; // Simple static cache for settings per request

        if (isset($settings_cache[$setting_key])) {
            return $settings_cache[$setting_key];
        }

        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        if(!$stmt) { 
            error_log("Functions.php - getSetting - Prepare failed for key '$setting_key': " . $conn->error); 
            return $default_value; 
        }
        $stmt->bind_param("s", $setting_key);
        if(!$stmt->execute()) { 
            error_log("Functions.php - getSetting - Execute failed for key '$setting_key': " . $stmt->error); 
            $stmt->close(); 
            return $default_value; 
        }
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc()){
            $stmt->close();
            $settings_cache[$setting_key] = $row['setting_value']; // Cache it
            return $row['setting_value'];
        }
        $stmt->close();
        $settings_cache[$setting_key] = $default_value; // Cache default if not found
        return $default_value;
    }

    /**
     * Updates a setting value in the 'settings' table.
     * Audit logging for setting changes should ideally be handled in the script that calls updateSetting 
     * for more context (e.g., which admin changed what from which page).
     */
    function updateSetting(mysqli $conn, string $setting_key, string $setting_value): bool {
        static $settings_cache = []; // Static cache for settings
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        if(!$stmt) { 
            error_log("Functions.php - updateSetting - Prepare failed for key '$setting_key': " . $conn->error); 
            return false; 
        }
        $stmt->bind_param("ss", $setting_key, $setting_value); 
        if($stmt->execute()){
            $stmt->close();
            $settings_cache[$setting_key] = $setting_value; // Update cache
            return true;
        }
        error_log("Functions.php - updateSetting - Execute failed for key '$setting_key': " . $stmt->error);
        $stmt->close();
        return false;
    }

    /**
     * Converts a datetime string or Unix timestamp into a user-friendly "time ago" string.
     */
    function time_elapsed_string($datetime, bool $full = false): string {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return 'an unknown time'; 
        }
        try {
            $now = new DateTime('now', new DateTimeZone('UTC')); 
            if (is_numeric($datetime)) { 
                $ago = new DateTime("@{$datetime}", new DateTimeZone('UTC'));
            } else { 
                $ago = new DateTime($datetime, new DateTimeZone('UTC')); 
            }
        } catch (Exception $e) {
            error_log("Error parsing date for time_elapsed_string: " . $e->getMessage() . " | Input: " . print_r($datetime, true));
            return 'a while ago'; 
        }

        $diff = $now->diff($ago);
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else { unset($string[$k]); }
        }
        if (!$full) $string = array_slice($string, 0, 1);
        $time_elapsed = $string ? implode(', ', $string) : 'just now';
        
        return ($now < $ago) ? 'in ' . $time_elapsed : $time_elapsed . ' ago';
    }

    /**
     * Generates a URL-friendly slug from a string.
     */
    function generateSlug(string $title, int $maxLength = 250): string {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-\s]+/', '', $slug); // Remove non-alphanumeric, except hyphens and spaces
        $slug = preg_replace('/[\s-]+/', '-', $slug);       // Replace spaces and multiple hyphens with single hyphen
        $slug = trim($slug, '-');                          // Remove leading/trailing hyphens
        if (strlen($slug) > $maxLength) {
            $slug = substr($slug, 0, $maxLength);
            $slug = trim($slug, '-'); // Trim again if substr cut a hyphen
        }
        return $slug ?: 'n-a'; // Fallback for empty titles after sanitization
    }

    /**
     * Helper to get blog post status for conditional logic (e.g. setting published_at).
     * (Used in admin/blogs.php)
     */
    function getPostStatus(mysqli $conn, int $post_id): ?string {
        $stmt = $conn->prepare("SELECT status FROM blog_posts WHERE id = ?");
        if($stmt){
            $stmt->bind_param("i", $post_id);
            if($stmt->execute()){
                $result = $stmt->get_result();
                if($row = $result->fetch_assoc()){
                    $stmt->close();
                    return $row['status'];
                }
            }
            $stmt->close();
        }
        return null;
    }

    /**
     * Generates pagination links.
     * @param int $current_page Current page number.
     * @param int $total_pages Total number of pages.
     * @param string $base_url The base URL for pagination links (e.g., "blogs.php").
     * @param array $query_params Existing GET parameters to preserve in links.
     * @param int $links_to_show Number of direct page links to show around current page.
     * @return string HTML for pagination.
     */
    function generatePaginationLinks(int $current_page, int $total_pages, string $base_url, array $query_params = [], int $links_to_show = 3): string {
        if ($total_pages <= 1) return "";

        $output = '<nav class="pagination-container" aria-label="Page navigation"><ul class="pagination-list">';
        
        // Previous button
        if ($current_page > 1) {
            $prev_page_params = array_merge($query_params, ['page' => $current_page - 1]);
            $output .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($prev_page_params) . '"><i class="fas fa-chevron-left"></i> Prev</a></li>';
        } else {
            $output .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i> Prev</span></li>';
        }

        // Numbered links
        $start_page = max(1, $current_page - floor($links_to_show / 2));
        $end_page = min($total_pages, $start_page + $links_to_show - 1);
        if ($end_page - $start_page + 1 < $links_to_show && $start_page > 1) {
            $start_page = max(1, $end_page - $links_to_show + 1);
        }

        if ($start_page > 1) {
            $first_page_params = array_merge($query_params, ['page' => 1]);
            $output .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($first_page_params) . '">1</a></li>';
            if ($start_page > 2) {
                $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
            $page_params = array_merge($query_params, ['page' => $i]);
            $active_class = ($i == $current_page) ? 'active' : '';
            $output .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="' . $base_url . '?' . http_build_query($page_params) . '">' . $i . '</a></li>';
        }

        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $last_page_params = array_merge($query_params, ['page' => $total_pages]);
            $output .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($last_page_params) . '">' . $total_pages . '</a></li>';
        }

        // Next button
        if ($current_page < $total_pages) {
            $next_page_params = array_merge($query_params, ['page' => $current_page + 1]);
            $output .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($next_page_params) . '">Next <i class="fas fa-chevron-right"></i></a></li>';
        } else {
            $output .= '<li class="page-item disabled"><span class="page-link">Next <i class="fas fa-chevron-right"></i></span></li>';
        }
        
        $output .= '</ul></nav>';
        return $output;
    }

    /**
     * Records manual or QR-based attendance for an event.
     *
     * @param mysqli $conn Database connection.
     * @param int $event_id The ID of the event.
     * @param int $user_id_att The ID of the user whose attendance is being recorded.
     * @param string $scan_type_att 'entry' or 'exit'.
     * @param int $admin_id_att The ID of the admin/manager performing the action.
     * @param int|null $registration_id_att The ID of the event_registrations entry (optional).
     * @param string|null $notes_att Optional notes for the attendance log.
     * @return array ['success' => bool, 'message' => string]
     */
    function recordAttendance(mysqli $conn, int $event_id_att, int $user_id_att, string $scan_type_att, int $admin_id_att, ?int $registration_id_att = null, ?string $notes_att = null): array {
        $response = ['success' => false, 'message' => 'Failed to record attendance.'];
        
        if (!in_array($scan_type_att, ['entry', 'exit'])) {
            $response['message'] = 'Invalid attendance type specified.';
            return $response;
        }

        // Check last scan status for this user and event to prevent illogical scans
        $last_scan_type = null;
        $stmt_ls = $conn->prepare("SELECT scan_type FROM event_attendance_log WHERE event_id = ? AND user_id = ? ORDER BY scan_time DESC LIMIT 1");
        if ($stmt_ls) {
            $stmt_ls->bind_param("ii", $event_id_att, $user_id_att);
            if ($stmt_ls->execute()) {
                $res_ls = $stmt_ls->get_result();
                if ($row_ls = $res_ls->fetch_assoc()) {
                    $last_scan_type = $row_ls['scan_type'];
                }
            } else { error_log("recordAttendance: Last scan execute error: " . $stmt_ls->error); }
            $stmt_ls->close();
        } else { error_log("recordAttendance: Last scan prepare error: " . $conn->error); }

        if ($scan_type_att === 'entry' && $last_scan_type === 'entry') {
            $response['message'] = "User is already marked as 'Checked In'.";
            return $response;
        }
        if ($scan_type_att === 'exit' && $last_scan_type !== 'entry') {
            // Allow exit if no previous scan (implies they were missed at entry) OR if last was not entry
            // For stricter logic: if ($last_scan_type !== 'entry') -> "User not checked in."
             $response['message'] = "User was not marked as 'Checked In'. Cannot process exit unless they were missed at entry.";
            // To allow exit anyway: comment out the return above and let it proceed
        }

        $stmt_log = $conn->prepare("INSERT INTO event_attendance_log (event_id, user_id, registration_id, scan_type, scanned_by_user_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt_log) {
            $final_notes = $notes_att ?? "Update by admin ID: $admin_id_att";
            $stmt_log->bind_param("iiisis", $event_id_att, $user_id_att, $registration_id_att, $scan_type_att, $admin_id_att, $final_notes);
            if ($stmt_log->execute()) {
                $response['success'] = true;
                $response['message'] = "User attendance successfully updated to '" . ucfirst($scan_type_att) . "'.";
                logAudit($conn, $admin_id_att, "Set attendance to " . strtoupper($scan_type_att), "event_attendance", $stmt_log->insert_id, "Event ID: $event_id_att, User ID: $user_id_att. Notes: $final_notes");
            } else { 
                $response['message'] = "Error updating attendance: " . $stmt_log->error; 
                error_log("recordAttendance: Log execute error: " . $stmt_log->error); 
            }
            $stmt_log->close();
        } else { 
            $response['message'] = "Error preparing attendance update."; 
            error_log("recordAttendance: Log prepare error: " . $conn->error); 
        }
        return $response;
    }

    ?>
    