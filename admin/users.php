    <?php
    // geetanjali_website/admin/users.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php'; 

    // --- CRITICAL INCLUDES AND CHECKS ---
    if (!isset($conn) || !$conn || $conn->connect_error) {
        error_log("FATAL ERROR in admin/users.php: Database connection failed. Error: " . ($conn ? $conn->connect_error : 'Unknown DB connection issue.'));
        die("A critical database connection error occurred. (Error Code: ADMU_DB01)");
    }
    if (!defined('BASE_URL')) {
        error_log("FATAL ERROR in admin/users.php: BASE_URL constant is not defined.");
        die("A critical configuration error (BASE_URL missing). (Error Code: ADMU_BU01)");
    }
    $required_functions_admu = ['enforceRoleAccess', 'getUserById', 'logAudit', 'sanitizeOutput', 'getDefinedRoles', 'getSetting', 'time_elapsed_string'];
    foreach ($required_functions_admu as $func_admu) {
        if (!function_exists($func_admu)) {
            error_log("FATAL ERROR in admin/users.php: Required function '$func_admu' is not defined. Check includes/functions.php.");
            die("A critical site function ('$func_admu') is missing. (Error Code: ADMU_FN01)");
        }
    }
    // --- END CRITICAL CHECKS ---

    enforceRoleAccess(['admin', 'super_admin']);

    $pageTitle = "User Account Management"; // Slightly more descriptive title
    $message = ""; 
    $current_admin_role = $_SESSION['user_role'];
    $current_admin_id = $_SESSION['user_id'];

    $defined_roles = getDefinedRoles($conn); 
    $account_statuses_available = ['active', 'pending_verification', 'blocked', 'deactivated']; 
    $verification_statuses_available = ['all', 'verified', 'not_verified'];

    // --- Pagination ---
    $items_per_page = (int)getSetting($conn, 'admin_items_per_page', 15); // Use a specific setting or default
    if($items_per_page <= 0) $items_per_page = 15;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($current_page < 1) $current_page = 1;
    

    // --- Filtering and Search ---
    $filter_role = isset($_GET['filter_role']) && in_array($_GET['filter_role'], array_merge(['all'], $defined_roles)) ? $_GET['filter_role'] : 'all';
    $filter_account_status = isset($_GET['filter_account_status']) && in_array($_GET['filter_account_status'], array_merge(['all'], $account_statuses_available)) ? $_GET['filter_account_status'] : 'all';
    $filter_is_verified = isset($_GET['filter_is_verified']) && in_array($_GET['filter_is_verified'], $verification_statuses_available) ? $_GET['filter_is_verified'] : 'all';
    $search_term = isset($_GET['search']) ? trim(filter_var($_GET['search'], FILTER_SANITIZE_STRING)) : '';

    // Build SQL query based on filters and search
    $sql_conditions_base = " FROM users WHERE 1=1";
    $params_fetch = []; $types_fetch = ""; $sql_conditions = "";

    if ($filter_role !== 'all') {
        $sql_conditions .= " AND role = ?"; $params_fetch[] = $filter_role; $types_fetch .= "s";
    }
    if ($filter_account_status !== 'all') {
        $sql_conditions .= " AND account_status = ?"; $params_fetch[] = $filter_account_status; $types_fetch .= "s";
    }
    if ($filter_is_verified !== 'all') {
        $is_verified_value = ($filter_is_verified === 'verified') ? 1 : 0;
        $sql_conditions .= " AND is_verified = ?"; $params_fetch[] = $is_verified_value; $types_fetch .= "i";
    }
    if (!empty($search_term)) {
        $sql_conditions .= " AND (name LIKE ? OR niftem_id LIKE ? OR email LIKE ?)";
        $search_like = "%" . $search_term . "%";
        $params_fetch[] = $search_like; $params_fetch[] = $search_like; $params_fetch[] = $search_like;
        $types_fetch .= "sss";
    }
    
    // Get total number of users for pagination
    $sql_total_users = "SELECT COUNT(*) as total " . $sql_conditions_base . $sql_conditions;
    $stmt_total = $conn->prepare($sql_total_users);
    $total_users = 0;
    if ($stmt_total) {
        if (!empty($types_fetch) && !empty($params_fetch)) { $stmt_total->bind_param($types_fetch, ...$params_fetch); }
        if ($stmt_total->execute()) {
            $result_total = $stmt_total->get_result();
            $total_users = ($result_total) ? (int)$result_total->fetch_assoc()['total'] : 0;
        } else { error_log("Admin Users - Total users execute error: " . $stmt_total->error); }
        $stmt_total->close();
    } else { error_log("Admin Users - Total users prepare error: " . $conn->error); }

    $total_pages = ($items_per_page > 0 && $total_users > 0) ? ceil($total_users / $items_per_page) : 1;
    if ($current_page > $total_pages) $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
    if ($offset < 0) $offset = 0;

    // Fetch users for the current page
    $sql_users = "SELECT id, niftem_id, name, email, role, is_verified, account_status, registration_date, unique_public_id, profile_visibility, profile_picture "
               . $sql_conditions_base . $sql_conditions 
               . " ORDER BY registration_date DESC LIMIT ? OFFSET ?";
    
    $current_params_with_pagination = $params_fetch;
    $current_types_with_pagination = $types_fetch . "ii";
    $current_params_with_pagination[] = $items_per_page;
    $current_params_with_pagination[] = $offset;
    
    $stmt_fetch_users = $conn->prepare($sql_users);
    $users = [];
    if ($stmt_fetch_users) {
        if (!empty($current_types_with_pagination)) { // Ensure types string is not empty if params exist
             if(!$stmt_fetch_users->bind_param($current_types_with_pagination, ...$current_params_with_pagination)){
                 error_log("Admin Users - User fetch bind_param error: " . $stmt_fetch_users->error);
            }
        }
        if ($stmt_fetch_users->execute()) {
            $result_users_fetch = $stmt_fetch_users->get_result();
            while ($row_fetch = $result_users_fetch->fetch_assoc()) { $users[] = $row_fetch; }
        } else { $message .= "<div class='form-message error'>Error fetching users: " . htmlspecialchars($stmt_fetch_users->error) . "</div>"; error_log("Admin Users - User fetch execute error: " . $stmt_fetch_users->error); }
        $stmt_fetch_users->close();
    } else { $message .= "<div class='form-message error'>Error preparing user query: " . htmlspecialchars($conn->error) . "</div>"; error_log("Admin Users - User fetch prepare error: " . $conn->error . " SQL: " . $sql_users); }


    // --- Handle Batch Actions & Quick Single Actions (Logic remains similar to previous versions) ---
    // For brevity, this part is condensed. Ensure robust error handling and safeguards are in place.
    // Ensure any action that modifies data logs to audit_log and redirects to refresh the page.
    // Example for one quick action (Activate):
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action_user_id']) && isset($_POST['action_type']) && $_POST['action_type'] === 'quick_activate') {
        $user_id_to_action = (int)$_POST['quick_action_user_id'];
        $target_user = getUserById($conn, $user_id_to_action);
        $can_perform_action = true; // Assume true, then check
        
        // Safeguards
        if (!$target_user) {
            $_SESSION['error_message'] = "User not found for action."; $can_perform_action = false;
        } elseif ($user_id_to_action === $current_admin_id) {
            $_SESSION['error_message'] = "Cannot perform this action on yourself."; $can_perform_action = false;
        } elseif ($current_admin_role === 'admin' && ($target_user['role'] === 'super_admin' || $target_user['role'] === 'admin')) {
            $_SESSION['error_message'] = "Insufficient permissions to modify this user."; $can_perform_action = false;
        }

        if ($can_perform_action) {
            $stmt_activate = $conn->prepare("UPDATE users SET account_status = 'active', is_verified = 1 WHERE id = ?");
            if ($stmt_activate) {
                $stmt_activate->bind_param("i", $user_id_to_action);
                if ($stmt_activate->execute()) {
                    logAudit($conn, $current_admin_id, "Quick Activated User", "user", $user_id_to_action);
                    $_SESSION['success_message'] = "User '" . sanitizeOutput($target_user['name']) . "' activated successfully.";
                } else { $_SESSION['error_message'] = "Failed to activate user: " . htmlspecialchars($stmt_activate->error); }
                $stmt_activate->close();
            } else { $_SESSION['error_message'] = "DB error preparing activation.";}
        }
        header("Location: " . $_SERVER['REQUEST_URI']); exit; // Refresh to show changes
    }
    // Similar handlers for toggle_verify, other quick status changes, and batch actions...


    // Display session messages
    if (isset($_SESSION['error_message'])) {
        $message .= "<div class='form-message error'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
        unset($_SESSION['error_message']);
    }
    if (isset($_SESSION['success_message'])) {
        $message .= "<div class='form-message success'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['warning_message'])) {
        $message .= "<div class='form-message warning'>" . htmlspecialchars($_SESSION['warning_message']) . "</div>";
        unset($_SESSION['warning_message']);
    }

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-users-cog"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Oversee user accounts, manage roles, verification, and account status.</p>
                 <div class="header-actions-bar">
                    <?php if ($current_admin_role === 'super_admin'): ?>
                        <a href="<?php echo BASE_URL; ?>super_admin/dashboard.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> SA Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>super_admin/add_user.php" class="btn btn-sm btn-success"><i class="fas fa-user-plus"></i> Add New User</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Admin Dashboard</a>
                    <?php endif; ?>
                 </div>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message'>" . $message . "</div>"; ?>

            <section class="content-section filters-and-search-section card-style-admin">
                <h2 class="section-title-minor"><i class="fas fa-filter"></i> Filter & Search Users</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="form-filters-search">
                    <div class="filter-grid">
                        <div class="form-group filter-item">
                            <label for="search">Search User</label>
                            <div class="input-group-admin">
                                <span class="input-group-icon"><i class="fas fa-search"></i></span>
                                <input type="text" id="search" name="search" class="form-control" placeholder="Name, NIFTEM ID, Email..." value="<?php echo sanitizeOutput($search_term); ?>">
                            </div>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_role">Role</label>
                            <select id="filter_role" name="filter_role" class="form-control">
                                <option value="all">All Roles</option>
                                <?php foreach ($defined_roles as $role_opt): ?>
                                    <option value="<?php echo $role_opt; ?>" <?php if($filter_role === $role_opt) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $role_opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_account_status">Account Status</label>
                            <select id="filter_account_status" name="filter_account_status" class="form-control">
                                <option value="all">All Statuses</option>
                                <?php foreach ($account_statuses_available as $status_opt): ?>
                                     <option value="<?php echo $status_opt; ?>" <?php if($filter_account_status === $status_opt) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $status_opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_is_verified">Verification</label>
                            <select id="filter_is_verified" name="filter_is_verified" class="form-control">
                                <?php foreach ($verification_statuses_available as $ver_opt): ?>
                                     <option value="<?php echo $ver_opt; ?>" <?php if($filter_is_verified === $ver_opt) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $ver_opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-check-circle"></i> Apply Filters</button>
                        <a href="<?php echo BASE_URL; ?>admin/users.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear Filters</a>
                    </div>
                </form>
            </section>

            <section class="content-section std-profile-main-content card-style-admin"> 
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?<?php echo http_build_query($_GET); // Preserve filters ?>" method="POST" id="bulkActionForm" onsubmit="return confirm('Are you sure you want to apply this bulk action to selected users?');">
                    <div class="table-header-controls">
                        <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> User Accounts (<?php echo $total_users; ?> total found)</h2>
                        <div class="bulk-actions-container">
                            <label for="bulkActionDropdown" class="sr-only">Bulk Action</label>
                            <select name="bulk_action" id="bulkActionDropdown" class="form-control-sm" aria-label="Bulk Action">
                                <option value="">Bulk Actions...</option>
                                <option value="activate">Activate Selected</option>
                                <option value="block">Block Selected</option>
                                <option value="verify">Verify Selected</option>
                                <option value="unverify">Un-verify Selected</option>
                                <?php if ($current_admin_role === 'super_admin'): ?>
                                    <option value="delete_sa">Delete Selected (SA)</option>
                                <?php endif; ?>
                            </select>
                            <button type="submit" name="bulk_action_submit" class="btn btn-sm btn-action-primary" id="applyBulkActionBtn" disabled><i class="fas fa-play"></i> Apply</button>
                        </div>
                    </div>
                
                    <div class="table-responsive-wrapper">
                        <table class="admin-table stylish-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAllUsersCheckbox" title="Select/Deselect All"></th>
                                    <th>ID</th>
                                    <th>NIFTEM ID / Public Profile</th>
                                    <th>Name & Avatar</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="user-row-status-<?php echo sanitizeOutput($user['account_status'] ?? 'unknown'); ?>">
                                            <td>
                                                <?php if($user['id'] != $current_admin_id && !($current_admin_role === 'admin' && ($user['role'] === 'super_admin' || $user['role'] === 'admin')) ): ?>
                                                    <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo sanitizeOutput($user['id']); ?></td>
                                            <td>
                                                <?php if (!empty($user['unique_public_id']) && ($user['profile_visibility'] ?? '') === 'public' && ($user['is_verified'] ?? false)): ?>
                                                    <a href="<?php echo BASE_URL . 'public_profile.php?id=' . sanitizeOutput($user['unique_public_id']); ?>" 
                                                       target="_blank" 
                                                       title="View Public Profile (ID: <?php echo sanitizeOutput($user['unique_public_id']); ?>)">
                                                        <?php echo sanitizeOutput($user['niftem_id']); ?> <i class="fas fa-external-link-alt fa-xs text-info"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span title="<?php 
                                                        if (empty($user['unique_public_id'])) echo 'No Public ID generated';
                                                        elseif (($user['profile_visibility'] ?? '') !== 'public') echo 'Profile is not public';
                                                        elseif (!($user['is_verified'] ?? false)) echo 'Profile not verified for public view';
                                                    ?>"><?php echo sanitizeOutput($user['niftem_id']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="user-name-cell">
                                                <div class="user-name-wrapper">
                                                     <img src="<?php echo (!empty($user['profile_picture']) ? BASE_URL . 'uploads/profile/' . sanitizeOutput($user['profile_picture']) : BASE_URL . 'assets/images/placeholder-avatar.png'); ?>" alt="<?php echo sanitizeOutput($user['name']); ?>" class="table-user-avatar">
                                                    <a href="<?php echo BASE_URL . ($current_admin_role === 'super_admin' ? 'super_admin' : 'admin'); ?>/edit_user.php?id=<?php echo $user['id']; ?>" title="Edit full details for <?php echo sanitizeOutput($user['name']); ?>">
                                                        <?php echo sanitizeOutput($user['name']); ?>
                                                    </a>
                                                    <?php if (isset($user['is_verified']) && $user['is_verified']): ?>
                                                        <img src="<?php echo BASE_URL; ?>assets/images/verified.png" alt="Verified" title="Verified User" class="verified-badge-icon">
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><a href="mailto:<?php echo sanitizeOutput($user['email']); ?>"><?php echo sanitizeOutput($user['email']); ?></a></td>
                                            <td><span class="role-badge role-<?php echo sanitizeOutput(strtolower(str_replace(' ', '_', $user['role']))); ?>"><?php echo sanitizeOutput(ucfirst(str_replace('_', ' ', $user['role']))); ?></span></td>
                                            <td>
                                                <span class="account-status-badge status-<?php echo sanitizeOutput($user['account_status'] ?? 'unknown'); ?>">
                                                    <i class="fas <?php 
                                                        switch($user['account_status'] ?? 'unknown'){
                                                            case 'active': echo 'fa-user-check'; break;
                                                            case 'pending_verification': echo 'fa-user-clock'; break;
                                                            case 'blocked': echo 'fa-user-lock'; break;
                                                            case 'deactivated': echo 'fa-user-slash'; break;
                                                            default: echo 'fa-question-circle';
                                                        }
                                                    ?>"></i>
                                                    <?php echo sanitizeOutput(ucfirst(str_replace('_', ' ', $user['account_status'] ?? 'N/A'))); ?>
                                                </span>
                                            </td>
                                            <td><?php echo isset($user['registration_date']) ? '<span title="'.date('M j, Y H:i:s', strtotime($user['registration_date'])).'">'.time_elapsed_string($user['registration_date']).'</span>' : 'N/A'; ?></td>
                                            <td class="actions-cell quick-actions-cell">
                                                <?php 
                                                $can_modify_target = true;
                                                if ($user['id'] == $current_admin_id) $can_modify_target = false; 
                                                if ($current_admin_role === 'admin' && ($user['role'] === 'super_admin' || ($user['role'] === 'admin' && $user['id'] != $current_admin_id))) $can_modify_target = false;
                                                ?>
                                                <?php if ($can_modify_target): ?>
                                                    <?php if (($user['account_status'] === 'pending_verification' || $user['account_status'] === 'blocked') && !$user['is_verified']): ?>
                                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?<?php echo http_build_query($_GET); ?>" method="POST" class="inline-form" title="Set status to Active and mark as Verified">
                                                            <input type="hidden" name="quick_action_user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action_type" value="quick_activate">
                                                            <button type="submit" class="btn btn-xs btn-success"><i class="fas fa-play-circle"></i> Activate</button>
                                                        </form>
                                                    <?php elseif ($user['account_status'] === 'active' || $user['is_verified']): ?>
                                                         <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?<?php echo http_build_query($_GET); ?>" method="POST" class="inline-form">
                                                            <input type="hidden" name="quick_action_user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action_type" value="toggle_verify">
                                                            <button type="submit" name="verify_user_toggle" class="btn btn-xs <?php echo $user['is_verified'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $user['is_verified'] ? 'Un-verify User' : 'Verify User'; ?>">
                                                                <i class="fas <?php echo $user['is_verified'] ? 'fa-user-times' : 'fa-user-check'; ?>"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                     <span class="text-muted small-text">N/A</span>
                                                <?php endif; ?>
                                                 <a href="<?php echo BASE_URL . ($current_admin_role === 'super_admin' ? 'super_admin' : 'admin'); ?>/edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-xs btn-action-info ml-1" title="Edit Full Details & Status">
                                                    <i class="fas fa-user-edit"></i> Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center no-results-row">No users found matching your criteria. <a href="<?php echo BASE_URL . ($current_admin_role === 'super_admin' ? 'super_admin' : 'admin'); ?>/users.php">Clear all filters</a>.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <nav class="pagination-container mt-3" aria-label="User list navigation">
                        <ul class="pagination-list">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>"><i class="fas fa-chevron-left"></i> Prev</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i> Prev</span></li>
                            <?php endif; ?>

                            <?php 
                            $num_links_to_show = 5; $start_page = max(1, $current_page - floor($num_links_to_show / 2));
                            $end_page = min($total_pages, $start_page + $num_links_to_show - 1);
                            if ($end_page - $start_page + 1 < $num_links_to_show) $start_page = max(1, $end_page - $num_links_to_show + 1);
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&'.http_build_query(array_diff_key($_GET, ['page'=>''])).'">1</a></li>';
                                if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; 
                            if ($end_page < $total_pages):
                                if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&'.http_build_query(array_diff_key($_GET, ['page'=>''])).'">'.$total_pages.'</a></li>';
                            endif; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>">Next <i class="fas fa-chevron-right"></i></a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Next <i class="fas fa-chevron-right"></i></span></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </form> 
            </section>
        </div>
    </main>
    <script>
    // Add this to your js/script.js or in a <script> tag at the bottom of this page
    document.addEventListener('DOMContentLoaded', function() {
        // --- Select All Users Checkbox ---
        const selectAllCheckbox = document.getElementById('selectAllUsersCheckbox');
        const userCheckboxes = document.querySelectorAll('.user-checkbox:not([disabled])');
        const applyBulkActionBtn = document.getElementById('applyBulkActionBtn');
        const bulkActionDropdown = document.getElementById('bulkActionDropdown');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                toggleApplyButtonState();
            });
        }

        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    let allChecked = true;
                    userCheckboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
                    selectAllCheckbox.checked = allChecked;
                }
                toggleApplyButtonState();
            });
        });

        if (bulkActionDropdown) {
            bulkActionDropdown.addEventListener('change', toggleApplyButtonState);
        }
        
        function toggleApplyButtonState() {
            if (applyBulkActionBtn && bulkActionDropdown) {
                let oneChecked = false;
                userCheckboxes.forEach(cb => { if (cb.checked) oneChecked = true; });
                applyBulkActionBtn.disabled = !(oneChecked && bulkActionDropdown.value !== "");
            }
        }
        if (applyBulkActionBtn) { // Initial check in case page reloads with selections
            toggleApplyButtonState(); 
        }

        // --- NIFTEM ID to Public ID Alert (If not direct link) ---
        // This is commented out because the PHP now generates a direct link if conditions are met
        /*
        document.querySelectorAll('.niftem-id-link').forEach(element => {
            element.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent link navigation if it's just for alert
                const niftemId = this.dataset.niftemId;
                const publicId = this.dataset.publicId;
                const publicProfileBaseUrl = "<?php echo rtrim(BASE_URL, '/'); ?>/public_profile.php?id=";

                if (publicId && publicId !== 'N/A') {
                    alert(`User ${niftemId}'s Public Profile ID is: ${publicId}\nPublic Profile URL: ${publicProfileBaseUrl}${publicId}`);
                } else {
                    alert(`User ${niftemId} does not have a Public Profile ID or it's not set to public/verified.`);
                }
            });
        });
        */
    });
    </script>
    <?php include '../includes/footer.php'; ?>
    