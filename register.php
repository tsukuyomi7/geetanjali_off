    <?php
    // register.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'includes/db_connection.php'; 
    require_once 'includes/auth.php';       

    $pageTitle = "Register Account";
    $registration_message = "";
    $form_data = ['niftem_id' => '', 'name' => '', 'email' => '', 'department' => ''];

    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        header("Location: " . BASE_URL . "student/dashboard.php");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_submit'])) {
        $niftem_id = trim($_POST['niftem_id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $department = isset($_POST['department']) ? trim($_POST['department']) : null;

        $form_data = ['niftem_id' => htmlspecialchars($niftem_id), 'name' => htmlspecialchars($name), 'email' => htmlspecialchars($email), 'department' => htmlspecialchars($department)];

        $errors = [];
        if (empty($niftem_id)) $errors[] = "NIFTEM ID is required.";
        if (empty($name)) $errors[] = "Full Name is required.";
        if (empty($email)) {
            $errors[] = "Email Address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }

        if (!empty($errors)) {
            $registration_message = "<div class='form-message error'><ul>";
            foreach($errors as $error) {
                $registration_message .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $registration_message .= "</ul></div>";
        } else {
            if (!$conn) {
                error_log("Register page: Database connection object not available.");
                $registration_message = "<div class='form-message error'>System error. Please try again later. (DB_CONN_REG)</div>";
            } else {
                $result = registerUser($conn, $niftem_id, $name, $email, $password, $department);
                if ($result['success']) {
                    header("Location: " . BASE_URL . "login.php?registered=success");
                    exit; // Crucial: Always exit after a header redirect
                } else {
                    $registration_message = "<div class='form-message error'>" . $result['message'] . "</div>";
                }
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
                <h2>Create Your Account</h2>
                <?php if (!empty($registration_message)) echo $registration_message; ?>
                
                <form id="registration-form" action="<?php echo htmlspecialchars(BASE_URL . 'register.php'); ?>" method="POST" novalidate>
                    <div class="form-group">
                        <label for="niftem_id">NIFTEM ID <span class="required">*</span></label>
                        <input type="text" id="niftem_id" name="niftem_id" value="<?php echo $form_data['niftem_id']; ?>" required aria-describedby="niftemIdHelpReg">
                        <small id="niftemIdHelpReg" class="form-text text-muted">Your official NIFTEM identification number.</small>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo $form_data['name']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo $form_data['email']; ?>" required>
                    </div>
                     <div class="form-group">
                        <label for="department">Department (Optional)</label>
                        <input type="text" id="department" name="department" value="<?php echo $form_data['department']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required minlength="6" aria-describedby="passwordHelp">
                        <small id="passwordHelp" class="form-text text-muted">Must be at least 6 characters long.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="register_submit" class="cta-button auth-button">Register</button>
                    </div>
                </form>
                <p class="auth-switch-link">Already have an account? <a href="<?php echo BASE_URL; ?>login.php">Login here</a>.</p>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    