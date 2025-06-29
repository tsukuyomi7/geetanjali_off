    <?php
    // blog/view.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; // Defines $conn and BASE_URL
    require_once '../includes/functions.php';     // For helpers

    $post_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
    $post = null;
    $comments = [];
    $pageTitle = "Blog Post"; // Default title

    if (!empty($post_slug)) {
        // Fetch blog post
        $stmt_post = $conn->prepare("SELECT bp.id, bp.title, bp.slug, bp.content, bp.created_at, bp.author_id, u.name as author_name, u.profile_picture as author_profile_pic 
                                     FROM blog_posts bp
                                     JOIN users u ON bp.author_id = u.id
                                     WHERE bp.slug = ? AND bp.is_published = TRUE");
        if ($stmt_post) {
            $stmt_post->bind_param("s", $post_slug);
            $stmt_post->execute();
            $result_post = $stmt_post->get_result();
            if ($result_post->num_rows === 1) {
                $post = $result_post->fetch_assoc();
                $pageTitle = htmlspecialchars($post['title']);

                // Fetch comments for this post
                $stmt_comments = $conn->prepare("SELECT c.comment, c.created_at, u.name as commenter_name, u.profile_picture as commenter_profile_pic
                                                 FROM blog_comments c
                                                 JOIN users u ON c.user_id = u.id
                                                 WHERE c.post_id = ?
                                                 ORDER BY c.created_at DESC");
                if ($stmt_comments) {
                    $stmt_comments->bind_param("i", $post['id']);
                    $stmt_comments->execute();
                    $result_comments = $stmt_comments->get_result();
                    while ($comment_row = $result_comments->fetch_assoc()) {
                        $comments[] = $comment_row;
                    }
                    $stmt_comments->close();
                } else {
                    error_log("Failed to prepare statement for comments: " . $conn->error);
                }
            }
            $stmt_post->close();
        } else {
            error_log("Failed to prepare statement for blog post: " . $conn->error);
        }
    }
    
    // Handle Comment Submission (Basic - needs more robust validation & security)
    $comment_form_status = "";
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment']) && $post && checkUserLoggedIn()) {
        $comment_text = trim($_POST['comment_text']);
        if (!empty($comment_text)) {
            $user_id = $_SESSION['user_id'];
            $post_id = $post['id'];

            $stmt_insert_comment = $conn->prepare("INSERT INTO blog_comments (post_id, user_id, comment) VALUES (?, ?, ?)");
            if ($stmt_insert_comment) {
                $stmt_insert_comment->bind_param("iis", $post_id, $user_id, $comment_text);
                if ($stmt_insert_comment->execute()) {
                    // Refresh comments or add to array (for simplicity, redirecting to clear POST)
                    header("Location: " . BASE_URL . "blog/view.php?slug=" . urlencode($post_slug) . "&comment=success#comments-section");
                    exit;
                } else {
                    $comment_form_status = "<p class='alert alert-danger'>Error posting comment. Please try again.</p>";
                    error_log("Error inserting comment: " . $stmt_insert_comment->error);
                }
                $stmt_insert_comment->close();
            } else {
                 $comment_form_status = "<p class='alert alert-danger'>Error preparing comment. Please try again.</p>";
                 error_log("Error preparing comment statement: " . $conn->error);
            }
        } else {
            $comment_form_status = "<p class='alert alert-warning'>Comment cannot be empty.</p>";
        }
    }
    if(isset($_GET['comment']) && $_GET['comment'] == 'success'){
        $comment_form_status = "<p class='alert alert-success'>Your comment has been posted successfully!</p>";
    }


    include '../includes/header.php';
    ?>

    <main id="page-content">
        <?php if ($post): ?>
            <section class="page-title-section blog-post-title-hero">
                <div class="container">
                    <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                    <p class="post-meta-hero">
                        Published on <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                        by <a href="<?php echo BASE_URL . 'author.php?id=' . htmlspecialchars($post['author_id']); ?>"><?php echo htmlspecialchars($post['author_name']); ?></a>
                    </p>
                </div>
            </section>

            <section class="container content-section blog-post-view-section">
                <div class="blog-post-layout">
                    <article class="blog-post-full-content">
                        <?php /* If you have a featured image for blog posts:
                        <figure class="featured-image-container">
                            <img src="<?php echo BASE_URL; ?>images/blog/<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="featured-image">
                        </figure>
                        */ ?>
                        <div class="post-body">
                            <?php echo nl2br(htmlspecialchars_decode($post['content'])); // Assuming content might have some HTML, be careful with security. Consider a proper HTML purifier if admins can input rich text. For plain text, htmlspecialchars is fine. ?>
                        </div>

                        <hr class="my-4">

                        <div class="author-bio-box">
                            <div class="author-avatar">
                                <img src="<?php echo BASE_URL . (isset($post['author_profile_pic']) ? 'uploads/profile/' . htmlspecialchars($post['author_profile_pic']) : 'assets/images/placeholder-avatar.png'); ?>" alt="<?php echo htmlspecialchars($post['author_name']); ?>">
                            </div>
                            <div class="author-details">
                                <h4>About <?php echo htmlspecialchars($post['author_name']); ?></h4>
                                <p><?php /* Fetch author bio from users table if available */ ?>A passionate writer and member of Geetanjali. More details about the author can be placed here.</p>
                                <a href="<?php echo BASE_URL . 'author.php?id=' . htmlspecialchars($post['author_id']); ?>">View all posts by <?php echo htmlspecialchars($post['author_name']); ?></a>
                            </div>
                        </div>
                    </article>

                    <aside class="blog-post-sidebar">
                        <div class="sidebar-widget">
                            <h4>Share This Post</h4>
                             <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(BASE_URL . 'blog/view.php?slug=' . $post_slug); ?>" target="_blank" class="social-share-link"><i class="fab fa-facebook-f"></i> Facebook</a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . 'blog/view.php?slug=' . $post_slug); ?>&text=<?php echo urlencode($post['title']); ?>" target="_blank" class="social-share-link"><i class="fab fa-twitter"></i> Twitter</a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(BASE_URL . 'blog/view.php?slug=' . $post_slug); ?>&title=<?php echo urlencode($post['title']); ?>" target="_blank" class="social-share-link"><i class="fab fa-linkedin-in"></i> LinkedIn</a>
                        </div>
                        <div class="sidebar-widget">
                            <h4>Recent Posts</h4>
                            <?php
                            // Placeholder: Fetch 3-4 recent posts excluding the current one
                            // $recent_posts_stmt = $conn->prepare("SELECT title, slug FROM blog_posts WHERE is_published = TRUE AND id != ? ORDER BY created_at DESC LIMIT 4");
                            // $recent_posts_stmt->bind_param("i", $post['id']);
                            // ... execute and fetch ...
                            ?>
                            <ul class="styled-list arrow-list">
                                <li><a href="#">Another Recent Post Title</a></li>
                                <li><a href="#">Exploring Literary Themes</a></li>
                                <li><a href="#">A Quick Read</a></li>
                            </ul>
                        </div>
                    </aside>
                </div>

                <section id="comments-section" class="comments-area">
                    <h3><i class="fas fa-comments"></i> <?php echo count($comments); ?> Comment<?php echo (count($comments) != 1) ? 's' : ''; ?></h3>
                    <?php if (!empty($comment_form_status)) echo $comment_form_status; ?>

                    <?php if (checkUserLoggedIn()): ?>
                        <form action="<?php echo htmlspecialchars(BASE_URL . 'blog/view.php?slug=' . $post_slug); ?>#comments-section" method="POST" class="comment-form">
                            <div class="form-group">
                                <label for="comment_text">Leave a Comment:</label>
                                <textarea id="comment_text" name="comment_text" rows="4" required class="form-control"></textarea>
                            </div>
                            <button type="submit" name="submit_comment" class="cta-button">Post Comment</button>
                        </form>
                    <?php else: ?>
                        <p>Please <a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>#comments-section">login</a> to post a comment.</p>
                    <?php endif; ?>

                    <div class="comments-list">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item">
                                    <div class="comment-avatar">
                                        <img src="<?php echo BASE_URL . (isset($comment['commenter_profile_pic']) ? 'uploads/profile/' . htmlspecialchars($comment['commenter_profile_pic']) : 'assets/images/placeholder-avatar.png'); ?>" alt="<?php echo htmlspecialchars($comment['commenter_name']); ?>">
                                    </div>
                                    <div class="comment-content">
                                        <p class="comment-author"><strong><?php echo htmlspecialchars($comment['commenter_name']); ?></strong> 
                                        <span class="comment-date">on <?php echo date('F j, Y \a\t g:i A', strtotime($comment['created_at'])); ?></span></p>
                                        <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (empty($comment_form_status) || strpos($comment_form_status, 'success') === false) : ?>
                            <p>No comments yet. Be the first to comment!</p>
                        <?php endif; ?>
                    </div>
                </section>

            </section>

        <?php else: ?>
            <section class="container content-section">
                 <div class="alert alert-danger text-center">
                    <h2>Post Not Found</h2>
                    <p>Sorry, the blog post you are looking for does not exist, may have been moved, or is not published.</p>
                    <a href="<?php echo BASE_URL; ?>blog/" class="cta-button">Back to Blog</a>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php include '../includes/footer.php'; ?>
    