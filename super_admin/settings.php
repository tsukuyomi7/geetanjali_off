    <?php
    // geetanjali_website/super_admin/settings.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; 
    require_once '../includes/functions.php';     

    enforceRoleAccess(['super_admin']);

    $pageTitle = "Global Website Settings";
    $message = ""; 

    $setting_keys = [
        // General Site Configuration
        'website_name' => ['label' => 'Website Name/Title', 'type' => 'text', 'default' => 'Geetanjali - The Literary Society', 'category' => 'general', 'icon' => 'fa-globe-asia'],
        'admin_email' => ['label' => 'Main Administrative Email', 'type' => 'email', 'default' => 'admin@niftem.ac.in', 'category' => 'general', 'icon' => 'fa-envelope-open-text', 'help' => 'Used for site notifications and contact forms.'],
        'items_per_page' => ['label' => 'Items Per Page (for lists)', 'type' => 'number', 'default' => 10, 'category' => 'general', 'icon' => 'fa-list-ol', 'help' => 'Default items in paginated views (e.g., blog, events). Min: 1.'],
        'default_timezone' => ['label' => 'Default Timezone', 'type' => 'text', 'default' => 'Asia/Kolkata', 'help' => 'e.g., Asia/Kolkata, America/New_York. <a href="https://www.php.net/manual/en/timezones.php" target="_blank" rel="noopener noreferrer">PHP Supported Timezones</a>.', 'category' => 'general', 'icon' => 'fa-clock'],
        'footer_copyright_text' => ['label' => 'Footer Copyright Text', 'type' => 'text', 'default' => '© {year} Geetanjali - The Literary Society, NIFTEM-Kundli. All rights reserved.', 'category' => 'general', 'icon' => 'fa-copyright', 'help' => 'Use {year} to dynamically insert the current year.'],
        
        // Feature Flags
        'allow_new_registrations' => ['label' => 'Allow New User Registrations', 'type' => 'toggle', 'default' => '1', 'category' => 'features', 'icon' => 'fa-user-plus', 'group' => 'User Management'],
        'public_profiles_enabled' => ['label' => 'Enable Public Student Profiles', 'type' => 'toggle', 'default' => '1', 'category' => 'features', 'icon' => 'fa-address-card', 'help' => 'Allows public_profile.php to be accessible if user also sets their profile to public and is verified.', 'group' => 'User Management'],
        'require_admin_approval_for_new_users' => ['label' => 'Require Admin Approval for New Users', 'type' => 'toggle', 'default' => '0', 'category' => 'features', 'icon' => 'fa-user-shield', 'help' => 'If enabled, new users (role: student) will have account_status "pending_verification" and require Admin action to become "active". If disabled, they might become "active" directly if "is_verified" is also handled.', 'group' => 'User Management'],
        
        'allow_blog_comments' => ['label' => 'Allow Blog Comments Globally', 'type' => 'toggle', 'default' => '1', 'category' => 'features', 'icon' => 'fa-comments', 'group' => 'Content Features'],
        'moderate_blog_comments' => ['label' => 'Moderate Blog Comments Before Publishing', 'type' => 'toggle', 'default' => '1', 'category' => 'features', 'icon' => 'fa-comment-check', 'help' => 'If enabled, new comments require admin approval. (Requires backend logic in comment submission)', 'group' => 'Content Features'],
        'allow_event_registrations' => ['label' => 'Allow Event Registrations Globally', 'type' => 'toggle', 'default' => '1', 'category' => 'features', 'icon' => 'fa-calendar-check', 'group' => 'Content Features'],
        'allow_student_submissions' => ['label' => 'Allow Student Creative Submissions', 'type' => 'toggle', 'default' => '1', 'category' => 'features', 'icon' => 'fa-feather-alt', 'group' => 'Content Features'],
        
        // Maintenance
        'maintenance_mode' => ['label' => 'Enable Site Maintenance Mode', 'type' => 'toggle', 'default' => '0', 'help' => 'If enabled, public site will show a maintenance message. Admins can still access.', 'category' => 'maintenance', 'icon' => 'fa-tools', 'confirm' => 'Are you sure you want to toggle maintenance mode?'],
        'maintenance_mode_message' => ['label' => 'Maintenance Mode Message', 'type' => 'textarea', 'default' => 'Our website is currently undergoing scheduled maintenance. We should be back shortly. Thank you for your patience.', 'category' => 'maintenance', 'icon' => 'fa-comment-dots'],

        // Social Media Links
        'social_facebook_url' => ['label' => 'Official Facebook Page URL', 'type' => 'url', 'default' => '', 'category' => 'social', 'icon' => 'fa-facebook-square'],
        'social_instagram_url' => ['label' => 'Official Instagram Profile URL', 'type' => 'url', 'default' => '', 'category' => 'social', 'icon' => 'fa-instagram-square'],
        'social_linkedin_url' => ['label' => 'Official LinkedIn Page URL', 'type' => 'url', 'default' => '', 'category' => 'social', 'icon' => 'fa-linkedin'],
        'social_twitter_url' => ['label' => 'Official Twitter/X Profile URL', 'type' => 'url', 'default' => '', 'category' => 'social', 'icon' => 'fa-twitter-square'],
        
        // Security Settings
        'session_timeout_minutes' => ['label' => 'Session Timeout (minutes)', 'type' => 'number', 'default' => '30', 'category' => 'security', 'icon' => 'fa-user-clock', 'help' => 'Duration of inactivity before a user session expires. Requires server-side session handling update. Min: 5.', 'group' => 'Session & Login'],
        'enable_strong_passwords' => ['label' => 'Enforce Strong Passwords (Conceptual)', 'type' => 'toggle', 'default' => '0', 'category' => 'security', 'icon' => 'fa-key', 'help' => 'Requires passwords to meet complexity criteria (e.g., uppercase, number, symbol). Backend logic needed during registration/password change.', 'group' => 'Password Policy'],
        'max_login_attempts' => ['label' => 'Max Login Attempts Before Lockout', 'type' => 'number', 'default' => '5', 'category' => 'security', 'icon' => 'fa-user-lock', 'help' => 'Number of failed login attempts before temporarily locking an account. Backend logic needed. Min: 3.', 'group' => 'Session & Login'],

        // Theme & Appearance
        'site_logo_path_setting' => ['label' => 'Site Logo Path', 'type' => 'text', 'default' => 'assets/images/geetanjali.jpg', 'category' => 'theme', 'icon' => 'fa-image', 'help' => 'Relative path from BASE_URL to the main site logo. Actual upload mechanism would be separate.'],
        'primary_color_scheme_setting' => ['label' => 'Primary Theme Color', 'type' => 'color', 'default' => '#0a2342', 'category' => 'theme', 'icon' => 'fa-palette', 'help' => 'Select the main color for the theme. Requires dynamic CSS integration.'],
        'font_selection_setting' => ['label' => 'Font Pairing (Conceptual)', 'type' => 'select', 'options' => ['lato_playfair' => 'Lato & Playfair Display', 'roboto_merriweather' => 'Roboto & Merriweather', 'opensans_montserrat' => 'Open Sans & Montserrat'], 'default' => 'lato_playfair', 'category' => 'theme', 'icon' => 'fa-font', 'help' => 'Select primary and secondary fonts. Requires dynamic CSS integration.'],

        // User Roles & Permissions
        'default_new_user_role' => ['label' => 'Default Role for New Users', 'type' => 'select', 'options_callback' => 'getDefinedRoles', 'default' => 'student', 'category' => 'permissions', 'icon' => 'fa-user-tag', 'help' => 'Role assigned upon successful registration. Initial account status is "pending_verification" if admin approval is required (see Feature Flags).'],
        'allow_students_to_view_other_profiles' => ['label' => 'Students Can View Other Public Profiles', 'type' => 'toggle', 'default' => '1', 'category' => 'permissions', 'icon' => 'fa-users-viewfinder', 'help' => 'Controls access to public_profile.php for logged-in students to view other verified, public profiles.'],
        
        // Email Configuration
        'from_email_address' => ['label' => 'Default "From" Email Address', 'type' => 'email', 'default' => 'noreply@geetanjali-niftem.com', 'category' => 'email_config', 'icon' => 'fa-paper-plane', 'group' => 'Sender Details'],
        'from_email_name' => ['label' => 'Default "From" Name', 'type' => 'text', 'default' => 'Geetanjali Literary Society', 'category' => 'email_config', 'icon' => 'fa-user-circle', 'group' => 'Sender Details'],
        'smtp_host' => ['label' => 'SMTP Host', 'type' => 'text', 'default' => '', 'category' => 'email_config', 'icon' => 'fa-server', 'help' => 'For sending emails via SMTP.', 'group' => 'SMTP Configuration'],
        'smtp_port' => ['label' => 'SMTP Port', 'type' => 'number', 'default' => '587', 'category' => 'email_config', 'icon' => 'fa-ethernet', 'help' => 'Common ports: 25, 465 (SSL), 587 (TLS). Min: 0.', 'group' => 'SMTP Configuration'],
        'smtp_username' => ['label' => 'SMTP Username', 'type' => 'text', 'default' => '', 'category' => 'email_config', 'icon' => 'fa-user-secret', 'group' => 'SMTP Configuration'],
        'smtp_password' => ['label' => 'SMTP Password', 'type' => 'password_viewable', 'default' => '', 'category' => 'email_config', 'icon' => 'fa-key', 'group' => 'SMTP Configuration'], 
        'smtp_encryption' => ['label' => 'SMTP Encryption', 'type' => 'select', 'options' => ['none' => 'None', 'ssl' => 'SSL', 'tls' => 'TLS'], 'default' => 'tls', 'category' => 'email_config', 'icon' => 'fa-lock', 'group' => 'SMTP Configuration'],

        // SEO & Indexing
        'site_meta_description' => ['label' => 'Default Meta Description', 'type' => 'textarea', 'default' => 'Geetanjali - The Literary Society of NIFTEM-K. Fostering creativity, expression, and the power of words.', 'category' => 'seo', 'icon' => 'fa-file-alt'],
        'site_meta_keywords' => ['label' => 'Default Meta Keywords (comma-separated)', 'type' => 'text', 'default' => 'Geetanjali, NIFTEM, Literary Society, Literature, Events, Blog, Kundli', 'category' => 'seo', 'icon' => 'fa-tags'],
        'allow_search_engine_indexing' => ['label' => 'Allow Search Engine Indexing', 'type' => 'toggle', 'default' => '1', 'category' => 'seo', 'icon' => 'fa-robot', 'help' => 'If disabled, adds a "noindex, nofollow" meta tag to pages.'],

        // Upload Settings
        'max_profile_picture_size_kb' => ['label' => 'Max Profile Picture Size (KB)', 'type' => 'number', 'default' => '2048', 'category' => 'uploads', 'icon' => 'fa-image', 'help' => 'Maximum allowed file size for profile pictures in kilobytes (e.g., 2048 for 2MB). Min: 100.'],
        'max_submission_file_size_kb' => ['label' => 'Max Submission File Size (KB) (Conceptual)', 'type' => 'number', 'default' => '5120', 'category' => 'uploads', 'icon' => 'fa-file-upload', 'help' => 'Max size for creative submissions. Min: 500.'],

        // Advanced Settings (Integrations & Debug)
        'api_key_Maps' => ['label' => 'Google Maps API Key (Conceptual)', 'type' => 'text', 'default' => '', 'category' => 'advanced', 'icon' => 'fa-map-marked-alt', 'help' => 'For embedding Google Maps, if used.', 'group' => 'Integrations'],
        'enable_api_access' => ['label' => 'Enable Site API (Conceptual)', 'type' => 'toggle', 'default' => '0', 'category' => 'advanced', 'icon' => 'fa-code', 'help' => 'Enables external API access to site data. Requires API development.', 'group' => 'Integrations'],
        'debug_mode' => ['label' => 'Enable Debug Mode', 'type' => 'toggle', 'default' => '0', 'category' => 'advanced', 'icon' => 'fa-bug', 'help' => 'Shows detailed error messages. Only for development use. DO NOT enable on a live site.', 'group' => 'Development', 'confirm' => 'Enabling debug mode on a live site can expose sensitive information. Are you sure?'],
    ];

    $categories = [
        'general' => ['label' => 'General', 'icon' => 'fa-sitemap'],
        'features' => ['label' => 'Features', 'icon' => 'fa-toggle-on'],
        'maintenance' => ['label' => 'Maintenance', 'icon' => 'fa-tools'],
        'social' => ['label' => 'Social Media', 'icon' => 'fa-share-alt'],
        'security' => ['label' => 'Security', 'icon' => 'fa-shield-alt'],
        'theme' => ['label' => 'Theme & Appearance', 'icon' => 'fa-paint-brush'],
        'permissions' => ['label' => 'User Roles & Permissions', 'icon' => 'fa-users-cog'],
        'email_config' => ['label' => 'Email Configuration', 'icon' => 'fa-at'],
        'seo' => ['label' => 'SEO & Indexing', 'icon' => 'fa-search-dollar'],
        'uploads' => ['label' => 'Upload Settings', 'icon' => 'fa-file-upload'],
        'advanced' => ['label' => 'Advanced & Integrations', 'icon' => 'fa-cogs']
    ];

    // Handle settings update (POST logic remains largely the same as previous version,
    // ensuring it processes $setting_keys and uses updateSetting() and logAudit())
    // ... (Full POST handling for save_settings, clear_cache, reset_category_settings as in previous complete version) ...
    // The POST logic should iterate through $setting_keys IF $details['category'] === $active_category_on_save
    // to save only settings on the current tab.

    // --- Full POST Handling (condensed from previous for brevity, ensure all parts are present in your file) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $active_category_on_post = isset($_POST['active_category']) ? $_POST['active_category'] : array_key_first($categories);

        if (isset($_POST['save_settings'])) {
            $all_settings_saved_for_tab = true;
            $settings_saved_count = 0;
            $changes_logged = [];

            foreach ($setting_keys as $key => $details) {
                if ($details['category'] === $active_category_on_post) {
                    $current_db_value = getSetting($conn, $key, $details['default']);
                    $value_to_save = $current_db_value; 

                    if ($details['type'] === 'toggle') {
                        $value_to_save = isset($_POST[$key]) ? '1' : '0';
                    } elseif (array_key_exists($key, $_POST)) {
                        $submitted_value = trim($_POST[$key]);
                        $is_valid = true;
                        // Basic Validations (add more as needed)
                        if (($details['type'] === 'email' && !empty($submitted_value) && !filter_var($submitted_value, FILTER_VALIDATE_EMAIL)) ||
                            ($details['type'] === 'url' && !empty($submitted_value) && !filter_var($submitted_value, FILTER_VALIDATE_URL))) {
                            $message .= "<div class='form-message error'>Invalid format for " . htmlspecialchars($details['label']) . ". Not saved.</div>";
                            $all_settings_saved_for_tab = false; $is_valid = false;
                        }
                        if ($details['type'] === 'number') {
                            $min_range = 1; // Default min range
                            if (in_array($key, ['smtp_port', 'session_timeout_minutes', 'max_login_attempts', 'max_profile_picture_size_kb', 'max_submission_file_size_kb'])) $min_range = 0;
                            if ($key === 'session_timeout_minutes') $min_range = 5; 
                            if ($key === 'max_login_attempts') $min_range = 3;
                            if (in_array($key,['max_profile_picture_size_kb', 'max_submission_file_size_kb'])) $min_range = 100;


                            if (!empty($submitted_value) && !filter_var($submitted_value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min_range ]])) {
                                 $message .= "<div class='form-message error'>Invalid number for " . htmlspecialchars($details['label']) . ". Min: $min_range. Not saved.</div>";
                                $all_settings_saved_for_tab = false; $is_valid = false;
                            }
                        }
                        if ($is_valid) { $value_to_save = $submitted_value; }
                    } else { continue; } // Skip if not in POST and not a toggle
                    
                    if ($value_to_save !== $current_db_value) { // Only update if value changed
                        if (updateSetting($conn, $key, $value_to_save)) {
                            $settings_saved_count++;
                            $changes_logged[] = htmlspecialchars($details['label']) . " changed";
                        } else {
                            $all_settings_saved_for_tab = false;
                            $message .= "<div class='form-message error'>Failed to save: " . htmlspecialchars($details['label']) . "</div>";
                        }
                    }
                }
            }
            // Set session messages for redirect
            if ($all_settings_saved_for_tab && $settings_saved_count > 0) {
                logAudit($conn, $_SESSION['user_id'], "Updated settings: " . $categories[$active_category_on_post]['label'], "settings", null, implode("; ", $changes_logged));
                $_SESSION['success_message_settings'] = "Settings for '" . sanitizeOutput($categories[$active_category_on_post]['label']) . "' updated.";
            } elseif (!$all_settings_saved_for_tab) {
                 $_SESSION['error_message_settings'] = "Some settings in '" . sanitizeOutput($categories[$active_category_on_post]['label']) . "' had issues. " . strip_tags($message);
            } elseif ($settings_saved_count === 0 && empty($message)) {
                $_SESSION['info_message_settings'] = "No changes detected for '" . sanitizeOutput($categories[$active_category_on_post]['label']) . "'.";
            }
            header("Location: " . BASE_URL . "super_admin/settings.php?tab=" . $active_category_on_post); exit;

        } elseif (isset($_POST['clear_cache'])) {
            logAudit($conn, $_SESSION['user_id'], "Attempted to clear site cache");
            $_SESSION['info_message_settings'] = "Cache clearing process initiated (conceptual).";
            header("Location: " . BASE_URL . "super_admin/settings.php?tab=" . $active_category_on_post); exit;

        } elseif (isset($_POST['reset_category_settings'])) {
            $category_to_reset = isset($_POST['category_to_reset']) ? $_POST['category_to_reset'] : null;
            if ($category_to_reset && array_key_exists($category_to_reset, $categories)) {
                $reset_count = 0; $failed_resets = 0;
                foreach($setting_keys as $key => $details) {
                    if ($details['category'] === $category_to_reset) {
                        if (updateSetting($conn, $key, $details['default'])) { $reset_count++; } 
                        else { error_log("Failed to reset setting $key to default."); $failed_resets++; }
                    }
                }
                logAudit($conn, $_SESSION['user_id'], "Reset settings to default for category: " . $categories[$category_to_reset]['label']);
                if ($failed_resets > 0) { $_SESSION['error_message_settings'] = "Could not reset all settings in '" . sanitizeOutput($categories[$category_to_reset]['label']) . "'. $reset_count settings were reset."; }
                else { $_SESSION['success_message_settings'] = "Settings for '" . sanitizeOutput($categories[$category_to_reset]['label']) . "' reset to defaults ($reset_count settings affected)."; }
            } else { $_SESSION['error_message_settings'] = "Invalid category specified for reset."; }
            header("Location: " . BASE_URL . "super_admin/settings.php?tab=" . ($category_to_reset ?: array_key_first($categories))); exit;
        }
    }
    // --- End POST Handling ---


    // Display messages from session after redirect
    if (isset($_SESSION['success_message_settings'])) {
        $message .= "<div class='form-message success'>" . sanitizeOutput($_SESSION['success_message_settings']) . "</div>";
        unset($_SESSION['success_message_settings']);
    }
    if (isset($_SESSION['error_message_settings'])) {
        $message .= "<div class='form-message error'>" . sanitizeOutput($_SESSION['error_message_settings']) . "</div>";
        unset($_SESSION['error_message_settings']);
    }
    if (isset($_SESSION['info_message_settings'])) {
        $message .= "<div class='form-message info'>" . sanitizeOutput($_SESSION['info_message_settings']) . "</div>";
        unset($_SESSION['info_message_settings']);
    }
    if (isset($_SESSION['warning_message_settings'])) {
        $message .= "<div class='form-message warning'>" . sanitizeOutput($_SESSION['warning_message_settings']) . "</div>";
        unset($_SESSION['warning_message_settings']);
    }

    // Fetch current settings for display
    $current_settings = [];
    foreach ($setting_keys as $key => $details) {
        $current_settings[$key] = getSetting($conn, $key, $details['default']);
    }
    
    $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $categories) ? sanitizeOutput($_GET['tab']) : array_key_first($categories);

    include '../includes/header.php'; 
    ?>

    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-sliders-h"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Fine-tune every aspect of the Geetanjali website platform from a central control panel.</p>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message'>" . $message . "</div>"; ?>

            <section class="content-section std-profile-main-content admin-settings-container"> 
                <div class="settings-search-bar-container">
                    <input type="text" id="settingsSearchInput" class="form-control" placeholder="Search settings by label..." aria-label="Search settings">
                </div>

                <nav class="admin-tabs-nav">
                    <?php foreach ($categories as $cat_key => $cat_details): ?>
                        <a href="?tab=<?php echo $cat_key; ?>#settings-form" 
                           class="admin-tab-link <?php echo ($active_tab == $cat_key) ? 'active' : ''; ?>"
                           data-tab-key="<?php echo $cat_key; ?>">
                           <i class="fas <?php echo htmlspecialchars($cat_details['icon']); ?>"></i> <?php echo htmlspecialchars($cat_details['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <form action="<?php echo htmlspecialchars(BASE_URL . 'super_admin/settings.php'); ?>?tab=<?php echo $active_tab; ?>" method="POST" class="profile-form admin-settings-form" id="settings-form">
                    <input type="hidden" name="active_category" value="<?php echo $active_tab; ?>">
                    <?php 
                    $current_group = null;
                    foreach ($categories as $cat_key => $cat_details): ?>
                        <div class="settings-category-group <?php echo ($active_tab == $cat_key) ? 'active' : ''; ?>" id="category-<?php echo $cat_key; ?>" <?php if ($active_tab != $cat_key) echo 'style="display:none;"'; ?>>
                            <fieldset>
                                <legend class="fieldset-legend">
                                    <span><i class="fas <?php echo htmlspecialchars($cat_details['icon']); ?>"></i> <?php echo htmlspecialchars($cat_details['label']); ?></span>
                                    <button type="submit" name="reset_category_settings" formnovalidate value="<?php echo $cat_key; ?>" class="btn btn-sm btn-outline-danger reset-category-btn" 
                                            onclick="return confirm('Are you sure you want to reset all settings in the \'<?php echo htmlspecialchars($cat_details['label']); ?>\' category to their defaults? This action cannot be undone.');"
                                            title="Reset settings in this section to their default values">
                                        <i class="fas fa-undo-alt"></i> Reset This Section
                                    </button>
                                </legend>
                                <?php $has_settings_in_category = false; ?>
                                <?php foreach ($setting_keys as $key => $details): ?>
                                    <?php if ($details['category'] === $cat_key): 
                                        $has_settings_in_category = true; 
                                        // Check for subgroup
                                        if (isset($details['group']) && $details['group'] !== $current_group) {
                                            if ($current_group !== null) echo "</div>"; // Close previous group wrapper
                                            echo "<div class='setting-subgroup'><h4 class='subgroup-title'>" . htmlspecialchars($details['group']) . "</h4>";
                                            $current_group = $details['group'];
                                        } elseif (!isset($details['group']) && $current_group !== null) {
                                            echo "</div>"; // Close previous group if current setting doesn't have one
                                            $current_group = null;
                                        }
                                    ?>
                                        <div class="form-group <?php echo ($details['type'] === 'toggle') ? 'form-check-toggle' : ''; ?> <?php echo ($details['type'] === 'password_viewable') ? 'form-group-password-viewable' : ''; ?>" data-setting-label="<?php echo strtolower(htmlspecialchars($details['label'])); ?>">
                                            <label for="<?php echo $key; ?>">
                                                <?php if(isset($details['icon'])): ?><i class="fas <?php echo htmlspecialchars($details['icon']); ?> setting-label-icon"></i> <?php endif; ?>
                                                <?php echo htmlspecialchars($details['label']); ?>
                                            </label>
                                            
                                            <?php if ($details['type'] === 'toggle'): ?>
                                                <label class="switch" <?php if(isset($details['confirm'])) echo 'onclick="return confirm(\''.htmlspecialchars($details['confirm']).'\');"'; ?>>
                                                    <input type="checkbox" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="1" <?php echo ($current_settings[$key] == '1') ? 'checked' : ''; ?>>
                                                    <span class="slider round"></span>
                                                </label>
                                            <?php elseif ($details['type'] === 'textarea'): ?>
                                                <textarea id="<?php echo $key; ?>" name="<?php echo $key; ?>" class="form-control" rows="3"><?php echo htmlspecialchars($current_settings[$key]); ?></textarea>
                                            <?php elseif ($details['type'] === 'password_viewable'): ?>
                                                <div class="input-group">
                                                    <input type="password" id="<?php echo $key; ?>" name="<?php echo $key; ?>" 
                                                           value="<?php echo htmlspecialchars($current_settings[$key]); ?>" class="form-control" autocomplete="new-password">
                                                    <button type="button" class="btn btn-outline-secondary view-password-btn" aria-label="Show/Hide Password" title="Show/Hide Password">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            <?php elseif ($details['type'] === 'select'): 
                                                $options_array = [];
                                                if(isset($details['options_callback']) && function_exists($details['options_callback'])) {
                                                    $options_array = call_user_func($details['options_callback'], $conn); // Pass $conn
                                                } elseif (isset($details['options'])) {
                                                    $options_array = $details['options'];
                                                }
                                            ?>
                                                <select id="<?php echo $key; ?>" name="<?php echo $key; ?>" class="form-control">
                                                    <?php foreach ($options_array as $opt_val => $opt_label_or_val): 
                                                        $option_value = isset($details['options_callback']) ? $opt_label_or_val : $opt_val;
                                                        $option_label = isset($details['options_callback']) ? ucfirst(str_replace('_', ' ', $opt_label_or_val)) : $opt_label_or_val;
                                                    ?>
                                                        <option value="<?php echo htmlspecialchars($option_value); ?>" <?php echo ($current_settings[$key] == $option_value) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($option_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ($details['type'] === 'color'): ?>
                                                 <div class="color-picker-group">
                                                    <input type="color" id="<?php echo $key; ?>" name="<?php echo $key; ?>" 
                                                          value="<?php echo htmlspecialchars($current_settings[$key]); ?>" class="form-control form-control-color">
                                                    <span class="color-value-display"><?php echo htmlspecialchars($current_settings[$key]); ?></span>
                                                 </div>
                                            <?php else: // Default to text, number, email, url ?>
                                                <input type="<?php echo htmlspecialchars($details['type']); ?>" id="<?php echo $key; ?>" name="<?php echo $key; ?>" 
                                                       value="<?php echo htmlspecialchars($current_settings[$key]); ?>" class="form-control"
                                                       <?php if($details['type'] === 'number') {
                                                           $min_val = 1; 
                                                           if (in_array($key, ['smtp_port', 'session_timeout_minutes', 'max_login_attempts', 'max_profile_picture_size_kb', 'max_submission_file_size_kb'])) $min_val = 0;
                                                           if ($key === 'session_timeout_minutes') $min_val = 5;
                                                           if ($key === 'max_login_attempts') $min_val = 3;
                                                           if (in_array($key,['max_profile_picture_size_kb', 'max_submission_file_size_kb'])) $min_val = 100;
                                                           echo 'min="'.$min_val.'"';
                                                       } ?>
                                                       <?php if($details['type'] === 'url') echo 'placeholder="https://example.com"'; ?> >
                                            <?php endif; ?>

                                            <?php if (isset($details['help'])): ?>
                                                <small class="form-text text-muted"><?php echo $details['help']; // Allow HTML in help text for links ?></small>
                                            <?php endif; ?>
                                            <?php // Conceptual: Display last updated timestamp for individual setting
                                                // $setting_updated_at = getSettingUpdatedAt($conn, $key); // You'd need this function
                                                // if ($setting_updated_at) echo "<small class='form-text text-info'>Last updated: " . time_elapsed_string($setting_updated_at) . "</small>";
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($current_group !== null) echo "</div>"; // Close last subgroup wrapper ?>
                                <?php if (!$has_settings_in_category): ?>
                                    <p>No settings available in this category yet.</p>
                                <?php endif; ?>
                            </fieldset>
                        </div>
                    <?php $current_group = null; /* Reset for next category */ endforeach; ?>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_settings" class="cta-button cta-button-lg"><i class="fas fa-save"></i> Save Settings for This Tab</button>
                    </div>
                </form>
            </section>

            <section class="content-section std-profile-main-content alt-background">
                 <h2 class="section-title"><i class="fas fa-broom"></i> Cache Management</h2>
                 <p>If you are experiencing issues with outdated content, clearing the site cache might help. This is a conceptual action for demonstration and would require specific backend implementation (e.g., clearing OPcache, file caches, or other caching systems).</p>
                 <form action="<?php echo htmlspecialchars(BASE_URL . 'super_admin/settings.php'); ?>?tab=<?php echo $active_tab; ?>" method="POST">
                     <input type="hidden" name="active_category" value="<?php echo $active_tab; ?>">
                     <button type="submit" name="clear_cache" class="cta-button btn-info"><i class="fas fa-sync-alt"></i> Clear Site Cache (Simulated)</button>
                 </form>
            </section>

        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab Functionality
            const tabLinks = document.querySelectorAll('.admin-tabs-nav .admin-tab-link');
            const settingGroups = document.querySelectorAll('.settings-category-group');
            const form = document.getElementById('settings-form');
            const activeCategoryInput = form ? form.querySelector('input[name="active_category"]') : null;
            const settingsSearchInput = document.getElementById('settingsSearchInput');

            function showTab(targetTabKey, skipHistoryUpdate = false) {
                const targetTabId = 'category-' + targetTabKey;
                let foundActiveTab = false;

                settingGroups.forEach(group => {
                    if (group.id === targetTabId) {
                        group.style.display = 'block';
                        group.classList.add('active'); 
                        foundActiveTab = true;
                    } else {
                        group.style.display = 'none';
                        group.classList.remove('active');
                    }
                });

                tabLinks.forEach(link => {
                    if (link.dataset.tabKey === targetTabKey) { 
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });

                if(activeCategoryInput) {
                    activeCategoryInput.value = targetTabKey;
                }
                if (form) { 
                    form.action = '<?php echo htmlspecialchars(BASE_URL . 'super_admin/settings.php'); ?>?tab=' + targetTabKey;
                }
                
                if (!skipHistoryUpdate) {
                     history.pushState({tab: targetTabKey}, null, '?tab=' + targetTabKey + '#settings-form');
                }
                // Reset search when tab changes
                if (settingsSearchInput) settingsSearchInput.value = '';
                filterSettings(''); 
            }

            const initialTabKeyFromPHP = '<?php echo $active_tab; ?>';
            showTab(initialTabKeyFromPHP, true); // Skip history update on initial load

            tabLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault(); 
                    const targetTabKey = this.dataset.tabKey; 
                    if (targetTabKey) { showTab(targetTabKey); }
                });
            });
            
            window.addEventListener('popstate', function(event) {
                if (event.state && event.state.tab) {
                    showTab(event.state.tab, true);
                } else {
                    // Fallback if no state, could be from initial load with hash
                    const hashTab = window.location.search.split('tab=')[1]?.split('&')[0] || '<?php echo array_key_first($categories); ?>';
                    showTab(hashTab, true);
                }
            });


            // Password view toggle
            document.querySelectorAll('.view-password-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const inputGroup = this.closest('.input-group');
                    if (!inputGroup) return;
                    const input = inputGroup.querySelector('input'); 
                    if (!input) return;
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
                    }
                });
            });

            // Color picker value display
            document.querySelectorAll('input[type="color"].form-control-color').forEach(colorInput => {
                const displaySpan = colorInput.parentElement.querySelector('.color-value-display');
                if (displaySpan) {
                    colorInput.addEventListener('input', function() {
                        displaySpan.textContent = this.value;
                    });
                }
            });
            
            // Settings Search Functionality
            if (settingsSearchInput) {
                settingsSearchInput.addEventListener('keyup', function() {
                    filterSettings(this.value.toLowerCase());
                });
            }

            function filterSettings(searchTerm) {
                const activeCategoryGroup = document.querySelector('.settings-category-group.active');
                if (!activeCategoryGroup) return;

                const settingsItems = activeCategoryGroup.querySelectorAll('.form-group');
                settingsItems.forEach(item => {
                    const label = item.querySelector('label');
                    const helpText = item.querySelector('.form-text');
                    let matches = false;
                    if (label && label.textContent.toLowerCase().includes(searchTerm)) {
                        matches = true;
                    }
                    if (helpText && helpText.textContent.toLowerCase().includes(searchTerm)) {
                        matches = true;
                    }
                    item.style.display = matches ? '' : 'none';
                });
                 // Show/hide subgroup titles based on visible items
                const subgroups = activeCategoryGroup.querySelectorAll('.setting-subgroup');
                subgroups.forEach(subgroup => {
                    const visibleItemsInSubgroup = subgroup.querySelectorAll('.form-group[style*="display: block"], .form-group:not([style*="display: none"])'); // Check for items not hidden
                    subgroup.style.display = visibleItemsInSubgroup.length > 0 ? '' : 'none';
                });
            }

        });
    </script>
    <?php include '../includes/footer.php'; ?>
    