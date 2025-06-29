    <?php
    // geetanjali_website/includes/auth.php

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // It's assumed that db_connection.php (which defines $conn)
    // and functions.php (which defines getSetting)
    // are included by the script that calls functions from this auth.php file,
    // or $conn is passed as a parameter where needed.
    // Ensure BASE_URL is available for any redirects if functions here were to redirect directly.

    /**
     * Registers a new user.
     *
     * @param mysqli $conn Database connection object.
     * @param string $niftem_id User's NIFTEM ID.
     * @param string $name User's full name.
     * @param string $email User's email.
     * @param string $password User's raw password.
     * @param string|null $department User's department (optional).
     * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    function registerUser(mysqli $conn, string $niftem_id, string $name, string $email, string $password, ?string $department = null): array {
        $errors = [];

        // Sanitize inputs
        $niftem_id = trim(filter_var($niftem_id, FILTER_SANITIZE_STRING));
        $name = trim(filter_var($name, FILTER_SANITIZE_STRING));
        $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
        $department = $department ? trim(filter_var($department, FILTER_SANITIZE_STRING)) : null;

        // Validate input
        if (empty($niftem_id)) $errors[] = "NIFTEM ID is required.";
        if (empty($name)) $errors[] = "Name is required.";
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (empty($password) || strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }

        // Check if NIFTEM ID or email already exists if no other validation errors
        if (empty($errors)) {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE niftem_id = ? OR email = ?");
            if (!$stmt_check) {
                error_log("Auth.php - registerUser - Prepare failed (check user): " . $conn->error);
                return ['success' => false, 'message' => 'Database error (Code: AU_REG_P01). Please try again.'];
            }
            $stmt_check->bind_param("ss", $niftem_id, $email);
            if (!$stmt_check->execute()) {
                error_log("Auth.php - registerUser - Execute failed (check user): " . $stmt_check->error);
                $stmt_check->close();
                return ['success' => false, 'message' => 'Database error (Code: AU_REG_E01). Please try again.'];
            }
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "An account with this NIFTEM ID or Email already exists.";
            }
            $stmt_check->close();
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => implode("<br>", $errors)];
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
            error_log("Auth.php - registerUser - Password hashing failed for NIFTEM ID: " . $niftem_id);
            return ['success' => false, 'message' => 'Error processing registration (Code: AU_REG_H01). Please try again.'];
        }
        
        $unique_public_id = '';
        do {
            $unique_public_id = bin2hex(random_bytes(16));
            $stmt_check_uid = $conn->prepare("SELECT id FROM users WHERE unique_public_id = ?");
            if(!$stmt_check_uid) {  error_log("Auth.php - registerUser - Prepare failed (check uid): " . $conn->error); return ['success' => false, 'message' => 'Database error (Code: AU_REG_UIDP).']; }
            $stmt_check_uid->bind_param("s", $unique_public_id);
            if(!$stmt_check_uid->execute()){ error_log("Auth.php - registerUser - Execute failed (check uid): " . $stmt_check_uid->error); $stmt_check_uid->close(); return ['success' => false, 'message' => 'Database error (Code: AU_REG_UIDE).']; }
            $stmt_check_uid->store_result();
            $uid_exists = $stmt_check_uid->num_rows > 0;
            $stmt_check_uid->close();
        } while ($uid_exists);

        // Determine initial account status and verification based on global setting
        $default_role = 'student'; 
        $is_verified_default = 0; // FALSE - Default to requiring approval
        $account_status_default = 'pending_verification'; // Default to requiring approval
        $registration_message_suffix = ' Your account is pending verification by an administrator.';

        // --- DEBUGGING AUTO-VERIFY ---
        // error_log("AUTH_DEBUG (registerUser): Initial defaults: is_verified_default = $is_verified_default, account_status_default = '$account_status_default'");

        if (function_exists('getSetting')) {
            // Default to '1' (true - require approval) if setting not found or getSetting fails, for security.
            $require_admin_approval = getSetting($conn, 'require_admin_approval_for_new_users', '1'); 
            
            // --- UNCOMMENT FOR DEBUGGING ---
            // error_log("AUTH_DEBUG (registerUser): Value of 'require_admin_approval_for_new_users' from DB/default: '" . $require_admin_approval . "' (Type: " . gettype($require_admin_approval) . ")");
            
            if ($require_admin_approval === '0') { // Strict comparison with string '0'
                $is_verified_default = 1; // TRUE
                $account_status_default = 'active';
                $registration_message_suffix = ' Your account has been automatically verified and activated!';
                // --- UNCOMMENT FOR DEBUGGING ---
                // error_log("AUTH_DEBUG (registerUser): Auto-verify LIKELY triggered. New defaults: is_verified_default = $is_verified_default, account_status_default = '$account_status_default'");
            } else {
                // --- UNCOMMENT FOR DEBUGGING ---
                // error_log("AUTH_DEBUG (registerUser): Auto-verify NOT triggered. require_admin_approval value was: '" . $require_admin_approval . "' (or was not strictly '0'). Current defaults: is_verified_default = $is_verified_default, account_status_default = '$account_status_default'");
            }
        } else {
            error_log("Auth.php - registerUser - getSetting() function not found. Defaulting to admin approval required. is_verified_default = $is_verified_default, account_status_default = '$account_status_default'");
            // Fallback keeps $is_verified_default = 0 and $account_status_default = 'pending_verification'
        }
        // --- END DEBUGGING AUTO-VERIFY ---


        $stmt_insert = $conn->prepare("INSERT INTO users (niftem_id, name, email, password, department, role, is_verified, account_status, unique_public_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_insert) {
            error_log("Auth.php - registerUser - Prepare failed (insert user): " . $conn->error);
            return ['success' => false, 'message' => 'Database error (Code: AU_REG_INSP). Please try again.'];
        }
        
        // --- DEBUGGING INSERT VALUES ---
        // error_log("AUTH_DEBUG (registerUser): Values for INSERT: niftem_id='$niftem_id', name='$name', email='$email', department='$department', role='$default_role', is_verified='$is_verified_default', account_status='$account_status_default', uid='$unique_public_id'");
        // --- END DEBUGGING INSERT VALUES ---

        $stmt_insert->bind_param("ssssssiss", $niftem_id, $name, $email, $hashed_password, $department, $default_role, $is_verified_default, $account_status_default, $unique_public_id);

        if ($stmt_insert->execute()) {
            $user_id_inserted = $stmt_insert->insert_id;
            $stmt_insert->close();
            return ['success' => true, 'message' => 'Registration successful!' . $registration_message_suffix, 'user_id' => $user_id_inserted];
        } else {
            error_log("Auth.php - registerUser - Execute failed (insert user): " . $stmt_insert->error . " for NIFTEM ID: " . $niftem_id);
            $stmt_insert->close();
            if ($conn->errno == 1062) { 
                 return ['success' => false, 'message' => 'This NIFTEM ID, Email, or generated Public ID might already be in use. Please try again or contact support if the issue persists. (Code: AU_REG_DUP)'];
            }
            return ['success' => false, 'message' => 'Registration failed due to a system error (Code: AU_REG_INSE). Please try again.'];
        }
    } // End of registerUser function

    /**
     * Logs in a user.
     *
     * @param mysqli $conn Database connection object.
     * @param string $niftem_id_or_email User's NIFTEM ID or email.
     * @param string $password User's raw password.
     * @return array ['success' => bool, 'message' => string]
     */
    function loginUser(mysqli $conn, string $niftem_id_or_email, string $password): array {
        $niftem_id_or_email = trim($niftem_id_or_email);

        if (empty($niftem_id_or_email) || empty($password)) {
            return ['success' => false, 'message' => 'NIFTEM ID/Email and password are required.'];
        }

        $field_type = filter_var($niftem_id_or_email, FILTER_VALIDATE_EMAIL) ? 'email' : 'niftem_id';
        // Fetch all necessary fields including account_status
        $sql = "SELECT id, name, password, role, profile_picture, is_verified, account_status FROM users WHERE $field_type = ?";
        
        $stmt = $conn->prepare($sql);
        if(!$stmt){
            error_log("Auth.php - loginUser - Prepare failed ($field_type): " . $conn->error);
            return ['success' => false, 'message' => 'Database error (Code: AU_LOGIN_P01). Please try again.'];
        }
        $stmt->bind_param("s", $niftem_id_or_email);
        
        if (!$stmt->execute()) {
            error_log("Auth.php - loginUser - Execute failed: " . $stmt->error);
            $stmt->close();
            return ['success' => false, 'message' => 'Login error (Code: AU_LOGIN_E01). Please try again.'];
        }
        
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close(); 

            // Check account status first
            if ($user['account_status'] === 'blocked') {
                return ['success' => false, 'message' => 'Your account has been blocked. Please contact an administrator.'];
            }
            if ($user['account_status'] === 'deactivated') {
                return ['success' => false, 'message' => 'Your account has been deactivated.'];
            }
            // If account is pending verification AND not marked as verified, block login.
            if ($user['account_status'] === 'pending_verification' && !$user['is_verified']) { 
                 return ['success' => false, 'message' => 'Your account is awaiting administrator verification. Please check back later or contact support.'];
            }

            if (password_verify($password, $user['password'])) {
                // Password is correct
                if (!session_regenerate_id(true)) { // Regenerate session ID for security
                    error_log("Auth.php - loginUser - session_regenerate_id failed.");
                }

                // Set session variables
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = (int)$user['id'];
                $_SESSION["user_name"] = $user['name'];
                $_SESSION["user_role"] = $user['role']; 
                $_SESSION["user_profile_picture"] = $user['profile_picture'];
                $_SESSION["user_is_verified"] = (bool)$user['is_verified'];
                $_SESSION["user_account_status"] = $user['account_status']; 
                $_SESSION["user_unique_public_id"] = $user['unique_public_id'] ?? null; // Ensure your getUserById/login query fetches this
                $_SESSION["user_profile_visibility"] = $user['profile_visibility'] ?? 'members_only'; // Ensure your getUserById/login query fetches this
                
                // Optional: Update last login timestamp in the database
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                if($update_stmt){
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    error_log("Auth.php - loginUser - Failed to prepare last_login update: " . $conn->error);
                }

                return ['success' => true, 'message' => 'Login successful!'];
            } else {
                // Invalid password
                return ['success' => false, 'message' => 'Invalid NIFTEM ID/Email or password.'];
            }
        } else {
            // No user found with that NIFTEM ID/Email
            $stmt->close(); 
            return ['success' => false, 'message' => 'Invalid NIFTEM ID/Email or password.'];
        }
    }

    /**
     * Logs out the current user.
     * @return bool True on successful logout, false otherwise.
     */
    function logoutUser(): bool {
        // Unset all of the session variables
        $_SESSION = array();

        // If using session cookies, delete the cookie.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Finally, destroy the session.
        if (session_destroy()) {
            return true;
        }
        error_log("Auth.php - logoutUser - Session destruction failed.");
        return false;
    }
    ?>
    