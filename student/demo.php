<?php
// GREETINGS FROM YOUR 15-YEAR EXPERIENCE DEVELOPER!
// This is the production-ready dashboard.php, merging your robust backend logic
// with the modern user interface. All data is now fetched live from the database.

// --- CRITICAL INCLUDES AND SECURE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// These includes must point to your actual files.
// This assumes dashboard.php is in a 'student' subdirectory.
if (!file_exists('../includes/db_connection.php')) { 
    error_log("FATAL ERROR: student/dashboard.php - db_connection.php not found at ../includes/");
    die("A critical file is missing. Site configuration error. Please contact support. (Code: SD_DB_MISSING)"); 
}
require_once '../includes/db_connection.php'; 

if (!file_exists('../includes/functions.php')) { 
    error_log("FATAL ERROR: student/dashboard.php - functions.php not found at ../includes/");
    die("A critical file is missing. Site configuration error. Please contact support. (Code: SD_FN_MISSING)"); 
}
require_once '../includes/functions.php';

// 1. Validate Database Connection & Essential Config
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    error_log("FATAL ERROR in student/dashboard.php: Database connection failed. Error: " . ($conn ? $conn->connect_error : 'Unknown DB issue.'));
    die("A critical database error occurred. Please check server logs. (Error Code: SD_DB_FAIL)");
}
if (!defined('BASE_URL')) {
    error_log("FATAL ERROR in student/dashboard.php: BASE_URL constant is not defined.");
    die("A critical configuration error occurred. Please check server logs. (Error Code: SD_URL_FAIL)");
}

// 2. Enforce Role Access & Validate Session
enforceRoleAccess(['student']); // This function (from functions.php) should handle unauthorized access

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php?error=session_expired");
    exit;
}
$user_id = $_SESSION['user_id'];

// --- LIVE DATA FETCHING & PROCESSING ---

// 1. Get User Details
$student = getUserById($conn, $user_id);
if (!$student) {
    error_log("Critical error: Could not fetch user details for user_id: $user_id in dashboard.php");
    // You might want to log the user out here or show a generic error page
    header("Location: " . BASE_URL . "logout.php?error=user_not_found");
    exit;
}
$user_name_display = sanitizeOutput($student['name'] ?? 'Student');
$user_profile_picture_path = (!empty($student['profile_picture']))
                           ? BASE_URL . 'uploads/profile/' . sanitizeOutput($student['profile_picture']) 
                           : 'https://placehold.co/100x100/E2E8F0/4A5568?text=' . strtoupper(substr($user_name_display, 0, 1));

// 2. Time-based Greeting
$current_hour = date('G');
$time_greeting = ($current_hour < 12) ? "Good Morning" : (($current_hour < 18) ? "Good Afternoon" : "Good Evening");

// 3. Literary Quote of the Day (can be moved to a DB table: `quotes`)
$quotes = [
    ["quote" => "The books that the world calls immoral are books that show the world its own shame.", "author" => "Oscar Wilde"],
    ["quote" => "A room without books is like a body without a soul.", "author" => "Marcus Tullius Cicero"]
];
$daily_quote = $quotes[date('j') % count($quotes)];

// 4. Profile Completeness Calculation
$profile_completion_percent = calculateProfileCompleteness($student); // This logic can be moved to functions.php
function calculateProfileCompleteness($userDetails) {
    $profile_fields_to_check = [
        'profile_picture' => 25, 'department' => 25, 'bio' => 20,
        'interests_list' => 15, 'skills_list' => 15
    ];
    $filled_weight = 0;
    $total_weight = array_sum($profile_fields_to_check);
    foreach ($profile_fields_to_check as $field => $weight) {
        if (!empty($userDetails[$field])) {
            $filled_weight += $weight;
        }
    }
    return ($total_weight > 0) ? round(($filled_weight / $total_weight) * 100) : 0;
}


// 5. Dashboard Stats (Live from DB using Prepared Statements)
$dashboard_stats = [];
$stats_queries = [
    'upcoming_events' => "SELECT COUNT(er.id) as total FROM event_registrations er JOIN events e ON er.event_id = e.id WHERE er.user_id = ? AND e.date_time >= NOW()",
    'certificates_earned' => "SELECT COUNT(*) as total FROM certificates WHERE user_id = ?",
    'pending_submissions' => "SELECT COUNT(*) as total FROM submissions WHERE user_id = ? AND status IN ('pending_review', 'under_review', 'needs_revision')"
];
foreach ($stats_queries as $key => $sql) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $dashboard_stats[$key] = $row ? (int)$row['total'] : 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Dashboard stat query failed for key '$key': " . $e->getMessage());
        $dashboard_stats[$key] = 0;
    }
}


// 6. Recent Activity Feed (Live from DB)
$activity_feed = [];
$sql_activity = "
    (SELECT 'certificate' as type, c.certificate_title as title, NULL as status, c.issued_date as timestamp, c.id, NULL as event_id FROM certificates c WHERE c.user_id = ? AND c.issued_date >= DATE_SUB(NOW(), INTERVAL 30 DAY))
    UNION ALL
    (SELECT 'submission' as type, s.title, s.status, s.updated_at as timestamp, s.id, NULL as event_id FROM submissions s WHERE s.user_id = ? AND s.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s.status IN ('approved', 'rejected', 'needs_revision'))
    UNION ALL
    (SELECT 'event' as type, e.title, 'reminder' as status, e.date_time as timestamp, e.id, e.id as event_id FROM event_registrations er JOIN events e ON er.event_id = e.id WHERE er.user_id = ? AND e.date_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY))
    ORDER BY timestamp DESC LIMIT 5";
$stmt_activity = $conn->prepare($sql_activity);
$stmt_activity->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_activity->execute();
$result_activity = $stmt_activity->get_result();
while ($row = $result_activity->fetch_assoc()) {
    $item = [];
    $time_str = function_exists('time_elapsed_string') ? time_elapsed_string($row['timestamp']) : date('M j, Y', strtotime($row['timestamp']));
    switch($row['type']) {
        case 'certificate':
            $item = ['icon' => 'fa-award', 'color' => 'yellow', 'text' => '<strong>Congratulations!</strong> You earned "' . sanitizeOutput($row['title']) . '".', 'time' => $time_str, 'link' => BASE_URL . 'student/certificates.php#cert-' . $row['id']];
            break;
        case 'submission':
            $status_text = sanitizeOutput(ucwords(str_replace('_', ' ', $row['status'])));
            $color = ($row['status'] == 'approved') ? 'green' : (($row['status'] == 'rejected') ? 'red' : 'blue');
            $icon = ($row['status'] == 'approved') ? 'fa-check-circle' : (($row['status'] == 'rejected') ? 'fa-times-circle' : 'fa-info-circle');
            $item = ['icon' => $icon, 'color' => $color, 'text' => 'Your submission "' . sanitizeOutput($row['title']) . '" has been updated to: <strong>' . $status_text . '</strong>.', 'time' => $time_str, 'link' => BASE_URL . 'student/my_submissions.php#submission-' . $row['id']];
            break;
        case 'event':
            $item = ['icon' => 'fa-calendar-check', 'color' => 'blue', 'text' => '<strong>Reminder:</strong> Your event "' . sanitizeOutput($row['title']) . '" is coming up soon.', 'time' => $time_str, 'link' => BASE_URL . 'events/view.php?id=' . $row['event_id']];
            break;
    }
    if (!empty($item)) $activity_feed[] = $item;
}
$stmt_activity->close();

// 7. Announcements (Live from DB)
$announcements = [];
$sql_announcements = "SELECT title, excerpt as content, created_at as date FROM announcements WHERE is_active = TRUE AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY priority DESC, created_at DESC LIMIT 3";
$result_ann = $conn->query($sql_announcements);
if ($result_ann) {
    while($row = $result_ann->fetch_assoc()) {
        $announcements[] = $row;
    }
} else {
    error_log("Announcements fetch failed: " . $conn->error);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Student Dashboard - Geetanjali Literary Society</title>
    <meta name="description" content="Personalized dashboard for Geetanjali Literary Society members at NIFTEM Kundli. View events, certificates, submit work, and stay updated with announcements.">
    
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/favicon.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">


    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .card { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .card-link { display: block; text-decoration: none; color: inherit; }
        .card-link:hover .card { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.1); }
        .card-link .card-arrow { transition: transform 0.2s ease-in-out; }
        .card-link:hover .card-arrow { transform: translateX(4px); }
        .announcement-scroll::-webkit-scrollbar { width: 5px; }
        .announcement-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .announcement-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="antialiased text-slate-700">

    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center space-x-4">
                     <a href="<?php echo BASE_URL; ?>" class="flex items-center space-x-4">
                        <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Geetanjali Logo" class="h-10 w-10 rounded-full">
                        <div>
                            <h1 class="text-xl font-bold text-slate-800">Geetanjali Literary Society</h1>
                            <p class="text-sm text-slate-500">NIFTEM, Kundli</p>
                        </div>
                    </a>
                </div>
                <div class="flex items-center space-x-6">
                    <span class="hidden md:block font-medium text-slate-600"><?php echo htmlspecialchars($time_greeting); ?>, <?php echo htmlspecialchars(explode(' ', $user_name_display)[0]); ?>!</span>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        <!-- Profile Completeness Widget -->
        <?php if ($profile_completion_percent < 100): ?>
        <div class="bg-yellow-100 border-t-4 border-yellow-500 rounded-b text-yellow-900 px-4 py-3 shadow-md mb-8" role="alert">
            <div class="flex items-center">
                <div class="py-1"><i class="fas fa-user-edit text-yellow-600 mr-4"></i></div>
                <div class="flex-grow">
                    <p class="font-bold">Complete Your Profile (<?php echo $profile_completion_percent; ?>%)</p>
                    <div class="w-full bg-yellow-200 rounded-full h-2.5 my-2">
                        <div class="bg-yellow-500 h-2.5 rounded-full" style="width: <?php echo $profile_completion_percent; ?>%"></div>
                    </div>
                    <p class="text-sm">Your profile is almost complete! A full profile helps you get noticed. <a href="<?php echo BASE_URL; ?>student/profile.php" class="font-semibold underline">Update Profile Now</a></p>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Clickable Feature Cards Grid -->
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- My Profile / ID Card -->
                    <a href="<?php echo BASE_URL; ?>student/profile.php" class="card-link group">
                        <div class="card p-6 flex items-center space-x-5 h-full">
                            <img class="h-20 w-20 rounded-full border-4 border-slate-200 object-cover" src="<?php echo $user_profile_picture_path; ?>" alt="Profile Picture">
                            <div>
                                <p class="text-xl font-bold text-indigo-600"><?php echo $user_name_display; ?></p>
                                <p class="text-sm text-slate-500">View My Profile & ID Card</p>
                                <span class="text-indigo-500 text-sm font-semibold mt-2 inline-block">Manage Profile <i class="fas fa-arrow-right card-arrow"></i></span>
                            </div>
                        </div>
                    </a>
                    
                    <!-- My Certificates -->
                    <a href="<?php echo BASE_URL; ?>student/certificates.php" class="card-link group">
                         <div class="card p-6 flex flex-col justify-between h-full bg-yellow-50 text-yellow-900">
                             <div>
                                <i class="fas fa-award fa-2x text-yellow-500 mb-2"></i>
                                <p class="text-3xl font-bold"><?php echo htmlspecialchars($dashboard_stats['certificates_earned']); ?></p>
                                <p class="font-semibold">Certificates Earned</p>
                             </div>
                             <span class="text-yellow-700 font-semibold mt-2 inline-block">View All Certificates <i class="fas fa-arrow-right card-arrow"></i></span>
                         </div>
                    </a>
                    
                    <!-- Upcoming Events -->
                    <a href="<?php echo BASE_URL; ?>events/" class="card-link group">
                         <div class="card p-6 flex flex-col justify-between h-full bg-blue-50 text-blue-900">
                             <div>
                                <i class="fas fa-calendar-check fa-2x text-blue-500 mb-2"></i>
                                <p class="text-3xl font-bold"><?php echo htmlspecialchars($dashboard_stats['upcoming_events']); ?></p>
                                <p class="font-semibold">My Upcoming Events</p>
                             </div>
                             <span class="text-blue-700 font-semibold mt-2 inline-block">Explore Events Calendar <i class="fas fa-arrow-right card-arrow"></i></span>
                         </div>
                    </a>

                    <!-- My Submissions -->
                     <a href="<?php echo BASE_URL; ?>student/my_submissions.php" class="card-link group">
                         <div class="card p-6 flex flex-col justify-between h-full bg-green-50 text-green-900">
                             <div>
                                <i class="fas fa-feather-alt fa-2x text-green-500 mb-2"></i>
                                <p class="text-3xl font-bold"><?php echo htmlspecialchars($dashboard_stats['pending_submissions']); ?></p>
                                <p class="font-semibold">Submissions in Review</p>
                             </div>
                             <span class="text-green-700 font-semibold mt-2 inline-block">Track My Work <i class="fas fa-arrow-right card-arrow"></i></span>
                         </div>
                    </a>
                </div>

                <!-- Recent Activity Feed -->
                <div class="card p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Recent Activity</h2>
                    <div class="space-y-4">
                        <?php if (!empty($activity_feed)): ?>
                            <?php foreach ($activity_feed as $item): ?>
                                <a href="<?php echo htmlspecialchars($item['link']); ?>" class="flex items-start space-x-4 p-3 rounded-lg hover:bg-slate-50">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-<?php echo $item['color']; ?>-100 flex items-center justify-center">
                                         <i class="fas <?php echo htmlspecialchars($item['icon']); ?> text-<?php echo $item['color']; ?>-600"></i>
                                    </div>
                                    <div class="flex-grow">
                                        <p class="text-sm text-slate-700"><?php echo $item['text']; /* HTML is allowed here */ ?></p>
                                        <p class="text-xs text-slate-400 mt-1"><?php echo htmlspecialchars($item['time']); ?></p>
                                    </div>
                                    <i class="fas fa-chevron-right text-slate-300 self-center"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-sm text-slate-500 text-center py-4">No recent activity to show. Welcome to your dashboard!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Announcements -->
                 <div class="card p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">📢 Announcements</h2>
                    <div class="space-y-5 max-h-[30rem] overflow-y-auto pr-2 announcement-scroll">
                        <?php if (!empty($announcements)): ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="p-4 bg-slate-50/70 border-l-4 border-indigo-500 rounded-r-lg">
                                    <p class="text-xs text-slate-500 font-semibold"><?php echo date('F j, Y', strtotime($announcement['date'])); ?></p>
                                    <p class="font-bold text-md text-slate-800 mt-1"><?php echo sanitizeOutput($announcement['title']); ?></p>
                                    <p class="text-sm text-slate-600 mt-1"><?php echo sanitizeOutput($announcement['content']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-sm text-slate-500 text-center py-4">No new announcements at this time.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Submit Work -->
                <div class="card p-6 bg-indigo-600 text-white text-center">
                     <i class="fas fa-paper-plane fa-3x mb-3"></i>
                     <h2 class="text-xl font-bold mb-2">Share Your Creativity</h2>
                     <p class="text-sm text-indigo-200 mb-4">Have a poem, a story, or a blog post? Submit it here for review.</p>
                     <button id="open-modal-btn" class="w-full py-3 font-bold bg-white text-indigo-600 rounded-lg hover:bg-indigo-50 transition-colors">
                        Submit Your Work
                     </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-800 text-slate-400 mt-12">
        <div class="max-w-7xl mx-auto text-center py-6 px-4">
            <p>&copy; <?php echo date('Y'); ?> Geetanjali Literary Society, NIFTEM Kundli. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Submission Modal -->
    <div id="submission-modal" class="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4 hidden">
        <div id="modal-content" class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-8 transform transition-all opacity-0 -translate-y-10">
            <!-- Modal content will be loaded here via AJAX -->
        </div>
    </div>


    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('submission-modal');
        const modalContent = document.getElementById('modal-content');
        const openBtn = document.getElementById('open-modal-btn');

        // Function to open the modal
        const openModal = () => {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('opacity-0', '-translate-y-10');
            }, 10);
        };

        // Function to close the modal
        const closeModal = () => {
            modalContent.classList.add('opacity-0', '-translate-y-10');
            setTimeout(() => {
                modal.classList.add('hidden');
                modalContent.innerHTML = ''; // Clear content on close
            }, 300);
        };
        
        // --- AJAX Form Handling ---
        const loadForm = () => {
            // Display a loading state
            modalContent.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin fa-3x text-indigo-500"></i><p class="mt-4">Loading Form...</p></div>';
            openModal();

            // This URL should point to a PHP file that ONLY outputs the form HTML
            fetch('<?php echo BASE_URL; ?>student/ajax/get_submission_form.php')
                .then(response => response.text())
                .then(html => {
                    modalContent.innerHTML = html;
                    attachFormEvents();
                })
                .catch(error => {
                    modalContent.innerHTML = '<div class="text-center p-8 text-red-600">Could not load the form. Please try again later.</div>';
                    console.error('Error loading form:', error);
                });
        };

        const attachFormEvents = () => {
            const form = document.getElementById('work-submission-form');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                submitButton.disabled = true;

                const formData = new FormData(form);
                
                // This URL should point to a PHP file that handles the submission logic
                fetch('<?php echo BASE_URL; ?>student/ajax/handle_submission.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalContent.innerHTML = `<div class="text-center p-8">
                            <i class="fas fa-check-circle fa-4x text-green-500 mb-4"></i>
                            <h3 class="text-2xl font-bold">Submission Successful!</h3>
                            <p class="text-slate-600 mt-2">${data.message}</p>
                            <button onclick="window.location.reload()" class="mt-6 px-6 py-2 bg-indigo-600 text-white rounded-md">Close & Refresh</button>
                        </div>`;
                    } else {
                        // Display error message
                        const errorDiv = form.querySelector('.error-message');
                        errorDiv.textContent = data.message || 'An unknown error occurred.';
                        errorDiv.classList.remove('hidden');
                        submitButton.innerHTML = originalButtonText;
                        submitButton.disabled = false;
                    }
                })
                .catch(error => {
                    const errorDiv = form.querySelector('.error-message');
                    errorDiv.textContent = 'A network error occurred. Please try again.';
                    errorDiv.classList.remove('hidden');
                    submitButton.innerHTML = originalButtonText;
                    submitButton.disabled = false;
                    console.error('Submission error:', error);
                });
            });

            // Re-attach close/cancel button events for the new form content
            document.getElementById('close-modal-btn').addEventListener('click', closeModal);
            document.getElementById('cancel-btn').addEventListener('click', closeModal);
        };
        
        // --- Main Event Listeners ---
        openBtn.addEventListener('click', loadForm);
        
        // Close modal if user clicks outside the content area (but only if it's the modal itself)
        modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });

        // Close modal with the Escape key
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
    });
    </script>
</body>
</html>
