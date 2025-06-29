    <?php
    // events/view.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; // Defines $conn and BASE_URL
    require_once '../includes/functions.php';     // For checkUserLoggedIn() or other helpers

    $event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $event = null;
    $pageTitle = "Event Details"; // Default title

    if ($event_id > 0) {
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("SELECT e.id, e.title, e.description, e.date_time, e.venue, e.registration_deadline, e.capacity, u.name as creator_name 
                                FROM events e 
                                LEFT JOIN users u ON e.created_by = u.id 
                                WHERE e.id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $event = $result->fetch_assoc();
                $pageTitle = htmlspecialchars($event['title']); // Set page title to event title
            }
            $stmt->close();
        } else {
            // Log database error
            error_log("Failed to prepare statement for event fetching: " . $conn->error);
            // You could set an error message to display to the user
        }
    }

    include '../includes/header.php'; // Navbar is included within header.php
    ?>

    <main id="page-content">
        <?php if ($event): ?>
            <section class="page-title-section event-title-hero" style="<?php /* You could add a dynamic background image here if events have images */ ?>">
                <div class="container">
                    <h1><?php echo htmlspecialchars($event['title']); ?></h1>
                    <p class="event-meta-hero">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($event['date_time'])); ?>
                        at <?php echo date('g:i A', strtotime($event['date_time'])); ?>
                        <br>
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?>
                    </p>
                </div>
            </section>

            <section class="container content-section event-details-section">
                <div class="event-content-layout">
                    <div class="event-main-content">
                        <h2>About This Event</h2>
                        <div class="event-description">
                            <?php echo nl2br(htmlspecialchars($event['description'])); // nl2br to respect line breaks ?>
                        </div>

                        <hr class="my-4"> <?php // Bootstrap class, or use custom styling ?>
                        
                        <h3>Event Information</h3>
                        <ul class="event-info-list">
                            <li><strong>Date & Time:</strong> <?php echo date('l, F j, Y - g:i A', strtotime($event['date_time'])); ?></li>
                            <li><strong>Location:</strong> <?php echo htmlspecialchars($event['venue']); ?></li>
                            <?php if ($event['capacity']): ?>
                                <li><strong>Capacity:</strong> <?php echo htmlspecialchars($event['capacity']); ?> attendees</li>
                            <?php endif; ?>
                            <?php if ($event['registration_deadline']): ?>
                                <li><strong>Registration Deadline:</strong> 
                                    <?php 
                                    $deadline = strtotime($event['registration_deadline']);
                                    echo date('F j, Y - g:i A', $deadline); 
                                    if (time() > $deadline) {
                                        echo ' <span class="text-danger">(Deadline Passed)</span>';
                                    }
                                    ?>
                                </li>
                            <?php endif; ?>
                            <?php if ($event['creator_name']): ?>
                                <?php endif; ?>
                        </ul>
                    </div>

                    <aside class="event-sidebar">
                        <div class="sidebar-widget registration-widget">
                            <h4>Register for this Event</h4>
                            <?php
                            $can_register = true;
                            if ($event['registration_deadline'] && time() > strtotime($event['registration_deadline'])) {
                                echo "<p class='alert alert-warning'>Registration for this event has closed.</p>";
                                $can_register = false;
                            }
                            // You would also check for event capacity if implemented fully
                            
                            if ($can_register):
                                if (checkUserLoggedIn()):
                                    // Check if user is already registered (you'll need a function for this)
                                    // $is_registered = checkIfUserRegistered($conn, $_SESSION['user_id'], $event_id);
                                    $is_registered = false; // Placeholder
                                    if ($is_registered):
                            ?>
                                        <p class="alert alert-success">You are already registered for this event!</p>
                                        <a href="<?php echo BASE_URL; ?>student/registrations.php" class="cta-button cta-button-outline">View My Registrations</a>
                            <?php   else: ?>
                                        <form action="<?php echo BASE_URL; ?>events/register.php" method="POST">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ''; ?>">
                                            <button type="submit" name="register_event" class="cta-button cta-full-width">Register Now</button>
                                        </form>
                            <?php
                                    endif;
                                else:
                            ?>
                                <p>Please <a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">login</a> or <a href="<?php echo BASE_URL; ?>register.php">register</a> to sign up for this event.</p>
                            <?php endif; ?>
                            <?php endif; // end $can_register check ?>
                        </div>

                        <div class="sidebar-widget share-widget">
                            <h4>Share this Event</h4>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(BASE_URL . 'events/view.php?id=' . $event['id']); ?>" target="_blank" class="social-share-link"><i class="fab fa-facebook-f"></i> Facebook</a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . 'events/view.php?id=' . $event['id']); ?>&text=<?php echo urlencode($event['title']); ?>" target="_blank" class="social-share-link"><i class="fab fa-twitter"></i> Twitter</a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(BASE_URL . 'events/view.php?id=' . $event['id']); ?>&title=<?php echo urlencode($event['title']); ?>" target="_blank" class="social-share-link"><i class="fab fa-linkedin-in"></i> LinkedIn</a>
                        </div>
                        
                        <?php /*
                        <div class="sidebar-widget related-events-widget">
                            <h4>Related Events</h4>
                            <ul>
                                <li><a href="#">Another Interesting Event</a></li>
                                <li><a href="#">Upcoming Workshop</a></li>
                            </ul>
                        </div>
                        */ ?>
                    </aside>
                </div>
            </section>

        <?php else: ?>
            <section class="container content-section">
                <div class="alert alert-danger text-center">
                    <h2>Event Not Found</h2>
                    <p>Sorry, the event you are looking for does not exist or may have been removed.</p>
                    <a href="<?php echo BASE_URL; ?>events/" class="cta-button">Back to Events List</a>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php include '../includes/footer.php'; ?>
    