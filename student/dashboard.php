<?php
// geetanjali_website/student/dashboard.php (Improved Version)

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- 1. CRITICAL INCLUDES AND CHECKS ---
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if (!isset($conn) || !$conn instanceof mysqli) { die("Critical DB connection error."); }
if (!defined('BASE_URL')) { die("Critical configuration error (BASE_URL)."); }
$required_functions = ['enforceRoleAccess', 'getUserById', 'sanitizeOutput', 'time_elapsed_string'];
foreach ($required_functions as $func) { if (!function_exists($func)) die("Critical function '$func' missing."); }

enforceRoleAccess(['student', 'moderator', 'blog_editor', 'event_manager', 'admin', 'super_admin']);
if (!isset($_SESSION['user_id'])) { header("Location: " . BASE_URL . "login.php?auth_error=session_invalid"); exit; }

$user_id = $_SESSION['user_id'];
$pageTitle = "My Dashboard";


// --- 2. CONSOLIDATED DATA FETCHING ---
function get_dashboard_data($conn, $user_id) {
    $data = [];
    $data['user'] = getUserById($conn, $user_id);
    if (!$data['user']) return null;

    // Main Stats Grid
    $stmt = $conn->prepare("SELECT
        (SELECT COUNT(*) FROM event_registrations er JOIN events e ON er.event_id = e.id WHERE er.user_id = ? AND e.date_time >= NOW()) as upcoming_events,
        (SELECT COUNT(*) FROM certificates WHERE user_id = ?) as certificates,
        (SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status = 'Approved') as approved_works,
        (SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status IN ('Pending', 'Under Review')) as pending_submissions
    ");
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $data['stats'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Data for Charts
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM submissions WHERE user_id = ? GROUP BY status");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data['chart_data']['submissions'] = ['Approved' => 0, 'Pending' => 0, 'Revision' => 0];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Approved' || $row['status'] === 'Published') $data['chart_data']['submissions']['Approved'] += $row['count'];
        if ($row['status'] === 'Pending' || $row['status'] === 'Under Review') $data['chart_data']['submissions']['Pending'] += $row['count'];
        if ($row['status'] === 'Requires Revision') $data['chart_data']['submissions']['Revision'] += $row['count'];
    }
    $stmt->close();
    
    // Profile Completeness
    $profile_fields = ['profile_picture' => 25, 'department' => 25, 'bio' => 20, 'interests_list' => 15, 'skills_list' => 15];
    $filled_weight = 0;
    foreach ($profile_fields as $field => $weight) { if (!empty($data['user'][$field])) { $filled_weight += $weight; } }
    $data['profile_completion_percent'] = round(($filled_weight / array_sum($profile_fields)) * 100);
    
    // Sidebar Widgets Data
    $data['announcements'] = $conn->query("SELECT id, title, excerpt, link FROM announcements WHERE is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY priority DESC, created_at DESC LIMIT 2")->fetch_all(MYSQLI_ASSOC);
    $data['upcoming_deadlines'] = $conn->query("SELECT id, title, registration_deadline FROM events WHERE registration_deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND is_published = 1 ORDER BY registration_deadline ASC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
    $data['community_showcase'] = $conn->query("SELECT bp.title, bp.slug, u.name as author_name FROM blog_posts bp JOIN users u ON bp.author_id = u.id WHERE bp.is_published = 1 AND bp.submission_id IS NOT NULL ORDER BY bp.published_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("SELECT id, title, status FROM submissions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 3");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $data['recent_submissions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $data;
}

// --- 3. PAGE-LEVEL PROCESSING ---
$dashboard_data = get_dashboard_data($conn, $user_id);
if (!$dashboard_data) { header("Location: " . BASE_URL . "logout.php"); exit; }
extract($dashboard_data);

$user_name = sanitizeOutput($user['name']);
$user_profile_picture_path = (!empty($user['profile_picture'])) ? BASE_URL . 'uploads/profile/' . sanitizeOutput($user['profile_picture']) : BASE_URL . 'assets/images/placeholder-avatar.png';
$time_greeting = "Good " . (date('G') < 12 ? "Morning" : (date('G') < 18 ? "Afternoon" : "Evening"));
$daily_quote = [["quote" => "The books that the world calls immoral are books that show the world its own shame.", "author" => "Oscar Wilde"],["quote" => "A room without books is like a body without a soul.", "author" => "Marcus Tullius Cicero"]][array_rand([0,1])];

// Prepare data for JS charts
$profile_chart_js_data = json_encode([$profile_completion_percent, 100 - $profile_completion_percent]);
$submissions_chart_js_data = json_encode(array_values($chart_data['submissions']));

// --- 4. HTML VIEW ---
include '../includes/header.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    /* Small style additions for new & improved widgets */
    .register-btn { float: right; }
    .deadline-item .btn-success, .deadline-item .btn-info { cursor: default; }
    .card-style-admin .sidebar-list p { margin-bottom: 0; }
    .card-style-admin .sidebar-list li { padding-bottom: 1rem; }
    .chart-container { position: relative; height: 160px; width: 100%; }
</style>

<div class="std-dash-container"> 
    <div class="container"> 
        <header class="std-dash-header">
            <div class="std-dash-user-info">
                <img src="<?php echo $user_profile_picture_path; ?>" alt="Profile Picture" class="std-dash-profile-pic">
                <div>
                    <h1 class="std-dash-greeting"><?php echo $time_greeting; ?>, <?php echo $user_name; ?>!</h1>
                    <p class="std-dash-subtitle">Here’s your personalized hub for all things Geetanjali.</p>
                </div>
            </div>
            <div class="std-dash-header-quote"><i class="fas fa-quote-left"></i><p>"<?php echo htmlspecialchars($daily_quote['quote']); ?>"</p><cite>- <?php echo htmlspecialchars($daily_quote['author']); ?></cite></div>
        </header>

        <section class="dashboard-section std-dash-metrics-grid">
            <div class="std-dash-metric-card upcoming-events"><div class="metric-icon"><i class="fas fa-calendar-check fa-3x"></i></div><div class="metric-value"><?php echo $stats['upcoming_events']; ?></div><div class="metric-label">Upcoming Events</div><a href="<?php echo BASE_URL; ?>events" class="metric-card-link">View All <i class="fas fa-chevron-right"></i></a></div>
            <div class="std-dash-metric-card certificates-earned"><div class="metric-icon"><i class="fas fa-award fa-3x"></i></div><div class="metric-value"><?php echo $stats['certificates']; ?></div><div class="metric-label">Certificates Earned</div><a href="<?php echo BASE_URL; ?>student/certificates.php" class="metric-card-link">My Certificates <i class="fas fa-chevron-right"></i></a></div>
            <div class="std-dash-metric-card approved-works"><div class="metric-icon"><i class="fas fa-file-check fa-3x"></i></div><div class="metric-value"><?php echo $stats['approved_works']; ?></div><div class="metric-label">Approved Works</div><a href="<?php echo BASE_URL; ?>student/my_submissions.php" class="metric-card-link">View All <i class="fas fa-chevron-right"></i></a></div>
            <div class="std-dash-metric-card pending-submissions"><div class="metric-icon"><i class="fas fa-hourglass-half fa-3x"></i></div><div class="metric-value"><?php echo $stats['pending_submissions']; ?></div><div class="metric-label">Pending Submissions</div><a href="<?php echo BASE_URL; ?>student/my_submissions.php" class="metric-card-link">Track Status <i class="fas fa-chevron-right"></i></a></div>
        </section>

        <div class="std-dash-columns">
            <div class="std-dash-main-col">
                <section class="dashboard-section std-dash-quick-actions" data-aos="fade-right">
                    <h3 class="std-dash-widget-title">Your Toolkit</h3>
                    <div class="quick-actions-grid">
                        <a href="<?php echo BASE_URL; ?>student/submit_work.php" class="action-button submit-work-action"><i class="fas fa-feather-alt"></i><span>Submit Work</span></a>
                        <a href="<?php echo BASE_URL; ?>student/my_submissions.php" class="action-button"><i class="fas fa-folder-open"></i><span>My Submissions</span></a>
                        <a href="<?php echo BASE_URL; ?>events/" class="action-button"><i class="fas fa-calendar-plus"></i><span>Find Events</span></a>
                        <a href="<?php echo BASE_URL; ?>blog/" class="action-button"><i class="fas fa-book-reader"></i><span>Read Our Blog</span></a>
                        <a href="<?php echo BASE_URL; ?>student/profile.php#digital-id" class="action-button"><i class="fas fa-id-badge"></i><span>My Digital ID</span></a>
                        <a href="<?php echo BASE_URL; ?>contact.php" class="action-button"><i class="fas fa-headset"></i><span>Contact Support</span></a>
                    </div>
                </section>

                <section class="dashboard-section std-dash-activity-feed alt-background" data-aos="fade-right" data-aos-delay="100">
                    <h3 class="std-dash-widget-title">Activity & Notifications</h3>
                    <div id="activity-feed-container"><div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div></div>
                </section>
            </div>

            <aside class="std-dash-sidebar-col">
                <section class="std-dash-sidebar-widget card-style-admin" data-aos="fade-left">
                    <h4 class="std-dash-widget-title"><i class="fas fa-bullhorn"></i> Announcements</h4>
                    <?php if (!empty($announcements)): ?>
                        <ul class="sidebar-list">
                            <?php foreach($announcements as $item): ?><li><a href="<?php echo htmlspecialchars($item['link'] ?: '#'); ?>"><strong><?php echo sanitizeOutput($item['title']); ?></strong></a><p class="mb-0"><?php echo sanitizeOutput($item['excerpt']); ?></p></li><?php endforeach; ?>
                        </ul>
                    <?php else: ?><p>No new announcements.</p><?php endif; ?>
                </section>
                
                <section class="std-dash-sidebar-widget card-style-admin" data-aos="fade-left" data-aos-delay="100">
                    <h4 class="std-dash-widget-title"><i class="fas fa-hourglass-end"></i> Registration Deadlines</h4>
                    <div id="deadline-feedback" class="mb-2"></div>
                    <?php if (!empty($upcoming_deadlines)): ?>
                        <ul class="sidebar-list">
                            <?php foreach($upcoming_deadlines as $event): ?>
                            <li class="deadline-item">
                                <button class="btn btn-sm btn-primary register-btn" data-event-id="<?php echo $event['id']; ?>">Register</button>
                                <a href="<?php echo BASE_URL . 'events/view.php?id=' . $event['id']; ?>"><strong><?php echo sanitizeOutput($event['title']); ?></strong></a>
                                <p class="deadline-date-sidebar">Closes: <?php echo date('M j, g:i A', strtotime($event['registration_deadline'])); ?></p>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?><p>No immediate deadlines.</p><?php endif; ?>
                </section>

                <section class="std-dash-sidebar-widget card-style-admin" data-aos="fade-left" data-aos-delay="200">
                    <h4 class="std-dash-widget-title"><i class="fas fa-feather-alt"></i> My Recent Submissions</h4>
                    <?php if (!empty($recent_submissions)): ?>
                        <ul class="sidebar-list">
                            <?php foreach($recent_submissions as $sub): ?>
                            <li>
                                <a href="<?php echo BASE_URL . 'student/my_submissions.php#submission-' . $sub['id']; ?>"><strong><?php echo sanitizeOutput(substr($sub['title'], 0, 30)); ?>...</strong></a>
                                <p>Status: <span class="submission-status status-<?php echo strtolower(str_replace(' ', '-', $sub['status'])); ?>"><?php echo sanitizeOutput($sub['status']); ?></span>
                                <?php if ($sub['status'] == 'Requires Revision'): ?>
                                    <a href="<?php echo BASE_URL . 'student/submit_work.php?edit=' . $sub['id']; ?>" class="btn btn-warning btn-xs pull-right">Revise</a>
                                <?php endif; ?>
                                </p>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?><p>You haven't made any submissions yet.</p><?php endif; ?>
                </section>

                <section class="std-dash-sidebar-widget card-style-admin" data-aos="fade-left" data-aos-delay="300">
                    <h4 class="std-dash-widget-title"><i class="fas fa-chart-line"></i> Your Progress</h4>
                    <div class="chart-container"><canvas id="profileCompletenessChart"></canvas></div>
                    <div class="chart-container mt-3"><canvas id="submissionsChart"></canvas></div>
                </section>

                <section class="std-dash-sidebar-widget card-style-admin" data-aos="fade-left" data-aos-delay="400">
                    <h4 class="std-dash-widget-title"><i class="fas fa-book-reader"></i> Community Showcase</h4>
                    <?php if (!empty($community_showcase)): ?>
                        <ul class="sidebar-list">
                           <?php foreach($community_showcase as $post): ?><li><a href="<?php echo BASE_URL . 'blog/' . $post['slug']; ?>"><strong><?php echo sanitizeOutput($post['title']); ?></strong><small class="d-block text-muted">by <?php echo sanitizeOutput($post['author_name']); ?></small></a></li><?php endforeach; ?>
                        </ul>
                    <?php else: ?><p>Published member works will appear here.</p><?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Chart.js implementation
    new Chart(document.getElementById('profileCompletenessChart').getContext('2d'), { type: 'doughnut', data: { labels: ['Completed', 'Remaining'], datasets: [{ data: <?php echo $profile_chart_js_data; ?>, backgroundColor: ['#007bff', '#e9ecef'], borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Profile Completeness' } } } });
    new Chart(document.getElementById('submissionsChart').getContext('2d'), { type: 'bar', data: { labels: ['Approved', 'Pending', 'Revision'], datasets: [{ label: 'Count', data: <?php echo $submissions_chart_js_data; ?>, backgroundColor: ['#28a745', '#ffc107', '#dc3545'] }] }, options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false }, title: { display: true, text: 'Submissions Status' } } } });

    // AJAX for Activity Feed
    fetch('<?php echo BASE_URL; ?>api/dashboard_activity.php').then(res => res.json()).then(data => {
        const container = document.getElementById('activity-feed-container'); container.innerHTML = '';
        if (data.error || !data.length) { container.innerHTML = "<div class='activity-item no-activity'><p>No recent activity.</p></div>"; return; }
        data.forEach(item => {
            const div = document.createElement('div'); div.className = 'activity-item';
            div.innerHTML = `<i class="fas ${item.icon} activity-icon"></i><div class="activity-text-content">${item.text} <small class="text-muted">(${item.time_ago})</small></div>`;
            container.appendChild(div);
        });
    }).catch(() => container.innerHTML = "<p class='text-danger'>Could not load activity.</p>");

    // AJAX for One-Click Event Registration
    document.querySelectorAll('.register-btn').forEach(button => {
        button.addEventListener('click', function() {
            const btn = this; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch('<?php echo BASE_URL; ?>api/register_for_event.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event_id: btn.dataset.eventId })
            }).then(res => res.json()).then(data => {
                const feedback = document.getElementById('deadline-feedback');
                feedback.innerHTML = ''; // Clear previous messages
                if (data.success) {
                    btn.className = 'btn btn-sm btn-success'; btn.innerHTML = '<i class="fas fa-check"></i> Registered';
                } else {
                    btn.disabled = false; btn.innerHTML = 'Register';
                    if (data.message.includes('Already registered')) { btn.className = 'btn btn-sm btn-info'; btn.innerHTML = '<i class="fas fa-check"></i> Joined'; }
                    feedback.innerHTML = `<div class="alert alert-danger alert-dismissible fade show small p-2" role="alert">${data.message}<button type="button" class="close p-2" data-dismiss="alert">&times;</button></div>`;
                }
            }).catch(() => btn.innerHTML = 'Error');
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>