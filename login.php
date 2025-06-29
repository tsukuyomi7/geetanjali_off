    <?php
    // login.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start(); 
    }

    require_once 'includes/db_connection.php'; 
    require_once 'includes/auth.php';       

    $pageTitle = "Login to Geetanjali";
    $login_message = ""; 
    $niftem_id_or_email_value = ""; 

    // If user is already logged in, redirect them to index with a message (or their dashboard if preferred)
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        // If already logged in and somehow on login page, just go to index.
        // Or, you could redirect to their dashboard as before.
        // For this request, let's redirect to index if they land here while logged in.
        header("Location: " . BASE_URL . "index.php?status=loggedin");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_submit'])) {
        $niftem_id_or_email = trim($_POST['niftem_id_or_email']);
        $password = $_POST['password'];
        $niftem_id_or_email_value = htmlspecialchars($niftem_id_or_email); 

        if (empty($niftem_id_or_email) || empty($password)) {
            $login_message = "<div class='form-message error'>Both NIFTEM ID/Email and Password are required.</div>";
        } else {
            if (!$conn) {
                error_log("Login page: Database connection object not available.");
                $login_message = "<div class='form-message error'>System error. Please try again later. (DB_CONN_LOGIN)</div>";
            } else {
                $result = loginUser($conn, $niftem_id_or_email, $password);

                if ($result['success']) {
                    // SUCCESS: Redirect to index.php with a success flag
                    header("Location: " . BASE_URL . "index.php?login=success");
                    exit; 
                } else {
                    $login_message = "<div class='form-message error'>" . htmlspecialchars($result['message']) . "</div>";
                }
            }
        }
    }
    
    if(isset($_GET['logout']) && $_GET['logout'] == 'success'){
        $login_message = "<div class='form-message success'>You have been logged out successfully.</div>";
    }
    if(isset($_GET['registered']) && $_GET['registered'] == 'success'){
        $login_message = "<div class='form-message success'>Registration successful! Please login.</div>";
    }
    if(isset($_GET['auth']) && $_GET['auth'] == 'required'){
        $login_message = "<div class='form-message warning'>Please login to access that page.</div>";
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
                <h2>Member Login</h2>
                <?php if (!empty($login_message)) { echo $login_message; } ?>
                
                <form id="login-form" action="<?php echo htmlspecialchars(BASE_URL . 'login.php' . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '')); ?>" method="POST" novalidate>
                    <div class="form-group">
                        <label for="niftem_id_or_email">NIFTEM ID or Email <span class="required">*</span></label>
                        <input type="text" id="niftem_id_or_email" name="niftem_id_or_email" value="<?php echo $niftem_id_or_email_value; ?>" required aria-describedby="niftemIdHelp" autofocus>
                        <small id="niftemIdHelp" class="form-text text-muted">Enter your NIFTEM ID or registered email.</small>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group text-right">
                        <a href="<?php echo BASE_URL; ?>forgot_password.php" class="forgot-password-link">Forgot Password?</a>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="login_submit" class="cta-button auth-button">Login</button>
                    </div>
                </form>
                <p class="auth-switch-link">Don't have an account? <a href="<?php echo BASE_URL; ?>register.php">Register here</a>.</p>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    