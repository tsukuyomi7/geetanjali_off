    <?php
    // geetanjali_website/student/submit_work.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    // Enforce student (or higher) access
    enforceRoleAccess(['student', 'moderator', 'blog_editor', 'event_manager', 'admin', 'super_admin']);

    $pageTitle = "Submit Your Creative Work";
    $message = "";
    $user_id = $_SESSION['user_id'];

    // Define allowed submission types based on ENUM in DB
    $submission_types_allowed = ['poetry', 'short_story', 'article', 'essay', 'artwork', 'photography', 'other'];
    // Define allowed file types for uploads
    $allowed_file_mime_types = [
        'application/pdf' => '.pdf',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'text/plain' => '.txt',
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/gif' => '.gif'
    ];
    $max_file_upload_size = 5 * 1024 * 1024; // 5MB

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_creation'])) {
        $title = trim(filter_var($_POST['title'], FILTER_SANITIZE_STRING));
        $submission_type = isset($_POST['submission_type']) && in_array($_POST['submission_type'], $submission_types_allowed) ? $_POST['submission_type'] : null;
        $content_text = isset($_POST['content_text']) ? trim(filter_var($_POST['content_text'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)) : null;
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        // $event_id = isset($_POST['event_id']) && !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null; // Optional event linking

        $file_path_db = null;
        $file_original_name_db = null;
        $errors = [];

        if (empty($title)) $errors[] = "Submission title is required.";
        if (empty($submission_type)) $errors[] = "Please select a submission type.";
        if ($submission_type === 'other' && empty($_POST['submission_type_other'])) {
            // If 'other' is selected, but the text field is empty, you might want to handle this.
            // For now, we'll just use 'other'. A more robust solution would take the text field value.
        }

        // Either text content or a file (or both for some types like 'article' with an image) should be provided
        if (empty($content_text) && (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] == UPLOAD_ERR_NO_FILE)) {
            $errors[] = "Please provide either text content or upload a file for your submission.";
        }
        if (strlen($content_text ?? '') > 65000) $errors[] = "Text content is too long (max 65,000 characters).";


        // Handle file upload if a file is provided
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/submissions/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "Failed to create submission upload directory.";
                    error_log("Failed to create directory: " . $upload_dir);
                }
            }

            if (empty($errors) && is_dir($upload_dir) && is_writable($upload_dir)) {
                $file_name = $_FILES['submission_file']['name'];
                $file_tmp_name = $_FILES['submission_file']['tmp_name'];
                $file_size = $_FILES['submission_file']['size'];
                $file_mime_type = mime_content_type($file_tmp_name); // More reliable than $_FILES['type']
                
                if (!array_key_exists($file_mime_type, $allowed_file_mime_types)) {
                    $errors[] = "Invalid file type uploaded. Allowed types: PDF, DOC, DOCX, TXT, JPG, PNG, GIF.";
                } elseif ($file_size > $max_file_upload_size) {
                    $errors[] = "File size exceeds the limit of " . ($max_file_upload_size / 1024 / 1024) . "MB.";
                } else {
                    $file_extension = $allowed_file_mime_types[$file_mime_type];
                    $file_original_name_db = filter_var($file_name, FILTER_SANITIZE_STRING);
                    // Create a unique filename to prevent overwrites and ensure valid characters
                    $unique_prefix = "sub_" . $user_id . "_" . time() . "_";
                    $file_path_db = $unique_prefix . preg_replace("/[^a-zA-Z0-9_.-]/", "_", pathinfo($file_original_name_db, PATHINFO_FILENAME)) . $file_extension;
                    $target_file = $upload_dir . $file_path_db;

                    if (!move_uploaded_file($file_tmp_name, $target_file)) {
                        $errors[] = "Failed to save uploaded file. Please try again.";
                        error_log("Submission file move_uploaded_file failed for user ID: " . $user_id . ". Target: " . $target_file);
                    }
                }
            } elseif(empty($errors)) {
                $errors[] = "File upload directory is not configured correctly. Please contact support.";
                error_log("Submission upload directory not writable or missing: " . $upload_dir);
            }
        } elseif (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['submission_file']['error'] != UPLOAD_ERR_OK) {
            $errors[] = "There was an error uploading your file (Error code: " . $_FILES['submission_file']['error'] . "). Please try again.";
        }


        if (empty($errors)) {
            $sql_insert = "INSERT INTO submissions (user_id, title, submission_type, content_text, file_path, file_original_name, is_anonymous, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_review')";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                // Types: i (user_id), s (title), s (submission_type), s (content_text), s (file_path), s (file_original_name), i (is_anonymous)
                $stmt_insert->bind_param("isssssi", 
                    $user_id, $title, $submission_type, $content_text, 
                    $file_path_db, $file_original_name_db, $is_anonymous
                );

                if ($stmt_insert->execute()) {
                    $submission_id = $stmt_insert->insert_id;
                    logAudit($conn, $user_id, "Submitted new creative work", "submission", $submission_id, "Title: " . $title);
                    $_SESSION['success_message_submission'] = "Your work \"".htmlspecialchars($title)."\" has been submitted successfully! It is now pending review.";
                    header("Location: " . BASE_URL . "student/my_submissions.php"); // Redirect to a page showing their submissions
                    exit;
                } else {
                    $message = "<div class='form-message error'>Error submitting your work: " . htmlspecialchars($stmt_insert->error) . ".</div>";
                    error_log("Submission insert execute failed: " . $stmt_insert->error);
                }
                $stmt_insert->close();
            } else {
                $message = "<div class='form-message error'>Database error preparing your submission: " . htmlspecialchars($conn->error) . ".</div>";
                error_log("Submission insert prepare failed: " . $conn->error);
            }
        } else {
            $message = "<div class='form-message error'><ul>";
            foreach ($errors as $error) { $message .= "<li>" . htmlspecialchars($error) . "</li>"; }
            $message .= "</ul></div>";
        }
    }


    include '../includes/header.php';
    ?>

    <main id="page-content" class="std-profile-container"> <?php // Reusing profile container for consistent page layout ?>
        <div class="container">
            <header class="page-title-section">
                 <h1><i class="fas fa-feather-alt"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                 <p class="subtitle">Share your poetry, stories, articles, artwork, and more with the Geetanjali community.</p>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message'>" . $message . "</div>"; ?>

            <section class="content-section std-profile-main-content submit-work-form-section"> 
                <h2 class="section-title"><i class="fas fa-edit"></i> New Submission</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="profile-form" novalidate>
                    <fieldset>
                        <legend>Submission Details</legend>
                        <div class="form-group">
                            <label for="title">Title of Your Work <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-control form-control-lg" value="<?php echo sanitizeOutput($_POST['title'] ?? ''); ?>" required placeholder="e.g., My Epic Poem, A Short Tale of a Long Journey">
                        </div>

                        <div class="form-group">
                            <label for="submission_type">Type of Submission <span class="required">*</span></label>
                            <select id="submission_type" name="submission_type" class="form-control" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach ($submission_types_allowed as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php if(isset($_POST['submission_type']) && $_POST['submission_type'] == $type) echo 'selected'; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php /* Optional: If submission is tied to an event
                        <div class="form-group">
                            <label for="event_id">Related Event (Optional)</label>
                            <select id="event_id" name="event_id" class="form-control">
                                <option value="">-- None --</option>
                                </select>
                        </div>
                        */ ?>
                    </fieldset>

                    <fieldset>
                        <legend>Your Content</legend>
                        <p class="form-text text-muted">You can either paste your text content directly or upload a file (or both, if applicable).</p>
                        <div class="form-group">
                            <label for="content_text">Text Content (Paste here if applicable)</label>
                            <textarea id="content_text" name="content_text" class="form-control" rows="10" placeholder="Enter or paste your text-based submission here..."><?php echo sanitizeOutput($_POST['content_text'] ?? '', true); ?></textarea>
                            <small class="form-text text-muted">Max 65,000 characters. For longer works or specific formatting, please use file upload.</small>
                        </div>

                        <div class="form-group">
                            <label for="submission_file">Upload File (Optional)</label>
                            <input type="file" id="submission_file" name="submission_file" class="form-control-file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                            <small class="form-text text-muted">Max file size: <?php echo ($max_file_upload_size / 1024 / 1024); ?>MB. Allowed types: PDF, DOC, DOCX, TXT, JPG, PNG, GIF.</small>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Submission Preferences</legend>
                        <div class="form-group form-check-inline">
                            <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1" class="form-check-input" <?php if(isset($_POST['is_anonymous'])) echo 'checked'; ?>>
                            <label for="is_anonymous" class="form-check-label">Submit Anonymously (Your name will be hidden during initial review/publication if this option is supported)</label>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" name="submit_creation" class="cta-button btn-lg"><i class="fas fa-paper-plane"></i> Submit My Work</button>
                        <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="cta-button btn-secondary btn-lg"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    