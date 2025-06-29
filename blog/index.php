    <?php
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    define('INITIALIZE_AOS', true);
    
    // Use require_once for all core includes
    require_once '../includes/db_connection.php';
    require_once '../includes/functions.php';

    // --- Critical Dependency Checks ---
    if (!isset($conn) || !$conn) { die("FATAL DB ERROR (BLOG_IDX_DB01): Please fix db_connection.php."); }
    if (!defined('BASE_URL')) { die("FATAL CONFIG ERROR (BLOG_IDX_BU01): BASE_URL is not defined in db_connection.php."); }
    $blog_idx_required_functions = ['getSetting', 'generatePaginationLinks', 'sanitizeOutput', 'getLatestBlogPosts', 'time_elapsed_string'];
    foreach ($blog_idx_required_functions as $func) { if (!function_exists($func)) { die("FATAL FUNCTION MISSING: {$func} (BLOG_IDX_FN01). Please check includes/functions.php."); } }

    $pageTitle = "Geetanjali Blog";

    // --- Dynamic Data Fetching & Filtering Logic ---
    $posts_per_page = (int)getSetting($conn, 'blog_posts_per_page', 5);
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if($current_page < 1) $current_page = 1;
    $offset = ($current_page - 1) * $posts_per_page;
    
    $search_query = isset($_GET['q']) ? sanitizeOutput(trim($_GET['q'])) : '';
    $category_slug = isset($_GET['category']) ? sanitizeOutput(trim($_GET['category'])) : '';
    
    // Dynamic Page Title & Subtitle
    $page_heading = "The Official Blog";
    $page_subtitle = "Insights, reflections, and creative pieces from the Geetanjali community.";

    // --- Build the main SQL query with filters ---
    $sql_select = "SELECT bp.id, bp.title, bp.slug, bp.excerpt, bp.featured_image, bp.published_at, u.name as author_name, u.unique_public_id, u.profile_visibility";
    $sql_from = " FROM blog_posts bp JOIN users u ON bp.author_id = u.id";
    $sql_joins = "";
    $where_clauses = ["bp.is_published = TRUE", "bp.published_at <= NOW()"];
    $params = [];
    $types = "";

    if (!empty($search_query)) {
        $where_clauses[] = "(bp.title LIKE ? OR bp.content LIKE ?)";
        $search_term = "%" . $search_query . "%";
        array_push($params, $search_term, $search_term);
        $types .= "ss";
        $page_heading = "Search Results";
        $page_subtitle = "Showing posts matching '" . sanitizeOutput($search_query) . "'";
    }

    if (!empty($category_slug)) {
        $sql_joins = " JOIN blog_post_categories bpc ON bp.id = bpc.post_id JOIN blog_categories bc ON bpc.category_id = bc.id";
        $where_clauses[] = "bc.slug = ?";
        $params[] = $category_slug;
        $types .= "s";
        
        $category_name_stmt = $conn->prepare("SELECT name FROM blog_categories WHERE slug = ? LIMIT 1");
        if($category_name_stmt) {
            $category_name_stmt->bind_param("s", $category_slug);
            $category_name_stmt->execute();
            $category_name_res = $category_name_stmt->get_result();
            if($category_name_row = $category_name_res->fetch_assoc()){
                $page_heading = "Category: " . sanitizeOutput($category_name_row['name']);
            }
            $category_name_stmt->close();
        }
    }

    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
    $sql_order = " ORDER BY bp.published_at DESC";

    // Get total count for pagination
    $total_posts = 0;
    $total_posts_sql = "SELECT COUNT(DISTINCT bp.id) as total " . $sql_from . $sql_joins . $sql_where;
    $stmt_total = $conn->prepare($total_posts_sql);
    if($stmt_total) {
        if(!empty($types)) { $stmt_total->bind_param($types, ...$params); }
        if($stmt_total->execute()) {
            $total_result = $stmt_total->get_result()->fetch_assoc();
            $total_posts = $total_result ? (int)$total_result['total'] : 0;
        } else { error_log("Blog Count Query Execute Error: " . $stmt_total->error); }
        $stmt_total->close();
    } else { error_log("Blog Count Query Prepare Error: " . $conn->error); }
    
    $total_pages = $posts_per_page > 0 ? ceil($total_posts / $posts_per_page) : 0;
    if ($current_page > $total_pages && $total_pages > 0) { $current_page = $total_pages; $offset = ($current_page - 1) * $posts_per_page; }

    // Fetch posts for the current page
    $blog_posts = [];
    $sql_posts_list = $sql_select . $sql_from . $sql_joins . $sql_where . $sql_order . " LIMIT ? OFFSET ?";
    $paginated_params = $params;
    array_push($paginated_params, $posts_per_page, $offset);
    $paginated_types = $types . "ii";
    
    $stmt_posts = $conn->prepare($sql_posts_list);
    if ($stmt_posts) {
        if(!empty($paginated_types)) { $stmt_posts->bind_param($paginated_types, ...$paginated_params); }
        if ($stmt_posts->execute()) {
            $result = $stmt_posts->get_result();
            while($row = $result->fetch_assoc()) { $blog_posts[] = $row; }
        } else { error_log("Blog Index List Execute Error: " . $stmt_posts->error); }
        $stmt_posts->close();
    } else { error_log("Blog Index List Prepare Error: " . $conn->error); }
    
    // --- Sidebar Data Fetching ---
    $recent_posts = getLatestBlogPosts($conn, 5);
    $categories = [];
    $sql_cat = "SELECT bc.name, bc.slug, COUNT(bpc.post_id) as post_count FROM blog_categories bc JOIN blog_post_categories bpc ON bc.id = bpc.category_id JOIN blog_posts bp ON bpc.post_id = bp.id WHERE bp.is_published = TRUE AND bp.published_at <= NOW() GROUP BY bc.id, bc.name, bc.slug HAVING post_count > 0 ORDER BY bc.name ASC";
    $cat_result = $conn->query($sql_cat);
    if ($cat_result) { while($row = $cat_result->fetch_assoc()) { $categories[] = $row; } }


    include '../includes/header.php'; 
    ?>

    <main id="page-content">
        <section class="page-title-section">
            <div class="container">
                <h1><i class="fas fa-rss-square"></i> <?php echo $page_heading; ?></h1>
                <p class="subtitle"><?php echo $page_subtitle; ?></p>
            </div>
        </section>

        <section class="container content-section">
            <div class="blog-listing-layout">
                <div class="blog-posts-main">
                    <?php if (!empty($blog_posts)): ?>
                        <?php foreach ($blog_posts as $index => $post): ?>
                            <article class="blog-post-item <?php if ($index === 0 && $current_page === 1 && empty($search_query) && empty($category_slug)) echo 'featured-post'; ?>" data-aos="fade-up">
                                <?php if (!empty($post['featured_image'])): ?>
                                    <a href="<?php echo BASE_URL; ?>blog/post.php?slug=<?php echo sanitizeOutput($post['slug']); ?>" class="post-thumbnail-link">
                                        <img src="<?php echo BASE_URL . 'uploads/blog/' . sanitizeOutput($post['featured_image']); ?>" alt="<?php echo sanitizeOutput($post['title']); ?>" class="post-thumbnail">
                                    </a>
                                <?php endif; ?>
                                <div class="post-content">
                                    <header class="post-header">
                                        <h2 class="post-title"><a href="<?php echo BASE_URL; ?>blog/post.php?slug=<?php echo sanitizeOutput($post['slug']); ?>"><?php echo sanitizeOutput($post['title']); ?></a></h2>
                                        <p class="post-meta">
                                            By 
                                            <?php if(($post['profile_visibility'] ?? 'private') === 'public' && !empty($post['unique_public_id'])): ?>
                                                <a href="<?php echo BASE_URL . 'public_profile.php?id=' . $post['unique_public_id']; ?>"><?php echo sanitizeOutput($post['author_name']); ?></a>
                                            <?php else: ?>
                                                <span><?php echo sanitizeOutput($post['author_name']); ?></span>
                                            <?php endif; ?>
                                            on <?php echo date('F j, Y', strtotime($post['published_at'])); ?>
                                        </p>
                                    </header>
                                    <div class="post-excerpt">
                                        <p><?php echo sanitizeOutput($post['excerpt']); ?></p>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>blog/post.php?slug=<?php echo sanitizeOutput($post['slug']); ?>" class="cta-button-small">Read More <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php 
                            $query_params_for_pagination = ['q' => $search_query, 'category' => $category_slug];
                            echo generatePaginationLinks($current_page, $total_pages, BASE_URL . 'blog/', array_filter($query_params_for_pagination)); 
                        ?>

                    <?php else: ?>
                        <div class="no-posts-message card-style-admin">
                            <i class="fas fa-ghost fa-3x"></i>
                            <h4>No Posts Found</h4>
                            <p>Sorry, we couldn't find any articles matching your criteria. Try a different search or browse our categories.</p>
                            <a href="<?php echo BASE_URL; ?>blog/" class="cta-button btn-sm">Back to All Posts</a>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="blog-sidebar" data-aos="fade-left" data-aos-delay="200">
                    <div class="sidebar-widget">
                        <h4>Search Blog</h4>
                        <form action="<?php echo BASE_URL; ?>blog/" method="GET" class="search-form">
                            <input type="search" name="q" placeholder="Search articles..." aria-label="Search articles" value="<?php echo $search_query; ?>">
                            <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                    <?php if(!empty($recent_posts)): ?>
                    <div class="sidebar-widget">
                        <h4>Recent Posts</h4>
                        <ul class="styled-list arrow-list">
                            <?php foreach ($recent_posts as $recent_post): ?>
                            <li><a href="<?php echo BASE_URL; ?>blog/post.php?slug=<?php echo sanitizeOutput($recent_post['slug']); ?>"><?php echo sanitizeOutput($recent_post['title']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($categories)): ?>
                    <div class="sidebar-widget">
                        <h4>Categories</h4>
                        <ul class="styled-list category-list">
                             <li><a href="<?php echo BASE_URL; ?>blog/"><strong>All Posts</strong> (<?php echo $total_posts; ?>)</a></li>
                            <?php foreach($categories as $category): ?>
                            <li><a href="<?php echo BASE_URL; ?>blog/?category=<?php echo sanitizeOutput($category['slug']); ?>"><?php echo sanitizeOutput($category['name']); ?> <span>(<?php echo $category['post_count']; ?>)</span></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </aside>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    