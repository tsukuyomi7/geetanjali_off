    <?php
    // geetanjali_website/admin/dashboard.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; // Defines $conn and BASE_URL
    require_once '../includes/functions.php';     // For enforceRoleAccess()

    // Enforce Admin or Super Admin access
    // Regular admins might have slightly different views/capabilities than super admins
    // but they can access this general admin dashboard.
    enforceRoleAccess(['admin', 'super_admin']);

    $pageTitle = "Admin Dashboard";
    // If you create a specific admin_style.css, you can add it:
    // $additional_css[] = '../css/admin_style.css'; 
    // For now, it will use the styles from style.css which includes .admin-area rules.

    // Fetch some relevant stats for the admin
    // These can be tailored to what an admin needs to see
    $total_pending_blog_posts_query = "SELECT COUNT(*) as total FROM blog_posts WHERE is_published = FALSE";
    $total_pending_blog_posts_result = mysqli_query($conn, $total_pending_blog_posts_query);
    $total_pending_blog_posts = ($total_pending_blog_posts_result) ? mysqli_fetch_assoc($total_pending_blog_posts_result)['total'] : 0;

    $total_active_events_query = "SELECT COUNT(*) as total FROM events WHERE date_time >= NOW()"; // Assuming 'date_time' for events
    $total_active_events_result = mysqli_query($conn, $total_active_events_query);
    $total_active_events = ($total_active_events_result) ? mysqli_fetch_assoc($total_active_events_result)['total'] : 0;
    
    $total_registered_users_query = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
    $total_registered_users_result = mysqli_query($conn, $total_registered_users_query);
    $total_students = ($total_registered_users_result) ? mysqli_fetch_assoc($total_registered_users_result)['total'] : 0;


    include '../includes/header.php'; // Use relative path for includes
    ?>

    <main id="page-content" class="admin-area">
        <div class="container">
            <section class="page-title-section">
                <h1><i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Manage website content and user activities.</p>
            </section>

            <section class="admin-stats-cards content-section">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3>Registered Students</h3>
                    <p><?php echo $total_students; ?></p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Active Events</h3>
                    <p><?php echo $total_active_events; ?></p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-hourglass-half"></i>
                    <h3>Pending Blog Posts</h3>
                    <p><?php echo $total_pending_blog_posts; ?></p>
                </div>
                 <div class="stat-card">
                    <i class="fas fa-image"></i>
                    <h3>Gallery Items</h3>
                    <?php
                        $gallery_count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM gallery");
                        $gallery_count = ($gallery_count_res) ? mysqli_fetch_assoc($gallery_count_res)['total'] : 0;
                    ?>
                    <p><?php echo $gallery_count; ?></p>
                </div>
            </section>

            <section class="admin-quick-links content-section">
                <h2>Core Management Areas</h2>
                <div class="quick-links-grid">
                    <a href="<?php echo BASE_URL; ?>admin/users.php" class="quick-link-item">
                        <i class="fas fa-user-friends"></i> View Users
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/events.php" class="quick-link-item">
                        <i class="fas fa-calendar-plus"></i> Manage Events
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/blogs.php" class="quick-link-item">
                        <i class="fas fa-blog"></i> Manage Blog Posts
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/gallery.php" class="quick-link-item">
                        <i class="fas fa-photo-video"></i> Manage Gallery
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/certificates.php" class="quick-link-item">
                        <i class="fas fa-award"></i> Manage Certificates
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/settings.php" class="quick-link-item">
                        <i class="fas fa-file-alt"></i> Website Content
                    </a>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'super_admin'): ?>
                        <a href="<?php echo BASE_URL; ?>super_admin/dashboard.php" class="quick-link-item" style="background-color: var(--accent-gold); color: var(--primary-color);">
                            <i class="fas fa-user-shield"></i> Go to Super Admin
                        </a>
                    <?php endif; ?>
                </div>
            </section>
            
            <section class="recent-activity content-section alt-background">
                <h2>Recent Admin Actions (Placeholder)</h2>
                <p>This section could display the last few entries from the audit log relevant to general admin actions or important system notifications.</p>
                <ul>
                    <?php
                    // Example: Fetch last 3 general admin actions (excluding super_admin specific ones if desired)
                    // $recent_actions_sql = "SELECT action, timestamp, target_type, target_id FROM audit_log ORDER BY timestamp DESC LIMIT 3";
                    // $recent_actions_result = mysqli_query($conn, $recent_actions_sql);
                    // if ($recent_actions_result && mysqli_num_rows($recent_actions_result) > 0) {
                    //     while($action_item = mysqli_fetch_assoc($recent_actions_result)) {
                    //         echo "<li>" . htmlspecialchars($action_item['action']) . " on " . date('M j, Y H:i', strtotime($action_item['timestamp'])) . "</li>";
                    //     }
                    // } else {
                    //     echo "<li>No recent administrative actions logged.</li>";
                    // }
                    ?>
                    <li>Admin User 'geetanjali_admin' approved a new blog post. (Example)</li>
                    <li>New event 'Poetry Night Vol. 3' created. (Example)</li>
                </ul>
            </section>

        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    