    <?php
    // geetanjali_website/admin/events.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    // Enforce access for relevant roles
    enforceRoleAccess(['admin', 'super_admin', 'event_manager']);

    $pageTitle = "Manage Events";
    $current_admin_id = $_SESSION['user_id'];

    $action = $_GET['action'] ?? 'list'; // Default action
    $event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $message = $_SESSION['message'] ?? ''; unset($_SESSION['message']);
    $error_message = $_SESSION['error_message'] ?? ''; unset($_SESSION['error_message']);

    // Define event types (mirroring ENUM in DB or a categories table)
    $event_type_options = ['workshop', 'competition', 'guest_lecture', 'celebration', 'meeting', 'webinar', 'fest', 'other'];
    $event_statuses = ['draft', 'published']; // Simplified for this example

    // --- Handle Add/Edit Form Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
        $id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
        $title = trim(filter_var($_POST['title'], FILTER_SANITIZE_STRING));
        $description = trim($_POST['description']); // Sanitize based on editor or use htmlspecialchars for plain text
        $date_time_str = trim($_POST['date_time']);
        $venue = trim(filter_var($_POST['venue'], FILTER_SANITIZE_STRING));
        $event_type = isset($_POST['event_type']) && in_array($_POST['event_type'], $event_type_options) ? $_POST['event_type'] : 'other';
        $tags = trim(filter_var($_POST['tags'], FILTER_SANITIZE_STRING));
        $registration_deadline_str = trim($_POST['registration_deadline']);
        $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        
        $current_featured_image = $_POST['current_featured_image'] ?? null;
        $featured_image_path = $current_featured_image; // Keep current if no new upload or removal

        $errors = [];
        if (empty($title)) $errors[] = "Event title is required.";
        if (empty($date_time_str)) $errors[] = "Event date and time are required.";
        else {
            $date_time_obj = DateTime::createFromFormat('Y-m-d\TH:i', $date_time_str);
            if (!$date_time_obj) $errors[] = "Invalid event date/time format.";
            else $date_time = $date_time_obj->format('Y-m-d H:i:s');
        }
        if (!empty($registration_deadline_str)) {
            $reg_deadline_obj = DateTime::createFromFormat('Y-m-d\TH:i', $registration_deadline_str);
            if (!$reg_deadline_obj) $errors[] = "Invalid registration deadline format.";
            else $registration_deadline = $reg_deadline_obj->format('Y-m-d H:i:s');
        } else { $registration_deadline = null; }
        
        if ($capacity !== null && $capacity < 0) $errors[] = "Capacity cannot be negative.";


        // Handle Featured Image Upload
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir_events = '../uploads/events/';
            if (!is_dir($upload_dir_events)) mkdir($upload_dir_events, 0755, true);
            // Basic file validation (add more as needed: size, type)
            $img_name_new = time() . '_' . basename($_FILES['featured_image']['name']);
            $target_file_new = $upload_dir_events . $img_name_new;
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file_new)) {
                if($id && $current_featured_image && file_exists($upload_dir_events . $current_featured_image) && $current_featured_image !== $img_name_new) {
                    @unlink($upload_dir_events . $current_featured_image);
                }
                $featured_image_path = $img_name_new;
            } else { $errors[] = "Failed to upload new featured image."; }
        } elseif (isset($_POST['remove_featured_image']) && $id && $current_featured_image) {
            if(file_exists('../uploads/events/' . $current_featured_image)) {
                @unlink('../uploads/events/' . $current_featured_image);
            }
            $featured_image_path = null;
        }

        if (empty($errors)) {
            if ($id) { // Update existing event
                $sql = "UPDATE events SET title=?, description=?, date_time=?, venue=?, event_type=?, tags=?, registration_deadline=?, capacity=?, is_published=?, featured_image=?, updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssiisi", $title, $description, $date_time, $venue, $event_type, $tags, $registration_deadline, $capacity, $is_published, $featured_image_path, $id);
            } else { // Add new event
                $sql = "INSERT INTO events (title, description, date_time, venue, event_type, tags, registration_deadline, capacity, is_published, featured_image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssiisi", $title, $description, $date_time, $venue, $event_type, $tags, $registration_deadline, $capacity, $is_published, $featured_image_path, $current_admin_id);
            }

            if ($stmt && $stmt->execute()) {
                $action_taken = $id ? "Updated event" : "Created new event";
                $new_event_id = $id ?: $stmt->insert_id;
                logAudit($conn, $current_admin_id, $action_taken, "event", $new_event_id, "Title: " . $title);
                $_SESSION['success_message'] = "Event " . ($id ? "updated" : "created") . " successfully.";
                header("Location: " . BASE_URL . "admin/events.php"); exit;
            } else {
                $error_message = "Error saving event: " . ($stmt ? htmlspecialchars($stmt->error) : htmlspecialchars($conn->error));
                error_log("Event save error: " . ($stmt ? $stmt->error : $conn->error) . " SQL: " . $sql);
            }
            if($stmt) $stmt->close();
        } else { // Display validation errors
            $error_message = "<ul>";
            foreach ($errors as $err) { $error_message .= "<li>" . htmlspecialchars($err) . "</li>"; }
            $error_message .= "</ul>";
        }
    }

    // --- Handle Delete Action ---
    if ($action === 'delete' && $event_id > 0) {
        // Add CSRF token check for GET-based delete
        $event_to_delete = $conn->query("SELECT title, featured_image FROM events WHERE id = $event_id")->fetch_assoc();
        if ($event_to_delete) {
            $stmt_del = $conn->prepare("DELETE FROM events WHERE id = ?");
            if($stmt_del){
                $stmt_del->bind_param("i", $event_id);
                if ($stmt_del->execute()) {
                    if (!empty($event_to_delete['featured_image']) && file_exists('../uploads/events/' . $event_to_delete['featured_image'])) {
                        @unlink('../uploads/events/' . $event_to_delete['featured_image']);
                    }
                    logAudit($conn, $current_admin_id, "Deleted event", "event", $event_id, "Title: " . $event_to_delete['title']);
                    $_SESSION['success_message'] = "Event '" . sanitizeOutput($event_to_delete['title']) . "' deleted.";
                } else { $_SESSION['error_message'] = "Error deleting event: " . htmlspecialchars($stmt_del->error); }
                $stmt_del->close();
            } else { $_SESSION['error_message'] = "DB error preparing delete."; }
        } else { $_SESSION['error_message'] = "Event not found for deletion.";}
        header("Location: " . BASE_URL . "admin/events.php"); exit;
    }

    // --- Data for Add/Edit Form ---
    $event_data = ['id' => null, 'title' => '', 'description' => '', 'date_time' => date('Y-m-d\TH:i'), 'venue' => '', 'event_type' => 'other', 'tags' => '', 'registration_deadline' => '', 'capacity' => null, 'is_published' => 1, 'featured_image' => null];
    if ($action === 'edit' && $event_id > 0) {
        $pageTitle = "Edit Event";
        $stmt_edit = $conn->prepare("SELECT * FROM events WHERE id = ?");
        if($stmt_edit){
            $stmt_edit->bind_param("i", $event_id);
            if($stmt_edit->execute()){
                $result_edit = $stmt_edit->get_result();
                if ($result_edit->num_rows === 1) {
                    $event_data = $result_edit->fetch_assoc();
                    // Format datetime for datetime-local input
                    if (!empty($event_data['date_time'])) {
                        $event_data['date_time'] = date('Y-m-d\TH:i', strtotime($event_data['date_time']));
                    }
                    if (!empty($event_data['registration_deadline'])) {
                        $event_data['registration_deadline'] = date('Y-m-d\TH:i', strtotime($event_data['registration_deadline']));
                    }
                } else { $_SESSION['error_message'] = "Event not found for editing."; header("Location: " . BASE_URL . "admin/events.php"); exit; }
            } else { error_log("Event edit fetch execute error: " . $stmt_edit->error); }
            $stmt_edit->close();
        } else { error_log("Event edit fetch prepare error: ".$conn->error); }
    } elseif ($action === 'add') {
        $pageTitle = "Add New Event";
    }


    // --- Fetch Events for Listing ---
    if ($action === 'list') {
        $pageTitle = "Manage Events";
        $filter_status_list = isset($_GET['status']) ? (int)$_GET['status'] : null; // 0 for draft, 1 for published
        $filter_type_list = isset($_GET['type']) && in_array($_GET['type'], $event_type_options) ? $_GET['type'] : '';
        $search_title_list = isset($_GET['search_title']) ? sanitizeOutput(trim($_GET['search_title'])) : '';

        $list_sql_base = "FROM events e LEFT JOIN users u ON e.created_by = u.id";
        $list_where_clauses = []; $list_params = []; $list_types = "";

        if ($filter_status_list !== null) { $list_where_clauses[] = "e.is_published = ?"; $list_params[] = $filter_status_list; $list_types .= "i"; }
        if (!empty($filter_type_list)) { $list_where_clauses[] = "e.event_type = ?"; $list_params[] = $filter_type_list; $list_types .= "s"; }
        if (!empty($search_title_list)) { $list_where_clauses[] = "e.title LIKE ?"; $list_params[] = "%" . $search_title_list . "%"; $list_types .= "s"; }
        
        $list_sql_where = "";
        if(!empty($list_where_clauses)) { $list_sql_where = " WHERE " . implode(" AND ", $list_where_clauses); }

        // Pagination
        $items_per_page_list = (int)getSetting($conn, 'admin_items_per_page', 10);
        $current_page_list = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($current_page_list < 1) $current_page_list = 1;

        $total_events_sql = "SELECT COUNT(e.id) as total " . $list_sql_base . $list_sql_where;
        $stmt_total_list = $conn->prepare($total_events_sql);
        $total_events_count = 0;
        if ($stmt_total_list) { /* ... (bind, execute, fetch total) ... */ 
            if(!empty($list_types)) $stmt_total_list->bind_param($list_types, ...$list_params);
            if($stmt_total_list->execute()){
                $total_events_row = $stmt_total_list->get_result()->fetch_assoc();
                $total_events_count = $total_events_row ? (int)$total_events_row['total'] : 0;
            }
            $stmt_total_list->close();
        }

        $total_pages_list = ($items_per_page_list > 0 && $total_events_count > 0) ? ceil($total_events_count / $items_per_page_list) : 1;
        if($current_page_list > $total_pages_list) $current_page_list = $total_pages_list;
        $offset_list = ($current_page_list - 1) * $items_per_page_list;
        if($offset_list < 0) $offset_list = 0;

        $events_list = [];
        $sql_list_events = "SELECT e.id, e.title, e.date_time, e.venue, e.event_type, e.is_published, e.capacity, 
                                  (SELECT COUNT(er.id) FROM event_registrations er WHERE er.event_id = e.id) as registered_count,
                                  u.name as creator_name "
                        . $list_sql_base . $list_sql_where 
                        . " ORDER BY e.date_time DESC LIMIT ? OFFSET ?";
        
        $stmt_list_events = $conn->prepare($sql_list_events);
        if($stmt_list_events){
            $list_current_types = $list_types . "ii";
            $list_current_params = array_merge($list_params, [$items_per_page_list, $offset_list]);
            if(empty($list_types)) { if(!$stmt_list_events->bind_param("ii", $items_per_page_list, $offset_list)) error_log("Bind Error: " . $stmt_list_events->error); }
            else { if(!$stmt_list_events->bind_param($list_current_types, ...$list_current_params)) error_log("Bind Error: " . $stmt_list_events->error); }

            if($stmt_list_events->execute()){
                $res_list_events = $stmt_list_events->get_result();
                while($row = $res_list_events->fetch_assoc()) { $events_list[] = $row; }
            } else { error_log("Event list fetch execute error: " . $stmt_list_events->error); }
            $stmt_list_events->close();
        } else { error_log("Event list fetch prepare error: " . $conn->error); }
    }

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-calendar-edit"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <?php if ($action === 'list'): ?>
                    <p class="subtitle">Create, edit, and manage all Geetanjali events.</p>
                    <p><a href="<?php echo BASE_URL; ?>admin/events.php?action=add" class="cta-button btn-success"><i class="fas fa-plus-circle"></i> Add New Event</a></p>
                <?php else: ?>
                    <p class="subtitle"><?php echo $action === 'add' ? 'Define a new event for the society.' : 'Modify details for this event.'; ?></p>
                    <p><a href="<?php echo BASE_URL; ?>admin/events.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Events List</a></p>
                <?php endif; ?>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message form-message success'>" . $message . "</div>"; ?>
            <?php if (!empty($error_message)): ?>
                <div class="form-message error admin-page-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
            <section class="content-section std-profile-main-content card-style-admin">
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" enctype="multipart/form-data" class="profile-form admin-settings-form" id="eventPostForm">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="event_id" value="<?php echo $event_data['id']; ?>">
                        <input type="hidden" name="current_featured_image" value="<?php echo sanitizeOutput($event_data['featured_image'] ?? ''); ?>">
                    <?php endif; ?>

                    <fieldset>
                        <legend>Event Core Details</legend>
                        <div class="form-group">
                            <label for="title">Event Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-control form-control-lg" value="<?php echo sanitizeOutput($event_data['title'] ?? $_POST['title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description <span class="required">*</span></label>
                            <textarea id="description" name="description" class="form-control" rows="10" placeholder="Detailed information about the event..."><?php echo htmlspecialchars($event_data['description'] ?? $_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <small class="form-text text-muted">HTML is allowed. Consider using a Rich Text Editor for a better experience.</small>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Date, Time & Location</legend>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="date_time">Event Date & Time <span class="required">*</span></label>
                                <input type="datetime-local" id="date_time" name="date_time" class="form-control" value="<?php echo sanitizeOutput($event_data['date_time'] ?? $_POST['date_time'] ?? date('Y-m-d\TH:i')); ?>" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="venue">Venue</label>
                                <input type="text" id="venue" name="venue" class="form-control" value="<?php echo sanitizeOutput($event_data['venue'] ?? $_POST['venue'] ?? ''); ?>" placeholder="e.g., NIFTEM Auditorium, Online via Zoom">
                            </div>
                        </div>
                    </fieldset>

                     <fieldset>
                        <legend>Categorization & Display</legend>
                         <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="event_type">Event Type <span class="required">*</span></label>
                                <select id="event_type" name="event_type" class="form-control">
                                    <?php foreach($event_type_options as $type_opt): ?>
                                    <option value="<?php echo $type_opt; ?>" <?php if (($event_data['event_type'] ?? $_POST['event_type'] ?? 'other') == $type_opt) echo 'selected'; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $type_opt)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="tags">Tags (comma-separated)</label>
                                <input type="text" id="tags" name="tags" class="form-control" value="<?php echo sanitizeOutput($event_data['tags'] ?? $_POST['tags'] ?? ''); ?>" placeholder="e.g., poetry, competition, guest speaker">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="featured_image">Featured Image</label>
                            <input type="file" id="featured_image" name="featured_image" class="form-control-file" accept="image/jpeg,image/png,image/gif">
                            <?php if (!empty($event_data['featured_image'])): ?>
                                <div class="mt-2 current-image-admin">
                                    <img src="<?php echo BASE_URL . 'uploads/events/' . sanitizeOutput($event_data['featured_image']); ?>" alt="Current Featured Image">
                                    <label class="ml-2"><input type="checkbox" name="remove_featured_image" value="1"> Remove current image</label>
                                </div>
                            <?php endif; ?>
                            <small class="form-text text-muted">Recommended: Landscape, ~1200x628px. Max 2MB.</small>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Registration & Publishing</legend>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="registration_deadline">Registration Deadline (Optional)</label>
                                <input type="datetime-local" id="registration_deadline" name="registration_deadline" class="form-control" value="<?php echo sanitizeOutput($event_data['registration_deadline'] ?? $_POST['registration_deadline'] ?? ''); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="capacity">Capacity (Optional)</label>
                                <input type="number" id="capacity" name="capacity" class="form-control" value="<?php echo sanitizeOutput($event_data['capacity'] ?? $_POST['capacity'] ?? ''); ?>" min="0" placeholder="Leave blank for unlimited">
                            </div>
                        </div>
                        <div class="form-group form-check-toggle">
                             <label for="is_published">Publish Event?</label>
                             <label class="switch">
                                <input type="checkbox" id="is_published" name="is_published" value="1" <?php echo (($event_data['is_published'] ?? $_POST['is_published'] ?? 1) == 1) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                            <small class="form-text text-muted">Uncheck to save as a draft.</small>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" name="save_event" class="cta-button btn-lg"><i class="fas fa-save"></i> <?php echo $action === 'edit' ? 'Update Event' : 'Create Event'; ?></button>
                        <a href="<?php echo BASE_URL; ?>admin/events.php" class="cta-button btn-secondary btn-lg"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </section>

            <?php elseif ($action === 'list'): ?>
            <section class="content-section filters-and-search-section card-style-admin">
                <h2 class="section-title-minor"><i class="fas fa-filter"></i> Filter & Search Events</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="form-filters-search">
                    <input type="hidden" name="action" value="list">
                    <div class="filter-grid">
                        <div class="form-group filter-item">
                            <label for="search_title_list">Search by Title</label>
                            <div class="input-group-admin">
                                <span class="input-group-icon"><i class="fas fa-search"></i></span>
                                <input type="text" id="search_title_list" name="search_title" class="form-control" placeholder="Enter title keywords..." value="<?php echo sanitizeOutput($search_title_list ?? ''); ?>">
                            </div>
                        </div>
                         <div class="form-group filter-item">
                            <label for="filter_type_list">Filter by Type</label>
                            <select id="filter_type_list" name="type" class="form-control">
                                <option value="">All Types</option>
                                <?php foreach($event_type_options as $type_opt): ?>
                                <option value="<?php echo $type_opt; ?>" <?php if (($filter_type_list ?? '') == $type_opt) echo 'selected'; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type_opt)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_status_list">Filter by Status</label>
                            <select id="filter_status_list" name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="1" <?php if (($filter_status_list ?? null) === 1) echo 'selected'; ?>>Published</option>
                                <option value="0" <?php if (($filter_status_list ?? null) === 0 && $filter_status_list !== null) echo 'selected'; ?>>Draft</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-check-circle"></i> Apply</button>
                        <a href="<?php echo BASE_URL; ?>admin/events.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear</a>
                    </div>
                </form>
            </section>

            <section class="content-section std-profile-main-content card-style-admin"> 
                <div class="table-header-controls">
                    <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> Events List (<?php echo $total_events_count; ?> total)</h2>
                     <a href="<?php echo BASE_URL; ?>admin/events.php?action=add" class="cta-button btn-success btn-sm"><i class="fas fa-plus-circle"></i> Add New Event</a>
                </div>
                <div class="table-responsive-wrapper">
                    <table class="admin-table stylish-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Capacity</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($events_list)): ?>
                                <?php foreach ($events_list as $event_item): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo BASE_URL . 'events/view.php?id=' . $event_item['id']; ?>" target="_blank" title="View Event: <?php echo sanitizeOutput($event_item['title']); ?>">
                                                <strong><?php echo sanitizeOutput($event_item['title']); ?></strong>
                                            </a>
                                        </td>
                                        <td><?php echo date('M j, Y - g:i A', strtotime($event_item['date_time'])); ?><br><small class="text-muted"><?php echo time_elapsed_string($event_item['date_time']); ?></small></td>
                                        <td><?php echo sanitizeOutput($event_item['venue'] ?: 'N/A'); ?></td>
                                        <td><span class="badge type-<?php echo sanitizeOutput($event_item['event_type']); ?>"><?php echo sanitizeOutput(ucfirst(str_replace('_', ' ', $event_item['event_type']))); ?></span></td>
                                        <td>
                                            <?php if ($event_item['is_published']): ?>
                                                <span class="status-badge status-published">Published</span>
                                            <?php else: ?>
                                                <span class="status-badge status-draft">Draft</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $event_item['capacity'] ? sanitizeOutput($event_item['capacity']) : 'Open'; ?></td>
                                        <td><?php echo sanitizeOutput($event_item['registered_count'] ?? 0); ?></td>
                                        <td class="actions-cell">
                                            <a href="<?php echo BASE_URL; ?>admin/events.php?action=edit&id=<?php echo $event_item['id']; ?>" class="btn btn-xs btn-info" title="Edit Event"><i class="fas fa-edit"></i></a>
                                            <a href="<?php echo BASE_URL; ?>admin/events.php?action=delete&id=<?php echo $event_item['id']; ?>&amp;csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; /* Add CSRF for GET delete */?>" class="btn btn-xs btn-danger" title="Delete Event" onclick="return confirm('Are you sure you want to delete this event PERMANENTLY? This will also remove all associated registrations.');"><i class="fas fa-trash-alt"></i></a>
                                            <a href="<?php echo BASE_URL . 'events/view.php?id=' . $event_item['id']; ?>" target="_blank" class="btn btn-xs btn-secondary" title="View Event"><i class="fas fa-eye"></i></a>
                                            <a href="<?php echo BASE_URL; ?>admin/event_registrations.php?event_id=<?php echo $event_item['id']; ?>" class="btn btn-xs btn-primary-outline" title="View Registrations"><i class="fas fa-users"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center no-results-row">No events found matching your criteria. <a href="<?php echo BASE_URL; ?>admin/events.php">Clear filters</a>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <?php 
                    $pagination_query_params_list = $_GET; unset($pagination_query_params_list['page']);
                    echo generatePaginationLinks($current_page_list, $total_pages_list, BASE_URL . 'admin/events.php', $pagination_query_params_list); 
                ?>
            </section>
            <?php endif; // End if action === 'list' ?>
        </div>
    </main>
    <script>
    // JavaScript for slug generation, featured image preview, and potentially Rich Text Editor init
    document.addEventListener('DOMContentLoaded', function() {
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');

        if (titleInput && slugInput) {
            titleInput.addEventListener('input', function() { // 'input' is better than 'keyup' for this
                if(document.getElementById('eventPostForm').dataset.action === 'add' || slugInput.value === '') { // Only auto-slug for new posts or if slug is empty
                    slugInput.value = generatePageSlug(this.value);
                }
            });
            slugInput.addEventListener('input', function() { // Allow manual override
                this.value = generatePageSlug(this.value);
            });
        }

        function generatePageSlug(text) {
            if (!text) return '';
            return text.toString().toLowerCase().trim()
                .replace(/\s+/g, '-')           // Replace spaces with -
                .replace(/[^\w-]+/g, '')       // Remove all non-word chars but keep hyphens
                .replace(/--+/g, '-')           // Replace multiple - with single -
                .substring(0, 200);             // Max length for slug
        }

        const featuredImageInput = document.getElementById('featured_image');
        const currentImageAdmin = document.querySelector('.current-image-admin img');
        const removeImageCheckbox = document.querySelector('input[name="remove_featured_image"]');

        if (featuredImageInput) {
            featuredImageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file && currentImageAdmin) { // Check if preview element exists
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        currentImageAdmin.src = e.target.result;
                        currentImageAdmin.style.display = 'block';
                        if(removeImageCheckbox) removeImageCheckbox.checked = false; 
                    }
                    reader.readAsDataURL(file);
                } else if (file && !currentImageAdmin) { // No current image, create one for preview
                    const newPreviewContainer = featuredImageInput.closest('.form-group').querySelector('.mt-2');
                    if (newPreviewContainer) { // This structure might need adjustment
                        const newImg = document.createElement('img');
                        newImg.style.maxHeight = '100px';
                        newImg.style.borderRadius = '4px';
                        newImg.alt = 'New image preview';
                        const reader = new FileReader();
                        reader.onload = function(e) { newImg.src = e.target.result; }
                        reader.readAsDataURL(file);
                        // Clear previous content and add new image
                        while(newPreviewContainer.firstChild) newPreviewContainer.removeChild(newPreviewContainer.firstChild);
                        newPreviewContainer.appendChild(newImg);
                         if(removeImageCheckbox) removeImageCheckbox.checked = false; 
                    }
                }
            });
        }
        if (removeImageCheckbox && currentImageAdmin) {
            removeImageCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    currentImageAdmin.style.opacity = '0.5'; 
                } else {
                    currentImageAdmin.style.opacity = '1';
                }
            });
        }

        // Conceptual: Initialize Rich Text Editor (e.g., TinyMCE)
        // if (typeof tinymce !== 'undefined' && document.getElementById('content')) {
        //     tinymce.init({
        //         selector: 'textarea#content',
        //         plugins: 'lists link image table code help wordcount emoticons',
        //         toolbar: 'undo redo | styleselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | code emoticons | help'
        //     });
        // }
    });
    </script>
    <?php include '../includes/footer.php'; ?>
    