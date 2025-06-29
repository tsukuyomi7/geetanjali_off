    <?php
    // geetanjali_website/includes/navbar.php
    // Assumes session has been started (e.g., in header.php)
    // Assumes BASE_URL is defined (e.g., in header.php)

    // Helper function to determine if a link is active (can be refined)
    function isNavLinkActive($path_segment) {
        // Construct the full path to check against, relative to BASE_URL
        $check_path = rtrim(BASE_URL, '/') . '/' . ltrim($path_segment, '/');
        // Get current request URI without query string
        $current_uri = strtok($_SERVER["REQUEST_URI"], '?');
        
        // Special case for home page (index.php at the root of BASE_URL)
        if ($path_segment === 'index.php' || $path_segment === '') {
            // Check if current URI is exactly BASE_URL or BASE_URL/index.php
            return ($current_uri === rtrim(BASE_URL, '/') . '/' || $current_uri === BASE_URL . 'index.php');
        }
        
        // Check if the current URI starts with the path segment
        // For directory links like 'events/', ensure it matches '/events/' part of URI
        if (substr($path_segment, -1) === '/') { // It's a directory link
            return strpos($current_uri, rtrim(BASE_URL,'/') . '/' . trim($path_segment,'/').'/') !== false;
        } else { // It's a file link
             return strpos($current_uri, rtrim(BASE_URL,'/') . '/' . trim($path_segment,'/')) !== false;
        }
    }
    ?>
    <nav>
        <button class="menu-toggle" id="mobile-menu" aria-label="Open Navigation Menu" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="nav-list" id="nav-list">
            <li><a href="<?php echo BASE_URL; ?>index.php" class="<?php echo isNavLinkActive('index.php') ? 'active' : ''; ?>">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>about.php" class="<?php echo isNavLinkActive('about.php') ? 'active' : ''; ?>">About Us</a></li>
            <li><a href="<?php echo BASE_URL; ?>events/" class="<?php echo isNavLinkActive('events/') ? 'active' : ''; ?>">Events</a></li>
            <li><a href="<?php echo BASE_URL; ?>blog/" class="<?php echo isNavLinkActive('blog/') ? 'active' : ''; ?>">Blog</a></li>
            <li><a href="<?php echo BASE_URL; ?>gallery/" class="<?php echo isNavLinkActive('gallery/') ? 'active' : ''; ?>">Gallery</a></li>
            <li><a href="<?php echo BASE_URL; ?>contact.php" class="<?php echo isNavLinkActive('contact.php') ? 'active' : ''; ?>">Contact</a></li>
            
            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                <li><a href="<?php echo BASE_URL; ?>student/dashboard.php" class="<?php echo isNavLinkActive('student/') ? 'active' : ''; ?>">My Portal</a></li>
                
                <?php // Admin and Super Admin Links
                if (isset($_SESSION["user_role"])):
                    if ($_SESSION["user_role"] == 'admin' || $_SESSION["user_role"] == 'super_admin'): ?>
                        <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="<?php echo isNavLinkActive('admin/') ? 'active' : ''; ?>">Admin Panel</a></li>
                    <?php endif;
                    if ($_SESSION["user_role"] == 'super_admin'): ?>
                        <li><a href="<?php echo BASE_URL; ?>super_admin/dashboard.php" class="<?php echo isNavLinkActive('super_admin/') ? 'active' : ''; ?>">Super Admin</a></li>
                    <?php endif;
                endif; ?>
                
                <li><a href="<?php echo BASE_URL; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION["user_name"]); ?>)</a></li>
            <?php else: // User is not logged in ?>
                <li><a href="<?php echo BASE_URL; ?>login.php" class="<?php echo isNavLinkActive('login.php') ? 'active' : ''; ?>">Login</a></li>
               
            <?php endif; ?>
        </ul>
    </nav>
    