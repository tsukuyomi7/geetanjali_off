<?php
// if (session_status() == PHP_SESSION_NONE) { session_start(); }
$pageTitle = "Contact Us";
$contact_form_status = ""; // For form submission messages

// --- Basic Form Submission Handling (Placeholder) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_contact'])) {
    // Sanitize and validate input (CRUCIAL - not fully implemented here)
    $name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $subject = filter_var(trim($_POST['subject']), FILTER_SANITIZE_STRING);
    $message = filter_var(trim($_POST['message']), FILTER_SANITIZE_STRING);
    $errors = [];

    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($subject)) $errors[] = "Subject is required.";
    if (empty($message)) $errors[] = "Message is required.";

    if (empty($errors)) {
        // Process the form (e.g., send email, save to database)
        // For now, just a success message
        // IMPORTANT: Implement actual email sending logic here.
        // mail("your-email@example.com", "Contact Form: " . $subject, $message, "From: " . $email);
        $contact_form_status = "<div class='form-message success'>Thank you for your message, " . htmlspecialchars($name) . "! We will get back to you shortly.</div>";
        // Clear POST data to prevent resubmission on refresh (Post/Redirect/Get pattern is better)
        $_POST = [];
    } else {
        $contact_form_status = "<div class='form-message error'>Please correct the following errors:<ul>";
        foreach ($errors as $error) {
            $contact_form_status .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $contact_form_status .= "</ul></div>";
    }
}

include 'includes/header.php';
?>

<main id="page-content">
    <section class="page-title-section">
        <div class="container">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="subtitle">We'd love to hear from you. Reach out with your queries, suggestions, or collaborations.</p>
        </div>
    </section>

    <section class="container content-section" data-aos="fade-up">
        <div class="contact-grid">
            <div class="contact-details-column">
                <h2>Our Contact Information</h2>
                <p>Feel free to connect with us through any of the following channels. For specific enquiries, you may also reach out to our committee members.</p>
                
                <ul class="contact-info-list">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <strong>Geetanjali - The Literary Society</strong><br>
                            National Institute of Food Technology Entrepreneurship and Management (NIFTEM-K),<br>
                            Plot No. 97, Sector 56, HSIIDC Industrial Estate,<br>
                            Kundli, Sonipat, Haryana - 131028, India.
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <div>
                            <strong>General Enquiries:</strong> <a href="mailto:geetanjali.society@niftem.ac.in">geetanjali.society@niftem.ac.in</a> (example)
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-users"></i>
                        <div>
                            <strong>Faculty Coordinator:</strong> Prof. (Dr.) Advisor Name <br>
                            (Email: <a href="mailto:faculty.advisor@niftem.ac.in">faculty.advisor@niftem.ac.in</a> - example)
                        </div>
                    </li>
                </ul>

                <h3>Follow Us</h3>
                <div class="social-links-contact">
                    <a href="#" aria-label="Facebook" target="_blank" rel="noopener" class="social-icon"><i class="fab fa-facebook-f"></i> Facebook</a>
                    <a href="#" aria-label="Instagram" target="_blank" rel="noopener" class="social-icon"><i class="fab fa-instagram"></i> Instagram</a>
                    <a href="#" aria-label="LinkedIn" target="_blank" rel="noopener" class="social-icon"><i class="fab fa-linkedin-in"></i> LinkedIn</a>
                    </div>
            </div>

            <div class="contact-form-column">
                <h2>Send Us a Message</h2>
                <?php if (!empty($contact_form_status)) echo $contact_form_status; ?>
                <form id="contact-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject <span class="required">*</span></label>
                        <input type="text" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="message">Message <span class="required">*</span></label>
                        <textarea id="message" name="message" rows="6" required aria-required="true"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="submit_contact" class="cta-button">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="container content-section" data-aos="fade-up">
        <div id="map-container">
            <h2>Our Location</h2>
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3492.632810567815!2d77.0980078150459!3d28.87943508237984!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x390da4384fffffff%3A0x832be26c35b75755!2sNIFTEM%20Kundli!5e0!3m2!1sen!2sin!4v1620726128913!5m2!1sen!2sin" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </section>

</main>

<?php include 'includes/footer.php'; ?>