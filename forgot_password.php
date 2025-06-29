    <?php
    // geetanjali_website/forgot_password.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; // Defines $conn and BASE_URL
    require_once '../includes/functions.php';     // For sanitizeOutput(), logAudit() etc.

    // --- PHPMailer Inclusion ---
    // Ensure these paths are correct for your PHPMailer installation
    // If using Composer, it's usually: require_once __DIR__ . '/../vendor/autoload.php';
    require_once '../includes/phpmailer/Exception.php';
    require_once '../includes/phpmailer/PHPMailer.php';
    require_once '../includes/phpmailer/SMTP.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    // --- End PHPMailer Inclusion ---


    // --- SMTP Configuration (Using your Hostinger details) ---
    // IMPORTANT: For production, move these to a secure config file or environment variables!
    // You can fetch these from your database 'settings' table if you prefer,
    // using the getSetting($conn, 'setting_key', 'default_value') function.

    // define('SMTP_HOST_CONFIG', getSetting($conn, 'smtp_host', 'smtp.hostinger.com'));
    // define('SMTP_USER_CONFIG', getSetting($conn, 'smtp_username', 'support@kalakriti.fun'));
    // define('SMTP_PASS_CONFIG', getSetting($conn, 'smtp_password', 'YOUR_EMAIL_PASSWORD_HERE')); // !!! REPLACE WITH YOUR ACTUAL PASSWORD (and secure it) !!!
    // define('SMTP_PORT_CONFIG', (int)getSetting($conn, 'smtp_port', 465));
    // define('SMTP_ENCRYPTION_CONFIG', getSetting($conn, 'smtp_encryption', 'ssl')); // 'ssl' for PHPMailer::ENCRYPTION_SMTPS

    // define('FROM_EMAIL_CONFIG', getSetting($conn, 'from_email_address', 'support@kalakriti.fun'));
    // define('FROM_NAME_CONFIG', getSetting($conn, 'from_email_name', 'Geetanjali Literary Society'));

    // For immediate use, directly defining (but remember to secure credentials for production):
    define('SMTP_HOST_CONFIG', 'smtp.hostinger.com');
    define('SMTP_USER_CONFIG', 'support@kalakriti.fun');
    define('SMTP_PASS_CONFIG', '=L7;?d97P0r3'); // !!! REPLACE AND SECURE THIS !!!
    define('SMTP_PORT_CONFIG', 465);
    define('SMTP_ENCRYPTION_CONFIG', PHPMailer::ENCRYPTION_SMTPS); // PHPMailer constant for SSL

    define('FROM_EMAIL_CONFIG', 'support@kalakriti.fun');
    define('FROM_NAME_CONFIG', getSetting($conn, 'website_name', 'Geetanjali Literary Society')); // Get website name from settings
    // --- End SMTP Configuration ---


    $pageTitle = "Forgot Your Password?";
    $message = "";
    $email_sent_success = false;

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_reset'])) {
        $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='form-message error'>Please enter a valid email address.</div>";
        } else {
            $stmt_user = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND account_status IN ('active', 'pending_verification')");
            if ($stmt_user) {
                $stmt_user->bind_param("s", $email);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();

                if ($user = $result_user->fetch_assoc()) {
                    $user_id = $user['id'];
                    $user_name = $user['name'];

                    $token = bin2hex(random_bytes(32));
                    $expiry_time = date('Y-m-d H:i:s', time() + 3600); // Token expires in 1 hour

                    $stmt_token = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                    if ($stmt_token) {
                        $stmt_token->bind_param("ssi", $token, $expiry_time, $user_id);
                        if ($stmt_token->execute()) {
                            // --- Send Password Reset Email using PHPMailer ---
                            $reset_link = BASE_URL . "reset_password.php?token=" . urlencode($token);
                            $email_subject = "Password Reset Request - " . FROM_NAME_CONFIG;
                            $email_body_html = "
                            <html><body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                            <h2 style='color: #0a2342;'>Password Reset Request</h2>
                            <p>Dear " . htmlspecialchars($user_name) . ",</p>
                            <p>We received a request to reset your password for your " . FROM_NAME_CONFIG . " account associated with this email address.</p>
                            <p>If you made this request, please click the link below to set a new password. This link is valid for 1 hour:</p>
                            <p style='margin: 20px 0;'><a href='" . $reset_link . "' style='background-color: #0a2342; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Your Password</a></p>
                            <p>If the button above does not work, please copy and paste the following URL into your browser's address bar:</p>
                            <p><a href='" . $reset_link . "'>" . $reset_link . "</a></p>
                            <p>If you did not request a password reset, please ignore this email or contact us if you have concerns.</p>
                            <p>Thank you,<br>The " . FROM_NAME_CONFIG . " Team</p>
                            </body></html>";
                            $email_body_plain = "Dear " . htmlspecialchars($user_name) . ",\n\nWe received a request to reset your password for your " . FROM_NAME_CONFIG . " account.\n\nPlease use this link (valid for 1 hour): " . $reset_link . "\n\nIf you did not request this, please ignore this email.\n\nThank you,\nThe " . FROM_NAME_CONFIG . " Team";

                            $mail = new PHPMailer(true);
                            try {
                                //Server settings
                                // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable for detailed debug output during testing
                                $mail->isSMTP();                                     
                                $mail->Host       = SMTP_HOST_CONFIG;                
                                $mail->SMTPAuth   = true;                            
                                $mail->Username   = SMTP_USER_CONFIG;                
                                $mail->Password   = SMTP_PASS_CONFIG; // THIS IS YOUR SENSITIVE PASSWORD                
                                $mail->SMTPSecure = SMTP_ENCRYPTION_CONFIG; // PHPMailer::ENCRYPTION_SMTPS for SSL, PHPMailer::ENCRYPTION_STARTTLS for TLS          
                                $mail->Port       = SMTP_PORT_CONFIG;                                    

                                //Recipients
                                $mail->setFrom(FROM_EMAIL_CONFIG, FROM_NAME_CONFIG);
                                $mail->addAddress($email, htmlspecialchars($user_name)); 

                                // Content
                                $mail->isHTML(true);                                  
                                $mail->Subject = $email_subject;
                                $mail->Body    = $email_body_html;
                                $mail->AltBody = $email_body_plain;

                                $mail->send();
                                logAudit($conn, $user_id, "Password reset email successfully sent (PHPMailer)");
                                $email_sent_success = true;

                            } catch (Exception $e) {
                                error_log("Forgot Password: PHPMailer message could not be sent. Mailer Error: {$mail->ErrorInfo} | User Email: $email");
                                $message = "<div class='form-message error'>We encountered an issue sending the reset email. Please check your SMTP settings or contact support. (Code: FP_MAIL_ERR) <br><small>Details: " . htmlspecialchars($mail->ErrorInfo) . "</small></div>";
                            }
                        } else {
                            error_log("Forgot Password: DB token update execute failed: " . $stmt_token->error);
                            $message = "<div class='form-message error'>Error processing your request. Please try again. (Code: FP03)</div>";
                        }
                        $stmt_token->close();
                    } else {
                         error_log("Forgot Password: DB token update prepare failed: " . $conn->error);
                         $message = "<div class='form-message error'>Error processing your request. Please try again. (Code: FP02)</div>";
                    }
                } else {
                    error_log("Forgot Password: Attempt for non-existent or ineligible email: " . $email);
                    $email_sent_success = true; 
                }
                if($result_user) $result_user->free();
                $stmt_user->close();
            } else {
                error_log("Forgot Password: User check prepare failed: " . $conn->error);
                $message = "<div class='form-message error'>Error processing your request. Please try again. (Code: FP01)</div>";
            }

            if ($email_sent_success && empty($message)) { 
                $message = "<div class='form-message success'>If an account with that email address exists and is eligible for password reset, instructions have been sent to it. Please check your inbox (and spam folder). The link will be valid for 1 hour.</div>";
            }
        }
    }

    include 'includes/header.php';
    ?>

    <main id="page-content" class="auth-page-background">
        <section class="page-title-section visually-hidden">
            <div class="container">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            </div>
        </section>

        <div class="auth-form-container" data-aos="fade-up">
            <div class="auth-form-wrapper">
                <h2><i class="fas fa-question-circle"></i> Forgot Password?</h2>
                <p class="text-muted text-center mb-4">No worries! Enter your registered email address below, and if an account exists, we'll send you a link to reset your password.</p>
                
                <?php if (!empty($message)) { echo $message; } ?>
                
                <?php if (!$email_sent_success): ?>
                <form id="forgot-password-form" action="<?php echo htmlspecialchars(BASE_URL . 'forgot_password.php'); ?>" method="POST" novalidate>
                    <div class="form-group">
                        <label for="email">Your Registered Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control form-control-lg" placeholder="e.g., yourname@niftem.ac.in" required autofocus>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="request_reset" class="cta-button auth-button btn-block"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
                    </div>
                </form>
                <?php endif; ?>
                <p class="auth-switch-link mt-4">
                    Remember your password? <a href="<?php echo BASE_URL; ?>login.php">Login here</a>. <br>
                    Or, go back <a href="<?php echo BASE_URL; ?>index.php">to Homepage</a>.
                </p>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    