    <?php
    // geetanjali_website/student/my_submissions.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    // Enforce access for logged-in users (students or higher roles who might submit)
    enforceRoleAccess(['student', 'moderator', 'blog_editor', 'event_manager', 'admin', 'super_admin']);

    $pageTitle = "My Submissions";
    $user_id = $_SESSION['user_id'];
    $submissions = [];
    
    $message = $_SESSION['success_message_submission'] ?? ''; unset($_SESSION['success_message_submission']);
    $error_message = $_SESSION['error_message_submission'] ?? ''; unset($_SESSION['error_message_submission']);


    // --- Handle Delete Action (if implemented) ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete_submission' && isset($_GET['id'])) {
        $submission_id_to_delete = (int)$_GET['id'];
        // Add CSRF token check for security
        
        // First, check if the submission belongs to the current user and is in a deletable status (e.g., 'draft')
        $stmt_check = $conn->prepare("SELECT file_path, status FROM submissions WHERE id = ? AND user_id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("ii", $submission_id_to_delete, $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $submission_to_delete = $result_check->fetch_assoc();
            $stmt_check->close();

            if ($submission_to_delete && ($submission_to_delete['status'] === 'draft' || $submission_to_delete['status'] === 'pending_review')) { // Allow deletion of pending_review too
                $stmt_del = $conn->prepare("DELETE FROM submissions WHERE id = ? AND user_id = ?");
                if ($stmt_del) {
                    $stmt_del->bind_param("ii", $submission_id_to_delete, $user_id);
                    if ($stmt_del->execute() && $stmt_del->affected_rows > 0) {
                        // Delete associated file if it exists
                        if (!empty($submission_to_delete['file_path']) && file_exists('../uploads/submissions/' . $submission_to_delete['file_path'])) {
                            @unlink('../uploads/submissions/' . $submission_to_delete['file_path']);
                        }
                        logAudit($conn, $user_id, "Deleted own submission", "submission", $submission_id_to_delete);
                        $_SESSION['success_message_submission'] = "Submission deleted successfully.";
                    } else {
                        $_SESSION['error_message_submission'] = "Error deleting submission or not authorized.";
                        error_log("Submission delete error (user: $user_id, submission: $submission_id_to_delete): " . $stmt_del->error);
                    }
                    $stmt_del->close();
                } else { $_SESSION['error_message_submission'] = "Error preparing delete statement."; }
            } else {
                $_SESSION['error_message_submission'] = "Submission cannot be deleted or does not exist.";
            }
        } else { $_SESSION['error_message_submission'] = "Error checking submission for deletion."; }
        header("Location: " . BASE_URL . "student/my_submissions.php");
        exit;
    }


    // --- Pagination ---
    $items_per_page = (int)getSetting($conn, 'student_submissions_per_page', 5);
    if($items_per_page <= 0) $items_per_page = 5;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($current_page < 1) $current_page = 1;

    $total_submissions_sql = "SELECT COUNT(*) as total FROM submissions WHERE user_id = ?";
    $stmt_total = $conn->prepare($total_submissions_sql);
    $total_items = 0;
    if($stmt_total){
        $stmt_total->bind_param("i", $user_id);
        if($stmt_total->execute()){
            $result_total = $stmt_total->get_result()->fetch_assoc();
            $total_items = $result_total ? (int)$result_total['total'] : 0;
        } else { error_log("MySubmissions: Total count execute error: ".$stmt_total->error); }
        $stmt_total->close();
    } else { error_log("MySubmissions: Total count prepare error: ".$conn->error); }

    $total_pages = ($items_per_page > 0 && $total_items > 0) ? ceil($total_items / $items_per_page) : 1;
    if ($current_page > $total_pages) $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
    if ($offset < 0) $offset = 0;

    // Fetch submissions for the logged-in user
    $sql = "SELECT s.id, s.title, s.submission_type, s.content_text, s.file_path, s.file_original_name, 
                   s.submission_date, s.status, s.reviewer_feedback, s.updated_at, 
                   e.title as event_title
            FROM submissions s
            LEFT JOIN events e ON s.event_id = e.id
            WHERE s.user_id = ?
            ORDER BY s.submission_date DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iii", $user_id, $items_per_page, $offset);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $submissions[] = $row;
            }
        } else { error_log("My Submissions: Failed to execute statement: " . $stmt->error); }
        $stmt->close();
    } else { error_log("My Submissions: Failed to prepare statement: " . $conn->error); }

    include '../includes/header.php';
    ?>

    <main id="page-content" class="std-profile-container"> 
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Track the status of your creative works and view feedback.</p>
                <p>
                    <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>student/submit_work.php" class="btn btn-sm btn-success ml-2"><i class="fas fa-plus-circle"></i> Submit New Work</a>
                </p>
            </header>

            <section class="content-section std-profile-main-content">
                <?php if (!empty($message)): ?>
                    <div class="form-message success admin-page-message"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="form-message error admin-page-message"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if (!empty($submissions)): ?>
                    <div class="submissions-list">
                        <?php foreach ($submissions as $sub): ?>
                            <div class="submission-item card-style-admin" id="submission-<?php echo $sub['id']; ?>" data-aos="fade-up">
                                <div class="submission-item-header">
                                    <h3 class="submission-title"><?php echo sanitizeOutput($sub['title']); ?></h3>
                                    <span class="submission-status-badge status-<?php echo strtolower(str_replace(' ', '-', sanitizeOutput($sub['status']))); ?>">
                                        <?php echo sanitizeOutput(ucwords(str_replace('_', ' ', $sub['status']))); ?>
                                    </span>
                                </div>
                                <div class="submission-meta">
                                    <span><i class="fas fa-puzzle-piece"></i> Type: <strong><?php echo sanitizeOutput(ucwords(str_replace('_', ' ', $sub['submission_type']))); ?></strong></span>
                                    <span><i class="fas fa-calendar-alt"></i> Submitted: <?php echo time_elapsed_string($sub['submission_date']); ?></span>
                                    <?php if(!empty($sub['event_title'])): ?>
                                        <span><i class="fas fa-calendar-check"></i> For Event: <?php echo sanitizeOutput($sub['event_title']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if(!empty($sub['content_text'])): ?>
                                    <div class="submission-content-preview">
                                        <h4>Content Snippet:</h4>
                                        <p><?php echo sanitizeOutput(substr(strip_tags($sub['content_text']), 0, 200)); ?>...</p>
                                    </div>
                                <?php endif; ?>

                                <?php if(!empty($sub['file_path'])): ?>
                                    <div class="submission-file-info">
                                        <p><i class="fas fa-paperclip"></i> Attached File: 
                                            <a href="<?php echo BASE_URL . 'uploads/submissions/' . sanitizeOutput($sub['file_path']); ?>" target="_blank" rel="noopener noreferrer" download="<?php echo sanitizeOutput($sub['file_original_name'] ?? 'submission_file'); ?>">
                                                <?php echo sanitizeOutput($sub['file_original_name'] ?? $sub['file_path']); ?>
                                            </a>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if(!empty($sub['reviewer_feedback']) && in_array($sub['status'], ['rejected', 'needs_revision'])): ?>
                                    <div class="submission-feedback alert alert-info">
                                        <h4><i class="fas fa-comment-dots"></i> Reviewer Feedback:</h4>
                                        <p><?php echo nl2br(sanitizeOutput($sub['reviewer_feedback'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="submission-actions">
                                    <?php if(in_array($sub['status'], ['draft', 'needs_revision'])): ?>
                                        <a href="<?php echo BASE_URL . 'student/submit_work.php?action=edit&id=' . $sub['id']; ?>" class="cta-button btn-info btn-sm">
                                            <i class="fas fa-edit"></i> Edit Submission
                                        </a>
                                    <?php endif; ?>
                                    <?php if(in_array($sub['status'], ['draft', 'pending_review'])): // Can delete drafts or pending ones ?>
                                         <a href="<?php echo BASE_URL . 'student/my_submissions.php?action=delete_submission&id=' . $sub['id']; ?>&amp;csrf_token=<?php // echo $_SESSION['csrf_token_val']; ?>" 
                                            class="cta-button btn-danger-outline btn-sm" 
                                            onclick="return confirm('Are you sure you want to delete this submission? This cannot be undone.');">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                    <?php /* <a href="#" class="cta-button btn-secondary-outline btn-sm">View Details (Conceptual)</a> */ ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php 
                    if ($total_pages > 1) {
                        echo generatePaginationLinks($current_page, $total_pages, BASE_URL . 'student/my_submissions.php');
                    }
                    ?>
                <?php else: ?>
                    <div class="no-results-message text-center card-style-admin" style="padding: 2rem;">
                        <i class="fas fa-folder-plus fa-4x text-muted mb-3"></i>
                        <h4>No Submissions Yet</h4>
                        <p>You haven't submitted any creative works. Share your talent with the community!</p>
                        <a href="<?php echo BASE_URL; ?>student/submit_work.php" class="cta-button btn-success mt-3"><i class="fas fa-feather-alt"></i> Submit Your First Piece</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    