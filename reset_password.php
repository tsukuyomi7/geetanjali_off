    <?php
    // geetanjali_website/reset_password.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'includes/db_connection.php';
    require_once 'includes/functions.php';
    // require_once 'includes/auth.php'; // Potentially for password update logic

    $pageTitle = "Reset Your Password";
    $message = "";
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    $user_id_for_reset = null;
    $can_show_form = false;

    if (empty($token)) {
        $message = "<div class='form-message error'>Invalid or missing password reset token. Please request a new one if needed.</div>";
    } else {
        // Validate token: Check against database and expiry
        $stmt_validate = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        if ($stmt_validate) {
            $stmt_validate->bind_param("s", $token);
            $stmt_validate->execute();
            $result_validate = $stmt_validate->get_result();
            if ($user_data = $result_validate->fetch_assoc()) {
                $user_id_for_reset = $user_data['id'];
                $can_show_form = true;
            } else {
                $message = "<div class='form-message error'>This password reset token is invalid or has expired. Please request a new one.</div>";
            }
            $stmt_validate->close();
        } else {
            error_log("Reset Password: Token validation prepare failed: " . $conn->error);
            $message = "<div class='form-message error'>Error validating your request. Please try again. (Code: RP01)</div>";
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password_submit']) && $can_show_form && $user_id_for_reset) {
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];
        $posted_token = $_POST['token']; // Ensure token is still valid on POST

        if ($posted_token !== $token) {
             $message = "<div class='form-message error'>Token mismatch. Please use the link from your email.</div>";
             $can_show_form = false; // Prevent further processing
        } elseif (empty($new_password) || empty($confirm_new_password)) {
            $message = "<div class='form-message error'>Both password fields are required.</div>";
        } elseif ($new_password !== $confirm_new_password) {
            $message = "<div class='form-message error'>New passwords do not match.</div>";
        } elseif (strlen($new_password) < 6) {
            $message = "<div class='form-message error'>New password must be at least 6 characters long.</div>";
        } else {
            // Hash the new password
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password and clear reset token in DB
            $stmt_update_pw = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ? AND reset_token = ?");
            if ($stmt_update_pw) {
                $stmt_update_pw->bind_param("sis", $hashed_new_password, $user_id_for_reset, $token);
                if ($stmt_update_pw->execute() && $stmt_update_pw->affected_rows > 0) {
                    logAudit($conn, $user_id_for_reset, "Password successfully reset via token");
                    $_SESSION['success_message_login'] = "Your password has been successfully reset! You can now log in with your new password.";
                    header("Location: " . BASE_URL . "login.php");
                    exit;
                } else {
                    $message = "<div class='form-message error'>Could not update your password. The reset link may have been used or expired. Please try again. (Code: RP03)</div>";
                    error_log("Reset Password: Password update execute failed or no rows affected: " . $stmt_update_pw->error . " User ID: " . $user_id_for_reset);
                }
                $stmt_update_pw->close();
            } else {
                $message = "<div class='form-message error'>Error processing your request. Please try again. (Code: RP02)</div>";
                error_log("Reset Password: Update password prepare failed: " . $conn->error);
            }
            $can_show_form = false; // Hide form after attempt, successful or not, unless specific error.
        }
    }
    
    // If login page needs to display a success message passed from here
    if(isset($_SESSION['success_message_login']) && empty($message)){
        // This won't be displayed here due to redirect, but good practice for other pages
        $message = "<div class='form-message success'>" . sanitizeOutput($_SESSION['success_message_login']) . "</div>";
        unset($_SESSION['success_message_login']);
    }


    include 'includes/header.php';
    ?>

    <main id="page-content" class="auth-page-background">
        <div class="auth-form-container" data-aos="fade-up">
            <div class="auth-form-wrapper">
                <h2><i class="fas fa-key"></i> <?php echo htmlspecialchars($pageTitle); ?></h2>
                
                <?php if (!empty($message)) { echo $message; } ?>

                <?php if ($can_show_form): ?>
                    <p class="text-muted text-center mb-4">Please enter your new password below. Make sure it's secure.</p>
                    <form id="reset-password-form" action="<?php echo htmlspecialchars(BASE_URL . 'reset_password.php?token=' . urlencode($token)); ?>" method="POST" novalidate>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="form-group">
                            <label for="new_password">New Password <span class="required">*</span></label>
                            <input type="password" id="new_password" name="new_password" class="form-control form-control-lg" required minlength="6" autofocus>
                            <small class="form-text text-muted">Must be at least 6 characters long.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password">Confirm New Password <span class="required">*</span></label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control form-control-lg" required minlength="6">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="reset_password_submit" class="cta-button auth-button btn-block"><i class="fas fa-save"></i> Set New Password</button>
                        </div>
                    </form>
                <?php elseif(empty($message)): // No token, no error yet, means page loaded directly without token ?>
                    <div class="form-message error">Invalid request. Please use the link provided in your email.</div>
                <?php endif; ?>
                <p class="auth-switch-link mt-4">
                    Remembered your password? <a href="<?php echo BASE_URL; ?>login.php">Login here</a>.
                </p>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    