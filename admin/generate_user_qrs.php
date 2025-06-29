    <?php
    // geetanjali_website/admin/generate_user_qrcodes.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    enforceRoleAccess(['admin', 'super_admin']);
    $pageTitle = "Generate User QR Codes";
    
    $qr_library_path = '../includes/phpqrcode/qrlib.php';
    $qr_output_dir = '../uploads/qr_codes/';
    $messages = [];

    if (!file_exists($qr_library_path)) {
        $messages[] = "<div class='form-message error'><strong>Error:</strong> QR Code library (qrlib.php) not found in 'includes/phpqrcode/'. Cannot proceed.</div>";
    } else {
        require_once $qr_library_path;
        
        if (!is_dir($qr_output_dir)) {
            if (!mkdir($qr_output_dir, 0755, true)) {
                $messages[] = "<div class='form-message error'><strong>Error:</strong> Could not create QR code output directory at '$qr_output_dir'. Please check server permissions.</div>";
            } else {
                $messages[] = "<div class='form-message success'>Created QR code directory: '$qr_output_dir'.</div>";
            }
        }

        if (is_dir($qr_output_dir) && is_writable($qr_output_dir)) {
            $stmt = $conn->prepare("SELECT id, name, unique_public_id FROM users WHERE unique_public_id IS NOT NULL AND unique_public_id != ''");
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                $generated_count = 0;
                $skipped_count = 0;

                while ($user = $result->fetch_assoc()) {
                    $qr_data = BASE_URL . 'public_profile.php?id=' . $user['unique_public_id'];
                    $filename = $qr_output_dir . sanitizeOutput($user['unique_public_id']) . '.png';
                    
                    if (!file_exists($filename)) {
                        QRcode::png($qr_data, $filename, QR_ECLEVEL_L, 5, 2);
                        $generated_count++;
                    } else {
                        $skipped_count++;
                    }
                }
                $messages[] = "<div class='form-message success'>Process complete. Generated <strong>$generated_count</strong> new QR codes. Skipped <strong>$skipped_count</strong> existing codes.</div>";
            } else {
                 $messages[] = "<div class='form-message error'>Failed to fetch users from the database.</div>";
            }
        } else {
            $messages[] = "<div class='form-message error'><strong>Error:</strong> Output directory '$qr_output_dir' is not writable. Please check server permissions.</div>";
        }
    }

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area">
        <div class="container">
            <header class="page-title-section">
                <h1><i class="fas fa-qrcode"></i> <?php echo $pageTitle; ?></h1>
                <p class="subtitle">Generate QR code image files for all users with a public ID.</p>
            </header>
            <section class="content-section card-style-admin">
                <p>This utility scans the user database and creates a static QR code image for each user who has a `unique_public_id`. These images are stored in `uploads/qr_codes/`.</p>
                <p>Running this page will only generate QR codes for users who don't already have one. It is safe to run multiple times.</p>
                <hr>
                <h3>Processing Log:</h3>
                <?php
                if (!empty($messages)) {
                    foreach ($messages as $msg) {
                        echo $msg;
                    }
                }
                ?>
                 <div class="form-actions mt-4">
                    <a href="<?php echo BASE_URL; ?>super_admin/dashboard.php" class="cta-button btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </section>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    