<?php
ob_start(); // Start output buffering to prevent header errors
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- CRITICAL INCLUDES AND CHECKS ---
if (!file_exists('../includes/db_connection.php')) { die("FATAL ERROR: db_connection.php not found. (Code: AER_DB00)"); }
require_once '../includes/db_connection.php'; 
if (!file_exists('../includes/functions.php')) { die("FATAL ERROR: functions.php not found. (Code: AER_FN00)"); }
require_once '../includes/functions.php';     

if (!isset($conn) || !$conn) { die("Critical database connection error. (Code: AER_DB01)"); }
if (!defined('BASE_URL')) { die("Critical configuration error (BASE_URL missing). (Code: AER_BU01)"); }
$aer_required_functions = ['enforceRoleAccess', 'sanitizeOutput', 'getSetting', 'time_elapsed_string', 'generatePaginationLinks', 'logAudit', 'generateSlug', 'sanitizeOutputJS', 'recordAttendance'];
foreach ($aer_required_functions as $aer_func) {
    if (!function_exists($aer_func)) {
        die("Critical site function ('$aer_func') is missing. Check includes/functions.php. (Code: AER_FN01)");
    }
}
// Centralized error logging
function logAERError($message, $code = 'AER_UNKNOWN') {
    error_log("AER [$code]: $message, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . ", Time: " . date('Y-m-d H:i:s'));
}

// Enforce HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// --- END CRITICAL CHECKS ---
enforceRoleAccess(['admin', 'super_admin', 'event_manager']);

$pageTitle = "Event Registrations";
$current_admin_id = (int)$_SESSION['user_id'];
$message = $_SESSION['success_message_event_reg'] ?? ''; unset($_SESSION['success_message_event_reg']);
$error_message = $_SESSION['error_message_event_reg'] ?? ''; unset($_SESSION['error_message_event_reg']);

// Validate event_id
$event_id = isset($_GET['event_id']) && is_numeric($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$event = null;
$registrations_with_status = [];
$total_registrations_for_display = 0;
$total_filtered_registrations = 0;
$limit = 10; // Records per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $limit;
$attendance_summary = ['entry' => 0, 'exit' => 0];

// CSRF Token
if (empty($_SESSION['csrf_token_event_reg_page'])) { 
    $_SESSION['csrf_token_event_reg_page'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_event_reg_page'];

// Rate limiting for actions
if (!isset($_SESSION['last_action_time'])) $_SESSION['last_action_time'] = 0;
if ((time() - $_SESSION['last_action_time']) < 1) {
    $_SESSION['error_message_event_reg'] = "Please wait a moment before performing another action.";
    header("Location: " . BASE_URL . "admin/event_registrations.php?event_id=" . $event_id); exit;
}
$_SESSION['last_action_time'] = time();

if ($event_id <= 0) {
    $_SESSION['error_message_event_reg'] = "Invalid or missing event ID.";
    header("Location: " . BASE_URL . "admin/events.php"); exit;
}

// Cache event details
if (!isset($_SESSION['event_' . $event_id])) {
    $stmt_event = $conn->prepare("SELECT id, title, date_time, capacity FROM events WHERE id = ?");
    if ($stmt_event) { 
        $stmt_event->bind_param("i", $event_id);
        if ($stmt_event->execute()) {
            $result_event = $stmt_event->get_result();
            if ($result_event->num_rows === 1) {
                $event = $result_event->fetch_assoc();
                $_SESSION['event_' . $event_id] = $event;
                $pageTitle = "Registrations: " . sanitizeOutput($event['title']);
            } else {
                $_SESSION['error_message_event_reg'] = "Event not found (ID: $event_id).";
                header("Location: " . BASE_URL . "admin/events.php"); exit;
            }
        } else {
            logAERError("Fetch event execute error: " . $stmt_event->error, "AER_DB02");
            $_SESSION['error_message_event_reg'] = "Error fetching event details.";
        }
        $stmt_event->close();
    } else {
        logAERError("Fetch event prepare error: " . $conn->error, "AER_DB03");
        $_SESSION['error_message_event_reg'] = "Database error fetching event.";
    }
} else {
    $event = $_SESSION['event_' . $event_id];
}
if (!$event) { header("Location: " . BASE_URL . "admin/events.php"); exit; }

// Fetch total registrations and attendance summary
$stmt_summary = $conn->prepare("
    SELECT 
        (SELECT COUNT(id) FROM event_registrations WHERE event_id = ?) as total_reg,
        (SELECT COUNT(*) FROM event_attendance_log WHERE event_id = ? AND scan_type = 'entry') as entry_count,
        (SELECT COUNT(*) FROM event_attendance_log WHERE event_id = ? AND scan_type = 'exit') as exit_count
");
if ($stmt_summary) {
    $stmt_summary->bind_param("iii", $event_id, $event_id, $event_id);
    if ($stmt_summary->execute()) {
        $result = $stmt_summary->get_result()->fetch_assoc();
        $total_registrations_for_display = (int)$result['total_reg'];
        $attendance_summary = ['entry' => (int)$result['entry_count'], 'exit' => (int)$result['exit_count']];
    } else {
        logAERError("Summary query failed: " . $stmt_summary->error, "AER_DB04");
    }
    $stmt_summary->close();
} else {
    logAERError("Summary query prepare failed: " . $conn->error, "AER_DB05");
}

// Handle filters
$filter_params = [];
$filter_query = "";
$search_term = isset($_GET['search']) ? trim(preg_replace("/[^a-zA-Z0-9\s]/", "", $_GET['search'])) : '';
$attendance_filter = isset($_GET['attendance']) && in_array($_GET['attendance'], ['all', 'entry', 'exit', 'not_scanned']) ? $_GET['attendance'] : 'all';
if ($search_term) {
    $filter_query .= " AND (u.name LIKE ? OR u.niftem_id LIKE ?)";
    $filter_params['search'] = $search_term;
}
if ($attendance_filter !== 'all') {
    if ($attendance_filter === 'not_scanned') {
        $filter_query .= " AND al.scan_type IS NULL";
    } else {
        $filter_query .= " AND al.scan_type = ?";
        $filter_params['attendance'] = $attendance_filter;
    }
}

// Fetch filtered registrations
$query = "
    SELECT er.id, er.user_id, er.registration_time, u.niftem_id, u.name, al.scan_type, al.scan_time 
    FROM event_registrations er 
    LEFT JOIN users u ON er.user_id = u.id 
    LEFT JOIN event_attendance_log al ON er.id = al.registration_id 
    WHERE er.event_id = ?" . $filter_query . " 
    LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $bind_params = [$event_id];
    if ($search_term) {
        $search_wildcard = "%$search_term%";
        $bind_params[] = $search_wildcard;
        $bind_params[] = $search_wildcard;
    }
    if ($attendance_filter !== 'all' && $attendance_filter !== 'not_scanned') {
        $bind_params[] = $attendance_filter;
    }
    $bind_params[] = $limit;
    $bind_params[] = $offset;
    $stmt->bind_param(str_repeat("s", count($bind_params) - 2) . "ii", ...$bind_params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $registrations_with_status[] = $row;
        }
        $total_filtered_registrations = count($registrations_with_status);
    } else {
        logAERError("Registration query execute failed: " . $stmt->error, "AER_DB06");
        $_SESSION['error_message_event_reg'] = "Failed to fetch registrations.";
    }
    $stmt->close();
} else {
    logAERError("Registration query prepare failed: " . $conn->error, "AER_DB07");
    $_SESSION['error_message_event_reg'] = "Database query error.";
}

// Calculate total pages
$total_query = "SELECT COUNT(*) as total FROM event_registrations er LEFT JOIN users u ON er.user_id = u.id LEFT JOIN event_attendance_log al ON er.id = al.registration_id WHERE er.event_id = ?" . $filter_query;
$stmt_total = $conn->prepare($total_query);
if ($stmt_total) {
    $bind_params = [$event_id];
    if ($search_term) {
        $search_wildcard = "%$search_term%";
        $bind_params[] = $search_wildcard;
        $bind_params[] = $search_wildcard;
    }
    if ($attendance_filter !== 'all' && $attendance_filter !== 'not_scanned') {
        $bind_params[] = $attendance_filter;
    }
    $stmt_total->bind_param(str_repeat("s", count($bind_params)), ...$bind_params);
    if ($stmt_total->execute()) {
        $total_filtered_registrations = $stmt_total->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_filtered_registrations / $limit);
    } else {
        logAERError("Total filtered registrations query failed: " . $stmt_total->error, "AER_DB08");
    }
    $stmt_total->close();
} else {
    logAERError("Total filtered registrations prepare failed: " . $conn->error, "AER_DB09");
}

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['csrf_token']) && hash_equals($csrf_token, $_POST['csrf_token'])) {
    $action_type = $_POST['action'];
    $selected_reg_ids = isset($_POST['reg_ids']) && is_array($_POST['reg_ids']) ? array_map('intval', $_POST['reg_ids']) : [];
    if ($action_type === 'bulk_check_in' || $action_type === 'bulk_check_out') {
        $scan_type = $action_type === 'bulk_check_in' ? 'entry' : 'exit';
        foreach ($selected_reg_ids as $reg_id) {
            $stmt = $conn->prepare("SELECT user_id FROM event_registrations WHERE id = ? AND event_id = ?");
            $stmt->bind_param("ii", $reg_id, $event_id);
            $stmt->execute();
            $user_id = $stmt->get_result()->fetch_assoc()['user_id'];
            $stmt->close();
            if ($user_id) {
                $result = recordAttendance($conn, $event_id, $user_id, $scan_type, $current_admin_id, $reg_id, "Bulk $scan_type for event '" . ($event['title'] ?? $event_id) . "'");
                if (!$result['success']) {
                    $_SESSION['error_message_event_reg'] = $result['message'];
                    break;
                }
            }
        }
        $_SESSION['success_message_event_reg'] = "Bulk $scan_type completed successfully.";
    } elseif ($action_type === 'bulk_remove' && $selected_reg_ids) {
        $stmt = $conn->prepare("DELETE FROM event_registrations WHERE id IN (" . implode(',', array_fill(0, count($selected_reg_ids), '?')) . ") AND event_id = ?");
        $bind_params = array_merge($selected_reg_ids, [$event_id]);
        $stmt->bind_param(str_repeat("i", count($bind_params)), ...$bind_params);
        if ($stmt->execute()) {
            logAudit($conn, $current_admin_id, "Bulk removed registrations: " . implode(',', $selected_reg_ids));
            $_SESSION['success_message_event_reg'] = "Selected registrations removed successfully.";
        } else {
            logAERError("Bulk remove failed: " . $stmt->error, "AER_DB10");
            $_SESSION['error_message_event_reg'] = "Failed to remove registrations.";
        }
        $stmt->close();
    }
    $_SESSION['csrf_token_event_reg_page'] = bin2hex(random_bytes(32));
    header("Location: " . BASE_URL . "admin/event_registrations.php?event_id=" . $event_id); exit;
}

// Handle Single Actions
if (isset($_GET['action']) && isset($_GET['token']) && hash_equals($csrf_token, $_GET['token'])) {
    $action_type = $_GET['action'];
    $target_reg_id = isset($_GET['reg_id']) && is_numeric($_GET['reg_id']) ? (int)$_GET['reg_id'] : 0;
    $target_user_id_action = isset($_GET['user_id_action']) && is_numeric($_GET['user_id_action']) ? (int)$_GET['user_id_action'] : 0;

    if ($action_type === 'remove_registration' && $target_reg_id > 0) {
        $stmt = $conn->prepare("DELETE FROM event_registrations WHERE id = ? AND event_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $target_reg_id, $event_id);
            if ($stmt->execute()) {
                logAudit($conn, $current_admin_id, "Removed registration ID $target_reg_id for event ID $event_id");
                $_SESSION['success_message_event_reg'] = "Registration removed successfully.";
            } else {
                logAERError("Remove registration failed: " . $stmt->error, "AER_DB11");
                $_SESSION['error_message_event_reg'] = "Failed to remove registration.";
            }
            $stmt->close();
        } else {
            logAERError("Remove registration prepare failed: " . $conn->error, "AER_DB12");
            $_SESSION['error_message_event_reg'] = "Database error removing registration.";
        }
    } elseif (($action_type === 'manual_check_in' || $action_type === 'manual_check_out') && $target_user_id_action > 0 && $target_reg_id > 0) {
        $scan_type_manual = ($action_type === 'manual_check_in') ? 'entry' : 'exit';
        $attendance_result = recordAttendance($conn, $event_id, $target_user_id_action, $scan_type_manual, $current_admin_id, $target_reg_id, "Manual update for event '" . ($event['title'] ?? $event_id) . "'");
        if ($attendance_result['success']) {
            $_SESSION['success_message_event_reg'] = $attendance_result['message'];
        } else {
            $_SESSION['error_message_event_reg'] = $attendance_result['message'];
        }
    }
    $_SESSION['csrf_token_event_reg_page'] = bin2hex(random_bytes(32));
    header("Location: " . BASE_URL . "admin/event_registrations.php?event_id=" . $event_id); exit;
}

// Handle CSV Export
if (isset($_GET['export_csv']) && $_GET['export_csv'] === 'true' && hash_equals($csrf_token, $_GET['token'] ?? '')) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="event_registrations_' . $event_id . '_' . date('YmdHis') . '.csv"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['ID', 'NIFTEM ID', 'Name', 'Registration Time', 'Attendance Status', 'Scan Time']);
    $stmt = $conn->prepare("SELECT er.id, u.niftem_id, u.name, er.registration_time, al.scan_type, al.scan_time 
                            FROM event_registrations er 
                            LEFT JOIN users u ON er.user_id = u.id 
                            LEFT JOIN event_attendance_log al ON er.id = al.registration_id 
                            WHERE er.event_id = ?" . $filter_query);
    $bind_params = [$event_id];
    if ($search_term) {
        $search_wildcard = "%$search_term%";
        $bind_params[] = $search_wildcard;
        $bind_params[] = $search_wildcard;
    }
    if ($attendance_filter !== 'all' && $attendance_filter !== 'not_scanned') {
        $bind_params[] = $attendance_filter;
    }
    $stmt->bind_param(str_repeat("s", count($bind_params)), ...$bind_params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            fputcsv($fp, [
                $row['id'],
                $row['niftem_id'],
                $row['name'],
                date('Y-m-d H:i:s', strtotime($row['registration_time'])),
                $row['scan_type'] ? ucfirst($row['scan_type']) : 'Not scanned',
                $row['scan_time'] ? date('Y-m-d H:i:s', strtotime($row['scan_time'])) : ''
            ]);
        }
    }
    $stmt->close();
    fclose($fp);
    exit;
}

include '../includes/header.php';
?>

<style>
    .table-responsive-wrapper { overflow-x: auto; }
    .admin-table th, .admin-table td { white-space: nowrap; }
    .page-loading-spinner { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); }
    .camera-select { margin: 10px auto; width: 100%; max-width: 400px; }
</style>

<main id="page-content" class="admin-area std-profile-container">
    <div id="pageLoadingSpinner" class="spinner-border page-loading-spinner"></div>
    <div class="container">
        <header class="std-profile-header page-title-section">
            <h1><i class="fas fa-id-card-alt"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
            <?php if ($event): ?>
            <p class="subtitle">
                Event: <strong><?php echo sanitizeOutput($event['title']); ?></strong> | 
                Date: <?php echo date('M j, Y @ g:i A', strtotime($event['date_time'])); ?> <br>
                Capacity: <?php echo ($event['capacity'] ?? 0) > 0 ? sanitizeOutput($event['capacity']) : 'Open'; ?> | 
                Total Registered: <strong class="text-primary"><?php echo $total_registrations_for_display; ?></strong> | 
                Checked-In: <?php echo $attendance_summary['entry']; ?> | 
                Checked-Out: <?php echo $attendance_summary['exit']; ?>
                <?php if (($event['capacity'] ?? 0) > 0 && $total_registrations_for_display >= $event['capacity']) echo '<span class="badge badge-danger ml-2">EVENT FULL</span>'; ?>
            </p>
            <?php endif; ?>
            <div class="header-actions-bar">
                <a href="<?php echo BASE_URL; ?>admin/events.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Events List</a>
                <a href="<?php echo BASE_URL; ?>admin/events.php?action=edit&id=<?php echo $event_id; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i> Edit Event</a>
                <button type="button" id="launchQrScannerButton" class="btn btn-sm btn-success"><i class="fas fa-qrcode"></i> Scan Attendance</button>
                <a href="<?php echo BASE_URL; ?>admin/audit_log.php?event_id=<?php echo $event_id; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-history"></i> View Audit Log</a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
        <div class="form-message success admin-page-message" data-aos="fade-right"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
        <div class="form-message error admin-page-message" data-aos="fade-right"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section class="content-section filters-and-search-section card-style-admin" data-aos="fade-up">
            <form method="GET" action="">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                <div class="input-group mb-2">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or NIFTEM ID" value="<?php echo htmlspecialchars($search_term); ?>">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    </div>
                </div>
                <div class="input-group">
                    <select name="attendance" class="form-control">
                        <option value="all" <?php echo $attendance_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="entry" <?php echo $attendance_filter === 'entry' ? 'selected' : ''; ?>>Checked-In</option>
                        <option value="exit" <?php echo $attendance_filter === 'exit' ? 'selected' : ''; ?>>Checked-Out</option>
                        <option value="not_scanned" <?php echo $attendance_filter === 'not_scanned' ? 'selected' : ''; ?>>Not Scanned</option>
                    </select>
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="content-section std-profile-main-content card-style-admin" data-aos="fade-up" data-aos-delay="100"> 
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="table-header-controls">
                    <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> Registered Users (Displaying <?php echo count($registrations_with_status); ?> of <?php echo $total_filtered_registrations; ?>)</h2>
                    <div class="bulk-actions">
                        <select name="action" class="form-control d-inline-block w-auto">
                            <option value="">Bulk Actions</option>
                            <option value="bulk_check_in">Check-In Selected</option>
                            <option value="bulk_check_out">Check-Out Selected</option>
                            <option value="bulk_remove">Remove Selected</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                        <?php if ($total_registrations_for_display > 0): ?>
                        <a href="?event_id=<?php echo $event_id; ?>&export_csv=true&token=<?php echo $csrf_token; ?>&<?php echo http_build_query($filter_params); ?>" class="cta-button btn-success btn-sm">
                            <i class="fas fa-file-csv"></i> Export to CSV
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="admin-table stylish-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>#</th>
                                <th>User (NIFTEM ID)</th>
                                <th>Registered</th>
                                <th>Attendance Status</th>
                                <th>Manual Actions</th>
                                <th>Remove Reg.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations_with_status as $index => $reg): ?>
                            <tr>
                                <td><input type="checkbox" name="reg_ids[]" class="select-row" value="<?php echo $reg['id']; ?>"></td>
                                <td><?php echo $index + 1 + $offset; ?></td>
                                <td><?php echo sanitizeOutput($reg['name']) . ' (' . sanitizeOutput($reg['niftem_id']) . ')'; ?></td>
                                <td><?php echo date('M j, Y @ g:i A', strtotime($reg['registration_time'])); ?></td>
                                <td><?php echo $reg['scan_type'] ? ucfirst($reg['scan_type']) . ' at ' . date('g:i A', strtotime($reg['scan_time'])) : 'Not scanned'; ?></td>
                                <td>
                                    <a href="?event_id=<?php echo $event_id; ?>&action=manual_check_in&reg_id=<?php echo $reg['id']; ?>&user_id_action=<?php echo $reg['user_id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-success">Check-In</a>
                                    <a href="?event_id=<?php echo $event_id; ?>&action=manual_check_out&reg_id=<?php echo $reg['id']; ?>&user_id_action=<?php echo $reg['user_id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-warning">Check-Out</a>
                                </td>
                                <td>
                                    <a href="?event_id=<?php echo $event_id; ?>&action=remove_registration&reg_id=<?php echo $reg['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remove registration?');">Remove</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php echo generatePaginationLinks($current_page, $total_pages, BASE_URL . 'admin/event_registrations.php', array_merge($filter_params, ['event_id' => $event_id])); ?>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div id="qrScannerModal" class="admin-terminal-modal" style="display: none;" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="qrScannerModalTitle">
        <div class="admin-terminal-modal-content qr-scanner-modal-content">
            <div id="qrScannerToolbar" class="admin-terminal-toolbar">
                <span id="qrScannerModalTitle">Scan Attendee QR: <?php echo sanitizeOutput($event['title'] ?? 'Event'); ?></span>
                <button type="button" class="admin-window-button close" id="closeQrScannerModalButton" title="Close Scanner">×</button>
            </div>
            <div class="qr-scanner-body">
                <select id="cameraSelect" class="form-control camera-select" style="display: none;"></select>
                <div id="qrScannerMessageTop" class="alert alert-info">Point camera at a user's QR code.</div>
                <div id="qrScannerVideoContainer" style="width:100%; max-width:400px; margin: 10px auto; border: 1px solid #ccc; min-height:200px; background:#000;"></div>
                <div id="qrScannerResult" class="mt-2"></div>
                <div class="qr-scanner-actions mt-2">
                    <button type="button" id="qrScanCheckInButton" class="cta-button btn-success" disabled><i class="fas fa-sign-in-alt"></i> Process Check-In</button>
                    <button type="button" id="qrScanCheckOutButton" class="cta-button btn-warning ml-2" disabled><i class="fas fa-sign-out-alt"></i> Process Check-Out</button>
                    <button type="button" id="qrScanResetButton" class="cta-button btn-secondary ml-2"><i class="fas fa-redo"></i> Scan Next</button>
                </div>
                <div id="qrScannerErrorLog" class="text-danger mt-2 small-text"></div>
            </div>
        </div>
    </div>
</main>

<?php 
if (defined('BASE_URL')) {
    echo '<script src="' . BASE_URL . 'assets/js/html5-qrcode.min.js?v=' . time() . '"></script>'; 
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const launchScannerButton = document.getElementById('launchQrScannerButton');
    const qrScannerModal = document.getElementById('qrScannerModal');
    const closeQrScannerButton = document.getElementById('closeQrScannerModalButton');
    const qrScannerVideoContainerId = 'qrScannerVideoContainer';
    const qrScannerResultDiv = document.getElementById('qrScannerResult');
    const qrScannerErrorLog = document.getElementById('qrScannerErrorLog');
    const qrScannerMessageTop = document.getElementById('qrScannerMessageTop');
    const checkInButton = document.getElementById('qrScanCheckInButton');
    const checkOutButton = document.getElementById('qrScanCheckOutButton');
    const resetButton = document.getElementById('qrScanResetButton');
    const cameraSelect = document.getElementById('cameraSelect');
    const pageLoadingSpinner = document.getElementById('pageLoadingSpinner');
    
    let html5QrCode = null; 
    let lastScannedQrData = null;
    const currentEventId = <?php echo (int)$event_id; ?>;
    const csrfTokenForAjax = "<?php echo $csrf_token; ?>";

    // Show loading spinner on page load
    window.addEventListener('beforeunload', () => pageLoadingSpinner.style.display = 'block');
    window.addEventListener('load', () => pageLoadingSpinner.style.display = 'none');

    // Bulk action checkbox handling
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('.select-row').forEach(cb => cb.checked = this.checked);
    });

    function sanitizeOutputJS(str) { 
        if (str === null || typeof str === 'undefined') return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#x27;', '/': '&#x2F;' };
        return String(str).replace(/[&<>"'\/]/g, m => map[m]);
    }

    function displayScannerMessage(type, message, permanent = false) {
        qrScannerResultDiv.innerHTML = `<div class="alert alert-${type} mt-2">${message}</div>`;
        qrScannerMessageTop.className = `alert alert-${type}`;
        qrScannerMessageTop.innerHTML = message + (type === 'info' && !permanent ? ' <span class="spinner-border spinner-border-sm"></span>' : '');
    }

    function resetScanner() {
        lastScannedQrData = null;
        qrScannerResultDiv.innerHTML = "";
        checkInButton.disabled = true;
        checkOutButton.disabled = true;
        if (html5QrCode && html5QrCode.getState && html5QrCode.getState() === Html5Qrcode.SCANNING_STATE_PAUSED) {
            try { html5QrCode.resume(); qrScannerMessageTop.textContent = "Scanner active. Aim at QR code."; } catch(e) { console.warn("Error resuming scanner:", e); }
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        console.log(`QR Scan Success: ${decodedText}`);
        lastScannedQrData = decodedText;
        displayScannerMessage('info', `QR Detected: <strong>${sanitizeOutputJS(decodedText)}</strong>. Please select an action.`, true);
        checkInButton.disabled = false;
        checkOutButton.disabled = false;
        if (html5QrCode && typeof html5QrCode.pause === 'function') {
            try { html5QrCode.pause(true); } catch(e) { console.warn("Could not pause scanner", e); }
        }
    }

    function onScanFailure(error) { /* Silent failure handling */ }

    function startQrScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            qrScannerErrorLog.textContent = "QR Scanner library not loaded. Check internet connection or library path.";
            displayScannerMessage('danger', "Scanner library missing!", true);
            return;
        }
        qrScannerErrorLog.textContent = ""; 
        lastScannedQrData = null; 
        qrScannerResultDiv.innerHTML = "";
        checkInButton.disabled = true; 
        checkOutButton.disabled = true;
        document.getElementById(qrScannerVideoContainerId).innerHTML = '';

        html5QrCode = new Html5Qrcode(qrScannerVideoContainerId, { verbose: false });
        const config = { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };
        
        displayScannerMessage('info', "Requesting camera access...", true);
        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                let cameraId = devices[0].id; 
                const rearCamera = devices.find(d => d.label.toLowerCase().includes('back'));
                if (rearCamera) cameraId = rearCamera.id;

                if (devices.length > 1) {
                    cameraSelect.style.display = 'block';
                    cameraSelect.innerHTML = '';
                    devices.forEach(d => {
                        const option = document.createElement('option');
                        option.value = d.id; option.text = d.label || `Camera ${d.id}`;
                        cameraSelect.appendChild(option);
                    });
                    cameraSelect.value = cameraId;
                    cameraSelect.addEventListener('change', () => {
                        stopQrScanner();
                        html5QrCode.start(cameraSelect.value, config, onScanSuccess, onScanFailure)
                            .catch(err => displayScannerMessage('danger', `Camera switch error: ${sanitizeOutputJS(err.message)}`, true));
                    });
                }

                html5QrCode.start(cameraId, config, onScanSuccess, onScanFailure)
                    .then(() => { displayScannerMessage('info', "Scanner active. Aim at QR code.", false); })
                    .catch(err => { 
                        qrScannerErrorLog.textContent = "START ERROR: " + sanitizeOutputJS(err.message || err); 
                        displayScannerMessage('danger', "Could not start scanner.", true);
                    });
            } else { 
                qrScannerErrorLog.textContent = "No cameras found."; 
                displayScannerMessage('warning', "No cameras detected.", true); 
            }
        }).catch(err => { 
            qrScannerErrorLog.textContent = "CAMERA ERROR: " + sanitizeOutputJS(err.message || err); 
            displayScannerMessage('danger', err.message.includes('Permission denied') ? "Camera access denied. Please allow camera permissions." : "Camera access error.", true);
        });

        setTimeout(() => {
            if (!html5QrCode || !html5QrCode.isScanning) {
                displayScannerMessage('danger', "Scanner failed to start within 10 seconds.", true);
            }
        }, 10000);
    }

    function stopQrScanner() {
        if (html5QrCode && (html5QrCode.isScanning || (html5QrCode.getState && (html5QrCode.getState() === Html5Qrcode.SCANNING_STATE_SCANNING || html5QrCode.getState() === Html5Qrcode.SCANNING_STATE_PAUSED)))) {
            html5QrCode.stop().catch(err => console.warn("Error stopping scanner:", err));
        }
        qrScannerModal.style.display = 'none';
    }

    function handleBackendResponse(data) {
        if (!data || typeof data !== 'object') {
            displayScannerMessage('danger', 'Invalid server response.', true);
            return;
        }
        if (data.success) {
            displayScannerMessage('success', data.message, true);
            setTimeout(() => { window.location.reload(); }, 2000); 
        } else {
            if (data.code === 'USER_NOT_REGISTERED_BUT_VERIFIED' && data.data && data.data.userId) {
                qrScannerResultDiv.innerHTML = `
                    <div class="alert alert-warning mt-2">
                        <h4>On-the-Spot Registration?</h4>
                        <p>${sanitizeOutputJS(data.message)}</p>
                        <p>User: <strong>${sanitizeOutputJS(data.data.userName)}</strong> (NID: ${sanitizeOutputJS(data.data.niftemId || 'N/A')})</p>
                        <button type="button" class="cta-button btn-success btn-sm" id="confirmRegisterAndCheckInBtnJS_AER" data-user-id="${data.data.userId}">Yes, Register & Check-In</button>
                        <button type="button" class="cta-button btn-danger-outline btn-sm ml-2" id="cancelRegisterBtnJS_AER">No, Cancel</button>
                    </div>`;
                
                document.getElementById('confirmRegisterAndCheckInBtnJS_AER').addEventListener('click', function() {
                    const userIdToReg = this.dataset.userId;
                    displayScannerMessage('info', `Registering and checking in ${sanitizeOutputJS(data.data.userName)}...`, true);
                    fetch('<?php echo BASE_URL; ?>admin/process_qr_scan.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: `action=register_and_checkin&event_id=${currentEventId}&user_id_to_process=${userIdToReg}&csrf_token_ajax=${encodeURIComponent(csrfTokenForAjax)}`
                    })
                    .then(response => response.json())
                    .then(regData => handleBackendResponse(regData))
                    .catch(error => displayScannerMessage('danger', `On-spot Reg Error: ${error.message}`, true));
                });
                document.getElementById('cancelRegisterBtnJS_AER').addEventListener('click', resetScanner);
            } else { 
                displayScannerMessage('danger', data.message || 'An unknown error occurred.', true);
            }
        }
        if (data.code !== 'USER_NOT_REGISTERED_BUT_VERIFIED') { 
            resetScanner();
        }
    }

    function processScanAction(scanType) { 
        if (!lastScannedQrData) {
            displayScannerMessage('warning', "No QR code data captured. Please scan a user's ID first.", true);
            resetScanner();
            return;
        }
        displayScannerMessage('info', `Processing ${scanType}...`, true);
        fetch('<?php echo BASE_URL; ?>admin/process_qr_scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: `action=scan_attendance&event_id=${currentEventId}&scanned_user_identifier=${encodeURIComponent(lastScannedQrData)}&scan_type=${scanType}&csrf_token_ajax=${encodeURIComponent(csrfTokenForAjax)}`
        })
        .then(response => {
            if (!response.ok) { throw new Error(`HTTP error ${response.status}: ${response.statusText}`); }
            return response.json();
        })
        .then(data => handleBackendResponse(data))
        .catch(error => {
            console.error('Error processing scan AJAX:', error);
            displayScannerMessage('danger', `Server Communication Error: ${error.message}`, true);
            resetScanner();
        });
    }

    if (launchScannerButton && qrScannerModal) {
        launchScannerButton.addEventListener('click', () => {
            qrScannerModal.style.display = 'block';
            startQrScanner();
        });
    }
    if (closeQrScannerButton) {
        closeQrScannerButton.addEventListener('click', stopQrScanner);
    }
    if (checkInButton) checkInButton.addEventListener('click', () => processScanAction('entry'));
    if (checkOutButton) checkOutButton.addEventListener('click', () => processScanAction('exit'));
    if (resetButton) resetButton.addEventListener('click', resetScanner);
});
</script>
<?php include '../includes/footer.php'; ?>

