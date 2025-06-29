    <?php
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    enforceRoleAccess(['admin', 'super_admin', 'blog_editor', 'event_manager']);

    $pageTitle = "Manage Submissions";
    include '../includes/header.php';

    // Fetch all submissions with filters
    $filter_status = $_GET['status'] ?? 'all';
    $filter_type = $_GET['type'] ?? 'all';
    $search_query = isset($_GET['search']) ? sanitizeOutput(trim($_GET['search'])) : '';

    $sql = "SELECT s.id, s.title, s.submission_type, s.status, s.submission_date, u.name as author_name, u.niftem_id
            FROM submissions s
            JOIN users u ON s.user_id = u.id";
    
    $where_clauses = [];
    $params = [];
    $types = "";

    if ($filter_status !== 'all') {
        $where_clauses[] = "s.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    if ($filter_type !== 'all') {
        $where_clauses[] = "s.submission_type = ?";
        $params[] = $filter_type;
        $types .= "s";
    }
    if (!empty($search_query)) {
        $where_clauses[] = "(s.title LIKE ? OR u.name LIKE ? OR u.niftem_id LIKE ?)";
        $search_term = "%" . $search_query . "%";
        array_push($params, $search_term, $search_term, $search_term);
        $types .= "sss";
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $sql .= " ORDER BY s.submission_date DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $submissions = [];
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $submissions[] = $row;
        }
    } elseif ($stmt) {
        error_log("Error executing submissions query: " . $stmt->error);
    } else {
        error_log("Error preparing submissions query: " . $conn->error);
    }

    $submission_statuses = ['pending_review', 'under_review', 'approved', 'rejected', 'needs_revision', 'published', 'draft'];
    $submission_types = ['poetry', 'short_story', 'article', 'essay', 'artwork', 'photography', 'other'];
    ?>

    <main id="page-content" class="admin-area">
        <div class="container">
            <header class="page-title-section">
                <h1><i class="fas fa-inbox"></i> Manage Submissions</h1>
                <p class="subtitle">Review, approve, and manage all creative works submitted by members.</p>
            </header>

            <section class="content-section card-style-admin" data-aos="fade-up">
                <h2 class="section-title-minor"><i class="fas fa-filter"></i> Filter Submissions</h2>
                <form method="GET" action="" class="form-filters-search">
                    <div class="filter-grid">
                        <div class="form-group filter-item">
                            <label for="search">Search by Title, Author, or NID</label>
                            <input type="text" id="search" name="search" class="form-control" value="<?php echo $search_query; ?>" placeholder="e.g., 'The Lost Star' or 'Priya'">
                        </div>
                        <div class="form-group filter-item">
                            <label for="status">Filter by Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="all">All Statuses</option>
                                <?php foreach($submission_statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($filter_status === $status) ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-item">
                            <label for="type">Filter by Type</label>
                             <select name="type" id="type" class="form-control">
                                <option value="all">All Types</option>
                                <?php foreach($submission_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo ($filter_type === $type) ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $type)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                     <div class="filter-actions">
                        <button type="submit" class="cta-button btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                        <a href="<?php echo BASE_URL; ?>admin/submissions.php" class="cta-button btn-secondary"><i class="fas fa-times-circle"></i> Clear</a>
                    </div>
                </form>
            </section>
            
            <section class="content-section card-style-admin" data-aos="fade-up" data-aos-delay="100">
                <div class="table-header-controls">
                    <h2 class="section-title-minor"><i class="fas fa-list-ul"></i> Submission Queue (<?php echo count($submissions); ?> found)</h2>
                </div>
                <div class="table-responsive-wrapper">
                    <table class="admin-table stylish-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author (NID)</th>
                                <th>Type</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($submissions)): ?>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td><strong><?php echo sanitizeOutput($submission['title']); ?></strong></td>
                                        <td><?php echo sanitizeOutput($submission['author_name']); ?><br><small class="text-muted">(<?php echo sanitizeOutput($submission['niftem_id']); ?>)</small></td>
                                        <td><?php echo sanitizeOutput(ucwords(str_replace('_',' ', $submission['submission_type']))); ?></td>
                                        <td title="<?php echo date('M j, Y, g:i a', strtotime($submission['submission_date'])); ?>"><?php echo time_elapsed_string($submission['submission_date']); ?></td>
                                        <td>
                                            <span class="submission-status-badge status-<?php echo sanitizeOutput($submission['status']); ?>">
                                                <?php echo sanitizeOutput(ucwords(str_replace('_', ' ', $submission['status']))); ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="review_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-xs btn-info"><i class="fas fa-eye"></i> Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center no-results-row">No submissions found matching your criteria.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    