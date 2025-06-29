    <?php
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    define('INITIALIZE_AOS', true);
    
    // --- CRITICAL INCLUDES AND CHECKS ---
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    if (!isset($conn) || !$conn) { die("FATAL DB ERROR. Check db_connection.php."); }
    if (!defined('BASE_URL')) { die("FATAL CONFIG ERROR. BASE_URL not defined."); }
    $required_functions = ['getPostBySlug', 'getCategoriesForPost', 'getCommentsForPost', 'getRelatedPosts', 'checkUserLoggedIn', 'sanitizeOutput', 'time_elapsed_string'];
    foreach ($required_functions as $func) { if (!function_exists($func)) { die("FATAL FUNCTION MISSING: {$func}. Please check includes/functions.php."); } }

    // --- DATA FETCHING ---
    $slug = isset($_GET['slug']) ? sanitizeOutput($_GET['slug']) : '';
    if (empty($slug)) { header("Location: " . BASE_URL . "blog/"); exit; }
    $post = getPostBySlug($conn, $slug);

    // --- 404 NOT FOUND HANDLING ---
    if (!$post) {
        http_response_code(404);
        $pageTitle = "Post Not Found";
        include '../includes/header.php';
        echo "<main id='page-content'><div class='container content-section text-center'><div class='card-style-admin'><h2>404 - Post Not Found</h2><p>Sorry, the post you are looking for does not exist or has been removed.</p><a href='".BASE_URL."blog/' class='cta-button'>Back to Blog</a></div></div></main>";
        include '../includes/footer.php';
        exit;
    }

    $pageTitle = $post['title'];
    $current_user_id = $_SESSION['user_id'] ?? 0;

    // --- COMMENT FORM SUBMISSION HANDLING ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
        if (!checkUserLoggedIn()) { $_SESSION['error_message_comment'] = "You must be logged in to comment."; }
        elseif (empty(trim($_POST['comment']))) { $_SESSION['error_message_comment'] = "Comment cannot be empty."; }
        else {
            $comment_text = sanitizeOutput($_POST['comment']);
            $sql = "INSERT INTO comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $post['id'], $current_user_id, $comment_text);
            if ($stmt->execute()) {
                $_SESSION['success_message_comment'] = "Your comment has been submitted successfully!";
            } else { $_SESSION['error_message_comment'] = "Failed to submit comment. Please try again."; }
        }
        header("Location: " . $_SERVER['REQUEST_URI'] . '#comments'); exit;
    }
    
    // --- DYNAMIC DATA FOR PAGE SECTIONS ---
    $post_categories = getCategoriesForPost($conn, $post['id']);
    $comments = getCommentsForPost($conn, $post['id']);
    $related_posts = getRelatedPosts($conn, $post['id'], array_column($post_categories, 'id'));

    include '../includes/header.php';
    ?>

    <style>
        .blog-post-layout {
            display: grid; grid-template-columns: 1fr; gap: 40px;
        }
        @media (min-width: 992px) {
            .blog-post-layout { grid-template-columns: minmax(0, 3fr) minmax(0, 1fr); }
        }
        .blog-post-title-hero {
            padding: 4rem 0; color: #fff; text-align: center;
            background-size: cover; background-position: center;
            position: relative; margin-bottom: 2rem;
        }
        .post-meta-hero-categories { margin-bottom: 1rem; }
        .post-meta-hero-categories .category-badge {
            background-color: var(--accent-gold); color: var(--primary-color);
            padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold;
        }
        .blog-post-title-hero h1 { font-size: 3.5em; text-shadow: 2px 2px 5px rgba(0,0,0,0.5); }
        .post-meta-hero { font-size: 1.1em; opacity: 0.9; }

        .blog-post-full-content { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: var(--shadow-sm); }
        .post-body { line-height: 1.8; font-size: 1.1em; color: #333; }

        /* --- Author Bio Box CSS --- */
        .author-bio-box {
            background-color: var(--background-offwhite); padding: 1.5rem; margin-top: 2rem;
            border-radius: 8px; display: flex; align-items: flex-start; gap: 1.5rem;
        }
        .author-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; }
        .author-details h4 { margin-top: 0; color: var(--primary-color); }
        .author-details p { margin-bottom: 0; font-size: 0.95em; color: var(--text-muted); }

        /* --- Related Posts CSS --- */
        .related-posts-section { margin-top: 3rem; }
        .related-posts-section .subsection-title { text-align: center; margin-bottom: 2rem; }
        .post-preview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .post-card-small { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: var(--shadow-sm); transition: transform 0.2s ease; }
        .post-card-small:hover { transform: translateY(-5px); }
        .post-card-image-small { width: 100%; height: 150px; object-fit: cover; }
        .post-card-content-small h4 { padding: 1rem; margin: 0; font-size: 1em; }

        /* --- Comments Area CSS --- */
        .comments-area { margin-top: 3rem; padding: 2rem; }
        .comments-list { margin-top: 2rem; }
        .comment-item { display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border-color); }
        .comment-item:last-child { border-bottom: none; }
        .comment-avatar { width: 50px; height: 50px; border-radius: 50%; }
        .comment-author { font-weight: bold; color: var(--primary-color); }
        .comment-date { font-size: 0.85em; color: #888; margin-left: 0.5rem; }
        .comment-content p { margin: 0.5rem 0 0 0; }
        .comment-form { margin-top: 1.5rem; }

        /* --- Sidebar & Social Share CSS --- */
        .blog-post-sidebar .sidebar-widget { background-color: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow-sm); }
        .sidebar-widget h4 { margin-top: 0; }
        .social-share-links { display: flex; flex-direction: column; gap: 0.5rem; }
        .social-share-link {
            padding: 10px; border-radius: 4px; text-decoration: none; color: #fff;
            display: block; text-align: center; transition: opacity 0.2s ease;
        }
        .social-share-link:hover { opacity: 0.85; }
        .social-share-link i { margin-right: 8px; }
        .social-share-link.facebook { background-color: #3b5998; }
        .social-share-link.twitter { background-color: #1da1f2; }
        .social-share-link.linkedin { background-color: #0077b5; }
        .social-share-link.whatsapp { background-color: #25d366; }
    </style>
    <main id="page-content">
        <article class="blog-post-full-view">
            <header class="blog-post-title-hero" style="background-image: linear-gradient(rgba(10,35,66,0.8), rgba(10,35,66,0.7)), url('<?php echo !empty($post['featured_image']) ? BASE_URL . 'uploads/blog/' . sanitizeOutput($post['featured_image']) : BASE_URL . 'assets/images/hero-bg.jpg'; ?>');">
                <div class="container" data-aos="fade-in">
                    <div class="post-meta-hero-categories">
                        <?php if(!empty($post_categories)): foreach($post_categories as $cat): ?>
                            <a href="<?php echo BASE_URL . 'blog/?category=' . $cat['slug']; ?>" class="category-badge"><?php echo sanitizeOutput($cat['name']); ?></a>
                        <?php endforeach; endif; ?>
                    </div>
                    <h1 data-aos="fade-up" data-aos-delay="100"><?php echo sanitizeOutput($post['title']); ?></h1>
                    <p class="post-meta-hero" data-aos="fade-up" data-aos-delay="200">
                        Posted on <?php echo date('F j, Y', strtotime($post['published_at'])); ?>
                    </p>
                </div>
            </header>

            <div class="container content-section">
                <div class="blog-post-layout">
                    <div class="blog-post-full-content">
                        <div class="post-body typography-style">
                            <?php echo nl2br($post['content']); ?>
                        </div>
                        <hr class="my-5">
                        <div class="author-bio-box card-style-admin">
                            <img src="<?php echo !empty($post['author_avatar']) ? BASE_URL . 'uploads/profile/' . sanitizeOutput($post['author_avatar']) : BASE_URL . 'assets/images/placeholder-avatar.png'; ?>" alt="<?php echo sanitizeOutput($post['author_name']); ?>" class="author-avatar">
                            <div class="author-details">
                                <h4>ABOUT: <?php echo sanitizeOutput($post['author_name']); ?></h4>
                                <p><?php echo sanitizeOutput($post['author_bio'] ?? 'This author has not provided a bio yet.'); ?></p>
                            </div>
                        </div>

                        <?php if (!empty($related_posts)): ?>
                        <div class="related-posts-section">
                            <h3 class="subsection-title">You Might Also Like</h3>
                            <div class="post-preview-grid">
                                <?php foreach($related_posts as $related): ?>
                                    <div class="post-card-small">
                                        <a href="<?php echo BASE_URL . 'blog/post.php?slug=' . sanitizeOutput($related['slug']); ?>">
                                            <?php if(!empty($related['featured_image'])): ?>
                                            <img src="<?php echo BASE_URL . 'uploads/blog/' . sanitizeOutput($related['featured_image']); ?>" alt="<?php echo sanitizeOutput($related['title']); ?>" class="post-card-image-small">
                                            <?php endif; ?>
                                            <div class="post-card-content-small">
                                                <h4><?php echo sanitizeOutput($related['title']); ?></h4>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div id="comments" class="comments-area card-style-admin">
                            <h3 class="subsection-title"><?php echo count($comments); ?> Comment(s)</h3>
                             <?php if(isset($_SESSION['success_message_comment'])) { echo "<div class='form-message success'>".$_SESSION['success_message_comment']."</div>"; unset($_SESSION['success_message_comment']); } ?>
                             <?php if(isset($_SESSION['error_message_comment'])) { echo "<div class='form-message error'>".$_SESSION['error_message_comment']."</div>"; unset($_SESSION['error_message_comment']); } ?>
                            
                            <?php if (checkUserLoggedIn()): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>#comments" method="POST" class="comment-form">
                                    <div class="form-group">
                                        <label for="comment">Leave a Comment</label>
                                        <textarea name="comment" id="comment" rows="4" class="form-control" placeholder="Write your thoughts..." required></textarea>
                                    </div>
                                    <button type="submit" name="submit_comment" class="cta-button">Submit Comment</button>
                                </form>
                            <?php else: ?>
                                <p class="alert alert-info">Please <a href="<?php echo BASE_URL; ?>login.php?redirect_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">login</a> to post a comment.</p>
                            <?php endif; ?>
                            
                            <div class="comments-list">
                                <?php if(empty($comments)): ?>
                                    <p class="mt-3">Be the first to share your thoughts!</p>
                                <?php else: foreach($comments as $comment): ?>
                                <div class="comment-item">
                                    <img src="<?php echo !empty($comment['author_avatar']) ? BASE_URL . 'uploads/profile/' . sanitizeOutput($comment['author_avatar']) : BASE_URL . 'assets/images/placeholder-avatar.png'; ?>" alt="" class="comment-avatar">
                                    <div class="comment-content">
                                        <div class="comment-author"><strong><?php echo sanitizeOutput($comment['author_name']); ?></strong> <span class="comment-date"><?php echo time_elapsed_string($comment['created_at']); ?></span></div>
                                        <p><?php echo nl2br(sanitizeOutput($comment['comment'])); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                    <aside class="blog-post-sidebar">
                        <div class="sidebar-widget card-style-admin">
                            <h4><i class="fas fa-share-alt"></i> Share This Post</h4>
                            <div class="social-share-links">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(BASE_URL . 'blog/post.php?slug=' . $post['slug']); ?>" target="_blank" rel="noopener noreferrer" class="social-share-link facebook"><i class="fab fa-facebook-f"></i> Facebook</a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . 'blog/post.php?slug=' . $post['slug']); ?>&text=<?php echo urlencode($post['title']); ?>" target="_blank" rel="noopener noreferrer" class="social-share-link twitter"><i class="fab fa-twitter"></i> Twitter</a>
                                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(BASE_URL . 'blog/post.php?slug=' . $post['slug']); ?>&title=<?php echo urlencode($post['title']); ?>" target="_blank" rel="noopener noreferrer" class="social-share-link linkedin"><i class="fab fa-linkedin-in"></i> LinkedIn</a>
                                <a href="whatsapp://send?text=<?php echo urlencode($post['title'] . " " . BASE_URL . 'blog/post.php?slug=' . $post['slug']); ?>" data-action="share/whatsapp/share" class="social-share-link whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </article>
    </main>
    <?php include '../includes/footer.php'; ?>
    