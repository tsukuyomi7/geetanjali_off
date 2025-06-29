    <?php
    // geetanjali_website/logout.php
    
    // Ensure session is started to access session functions like session_destroy
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Include auth.php for logoutUser function and BASE_URL definition (if not already defined)
    require_once 'includes/auth.php'; 

    // Define BASE_URL if it's not already set (e.g., if auth.php doesn't define it or header.php wasn't included)
    // This is a fallback, ideally BASE_URL is consistently defined in one central place like header.php or db_connection.php
    if (!defined('BASE_URL')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // Basic way to get subdirectory if any. Assumes logout.php is in the root.
        // For more complex setups, the BASE_URL logic from header.php is more robust.
        $script_dir_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        define('BASE_URL', $protocol . $host . $script_dir_path . '/');
    }

    if (logoutUser()) {
        // Redirect to login page with a success message
        header("Location: " . BASE_URL . "login.php?logout=success");
        exit; // Crucial: Always exit after a header redirect
    } else {
        // Handle logout error, though very unlikely if session_destroy itself fails
        // Redirect to homepage with an error message (or just homepage)
        error_log("Logout failed unexpectedly."); // Log this rare event
        header("Location: " . BASE_URL . "index.php?logout_error=1");
        exit; // Crucial: Always exit after a header redirect
    }
    ?>
    