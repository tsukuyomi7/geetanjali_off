    <?php
    // geetanjali_website/super_admin/edit_user.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php'; // For enforceRoleAccess, getUserById, logAudit, sanitizeOutput, getDefinedRoles

    // --- CRITICAL INCLUDES AND CHECKS ---
    if (!isset($conn) || !$conn || $conn->connect_error) {
        error_log("FATAL ERROR in edit_user.php: Database connection failed. Error: " . ($conn ? $conn->connect_error : 'Unknown DB connection issue.'));
        die("A critical database connection error occurred. (Error Code: SAE_DB01)");
    }
    if (!defined('BASE_URL')) {
        error_log("FATAL ERROR in edit_user.php: BASE_URL constant is not defined.");
        die("A critical configuration error (BASE_URL missing). (Error Code: SAE_BU01)");
    }
    $required_functions_sae = ['enforceRoleAccess', 'getUserById', 'logAudit', 'sanitizeOutput', 'getDefinedRoles'];
    foreach ($required_functions_sae as $func_sae) {
        if (!function_exists($func_sae)) {
            error_log("FATAL ERROR in edit_user.php: Required function '$func_sae' is not defined. Check includes/functions.php.");
            die("A critical site function ('$func_sae') is missing. (Error Code: SAE_FN01)");
        }
    }
    // --- END CRITICAL CHECKS ---

    enforceRoleAccess(['super_admin']);

    $pageTitle = "Edit User Profile & Account";
    $message = "";
    $user_to_edit = null;
    $user_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($user_id_to_edit <= 0) {
        $_SESSION['error_message'] = "Invalid user ID specified for editing.";
        header("Location: " . BASE_URL . "super_admin/manage_users.php");
        exit;
    }

    $user_to_edit = getUserById($conn, $user_id_to_edit);

    if (!$user_to_edit) {
        $_SESSION['error_message'] = "User not found with ID: " . sanitizeOutput($user_id_to_edit) . ". They may have been deleted.";
        header("Location: " . BASE_URL . "super_admin/manage_users.php");
        exit;
    }

    $defined_roles = getDefinedRoles($conn);
    $account_statuses_available = ['active', 'pending_verification', 'blocked', 'deactivated']; 

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_details'])) {
        $name = trim(filter_var($_POST['name'], FILTER_SANITIZE_STRING));
        $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $department = isset($_POST['department']) ? trim(filter_var($_POST['department'], FILTER_SANITIZE_STRING)) : ($user_to_edit['department'] ?? null);
        $new_role = isset($_POST['role']) ? $_POST['role'] : $user_to_edit['role'];
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;
        $account_status = isset($_POST['account_status']) && in_array($_POST['account_status'], $account_statuses_available) ? $_POST['account_status'] : $user_to_edit['account_status'];
        $status_reason = isset($_POST['status_reason']) ? trim(filter_var($_POST['status_reason'], FILTER_SANITIZE_STRING)) : null;

        $bio = isset($_POST['bio']) ? trim(filter_var($_POST['bio'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)) : ($user_to_edit['bio'] ?? null);
        $pronouns = isset($_POST['pronouns']) ? trim(filter_var($_POST['pronouns'], FILTER_SANITIZE_STRING)) : ($user_to_edit['pronouns'] ?? null);
        $interests_input = isset($_POST['interests_list']) ? trim(filter_var($_POST['interests_list'], FILTER_SANITIZE_STRING)) : ($user_to_edit['interests_list'] ?? ''); 
        $skills_input = isset($_POST['skills_list']) ? trim(filter_var($_POST['skills_list'], FILTER_SANITIZE_STRING)) : ($user_to_edit['skills_list'] ?? '');     
        $social_linkedin = isset($_POST['social_linkedin']) ? trim(filter_var($_POST['social_linkedin'], FILTER_SANITIZE_URL)) : ($user_to_edit['social_linkedin'] ?? null);
        $social_twitter = isset($_POST['social_twitter']) ? trim(filter_var($_POST['social_twitter'], FILTER_SANITIZE_URL)) : ($user_to_edit['social_twitter'] ?? null);
        $social_portfolio = isset($_POST['social_portfolio']) ? trim(filter_var($_POST['social_portfolio'], FILTER_SANITIZE_URL)) : ($user_to_edit['social_portfolio'] ?? null);
        $profile_visibility = isset($_POST['profile_visibility']) && in_array($_POST['profile_visibility'], ['public', 'members_only', 'private']) ? $_POST['profile_visibility'] : ($user_to_edit['profile_visibility'] ?? 'members_only');

        $errors = [];

        if (empty($name)) $errors[] = "Name cannot be empty.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
        if (!in_array($new_role, $defined_roles)) $errors[] = "Invalid role selected.";
        if (!empty($social_linkedin) && !filter_var($social_linkedin, FILTER_VALIDATE_URL)) $errors[] = "Invalid LinkedIn URL.";
        if (!empty($social_twitter) && !filter_var($social_twitter, FILTER_VALIDATE_URL)) $errors[] = "Invalid Twitter URL.";
        if (!empty($social_portfolio) && !filter_var($social_portfolio, FILTER_VALIDATE_URL)) $errors[] = "Invalid Portfolio URL.";

        if (empty($errors) && strtolower($email) !== strtolower($user_to_edit['email'])) {
            $stmt_email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            if ($stmt_email_check) {
                $stmt_email_check->bind_param("si", $email, $user_id_to_edit);
                if($stmt_email_check->execute()){
                    $stmt_email_check->store_result();
                    if ($stmt_email_check->num_rows > 0) {
                        $errors[] = "This email address is already in use by another account.";
                    }
                } else {
                    $errors[] = "Database error during email check execution.";
                    error_log("Edit user: Email check execute failed: " . $stmt_email_check->error);
                }
                $stmt_email_check->close();
            } else {
                $errors[] = "Database error preparing email check.";
                error_log("Edit user: Email check prepare failed: " . $conn->error);
            }
        }
        
        // Safeguards
        if (empty($errors) && $user_id_to_edit == $_SESSION['user_id'] && $user_to_edit['role'] == 'super_admin') {
            if ($new_role != 'super_admin') {
                $stmt_count_super = $conn->prepare("SELECT COUNT(*) as total_super_admins FROM users WHERE role = 'super_admin' AND account_status = 'active'");
                if($stmt_count_super){
                    $stmt_count_super->execute(); $res_super = $stmt_count_super->get_result();
                    $count_super_admins = $res_super->fetch_assoc()['total_super_admins']; $stmt_count_super->close();
                    if ($count_super_admins <= 1) $errors[] = "Cannot change your own role from Super Admin as you are the only active one.";
                } else { error_log("Edit user: Super admin count prepare failed: " . $conn->error); $errors[] = "DB error checking super admin count.";}
            }
            if ($account_status == 'blocked' || $account_status == 'deactivated') {
                 $stmt_count_active_super = $conn->prepare("SELECT COUNT(*) as total_active_super FROM users WHERE role = 'super_admin' AND account_status = 'active'");
                 if($stmt_count_active_super){
                    $stmt_count_active_super->execute(); $res_active_super = $stmt_count_active_super->get_result();
                    $count_active_super_admins = $res_active_super->fetch_assoc()['total_active_super']; $stmt_count_active_super->close();
                    if ($count_active_super_admins <= 1) $errors[] = "Cannot block or deactivate your own account as you are the only active Super Admin.";
                 } else { error_log("Edit user: Active super admin count prepare failed: " . $conn->error); $errors[] = "DB error checking active super admin count.";}
            }
        } elseif (empty($errors) && $user_to_edit['role'] == 'super_admin' && ($account_status == 'blocked' || $account_status == 'deactivated')) {
             $stmt_count_active_super = $conn->prepare("SELECT COUNT(*) as total_active_super FROM users WHERE role = 'super_admin' AND account_status = 'active' AND id != ?");
             if($stmt_count_active_super){
                $stmt_count_active_super->bind_param("i", $user_id_to_edit);
                $stmt_count_active_super->execute(); $res_active_super = $stmt_count_active_super->get_result();
                $count_other_active_super_admins = $res_active_super->fetch_assoc()['total_active_super']; $stmt_count_active_super->close();
                if ($count_other_active_super_admins < 1) $errors[] = "Cannot block or deactivate the last active Super Admin.";
             } else { error_log("Edit user: Other active super admin count prepare failed: " . $conn->error); $errors[] = "DB error checking other active super admin count.";}
        }

        if (empty($errors)) {
            $sql_update = "UPDATE users SET name = ?, email = ?, department = ?, role = ?, is_verified = ?, account_status = ?,
                           bio = ?, pronouns = ?, interests_list = ?, skills_list = ?, 
                           social_linkedin = ?, social_twitter = ?, social_portfolio = ?, profile_visibility = ?
                           WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                // Types: name=s, email=s, department=s, role=s, is_verified=i, account_status=s,
                // bio=s, pronouns=s, interests_list=s, skills_list=s,
                // social_linkedin=s, social_twitter=s, social_portfolio=s, profile_visibility=s,
                // id=i (WHERE clause)
                // Total: 14 SET columns + 1 WHERE column = 15 parameters
                // String: s s s s i s s s s s s s s s i
                $stmt_update->bind_param("ssssisssssssssi", 
                    $name, $email, $department, $new_role, $is_verified, $account_status,
                    $bio, $pronouns, $interests_input, $skills_input,
                    $social_linkedin, $social_twitter, $social_portfolio, $profile_visibility,
                    $user_id_to_edit);

                if ($stmt_update->execute()) {
                    $log_details = "Name: $name, Email: $email, Role: $new_role, Verified: $is_verified, Status: $account_status, Visibility: $profile_visibility";
                    if(!empty($status_reason)) $log_details .= ", Update Reason: " . $status_reason;
                    logAudit($conn, $_SESSION['user_id'], "Updated user details", "user", $user_id_to_edit, $log_details);
                    $_SESSION['success_message'] = "User details for '" . sanitizeOutput($name) . "' updated successfully.";
                    header("Location: " . BASE_URL . "super_admin/manage_users.php"); 
                    exit;
                } else {
                    $message = "<div class='form-message error'>Failed to update user details: " . htmlspecialchars($stmt_update->error) . "</div>";
                    error_log("Edit user execute failed: " . $stmt_update->error . " | SQL: " . $sql_update);
                }
                $stmt_update->close();
            } else {
                $message = "<div class='form-message error'>Database error preparing user update: " . htmlspecialchars($conn->error) . ". Ensure all columns (bio, pronouns, social links etc.) exist in 'users' table.</div>";
                error_log("Edit user prepare failed: " . $conn->error . " | SQL: " . $sql_update);
            }
        } else {
            $message = "<div class='form-message error'><ul>";
            foreach ($errors as $error) { $message .= "<li>" . htmlspecialchars($error) . "</li>"; }
            $message .= "</ul></div>";
        }
    }

    // Re-fetch user data if an update was attempted and failed, to show current values or errors
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
        $user_to_edit_after_attempt = getUserById($conn, $user_id_to_edit);
        if ($user_to_edit_after_attempt) {
            $user_to_edit = $user_to_edit_after_attempt; 
        }
    }

    include '../includes/header.php';
    ?>

    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Modifying account for: <strong><?php echo sanitizeOutput($user_to_edit['name']); ?> (NIFTEM ID: <?php echo sanitizeOutput($user_to_edit['niftem_id']); ?>)</strong></p>
                <p><a href="<?php echo BASE_URL; ?>super_admin/manage_users.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to User Management</a></p>
            </header>

            <?php if (!empty($message)) echo $message; ?>

            <section class="content-section std-profile-main-content"> 
                <form action="<?php echo htmlspecialchars(BASE_URL . 'super_admin/edit_user.php?id=' . $user_id_to_edit); ?>" method="POST" class="profile-form admin-settings-form">
                    
                    <fieldset>
                        <legend><i class="fas fa-info-circle"></i> Core Account Information</legend>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="niftem_id_display">NIFTEM ID</label>
                                <input type="text" id="niftem_id_display" value="<?php echo sanitizeOutput($user_to_edit['niftem_id']); ?>" class="form-control" readonly disabled>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="name">Full Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" value="<?php echo sanitizeOutput($user_to_edit['name']); ?>" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" value="<?php echo sanitizeOutput($user_to_edit['email']); ?>" class="form-control" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" value="<?php echo sanitizeOutput($user_to_edit['department'] ?? ''); ?>" class="form-control">
                            </div>
                        </div>
                         <div class="form-group">
                            <label for="pronouns">Preferred Pronouns</label>
                            <input type="text" id="pronouns" name="pronouns" class="form-control" value="<?php echo sanitizeOutput($user_to_edit['pronouns'] ?? ''); ?>" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label for="bio">Bio / About (Max 1000 characters)</label>
                            <textarea id="bio" name="bio" class="form-control" rows="3" maxlength="1000"><?php echo sanitizeOutput($user_to_edit['bio'] ?? ''); ?></textarea>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><i class="fas fa-user-tag"></i> Role, Status & Verification</legend>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="role">User Role <span class="required">*</span></label>
                                <select id="role" name="role" class="form-control">
                                    <?php foreach ($defined_roles as $role_option): ?>
                                        <option value="<?php echo $role_option; ?>" <?php echo (isset($user_to_edit['role']) && $user_to_edit['role'] == $role_option) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $role_option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="account_status">Account Status <span class="required">*</span></label>
                                <select id="account_status" name="account_status" class="form-control" onchange="toggleStatusReason(this.value)">
                                    <?php foreach ($account_statuses_available as $status_option): ?>
                                        <option value="<?php echo $status_option; ?>" <?php echo (isset($user_to_edit['account_status']) && $user_to_edit['account_status'] == $status_option) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $status_option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Current: <span class="account-status-badge status-<?php echo sanitizeOutput($user_to_edit['account_status'] ?? 'pending_verification'); ?>"><?php echo sanitizeOutput(ucfirst(str_replace('_', ' ', $user_to_edit['account_status'] ?? 'pending_verification'))); ?></span></small>
                            </div>
                             <div class="form-group col-md-4 form-check-toggle align-self-center"> 
                                <label for="is_verified">User Verified</label>
                                <label class="switch">
                                    <input type="checkbox" id="is_verified" name="is_verified" value="1" <?php echo (isset($user_to_edit['is_verified']) && $user_to_edit['is_verified'] == 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group" id="status_reason_group" style="<?php echo (isset($user_to_edit['account_status']) && ($user_to_edit['account_status'] == 'blocked' || $user_to_edit['account_status'] == 'deactivated')) ? 'display:block;' : 'display:none;'; ?>">
                            <label for="status_reason">Reason for Status Change (Optional, for Blocked/Deactivated)</label>
                            <textarea id="status_reason" name="status_reason" class="form-control" rows="2" placeholder="e.g., Violation of terms, User request..."></textarea>
                            <small class="form-text text-muted">This reason will be logged in the audit trail if provided.</small>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><i class="fas fa-paint-brush"></i> Interests & Skills</legend>
                        <div class="form-group">
                            <label for="interests_list">Interests (comma-separated)</label>
                            <input type="text" id="interests_list" name="interests_list" class="form-control" value="<?php echo sanitizeOutput($user_to_edit['interests_list'] ?? ''); ?>" placeholder="e.g., Reading, Poetry, Debating">
                        </div>
                        <div class="form-group">
                            <label for="skills_list">Skills (comma-separated)</label>
                            <input type="text" id="skills_list" name="skills_list" class="form-control" value="<?php echo sanitizeOutput($user_to_edit['skills_list'] ?? ''); ?>" placeholder="e.g., Creative Writing, Public Speaking">
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><i class="fas fa-link"></i> Social Media Links</legend>
                        <div class="form-group">
                            <label for="social_linkedin">LinkedIn Profile URL</label>
                            <input type="url" id="social_linkedin" name="social_linkedin" class="form-control" placeholder="https://linkedin.com/in/yourprofile" value="<?php echo sanitizeOutput($user_to_edit['social_linkedin'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="social_twitter">Twitter Profile URL (X)</label>
                            <input type="url" id="social_twitter" name="social_twitter" class="form-control" placeholder="https://x.com/yourprofile" value="<?php echo sanitizeOutput($user_to_edit['social_twitter'] ?? ''); ?>">
                        </div>
                         <div class="form-group">
                            <label for="social_portfolio">Portfolio/Website URL</label>
                            <input type="url" id="social_portfolio" name="social_portfolio" class="form-control" placeholder="https://yourportfolio.com" value="<?php echo sanitizeOutput($user_to_edit['social_portfolio'] ?? ''); ?>">
                        </div>
                    </fieldset>
                     <fieldset>
                        <legend><i class="fas fa-eye"></i> Profile Visibility</legend>
                        <div class="form-group">
                            <label for="profile_visibility">Public Profile Visibility</label>
                            <select id="profile_visibility" name="profile_visibility" class="form-control">
                                <option value="public" <?php echo (isset($user_to_edit['profile_visibility']) && $user_to_edit['profile_visibility'] == 'public') ? 'selected' : ''; ?>>Public</option>
                                <option value="members_only" <?php echo (isset($user_to_edit['profile_visibility']) && $user_to_edit['profile_visibility'] == 'members_only') ? 'selected' : ''; ?>>Members Only</option>
                                <option value="private" <?php echo (isset($user_to_edit['profile_visibility']) && $user_to_edit['profile_visibility'] == 'private') ? 'selected' : ''; ?>>Private</option>
                            </select>
                            <small class="form-text text-muted">Controls who can see the user's public profile page.</small>
                        </div>
                    </fieldset>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_user_details" class="cta-button"><i class="fas fa-save"></i> Save All Changes</button>
                        <a href="<?php echo BASE_URL; ?>super_admin/manage_users.php" class="cta-button btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>

                <hr class="section-divider my-4">

                <section class="conceptual-actions-section">
                     <h3 class="subsection-title mini"><i class="fas fa-key"></i> Password Management</h3>
                     <p>If the user forgot their password, you can trigger a password reset email (requires email system setup and a separate page/process) or, in critical cases, set a temporary password (use with extreme caution).</p>
                     <button type="button" class="cta-button btn-warning btn-sm" onclick="alert('Password reset link functionality not implemented in this demo. This would typically send an email to the user.');"><i class="fas fa-envelope"></i> Send Password Reset Link</button>
                </section>

            </section>
        </div>
    </main>
    <script>
        function toggleStatusReason(selectedValue) {
            const reasonGroup = document.getElementById('status_reason_group');
            if (reasonGroup) {
                if (selectedValue === 'blocked' || selectedValue === 'deactivated') {
                    reasonGroup.style.display = 'block';
                } else {
                    reasonGroup.style.display = 'none';
                }
            }
        }
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const accountStatusSelect = document.getElementById('account_status');
            if (accountStatusSelect) {
                toggleStatusReason(accountStatusSelect.value);
            }
        });
    </script>
    <?php include '../includes/footer.php'; ?>
    