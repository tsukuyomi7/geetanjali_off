    <?php
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    enforceRoleAccess(['admin', 'super_admin']);

    $action = $_GET['action'] ?? 'list';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    // Form submission logic
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF check would go here
        
        $title = sanitizeOutput($_POST['title']);
        $content = $_POST['content']; // Sanitize later if allowing HTML
        $excerpt = sanitizeOutput($_POST['excerpt']);
        $link = filter_var($_POST['link'], FILTER_SANITIZE_URL);
        $link_text = sanitizeOutput($_POST['link_text']);
        $priority = (int)$_POST['priority'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $target_audience = sanitizeOutput($_POST['target_audience']);
        $start_date = !empty($_POST['start_date']) ? sanitizeOutput($_POST['start_date']) : NULL;
        $end_date = !empty($_POST['end_date']) ? sanitizeOutput($_POST['end_date']) : NULL;
        $created_by = $_SESSION['user_id'];

        if (isset($_POST['add'])) {
            $sql = "INSERT INTO announcements (title, content, excerpt, link, link_text, created_by, start_date, end_date, is_active, priority, target_audience) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssisiiis", $title, $content, $excerpt, $link, $link_text, $created_by, $start_date, $end_date, $is_active, $priority, $target_audience);
        } elseif (isset($_POST['update']) && $id > 0) {
            $sql = "UPDATE announcements SET title=?, content=?, excerpt=?, link=?, link_text=?, start_date=?, end_date=?, is_active=?, priority=?, target_audience=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssiiisi", $title, $content, $excerpt, $link, $link_text, $start_date, $end_date, $is_active, $priority, $target_audience, $id);
        }

        if (isset($stmt) && $stmt->execute()) {
            $_SESSION['success_message'] = "Announcement " . ($id ? "updated" : "added") . " successfully.";
        } else {
            $_SESSION['error_message'] = "Error: Could not save announcement. " . ($stmt->error ?? '');
        }
        header("Location: " . BASE_URL . "admin/manage_announcements.php");
        exit;
    }
    
    // Delete action
    if ($action === 'delete' && $id > 0) {
        // Add CSRF check here for security
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['success_message'] = "Announcement deleted successfully.";
        header("Location: " . BASE_URL . "admin/manage_announcements.php");
        exit;
    }
    
    $pageTitle = "Manage Announcements";
    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area">
        <div class="container">
            <?php if ($action === 'add' || $action === 'edit'): 
                $data = ['title' => '', 'content' => '', 'excerpt' => '', 'link' => '', 'link_text' => 'Learn More', 'start_date' => '', 'end_date' => '', 'is_active' => 1, 'priority' => 0, 'target_audience' => 'all'];
                if ($action === 'edit' && $id > 0) {
                    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) $data = $result->fetch_assoc();
                }
            ?>
                <header class="page-title-section">
                    <h1><i class="fas fa-plus-circle"></i> <?php echo ucfirst($action); ?> Announcement</h1>
                    <a href="manage_announcements.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
                </header>
                <form action="" method="post" class="form-admin card-style-admin">
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" class="form-control" value="<?php echo sanitizeOutput($data['title']); ?>" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="priority">Priority</label>
                            <input type="number" id="priority" name="priority" class="form-control" value="<?php echo (int)$data['priority']; ?>" min="0">
                            <small>Higher numbers appear first. 0 is normal.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="content">Full Content</label>
                        <textarea id="content" name="content" class="form-control" rows="8" required><?php echo sanitizeOutput($data['content']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="excerpt">Excerpt / Summary (Optional)</label>
                        <input type="text" id="excerpt" name="excerpt" class="form-control" value="<?php echo sanitizeOutput($data['excerpt']); ?>">
                    </div>
                     <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="link">Link URL (Optional)</label>
                            <input type="url" id="link" name="link" class="form-control" value="<?php echo sanitizeOutput($data['link']); ?>" placeholder="https://example.com/event-details">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="link_text">Link Button Text</label>
                            <input type="text" id="link_text" name="link_text" class="form-control" value="<?php echo sanitizeOutput($data['link_text']); ?>">
                        </div>
                    </div>
                     <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="start_date">Start Date (Optional)</label>
                            <input type="datetime-local" id="start_date" name="start_date" class="form-control" value="<?php echo !empty($data['start_date']) ? date('Y-m-d\TH:i', strtotime($data['start_date'])) : ''; ?>">
                            <small>Announcement will be visible from this date.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="end_date">End Date (Optional)</label>
                            <input type="datetime-local" id="end_date" name="end_date" class="form-control" value="<?php echo !empty($data['end_date']) ? date('Y-m-d\TH:i', strtotime($data['end_date'])) : ''; ?>">
                            <small>Announcement will be hidden after this date.</small>
                        </div>
                    </div>
                     <div class="form-row">
                         <div class="form-group col-md-6">
                            <label for="target_audience">Target Audience</label>
                            <select id="target_audience" name="target_audience" class="form-control">
                                <option value="all" <?php echo ($data['target_audience'] == 'all') ? 'selected' : ''; ?>>All Visitors</option>
                                <option value="students" <?php echo ($data['target_audience'] == 'students') ? 'selected' : ''; ?>>Students Only</option>
                                <option value="faculty" <?php echo ($data['target_audience'] == 'faculty') ? 'selected' : ''; ?>>Faculty Only</option>
                                <option value="admin" <?php echo ($data['target_audience'] == 'admin') ? 'selected' : ''; ?>>Admins Only</option>
                            </select>
                        </div>
                         <div class="form-group col-md-6 d-flex align-items-center pt-3">
                             <div class="form-check">
                                <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1" <?php echo ($data['is_active']) ? 'checked' : ''; ?>>
                                <label for="is_active" class="form-check-label">Announcement is Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="<?php echo $action == 'edit' ? 'update' : 'add'; ?>" class="cta-button btn-primary"><i class="fas fa-save"></i> Save Announcement</button>
                    </div>
                </form>
            <?php else: 
                $announcements = $conn->query("SELECT a.*, u.name as author_name FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.priority DESC, a.created_at DESC")->fetch_all(MYSQLI_ASSOC);
            ?>
                 <?php endif; ?>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    