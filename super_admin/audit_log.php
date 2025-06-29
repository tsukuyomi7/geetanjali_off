    <?php
    // geetanjali_website/super_admin/audit_log.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php'; // Ensure time_elapsed_string, getSetting, etc. are here

    // --- CRITICAL INCLUDES AND CHECKS ---
    if (!isset($conn) || !$conn || $conn->connect_error) {
        error_log("FATAL ERROR in audit_log.php: Database connection failed. Error: " . ($conn ? $conn->connect_error : 'Unknown DB issue.'));
        die("A critical database connection error occurred. (Error Code: SAL_DB01)");
    }
    if (!defined('BASE_URL')) {
        error_log("FATAL ERROR in audit_log.php: BASE_URL constant is not defined.");
        die("A critical configuration error (BASE_URL missing). (Error Code: SAL_BU01)");
    }
    $required_functions_sal = ['enforceRoleAccess', 'sanitizeOutput', 'getSetting', 'logAudit', 'time_elapsed_string'];
    foreach ($required_functions_sal as $func_sal) {
        if (!function_exists($func_sal)) {
            error_log("FATAL ERROR in audit_log.php: Required function '$func_sal' is not defined. Check includes/functions.php.");
            die("A critical site function ('$func_sal') is missing. (Error Code: SAL_FN01)");
        }
    }
    // --- END CRITICAL CHECKS ---

    enforceRoleAccess(['super_admin']);

    $pageTitle = "System Audit Log";
    $message = ""; // For success/error messages

    // --- Handle Clear Log Action ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_clear_log_submit'])) {
        $clear_period = $_POST['clear_period'] ?? 'older_than_90_days'; // Default period
        $interval_sql = "";
        $log_detail_period = "";

        switch ($clear_period) {
            case 'older_than_30_days':
                $interval_sql = "INTERVAL 30 DAY";
                $log_detail_period = "older than 30 days";
                break;
            case 'older_than_180_days':
                $interval_sql = "INTERVAL 180 DAY";
                $log_detail_period = "older than 180 days";
                break;
            case 'all':
                $interval_sql = ""; // No date condition, delete all
                $log_detail_period = "all entries";
                break;
            case 'older_than_90_days':
            default:
                $interval_sql = "INTERVAL 90 DAY";
                $log_detail_period = "older than 90 days";
                break;
        }

        $delete_sql = "DELETE FROM audit_log";
        if (!empty($interval_sql)) {
            $delete_sql .= " WHERE timestamp < DATE_SUB(NOW(), " . $interval_sql . ")";
        }
        // For 'all', no WHERE clause is needed.

        $stmt_delete = $conn->prepare($delete_sql);
        if ($stmt_delete) {
            if ($stmt_delete->execute()) {
                $affected_rows = $stmt_delete->affected_rows;
                logAudit($conn, $_SESSION['user_id'], "Cleared audit log entries", "audit_log", null, "Cleared $affected_rows entries ($log_detail_period).");
                $_SESSION['success_message_audit'] = "$affected_rows audit log entries ($log_detail_period) cleared successfully.";
            } else {
                $_SESSION['error_message_audit'] = "Failed to clear audit log entries: " . htmlspecialchars($stmt_delete->error);
                error_log("Audit Log Clear execute error: " . $stmt_delete->error . " SQL: " . $delete_sql);
            }
            $stmt_delete->close();
        } else {
            $_SESSION['error_message_audit'] = "Database error preparing to clear audit log: " . htmlspecialchars($conn->error);
            error_log("Audit Log Clear prepare error: " . $conn->error . " SQL: " . $delete_sql);
        }
        // Redirect to prevent form resubmission and to show session message
        header("Location: " . BASE_URL . "super_admin/audit_log.php" . (isset($_GET['tab']) ? "?tab=".$_GET['tab'] : ""));
        exit;
    }


    // --- Filtering ---
    $filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $filter_action_type = isset($_GET['action_type']) ? sanitizeOutput(trim($_GET['action_type'])) : '';
    $filter_date_from = isset($_GET['date_from']) ? sanitizeOutput(trim($_GET['date_from'])) : '';
    $filter_date_to = isset($_GET['date_to']) ? sanitizeOutput(trim($_GET['date_to'])) : '';
    $search_ip = isset($_GET['search_ip']) ? sanitizeOutput(trim($_GET['search_ip'])) : '';

    $filter_params_for_url = [];
    if ($filter_user_id) $filter_params_for_url['user_id'] = $filter_user_id;
    if ($filter_action_type) $filter_params_for_url['action_type'] = $filter_action_type;
    if ($filter_date_from) $filter_params_for_url['date_from'] = $filter_date_from;
    if ($filter_date_to) $filter_params_for_url['date_to'] = $filter_date_to;
    if ($search_ip) $filter_params_for_url['search_ip'] = $search_ip;


    // --- Pagination variables ---
    $items_per_page_setting = getSetting($conn, 'admin_items_per_page', 25);
    $limit = is_numeric($items_per_page_setting) && $items_per_page_setting > 0 ? (int)$items_per_page_setting : 25;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    
    // --- Build SQL query with filters ---
    $sql_base = "FROM audit_log al JOIN users u ON al.user_id = u.id";
    $where_clauses = [];
    $params = [];
    $types = "";

    if ($filter_user_id) {
        $where_clauses[] = "al.user_id = ?"; $params[] = $filter_user_id; $types .= "i";
    }
    if (!empty($filter_action_type)) {
        $where_clauses[] = "al.action LIKE ?"; $params[] = "%" . $filter_action_type . "%"; $types .= "s";
    }
    if (!empty($filter_date_from) && DateTime::createFromFormat('Y-m-d', $filter_date_from)) {
        $where_clauses[] = "DATE(al.timestamp) >= ?"; $params[] = $filter_date_from; $types .= "s";
    }
    if (!empty($filter_date_to) && DateTime::createFromFormat('Y-m-d', $filter_date_to)) {
        $where_clauses[] = "DATE(al.timestamp) <= ?"; $params[] = $filter_date_to; $types .= "s";
    }
    if (!empty($search_ip)) {
        $where_clauses[] = "al.ip_address = ?"; $params[] = $search_ip; $types .= "s";
    }
    
    $sql_where_condition = "";
    if (!empty($where_clauses)) {
        $sql_where_condition = " WHERE " . implode(" AND ", $where_clauses);
    }

    // Get total number of audit log entries for pagination
    $total_logs_sql = "SELECT COUNT(al.id) as total " . $sql_base . $sql_where_condition;
    $stmt_total = $conn->prepare($total_logs_sql);
    $total_logs = 0;
    if ($stmt_total) {
        if (!empty($types) && !empty($params)) { 
             if (!$stmt_total->bind_param($types, ...$params)) {
                error_log("Audit Log: Total count bind_param error: " . $stmt_total->error);
             }
        }
        if ($stmt_total->execute()) {
            $result_total = $stmt_total->get_result();
            $total_logs_row = $result_total->fetch_assoc();
            $total_logs = $total_logs_row ? (int)$total_logs_row['total'] : 0;
        } else { error_log("Audit Log: Total count execute error: " . $stmt_total->error); }
        $stmt_total->close();
    } else { error_log("Audit Log: Total count prepare error: " . $conn->error . " SQL: " . $total_logs_sql); }

    $total_pages = ($limit > 0 && $total_logs > 0) ? ceil($total_logs / $limit) : 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages; // Ensure current page is not out of bounds
    $offset = ($page - 1) * $limit;
    if ($offset < 0) $offset = 0;


    // Fetch audit logs with user names, IP, and User Agent
    $audit_logs = [];
    $sql_select = "SELECT al.id, al.user_id, u.name as admin_name, u.niftem_id as admin_niftem_id, 
                          al.action, al.target_type, al.target_id, al.details, 
                          al.ip_address, al.user_agent, al.timestamp ";
    $sql_final = $sql_select . $sql_base . $sql_where_condition . " ORDER BY al.timestamp DESC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql_final);
    if($stmt){
        $current_params_paginated = $params; // Start with filter params
        $current_types_paginated = $types;   // Start with filter types
        $current_types_paginated .= "ii";    // Add types for LIMIT and OFFSET
        $current_params_paginated[] = $limit;
        $current_params_paginated[] = $offset;
        
        if (!empty($current_types_paginated)) { // Check if types string is not empty
            if (!$stmt->bind_param($current_types_paginated, ...$current_params_paginated)) {
                error_log("Audit Log: Bind_param failed: " . $stmt->error . " Types: " . $current_types_paginated . " Params: " . print_r($current_params_paginated, true));
            }
        }
        
        if($stmt->execute()){
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $audit_logs[] = $row;
            }
        } else { error_log("Audit log fetch execute failed: " . $stmt->error); }
        $stmt->close();
    } else { error_log("Audit log prepare failed: " . $conn->error . " SQL: " . $sql_final); }
    
    // Fetch users for filter dropdown
    $admin_users_for_filter = [];
    $sql_admin_users = "SELECT id, name, niftem_id FROM users WHERE role IN ('admin', 'super_admin') ORDER BY name ASC";
    $res_admin_users = $conn->query($sql_admin_users);
    if ($res_admin_users) { while($row = $res_admin_users->fetch_assoc()) { $admin_users_for_filter[] = $row; } mysqli_free_result($res_admin_users); }

    // Display session messages if any (from redirect after clear log)
    if (isset($_SESSION['success_message_audit'])) {
        $message .= "<div class='form-message success'>" . sanitizeOutput($_SESSION['success_message_audit']) . "</div>";
        unset($_SESSION['success_message_audit']);
    }
    if (isset($_SESSION['error_message_audit'])) {
        $message .= "<div class='form-message error'>" . sanitizeOutput($_SESSION['error_message_audit']) . "</div>";
        unset($_SESSION['error_message_audit']);
    }

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-history"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Review of administrative actions, user activity, and system events.</p>
                 <p><a href="<?php echo BASE_URL; ?>super_admin/dashboard.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Super Admin Dashboard</a></p>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message'>" . $message . "</div>"; ?>

            <section class="content-section filters-and-search-section card-style-admin">
                <h2 class="section-title-minor"><i class="fas fa-filter"></i> Filter Audit Log</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="form-filters-search">
                    <div class="filter-grid audit-filter-grid"> 
                        <div class="form-group filter-item">
                            <label for="filter_user_id"><i class="fas fa-user"></i> By User</label>
                            <select id="filter_user_id" name="user_id" class="form-control">
                                <option value="">All Users</option>
                                <?php foreach ($admin_users_for_filter as $admin_user): ?>
                                    <option value="<?php echo $admin_user['id']; ?>" <?php if ($filter_user_id == $admin_user['id']) echo 'selected'; ?>>
                                        <?php echo sanitizeOutput($admin_user['name']) . ' (' . sanitizeOutput($admin_user['niftem_id']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_action_type"><i class="fas fa-bolt"></i> By Action Type (keyword)</label>
                            <input type="text" id="filter_action_type" name="action_type" class="form-control" placeholder="e.g., Updated, Deleted, Login" value="<?php echo sanitizeOutput($filter_action_type); ?>">
                        </div>
                         <div class="form-group filter-item">
                            <label for="search_ip"><i class="fas fa-network-wired"></i> By IP Address</label>
                            <input type="text" id="search_ip" name="search_ip" class="form-control" placeholder="e.g., 192.168.1.1" value="<?php echo sanitizeOutput($search_ip); ?>">
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_date_from"><i class="fas fa-calendar-day"></i> From Date</label>
                            <input type="date" id="filter_date_from" name="date_from" class="form-control" value="<?php echo sanitizeOutput($filter_date_from); ?>">
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_date_to"><i class="fas fa-calendar-day"></i> To Date</label>
                            <input type="date" id="filter_date_to" name="date_to" class="form-control" value="<?php echo sanitizeOutput($filter_date_to); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-check-circle"></i> Apply Filters</button>
                        <a href="<?php echo BASE_URL; ?>super_admin/audit_log.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear Filters</a>
                    </div>
                </form>
            </section>


            <section class="content-section std-profile-main-content card-style-admin">
                <div class="table-header-controls">
                     <h2 class="section-title-minor"><i class="fas fa-clipboard-list"></i> Log Entries (<?php echo $total_logs; ?> total)</h2>
                     <?php if($total_logs > 0 && $_SESSION['user_role'] === 'super_admin'): ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?<?php echo http_build_query($filter_params_for_url); ?>" method="POST" class="inline-form" id="clearLogForm">
                            <input type="hidden" name="action_clear_log_submit" value="1">
                            <label for="clear_period_select" class="sr-only">Select period to clear</label>
                            <select name="clear_period" id="clear_period_select" class="form-control-sm mr-1">
                                <option value="older_than_90_days">Older than 90 days</option>
                                <option value="older_than_30_days">Older than 30 days</option>
                                <option value="older_than_180_days">Older than 180 days</option>
                                <option value="all">Clear All Logs (Use with extreme caution!)</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-danger" id="confirmClearLogBtn"><i class="fas fa-trash-alt"></i> Clear Selected Logs</button>
                        </form>
                     <?php endif; ?>
                </div>

                <div class="table-responsive-wrapper">
                    <table class="admin-table stylish-table audit-log-table">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Timestamp</th>
                                <th>Admin User</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th class="details-column">Details</th>
                                <th>IP Address</th>
                                <th class="user-agent-column">User Agent (Info)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($audit_logs)): ?>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><?php echo sanitizeOutput($log['id']); ?></td>
                                        <td title="<?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?>"><?php echo time_elapsed_string($log['timestamp']); ?></td>
                                        <td>
                                            <?php echo sanitizeOutput($log['admin_name']); ?>
                                            <br><small class="text-muted">(ID: <?php echo sanitizeOutput($log['user_id']); ?>, NID: <?php echo sanitizeOutput($log['admin_niftem_id'] ?? 'N/A'); ?>)</small>
                                        </td>
                                        <td class="action-cell-log"><?php echo sanitizeOutput($log['action']); ?></td>
                                        <td>
                                            <?php 
                                            if (!empty($log['target_type'])) {
                                                echo "<strong>" . ucfirst(sanitizeOutput($log['target_type'])) . "</strong>";
                                                if (!empty($log['target_id'])) {
                                                    echo " (ID: " . sanitizeOutput($log['target_id']) . ")";
                                                }
                                            } else {
                                                echo "<span class='text-muted'>N/A</span>";
                                            }
                                            ?>
                                        </td>
                                        <td class="audit-details"><?php echo nl2br(sanitizeOutput($log['details'])); ?></td>
                                        <td><?php echo sanitizeOutput($log['ip_address'] ?? 'N/A'); ?></td>
                                        <td class="user-agent-details" title="<?php echo sanitizeOutput($log['user_agent'] ?? 'N/A'); ?>">
                                            <?php 
                                            $ua_display = 'Unknown';
                                            if (!empty($log['user_agent'])) {
                                                $ua = $log['user_agent'];
                                                $browser_info = "Unknown Browser";
                                                $os_info = "Unknown OS";
                                                if (preg_match('/(Mozilla|Opera|Chrome|Safari|Firefox|Edge|MSIE|Trident)/i', $ua, $matches_browser)) {
                                                    $browser_info = $matches_browser[0];
                                                    if (preg_match('/(Version\/|Chrome\/|Firefox\/|Edge\/|MSIE |rv:)(\d+)/i', $ua, $matches_version)) {
                                                        $browser_info .= " " . $matches_version[2];
                                                    }
                                                }
                                                if (preg_match('/(Windows NT.*?|Windows Phone.*?|Mac OS X.*?|Android.*?|Linux.*?|iPhone|iPad)/i', $ua, $matches_os)) {
                                                    $os_info = str_replace([' NT', ' Phone', ' OS X'], '', $matches_os[0]);
                                                }
                                                $ua_display = sanitizeOutput($browser_info . " / " . $os_info);
                                            }
                                            echo $ua_display;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center no-results-row">No audit log entries found matching your criteria.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav class="pagination-container mt-3" aria-label="Audit Log navigation">
                    <ul class="pagination-list">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filter_params_for_url); ?>"><i class="fas fa-chevron-left"></i> Previous</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span></li>
                        <?php endif; ?>

                        <?php 
                        $num_links_to_show_audit = 5; 
                        $start_page_audit = max(1, $page - floor($num_links_to_show_audit / 2));
                        $end_page_audit = min($total_pages, $start_page_audit + $num_links_to_show_audit - 1);
                        if ($end_page_audit - $start_page_audit + 1 < $num_links_to_show_audit) $start_page_audit = max(1, $end_page_audit - $num_links_to_show_audit + 1);
                        
                        if ($start_page_audit > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1&'.http_build_query($filter_params_for_url).'">1</a></li>';
                            if ($start_page_audit > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        for ($i = $start_page_audit; $i <= $end_page_audit; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filter_params_for_url); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; 
                        if ($end_page_audit < $total_pages):
                            if ($end_page_audit < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&'.http_build_query($filter_params_for_url).'">'.$total_pages.'</a></li>';
                        endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filter_params_for_url); ?>">Next <i class="fas fa-chevron-right"></i></a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Next <i class="fas fa-chevron-right"></i></span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const confirmClearLogBtn = document.getElementById('confirmClearLogBtn');
        const clearLogForm = document.getElementById('clearLogForm');
        const clearPeriodSelect = document.getElementById('clear_period_select');

        if (confirmClearLogBtn && clearLogForm && clearPeriodSelect) {
            confirmClearLogBtn.addEventListener('click', function(event) {
                event.preventDefault(); // Prevent immediate form submission
                const selectedPeriodText = clearPeriodSelect.options[clearPeriodSelect.selectedIndex].text;
                let confirmMessage = "Are you sure you want to permanently delete audit log entries " + selectedPeriodText + "?";
                if (clearPeriodSelect.value === 'all') {
                    confirmMessage = "Are you ABSOLUTELY sure you want to permanently delete ALL audit log entries? This action CANNOT be undone and will remove all historical log data.";
                }
                
                if (confirm(confirmMessage)) {
                    clearLogForm.submit();
                }
            });
        }
    });
    </script>
    <?php include '../includes/footer.php'; ?>
    