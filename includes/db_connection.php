    <?php
    // geetanjali_website/includes/db_connection.php
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        // Start session if not already started, AND no output has been sent.
        // This is a good place for it if db_connection.php is your first include on every page.
        session_start();
    }

    // Define database connection constants
    // Ensure these are correct for your XAMPP MySQL setup.
    // Default XAMPP: user 'root', empty password.
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'root'); 
    define('DB_PASSWORD', ''); // For default XAMPP, this is usually empty. Change if you set one.
    
    // CRITICAL: This MUST be the name of the database you created in phpMyAdmin.
    define('DB_NAME', 'geetanjali_website_db'); 

    // Attempt to connect to MySQL database
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check connection
    if ($conn === false) {
        // Log the detailed error to your PHP error log
        error_log("FATAL: Database connection failed: " . mysqli_connect_error() . 
                  " | Attempted to connect to DB: '" . DB_NAME . "' with User: '" . DB_USERNAME . "'");
        
        // Display a user-friendly message and stop script execution.
        // In a production environment, you might show a more generic error page.
        die("ERROR: Could not connect to the database. Please check configuration or contact support. (DB_CONN_FAIL)");
    }

    // Set character set to utf8mb4 for full Unicode support
    if (!mysqli_set_charset($conn, "utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . mysqli_error($conn));
        // Potentially die here as well if charset is critical
        // die("Error setting database character set.");
    }

    // --- Define BASE_URL ---
    // This MUST be at the end of this file, after the $conn is established (or attempted).
    if (!defined('BASE_URL')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Fallback to localhost if HTTP_HOST isn't set (e.g. CLI)
        
        // CRITICAL: Adjust $project_subfolder if your project is not in the web root's 'geetanjali' subfolder.
        // If project is at http://localhost/ (directly in htdocs), $project_subfolder = '';
        // If project is at http://localhost/geetanjali/, $project_subfolder = '/geetanjali';
        $project_subfolder = '/geetanjali'; // THIS MUST MATCH YOUR XAMPP HTDOCS SETUP
        
        define('BASE_URL', $protocol . $host . $project_subfolder . '/');
    }

    // Optional: Define a constant to check if this file was included
    if (!defined('DB_CONNECTION_INCLUDED')) {
        define('DB_CONNECTION_INCLUDED', true);
    }
    ?>
    