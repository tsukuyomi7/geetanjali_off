<?php
// events/view.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$event = null;
$pageTitle = "Event Not Found"; // Default title

if ($event_id > 0) {
    // IMPROVEMENT: Unified query to get all necessary data at once.
    $sql = "SELECT e.*, 
                   u.name as creator_name,
                   (SELECT COUNT(id) FROM event_registrations WHERE event_id = e.id) as registered_count,
                   (SELECT COUNT(id) FROM event_registrations WHERE event_id = e.id AND user_id = ?) as user_is_registered
            FROM events e 
            LEFT JOIN users u ON e.created_by = u.id 
            WHERE e.id = ? AND e.is_published = TRUE";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $event_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $event = $result->fetch_assoc();
            $pageTitle = htmlspecialchars($event['title']);

            // Post-fetch processing to determine the event's status
            $event['is_past'] = strtotime($event['date_time']) < time();
            $event['deadline_passed'] = !$event['is_past'] && !empty($event['registration_deadline']) && time() > strtotime($event['registration_deadline']);
            $event['is_full'] = !$event['is_past'] && $event['capacity'] > 0 && $event['registered_count'] >= $event['capacity'];
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for event fetching: " . $conn->error);
    }
}

// CSRF token for the waitlist form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

include '../includes/header.php';
?>

<style>
    :root {
        --primary-color: #0d6efd;
        --success-color: #198754;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --light-gray: #f8f9fa;
        --dark-text: #212529;
        --card-shadow: 0 4px 15px rgba(0,0,0,0.08);
        --border-radius: .5rem;
    }
    .event-view-page .page-content { padding-top: 0; }

    /* --- Hero Section --- */
    .event-title-hero {
        padding: 5rem 0;
        text-align: center;
        color: white;
        background-color: #333; /* Fallback color */
        background-size: cover;
        background-position: center;
        position: relative;
    }
    .event-title-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(0deg, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.4));
    }
    .event-title-hero .container { position: relative; z-index: 2; }
    .event-title-hero h1 { font-size: 3rem; font-weight: 700; text-shadow: 2px 2px 5px rgba(0,0,0,0.5); }
    .event-title-hero .event-meta-hero { font-size: 1.1rem; opacity: 0.9; margin-top: 0.5rem; }
    .event-title-hero .event-meta-hero i { margin: 0 0.5rem; }

    /* --- Main Layout --- */
    .event-details-section { padding-top: 3rem; padding-bottom: 3rem; }
    .event-content-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }
    @media (min-width: 992px) {
        .event-content-layout { grid-template-columns: 1fr 320px; }
    }
    .event-main-content h2, .event-main-content h3 { font-weight: 600; margin-bottom: 1.5rem; }
    .event-main-content .event-description {
        font-size: 1.1rem;
        line-height: 1.7;
    }
    .event-main-content .event-description p { margin-bottom: 1em; }
    .event-info-list { list-style: none; padding-left: 0; }
    .event-info-list li { padding: .5rem 0; border-bottom: 1px solid #eee; }
    .event-info-list li:last-child { border-bottom: none; }
    
    /* --- Sidebar Widgets --- */
    .event-sidebar .sidebar-widget {
        background: white;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border: 1px solid #e9ecef;
        box-shadow: var(--card-shadow);
    }
    .event-sidebar h4 { font-weight: 600; margin-bottom: 1rem; padding-bottom: .5rem; }
    .registration-widget .alert { font-size: 1rem; text-align: center; }
    .registration-widget .cta-button { text-align: center; display: block; width: 100%; padding: .75rem; font-size: 1.1rem; text-decoration: none; }
    .btn-register { background-color: var(--success-color); color: white; }
    .btn-waitlist { background-color: var(--warning-color); color: #212529; }

    .share-widget .social-share-links { display: flex; justify-content: center; gap: 1rem; }
    .share-widget .social-share-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        text-decoration: none;
        font-size: 1.1rem;
        transition: transform 0.2s;
    }
    .share-widget .social-share-link:hover { transform: scale(1.1); }
    .ss-facebook { background: #3b5998; }
    .ss-twitter { background: #1da1f2; }
    .ss-linkedin { background: #0077b5; }
</style>

<div class="event-view-page">
<main id="page-content">
    <?php if ($event): ?>
        <?php $hero_image_path = !empty($event['featured_image']) ? BASE_URL . 'Uploads/events/' . sanitizeOutput($event['featured_image']) : ''; ?>
        <section class="page-title-section event-title-hero" style="background-image: url('<?php echo htmlspecialchars($hero_image_path); ?>');">
            <div class="container">
                <h1><?php echo htmlspecialchars($event['title']); ?></h1>
                <p class="event-meta-hero">
                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y \a\t g:i A', strtotime($event['date_time'])); ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?></span>
                </p>
            </div>
        </section>

        <section class="container content-section event-details-section">
            <div class="event-content-layout">
                <div class="event-main-content">
                    <h2>About This Event</h2>
                    <div class="event-description">
                        <?php
                            // SECURITY NOTE: For a production site, sanitize user-generated content upon input.
                            // To allow safe HTML (<b>, <i>, <ul> etc.) from a WYSIWYG editor,
                            // use a library like HTML Purifier on output instead of htmlspecialchars.
                            echo nl2br($event['description']); 
                        ?>
                    </div>

                    <hr class="my-5">
                    
                    <h3>Event Information</h3>
                    <ul class="event-info-list">
                        <li><strong>Date & Time:</strong> <?php echo date('l, F j, Y - g:i A', strtotime($event['date_time'])); ?></li>
                        <li><strong>Location:</strong> <?php echo htmlspecialchars($event['venue']); ?></li>
                        <?php if ($event['capacity'] > 0): ?>
                            <li><strong>Capacity:</strong> <?php echo htmlspecialchars($event['capacity']); ?> attendees</li>
                        <?php endif; ?>
                        <?php if (!empty($event['registration_deadline'])): ?>
                            <li><strong>Registration Deadline:</strong> 
                                <?php 
                                $deadline = strtotime($event['registration_deadline']);
                                echo date('F j, Y - g:i A', $deadline); 
                                if (time() > $deadline) {
                                    echo ' <span class="text-danger">(Passed)</span>';
                                }
                                ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($event['creator_name'])): ?>
                            <li><strong>Posted By:</strong> <?php echo htmlspecialchars($event['creator_name']); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <aside class="event-sidebar">
                    <div class="sidebar-widget registration-widget">
                        <h4>Registration</h4>
                        <?php if ($event['user_is_registered']): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle"></i> You are registered for this event!</div>
                        <?php elseif ($event['is_past']): ?>
                            <div class="alert alert-secondary"><i class="fas fa-history"></i> This event has ended.</div>
                        <?php elseif ($event['deadline_passed']): ?>
                            <div class="alert alert-danger"><i class="fas fa-times-circle"></i> Registration has closed.</div>
                        <?php elseif ($event['is_full']): ?>
                            <div class="alert alert-warning">This event is currently full.</div>
                            <?php if(checkUserLoggedIn()): ?>
                                <button class="cta-button btn-waitlist one-click-action" data-action="join_waitlist" data-event-id="<?php echo $event['id']; ?>" data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"><i class="fas fa-list-ol"></i> Join Waitlist</button>
                            <?php else: ?>
                                <p class="text-center small mt-2">Please <a href="<?php echo BASE_URL; ?>login.php">log in</a> to join the waitlist.</p>
                            <?php endif; ?>
                        <?php else: // Registration is open ?>
                            <p class="mb-3">Secure your spot for this event.</p>
                            <?php if (checkUserLoggedIn()): ?>
                                <a href="<?php echo BASE_URL; ?>events/register.php?id=<?php echo $event['id']; ?>" class="cta-button btn-register">Register Now</a>
                            <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode(BASE_URL . 'events/register.php?id=' . $event['id']); ?>" class="cta-button btn-register">Login to Register</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="sidebar-widget share-widget text-center">
                        <h4>Share this Event</h4>
                        <div class="social-share-links">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(getCurrentUrl()); ?>" target="_blank" class="social-share-link ss-facebook" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(getCurrentUrl()); ?>&text=<?php echo urlencode($event['title']); ?>" target="_blank" class="social-share-link ss-twitter" aria-label="Share on Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(getCurrentUrl()); ?>" target="_blank" class="social-share-link ss-linkedin" aria-label="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

    <?php else: ?>
        <section class="container content-section text-center py-5">
            <div class="alert alert-danger">
                <h2><i class="fas fa-exclamation-triangle"></i> Event Not Found</h2>
                <p>Sorry, the event you are looking for does not exist or may have been removed.</p>
                <a href="<?php echo BASE_URL; ?>events/" class="btn btn-primary mt-3">Back to Events List</a>
            </div>
        </section>
    <?php endif; ?>
</main>
</div>

<?php include '../includes/footer.php'; ?>