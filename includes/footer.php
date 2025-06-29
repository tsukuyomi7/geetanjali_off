</main> <?php // Closing the main tag opened in index.php or other content files ?>
    <!-- <footer id="main-footer-section">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column about-column">
                    <img src="<?php echo BASE_URL; ?>assets/images/geetanjali.jpg" alt="Geetanjali Logo" class="footer-logo">
                    <p>Geetanjali, The Literary Society of NIFTEM-Kundli, nurturing creativity and a love for literature. Affiliated with the National Institute of Food Technology Entrepreneurship and Management, Sonipat, Haryana.</p>
                     <p><strong>NIFTEM-Kundli:</strong> Plot No. 97, Sector 56, HSIIDC Industrial Estate, Kundli, Sonipat, Haryana - 131028</p>
                </div>

                <div class="footer-column links-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>index.php">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php">About Us</a></li>
                        <li><a href="<?php echo BASE_URL; ?>events/">Events</a></li>
                        <li><a href="<?php echo BASE_URL; ?>blog/">Blog</a></li>
                        <li><a href="<?php echo BASE_URL; ?>gallery/">Gallery</a></li>
                        <li><a href="<?php echo BASE_URL; ?>contact.php">Contact Us</a></li>
                        <li><a href="<?php echo BASE_URL; ?>register.php">Join Us</a></li>
                        </ul>
                </div>

                <div class="footer-column contact-column">
                    <h4>Get In Touch</h4>
                    <p><i class="fas fa-envelope"></i> Email: <a href="mailto:geetanjali.society@niftem.ac.in">geetanjali.society@niftem.ac.in</a> (example)</p>
                    <p><i class="fas fa-map-marker-alt"></i> NIFTEM-Kundli, Sonipat, Haryana</p>
                    <div class="social-links-footer">
                        <a href="#" aria-label="Facebook" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="LinkedIn" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> Geetanjali - The Literary Society, NIFTEM-Kundli. All rights reserved.</p>
                <p>
                    <a href="https://www.niftem.ac.in/" target="_blank" rel="noopener">NIFTEM-Kundli Official Website</a>
                    <?php /* Optional: | <a href="<?php echo BASE_URL; ?>privacy.php">Privacy Policy</a> | <a href="<?php echo BASE_URL; ?>terms.php">Terms of Use</a> */ ?>
                </p>
            </div>
        </div>
         <button onclick="scrollToTop()" id="backToTopBtn" title="Go to top"><i class="fas fa-arrow-up"></i></button>
    </footer> -->

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="<?php echo BASE_URL; ?>js/script.js"></script>
    <?php if (isset($additional_js) && is_array($additional_js)): ?>
        <?php foreach ($additional_js as $js_file): ?>
            <script src="<?php echo BASE_URL . htmlspecialchars($js_file); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>