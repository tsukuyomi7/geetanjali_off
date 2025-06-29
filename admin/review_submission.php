    <?php
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    
    // --- CRITICAL INCLUDES AND CHECKS ---
    if (!file_exists('../includes/db_connection.php')) { die("FATAL ERROR: db_connection.php not found."); }
    require_once '../includes/db_connection.php'; 
    if (!file_exists('../includes/functions.php')) { die("FATAL ERROR: functions.php not found."); }
    require_once '../includes/functions.php';     

    if (!isset($conn) || !$conn) { die("FATAL DB ERROR. Check db_connection.php."); }
    if (!defined('BASE_URL')) { die("FATAL CONFIG ERROR. BASE_URL not defined."); }
    $required_review_functions = ['enforceRoleAccess', 'getUserById', 'getSubmissionById', 'getBlogPostBySubmissionId', 'logAudit', 'sanitizeOutput'];
    foreach ($required_review_functions as $func) {
        if (!function_exists($func)) {
            die("FATAL FUNCTION MISSING: {$func}. Please add it to includes/functions.php.");
        }
    }

    enforceRoleAccess(['admin', 'super_admin', 'blog_editor', 'event_manager']);
    
    $submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($submission_id <= 0) {
        $_SESSION['error_message_submissions'] = "Invalid submission ID.";
        header("Location: " . BASE_URL . "admin/submissions.php"); exit;
    }
    
    // CSRF Token
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    $csrf_token = $_SESSION['csrf_token'];

    // Handle form submission for review update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submission_status'])) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
             $_SESSION['error_message_review'] = "CSRF validation failed. Please try again.";
        } else {
            $new_status = $_POST['status'];
            $feedback = trim($_POST['reviewer_feedback']);
            $admin_id = $_SESSION['user_id'];
            
            $allowed_statuses = ['pending_review', 'under_review', 'approved', 'rejected', 'needs_revision', 'published'];
            if (in_array($new_status, $allowed_statuses)) {
                $sql = "UPDATE submissions SET status = ?, reviewer_feedback = ?, reviewed_by = ?, review_date = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssii", $new_status, $feedback, $admin_id, $submission_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message_review'] = "Submission status updated successfully.";
                    logAudit($conn, $admin_id, "Reviewed submission (ID: $submission_id)", "submission", $submission_id, "Set status to '$new_status'");
                } else { $_SESSION['error_message_review'] = "Error updating status: " . $stmt->error; }
            } else { $_SESSION['error_message_review'] = "Invalid status selected."; }
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate token
        header("Location: " . $_SERVER['REQUEST_URI']); exit;
    }
    
    $submission = getSubmissionById($conn, $submission_id);
    if (!$submission) {
        $_SESSION['error_message_submissions'] = "Submission with ID #{$submission_id} not found.";
        header("Location: " . BASE_URL . "admin/submissions.php"); exit;
    }
    
    $existing_blog_post = getBlogPostBySubmissionId($conn, $submission_id);

    $pageTitle = "Review: " . sanitizeOutput($submission['title']);
    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area">
        <div class="container">
            <header class="page-title-section">
                <h1><i class="fas fa-file-signature"></i> <?php echo $pageTitle; ?></h1>
                <p class="subtitle">Reviewing a <?php echo sanitizeOutput(ucwords(str_replace('_',' ', $submission['submission_type']))); ?> submission</p>
                <a href="<?php echo BASE_URL; ?>admin/submissions.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Submissions Queue</a>
            </header>
            
            <?php if(isset($_SESSION['success_message_review'])) { echo "<div class='form-message success'>".$_SESSION['success_message_review']."</div>"; unset($_SESSION['success_message_review']); } ?>
            <?php if(isset($_SESSION['error_message_review'])) { echo "<div class='form-message error'>".$_SESSION['error_message_review']."</div>"; unset($_SESSION['error_message_review']); } ?>

            <div class="review-layout">
                <div class="review-content card-style-admin">
                    <h3 class="subsection-title">Submission Content</h3>
                    <?php if(!empty($submission['content_text'])): ?>
                        <div class="submission-text-content">
                            <?php echo nl2br(sanitizeOutput($submission['content_text'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($submission['file_path'])): 
                        $file_url = BASE_URL . 'uploads/submissions/' . sanitizeOutput($submission['file_path']);
                        $file_extension = strtolower(pathinfo($submission['file_path'], PATHINFO_EXTENSION));
                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    ?>
                        <hr>
                        <h4 class="subsection-title mini">Attached File</h4>
                        <?php if(in_array($file_extension, $image_extensions)): ?>
                            <div class="file-preview image-preview">
                                <a href="<?php echo $file_url; ?>" target="_blank"><img src="<?php echo $file_url; ?>" alt="Submitted Image Attachment"></a>
                            </div>
                        <?php elseif($file_extension === 'pdf'): ?>
                            <div class="file-preview pdf-preview">
                                <iframe src="<?php echo $file_url; ?>" width="100%" height="600px" title="PDF Preview">Your browser does not support embedded PDFs.</iframe>
                            </div>
                        <?php endif; ?>
                        <div class="submission-file-link mt-3">
                           <a href="<?php echo $file_url; ?>" target="_blank" download="<?php echo sanitizeOutput($submission['file_original_name'] ?? ''); ?>" class="cta-button btn-secondary-outline"><i class="fas fa-download"></i> Download Original File (<?php echo sanitizeOutput($submission['file_original_name'] ?? 'file'); ?>)</a>
                        </div>
                    <?php endif; ?>
                </div>
                <aside class="review-sidebar">
                    <div class="sidebar-widget card-style-admin">
                        <h3 class="subsection-title">Author Details</h3>
                        <p><strong>Name:</strong> <a href="<?php echo BASE_URL . 'super_admin/edit_user.php?id=' . $submission['user_id']; ?>" title="View User Profile"><?php echo sanitizeOutput($submission['author_name']); ?></a></p>
                        <p><strong>NIFTEM ID:</strong> <?php echo sanitizeOutput($submission['niftem_id']); ?></p>
                        <p><strong>Email:</strong> <a href="mailto:<?php echo sanitizeOutput($submission['author_email']); ?>"><?php echo sanitizeOutput($submission['author_email']); ?></a></p>
                         <?php if(!empty($submission['event_title'])): ?>
                            <p><strong>For Event:</strong> <?php echo sanitizeOutput($submission['event_title']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-widget card-style-admin">
                        <h3 class="subsection-title">Review & Actions</h3>
                        <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">
                             <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="form-group">
                                <label for="status">Change Status</label>
                                <select name="status" id="status" class="form-control">
                                    <?php 
                                    $statuses = ['pending_review', 'under_review', 'approved', 'rejected', 'needs_revision', 'published'];
                                    foreach ($statuses as $status_option): ?>
                                        <option value="<?php echo $status_option; ?>" <?php echo ($submission['status'] == $status_option) ? 'selected' : ''; ?>>
                                            <?php echo ucwords(str_replace('_', ' ', $status_option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="reviewer_feedback">Feedback for Student (optional)</label>
                                <textarea name="reviewer_feedback" id="reviewer_feedback" class="form-control" rows="8" placeholder="Provide constructive feedback or reasons for status change..."><?php echo sanitizeOutput($submission['reviewer_feedback']); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_submission_status" class="cta-button btn-primary"><i class="fas fa-save"></i> Save Review</button>
                            </div>
                        </form>
                        <hr>
                        <?php if ($submission['status'] === 'approved' || ($submission['status'] === 'published' && $existing_blog_post)): ?>
                            <h4 class="subsection-title mini">Publishing Options</h4>
                            <?php if ($existing_blog_post): ?>
                                <p>This submission is already published.</p>
                                <a href="<?php echo BASE_URL . 'blog/post.php?slug=' . $existing_blog_post['slug']; ?>" class="cta-button btn-info" target="_blank"><i class="fas fa-eye"></i> View Published Post</a>
                            <?php else: ?>
                                <p>This submission is approved. You can now create a blog post from it.</p>
                                <a href="<?php echo BASE_URL; ?>admin/blogs.php?action=add&from_submission=<?php echo $submission['id']; ?>" class="cta-button btn-success"><i class="fas fa-file-import"></i> Publish to Blog</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    