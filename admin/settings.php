    <?php
    // geetanjali_website/admin/settings.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; 
    require_once '../includes/functions.php';     

    // Enforce Admin or Super Admin access
    enforceRoleAccess(['admin', 'super_admin']);

    $pageTitle = "Website Settings";
    $message = ""; 

    // Define settings keys manageable by a regular Admin
    // This is a SUBSET of the settings available to a Super Admin
    $admin_setting_keys = [
        'items_per_page' => [
            'label' => 'Items Per Page (for lists)', 
            'type' => 'number', 
            'default' => 10, 
            'category' => 'content', 
            'icon' => 'fa-list-ol',
            'help' => 'Default number of items in paginated views (e.g., blog, events). Min: 1.'
        ],
        'footer_copyright_text' => [
            'label' => 'Footer Copyright Text', 
            'type' => 'text', 
            'default' => '© {year} Geetanjali - The Literary Society, NIFTEM-Kundli. All rights reserved.', 
            'category' => 'content', 
            'icon' => 'fa-copyright',
            'help' => 'Use {year} to dynamically insert the current year.'
        ],
        'allow_blog_comments' => [ // Example: Admins might toggle this if they manage the blog
            'label' => 'Allow Blog Comments Globally', 
            'type' => 'toggle', 
            'default' => '1', 
            'category' => 'content', 
            'icon' => 'fa-comments',
            'help' => 'Enable or disable comments on all blog posts.'
        ],
        'social_facebook_url' => [
            'label' => 'Official Facebook Page URL', 
            'type' => 'url', 
            'default' => '', 
            'category' => 'social', 
            'icon' => 'fa-facebook-square'
        ],
        'social_instagram_url' => [
            'label' => 'Official Instagram Profile URL', 
            'type' => 'url', 
            'default' => '', 
            'category' => 'social', 
            'icon' => 'fa-instagram-square'
        ],
        'social_linkedin_url' => [
            'label' => 'Official LinkedIn Page URL', 
            'type' => 'url', 
            'default' => '', 
            'category' => 'social', 
            'icon' => 'fa-linkedin'
        ],
        'social_twitter_url' => [
            'label' => 'Official Twitter/X Profile URL', 
            'type' => 'url', 
            'default' => '', 
            'category' => 'social', 
            'icon' => 'fa-twitter-square'
        ],
    ];

    // Categories for Admin settings page
    $admin_categories = [
        'content' => ['label' => 'Content & Display', 'icon' => 'fa-file-alt'],
        'social' => ['label' => 'Social Media Links', 'icon' => 'fa-share-alt'],
    ];

    // Handle settings update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $all_settings_saved_for_tab = true;
        $active_category_on_save = isset($_POST['active_category']) && array_key_exists($_POST['active_category'], $admin_categories) 
                                   ? $_POST['active_category'] 
                                   : array_key_first($admin_categories);
        $settings_saved_count = 0;
        $changes_logged = [];

        foreach ($admin_setting_keys as $key => $details) {
            if ($details['category'] === $active_category_on_save) {
                $current_db_value = getSetting($conn, $key, $details['default']);
                $value_to_save = $current_db_value; 

                if ($details['type'] === 'toggle') {
                    $value_to_save = isset($_POST[$key]) ? '1' : '0';
                } elseif (array_key_exists($key, $_POST)) {
                    $submitted_value = trim($_POST[$key]);
                    $is_valid = true;
                    
                    if ($details['type'] === 'email' && !empty($submitted_value) && !filter_var($submitted_value, FILTER_VALIDATE_EMAIL)) {
                        $message .= "<div class='form-message error'>Invalid email format for " . htmlspecialchars($details['label']) . ". Not saved.</div>";
                        $all_settings_saved_for_tab = false; $is_valid = false;
                    }
                    if ($details['type'] === 'url' && !empty($submitted_value) && !filter_var($submitted_value, FILTER_VALIDATE_URL)) {
                        $message .= "<div class='form-message error'>Invalid URL format for " . htmlspecialchars($details['label']) . ". Not saved.</div>";
                        $all_settings_saved_for_tab = false; $is_valid = false;
                    }
                    if ($details['type'] === 'number' && !empty($submitted_value) && !filter_var($submitted_value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                         $message .= "<div class='form-message error'>Invalid number for " . htmlspecialchars($details['label']) . ". Must be a positive integer. Not saved.</div>";
                        $all_settings_saved_for_tab = false; $is_valid = false;
                    }
                    if ($is_valid) { $value_to_save = $submitted_value; }
                } else { continue; }
                
                if ($value_to_save !== $current_db_value) {
                    if (updateSetting($conn, $key, $value_to_save)) {
                        $settings_saved_count++;
                        $changes_logged[] = htmlspecialchars($details['label']) . " changed";
                    } else {
                        $all_settings_saved_for_tab = false;
                        $message .= "<div class='form-message error'>Failed to save setting: " . htmlspecialchars($details['label']) . "</div>";
                        error_log("Failed to update setting by admin: $key");
                    }
                }
            }
        }

        $log_category_label = isset($admin_categories[$active_category_on_save]['label']) ? $admin_categories[$active_category_on_save]['label'] : 'Unknown';
        if ($all_settings_saved_for_tab && $settings_saved_count > 0) {
            logAudit($conn, $_SESSION['user_id'], "Admin updated settings: " . $log_category_label, "settings", null, implode("; ", $changes_logged));
            $_SESSION['success_message_admin_settings'] = "Settings for '" . sanitizeOutput($log_category_label) . "' updated successfully.";
        } elseif (!$all_settings_saved_for_tab) {
             $_SESSION['error_message_admin_settings'] = "Some settings in '" . sanitizeOutput($log_category_label) . "' tab had issues. " . strip_tags($message);
        } elseif ($settings_saved_count === 0 && empty($message)) {
            $_SESSION['info_message_admin_settings'] = "No changes detected for settings in the '" . sanitizeOutput($log_category_label) . "' tab.";
        }
         
        if (!empty($active_category_on_save)) {
            header("Location: " . BASE_URL . "admin/settings.php?tab=" . $active_category_on_save);
            exit;
        }
    }
    
    // Display messages from session after redirect
    if (isset($_SESSION['success_message_admin_settings'])) {
        $message .= "<div class='form-message success'>" . sanitizeOutput($_SESSION['success_message_admin_settings']) . "</div>";
        unset($_SESSION['success_message_admin_settings']);
    }
    if (isset($_SESSION['error_message_admin_settings'])) {
        $message .= "<div class='form-message error'>" . sanitizeOutput($_SESSION['error_message_admin_settings']) . "</div>";
        unset($_SESSION['error_message_admin_settings']);
    }
    if (isset($_SESSION['info_message_admin_settings'])) {
        $message .= "<div class='form-message info'>" . sanitizeOutput($_SESSION['info_message_admin_settings']) . "</div>";
        unset($_SESSION['info_message_admin_settings']);
    }

    // Fetch current settings for display
    $current_settings = [];
    foreach ($admin_setting_keys as $key => $details) {
        $current_settings[$key] = getSetting($conn, $key, $details['default']);
    }
    
    $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $admin_categories) ? sanitizeOutput($_GET['tab']) : array_key_first($admin_categories);

    include '../includes/header.php'; 
    ?>

    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-cog"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Manage general website settings and public information.</p>
                <p><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a></p>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message'>" . $message . "</div>"; ?>

            <section class="content-section std-profile-main-content admin-settings-container"> 
                <nav class="admin-tabs-nav">
                    <?php foreach ($admin_categories as $cat_key => $cat_details): ?>
                        <a href="?tab=<?php echo $cat_key; ?>#settings-form" 
                           class="admin-tab-link <?php echo ($active_tab == $cat_key) ? 'active' : ''; ?>"
                           data-tab-key="<?php echo $cat_key; ?>">
                           <i class="fas <?php echo htmlspecialchars($cat_details['icon']); ?>"></i> <?php echo htmlspecialchars($cat_details['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <form action="<?php echo htmlspecialchars(BASE_URL . 'admin/settings.php'); ?>?tab=<?php echo $active_tab; ?>" method="POST" class="profile-form admin-settings-form" id="settings-form">
                    <input type="hidden" name="active_category" value="<?php echo $active_tab; ?>">
                    <?php 
                    $current_group = null; // For grouping settings within a fieldset
                    foreach ($admin_categories as $cat_key => $cat_details): ?>
                        <div class="settings-category-group <?php echo ($active_tab == $cat_key) ? 'active' : ''; ?>" id="category-<?php echo $cat_key; ?>" <?php if ($active_tab != $cat_key) echo 'style="display:none;"'; ?>>
                            <fieldset>
                                <legend class="fieldset-legend">
                                    <span><i class="fas <?php echo htmlspecialchars($cat_details['icon']); ?>"></i> <?php echo htmlspecialchars($cat_details['label']); ?></span>
                                    <?php /* Reset button can be added if admins should have this power for their subset of settings 
                                    <button type="submit" name="reset_category_settings" formnovalidate value="<?php echo $cat_key; ?>" class="btn btn-sm btn-outline-danger reset-category-btn" 
                                            onclick="return confirm('Are you sure you want to reset settings in this category to their defaults?');"
                                            title="Reset settings in this section to their default values">
                                        <i class="fas fa-undo-alt"></i> Reset Section
                                    </button>
                                    */ ?>
                                </legend>
                                <?php $has_settings_in_category = false; ?>
                                <?php foreach ($admin_setting_keys as $key => $details): ?>
                                    <?php if ($details['category'] === $cat_key): 
                                        $has_settings_in_category = true; 
                                        
                                        // Simple grouping based on 'group' key in $admin_setting_keys
                                        if (isset($details['group']) && $details['group'] !== $current_group) {
                                            if ($current_group !== null) echo "</div><hr class='setting-subgroup-divider'>"; // Close previous group wrapper
                                            echo "<div class='setting-subgroup'><h4 class='subgroup-title'>" . htmlspecialchars($details['group']) . "</h4>";
                                            $current_group = $details['group'];
                                        } elseif (!isset($details['group']) && $current_group !== null) {
                                            echo "</div><hr class='setting-subgroup-divider'>"; 
                                            $current_group = null;
                                        }
                                    ?>
                                        <div class="form-group <?php echo ($details['type'] === 'toggle') ? 'form-check-toggle' : ''; ?>">
                                            <label for="<?php echo $key; ?>">
                                                <?php if(isset($details['icon'])): ?><i class="fas <?php echo htmlspecialchars($details['icon']); ?> setting-label-icon"></i> <?php endif; ?>
                                                <?php echo htmlspecialchars($details['label']); ?>
                                            </label>
                                            
                                            <?php if ($details['type'] === 'toggle'): ?>
                                                <label class="switch">
                                                    <input type="checkbox" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="1" <?php echo ($current_settings[$key] == '1') ? 'checked' : ''; ?>>
                                                    <span class="slider round"></span>
                                                </label>
                                            <?php elseif ($details['type'] === 'textarea'): ?>
                                                <textarea id="<?php echo $key; ?>" name="<?php echo $key; ?>" class="form-control" rows="3"><?php echo htmlspecialchars($current_settings[$key]); ?></textarea>
                                            <?php else: // Default to text, number, email, url, color ?>
                                                <input type="<?php echo htmlspecialchars($details['type']); ?>" id="<?php echo $key; ?>" name="<?php echo $key; ?>" 
                                                       value="<?php echo htmlspecialchars($current_settings[$key]); ?>" class="form-control <?php if($details['type'] === 'color') echo 'form-control-color'; ?>"
                                                       <?php if($details['type'] === 'number') echo 'min="1"'; ?>
                                                       <?php if($details['type'] === 'url') echo 'placeholder="https://example.com"'; ?> >
                                            <?php endif; ?>

                                            <?php if (isset($details['help'])): ?>
                                                <small class="form-text text-muted"><?php echo $details['help']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($current_group !== null) echo "</div>"; // Close last subgroup wrapper ?>
                                <?php if (!$has_settings_in_category): ?>
                                    <p>No settings available in this category for your role.</p>
                                <?php endif; ?>
                            </fieldset>
                        </div>
                    <?php $current_group = null; endforeach; ?>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_settings" class="cta-button cta-button-lg"><i class="fas fa-save"></i> Save Settings for This Tab</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <script>
        // JavaScript for tab functionality (same as super_admin/settings.php)
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.admin-tabs-nav .admin-tab-link');
            const settingGroups = document.querySelectorAll('.settings-category-group');
            const form = document.getElementById('settings-form');
            const activeCategoryInput = form ? form.querySelector('input[name="active_category"]') : null;

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
                    activeCategoryInput.value = targetTabKey; // Should be targetTabKey
                }
                if (form) { 
                    form.action = '<?php echo htmlspecialchars(BASE_URL . 'admin/settings.php'); ?>?tab=' + targetTabKey;
                }
                
                if (!skipHistoryUpdate && window.history && window.history.pushState) { // Check if history API is available
                     history.pushState({tab: targetTabKey}, null, '?tab=' + targetTabKey + '#settings-form');
                }
            }

            const initialTabKeyFromPHP = '<?php echo $active_tab; ?>';
            showTab(initialTabKeyFromPHP, true); 

            tabLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault(); 
                    const targetTabKey = this.dataset.tabKey; 
                    if (targetTabKey) { showTab(targetTabKey); }
                });
            });
            
            window.addEventListener('popstate', function(event) {
                let tabToLoad = '<?php echo array_key_first($admin_categories); ?>'; // Default
                if (event.state && event.state.tab) {
                    tabToLoad = event.state.tab;
                } else {
                    const urlParams = new URLSearchParams(window.location.search);
                    const tabFromUrl = urlParams.get('tab');
                    if (tabFromUrl && <?php echo json_encode(array_keys($admin_categories)); ?>.includes(tabFromUrl)) {
                        tabToLoad = tabFromUrl;
                    }
                }
                showTab(tabToLoad, true); // true to skip history update as it's a popstate
            });
        });
    </script>
    <?php include '../includes/footer.php'; ?>
    