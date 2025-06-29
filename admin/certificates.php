    <?php
    // geetanjali_website/admin/certificates.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    enforceRoleAccess(['admin', 'super_admin', 'event_manager']);

    $pageTitle = "Manage Certificates";
    $current_admin_id = $_SESSION['user_id'];

    $action = $_GET['action'] ?? 'list';
    $certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $message = $_SESSION['message'] ?? ''; unset($_SESSION['message']);
    $error_message = $_SESSION['error_message'] ?? ''; unset($_SESSION['error_message']);

    // Fetch users for dropdown (can be very long, consider AJAX search for larger sites)
    $users_for_select = [];
    $sql_users = "SELECT id, name, niftem_id FROM users ORDER BY name ASC";
    $res_users = $conn->query($sql_users);
    if ($res_users) { while($row = $res_users->fetch_assoc()) { $users_for_select[] = $row; } }

    // Fetch events for dropdown
    $events_for_select = [];
    $sql_events = "SELECT id, title FROM events ORDER BY date_time DESC";
    $res_events = $conn->query($sql_events);
    if ($res_events) { while($row = $res_events->fetch_assoc()) { $events_for_select[] = $row; } }

    // --- Handle Add/Edit Form Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_certificate'])) {
        $id = isset($_POST['certificate_id']) ? (int)$_POST['certificate_id'] : null;
        $user_id_form = (int)$_POST['user_id'];
        $event_id_form = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
        $certificate_title_form = trim(filter_var($_POST['certificate_title'], FILTER_SANITIZE_STRING));
        $certificate_description_form = trim(filter_var($_POST['certificate_description'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
        $issued_date_form = trim($_POST['issued_date']);
        $certificate_link_form = trim(filter_var($_POST['certificate_link'], FILTER_SANITIZE_URL));
        $awarded_by_form = trim(filter_var($_POST['awarded_by'], FILTER_SANITIZE_STRING));

        $errors = [];
        if (empty($user_id_form)) $errors[] = "Recipient User is required.";
        if (empty($certificate_title_form)) $errors[] = "Certificate Title is required.";
        if (empty($issued_date_form)) $errors[] = "Issued Date is required.";
        elseif (!DateTime::createFromFormat('Y-m-d', $issued_date_form)) $errors[] = "Invalid Issued Date format.";
        if (empty($certificate_link_form)) $errors[] = "Certificate Link (Google Drive) is required.";
        elseif (!filter_var($certificate_link_form, FILTER_VALIDATE_URL)) $errors[] = "Invalid Certificate Link URL format.";
        if (empty($awarded_by_form)) $awarded_by_form = getSetting($conn, 'website_name', 'Geetanjali Literary Society');


        if (empty($errors)) {
            if ($id) { // Update
                $sql = "UPDATE certificates SET user_id=?, event_id=?, certificate_title=?, certificate_description=?, issued_date=?, certificate_link=?, awarded_by=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisssssi", $user_id_form, $event_id_form, $certificate_title_form, $certificate_description_form, $issued_date_form, $certificate_link_form, $awarded_by_form, $id);
            } else { // Insert
                $sql = "INSERT INTO certificates (user_id, event_id, certificate_title, certificate_description, issued_date, certificate_link, awarded_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisssss", $user_id_form, $event_id_form, $certificate_title_form, $certificate_description_form, $issued_date_form, $certificate_link_form, $awarded_by_form);
            }

            if ($stmt && $stmt->execute()) {
                $action_taken = $id ? "Updated certificate" : "Awarded new certificate";
                $new_cert_id = $id ?: $stmt->insert_id;
                $recipient_user = getUserById($conn, $user_id_form); // Fetch user for logging
                logAudit($conn, $current_admin_id, $action_taken, "certificate", $new_cert_id, "Title: " . $certificate_title_form . " for User: " . ($recipient_user['name'] ?? $user_id_form));
                $_SESSION['success_message'] = "Certificate " . ($id ? "updated" : "awarded") . " successfully.";
                header("Location: " . BASE_URL . "admin/certificates.php"); exit;
            } else {
                $error_message = "Error saving certificate: " . ($stmt ? htmlspecialchars($stmt->error) : htmlspecialchars($conn->error));
                error_log("Certificate save error: " . ($stmt ? $stmt->error : $conn->error));
            }
            if($stmt) $stmt->close();
        } else {
            $error_message = "<ul>"; foreach ($errors as $err) { $error_message .= "<li>" . htmlspecialchars($err) . "</li>"; } $error_message .= "</ul>";
        }
    }

    // --- Handle Delete Action ---
    if ($action === 'delete' && $certificate_id > 0) {
        // Add CSRF token check for security
        $cert_to_delete_info = $conn->query("SELECT certificate_title, user_id FROM certificates WHERE id = $certificate_id")->fetch_assoc();
        $stmt_del = $conn->prepare("DELETE FROM certificates WHERE id = ?");
        if ($stmt_del) {
            $stmt_del->bind_param("i", $certificate_id);
            if ($stmt_del->execute()) {
                logAudit($conn, $current_admin_id, "Deleted certificate", "certificate", $certificate_id, "Title: ". ($cert_to_delete_info['certificate_title'] ?? 'N/A') . " for User ID: " . ($cert_to_delete_info['user_id'] ?? 'N/A'));
                $_SESSION['success_message'] = "Certificate deleted successfully.";
            } else { $_SESSION['error_message'] = "Error deleting certificate: " . htmlspecialchars($stmt_del->error); }
            $stmt_del->close();
        } else { $_SESSION['error_message'] = "DB error preparing delete statement."; }
        header("Location: " . BASE_URL . "admin/certificates.php"); exit;
    }

    // --- Data for Add/Edit Form ---
    $cert_data = ['id' => null, 'user_id' => '', 'event_id' => null, 'certificate_title' => '', 'certificate_description' => '', 'issued_date' => date('Y-m-d'), 'certificate_link' => '', 'awarded_by' => getSetting($conn, 'website_name', 'Geetanjali Literary Society')];
    if ($action === 'edit' && $certificate_id > 0) {
        $pageTitle = "Edit Certificate";
        $stmt_edit = $conn->prepare("SELECT * FROM certificates WHERE id = ?");
        if ($stmt_edit) {
            $stmt_edit->bind_param("i", $certificate_id);
            if ($stmt_edit->execute()) {
                $result_edit = $stmt_edit->get_result();
                if ($result_edit->num_rows === 1) {
                    $cert_data = $result_edit->fetch_assoc();
                } else { $_SESSION['error_message'] = "Certificate not found for editing."; header("Location: " . BASE_URL . "admin/certificates.php"); exit; }
            } else { error_log("Cert edit fetch execute error: " . $stmt_edit->error); }
            $stmt_edit->close();
        } else { error_log("Cert edit fetch prepare error: " . $conn->error); }
    } elseif ($action === 'add') {
        $pageTitle = "Award New Certificate";
    }


    // --- Fetch Certificates for Listing ---
    if ($action === 'list') {
        // Filters
        $filter_user_id_list = isset($_GET['user_id_filter']) ? (int)$_GET['user_id_filter'] : null;
        $filter_event_id_list = isset($_GET['event_id_filter']) ? (int)$_GET['event_id_filter'] : null;
        $search_cert_title_list = isset($_GET['search_title']) ? sanitizeOutput(trim($_GET['search_title'])) : '';

        $list_sql_base = "FROM certificates c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN events e ON c.event_id = e.id";
        $list_where_clauses = []; $list_params = []; $list_types = "";

        if ($filter_user_id_list) { $list_where_clauses[] = "c.user_id = ?"; $list_params[] = $filter_user_id_list; $list_types .= "i"; }
        if ($filter_event_id_list) { $list_where_clauses[] = "c.event_id = ?"; $list_params[] = $filter_event_id_list; $list_types .= "i"; }
        if (!empty($search_cert_title_list)) { $list_where_clauses[] = "c.certificate_title LIKE ?"; $list_params[] = "%" . $search_cert_title_list . "%"; $list_types .= "s"; }
        
        $list_sql_where = "";
        if(!empty($list_where_clauses)) { $list_sql_where = " WHERE " . implode(" AND ", $list_where_clauses); }

        // Pagination
        $items_per_page_list = (int)getSetting($conn, 'admin_items_per_page', 15);
        $current_page_list = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($current_page_list < 1) $current_page_list = 1;

        $total_certs_sql = "SELECT COUNT(c.id) as total " . $list_sql_base . $list_sql_where;
        $stmt_total_list = $conn->prepare($total_certs_sql);
        $total_certs_count = 0;
        if($stmt_total_list) {
            if(!empty($list_types)) $stmt_total_list->bind_param($list_types, ...$list_params);
            if($stmt_total_list->execute()){
                $res_total_list = $stmt_total_list->get_result()->fetch_assoc();
                $total_certs_count = $res_total_list ? (int)$res_total_list['total'] : 0;
            } else {error_log("Cert list total execute error: ".$stmt_total_list->error);}
            $stmt_total_list->close();
        }  else {error_log("Cert list total prepare error: ".$conn->error . " SQL: " . $total_certs_sql);}

        $total_pages_list = ($items_per_page_list > 0 && $total_certs_count > 0) ? ceil($total_certs_count / $items_per_page_list) : 1;
        if($current_page_list > $total_pages_list) $current_page_list = $total_pages_list;
        $offset_list = ($current_page_list - 1) * $items_per_page_list;
        if($offset_list < 0) $offset_list = 0;

        $certificates_list = [];
        $sql_list_certs = "SELECT c.id, c.certificate_title, c.issued_date, c.certificate_link, c.awarded_by, 
                                  u.name as user_name, u.niftem_id as user_niftem_id, 
                                  e.title as event_title "
                        . $list_sql_base . $list_sql_where 
                        . " ORDER BY c.issued_date DESC, c.id DESC LIMIT ? OFFSET ?";
        
        $stmt_list_certs = $conn->prepare($sql_list_certs);
        if($stmt_list_certs){
            $list_current_types = $list_types . "ii";
            $list_current_params = array_merge($list_params, [$items_per_page_list, $offset_list]);
            if(empty($list_types)) { if(!$stmt_list_certs->bind_param("ii", $items_per_page_list, $offset_list)) error_log("Bind Error: " . $stmt_list_certs->error); }
            else { if(!$stmt_list_certs->bind_param($list_current_types, ...$list_current_params)) error_log("Bind Error: " . $stmt_list_certs->error); }

            if($stmt_list_certs->execute()){
                $res_list_certs = $stmt_list_certs->get_result();
                while($row = $res_list_certs->fetch_assoc()) { $certificates_list[] = $row; }
            } else { error_log("Cert list fetch execute error: " . $stmt_list_certs->error); }
            $stmt_list_certs->close();
        } else { error_log("Cert list fetch prepare error: " . $conn->error); }
    }

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-award"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <?php if ($action === 'list'): ?>
                    <p class="subtitle">Award, view, and manage student certificates.</p>
                    <p><a href="<?php echo BASE_URL; ?>admin/certificates.php?action=add" class="cta-button btn-success"><i class="fas fa-plus-circle"></i> Award New Certificate</a></p>
                <?php else: ?>
                    <p class="subtitle"><?php echo $action === 'add' ? 'Enter details for the new certificate.' : 'Update certificate information.'; ?></p>
                    <p><a href="<?php echo BASE_URL; ?>admin/certificates.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Certificates List</a></p>
                <?php endif; ?>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message form-message success'>" . $message . "</div>"; ?>
            <?php if (!empty($error_message)): ?>
                <div class="form-message error admin-page-message"><?php echo $error_message; ?></div>
            <?php endif; ?>


            <?php if ($action === 'add' || $action === 'edit'): ?>
            <section class="content-section std-profile-main-content card-style-admin">
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" class="profile-form admin-settings-form" id="certificateForm">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="certificate_id" value="<?php echo $cert_data['id']; ?>">
                    <?php endif; ?>

                    <fieldset>
                        <legend>Certificate Details</legend>
                        <div class="form-group">
                            <label for="certificate_title">Certificate Title <span class="required">*</span></label>
                            <input type="text" id="certificate_title" name="certificate_title" class="form-control form-control-lg" value="<?php echo sanitizeOutput($cert_data['certificate_title'] ?? $_POST['certificate_title'] ?? ''); ?>" required placeholder="e.g., Certificate of Participation, Winner - Debate Competition">
                        </div>
                        <div class="form-group">
                            <label for="user_id">Recipient User <span class="required">*</span></label>
                            <select id="user_id" name="user_id" class="form-control" required>
                                <option value="">-- Select User --</option>
                                <?php foreach($users_for_select as $user_opt): ?>
                                <option value="<?php echo $user_opt['id']; ?>" <?php if (($cert_data['user_id'] ?? $_POST['user_id'] ?? '') == $user_opt['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($user_opt['name'] . ' (' . $user_opt['niftem_id'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="event_id">Related Event (Optional)</label>
                            <select id="event_id" name="event_id" class="form-control">
                                <option value="">-- None --</option>
                                <?php foreach($events_for_select as $event_opt): ?>
                                <option value="<?php echo $event_opt['id']; ?>" <?php if (($cert_data['event_id'] ?? $_POST['event_id'] ?? '') == $event_opt['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($event_opt['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="certificate_description">Description (Optional)</label>
                            <textarea id="certificate_description" name="certificate_description" class="form-control" rows="3" placeholder="Any additional details about the certificate..."><?php echo sanitizeOutput($cert_data['certificate_description'] ?? $_POST['certificate_description'] ?? ''); ?></textarea>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Issuance & Link</legend>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="issued_date">Issued Date <span class="required">*</span></label>
                                <input type="date" id="issued_date" name="issued_date" class="form-control" value="<?php echo sanitizeOutput($cert_data['issued_date'] ?? $_POST['issued_date'] ?? date('Y-m-d')); ?>" required>
                            </div>
                             <div class="form-group col-md-6">
                                <label for="awarded_by">Awarded By</label>
                                <input type="text" id="awarded_by" name="awarded_by" class="form-control" value="<?php echo sanitizeOutput($cert_data['awarded_by'] ?? $_POST['awarded_by'] ?? 'Geetanjali Literary Society'); ?>" placeholder="e.g., Geetanjali Literary Society, NIFTEM-K">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="certificate_link">Certificate Link (Google Drive etc.) <span class="required">*</span></label>
                            <input type="url" id="certificate_link" name="certificate_link" class="form-control" value="<?php echo sanitizeOutput($cert_data['certificate_link'] ?? $_POST['certificate_link'] ?? ''); ?>" required placeholder="https://docs.google.com/document/d/...">
                            <small class="form-text text-muted">Ensure this is a publicly shareable link ("Anyone with the link can view").</small>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" name="save_certificate" class="cta-button btn-lg"><i class="fas fa-save"></i> <?php echo $action === 'edit' ? 'Update Certificate' : 'Award Certificate'; ?></button>
                        <a href="<?php echo BASE_URL; ?>admin/certificates.php" class="cta-button btn-secondary btn-lg"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </section>

            <?php elseif ($action === 'list'): ?>
            <section class="content-section filters-and-search-section card-style-admin">
                <h2 class="section-title-minor"><i class="fas fa-filter"></i> Filter Certificates</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="form-filters-search">
                    <input type="hidden" name="action" value="list">
                    <div class="filter-grid">
                        <div class="form-group filter-item">
                            <label for="search_title_cert_list">Search by Title</label>
                            <input type="text" id="search_title_cert_list" name="search_title" class="form-control" placeholder="Certificate title..." value="<?php echo sanitizeOutput($search_cert_title_list); ?>">
                        </div>
                         <div class="form-group filter-item">
                            <label for="filter_event_id_cert_list">Filter by Event</label>
                            <select id="filter_event_id_cert_list" name="event_id_filter" class="form-control">
                                <option value="">All Events</option>
                                <?php foreach($events_for_select as $event_opt): ?>
                                <option value="<?php echo $event_opt['id']; ?>" <?php if ($filter_event_id_list == $event_opt['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($event_opt['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_user_id_cert_list">Filter by User</label>
                            <select id="filter_user_id_cert_list" name="user_id_filter" class="form-control">
                                <option value="">All Users</option>
                                 <?php foreach($users_for_select as $user_opt): ?>
                                <option value="<?php echo $user_opt['id']; ?>" <?php if ($filter_user_id_list == $user_opt['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($user_opt['name'] . ' (' . $user_opt['niftem_id'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-check-circle"></i> Apply</button>
                        <a href="<?php echo BASE_URL; ?>admin/certificates.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear</a>
                    </div>
                </form>
            </section>

            <section class="content-section std-profile-main-content card-style-admin"> 
                <div class="table-header-controls">
                    <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> Awarded Certificates (<?php echo $total_certs_count; ?> total)</h2>
                     <a href="<?php echo BASE_URL; ?>admin/certificates.php?action=add" class="cta-button btn-success btn-sm"><i class="fas fa-plus-circle"></i> Award New Certificate</a>
                </div>
                <div class="table-responsive-wrapper">
                    <table class="admin-table stylish-table">
                        <thead>
                            <tr>
                                <th>Cert. Title</th>
                                <th>Recipient (NIFTEM ID)</th>
                                <th>Event</th>
                                <th>Issued On</th>
                                <th>Awarded By</th>
                                <th>Link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($certificates_list)): ?>
                                <?php foreach ($certificates_list as $cert_item): ?>
                                    <tr>
                                        <td><strong><?php echo sanitizeOutput($cert_item['certificate_title']); ?></strong></td>
                                        <td><?php echo sanitizeOutput($cert_item['user_name']); ?><br><small class="text-muted">(<?php echo sanitizeOutput($cert_item['user_niftem_id']); ?>)</small></td>
                                        <td><?php echo sanitizeOutput($cert_item['event_title'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($cert_item['issued_date'])); ?></td>
                                        <td><?php echo sanitizeOutput($cert_item['awarded_by']); ?></td>
                                        <td><a href="<?php echo sanitizeOutput($cert_item['certificate_link']); ?>" target="_blank" rel="noopener noreferrer" title="View certificate on Google Drive">View Link <i class="fas fa-external-link-alt fa-xs"></i></a></td>
                                        <td class="actions-cell">
                                            <a href="<?php echo BASE_URL; ?>admin/certificates.php?action=edit&id=<?php echo $cert_item['id']; ?>" class="btn btn-xs btn-info" title="Edit Certificate"><i class="fas fa-edit"></i></a>
                                            <a href="<?php echo BASE_URL; ?>admin/certificates.php?action=delete&id=<?php echo $cert_item['id']; ?>&amp;csrf_token=<?php // echo $_SESSION['csrf_token_val']; ?>" class="btn btn-xs btn-danger" title="Delete Certificate" onclick="return confirm('Are you sure you want to delete this certificate record? This does not delete the file from Google Drive.');"><i class="fas fa-trash-alt"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center no-results-row">No certificates found matching your criteria. <a href="<?php echo BASE_URL; ?>admin/certificates.php?action=add">Award the first one!</a></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php 
                    $pagination_query_params_list = $_GET; unset($pagination_query_params_list['page']); unset($pagination_query_params_list['action']);
                    echo generatePaginationLinks($current_page_list, $total_pages_list, BASE_URL . 'admin/certificates.php', $pagination_query_params_list); 
                ?>
            </section>
            <?php endif; ?>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    