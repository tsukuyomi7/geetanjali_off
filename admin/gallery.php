    <?php
    // geetanjali_website/admin/gallery.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    // Enforce access for relevant roles
    enforceRoleAccess(['admin', 'super_admin', 'event_manager']); // Add 'gallery_manager' if you create such a role

    $pageTitle = "Manage Gallery";
    $current_admin_id = $_SESSION['user_id'];

    $action = $_GET['action'] ?? 'list'; // Default action
    $image_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $message = $_SESSION['message'] ?? ''; unset($_SESSION['message']);
    $error_message = $_SESSION['error_message'] ?? ''; unset($_SESSION['error_message']);

    // Constants for file uploads
    define('GALLERY_UPLOAD_DIR', '../uploads/gallery/');
    define('MAX_FILE_SIZE_GALLERY', 5 * 1024 * 1024); // 5MB
    $allowed_gallery_mime_types = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/gif' => '.gif',
        'image/webp' => '.webp'
    ];

    // Fetch events for dropdown
    $events_for_select = [];
    $sql_events = "SELECT id, title FROM events WHERE date_time >= CURDATE() ORDER BY date_time ASC"; // Only upcoming or recent past
    // For a more comprehensive list: $sql_events = "SELECT id, title FROM events ORDER BY date_time DESC";
    $res_events = $conn->query($sql_events);
    if ($res_events) { while($row = $res_events->fetch_assoc()) { $events_for_select[] = $row; } }


    // --- Handle Add/Edit Form Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_gallery_image']) || isset($_POST['upload_gallery_image']))) {
        $id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : null;
        $caption = trim(filter_var($_POST['caption'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
        $event_id_link = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
        $tags = trim(filter_var($_POST['tags'], FILTER_SANITIZE_STRING));
        $current_file_path = $_POST['current_file_path'] ?? null;
        $file_path_db = $current_file_path; // Keep current if no new upload

        $errors = [];

        // File Upload Logic (only for new images, editing details doesn't require re-upload by default)
        if (isset($_FILES['gallery_image_file']) && $_FILES['gallery_image_file']['error'] == UPLOAD_ERR_OK) {
            if (!is_dir(GALLERY_UPLOAD_DIR)) {
                if (!mkdir(GALLERY_UPLOAD_DIR, 0755, true)) {
                    $errors[] = "Failed to create gallery upload directory.";
                    error_log("Failed to create directory: " . GALLERY_UPLOAD_DIR);
                }
            }
            if (empty($errors) && is_dir(GALLERY_UPLOAD_DIR) && is_writable(GALLERY_UPLOAD_DIR)) {
                $file_name = $_FILES['gallery_image_file']['name'];
                $file_tmp_name = $_FILES['gallery_image_file']['tmp_name'];
                $file_size = $_FILES['gallery_image_file']['size'];
                $file_mime_type = mime_content_type($file_tmp_name);

                if (!array_key_exists($file_mime_type, $allowed_gallery_mime_types)) {
                    $errors[] = "Invalid file type. Allowed: JPG, PNG, GIF, WEBP.";
                } elseif ($file_size > MAX_FILE_SIZE_GALLERY) {
                    $errors[] = "File size exceeds " . (MAX_FILE_SIZE_GALLERY / 1024 / 1024) . "MB limit.";
                } else {
                    $file_extension = $allowed_gallery_mime_types[$file_mime_type];
                    $clean_filename = preg_replace("/[^a-zA-Z0-9_.-]/", "_", pathinfo($file_name, PATHINFO_FILENAME));
                    $new_filename = "gallery_" . time() . "_" . $clean_filename . $file_extension;
                    $target_file = GALLERY_UPLOAD_DIR . $new_filename;

                    if (move_uploaded_file($file_tmp_name, $target_file)) {
                        // If editing and there was an old image, delete it
                        if ($id && $current_file_path && file_exists(GALLERY_UPLOAD_DIR . $current_file_path) && $current_file_path !== $new_filename) {
                            @unlink(GALLERY_UPLOAD_DIR . $current_file_path);
                        }
                        $file_path_db = $new_filename;
                    } else { $errors[] = "Failed to save uploaded image."; }
                }
            } elseif(empty($errors)) { $errors[] = "Upload directory error."; }
        } elseif (isset($_FILES['gallery_image_file']) && $_FILES['gallery_image_file']['error'] != UPLOAD_ERR_NO_FILE) {
            $errors[] = "File upload error: Code " . $_FILES['gallery_image_file']['error'];
        } elseif (!$id && (!isset($_FILES['gallery_image_file']) || $_FILES['gallery_image_file']['error'] == UPLOAD_ERR_NO_FILE)) {
            // If adding new and no file is provided
            $errors[] = "An image file is required when adding a new gallery item.";
        }


        if (empty($errors)) {
            if ($id) { // Update existing gallery item metadata
                $sql = "UPDATE gallery SET caption=?, event_id=?, tags=?, file_path=?, updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                // If event_id_link is null, it should be bound as null. Check type for bind_param.
                // $event_id_to_bind = $event_id_link === null ? null : (int)$event_id_link; (mysqli handles nulls correctly with 'i' type)
                $stmt->bind_param("sisii", $caption, $event_id_link, $tags, $file_path_db, $id);
            } else { // Add new gallery item
                $uploaded_by = $_SESSION['user_id'];
                $sql = "INSERT INTO gallery (file_path, caption, event_id, tags, uploaded_by) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssisi", $file_path_db, $caption, $event_id_link, $tags, $uploaded_by);
            }

            if ($stmt && $stmt->execute()) {
                $action_taken = $id ? "Updated gallery image details" : "Uploaded new gallery image";
                $new_image_id = $id ?: $stmt->insert_id;
                logAudit($conn, $current_admin_id, $action_taken, "gallery_image", $new_image_id, "Caption: " . $caption);
                $_SESSION['success_message'] = "Gallery image " . ($id ? "details updated" : "uploaded") . " successfully.";
                header("Location: " . BASE_URL . "admin/gallery.php"); exit;
            } else {
                $error_message = "Error saving gallery image: " . ($stmt ? htmlspecialchars($stmt->error) : htmlspecialchars($conn->error));
                error_log("Gallery save error: " . ($stmt ? $stmt->error : $conn->error));
            }
            if($stmt) $stmt->close();
        } else { // Display validation errors
            $error_message = "<ul>";
            foreach ($errors as $err) { $error_message .= "<li>" . htmlspecialchars($err) . "</li>"; }
            $error_message .= "</ul>";
        }
    }

    // --- Handle Delete Action ---
    if ($action === 'delete' && $image_id > 0) {
        // Add CSRF token check here for better security
        $stmt_get_img = $conn->prepare("SELECT file_path, caption FROM gallery WHERE id = ?");
        if($stmt_get_img){
            $stmt_get_img->bind_param("i", $image_id);
            $stmt_get_img->execute();
            $result_img = $stmt_get_img->get_result();
            $image_to_delete = $result_img->fetch_assoc();
            $stmt_get_img->close();

            if ($image_to_delete) {
                $stmt_del = $conn->prepare("DELETE FROM gallery WHERE id = ?");
                if($stmt_del){
                    $stmt_del->bind_param("i", $image_id);
                    if ($stmt_del->execute()) {
                        if (!empty($image_to_delete['file_path']) && file_exists(GALLERY_UPLOAD_DIR . $image_to_delete['file_path'])) {
                            @unlink(GALLERY_UPLOAD_DIR . $image_to_delete['file_path']);
                        }
                        logAudit($conn, $current_admin_id, "Deleted gallery image", "gallery_image", $image_id, "Caption: " . $image_to_delete['caption']);
                        $_SESSION['success_message'] = "Image '" . sanitizeOutput($image_to_delete['caption'] ?: $image_to_delete['file_path']) . "' deleted successfully.";
                    } else { $_SESSION['error_message'] = "Error deleting image: " . htmlspecialchars($stmt_del->error); }
                    $stmt_del->close();
                } else { $_SESSION['error_message'] = "DB error preparing delete image statement.";}
            } else { $_SESSION['error_message'] = "Image not found for deletion."; }
        } else { $_SESSION['error_message'] = "DB error fetching image for deletion."; }
        header("Location: " . BASE_URL . "admin/gallery.php"); exit;
    }


    // --- Data for Add/Edit Form ---
    $image_data = ['id' => null, 'file_path' => '', 'caption' => '', 'event_id' => null, 'tags' => ''];
    if ($action === 'edit' && $image_id > 0) {
        $pageTitle = "Edit Gallery Image Details";
        $stmt_edit = $conn->prepare("SELECT id, file_path, caption, event_id, tags FROM gallery WHERE id = ?");
        if($stmt_edit){
            $stmt_edit->bind_param("i", $image_id);
            $stmt_edit->execute();
            $result_edit = $stmt_edit->get_result();
            if ($result_edit->num_rows === 1) {
                $image_data = $result_edit->fetch_assoc();
            } else { $_SESSION['error_message'] = "Image not found for editing."; header("Location: " . BASE_URL . "admin/gallery.php"); exit; }
            $stmt_edit->close();
        } else { error_log("Gallery edit fetch prepare error: ".$conn->error); }
    } elseif ($action === 'upload') { // Changed from 'add' to 'upload' for clarity
        $pageTitle = "Upload New Gallery Image";
    }


    // --- Fetch Images for Listing ---
    if ($action === 'list') {
        $pageTitle = "Manage Gallery";
        // Filtering and Search (Conceptual for now, can be expanded)
        $search_caption_list = isset($_GET['search_caption']) ? sanitizeOutput(trim($_GET['search_caption'])) : '';
        $filter_event_id_list = isset($_GET['event_id']) ? (int)$_GET['event_id'] : '';

        $list_sql_base = "FROM gallery g LEFT JOIN users u ON g.uploaded_by = u.id LEFT JOIN events e ON g.event_id = e.id";
        $list_where_clauses = []; $list_params = []; $list_types = "";

        if (!empty($search_caption_list)) { $list_where_clauses[] = "g.caption LIKE ?"; $list_params[] = "%" . $search_caption_list . "%"; $list_types .= "s"; }
        if (!empty($filter_event_id_list)) { $list_where_clauses[] = "g.event_id = ?"; $list_params[] = $filter_event_id_list; $list_types .= "i"; }
        
        $list_sql_where = "";
        if(!empty($list_where_clauses)) { $list_sql_where = " WHERE " . implode(" AND ", $list_where_clauses); }

        // Pagination
        $items_per_page_list = (int)getSetting($conn, 'admin_items_per_page', 12);
        $current_page_list = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($current_page_list < 1) $current_page_list = 1;

        $total_images_sql = "SELECT COUNT(g.id) as total " . $list_sql_base . $list_sql_where;
        $stmt_total_list = $conn->prepare($total_images_sql);
        $total_gallery_items = 0;
        if($stmt_total_list){
            if(!empty($list_types)) $stmt_total_list->bind_param($list_types, ...$list_params);
            if($stmt_total_list->execute()){
                $res_total_list = $stmt_total_list->get_result();
                $total_items_row = $res_total_list->fetch_assoc();
                $total_gallery_items = $total_items_row ? (int)$total_items_row['total'] : 0;
            } else { error_log("Gallery list total count execute error: " . $stmt_total_list->error); }
            $stmt_total_list->close();
        } else { error_log("Gallery list total count prepare error: " . $conn->error); }

        $total_pages_list = ($items_per_page_list > 0 && $total_gallery_items > 0) ? ceil($total_gallery_items / $items_per_page_list) : 1;
        if($current_page_list > $total_pages_list) $current_page_list = $total_pages_list;
        $offset_list = ($current_page_list - 1) * $items_per_page_list;
        if($offset_list < 0) $offset_list = 0;

        $gallery_items_list = [];
        $sql_list_gallery = "SELECT g.id, g.file_path, g.caption, g.upload_date, g.event_id, e.title as event_title, u.name as uploader_name "
                        . $list_sql_base . $list_sql_where 
                        . " ORDER BY g.upload_date DESC LIMIT ? OFFSET ?";
        
        $stmt_list_gallery = $conn->prepare($sql_list_gallery);
        if($stmt_list_gallery){
            $list_current_types = $list_types . "ii";
            $list_current_params = array_merge($list_params, [$items_per_page_list, $offset_list]);
            if(empty($list_types)) { if(!$stmt_list_gallery->bind_param("ii", $items_per_page_list, $offset_list)) error_log("Bind Error: " . $stmt_list_gallery->error); }
            else { if(!$stmt_list_gallery->bind_param($list_current_types, ...$list_current_params)) error_log("Bind Error: " . $stmt_list_gallery->error); }

            if($stmt_list_gallery->execute()){
                $res_list_gallery = $stmt_list_gallery->get_result();
                while($row = $res_list_gallery->fetch_assoc()) { $gallery_items_list[] = $row; }
            } else { error_log("Gallery list fetch execute error: " . $stmt_list_gallery->error); }
            $stmt_list_gallery->close();
        } else { error_log("Gallery list fetch prepare error: " . $conn->error); }
    }

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-images"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <?php if ($action === 'list'): ?>
                    <p class="subtitle">Upload, edit, and manage all gallery images.</p>
                    <p><a href="<?php echo BASE_URL; ?>admin/gallery.php?action=upload" class="cta-button btn-success"><i class="fas fa-plus-circle"></i> Upload New Image</a></p>
                <?php else: ?>
                    <p class="subtitle"><?php echo $action === 'upload' ? 'Add a new image to the gallery.' : 'Update image details.'; ?></p>
                    <p><a href="<?php echo BASE_URL; ?>admin/gallery.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Gallery List</a></p>
                <?php endif; ?>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message form-message success'>" . $message . "</div>"; ?>
            <?php if (!empty($error_message)): ?>
                <div class="form-message error admin-page-message"><?php echo $error_message; /* Already contains ul/li if multiple */ ?></div>
            <?php endif; ?>


            <?php if ($action === 'upload' || $action === 'edit'): ?>
            <section class="content-section std-profile-main-content card-style-admin">
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" enctype="multipart/form-data" class="profile-form admin-settings-form" id="galleryImageForm">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="image_id" value="<?php echo $image_data['id']; ?>">
                        <input type="hidden" name="current_file_path" value="<?php echo sanitizeOutput($image_data['file_path'] ?? ''); ?>">
                    <?php endif; ?>

                    <fieldset>
                        <legend>Image File & Details</legend>
                        <div class="form-group">
                            <label for="gallery_image_file"><?php echo $action === 'edit' ? 'Replace Image (Optional)' : 'Image File <span class="required">*</span>'; ?></label>
                            <input type="file" id="gallery_image_file" name="gallery_image_file" class="form-control-file" accept="image/jpeg,image/png,image/gif,image/webp" <?php if ($action === 'upload') echo 'required'; ?>>
                            <?php if ($action === 'edit' && !empty($image_data['file_path'])): ?>
                                <div class="mt-2 current-image-admin">
                                    <p>Current Image:</p>
                                    <img src="<?php echo BASE_URL . GALLERY_UPLOAD_DIR . sanitizeOutput($image_data['file_path']); ?>" alt="Current Image" style="max-height: 150px; border-radius: 4px; margin-bottom:10px;">
                                </div>
                            <?php endif; ?>
                            <small class="form-text text-muted">Max file size: <?php echo MAX_FILE_SIZE_GALLERY / 1024 / 1024; ?>MB. Allowed types: JPG, PNG, GIF, WEBP.</small>
                        </div>

                        <div class="form-group">
                            <label for="caption">Caption (Optional)</label>
                            <textarea id="caption" name="caption" class="form-control" rows="3" placeholder="Brief description of the image..."><?php echo sanitizeOutput($image_data['caption'] ?? $_POST['caption'] ?? ''); ?></textarea>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Categorization & Links</legend>
                        <div class="form-group">
                            <label for="event_id">Link to Event (Optional)</label>
                            <select id="event_id" name="event_id" class="form-control">
                                <option value="">-- No Specific Event --</option>
                                <?php foreach($events_for_select as $event_opt): ?>
                                <option value="<?php echo $event_opt['id']; ?>" <?php if (($image_data['event_id'] ?? $_POST['event_id'] ?? '') == $event_opt['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($event_opt['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tags">Tags (comma-separated, Optional)</label>
                            <input type="text" id="tags" name="tags" class="form-control" value="<?php echo sanitizeOutput($image_data['tags'] ?? $_POST['tags'] ?? ''); ?>" placeholder="e.g., annual fest, poetry, competition">
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" name="<?php echo $action === 'edit' ? 'save_gallery_image' : 'upload_gallery_image'; ?>" class="cta-button btn-lg">
                            <i class="fas <?php echo $action === 'edit' ? 'fa-save' : 'fa-upload'; ?>"></i> <?php echo $action === 'edit' ? 'Update Details' : 'Upload Image'; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>admin/gallery.php" class="cta-button btn-secondary btn-lg"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </section>

            <?php elseif ($action === 'list'): ?>
            <section class="content-section filters-and-search-section card-style-admin">
                <h2 class="section-title-minor"><i class="fas fa-filter"></i> Filter & Search Gallery</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="form-filters-search">
                    <input type="hidden" name="action" value="list">
                    <div class="filter-grid">
                        <div class="form-group filter-item">
                            <label for="search_caption_list">Search by Caption</label>
                            <div class="input-group-admin">
                                <span class="input-group-icon"><i class="fas fa-search"></i></span>
                                <input type="text" id="search_caption_list" name="search_caption" class="form-control" placeholder="Enter caption keywords..." value="<?php echo sanitizeOutput($search_caption_list); ?>">
                            </div>
                        </div>
                         <div class="form-group filter-item">
                            <label for="filter_event_id_list">Filter by Event</label>
                            <select id="filter_event_id_list" name="event_id" class="form-control">
                                <option value="">All Events</option>
                                <?php foreach($events_for_select as $event_opt): ?>
                                <option value="<?php echo $event_opt['id']; ?>" <?php if ($filter_event_id_list == $event_opt['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($event_opt['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php /* Conceptual: Filter by tags would need more advanced UI or text input */ ?>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-check-circle"></i> Apply</button>
                        <a href="<?php echo BASE_URL; ?>admin/gallery.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear</a>
                    </div>
                </form>
            </section>

            <section class="content-section std-profile-main-content card-style-admin"> 
                <div class="table-header-controls">
                    <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> Gallery Images (<?php echo $total_gallery_items; ?> total)</h2>
                     <a href="<?php echo BASE_URL; ?>admin/gallery.php?action=upload" class="cta-button btn-success btn-sm"><i class="fas fa-plus-circle"></i> Upload New Image</a>
                </div>
                <div class="table-responsive-wrapper">
                    <table class="admin-table stylish-table">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Caption</th>
                                <th>Event Linked</th>
                                <th>Tags</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($gallery_items_list)): ?>
                                <?php foreach ($gallery_items_list as $item): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo BASE_URL . GALLERY_UPLOAD_DIR . sanitizeOutput($item['file_path']); ?>" data-lightbox="admin-gallery" data-title="<?php echo sanitizeOutput($item['caption'] ?? ''); ?>">
                                                <img src="<?php echo BASE_URL . GALLERY_UPLOAD_DIR . sanitizeOutput($item['file_path']); ?>" alt="<?php echo sanitizeOutput($item['caption'] ?? 'Gallery Image'); ?>" class="admin-table-thumbnail">
                                            </a>
                                        </td>
                                        <td><?php echo sanitizeOutput($item['caption'] ? substr($item['caption'],0,50).'...' : 'N/A'); ?></td>
                                        <td><?php echo sanitizeOutput($item['event_title'] ?? 'N/A'); ?></td>
                                        <td><small><?php echo sanitizeOutput($item['tags'] ? substr($item['tags'],0,40).'...' : 'N/A'); ?></small></td>
                                        <td><?php echo sanitizeOutput($item['uploader_name'] ?? 'N/A'); ?></td>
                                        <td title="<?php echo date('M j, Y H:i', strtotime($item['upload_date'])); ?>"><?php echo time_elapsed_string($item['upload_date']); ?></td>
                                        <td class="actions-cell">
                                            <a href="<?php echo BASE_URL; ?>admin/gallery.php?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-xs btn-info" title="Edit Details"><i class="fas fa-edit"></i></a>
                                            <a href="<?php echo BASE_URL; ?>admin/gallery.php?action=delete&id=<?php echo $item['id']; ?>&amp;csrf_token=<?php // echo $_SESSION['csrf_token_val']; ?>" class="btn btn-xs btn-danger" title="Delete Image" onclick="return confirm('Are you sure you want to delete this image and its file PERMANENTLY?');"><i class="fas fa-trash-alt"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center no-results-row">No images found in the gallery. <a href="<?php echo BASE_URL; ?>admin/gallery.php?action=upload">Upload the first one!</a></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php 
                    $pagination_query_params_list = $_GET; unset($pagination_query_params_list['page']); unset($pagination_query_params_list['action']); // remove action for list view pagination
                    echo generatePaginationLinks($current_page_list, $total_pages_list, BASE_URL . 'admin/gallery.php', $pagination_query_params_list); 
                ?>
            </section>
            <?php endif; // End if action === 'list' ?>
        </div>
    </main>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const imageFileInput = document.getElementById('gallery_image_file');
        const imagePreview = document.querySelector('.current-image-admin img'); // More specific selector
        const removeImageCheckbox = document.querySelector('input[name="remove_featured_image"]'); // For event image

        if (imageFileInput) {
            imageFileInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let previewElement = imagePreview;
                        if (!previewElement) { // If adding new, create a preview element
                            previewElement = document.createElement('img');
                            previewElement.style.maxHeight = '150px';
                            previewElement.style.borderRadius = '4px';
                            previewElement.style.marginBottom = '10px';
                            // Insert it after the file input or in a designated preview area
                            const parentFormGroup = imageFileInput.closest('.form-group');
                            const existingPreviewDiv = parentFormGroup.querySelector('.mt-2.current-image-admin');
                            if (existingPreviewDiv) {
                                existingPreviewDiv.innerHTML = ''; // Clear old preview if any
                                existingPreviewDiv.appendChild(previewElement);
                            } else { // Fallback append if structure is different
                                imageFileInput.insertAdjacentElement('afterend', previewElement);
                            }
                        }
                        previewElement.src = e.target.result;
                        previewElement.style.display = 'block';
                        if(removeImageCheckbox) removeImageCheckbox.checked = false; 
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
         if (removeImageCheckbox && imagePreview) { // Specifically for events featured image
            removeImageCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    imagePreview.style.opacity = '0.5'; 
                } else {
                    imagePreview.style.opacity = '1';
                }
            });
        }
    });
    </script>
    <?php include '../includes/footer.php'; ?>
    