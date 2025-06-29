    <?php
    // geetanjali_website/student/profile.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // CRITICAL: Ensure these includes are correct and the files exist and are error-free.
    require_once '../includes/db_connection.php'; // Defines $conn and BASE_URL
    require_once '../includes/functions.php';     // For enforceRoleAccess(), getUserById(), sanitizeOutput(), logAudit()
    
    // Check for QR Code library, but don't make it fatal if not found for profile editing.
    if (file_exists('../includes/phpqrcode/qrlib.php')) {
        require_once '../includes/phpqrcode/qrlib.php';
    } else {
        error_log("QR Code library (qrlib.php) not found. QR Code generation will be disabled.");
    }

    // Check if $conn was successfully initialized in db_connection.php
    if (!isset($conn) || !$conn) {
        error_log("FATAL: Database connection failed or $conn is not set in student/profile.php. Check includes/db_connection.php.");
        die("A critical error occurred with the database connection. Please try again later or contact support. (Error Code: SPDB01)");
    }
    if (!defined('BASE_URL')) {
        error_log("FATAL: BASE_URL is not defined in student/profile.php. Check includes/db_connection.php or header.php include order.");
        die("A critical configuration error occurred. Please contact support. (Error Code: SPBU01)");
    }
    if (!function_exists('enforceRoleAccess') || !function_exists('getUserById') || !function_exists('logAudit')) {
        error_log("FATAL: One or more critical functions (enforceRoleAccess, getUserById, logAudit) not found. Check includes/functions.php.");
        die("A critical site function is missing. Please contact support. (Error Code: SPFA01)");
    }


    // Enforce access for logged-in users who are at least students
    enforceRoleAccess(['student', 'moderator', 'blog_editor', 'event_manager', 'admin', 'super_admin']);

    $pageTitle = "My Profile & Settings"; 
    $user_id = $_SESSION['user_id'];

    $profile_update_message = "";
    $password_change_message = "";
    $privacy_update_message = "";
    $account_action_message = "";

    // --- IMPORTANT DATABASE SCHEMA NOTE ---
    // Ensure your 'users' table has the following columns for all features to work:
    // name VARCHAR(100), email VARCHAR(100), department VARCHAR(100), profile_picture VARCHAR(255),
    // bio TEXT NULL,
    // pronouns VARCHAR(50) NULL,
    // interests_list TEXT NULL, (for comma-separated values)
    // skills_list TEXT NULL,    (for comma-separated values)
    // social_linkedin VARCHAR(255) NULL,
    // social_twitter VARCHAR(255) NULL,
    // social_portfolio VARCHAR(255) NULL,
    // profile_visibility ENUM('public', 'members_only', 'private') DEFAULT 'members_only'
    // If these columns are missing, the 'UPDATE users SET ...' query will fail.

    // --- Handle Profile Information Update ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
        error_log("Attempting profile update for user ID: " . $user_id); // Log attempt

        // Sanitize all text inputs
        $name = isset($_POST['name']) ? trim(filter_var($_POST['name'], FILTER_SANITIZE_STRING)) : '';
        $email = isset($_POST['email']) ? trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL)) : '';
        $department = isset($_POST['department']) ? trim(filter_var($_POST['department'], FILTER_SANITIZE_STRING)) : null;
        $bio = isset($_POST['bio']) ? trim(filter_var($_POST['bio'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)) : null;
        $pronouns = isset($_POST['pronouns']) ? trim(filter_var($_POST['pronouns'], FILTER_SANITIZE_STRING)) : null;
        
        $interests_input = isset($_POST['interests']) ? trim(filter_var($_POST['interests'], FILTER_SANITIZE_STRING)) : ''; 
        $skills_input = isset($_POST['skills']) ? trim(filter_var($_POST['skills'], FILTER_SANITIZE_STRING)) : '';     
        
        // Sanitize URL inputs
        $social_linkedin = isset($_POST['social_linkedin']) ? trim(filter_var($_POST['social_linkedin'], FILTER_SANITIZE_URL)) : null;
        $social_twitter = isset($_POST['social_twitter']) ? trim(filter_var($_POST['social_twitter'], FILTER_SANITIZE_URL)) : null;
        $social_portfolio = isset($_POST['social_portfolio']) ? trim(filter_var($_POST['social_portfolio'], FILTER_SANITIZE_URL)) : null;

        $errors = [];

        if (empty($name)) $errors[] = "Name cannot be empty.";
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid email is required.";
        }
        
        if (!empty($social_linkedin) && !filter_var($social_linkedin, FILTER_VALIDATE_URL)) $errors[] = "Invalid LinkedIn URL format.";
        if (!empty($social_twitter) && !filter_var($social_twitter, FILTER_VALIDATE_URL)) $errors[] = "Invalid Twitter URL format.";
        if (!empty($social_portfolio) && !filter_var($social_portfolio, FILTER_VALIDATE_URL)) $errors[] = "Invalid Portfolio/Website URL format.";
        
        if (strlen($bio ?? '') > 1000) $errors[] = "Bio cannot exceed 1000 characters.";
        if (strlen($pronouns ?? '') > 50) $errors[] = "Pronouns cannot exceed 50 characters.";

        // Check if email is already taken by another user
        if (empty($errors)) { 
            $stmt_email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            if($stmt_email_check){
                $stmt_email_check->bind_param("si", $email, $user_id);
                if ($stmt_email_check->execute()) {
                    $stmt_email_check->store_result();
                    if ($stmt_email_check->num_rows > 0) {
                        $errors[] = "This email address is already in use by another account.";
                    }
                } else {
                    $errors[] = "Database error during email check execution.";
                    error_log("Profile update: Email check execute failed: " . $stmt_email_check->error);
                }
                $stmt_email_check->close();
            } else {
                $errors[] = "Database error preparing email check.";
                error_log("Profile update: Email check prepare failed: " . $conn->error);
            }
        }

        $profile_picture_filename = $_SESSION['user_profile_picture'] ?? null; 
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profile/';
            if (!is_dir($upload_dir)) { 
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "Failed to create upload directory. Please check server permissions.";
                    error_log("Failed to create directory: " . $upload_dir);
                }
            }
            if (empty($errors) && is_dir($upload_dir) && is_writable($upload_dir)) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = $_FILES['profile_picture']['type'];
                $file_size = $_FILES['profile_picture']['size'];
                $max_file_size = 2 * 1024 * 1024; // 2MB

                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "Invalid file type. Only JPG, PNG, GIF allowed.";
                } elseif ($file_size > $max_file_size) {
                    $errors[] = "File size exceeds 2MB limit.";
                } else {
                    $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                    $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
                    $target_file = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        if ($profile_picture_filename && $profile_picture_filename !== $new_filename && file_exists($upload_dir . $profile_picture_filename)) {
                            @unlink($upload_dir . $profile_picture_filename); 
                        }
                        $profile_picture_filename = $new_filename;
                    } else {
                        $errors[] = "Failed to upload profile picture. Check server permissions for the uploads directory.";
                        error_log("Profile picture move_uploaded_file failed for user ID: " . $user_id . ". Target: " . $target_file);
                    }
                }
            } elseif (empty($errors)) { // Only add this error if no other errors and directory issue
                 $errors[] = "Upload directory is not writable or does not exist.";
                 error_log("Upload directory not writable or missing: " . $upload_dir);
            }
        }

        if (empty($errors)) {
            $sql_update = "UPDATE users SET name = ?, email = ?, department = ?, profile_picture = ?, 
                           bio = ?, pronouns = ?,
                           interests_list = ?, skills_list = ?, 
                           social_linkedin = ?, social_twitter = ?, social_portfolio = ? 
                           WHERE id = ?";
            $stmt = $conn->prepare($sql_update);
            if($stmt){
                $stmt->bind_param("sssssssssssi", 
                                  $name, $email, $department, $profile_picture_filename, 
                                  $bio, $pronouns,
                                  $interests_input, $skills_input, 
                                  $social_linkedin, $social_twitter, $social_portfolio, 
                                  $user_id);
                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $name; 
                    $_SESSION['user_profile_picture'] = $profile_picture_filename; 
                    logAudit($conn, $user_id, "Updated profile information");
                    $profile_update_message = "<div class='form-message success'>Profile updated successfully! Refreshing data...</div>";
                    // Re-fetch user data to display updated info immediately
                    // This is important because $user variable below is fetched before this block
                    $user = getUserById($conn, $user_id); 
                    if ($user) { // Check if getUserById was successful
                         $user_profile_picture_path_display = (!empty($user['profile_picture']))
                                   ? BASE_URL . 'uploads/profile/' . htmlspecialchars($user['profile_picture']) 
                                   : BASE_URL . 'assets/images/placeholder-avatar.png';
                        // Re-process interests and skills for immediate display
                        $user_interests_display = (!empty($user['interests_list']) && is_string($user['interests_list'])) ? array_filter(array_map('trim', explode(',', $user['interests_list']))) : [];
                        $user_skills_display = (!empty($user['skills_list']) && is_string($user['skills_list'])) ? array_filter(array_map('trim', explode(',', $user['skills_list']))) : [];
                    } else {
                        // Handle error if user data couldn't be re-fetched, though unlikely if update was successful
                        error_log("Could not re-fetch user data after profile update for user ID: " . $user_id);
                    }

                } else {
                    $profile_update_message = "<div class='form-message error'>Error updating profile: " . htmlspecialchars($stmt->error) . ". Please ensure all fields are valid.</div>";
                    error_log("Profile update execute failed: " . $stmt->error . " SQL: " . $sql_update);
                }
                $stmt->close();
            } else {
                 $profile_update_message = "<div class='form-message error'>Database error preparing profile update. Please check if all required database columns (bio, pronouns, interests_list, etc.) exist. Error: " . htmlspecialchars($conn->error) . "</div>";
                 error_log("Profile update prepare failed: " . $conn->error . " SQL: " . $sql_update);
            }
        } else {
            $profile_update_message = "<div class='form-message error'><ul>";
            foreach ($errors as $error) { $profile_update_message .= "<li>" . htmlspecialchars($error) . "</li>"; }
            $profile_update_message .= "</ul></div>";
        }
        error_log("Profile update process completed for user ID: " . $user_id . ". Message: " . strip_tags($profile_update_message)); // Log completion
    }

    // --- Handle Password Change ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];
        $errors_pw = [];

        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $errors_pw[] = "All password fields are required.";
        } elseif ($new_password !== $confirm_new_password) {
            $errors_pw[] = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $errors_pw[] = "New password must be at least 6 characters long.";
        } else {
            $user_data_for_pw_check = getUserById($conn, $user_id); 
            if ($user_data_for_pw_check && isset($user_data_for_pw_check['password']) && password_verify($current_password, $user_data_for_pw_check['password'])) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_pw = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if($stmt_pw){
                    $stmt_pw->bind_param("si", $hashed_new_password, $user_id);
                    if ($stmt_pw->execute()) {
                        logAudit($conn, $user_id, "Changed password");
                        $password_change_message = "<div class='form-message success'>Password changed successfully! Consider logging in again on other devices.</div>";
                    } else {
                        $password_change_message = "<div class='form-message error'>Error changing password: " . htmlspecialchars($stmt_pw->error) . "</div>";
                        error_log("Password change execute failed: " . $stmt_pw->error);
                    }
                    $stmt_pw->close();
                } else {
                    $password_change_message = "<div class='form-message error'>Database error preparing password change: " . htmlspecialchars($conn->error) . "</div>";
                    error_log("Password change prepare failed: " . $conn->error);
                }
            } else {
                $errors_pw[] = "Incorrect current password.";
            }
        }
        if(!empty($errors_pw)){
             $password_change_message = "<div class='form-message error'><ul>";
            foreach ($errors_pw as $error) { $password_change_message .= "<li>" . htmlspecialchars($error) . "</li>"; }
            $password_change_message .= "</ul></div>";
        }
    }
    
    // --- Handle Privacy Settings Update ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_privacy'])) {
        $profile_visibility = isset($_POST['profile_visibility']) ? $_POST['profile_visibility'] : 'members_only'; 
        $allowed_visibilities = ['public', 'members_only', 'private'];

        if (in_array($profile_visibility, $allowed_visibilities)) {
            $stmt_privacy = $conn->prepare("UPDATE users SET profile_visibility = ? WHERE id = ?");
            if ($stmt_privacy) {
                $stmt_privacy->bind_param("si", $profile_visibility, $user_id);
                if ($stmt_privacy->execute()) {
                    logAudit($conn, $user_id, "Updated profile visibility to: $profile_visibility");
                    $privacy_update_message = "<div class='form-message success'>Profile visibility updated to '" . htmlspecialchars(ucfirst(str_replace('_', ' ', $profile_visibility))) . "'.</div>";
                } else {
                    $privacy_update_message = "<div class='form-message error'>Error updating privacy settings: " . htmlspecialchars($stmt_privacy->error) . "</div>";
                    error_log("Privacy update execute failed: " . $stmt_privacy->error);
                }
                $stmt_privacy->close();
            } else {
                $privacy_update_message = "<div class='form-message error'>Database error preparing privacy update: " . htmlspecialchars($conn->error) . "</div>";
                error_log("Privacy update prepare failed: " . $conn->error);
            }
        } else {
            $privacy_update_message = "<div class='form-message error'>Invalid visibility option selected.</div>";
        }
    }

    // --- Handle Account Deactivation (Conceptual) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['deactivate_account_confirm'])) {
        $current_password_for_deactivation = $_POST['current_password_for_deactivation'];
        $user_for_deactivation_check = getUserById($conn, $user_id);

        if ($user_for_deactivation_check && isset($user_for_deactivation_check['password']) && password_verify($current_password_for_deactivation, $user_for_deactivation_check['password'])) {
            $account_action_message = "<div class='form-message info'>Account deactivation feature is conceptual. No action taken in this demo. For a real implementation, ensure all data privacy regulations are considered.</div>";
        } else {
           $account_action_message = "<div class='form-message error'>Incorrect password. Account deactivation denied.</div>";
        }
    }


    // Fetch current user data for display (ensure getUserById fetches all new fields)
    // This needs to be fetched *after* any potential updates from POST requests
    $user = getUserById($conn, $user_id); 
    if (!$user) {
        error_log("CRITICAL: User data for ID {$user_id} could not be fetched in profile.php after login. Session might be corrupt or user deleted.");
        $_SESSION = array(); 
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header("Location: " . BASE_URL . "login.php?error=session_error_profile");
        exit;
    }
    $user_profile_picture_path_display = (!empty($user['profile_picture']))
                               ? BASE_URL . 'uploads/profile/' . htmlspecialchars($user['profile_picture']) 
                               : BASE_URL . 'assets/images/placeholder-avatar.png';

    // QR Code Generation
    $qr_code_filename = '';
    if (class_exists('QRcode') && !empty($user['unique_public_id']) && (isset($user['profile_visibility']) && $user['profile_visibility'] == 'public')) {
        $qr_code_data = BASE_URL . 'public_profile.php?id=' . htmlspecialchars($user['unique_public_id']);
        $qr_code_relative_path = 'images/qr/user_' . htmlspecialchars($user['unique_public_id']) . '.png';
        $qr_code_full_path = '../' . $qr_code_relative_path; 
        if (!is_dir('../images/qr/')) { 
            if(!mkdir('../images/qr/', 0755, true)){
                 error_log("Failed to create QR code directory: ../images/qr/");
            }
        }
        if (is_dir('../images/qr/') && is_writable('../images/qr/') && (!file_exists($qr_code_full_path) /* Add condition to regenerate if needed */ )) { 
            QRcode::png($qr_code_data, $qr_code_full_path, QR_ECLEVEL_L, 4); 
        }
        if (file_exists($qr_code_full_path)) { 
             $qr_code_filename = BASE_URL . $qr_code_relative_path;
        } else {
            error_log("Failed to generate or find QR code image: " . $qr_code_full_path);
        }
    } elseif (!class_exists('QRcode')) {
         error_log("QRcode library (qrlib.php) not found or class QRcode does not exist in profile.php.");
    }


    $user_interests_display = (!empty($user['interests_list']) && is_string($user['interests_list'])) ? array_filter(array_map('trim', explode(',', $user['interests_list']))) : [];
    $user_skills_display = (!empty($user['skills_list']) && is_string($user['skills_list'])) ? array_filter(array_map('trim', explode(',', $user['skills_list']))) : [];
    
    // Placeholder data for conceptual sections (Badges, Activity Stream)
    $user_badges = [
        ['icon' => 'fa-award', 'title' => 'Debate Champion \'24', 'date' => 'Mar 2024'],
        ['icon' => 'fa-feather-alt', 'title' => 'Published Poet', 'date' => 'Jan 2024'],
    ];
    $user_activity_stream = [ 
        ['icon' => 'fa-calendar-check', 'text' => 'Registered for "Annual Literary Fest".', 'timestamp' => '2 days ago'],
        ['icon' => 'fa-edit', 'text' => 'Updated profile information.', 'timestamp' => '1 week ago']
    ];


    include '../includes/header.php';
    ?>

    <div class="std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                 <h1><i class="fas fa-user-alt"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                 <p class="subtitle">Manage your personal information, view your digital ID, and customize your Geetanjali experience.</p>
            </header>

            <div class="std-profile-layout">
                <aside class="std-profile-sidebar">
                    <div class="profile-user-summary">
                        <img src="<?php echo $user_profile_picture_path_display; ?>" alt="Profile Picture" class="sidebar-profile-pic">
                        <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                        <p><?php echo htmlspecialchars($user['niftem_id']); ?></p>
                        <?php if(!empty($user['pronouns'])): ?>
                            <p class="sidebar-pronouns">(<?php echo htmlspecialchars($user['pronouns']); ?>)</p>
                        <?php endif; ?>
                    </div>
                    <nav class="profile-nav">
                        <a href="#view-profile" class="profile-nav-link active" data-target="view-profile-section"><i class="fas fa-address-card"></i> View Profile & ID</a>
                        <a href="#edit-profile" class="profile-nav-link" data-target="edit-profile-section"><i class="fas fa-user-edit"></i> Edit Information</a>
                        <a href="#account-settings" class="profile-nav-link" data-target="account-settings-section"><i class="fas fa-user-cog"></i> Account Settings</a>
                        <a href="#achievements-badges" class="profile-nav-link" data-target="achievements-badges-section"><i class="fas fa-trophy"></i> Achievements</a>
                        <a href="#activity-stream" class="profile-nav-link" data-target="activity-stream-section"><i class="fas fa-history"></i> My Activity</a>
                        <hr class="sidebar-divider">
                        <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="profile-nav-link"><i class="fas fa-tachometer-alt"></i> Back to Dashboard</a>
                    </nav>
                </aside>

                <main class="std-profile-main-content">
                    <section id="view-profile-section" class="profile-section active-section" data-aos="fade-up">
                        <h2 class="section-title"><i class="fas fa-id-badge"></i> Digital ID Card & Overview</h2>
                        <div class="digital-id-card-wrapper">
                            <div class="digital-id-card">
                                <div class="id-card-header">
                                    <img src="<?php echo BASE_URL; ?>assets/images/niftem.png" alt="NIFTEM Logo" class="id-card-niftem-logo">
                                    <h3>Geetanjali Literary Society</h3>
                                    <p>NIFTEM-Kundli Student ID</p>
                                </div>
                                <div class="id-card-body">
                                    <div class="id-card-photo">
                                        <img src="<?php echo $user_profile_picture_path_display; ?>" alt="Profile Picture">
                                    </div>
                                    <div class="id-card-details">
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                                        <?php if(!empty($user['pronouns'])): ?><p><strong>Pronouns:</strong> <?php echo htmlspecialchars($user['pronouns']); ?></p><?php endif; ?>
                                        <p><strong>NIFTEM ID:</strong> <?php echo htmlspecialchars($user['niftem_id']); ?></p>
                                        <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department'] ?: 'Not Specified'); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                        <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))); ?></p>
                                        <p><strong>Member Since:</strong> <?php echo isset($user['registration_date']) ? date('M j, Y', strtotime($user['registration_date'])) : 'N/A'; ?></p>
                                    </div>
                                </div>
                                <div class="id-card-qr">
                                    <?php if ($qr_code_filename): ?>
                                        <img src="<?php echo $qr_code_filename; ?>" alt="QR Code to Public Profile">
                                        <p><small>Scan to view public profile</small></p>
                                    <?php elseif (!empty($user['unique_public_id']) && (!isset($user['profile_visibility']) || $user['profile_visibility'] != 'public')): ?>
                                        <p><small>QR code for public profile is available when visibility is set to 'Public'.</small></p>
                                    <?php else: ?>
                                        <p><small>QR Code not available.</small></p>
                                    <?php endif; ?>
                                </div>
                                <div class="id-card-footer">
                                    <img src="<?php echo BASE_URL; ?>assets/images/geetanjali.jpg" alt="Geetanjali Logo" class="id-card-geetanjali-logo">
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-view-details">
                            <?php if(!empty($user['bio'])): ?>
                            <h3 class="subsection-title"><i class="fas fa-user-tie"></i> About Me</h3>
                            <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                            <?php endif; ?>

                            <?php if(!empty($user_interests_display)): ?>
                            <h3 class="subsection-title"><i class="fas fa-palette"></i> Interests</h3>
                                <div class="profile-tags-container">
                                    <?php foreach($user_interests_display as $interest): if(!empty($interest)):?>
                                        <span class="profile-tag"><i class="fas fa-heart"></i> <?php echo htmlspecialchars($interest); ?></span>
                                    <?php endif; endforeach; ?>
                                </div>
                            <?php else: // Show message if viewing and no interests ?>
                                <?php if(empty($user['bio']) && empty($user_skills_display) && empty($user_interests_display)): // Only show if other fields are also empty ?>
                                <p>No additional profile details (bio, interests, skills) have been added yet.</p>
                                <?php elseif(empty($user_interests_display)): ?>
                                <p>No interests added yet. You can add them in the 'Edit Information' section.</p>
                                <?php endif; ?>
                            <?php endif; ?>


                            <?php if(!empty($user_skills_display)): ?>
                            <h3 class="subsection-title"><i class="fas fa-star"></i> Skills</h3>
                                <div class="profile-tags-container">
                                     <?php foreach($user_skills_display as $skill): if(!empty($skill)): ?>
                                        <span class="profile-tag skill-tag"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($skill); ?></span>
                                    <?php endif; endforeach; ?>
                                </div>
                            <?php elseif(isset($user['interests_list']) && !empty($user['interests_list'])): // Only show "no skills" if interests were shown ?>
                                <p>No skills added yet. You can add them in the 'Edit Information' section.</p>
                            <?php endif; ?>
                            
                            <?php 
                                $has_social = !empty($user['social_linkedin']) || !empty($user['social_twitter']) || !empty($user['social_portfolio']);
                            ?>
                            <?php if($has_social): ?>
                            <h3 class="subsection-title"><i class="fas fa-link"></i> Social Links</h3>
                            <div class="profile-social-links-list view-mode">
                                <?php if(!empty($user['social_linkedin'])):?><a href="<?php echo htmlspecialchars($user['social_linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-link" title="LinkedIn"><i class="fab fa-linkedin"></i> LinkedIn</a><?php endif; ?>
                                <?php if(!empty($user['social_twitter'])):?><a href="<?php echo htmlspecialchars($user['social_twitter']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-link" title="Twitter"><i class="fab fa-twitter-square"></i> Twitter</a><?php endif; ?>
                                <?php if(!empty($user['social_portfolio'])):?><a href="<?php echo htmlspecialchars($user['social_portfolio']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-link" title="Portfolio/Website"><i class="fas fa-globe"></i> Portfolio</a><?php endif; ?>
                            </div>
                            <?php elseif(isset($user['skills_list']) && !empty($user['skills_list'])): // Only show "no social" if skills were shown ?>
                                <p>No social links added yet. You can add them in the 'Edit Information' section.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section id="edit-profile-section" class="profile-section" data-aos="fade-up" style="display: none;">
                        <h2 class="section-title"><i class="fas fa-user-edit"></i> Edit Your Information</h2>
                        <?php if (!empty($profile_update_message)) echo $profile_update_message; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#edit-profile" method="POST" enctype="multipart/form-data" class="profile-form">
                            <fieldset>
                                <legend>Basic Information</legend>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="profile_name">Full Name <span class="required">*</span></label>
                                        <input type="text" id="profile_name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required class="form-control">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="profile_email">Email Address <span class="required">*</span></label>
                                        <input type="email" id="profile_email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required class="form-control">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="profile_niftem_id">NIFTEM ID</label>
                                        <input type="text" id="profile_niftem_id" name="niftem_id_display" value="<?php echo htmlspecialchars($user['niftem_id'] ?? ''); ?>" class="form-control" readonly disabled>
                                        <small class="form-text">NIFTEM ID cannot be changed.</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="profile_department">Department</label>
                                        <input type="text" id="profile_department" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="profile_pronouns">Preferred Pronouns (e.g., she/her, he/him, they/them)</label>
                                    <input type="text" id="profile_pronouns" name="pronouns" class="form-control" value="<?php echo htmlspecialchars($user['pronouns'] ?? ''); ?>" maxlength="50">
                                </div>
                                <div class="form-group">
                                    <label for="profile_bio">About Me / Bio (Max 1000 characters)</label>
                                    <textarea id="profile_bio" name="bio" class="form-control" rows="5" maxlength="1000"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="profile_picture_upload">Profile Picture (Max 2MB, JPG/PNG/GIF)</label>
                                    <input type="file" id="profile_picture_upload" name="profile_picture" class="form-control-file" accept="image/jpeg,image/png,image/gif">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <div class="current-profile-pic-display">
                                            <small>Current:</small> 
                                            <img src="<?php echo $user_profile_picture_path_display; ?>" alt="Current Profile Picture">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend>Interests & Skills</legend>
                                <div class="form-group">
                                    <label for="interests">Your Interests (comma-separated, e.g., Reading, Poetry, Debating)</label>
                                    <input type="text" id="interests" name="interests" class="form-control" value="<?php echo htmlspecialchars($user['interests_list'] ?? ''); ?>" placeholder="e.g., Fiction Writing, Public Speaking, Film Analysis">
                                </div>
                                <div class="form-group">
                                    <label for="skills">Your Skills (comma-separated, e.g., Creative Writing, Public Speaking)</label>
                                    <input type="text" id="skills" name="skills" class="form-control" value="<?php echo htmlspecialchars($user['skills_list'] ?? ''); ?>" placeholder="e.g., Editing, Event Coordination, Debating">
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend>Social Media Links</legend>
                                <div class="form-group">
                                    <label for="social_linkedin">LinkedIn Profile URL</label>
                                    <input type="url" id="social_linkedin" name="social_linkedin" class="form-control" placeholder="https://linkedin.com/in/yourprofile" value="<?php echo htmlspecialchars($user['social_linkedin'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="social_twitter">Twitter Profile URL (X)</label>
                                    <input type="url" id="social_twitter" name="social_twitter" class="form-control" placeholder="https://x.com/yourprofile" value="<?php echo htmlspecialchars($user['social_twitter'] ?? ''); ?>">
                                </div>
                                 <div class="form-group">
                                    <label for="social_portfolio">Portfolio/Website URL</label>
                                    <input type="url" id="social_portfolio" name="social_portfolio" class="form-control" placeholder="https://yourportfolio.com" value="<?php echo htmlspecialchars($user['social_portfolio'] ?? ''); ?>">
                                </div>
                            </fieldset>
                            <button type="submit" name="update_profile" class="cta-button"><i class="fas fa-save"></i> Save All Changes</button>
                        </form>
                    </section>

                    <section id="account-settings-section" class="profile-section" data-aos="fade-up" style="display: none;">
                        <h2 class="section-title"><i class="fas fa-user-cog"></i> Account Settings</h2>
                        
                        <div class="account-settings-subsection">
                            <h3 class="subsection-title mini"><i class="fas fa-key"></i> Change Password</h3>
                            <?php if (!empty($password_change_message)) echo $password_change_message; ?>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#account-settings" method="POST" class="profile-form compact-form">
                                <div class="form-group">
                                    <label for="current_password_acc">Current Password <span class="required">*</span></label>
                                    <input type="password" id="current_password_acc" name="current_password" required class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="new_password_acc">New Password (min. 6 characters) <span class="required">*</span></label>
                                    <input type="password" id="new_password_acc" name="new_password" required minlength="6" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_new_password_acc">Confirm New Password <span class="required">*</span></label>
                                    <input type="password" id="confirm_new_password_acc" name="confirm_new_password" required minlength="6" class="form-control">
                                </div>
                                <button type="submit" name="change_password" class="cta-button btn-secondary"><i class="fas fa-lock"></i> Update Password</button>
                            </form>
                        </div>
                        <hr class="section-divider">
                        <div class="account-settings-subsection">
                            <h3 class="subsection-title mini"><i class="fas fa-user-shield"></i> Profile Privacy</h3>
                            <?php if (!empty($privacy_update_message)) echo $privacy_update_message; ?>
                            <p>Control who can see your detailed profile information and public ID card.</p>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#account-settings" method="POST" class="profile-form compact-form">
                                <div class="form-group">
                                    <label for="profile_visibility">Profile Visibility <span class="required">*</span></label>
                                    <select id="profile_visibility" name="profile_visibility" class="form-control">
                                        <option value="public" <?php echo (isset($user['profile_visibility']) && $user['profile_visibility'] == 'public') ? 'selected' : ''; ?>>Public (Visible to everyone, QR code active)</option>
                                        <option value="members_only" <?php echo (isset($user['profile_visibility']) && $user['profile_visibility'] == 'members_only') ? 'selected' : ''; ?>>Members Only (Logged-in Geetanjali members)</option>
                                        <option value="private" <?php echo (isset($user['profile_visibility']) && $user['profile_visibility'] == 'private') ? 'selected' : ''; ?>>Private (Only you can see your full profile)</option>
                                    </select>
                                    <small class="form-text">This setting affects your public profile page accessible via the QR code.</small>
                                </div>
                                <button type="submit" name="update_privacy" class="cta-button btn-secondary"><i class="fas fa-check-circle"></i> Save Privacy</button>
                            </form>
                        </div>
                        <hr class="section-divider">
                        <div class="account-settings-subsection">
                             <h3 class="subsection-title mini"><i class="fas fa-file-download"></i> Download My Data (Conceptual)</h3>
                             <p>Request a copy of your personal data stored on Geetanjali website (e.g., profile info, event registrations, submissions). This feature is for demonstration and would require significant backend implementation for GDPR/data portability compliance.</p>
                             <button type="button" class="cta-button btn-info" onclick="alert('Data download request feature is conceptual and not yet implemented.');"><i class="fas fa-download"></i> Request Data Archive</button>
                        </div>
                        <hr class="section-divider">
                        <div class="account-settings-subsection">
                            <h3 class="subsection-title mini text-danger"><i class="fas fa-user-slash"></i> Deactivate Account (Conceptual)</h3>
                            <?php if (!empty($account_action_message)) echo $account_action_message; ?>
                            <p class="text-danger">Deactivating your account is a serious action. Your profile will no longer be visible, and you may lose access to certain features. This action may not be immediately reversible and data will be handled according to our retention policy.</p>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#account-settings" method="POST" class="profile-form compact-form" onsubmit="return confirm('Are you absolutely sure you want to request account deactivation? This will require admin confirmation and may be irreversible.');">
                                <div class="form-group">
                                    <label for="current_password_for_deactivation" class="text-danger">Enter Current Password to Confirm Deactivation Request <span class="required">*</span></label>
                                    <input type="password" id="current_password_for_deactivation" name="current_password_for_deactivation" required class="form-control">
                                </div>
                                <button type="submit" name="deactivate_account_confirm" class="cta-button btn-danger"><i class="fas fa-exclamation-triangle"></i> Request Account Deactivation</button>
                            </form>
                        </div>
                    </section>

                    <section id="achievements-badges-section" class="profile-section profile-badges-section" data-aos="fade-up" style="display: none;">
                        <h2 class="section-title"><i class="fas fa-trophy"></i> Achievements & Badges</h2>
                        <p>Milestones and recognitions from your journey with Geetanjali. (Conceptual placeholder).</p>
                        <div class="profile-badges-grid">
                            <?php foreach($user_badges as $badge): ?>
                            <div class="badge-item">
                                <i class="fas <?php echo htmlspecialchars($badge['icon']); ?>"></i>
                                <span class="badge-title"><?php echo htmlspecialchars($badge['title']); ?></span>
                                <small class="badge-date">Earned: <?php echo htmlspecialchars($badge['date']); ?></small>
                            </div>
                            <?php endforeach; ?>
                             <?php if(empty($user_badges)): ?><p>No badges earned yet. Participate in events to earn them!</p><?php endif; ?>
                        </div>
                    </section>

                    <section id="activity-stream-section" class="profile-section profile-activity-stream" data-aos="fade-up" style="display: none;">
                        <h2 class="section-title"><i class="fas fa-stream"></i> My Recent Activity</h2>
                        <p>A log of your recent interactions and contributions. (Conceptual placeholder).</p>
                        <div class="profile-activity-list">
                            <?php foreach($user_activity_stream as $activity): ?>
                            <div class="profile-activity-item">
                                <i class="fas <?php echo htmlspecialchars($activity['icon']); ?> activity-icon"></i>
                                <div class="profile-activity-content">
                                    <p><?php echo $activity['text']; // Assuming text is pre-sanitized or safe ?></p>
                                    <small class="activity-timestamp"><?php echo htmlspecialchars($activity['timestamp']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if(empty($user_activity_stream)): ?><p>No recent activity to display.</p><?php endif; ?>
                        </div>
                    </section>

                </main>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    