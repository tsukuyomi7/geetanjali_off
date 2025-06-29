    <?php
    // geetanjali_website/admin/blogs.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    // Enforce access for relevant roles
    enforceRoleAccess(['admin', 'super_admin', 'blog_editor']);

    $pageTitle = "Manage Blog Posts";
    $current_admin_id = $_SESSION['user_id'];
    $current_admin_role = $_SESSION['user_role'];

    // Actions: list (default), add, edit, delete
    $action = $_GET['action'] ?? 'list';
    $post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $message = $_SESSION['message'] ?? ''; // Flash messages
    unset($_SESSION['message']);
    $error_message = $_SESSION['error_message'] ?? '';
    unset($_SESSION['error_message']);

    // --- Function to generate a slug ---
    if (!function_exists('generateSlug')) {
        function generateSlug(string $title): string {
            $slug = strtolower(trim($title));
            $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug); // Replace non-alphanumeric with -
            $slug = trim($slug, '-'); // Remove leading/trailing hyphens
            $slug = preg_replace('/-+/', '-', $slug); // Replace multiple hyphens with single
            return $slug ?: 'n-a'; // Fallback for empty titles
        }
    }
    
    // Available statuses for blog posts
    $post_statuses = ['draft', 'published', 'pending_review', 'archived'];
    // Fetch categories for dropdown (conceptual - assumes blog_categories table)
    $categories = [];
    $cat_result = $conn->query("SELECT id, name FROM blog_categories ORDER BY name ASC");
    if ($cat_result) { while($cat_row = $cat_result->fetch_assoc()) { $categories[] = $cat_row; } }


    // --- Handle Form Submissions (Add/Edit) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_blog_post'])) {
            $id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
            $title = trim(filter_var($_POST['title'], FILTER_SANITIZE_STRING));
            $slug = trim(filter_var($_POST['slug'], FILTER_SANITIZE_STRING));
            $content = trim($_POST['content']); // Sanitize based on rich text editor or use a library
            $excerpt = trim(filter_var($_POST['excerpt'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $tags = trim(filter_var($_POST['tags'], FILTER_SANITIZE_STRING));
            $status = isset($_POST['status']) && in_array($_POST['status'], $post_statuses) ? $_POST['status'] : 'draft';
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
            $published_at = ($status === 'published' && (empty($id) || getPostStatus($conn, $id) !== 'published')) ? date('Y-m-d H:i:s') : (isset($_POST['current_published_at']) && !empty($_POST['current_published_at']) ? $_POST['current_published_at'] : null);


            $errors = [];
            if (empty($title)) $errors[] = "Title is required.";
            if (empty($content)) $errors[] = "Content is required.";
            if (empty($slug)) $slug = generateSlug($title); // Auto-generate slug if empty

            // Slug uniqueness check
            $slug_check_sql = $id ? "SELECT id FROM blog_posts WHERE slug = ? AND id != ?" : "SELECT id FROM blog_posts WHERE slug = ?";
            $stmt_slug = $conn->prepare($slug_check_sql);
            if ($id) $stmt_slug->bind_param("si", $slug, $id);
            else $stmt_slug->bind_param("s", $slug);
            $stmt_slug->execute();
            if ($stmt_slug->get_result()->num_rows > 0) $errors[] = "Slug already exists. Please choose a unique one.";
            $stmt_slug->close();
            
            $featured_image_path = $_POST['current_featured_image'] ?? null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == UPLOAD_ERR_OK) {
                // File upload logic (similar to profile picture or gallery upload)
                $upload_dir = '../uploads/blog/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                // ... (add file validation: type, size) ...
                $img_name = time() . '_' . basename($_FILES['featured_image']['name']);
                $target_file = $upload_dir . $img_name;
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                    // Delete old image if updating and new one uploaded
                    if($id && $featured_image_path && file_exists($upload_dir . $featured_image_path) && $featured_image_path !== $img_name) {
                        @unlink($upload_dir . $featured_image_path);
                    }
                    $featured_image_path = $img_name;
                } else { $errors[] = "Failed to upload featured image."; }
            } elseif (isset($_POST['remove_featured_image']) && $id && $featured_image_path) {
                // Delete existing image
                if(file_exists('../uploads/blog/' . $featured_image_path)) {
                    @unlink('../uploads/blog/' . $featured_image_path);
                }
                $featured_image_path = null;
            }


            if (empty($errors)) {
                if ($id) { // Update existing post
                    $sql = "UPDATE blog_posts SET title=?, slug=?, content=?, excerpt=?, category_id=?, tags=?, status=?, is_featured=?, allow_comments=?, featured_image=?, published_at=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssisiiissi", $title, $slug, $content, $excerpt, $category_id, $tags, $status, $is_featured, $allow_comments, $featured_image_path, $published_at, $id);
                } else { // Add new post
                    $user_id = $_SESSION['user_id']; // Author
                    $sql = "INSERT INTO blog_posts (user_id, title, slug, content, excerpt, category_id, tags, status, is_featured, allow_comments, featured_image, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("issssisiiiss", $user_id, $title, $slug, $content, $excerpt, $category_id, $tags, $status, $is_featured, $allow_comments, $featured_image_path, $published_at);
                }

                if ($stmt && $stmt->execute()) {
                    $action_taken = $id ? "Updated blog post" : "Created new blog post";
                    $new_post_id = $id ?: $stmt->insert_id;
                    logAudit($conn, $current_admin_id, $action_taken, "blog_post", $new_post_id, "Title: " . $title);
                    $_SESSION['success_message'] = "Blog post " . ($id ? "updated" : "created") . " successfully.";
                    header("Location: " . BASE_URL . "admin/blogs.php");
                    exit;
                } else {
                    $error_message = "Error saving blog post: " . ($stmt ? htmlspecialchars($stmt->error) : htmlspecialchars($conn->error));
                    error_log("Blog save error: " . ($stmt ? $stmt->error : $conn->error) . " SQL: " . $sql);
                }
                if($stmt) $stmt->close();
            } else {
                $error_message = "<ul>";
                foreach ($errors as $err) { $error_message .= "<li>" . htmlspecialchars($err) . "</li>"; }
                $error_message .= "</ul>";
            }
        }
    }

    // --- Handle Delete Action ---
    if ($action === 'delete' && $post_id > 0) {
        // Add CSRF token check here for GET-based delete for better security
        // For simplicity, direct delete with confirm. POST is better.
        $post_to_delete = getBlogPostMapping($conn, $post_id, 'id'); // Fetch to log title and delete image
        if ($post_to_delete) {
            $stmt_del = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("i", $post_id);
                if ($stmt_del->execute()) {
                    // Delete featured image if it exists
                    if (!empty($post_to_delete['featured_image']) && file_exists('../uploads/blog/' . $post_to_delete['featured_image'])) {
                        @unlink('../uploads/blog/' . $post_to_delete['featured_image']);
                    }
                    logAudit($conn, $current_admin_id, "Deleted blog post", "blog_post", $post_id, "Title: " . $post_to_delete['title']);
                    $_SESSION['success_message'] = "Blog post '" . sanitizeOutput($post_to_delete['title']) . "' deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Error deleting post: " . htmlspecialchars($stmt_del->error);
                    error_log("Blog delete error: " . $stmt_del->error);
                }
                $stmt_del->close();
            } else { $_SESSION['error_message'] = "DB error preparing delete."; error_log("Blog delete prepare error: ".$conn->error);}
        } else { $_SESSION['error_message'] = "Post not found for deletion."; }
        header("Location: " . BASE_URL . "admin/blogs.php");
        exit;
    }


    // --- Data for Add/Edit Form ---
    $post_data = [
        'id' => null, 'title' => '', 'slug' => '', 'content' => '', 'excerpt' => '', 
        'category_id' => null, 'tags' => '', 'status' => 'draft', 
        'is_featured' => 0, 'allow_comments' => 1, 'featured_image' => null, 'published_at' => null
    ];
    if ($action === 'edit' && $post_id > 0) {
        $pageTitle = "Edit Blog Post";
        $stmt_edit = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
        if($stmt_edit){
            $stmt_edit->bind_param("i", $post_id);
            $stmt_edit->execute();
            $result_edit = $stmt_edit->get_result();
            if ($result_edit->num_rows === 1) {
                $post_data = $result_edit->fetch_assoc();
            } else {
                $_SESSION['error_message'] = "Blog post not found for editing.";
                header("Location: " . BASE_URL . "admin/blogs.php"); exit;
            }
            $stmt_edit->close();
        } else { error_log("Blog edit fetch prepare error: ".$conn->error); /* Handle error */ }
    } elseif ($action === 'add') {
        $pageTitle = "Add New Blog Post";
    }


    // --- Fetch Posts for Listing ---
    if ($action === 'list') {
        $pageTitle = "Manage Blog Posts";
        // Filtering and Search
        $filter_status = isset($_GET['status']) && in_array($_GET['status'], $post_statuses) ? $_GET['status'] : '';
        $filter_category = isset($_GET['category_id']) ? (int)$_GET['category_id'] : '';
        $search_title = isset($_GET['search_title']) ? sanitizeOutput(trim($_GET['search_title'])) : '';

        $list_sql_base = "FROM blog_posts bp LEFT JOIN users u ON bp.user_id = u.id LEFT JOIN blog_categories bc ON bp.category_id = bc.id";
        $list_where_clauses = [];
        $list_params = [];
        $list_types = "";

        if (!empty($filter_status)) { $list_where_clauses[] = "bp.status = ?"; $list_params[] = $filter_status; $list_types .= "s"; }
        if (!empty($filter_category)) { $list_where_clauses[] = "bp.category_id = ?"; $list_params[] = $filter_category; $list_types .= "i"; }
        if (!empty($search_title)) { $list_where_clauses[] = "bp.title LIKE ?"; $list_params[] = "%" . $search_title . "%"; $list_types .= "s"; }
        
        $list_sql_where = "";
        if(!empty($list_where_clauses)) { $list_sql_where = " WHERE " . implode(" AND ", $list_where_clauses); }

        // Pagination
        $items_per_page_list = (int)getSetting($conn, 'admin_items_per_page', 10);
        $current_page_list = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($current_page_list < 1) $current_page_list = 1;

        $total_posts_sql = "SELECT COUNT(bp.id) as total " . $list_sql_base . $list_sql_where;
        $stmt_total_list = $conn->prepare($total_posts_sql);
        $total_posts = 0;
        if ($stmt_total_list) {
            if(!empty($list_types)) $stmt_total_list->bind_param($list_types, ...$list_params);
            if($stmt_total_list->execute()){
                $res_total_list = $stmt_total_list->get_result();
                $total_posts_row = $res_total_list->fetch_assoc();
                $total_posts = $total_posts_row ? (int)$total_posts_row['total'] : 0;
            } else { error_log("Blog list total count execute error: " . $stmt_total_list->error); }
            $stmt_total_list->close();
        } else { error_log("Blog list total count prepare error: " . $conn->error); }

        $total_pages_list = ($items_per_page_list > 0 && $total_posts > 0) ? ceil($total_posts / $items_per_page_list) : 1;
        if($current_page_list > $total_pages_list) $current_page_list = $total_pages_list;
        $offset_list = ($current_page_list - 1) * $items_per_page_list;
        if($offset_list < 0) $offset_list = 0;

        $blog_posts = [];
        $sql_list_posts = "SELECT bp.id, bp.title, bp.slug, bp.status, bp.is_featured, bp.published_at, bp.created_at, bp.updated_at, u.name as author_name, bc.name as category_name "
                        . $list_sql_base . $list_sql_where 
                        . " ORDER BY bp.created_at DESC LIMIT ? OFFSET ?";
        
        $stmt_list_posts = $conn->prepare($sql_list_posts);
        if($stmt_list_posts){
            $list_current_types = $list_types . "ii";
            $list_current_params = array_merge($list_params, [$items_per_page_list, $offset_list]);
            if(empty($list_types)) { if(!$stmt_list_posts->bind_param("ii", $items_per_page_list, $offset_list)) error_log("Bind Error: " . $stmt_list_posts->error); }
            else { if(!$stmt_list_posts->bind_param($list_current_types, ...$list_current_params)) error_log("Bind Error: " . $stmt_list_posts->error); }

            if($stmt_list_posts->execute()){
                $res_list_posts = $stmt_list_posts->get_result();
                while($row = $res_list_posts->fetch_assoc()) { $blog_posts[] = $row; }
            } else { error_log("Blog list fetch execute error: " . $stmt_list_posts->error); }
            $stmt_list_posts->close();
        } else { error_log("Blog list fetch prepare error: " . $conn->error); }
    } // end if action == list

    include '../includes/header.php';
    ?>
    <main id="page-content" class="admin-area std-profile-container">
        <div class="container">
            <header class="std-profile-header page-title-section">
                <h1><i class="fas fa-newspaper"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <?php if ($action === 'list'): ?>
                    <p class="subtitle">Create, edit, and manage all blog content.</p>
                    <p><a href="<?php echo BASE_URL; ?>admin/blogs.php?action=add" class="cta-button btn-success"><i class="fas fa-plus-circle"></i> Add New Post</a></p>
                <?php elseif ($action === 'add' || $action === 'edit'): ?>
                    <p class="subtitle"><?php echo $action === 'add' ? 'Craft a new article for your audience.' : 'Refine your existing post.'; ?></p>
                    <p><a href="<?php echo BASE_URL; ?>admin/blogs.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Back to Posts List</a></p>
                <?php endif; ?>
            </header>

            <?php if (!empty($message)) echo "<div class='admin-page-message'>" . $message . "</div>"; ?>
            <?php if (!empty($error_message)): ?>
                <div class="form-message error admin-page-message"><?php echo $error_message; /* Already contains ul/li if multiple */ ?></div>
            <?php endif; ?>


            <?php if ($action === 'add' || $action === 'edit'): ?>
            <section class="content-section std-profile-main-content card-style-admin">
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" enctype="multipart/form-data" class="profile-form admin-settings-form" id="blogPostForm">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="post_id" value="<?php echo $post_data['id']; ?>">
                        <input type="hidden" name="current_featured_image" value="<?php echo sanitizeOutput($post_data['featured_image'] ?? ''); ?>">
                        <input type="hidden" name="current_published_at" value="<?php echo sanitizeOutput($post_data['published_at'] ?? ''); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="active_category" value="<?php echo $action; ?>"> {/* For potential tab persistence if form was tabbed */}

                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="title">Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-control form-control-lg" value="<?php echo sanitizeOutput($post_data['title'] ?? $_POST['title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="slug">Slug (URL Friendly) <span class="required">*</span></label>
                            <input type="text" id="slug" name="slug" class="form-control form-control-lg" value="<?php echo sanitizeOutput($post_data['slug'] ?? $_POST['slug'] ?? ''); ?>" placeholder="auto-generated if blank">
                            <small class="form-text text-muted">Unique, URL-friendly identifier.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="content">Content <span class="required">*</span></label>
                        <textarea id="content" name="content" class="form-control" rows="15" placeholder="Write your blog post here... Supports basic HTML. For rich text, integrate an editor."><?php echo htmlspecialchars($post_data['content'] ?? $_POST['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="form-text text-muted">Consider using a Rich Text Editor (e.g., TinyMCE, CKEditor) for better formatting options.</small>
                    </div>

                    <div class="form-group">
                        <label for="excerpt">Excerpt (Optional)</label>
                        <textarea id="excerpt" name="excerpt" class="form-control" rows="3" placeholder="A short summary of the post for listings and SEO."><?php echo sanitizeOutput($post_data['excerpt'] ?? $_POST['excerpt'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="featured_image">Featured Image</label>
                            <input type="file" id="featured_image" name="featured_image" class="form-control-file" accept="image/jpeg,image/png,image/gif">
                            <?php if (!empty($post_data['featured_image'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo BASE_URL . 'uploads/blog/' . sanitizeOutput($post_data['featured_image']); ?>" alt="Current Featured Image" style="max-height: 100px; border-radius: 4px;">
                                    <label class="ml-2"><input type="checkbox" name="remove_featured_image" value="1"> Remove current image</label>
                                </div>
                            <?php endif; ?>
                            <small class="form-text text-muted">Recommended size: 1200x628px. Max 2MB.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value="">-- Select Category --</option>
                                <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php if (($post_data['category_id'] ?? $_POST['category_id'] ?? '') == $category['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tags">Tags (comma-separated)</label>
                        <input type="text" id="tags" name="tags" class="form-control" value="<?php echo sanitizeOutput($post_data['tags'] ?? $_POST['tags'] ?? ''); ?>" placeholder="e.g., poetry, short story, event recap">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="status">Status <span class="required">*</span></label>
                            <select id="status" name="status" class="form-control">
                                <?php foreach($post_statuses as $status_opt): ?>
                                <option value="<?php echo $status_opt; ?>" <?php if (($post_data['status'] ?? $_POST['status'] ?? 'draft') == $status_opt) echo 'selected'; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $status_opt)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4 form-check-toggle align-self-center">
                             <label for="is_featured">Feature this post?</label>
                             <label class="switch">
                                <input type="checkbox" id="is_featured" name="is_featured" value="1" <?php echo (($post_data['is_featured'] ?? $_POST['is_featured'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                         <div class="form-group col-md-4 form-check-toggle align-self-center">
                             <label for="allow_comments">Allow comments?</label>
                             <label class="switch">
                                <input type="checkbox" id="allow_comments" name="allow_comments" value="1" <?php echo (($post_data['allow_comments'] ?? $_POST['allow_comments'] ?? 1) == 1) ? 'checked' : ''; /* Default to allow */ ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="save_blog_post" class="cta-button btn-lg"><i class="fas fa-save"></i> <?php echo $action === 'edit' ? 'Update' : 'Publish'; ?> Post</button>
                        <a href="<?php echo BASE_URL; ?>admin/blogs.php" class="cta-button btn-secondary btn-lg"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </section>

            <?php elseif ($action === 'list'): ?>
            <section class="content-section filters-and-search-section card-style-admin">
                <h2 class="section-title-minor"><i class="fas fa-filter"></i> Filter & Search Posts</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="form-filters-search">
                    <input type="hidden" name="action" value="list">
                    <div class="filter-grid">
                        <div class="form-group filter-item">
                            <label for="search_title_list">Search by Title</label>
                            <div class="input-group-admin">
                                <span class="input-group-icon"><i class="fas fa-search"></i></span>
                                <input type="text" id="search_title_list" name="search_title" class="form-control" placeholder="Enter title keywords..." value="<?php echo sanitizeOutput($search_title); ?>">
                            </div>
                        </div>
                         <div class="form-group filter-item">
                            <label for="filter_category_list">Filter by Category</label>
                            <select id="filter_category_list" name="category_id" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php if ($filter_category == $category['id']) echo 'selected'; ?>>
                                    <?php echo sanitizeOutput($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="filter_status_list">Filter by Status</label>
                            <select id="filter_status_list" name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <?php foreach ($post_statuses as $status_opt): ?>
                                     <option value="<?php echo $status_opt; ?>" <?php if ($filter_status == $status_opt) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $status_opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-check-circle"></i> Apply</button>
                        <a href="<?php echo BASE_URL; ?>admin/blogs.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear</a>
                    </div>
                </form>
            </section>

            <section class="content-section std-profile-main-content card-style-admin"> 
                <div class="table-header-controls">
                    <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> Blog Posts (<?php echo $total_posts; ?> total)</h2>
                </div>
                <div class="table-responsive-wrapper">
                    <table class="admin-table stylish-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Tags</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Published On</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($blog_posts)): ?>
                                <?php foreach ($blog_posts as $post): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo BASE_URL . 'blog/post.php?slug=' . sanitizeOutput($post['slug']); ?>" target="_blank" title="View Post: <?php echo sanitizeOutput($post['title']); ?>">
                                                <strong><?php echo sanitizeOutput($post['title']); ?></strong>
                                            </a>
                                            <br><small class="text-muted">Slug: <?php echo sanitizeOutput($post['slug']); ?></small>
                                        </td>
                                        <td><?php echo sanitizeOutput($post['author_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo sanitizeOutput($post['category_name'] ?? 'N/A'); ?></td>
                                        <td><small><?php echo sanitizeOutput($post['tags'] ? substr($post['tags'],0,30).'...' : 'N/A'); ?></small></td>
                                        <td><span class="status-badge status-<?php echo sanitizeOutput($post['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', sanitizeOutput($post['status']))); ?></span></td>
                                        <td><?php echo $post['is_featured'] ? '<i class="fas fa-star text-warning" title="Featured"></i>' : '<i class="far fa-star text-muted" title="Not Featured"></i>'; ?></td>
                                        <td><?php echo $post['published_at'] ? '<span title="'.date('M j, Y H:i', strtotime($post['published_at'])).'">'.time_elapsed_string($post['published_at']).'</span>' : 'Not Published'; ?></td>
                                        <td><?php echo time_elapsed_string($post['updated_at']); ?></td>
                                        <td class="actions-cell">
                                            <a href="<?php echo BASE_URL; ?>admin/blogs.php?action=edit&id=<?php echo $post['id']; ?>" class="btn btn-xs btn-info" title="Edit Post"><i class="fas fa-edit"></i></a>
                                            <a href="<?php echo BASE_URL; ?>admin/blogs.php?action=delete&id=<?php echo $post['id']; ?>" class="btn btn-xs btn-danger" title="Delete Post" onclick="return confirm('Are you sure you want to delete this post permanently?');"><i class="fas fa-trash-alt"></i></a>
                                            <a href="<?php echo BASE_URL . 'blog/post.php?slug=' . sanitizeOutput($post['slug']); ?>" target="_blank" class="btn btn-xs btn-secondary" title="View Post"><i class="fas fa-eye"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center no-results-row">No blog posts found matching your criteria. <a href="<?php echo BASE_URL; ?>admin/blogs.php">Clear filters</a>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <?php if ($total_pages_list > 1): ?>
                <nav class="pagination-container mt-3" aria-label="Blog posts navigation">
                    <ul class="pagination-list">
                        <?php 
                        // Build query string for pagination links, excluding 'page'
                        $query_params_list = $_GET; unset($query_params_list['page']);
                        $pagination_query_string = http_build_query($query_params_list);
                        if(!empty($pagination_query_string)) $pagination_query_string .= '&';
                        ?>
                        <?php if ($current_page_list > 1): ?>
                            <li class="page-item"><a class="page-link" href="?<?php echo $pagination_query_string; ?>page=<?php echo $current_page_list - 1; ?>"><i class="fas fa-chevron-left"></i> Prev</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages_list; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page_list) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo $pagination_query_string; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($current_page_list < $total_pages_list): ?>
                            <li class="page-item"><a class="page-link" href="?<?php echo $pagination_query_string; ?>page=<?php echo $current_page_list + 1; ?>">Next <i class="fas fa-chevron-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </section>
            <?php endif; // End if action === 'list' ?>
        </div>
    </main>
    <script>
    // Basic JS for slug generation and featured image preview
    document.addEventListener('DOMContentLoaded', function() {
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');

        if (titleInput && slugInput) {
            titleInput.addEventListener('keyup', function() {
                // Basic slug generation (can be improved with more robust regex)
                let slug = this.value.toLowerCase().trim()
                    .replace(/\s+/g, '-')           // Replace spaces with -
                    .replace(/[^\w-]+/g, '')       // Remove all non-word chars
                    .replace(/--+/g, '-');          // Replace multiple - with single -
                slugInput.value = slug;
            });
        }

        const featuredImageInput = document.getElementById('featured_image');
        const imagePreviewContainer = document.querySelector('.mt-2 img'); // Assuming this is where current image is shown
        const removeImageCheckbox = document.querySelector('input[name="remove_featured_image"]');

        if (featuredImageInput && imagePreviewContainer) {
            featuredImageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreviewContainer.src = e.target.result;
                        imagePreviewContainer.style.display = 'block';
                        if(removeImageCheckbox) removeImageCheckbox.checked = false; // Uncheck remove if new image selected
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
        if (removeImageCheckbox && imagePreviewContainer) {
            removeImageCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    imagePreviewContainer.style.opacity = '0.5'; // Visually indicate it will be removed
                } else {
                    imagePreviewContainer.style.opacity = '1';
                }
            });
        }
    });
    </script>
    <?php include '../includes/footer.php'; ?>
    