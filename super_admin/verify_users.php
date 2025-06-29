    <?php
    // geetanjali_website/super_admin/verify_users.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; // Defines $conn and BASE_URL
    require_once '../includes/functions.php';     // For enforceRoleAccess(), getAllUsers(), logAudit(), sanitizeOutput()

    // Enforce Super Admin access
    enforceRoleAccess(['super_admin']);

    $pageTitle = "Verify User Accounts";
    // $additional_css[] = '../css/admin_table_style.css'; // If you have specific admin table CSS

    $message = ""; // For success/error messages

    // Handle user verification action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_user'])) {
        $user_id_to_verify = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $current_status = isset($_POST['current_status']) ? (int)$_POST['current_status'] : 0; // 0 for not verified, 1 for verified
        
        $new_status = ($current_status == 1) ? 0 : 1; // Toggle status

        if ($user_id_to_verify > 0) {
            // Prevent super_admin from un-verifying themselves if they are the only super_admin or other critical logic
            if ($user_id_to_verify == $_SESSION['user_id'] && $new_status == 0 && $_SESSION['user_role'] == 'super_admin') {
                 $message = "<div class='form-message error'>Super Admins cannot un-verify their own account directly if it affects critical status.</div>";
            } else {
                $stmt = $conn->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("ii", $new_status, $user_id_to_verify);
                    if ($stmt->execute()) {
                        $action_taken = $new_status == 1 ? "Verified user" : "Un-verified user";
                        logAudit($conn, $_SESSION['user_id'], $action_taken, "user", $user_id_to_verify);
                        $message = "<div class='form-message success'>User verification status updated successfully.</div>";
                    } else {
                        $message = "<div class='form-message error'>Failed to update user verification status: " . htmlspecialchars($stmt->error) . "</div>";
                        error_log("Verify user execute failed: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='form-message error'>Database error preparing verification update: " . htmlspecialchars($conn->error) . "</div>";
                    error_log("Verify user prepare failed: " . $conn->error);
                }
            }
        } else {
            $message = "<div class='form-message error'>Invalid user ID provided for verification.</div>";
        }
    }

    // Fetch all users to display their verification status
    $users = getAllUsers($conn); // This function should fetch 'id', 'niftem_id', 'name', 'email', 'role', 'is_verified'

    include '../includes/header.php'; // Use relative path for includes
    ?>

    <main id="page-content" class="admin-area std-profile-container"> <?php // Reusing some profile container styling for consistency ?>
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-user-check"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Review and manage the verification status of user accounts.</p>
            </header>

            <?php if (!empty($message)) echo $message; ?>

            <section class="content-section std-profile-main-content">  <?php // Reusing some profile content styling ?>
                <h2 class="section-title"><i class="fas fa-users"></i> User List & Verification Status</h2>
                <p>Verified users may have access to additional features or public profile visibility. Click "Verify" or "Un-verify" to change a user's status.</p>
                
                <div class="table-responsive-wrapper">
                    <table class="admin-table verify-users-table"> {/* Added specific class for this table */}
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>NIFTEM ID</th>
                                <th>Name & Verification</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Current Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo sanitizeOutput($user['id']); ?></td>
                                        <td><?php echo sanitizeOutput($user['niftem_id']); ?></td>
                                        <td class="user-name-cell">
                                            <div class="user-name-wrapper">
                                                <span><?php echo sanitizeOutput($user['name']); ?></span>
                                                <?php if ($user['is_verified']): ?>
                                                    <img src="<?php echo BASE_URL; ?>assets/images/verified.png" alt="Verified" title="Verified User" class="verified-badge-icon">
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo sanitizeOutput($user['email']); ?></td>
                                        <td><span class="role-badge role-<?php echo sanitizeOutput($user['role']); ?>"><?php echo sanitizeOutput(ucfirst(str_replace('_', ' ', $user['role']))); ?></span></td>
                                        <td>
                                            <?php if ($user['is_verified']): ?>
                                                <span class="status-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                            <?php else: ?>
                                                <span class="status-not-verified"><i class="fas fa-times-circle"></i> Not Verified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="inline-form">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $user['is_verified'] ? 1 : 0; ?>">
                                                <?php if ($user['id'] == $_SESSION['user_id'] && $user['role'] == 'super_admin' && $user['is_verified']): ?>
                                                    <button type="submit" name="verify_user" class="btn btn-small btn-warning" disabled title="Super Admins cannot un-verify themselves here">
                                                        <i class="fas fa-user-shield"></i> Un-verify (Locked)
                                                    </button>
                                                <?php elseif ($user['is_verified']): ?>
                                                    <button type="submit" name="verify_user" class="btn btn-small btn-warning">
                                                        <i class="fas fa-user-times"></i> Un-verify
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="verify_user" class="btn btn-small btn-success">
                                                        <i class="fas fa-user-check"></i> Verify
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    