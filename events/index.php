<?php
/**
 * Geetanjali Website - Main Events Index
 *
 * This final version directs users to a dedicated registration page and
 * features an AJAX-powered waitlist system for full events. It is secure,
 * accessible, and built on a maintainable code structure.
 *
 * @version 7.0 (Final)
 * @date June 8, 2025
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Dependencies & Core Site Checks
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if (!isset($conn) || !$conn) {
    error_log("Critical database error. (Code: EIDX_DB01)");
    die("An unexpected error occurred. Please try again later.");
}
if (!defined('BASE_URL')) {
    error_log("Critical configuration error. (Code: EIDX_BU01)");
    die("Site configuration error. Please contact support.");
}


// --- REQUEST HANDLER (Handles Waitlist actions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $response = [];

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $response = ['status' => 'error', 'message' => 'Invalid security token. Refresh and try again.'];
    } elseif (!checkUserLoggedIn()) {
        $response = ['status' => 'error', 'message' => 'You must be logged in to perform this action.'];
    } else {
        $event_id = (int)$_POST['event_id'];
        $user_id = (int)$_SESSION['user_id'];
        $action = $_POST['action'];

        if ($action === 'join_waitlist') {
            $conn->begin_transaction();
            try {
                $sql_check = "SELECT (SELECT COUNT(id) FROM event_registrations WHERE event_id = ? AND user_id = ?) as is_registered,
                                     (SELECT COUNT(id) FROM event_waitlists WHERE event_id = ? AND user_id = ?) as is_on_waitlist";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("iiii", $event_id, $user_id, $event_id, $user_id);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();

                if ($check_result['is_registered'] > 0) throw new Exception("You are already registered for this event.");
                if ($check_result['is_on_waitlist'] > 0) throw new Exception("You are already on the waitlist.");

                $sql_waitlist = "INSERT INTO event_waitlists (event_id, user_id) VALUES (?, ?)";
                $stmt_waitlist = $conn->prepare($sql_waitlist);
                $stmt_waitlist->bind_param("ii", $event_id, $user_id);
                $stmt_waitlist->execute();
                $stmt_waitlist->close();
                $conn->commit();
                $response = ['status' => 'success', 'message' => 'You have been added to the waitlist!'];

            } catch (Exception $e) {
                $conn->rollback();
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Invalid action.'];
        }
    }
    
    // Final response for AJAX calls
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}


// --- DATA FETCHING & PAGE SETUP ---

function getEvents(mysqli $conn, array $options): array {
    $base_sql = "FROM events WHERE is_published = TRUE";
    $base_sql .= ($options['status'] === 'upcoming') ? " AND date_time >= CURDATE()" : " AND date_time < CURDATE()";
    
    $total_count = 0;
    if($result = $conn->query("SELECT COUNT(id) " . $base_sql)) {
        $total_count = (int)$result->fetch_row()[0];
    }
    
    $order_by = ($options['status'] === 'upcoming') ? "ORDER BY date_time ASC" : "ORDER BY date_time DESC";
    $limit_sql = " LIMIT ? OFFSET ?";
    
    $data_sql = "SELECT id, title, description, date_time, venue, registration_deadline, capacity, featured_image, event_type,
                (SELECT COUNT(er.id) FROM event_registrations er WHERE er.event_id = events.id) as registered_count " 
                . $base_sql . " " . $order_by . " " . $limit_sql;
    
    $events = [];
    if($stmt_data = $conn->prepare($data_sql)) {
        $offset = ($options['page'] - 1) * $options['items_per_page'];
        $stmt_data->bind_param("ii", $options['items_per_page'], $offset);
        $stmt_data->execute();
        $result = $stmt_data->get_result();
        while ($row = $result->fetch_assoc()) $events[] = $row;
        $stmt_data->close();
    }
    
    return ['events' => $events, 'total_count' => $total_count];
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$pageTitle = "Geetanjali Events Calendar";
$meta_description = "Explore upcoming and past literary events at Geetanjali. Join us for workshops, competitions, and more.";

$items_per_page = (int)getSetting($conn, 'events_items_per_page', 6);
$current_page_upcoming = max(1, isset($_GET['page_upcoming']) ? (int)$_GET['page_upcoming'] : 1);
$current_page_past = max(1, isset($_GET['page_past']) ? (int)$_GET['page_past'] : 1);

$upcoming_data = getEvents($conn, ['status' => 'upcoming', 'page' => $current_page_upcoming, 'items_per_page' => $items_per_page]);
$upcoming_events = $upcoming_data['events'];
$total_upcoming_events = $upcoming_data['total_count'];
$total_pages_upcoming = ceil($total_upcoming_events / $items_per_page);

$past_data = getEvents($conn, ['status' => 'past', 'page' => $current_page_past, 'items_per_page' => $items_per_page]);
$past_events = $past_data['events'];
$total_past_events = $past_data['total_count'];
$total_pages_past = ceil($total_past_events / $items_per_page);

$user_registered_events = [];
if (checkUserLoggedIn()) {
    $user_id = (int)$_SESSION['user_id'];
    if ($stmt_registered = $conn->prepare("SELECT event_id FROM event_registrations WHERE user_id = ?")) {
        $stmt_registered->bind_param("i", $user_id);
        if ($stmt_registered->execute()) {
            $result_registered = $stmt_registered->get_result();
            while ($row = $result_registered->fetch_assoc()) $user_registered_events[$row['event_id']] = true;
        }
        $stmt_registered->close();
    }
}


// --- TEMPLATE FUNCTIONS ---

function renderPagination($total_pages, $current_page, $section, $other_page_param, $other_page_value) {
    if ($total_pages <= 1) return '';
    $query_string = http_build_query([$other_page_param => $other_page_value]);
    if (!empty($query_string)) $query_string = '&' . $query_string;
    
    $html = '<nav class="pagination-container mt-5" aria-label="' . htmlspecialchars($section) . ' events navigation"><ul class="pagination-list">';
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page_' . $section . '=' . ($current_page - 1) . $query_string . '#' . $section . '-events-section"><i class="fas fa-chevron-left"></i> Prev</a></li>';
    }
    // Simplified loop for cleaner output, showing all pages is fine for moderate numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        $html .= '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="?page_' . $section . '=' . $i . $query_string . '#' . $section . '-events-section">' . $i . '</a></li>';
    }
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="?page_' . $section . '=' . ($current_page + 1) . $query_string . '#' . $section . '-events-section">Next <i class="fas fa-chevron-right"></i></a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

function renderEventCard($event, $is_upcoming, $user_registered_events, $csrf_token) {
    $event_image_path = !empty($event['featured_image']) ? BASE_URL . 'Uploads/events/' . sanitizeOutput($event['featured_image']) : BASE_URL . 'assets/images/default-event.jpg';
    $is_registered = isset($user_registered_events[$event['id']]);
    $capacity = (int)($event['capacity'] ?? 0);
    $registered_count = (int)($event['registered_count'] ?? 0);
    $is_registration_open = $is_upcoming;
    $event_full = false;
    $registration_deadline_passed = false;

    if ($is_upcoming && !empty($event['registration_deadline']) && time() > strtotime($event['registration_deadline'])) {
        $is_registration_open = false;
        $registration_deadline_passed = true;
    }
    if ($is_upcoming && $capacity > 0 && $registered_count >= $capacity) {
        $is_registration_open = false;
        $event_full = true;
    }

    ob_start();
    ?>
    <article id="event-card-<?php echo $event['id']; ?>" class="event-item-card <?php echo !$is_upcoming ? 'past' : ''; ?>" data-aos="fade-up" aria-labelledby="event-title-<?php echo $event['id']; ?>">
        <div class="event-item-image-wrapper">
             <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $event['id']; ?>" class="event-item-image-link" aria-label="View details for <?php echo htmlspecialchars($event['title']); ?>">
                <img src="<?php echo htmlspecialchars($event_image_path); ?>" alt="" class="event-item-image" loading="lazy">
            </a>
            <div class="event-item-date-badge">
                <span class="event-day"><?php echo date('d', strtotime($event['date_time'])); ?></span>
                <span class="event-month-year"><?php echo date('M \'y', strtotime($event['date_time'])); ?></span>
            </div>
            <?php if (!empty($event['event_type'])): ?>
                <span class="event-type-badge type-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $event['event_type']))); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $event['event_type']))); ?></span>
            <?php endif; ?>
        </div>
        <div class="event-item-content">
            <p class="event-item-meta">
                <span><i class="fas fa-<?php echo $is_upcoming ? 'clock' : 'calendar-check'; ?>"></i> <?php echo $is_upcoming ? time_elapsed_string($event['date_time']) : date('F j, Y', strtotime($event['date_time'])); ?></span>
                <?php if (!empty($event['venue'])): ?><span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?></span><?php endif; ?>
            </p>
            <h3 class="event-item-title" id="event-title-<?php echo $event['id']; ?>"><a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?></a></h3>
            <p class="event-item-excerpt"><?php echo htmlspecialchars(substr(strip_tags($event['description'] ?? ''), 0, 90)); ?>...</p>

            <?php if ($is_upcoming && $capacity > 0): $percentage_filled = $capacity > 0 ? round(($registered_count / $capacity) * 100) : 0; ?>
            <div class="event-capacity-info">
                <div class="progress-bar-event-container" title="<?php echo $percentage_filled; ?>% Filled">
                    <div class="progress-bar-event <?php if ($percentage_filled >= 90 && $percentage_filled < 100) echo 'nearing-full'; elseif ($percentage_filled >= 100) echo 'full'; ?>" style="width: <?php echo min(100, $percentage_filled); ?>%;"></div>
                </div>
                <small class="capacity-text"><?php echo $registered_count; ?> / <?php echo $capacity; ?> Spots</small>
            </div>
            <?php endif; ?>

            <div id="actions-<?php echo $event['id']; ?>" class="event-item-actions">
                <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $event['id']; ?>" class="cta-button btn-details"><?php echo !$is_upcoming ? 'View Gallery' : 'Details'; ?> <i class="fas fa-arrow-right"></i></a>
                <?php if ($is_upcoming): ?>
                    <?php if ($is_registered): ?>
                        <button class="cta-button btn-registered" disabled><i class="fas fa-check"></i> Registered</button>
                    <?php elseif ($event_full && checkUserLoggedIn()): ?>
                        <button class="cta-button btn-waitlist one-click-action" data-action="join_waitlist" data-event-id="<?php echo $event['id']; ?>" data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"><i class="fas fa-list-ol"></i> Join Waitlist</button>
                    <?php elseif ($is_registration_open && checkUserLoggedIn()): ?>
                        <a href="<?php echo BASE_URL; ?>events/register.php?id=<?php echo $event['id']; ?>" class="cta-button btn-register">Register Now <i class="fas fa-edit"></i></a>
                    <?php elseif (!checkUserLoggedIn()): ?>
                        <a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode(BASE_URL . 'events/register.php?id=' . $event['id']); ?>" class="cta-button btn-register-login">Login to Register</a>
                    <?php elseif ($registration_deadline_passed): ?>
                        <button class="cta-button btn-disabled" disabled><i class="fas fa-calendar-times"></i> Closed</button>
                    <?php else: ?>
                        <button class="cta-button btn-disabled" disabled><i class="fas fa-users"></i> Full</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="sr-only" id="action-status-<?php echo $event['id']; ?>" aria-live="polite"></div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}


// --- PAGE RENDER ---
include '../includes/header.php';
?>
<main id="page-content" class="events-page-container">
    <section class="page-title-section events-header-section text-center">
        <div class="container">
            <h1 id="page-title" data-aos="fade-down"><i class="fas fa-calendar-star me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="subtitle" data-aos="fade-down" data-aos-delay="100"><?php echo htmlspecialchars($meta_description); ?></p>
            <?php if (checkUserLoggedIn() && in_array($_SESSION['user_role'] ?? '', ['admin', 'super_admin'])): ?>
            <div class="admin-actions-bar mt-4" data-aos="fade-up" data-aos-delay="200">
                <a href="<?php echo BASE_URL; ?>admin/events.php?action=add" class="cta-button btn-admin-add"><i class="fas fa-plus-circle me-2"></i> Add New Event</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <div id="registration-status-container" aria-live="polite" aria-atomic="true">
        <div class="container mt-4 mb-4">
            <?php
            if (isset($_SESSION['flash_message'])) {
                echo $_SESSION['flash_message'];
                unset($_SESSION['flash_message']);
            }
            ?>
        </div>
    </div>

    <div class="container content-section">
        <section id="upcoming-events-section" class="events-list-section mb-5 pb-4">
            <h2 class="section-title-minor" id="upcoming-events-title"><i class="fas fa-hourglass-start me-2"></i> Upcoming Events <span class="event-count-badge"><?php echo $total_upcoming_events; ?></span></h2>
            <?php if (!empty($upcoming_events)): ?>
                <div class="event-grid-items">
                    <?php foreach ($upcoming_events as $event) echo renderEventCard($event, true, $user_registered_events, $csrf_token); ?>
                </div>
                <?php echo renderPagination($total_pages_upcoming, $current_page_upcoming, 'upcoming', 'page_past', $current_page_past); ?>
            <?php else: ?>
                <div class="no-results-message text-center" role="alert">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h4>No Upcoming Events Found</h4>
                    <p class="text-muted">We're busy planning our next literary adventure. Please check back soon!</p>
                </div>
            <?php endif; ?>
        </section>

        <hr class="section-divider">

        <section id="past-events-section" class="events-list-section mt-5 pt-4">
            <h2 class="section-title-minor" id="past-events-title"><i class="fas fa-history me-2"></i> Past Event Highlights <span class="event-count-badge"><?php echo $total_past_events; ?></span></h2>
            <?php if (!empty($past_events)): ?>
                <div class="event-grid-items past-events">
                    <?php foreach ($past_events as $event) echo renderEventCard($event, false, $user_registered_events, $csrf_token); ?>
                </div>
                <?php echo renderPagination($total_pages_past, $current_page_past, 'past', 'page_upcoming', $current_page_upcoming); ?>
            <?php else: ?>
                <div class="no-results-message text-center" role="alert">
                    <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                    <h4>No Past Events Found</h4>
                    <p class="text-muted">Our event history will be showcased here as we grow.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="eventToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header"><strong id="toastTitle" class="me-auto"></strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div>
    <div id="toastBody" class="toast-body"></div>
  </div>
</div>

<script type="application/ld+json">
[<?php 
    $event_schemas = [];
    foreach ($upcoming_events as $event) {
        $schema = ["@context" => "https://schema.org", "@type" => "Event", "name" => $event['title'], "startDate" => date('c', strtotime($event['date_time'])), "description" => substr(strip_tags($event['description'] ?? ''), 0, 200), "image" => !empty($event['featured_image']) ? BASE_URL . 'uploads/events/' . $event['featured_image'] : BASE_URL . 'assets/images/default-event.jpg', "eventStatus" => "https://schema.org/EventScheduled", "eventAttendanceMode" => "https://schema.org/OfflineEventAttendanceMode"];
        if (!empty($event['venue'])) { $schema['location'] = ["@type" => "Place", "name" => $event['venue']]; }
        $event_schemas[] = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    echo implode(",\n", $event_schemas);
?>]
</script>

<?php
include '../includes/footer.php';
?>