<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];

    // Get the file system path to the project's root directory.
    // Assumes 'includes' folder is directly inside the project root folder.
    // __DIR__ is the directory of header.php (e.g., /var/www/html/geetanjali_website/includes)
    // dirname(__DIR__) is the project root directory on the file system (e.g., /var/www/html/geetanjali_website)
    $project_root_on_filesystem = dirname(__DIR__);

    // Replace the server's document root path from the project's root path to get the web path.
    // This gives the path from the web server's root to your project's root.
    $web_path_to_project_root = str_replace($_SERVER['DOCUMENT_ROOT'], '', $project_root_on_filesystem);

    // Ensure the web path starts with a slash if it's not empty (i.e., if it's a subdirectory)
    // And make it an empty string if the project is at the document root.
    if (!empty($web_path_to_project_root) && $web_path_to_project_root !== DIRECTORY_SEPARATOR && $web_path_to_project_root !== '/') {
        $final_web_path = '/' . trim($web_path_to_project_root, '/\\');
    } elseif (empty($web_path_to_project_root) || $web_path_to_project_root === DIRECTORY_SEPARATOR || $web_path_to_project_root === '/') {
         // Project is at the domain root, or str_replace resulted in just a separator
        $final_web_path = ''; // No subfolder path needed from the host
    }


    define('BASE_URL', $protocol . $host . $final_web_path . '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0a2342" />
    <meta name="description" content="Geetanjali - The Literary Society of NIFTEM-K. Discover events, creations, and the literary culture at NIFTEM Kundli." />
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : ''; ?>Geetanjali - NIFTEM-K</title>
    
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json" />
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/favicon.ico" type="image/x-icon" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/responsive.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/about-page.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/student_dashboard.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/profile.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/submit_work.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/gallery.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/admin.css" />

    <?php if (isset($additional_css) && is_array($additional_css)): ?>
        <?php foreach ($additional_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo BASE_URL . htmlspecialchars($css_file); ?>" />
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="niftem-brand-bar">
        <div class="container">
            <a href="https://niftem.ac.in/" target="_blank" rel="noopener noreferrer" title="Visit NIFTEM-K Official Website">
                <img src="<?php echo BASE_URL; ?>assets/images/niftem.png" alt="NIFTEM Kundli Logo" class="niftem-logo" />
                <span>National Institute of Food Technology Entrepreneurship and Management</span>
            </a>
        </div>
    </div>

    <header id="main-header" class="main-header-transparent">
        <div class="container">
            <div class="logo-area">
                <a href="<?php echo BASE_URL; ?>index.php">
                    <img src="<?php echo BASE_URL; ?>assets/images/geetanjali.jpg" alt="Geetanjali - The Literary Society Logo" class="site-logo" />
                </a>
            </div>
            <?php include 'navbar.php'; // Include the navbar ?>
        </div>
    </header>
    <main id="page-content"> <?php // Moved <main> tag here to ensure it's always present ?>