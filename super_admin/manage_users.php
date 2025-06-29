    <?php
    // geetanjali_website/super_admin/manage_users.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php'; // For enforceRoleAccess, getAllUsers, logAudit, sanitizeOutput, getDefinedRoles

    enforceRoleAccess(['super_admin']);

    $pageTitle = "Manage User Accounts";
    // $additional_css[] = '../css/admin_table_style.css'; 
    // $additional_css[] = '../css/admin_manage_users.css'; // Potentially a new CSS file for this specific page

    $message = ""; // For success/error messages

    // Get defined roles and account statuses for dropdowns
    $defined_roles = getDefinedRoles($conn); 
    $account_statuses_available = ['active', 'pending_verification', 'blocked', 'deactivated', 'all']; // Added 'all' for filter
    $verification_statuses_available = ['all', 'verified', 'not_verified'];


    // --- Filtering and Search ---
    $filter_role = isset($_GET['filter_role']) && in_array($_GET['filter_role'], array_merge(['all'], $defined_roles)) ? $_GET['filter_role'] : 'all';
    $filter_account_status = isset($_GET['filter_account_status']) && in_array($_GET['filter_account_status'], $account_statuses_available) ? $_GET['filter_account_status'] : 'all';
    $filter_is_verified = isset($_GET['filter_is_verified']) && in_array($_GET['filter_is_verified'], $verification_statuses_available) ? $_GET['filter_is_verified'] : 'all';
    $search_term = isset($_GET['search']) ? trim(filter_var($_GET['search'], FILTER_SANITIZE_STRING)) : '';

    // Build SQL query based on filters and search
    $sql_users = "SELECT id, niftem_id, name, email, role, is_verified, account_status, registration_date FROM users WHERE 1=1";
    $params = [];
    $types = "";

    if ($filter_role !== 'all') {
        $sql_users .= " AND role = ?";
        $params[] = $filter_role;
        $types .= "s";
    }
    if ($filter_account_status !== 'all') {
        $sql_users .= " AND account_status = ?";
        $params[] = $filter_account_status;
        $types .= "s";
    }
    if ($filter_is_verified !== 'all') {
        $is_verified_value = ($filter_is_verified === 'verified') ? 1 : 0;
        $sql_users .= " AND is_verified = ?";
        $params[] = $is_verified_value;
        $types .= "i";
    }
    if (!empty($search_term)) {
        $sql_users .= " AND (name LIKE ? OR niftem_id LIKE ? OR email LIKE ?)";
        $search_like = "%" . $search_term . "%";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
        $types .= "sss";
    }
    $sql_users .= " ORDER BY registration_date DESC";

    $stmt_users = $conn->prepare($sql_users);
    if ($stmt_users) {
        if (!empty($types) && !empty($params)) {
            $stmt_users->bind_param($types, ...$params);
        }
        if ($stmt_users->execute()) {
            $result_users = $stmt_users->get_result();
            $users = [];
            while ($row = $result_users->fetch_assoc()) {
                $users[] = $row;
            }
        } else {
            $message .= "<div class='form-message error'>Error fetching users: " . htmlspecialchars($stmt_users->error) . "</div>";
            error_log("Manage Users - User fetch execute error: " . $stmt_users->error);
            $users = [];
        }
        $stmt_users->close();
    } else {
        $message .= "<div class='form-message error'>Error preparing user query: " . htmlspecialchars($conn->error) . "</div>";
        error_log("Manage Users - User fetch prepare error: " . $conn->error . " SQL: " . $sql_users);
        $users = [];
    }


    // Handle role update from this page (Quick Role Change)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_on_list'])) {
        $user_id_to_update = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $new_role = isset($_POST['new_role']) ? $_POST['new_role'] : null;
        
        $current_user_being_edited = null; 
        if ($user_id_to_update > 0) {
            $current_user_being_edited = getUserById($conn, $user_id_to_update);
        }

        if ($user_id_to_update > 0 && $current_user_being_edited && $new_role && in_array($new_role, $defined_roles)) {
            $can_update_role = true;
            if ($user_id_to_update == $_SESSION['user_id'] && $current_user_being_edited['role'] == 'super_admin' && $new_role != 'super_admin') {
                $stmt_count_super = $conn->prepare("SELECT COUNT(*) as total_super_admins FROM users WHERE role = 'super_admin' AND account_status = 'active'");
                if($stmt_count_super){
                    $stmt_count_super->execute(); $res_super = $stmt_count_super->get_result();
                    $count_super_admins = $res_super->fetch_assoc()['total_super_admins']; $stmt_count_super->close();
                    if ($count_super_admins <= 1) {
                        $message = "<div class='form-message error'>Cannot change the role of the only active Super Admin.</div>";
                        $can_update_role = false;
                    }
                } else { 
                    error_log("Manage users - Super admin count prepare failed: " . $conn->error); 
                    $message = "<div class='form-message error'>DB error checking super admin count.</div>";
                    $can_update_role = false;
                }
            }
            
            if ($can_update_role) {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_role, $user_id_to_update);
                    if ($stmt->execute()) {
                        logAudit($conn, $_SESSION['user_id'], "Updated user role via manage list", "user", $user_id_to_update, "New role: $new_role");
                        $_SESSION['success_message'] = "User role for '" . sanitizeOutput($current_user_being_edited['name']) . "' updated successfully to " . ucfirst(str_replace('_', ' ', $new_role)) . ".";
                        header("Location: " . $_SERVER['REQUEST_URI']); 
                        exit;
                    } else {
                        $message = "<div class='form-message error'>Failed to update role: " . htmlspecialchars($stmt->error) . "</div>";
                        error_log("Manage users - role update execute failed: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                     $message = "<div class='form-message error'>Database error preparing role update.</div>";
                     error_log("Manage users - role update prepare failed: " . $conn->error);
                }
            }
        } elseif ($user_id_to_update <= 0) {
            $message = "<div class='form-message error'>Invalid user ID for update.</div>";
        } elseif (!$new_role || !in_array($new_role, $defined_roles)) {
             $message = "<div class='form-message error'>Invalid role selected for update.</div>";
        }
    }

    // Display session messages if any (e.g., from edit_user.php redirect or self-redirect)
    if (isset($_SESSION['error_message'])) {
        $message .= "<div class='form-message error'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
        unset($_SESSION['error_message']);
    }
    if (isset($_SESSION['success_message'])) {
        $message .= "<div class='form-message success'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
        unset($_SESSION['success_message']);
    }

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-users-cog"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Oversee all user accounts, manage roles, and verification status.</p>
                 <div class="header-actions-bar">
                    <a href="<?php echo BASE_URL; ?>super_admin/dashboard.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>super_admin/add_user.php" class="btn btn-sm btn-success"><i class="fas fa-user-plus"></i> Add New User</a>
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
                            <label for="filter_role">Filter by Role</label>
                            <select id="filter_role" name="filter_role" class="form-control">
                                <option value="all">All Roles</option>
                                <?php foreach ($defined_roles as $role_opt): ?>
                                    <option value="<?php echo $role_opt; ?>" <?php echo ($filter_role == $role_opt) ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $role_opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_account_status">Filter by Account Status</label>
                            <select id="filter_account_status" name="filter_account_status" class="form-control">
                                <?php foreach ($account_statuses_available as $status_opt): ?>
                                     <option value="<?php echo $status_opt; ?>" <?php echo ($filter_account_status == $status_opt) ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $status_opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_is_verified">Filter by Verification</label>
                            <select id="filter_is_verified" name="filter_is_verified" class="form-control">
                                <?php foreach ($verification_statuses_available as $ver_opt): ?>
                                     <option value="<?php echo $ver_opt; ?>" <?php echo ($filter_is_verified == $ver_opt) ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $ver_opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-check-circle"></i> Apply Filters</button>
                        <a href="<?php echo BASE_URL; ?>super_admin/manage_users.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear All</a>
                    </div>
                </form>
            </section>

            <section class="content-section std-profile-main-content card-style-admin"> 
                <div class="table-header-controls">
                    <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> User Accounts List</h2>
                    <span class="record-count"><?php echo count($users); ?> user(s) found</span>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="admin-table verify-users-table stylish-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>NIFTEM ID</th>
                                <th>Name & Verification</th>
                                <th>Email</th>
                                <th>Current Role</th>
                                <th>Account Status</th>
                                <th>Quick Role Change</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="user-row-status-<?php echo sanitizeOutput($user['account_status'] ?? 'unknown'); ?>">
                                        <td><?php echo sanitizeOutput($user['id']); ?></td>
                                        <td><?php echo sanitizeOutput($user['niftem_id']); ?></td>
                                        <td class="user-name-cell">
                                            <div class="user-name-wrapper">
                                                <span><?php echo sanitizeOutput($user['name']); ?></span>
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
                                        <td>
                                            <?php if ($user['id'] == $_SESSION['user_id'] && $user['role'] == 'super_admin'): ?>
                                                <span class="text-muted small-text">N/A (Self)</span>
                                            <?php else: ?>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?<?php echo http_build_query($_GET); // Preserve filters ?>" method="POST" class="inline-form quick-role-update-form">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="new_role" aria-label="New role for <?php echo sanitizeOutput($user['name']); ?>" class="form-control-sm">
                                                    <?php foreach ($defined_roles as $role_option): ?>
                                                        <option value="<?php echo $role_option; ?>" <?php echo ($user['role'] == $role_option) ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst(str_replace('_', ' ', $role_option)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="update_user_on_list" class="btn btn-xs btn-action-primary" title="Update Role"><i class="fas fa-check"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="<?php echo BASE_URL; ?>super_admin/edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-xs btn-action-info" title="Edit User Details">
                                                <i class="fas fa-user-edit"></i> Details
                                            </a>
                                            <?php /* Add other actions like 'Impersonate User' (with extreme caution) or 'View Activity Log' */ ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center no-results-row">No users found matching your criteria. <a href="<?php echo BASE_URL; ?>super_admin/manage_users.php">Clear all filters</a>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    