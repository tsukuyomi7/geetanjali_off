    <?php
    // geetanjali_website/public_profile.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start(); 
    }
    require_once 'includes/db_connection.php'; 
    require_once 'includes/functions.php';     

    if (!isset($conn) || !$conn) {
        error_log("FATAL: Database connection failed in public_profile.php.");
        die("A critical error occurred. Please try again later. (Error Code: PPDB01)");
    }
    if (!defined('BASE_URL')) {
        error_log("FATAL: BASE_URL is not defined in public_profile.php.");
        die("A critical configuration error occurred. (Error Code: PPBU01)");
    }
    if (!function_exists('sanitizeOutput')) {
        error_log("FATAL: sanitizeOutput() function not found in public_profile.php.");
        die("A critical site function is missing. (Error Code: PPFS01)");
    }

    $pageTitle = "Student Public Profile"; 
    $user_profile = null;
    $participated_events = [];
    $search_term_display = '';
    $search_message = ''; // Unified message variable

    // Handle search or direct ID access
    $profile_identifier = '';
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_profile'])) {
        $profile_identifier = trim($_POST['search_term']);
        $search_term_display = htmlspecialchars($profile_identifier);
        if (empty($profile_identifier)) {
            $search_message = "<div class='form-message warning'>Please enter a NIFTEM ID or Public Profile ID to search.</div>";
        }
    } elseif (isset($_GET['id'])) {
        $profile_identifier = trim($_GET['id']);
    }

    if (!empty($profile_identifier)) {
        // Search by unique_public_id first, then by niftem_id if it's not a typical UUID format
        $sql_get_profile = "SELECT id, name, niftem_id, department, profile_picture, registration_date, 
                                   bio, pronouns, interests_list, skills_list, 
                                   social_linkedin, social_twitter, social_portfolio,
                                   unique_public_id, profile_visibility 
                            FROM users 
                            WHERE (unique_public_id = ? OR niftem_id = ?) AND is_verified = TRUE";
        
        $stmt_get = $conn->prepare($sql_get_profile);
        if($stmt_get){
            $stmt_get->bind_param("ss", $profile_identifier, $profile_identifier);
            if($stmt_get->execute()){
                $result_get = $stmt_get->get_result();
                if ($result_get->num_rows === 1) {
                    $temp_profile = $result_get->fetch_assoc();
                    if ($temp_profile['profile_visibility'] == 'public') {
                        $user_profile = $temp_profile;
                        $pageTitle = sanitizeOutput($user_profile['name']) . "'s Public Profile";
                    } else {
                        $search_message = "<div class='form-message info'>This profile is set to private or members-only and cannot be viewed publicly.</div>";
                    }
                } else {
                     $search_message = "<div class='form-message error'>No verified profile found for the provided ID, or it does not exist.</div>";
                }
            } else {
                $search_message = "<div class='form-message error'>Error retrieving profile data. Please try again.</div>";
                error_log("Public profile GET execute failed: " . $stmt_get->error);
            }
            $stmt_get->close();
        } else {
            $search_message = "<div class='form-message error'>Error preparing to retrieve profile. Please try again.</div>";
            error_log("Public profile GET prepare failed: " . $conn->error . " SQL: " . $sql_get_profile);
        }
    }

    // If profile found and is public, fetch participated events
    if ($user_profile && isset($user_profile['id'])) {
        $sql_events = "SELECT e.id as event_id, e.title, e.date_time 
                       FROM events e
                       JOIN event_registrations er ON e.id = er.event_id
                       WHERE er.user_id = ? 
                       ORDER BY e.date_time DESC LIMIT 10"; // Limit to recent 10
        $stmt_events = $conn->prepare($sql_events);
        if($stmt_events){
            $stmt_events->bind_param("i", $user_profile['id']);
            if($stmt_events->execute()){
                $result_events = $stmt_events->get_result();
                while ($row = $result_events->fetch_assoc()) {
                    $participated_events[] = $row;
                }
            } else { error_log("Public profile: Events execute failed for user ID " . $user_profile['id'] . ": " . $stmt_events->error); }
            $stmt_events->close();
        } else { error_log("Public profile: Events prepare failed for user ID " . $user_profile['id'] . ": " . $conn->error); }
        
        $user_interests_display = (!empty($user_profile['interests_list']) && is_string($user_profile['interests_list'])) ? array_filter(array_map('trim', explode(',', $user_profile['interests_list']))) : [];
        $user_skills_display = (!empty($user_profile['skills_list']) && is_string($user_profile['skills_list'])) ? array_filter(array_map('trim', explode(',', $user_profile['skills_list']))) : [];
    }
    
    // Placeholder for public achievements/badges
    $public_badges = [];
    if ($user_profile) { // Only show if a profile is being displayed
        // In a real app, fetch public badges for $user_profile['id'] from a 'user_badges' table
        $public_badges = [
            ['icon' => 'fa-award', 'title' => 'Debate Champion \'24', 'description' => 'Winner of the Annual Inter-Departmental Debate.'],
            ['icon' => 'fa-feather-alt', 'title' => 'Poetry Laureate', 'description' => 'Recognized for outstanding contribution to poetry.'],
        ];
    }

    include 'includes/header.php'; 
    ?>

    <main id="page-content" class="public-profile-page-container">
        <div class="container">
            <section class="profile-search-section content-section text-center" data-aos="fade-down">
                <div class="search-card">
                    <h2><i class="fas fa-search-location"></i> Find a Student's Public Profile</h2>
                    <p>Enter a student's NIFTEM ID or their Unique Public Profile ID to view their shared information.</p>
                    <form action="<?php echo htmlspecialchars(BASE_URL . 'public_profile.php'); ?>" method="POST" class="profile-search-form">
                        <div class="form-group">
                            <input type="text" name="search_term" class="form-control form-control-lg" placeholder="Enter NIFTEM ID or Public ID..." value="<?php echo $search_term_display; ?>" required>
                        </div>
                        <button type="submit" name="search_profile" class="cta-button search-button-public"><i class="fas fa-search"></i> Search</button>
                    </form>
                    <?php if (!empty($search_message) && $_SERVER["REQUEST_METHOD"] == "POST"): ?>
                        <div class="search-message-area mt-3"><?php echo $search_message; ?></div>
                    <?php elseif (!empty($search_message) && isset($_GET['id'])): // Show error if ID from GET failed ?>
                         <div class="search-message-area mt-3"><?php echo $search_message; ?></div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($user_profile): ?>
                <div class="profile-display-area">
                    <section class="profile-card-public content-section" data-aos="zoom-in-up">
                        <div class="profile-card-public-bg"></div>
                        <div class="profile-header-public">
                            <img src="<?php echo (!empty($user_profile['profile_picture']) ? BASE_URL . 'uploads/profile/' . sanitizeOutput($user_profile['profile_picture']) : BASE_URL . 'assets/images/placeholder-avatar.png'); ?>" 
                                 alt="<?php echo sanitizeOutput($user_profile['name']); ?>'s Profile Picture" 
                                 class="profile-pic-public">
                            <div class="profile-info-public">
                                <h1><?php echo sanitizeOutput($user_profile['name']); ?></h1>
                                <?php if(!empty($user_profile['pronouns'])): ?>
                                    <p class="public-pronouns">(<?php echo sanitizeOutput($user_profile['pronouns']); ?>)</p>
                                <?php endif; ?>
                                <p class="public-niftem-id"><i class="fas fa-id-badge"></i> NIFTEM ID: <?php echo sanitizeOutput($user_profile['niftem_id']); ?></p>
                                <?php if (!empty($user_profile['department'])): ?>
                                    <p class="public-department"><i class="fas fa-university"></i> Department: <?php echo sanitizeOutput($user_profile['department']); ?></p>
                                <?php endif; ?>
                                <p class="public-member-since"><i class="fas fa-user-clock"></i> Geetanjali Member Since: <?php echo isset($user_profile['registration_date']) ? date('F Y', strtotime($user_profile['registration_date'])) : 'N/A'; ?></p>
                            </div>
                        </div>

                        <?php if(!empty($user_profile['bio'])): ?>
                        <div class="profile-bio-public">
                            <h3><i class="fas fa-feather-alt"></i> About <?php echo sanitizeOutput(strtok($user_profile['name'], ' ')); ?></h3>
                            <p><?php echo nl2br(sanitizeOutput($user_profile['bio'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </section>

                    <div class="public-profile-columns">
                        <div class="public-profile-main-col">
                            <section class="participated-events-public content-section card-style-public" data-aos="fade-right" data-aos-delay="100">
                                <h2><i class="fas fa-calendar-check"></i> Event Participation</h2>
                                <?php if (!empty($participated_events)): ?>
                                    <ul class="event-list-public">
                                        <?php foreach (array_slice($participated_events, 0, 5) as $event): // Show recent 5 ?>
                                            <li>
                                                <a href="<?php echo BASE_URL . 'events/view.php?id=' . sanitizeOutput($event['event_id']); ?>">
                                                    <strong><?php echo sanitizeOutput($event['title']); ?></strong>
                                                </a>
                                                <span class="event-date-public">(<?php echo isset($event['date_time']) ? date('M j, Y', strtotime($event['date_time'])) : 'Date N/A'; ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if(count($participated_events) > 5): ?>
                                        <p class="text-center mt-2"><small>And <?php echo count($participated_events) - 5; ?> more...</small></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p><?php echo sanitizeOutput($user_profile['name']); ?> has no publicly listed event participation yet.</p>
                                <?php endif; ?>
                            </section>

                            <?php if (!empty($public_badges)): ?>
                            <section class="profile-badges-public content-section card-style-public" data-aos="fade-right" data-aos-delay="200">
                                <h2><i class="fas fa-trophy"></i> Achievements & Badges</h2>
                                <div class="profile-badges-grid">
                                    <?php foreach ($public_badges as $badge): ?>
                                    <div class="badge-item-public">
                                        <i class="fas <?php echo sanitizeOutput($badge['icon']); ?> fa-2x badge-icon-public"></i>
                                        <div class="badge-details-public">
                                            <span class="badge-title-public"><?php echo sanitizeOutput($badge['title']); ?></span>
                                            <small class="badge-description-public"><?php echo sanitizeOutput($badge['description']); ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <?php endif; ?>
                        </div>
                        <aside class="public-profile-sidebar-col">
                            <?php if(!empty($user_interests_display)): ?>
                            <section class="profile-interests-public sidebar-widget-public card-style-public" data-aos="fade-left" data-aos-delay="150">
                                <h3><i class="fas fa-paint-brush"></i> Interests</h3>
                                <div class="profile-tags-container">
                                    <?php foreach($user_interests_display as $interest): ?>
                                        <span class="profile-tag"><i class="fas fa-heart"></i> <?php echo sanitizeOutput($interest); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <?php endif; ?>

                            <?php if(!empty($user_skills_display)): ?>
                            <section class="profile-skills-public sidebar-widget-public card-style-public" data-aos="fade-left" data-aos-delay="250">
                                <h3><i class="fas fa-star"></i> Skills</h3>
                                <div class="profile-tags-container">
                                     <?php foreach($user_skills_display as $skill): ?>
                                        <span class="profile-tag skill-tag"><i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <?php endif; ?>
                            
                            <?php 
                            $has_social_public = !empty($user_profile['social_linkedin']) || !empty($user_profile['social_twitter']) || !empty($user_profile['social_portfolio']);
                            ?>
                            <?php if($has_social_public): ?>
                            <section class="profile-social-public sidebar-widget-public card-style-public" data-aos="fade-left" data-aos-delay="350">
                                <h3><i class="fas fa-link"></i> Connect Online</h3>
                                <div class="profile-social-links-list view-mode">
                                    <?php if(!empty($user_profile['social_linkedin'])):?><a href="<?php echo sanitizeOutput($user_profile['social_linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-link linkedin" title="LinkedIn"><i class="fab fa-linkedin"></i> LinkedIn</a><?php endif; ?>
                                    <?php if(!empty($user_profile['social_twitter'])):?><a href="<?php echo sanitizeOutput($user_profile['social_twitter']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-link twitter" title="Twitter"><i class="fab fa-twitter-square"></i> Twitter/X</a><?php endif; ?>
                                    <?php if(!empty($user_profile['social_portfolio'])):?><a href="<?php echo sanitizeOutput($user_profile['social_portfolio']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-link portfolio" title="Portfolio/Website"><i class="fas fa-globe"></i> Portfolio</a><?php endif; ?>
                                </div>
                            </section>
                            <?php endif; ?>
                        </aside>
                    </div>
                </div> 
                
                <section class="content-section text-center" style="margin-top: 2rem;">
                    <p><small>This public profile is managed by Geetanjali - The Literary Society, NIFTEM-K. Information shared is based on the user's privacy settings.</small></p>
                     <a href="<?php echo sanitizeOutput(BASE_URL . 'public_profile.php'); ?>" class="cta-button cta-button-outline">Search Another Profile</a>
                </section>

            <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($search_message)): // Only show if a search was made and resulted in a message ?>
                <section class="content-section text-center">
                     <div class="search-message-area mt-3"><?php echo $search_message; ?></div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    