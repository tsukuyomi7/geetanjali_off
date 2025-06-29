    <?php
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    $author_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($author_id <= 0) { header("Location: " . BASE_URL . "blog/"); exit; }
    
    $author = getUserById($conn, $author_id);
    if (!$author || $author['account_status'] !== 'active') {
        $_SESSION['error_message'] = "Author not found or their account is not active.";
        header("Location: " . BASE_URL . "blog/"); exit;
    }

    $pageTitle = "Posts by " . sanitizeOutput($author['name']);
    
    // Fetch all posts by this author
    $author_posts = [];
    $sql = "SELECT id, title, slug, excerpt, featured_image, published_at 
            FROM blog_posts 
            WHERE author_id = ? AND is_published = TRUE AND published_at <= NOW()
            ORDER BY published_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $author_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $author_posts[] = $row;
    }
    $stmt->close();
    
    include '../includes/header.php';
    ?>
    <main id="page-content">
        <section class="page-title-section author-header">
            <div class="container">
                <div class="author-info-card">
                    <img src="<?php echo !empty($author['profile_picture']) ? BASE_URL . 'uploads/profile/' . sanitizeOutput($author['profile_picture']) : BASE_URL . 'assets/images/placeholder-avatar.png'; ?>" alt="<?php echo sanitizeOutput($author['name']); ?>" class="author-avatar-large">
                    <div class="author-details-large">
                        <h1 class="author-name-large"><?php echo sanitizeOutput($author['name']); ?></h1>
                        <?php if (!empty($author['bio'])): ?>
                            <p class="author-bio-large"><?php echo sanitizeOutput($author['bio']); ?></p>
                        <?php endif; ?>
                        </div>
                </div>
            </div>
        </section>

        <section class="container content-section">
            <h2 class="section-title text-center">Articles by <?php echo sanitizeOutput($author['name']); ?></h2>
            <div class="blog-posts-main">
                 <?php if (!empty($author_posts)): ?>
                    <?php foreach ($author_posts as $post): ?>
                        <article class="blog-post-item" data-aos="fade-up">
                            </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-posts-message">This author has not published any posts yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php include '../includes/footer.php'; ?>
    