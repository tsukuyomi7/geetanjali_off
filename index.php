    <?php
    // geetanjali_website/index.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Set a constant to tell header.php to initialize the AOS library
    define('INITIALIZE_AOS', true);

    require_once 'includes/db_connection.php';
    require_once 'includes/functions.php';

    $pageTitle = "Welcome to Geetanjali";

    // --- Fetch Dynamic Content for Homepage ---
    $upcoming_events_list = [];
    $latest_blog_posts_list = [];
    $stats = ['members' => 0, 'events' => 0, 'works' => 0];

    if (isset($conn) && $conn) {
        if (function_exists('getUpcomingEvents')) {
            $upcoming_events_list = getUpcomingEvents($conn, 3);
        }
        if (function_exists('getLatestBlogPosts')) {
            $latest_blog_posts_list = getLatestBlogPosts($conn, 3);
        }

        // Fetch stats (using simple, non-performant queries for this example; cache in production)
        $stats['members'] = $conn->query("SELECT COUNT(id) FROM users WHERE account_status = 'active'")->fetch_row()[0] ?? 0;
        $stats['events'] = $conn->query("SELECT COUNT(id) FROM events WHERE is_published = TRUE")->fetch_row()[0] ?? 0;
        $stats['works'] = $conn->query("SELECT COUNT(id) FROM submissions WHERE status IN ('approved', 'published')")->fetch_row()[0] ?? 0;
    }


    include 'includes/header.php'; 
    ?>

    <section id="hero" class="hero-section">
        <div class="ken-burns-background"></div>
        <div class="hero-overlay"></div>
        <div class="container hero-content" data-aos="fade-in">
            <h1 class="hero-title" data-aos="fade-down" data-aos-delay="300">Where Words Weave Worlds.</h1>
            <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="500">
                Welcome to Geetanjali, the Literary Society of NIFTEM-K. A vibrant community for thinkers, writers, and dreamers to share, create, and inspire.
            </p>
            <div data-aos="fade-up" data-aos-delay="700">
                <a href="<?php echo BASE_URL; ?>events/" class="cta-button">Explore Our Events</a>
            </div>
        </div>
    </section>

    <section id="highlights" class="content-section">
        <div class="container">
            <h2 class="section-title text-center" data-aos="fade-up">Our Pillars</h2>
            <div class="highlight-grid">
                <div class="highlight-item" data-aos="fade-up" data-aos-delay="100">
                    <i class="fas fa-feather-alt fa-3x highlight-icon"></i>
                    <h3>Creative Expression</h3>
                    <p>From poetry slams to short story competitions, we provide a platform for every voice to be heard and every story to be told.</p>
                    <a href="<?php echo BASE_URL; ?>submissions/index.php" class="highlight-link">Submit Your Work</a>
                </div>
                <div class="highlight-item" data-aos="fade-up" data-aos-delay="200">
                    <i class="fas fa-comments fa-3x highlight-icon"></i>
                    <h3>Intellectual Discourse</h3>
                    <p>Engage in stimulating debates, group discussions, and guest lectures that challenge perspectives and broaden horizons.</p>
                    <a href="<?php echo BASE_URL; ?>events/" class="highlight-link">Join a Discussion</a>
                </div>
                <div class="highlight-item" data-aos="fade-up" data-aos-delay="300">
                    <i class="fas fa-users fa-3x highlight-icon"></i>
                    <h3>Community Building</h3>
                    <p>Connect with fellow literature enthusiasts, collaborate on projects, and build lasting friendships in an inclusive environment.</p>
                    <a href="<?php echo BASE_URL; ?>register.php" class="highlight-link">Become a Member</a>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($upcoming_events_list)): ?>
    <section id="upcoming-events" class="content-section alt-background">
        <div class="container">
            <h2 class="section-title text-center" data-aos="fade-up">Upcoming Events</h2>
            <div class="event-preview-grid">
                <?php foreach ($upcoming_events_list as $event): ?>
                <div class="event-card" data-aos="zoom-in-up" data-aos-delay="<?php echo ($index++ * 100); /* Dynamic delay */ ?>">
                    <div class="event-card-image">
                        <a href="<?php echo BASE_URL . 'events/view.php?id=' . $event['id']; ?>">
                            <img src="<?php echo !empty($event['featured_image']) ? BASE_URL . 'uploads/events/' . sanitizeOutput($event['featured_image']) : BASE_URL . 'assets/images/event-placeholder.jpg'; ?>" alt="<?php echo sanitizeOutput($event['title']); ?>">
                        </a>
                        <div class="event-card-date">
                            <span class="day"><?php echo date('d', strtotime($event['date_time'])); ?></span>
                            <span class="month"><?php echo date('M', strtotime($event['date_time'])); ?></span>
                        </div>
                    </div>
                    <div class="event-card-content">
                        <h3 class="event-card-title">
                            <a href="<?php echo BASE_URL . 'events/view.php?id=' . $event['id']; ?>"><?php echo sanitizeOutput($event['title']); ?></a>
                        </h3>
                        <p class="event-card-meta">
                            <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($event['date_time'])); ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo sanitizeOutput($event['venue'] ?? 'TBA'); ?></span>
                        </p>
                        <p class="event-card-excerpt"><?php echo sanitizeOutput(substr(strip_tags($event['description']), 0, 90)) . '...'; ?></p>
                        <a href="<?php echo BASE_URL . 'events/view.php?id=' . $event['id']; ?>" class="cta-button-small">View Details <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
             <div class="section-view-all-link text-center" data-aos="fade-up">
                <a href="<?php echo BASE_URL; ?>events/" class="cta-button btn-primary-outline">View All Events</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section id="impact-stats" class="content-section">
        <div class="container">
            <h2 class="section-title text-center" data-aos="fade-up">Our Impact in Numbers</h2>
            <div class="stats-grid">
                <div class="stat-item" data-aos="fade-up">
                    <i class="fas fa-users"></i>
                    <p><span class="stat-counter" data-target="<?php echo $stats['members']; ?>">0</span>+</p>
                    <h4>Active Members</h4>
                </div>
                <div class="stat-item" data-aos="fade-up" data-aos-delay="150">
                    <i class="fas fa-calendar-check"></i>
                    <p><span class="stat-counter" data-target="<?php echo $stats['events']; ?>">0</span>+</p>
                    <h4>Events Hosted</h4>
                </div>
                <div class="stat-item" data-aos="fade-up" data-aos-delay="300">
                    <i class="fas fa-pen-nib"></i>
                    <p><span class="stat-counter" data-target="<?php echo $stats['works']; ?>">0</span>+</p>
                    <h4>Works Published</h4>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($latest_blog_posts_list)): ?>
    <section id="latest-posts" class="content-section alt-background">
        <div class="container">
            <h2 class="section-title text-center" data-aos="fade-up">From Our Blog</h2>
            <div class="post-preview-grid">
                <?php foreach ($latest_blog_posts_list as $post): ?>
                <div class="post-card" data-aos="fade-left" data-aos-delay="<?php echo ($index++ * 100); ?>">
                     <div class="post-card-image">
                        <a href="<?php echo BASE_URL . 'blog/post.php?slug=' . $post['slug']; ?>">
                            <img src="<?php echo !empty($post['featured_image']) ? BASE_URL . 'uploads/blog/' . sanitizeOutput($post['featured_image']) : BASE_URL . 'assets/images/blog-placeholder.jpg'; ?>" alt="<?php echo sanitizeOutput($post['title']); ?>">
                        </a>
                    </div>
                    <div class="post-card-content">
                        <p class="post-card-meta">
                            By <a href="#"><?php echo sanitizeOutput($post['author_name']); ?></a> on <?php echo date('M j, Y', strtotime($post['published_at'])); ?>
                        </p>
                        <h3 class="post-card-title">
                            <a href="<?php echo BASE_URL . 'blog/post.php?slug=' . $post['slug']; ?>"><?php echo sanitizeOutput($post['title']); ?></a>
                        </h3>
                        <p class="post-card-excerpt"><?php echo sanitizeOutput($post['excerpt']); ?></p>
                        <a href="<?php echo BASE_URL . 'blog/post.php?slug=' . $post['slug']; ?>" class="post-card-readmore">Read More <i class="fas fa-long-arrow-alt-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="section-view-all-link text-center" data-aos="fade-up">
                <a href="<?php echo BASE_URL; ?>blog/" class="cta-button btn-primary-outline">Visit Our Blog</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section id="join-cta" class="content-section">
        <div class="container text-center" data-aos="zoom-in">
            <h2 class="cta-title">Ready to Unleash Your Inner Wordsmith?</h2>
            <p class="cta-subtitle">Join Geetanjali today and become part of a thriving community that celebrates the power of words.</p>
            <a href="<?php echo BASE_URL; ?>register.php" class="cta-button btn-lg">Become a Member</a>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    