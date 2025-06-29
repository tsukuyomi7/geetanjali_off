<?php
/**
 * Event Registration Confirmation Page (Improved)
 *
 * This version includes embedded CSS, a live countdown timer, spots remaining,
 * and robust on-page error handling for a superior user experience.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// --- Security: User must be logged in ---
if (!checkUserLoggedIn()) {
    $_SESSION['redirect_url'] = BASE_URL . 'events/register.php?id=' . (int)($_GET['id'] ?? 0);
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// --- Validate Event ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Error: Invalid event ID provided.');
}
$event_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];
$error_message = ''; // To store errors from POST attempts

// --- Handle POST Request (Form Submission) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } elseif ((int)$_POST['event_id'] !== $event_id) {
        $error_message = 'There was a problem with your submission. Please try again.';
    } else {
        $conn->begin_transaction();
        try {
            // Re-validate event status within a transaction to prevent race conditions
            $sql_check = "SELECT title, capacity, registration_deadline, 
                         (SELECT COUNT(id) FROM event_registrations WHERE event_id = ?) as registered_count,
                         (SELECT COUNT(id) FROM event_registrations WHERE event_id = ? AND user_id = ?) as user_registered
                         FROM events WHERE id = ? AND is_published = TRUE AND date_time >= NOW() FOR UPDATE";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("iiii", $event_id, $event_id, $user_id, $event_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) throw new Exception("This event is no longer available for registration.");
            
            $event_data = $result_check->fetch_assoc();
            if ($event_data['user_registered'] > 0) throw new Exception("You are already registered for this event.");
            if (!empty($event_data['registration_deadline']) && time() > strtotime($event_data['registration_deadline'])) throw new Exception("The registration deadline has passed.");
            if ($event_data['capacity'] > 0 && $event_data['registered_count'] >= $event_data['capacity']) throw new Exception("Sorry, this event became full while you were registering.");

            // All checks passed, register the user
            $sql_register = "INSERT INTO event_registrations (event_id, user_id, registration_date) VALUES (?, ?, NOW())";
            $stmt_register = $conn->prepare($sql_register);
            $stmt_register->bind_param("ii", $event_id, $user_id);
            $stmt_register->execute();
            $conn->commit();

            $_SESSION['flash_message'] = '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success!</strong> You have registered for "'.htmlspecialchars($event_data['title']).'".<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            header('Location: ' . BASE_URL . 'events/');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            // Instead of redirecting, set the error message to be displayed on this page
            $error_message = $e->getMessage();
        }
    }
}


// --- Handle GET Request (Display Page) ---

$stmt = $conn->prepare("SELECT e.*, (SELECT COUNT(id) FROM event_registrations WHERE event_id = e.id) as registered_count FROM events e WHERE e.id = ? AND e.is_published = TRUE");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('This event could not be found or is not available.');
}
$event = $result->fetch_assoc();
$stmt->close();

// Determine Registration Status
$page_status = 'CAN_REGISTER';
$spots_left = ($event['capacity'] > 0) ? $event['capacity'] - $event['registered_count'] : 999;

if (strtotime($event['date_time']) < time()) $page_status = 'EVENT_PAST';
elseif (!empty($event['registration_deadline']) && time() > strtotime($event['registration_deadline'])) $page_status = 'DEADLINE_PASSED';
elseif ($event['capacity'] > 0 && $event['registered_count'] >= $event['capacity']) $page_status = 'EVENT_FULL';

// Check if user is already registered (overrides other statuses)
$stmt_check = $conn->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?");
$stmt_check->bind_param("ii", $event_id, $user_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $page_status = 'ALREADY_REGISTERED';
}
$stmt_check->close();

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];
$pageTitle = "Register for " . htmlspecialchars($event['title']);
include '../includes/header.php';
?>
<style>
    :root {
        --primary-accent: #0d6efd;
        --success-color: #198754;
        --info-color: #0dcaf0;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --light-bg: #f8f9fa;
        --border-radius: 0.5rem;
        --card-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.1);
    }
    body.register-page-body {
        background-color: var(--light-bg);
    }
    .registration-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden; /* To keep image corners rounded */
    }
    .registration-card .card-header {
        background: linear-gradient(135deg, var(--primary-accent), #0558c7);
        border-bottom: 0;
        padding: 1.5rem;
    }
    .registration-card .card-header .h2 {
        margin: 0;
        font-weight: 600;
    }
    .registration-container {
        display: flex;
        flex-wrap: wrap;
    }
    .event-image-column {
        background-size: cover;
        background-position: center;
        min-height: 250px;
    }
    .event-details-column {
        padding: 2rem;
    }
    .event-title {
        font-weight: 600;
        font-size: 1.75rem;
        color: #333;
    }
    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }
    .meta-item i {
        color: var(--primary-accent);
        font-size: 1.25rem;
        width: 25px;
        text-align: center;
    }
    .status-banner {
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        text-align: center;
        color: #fff;
    }
    .status-banner.info { background-color: var(--info-color); }
    .status-banner.success { background-color: var(--success-color); }
    .status-banner.danger { background-color: var(--danger-color); }
    .status-banner i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }
    
    #countdown-timer, .spots-left {
        background-color: #e9ecef;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-weight: 500;
        display: inline-block;
        margin-top: 1rem;
    }
    #countdown-timer.ending-soon { background-color: var(--warning-color); color: #000; }
    .btn-confirm {
        font-size: 1.2rem;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: transform 0.2s;
    }
    .btn-confirm:hover {
        transform: scale(1.02);
    }

    @media (min-width: 768px) {
        .event-image-column {
            flex: 0 0 300px;
        }
        .event-details-column {
            flex: 1;
        }
    }
</style>

<body class="register-page-body">
    <main id="page-content" class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-md-10">
                <div class="registration-card">
                    <div class="registration-container">
                        <?php 
                            $event_image_path = !empty($event['featured_image']) 
                                ? BASE_URL . 'Uploads/events/' . sanitizeOutput($event['featured_image']) 
                                : BASE_URL . 'assets/images/default-event.jpg';
                        ?>
                        <div class="event-image-column" style="background-image: url('<?php echo htmlspecialchars($event_image_path); ?>');"></div>
                        
                        <div class="event-details-column">
                            <p class="text-muted text-uppercase small">Confirm Your Spot</p>
                            <h1 class="event-title mb-4"><?php echo htmlspecialchars($event['title']); ?></h1>
                            
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt fa-fw"></i>
                                <span><?php echo date('l, F j, Y \a\t g:i A', strtotime($event['date_time'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-map-marker-alt fa-fw"></i>
                                <span><?php echo htmlspecialchars($event['venue']); ?></span>
                            </div>

                            <hr class="my-4">

                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>

                            <?php // Display correct status banner and form
                            switch ($page_status):
                                case 'ALREADY_REGISTERED': ?>
                                    <div class="status-banner success">
                                        <i class="fas fa-check-circle"></i>
                                        <h4>You are already registered!</h4>
                                        <p class="mb-0">We look forward to seeing you at the event.</p>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>events/" class="btn btn-outline-secondary w-100">Back to Events</a>
                                    <?php break; ?>

                                <?php case 'EVENT_FULL': case 'DEADLINE_PASSED': case 'EVENT_PAST': ?>
                                    <div class="status-banner danger">
                                        <i class="fas fa-times-circle"></i>
                                        <h4>Registration is Closed</h4>
                                        <p class="mb-0">
                                            <?php 
                                            if ($page_status === 'EVENT_FULL') echo 'This event is now full.';
                                            elseif ($page_status === 'DEADLINE_PASSED') echo 'The registration deadline has passed.';
                                            else echo 'This event has already taken place.';
                                            ?>
                                        </p>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>events/" class="btn btn-outline-secondary w-100">Back to Events</a>
                                    <?php break; ?>

                                <?php case 'CAN_REGISTER': default: ?>
                                    <p class="text-center">You are registering as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>.</p>
                                    
                                    <form method="POST" action="">
                                        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-success btn-lg btn-confirm">
                                                <i class="fas fa-check me-2"></i>Confirm My Registration
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>events/" class="btn btn-outline-secondary">Cancel</a>
                                        </div>
                                    </form>

                                    <?php if ($event['capacity'] > 0): ?>
                                        <div class="text-center mt-3">
                                            <span class="spots-left"><i class="fas fa-users me-2"></i><strong><?php echo $spots_left; ?></strong> spot(s) left!</span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($event['registration_deadline'])): ?>
                                        <div class="text-center mt-3">
                                            <div id="countdown-timer" data-deadline="<?php echo date('c', strtotime($event['registration_deadline'])); ?>">
                                                <i class="fas fa-hourglass-half me-2"></i> Registration closes in...
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php break; ?>
                            <?php endswitch; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const countdownElement = document.getElementById('countdown-timer');
    if (countdownElement) {
        const deadline = new Date(countdownElement.dataset.deadline).getTime();

        const countdownInterval = setInterval(function() {
            const now = new Date().getTime();
            const distance = deadline - now;

            if (distance < 0) {
                clearInterval(countdownInterval);
                countdownElement.innerHTML = '<i class="fas fa-times-circle me-2"></i>Registration has closed.';
                countdownElement.classList.add('ending-soon');
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            let output = 'Registration closes in: ';
            if (days > 0) output += `${days}d ${hours}h ${minutes}m ${seconds}s`;
            else if (hours > 0) output += `${hours}h ${minutes}m ${seconds}s`;
            else output += `${minutes}m ${seconds}s`;
            
            countdownElement.innerHTML = `<i class="fas fa-hourglass-half me-2"></i> ${output}`;

            // Add visual warning when time is running out
            if (distance < (1000 * 60 * 60 * 24)) { // Less than 1 day
                countdownElement.classList.add('ending-soon');
            }
        }, 1000);
    }
});
</script>

<?php include '../includes/footer.php'; ?>