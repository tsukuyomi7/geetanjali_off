    <?php
    // geetanjali_website/includes/generate_qr_code.php

    // No session needed here usually, unless you add specific user-based checks.
    // if (session_status() == PHP_SESSION_NONE) {
    //     session_start();
    // }

    // Define BASE_PATH to reliably locate the qrlib.php file
    // __DIR__ is the directory of the current file (includes/)
    // dirname(__DIR__) is the project root (geetanjali/)
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/');
    }

    $phpqrcode_library_path = BASE_PATH . 'phpqrcode/qrlib.php';

    if (!file_exists($phpqrcode_library_path)) {
        header("Content-type: image/png");
        $error_image_width = 250;
        $error_image_height = 120;
        $im = imagecreatetruecolor($error_image_width, $error_image_height);
        $bg_color = imagecolorallocate($im, 255, 255, 255); // White background
        $text_color_error = imagecolorallocate($im, 220, 53, 69); // Red text
        imagefill($im, 0, 0, $bg_color);
        $line_height = 15;
        $y_pos = 10;
        imagestring($im, 3, 10, $y_pos, "QR Library Error", $text_color_error); $y_pos += $line_height + 5;
        imagestring($im, 2, 10, $y_pos, "qrlib.php not found.", $text_color_error); $y_pos += $line_height;
        imagestring($im, 2, 10, $y_pos, "Expected in:", $text_color_error); $y_pos += $line_height;
        imagestring($im, 2, 10, $y_pos, "phpqrcode/", $text_color_error); $y_pos += $line_height;
        imagestring($im, 2, 10, $y_pos, "Please install it.", $text_color_error);
        imagepng($im);
        imagedestroy($im);
        exit;
    }

    require_once $phpqrcode_library_path;

    $data_to_encode = $_GET['data'] ?? null;
    $should_download = isset($_GET['download']) && $_GET['download'] == '1';
    
    $qr_filename = 'geetanjali_id_qr.png'; // Default filename for download
    if (isset($_GET['filename'])) {
        // Sanitize filename: allow alphanumeric, underscores, hyphens, dots.
        $clean_dl_filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $_GET['filename']);
        $qr_filename = !empty($clean_dl_filename) ? $clean_dl_filename . '.png' : 'qr_code.png';
    }


    if (empty(trim((string)$data_to_encode))) {
        header("Content-type: image/png");
        $im = imagecreatetruecolor(150, 50);
        $bg = imagecolorallocate($im, 255, 255, 255);
        $fg = imagecolorallocate($im, 220, 53, 69); 
        imagefill($im, 0, 0, $bg);
        imagestring($im, 3, 5, 5, "QR Data Error:", $fg);
        imagestring($im, 2, 5, 25, "No data provided.", $fg);
        imagepng($im);
        imagedestroy($im);
        exit;
    }

    // QR Code Generation Parameters
    $errorCorrectionLevel = 'L'; // Levels: L, M, Q, H
    $matrixPointSize = 5;      // Size of each "dot" in pixels (e.g., 4-10)
    $margin = 2;               // Margin around QR code in "dots" (e.g., 1-4)

    try {
        if ($should_download) {
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="' . $qr_filename . '"');
            // Output directly for download, no file saving on server needed for this specific action
            QRcode::png($data_to_encode, false, $errorCorrectionLevel, $matrixPointSize, $margin);
        } else {
            // Output QR code image directly to the browser (for <img> src)
            // The false parameter for $outfile outputs directly
            QRcode::png($data_to_encode, false, $errorCorrectionLevel, $matrixPointSize, $margin);
        }
    } catch (Exception $e) {
        error_log("QR Code Generation Error in generate_qr_code.php: " . $e->getMessage() . " for data: " . $data_to_encode);
        // Output a generic error image
        header("Content-type: image/png");
        $im = imagecreatetruecolor(150, 30);
        $bg = imagecolorallocate($im, 255, 255, 255);
        $fg = imagecolorallocate($im, 0, 0, 0); // Black text
        imagefill($im, 0, 0, $bg);
        imagestring($im, 3, 5, 5, "QR Gen Error", $fg);
        imagepng($im);
        imagedestroy($im);
    }
    exit; // Important to ensure no other output interferes with the image
    ?>
    