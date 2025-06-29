<?php
// This will be at the top of header.php, but good to remember it's needed.
// if (session_status() == PHP_SESSION_NONE) { session_start(); }
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = "About Geetanjali";
// $additional_css[] = 'css/about-page.css'; // If you create specific CSS for this page

include 'includes/header.php'; // Defines BASE_URL, session_start, outputs header and navbar
?>

<main id="page-content">
    <section class="page-title-section">
        <div class="container">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="subtitle">The Literary Soul of NIFTEM-Kundli: Our Journey, Mission, and Vision.</p>
        </div>
    </section>

    <section class="container content-section" data-aos="fade-up">
        <div class="about-intro">
            <figure class="about-figure align-right">
                <img src="<?php echo BASE_URL; ?>images/placeholder-about-image.jpg" alt="Geetanjali Society Members or Event" class="img-fluid rounded shadow">
                <figcaption>A glimpse from one of our vibrant literary gatherings.</figcaption>
            </figure>
            <h2>Our Genesis</h2>
            <p>Geetanjali, The Literary Society of the National Institute of Food Technology Entrepreneurship and Management (NIFTEM), Kundli, was conceived from a shared passion for literature and the expressive arts. In an institution renowned for its focus on science and technology, Geetanjali serves as a vital hub for nurturing creativity, fostering critical thinking, and celebrating the diverse tapestry of human expression through words.</p>
            <p>We aim to provide a stimulating environment where students can explore literary worlds, hone their writing and oratorical skills, and engage in meaningful dialogues that transcend academic disciplines.</p>
        </div>
    </section>

    <section class="container content-section alt-background" data-aos="fade-left">
        <div class="row-flex-spaced">
            <div class="col-flex-md-6">
                <h3><i class="fas fa-bullseye icon-left"></i>Our Mission</h3>
                <p>To cultivate a dynamic literary community within NIFTEM-K by organizing engaging events, workshops, and discussions. We strive to encourage reading, creative writing, and effective communication, providing a platform for students to showcase their talents and appreciate diverse literary forms.</p>
            </div>
            <div class="col-flex-md-6">
                <h3><i class="fas fa-eye icon-left"></i>Our Vision</h3>
                <p>To be recognized as a cornerstone of NIFTEM-K’s cultural landscape, inspiring a lifelong love for literature and the arts. We envision Geetanjali as a catalyst for intellectual curiosity, creative innovation, and the development of well-rounded individuals equipped with strong communication and analytical skills.</p>
            </div>
        </div>
    </section>
    
    <section class="container content-section" data-aos="fade-right">
        <h2>What We Do</h2>
        <ul class="styled-list tick-list">
            <li>Organize literary competitions: Debates, poetry slams, short story writing, essay contests.</li>
            <li>Conduct workshops on creative writing, public speaking, and literary analysis.</li>
            <li>Host open mic sessions for poetry, storytelling, and stand-up comedy.</li>
            <li>Arrange guest lectures and interactive sessions with authors, poets, and literary scholars.</li>
            <li>Publish an annual literary magazine/e-zine showcasing members' creative works.</li>
            <li>Facilitate book clubs and reading groups to explore diverse genres and authors.</li>
            <li>Screen literary-themed films and documentaries followed by discussions.</li>
        </ul>
    </section>

    <section id="team-section" class="container content-section alt-background" data-aos="fade-up">
        <h2>Meet the Core Committee (2024-2025 Placeholder)</h2>
        <p class="text-center lead-text">Geetanjali is driven by the enthusiasm and dedication of its student members and faculty advisors.</p>
        <div class="team-grid-about">
            <?php
            // Placeholder for team members - In future, this data would come from a database
            $team_members = [
                ['name' => 'Prof. (Dr.) Faculty Advisor', 'position' => 'Faculty Coordinator', 'image' => BASE_URL . 'images/team/placeholder-faculty.jpg', 'bio' => 'Guiding the society with experience and wisdom.'],
                ['name' => 'Rohan Sharma (Example)', 'position' => 'President', 'image' => BASE_URL . 'images/team/placeholder-male.jpg', 'bio' => 'Leads the society with vision and passion for literature.'],
                ['name' => 'Priya Singh (Example)', 'position' => 'Vice-President', 'image' => BASE_URL . 'images/team/placeholder-female.jpg', 'bio' => 'Supports all society functions and member engagement.'],
                ['name' => 'Amit Kumar (Example)', 'position' => 'Secretary', 'image' => BASE_URL . 'images/team/placeholder-male.jpg', 'bio' => 'Manages communications and administrative tasks efficiently.'],
                // Add more members as needed (e.g., Treasurer, Event Coordinators, Editors)
            ];

            foreach ($team_members as $member): ?>
                <div class="team-member-card">
                    <img src="<?php echo htmlspecialchars($member['image']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" class="team-member-photo">
                    <h3><?php echo htmlspecialchars($member['name']); ?></h3>
                    <p class="team-member-position"><?php echo htmlspecialchars($member['position']); ?></p>
                    <p class="team-member-bio-short"><?php echo htmlspecialchars($member['bio']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center" style="margin-top: 2rem;">
            <a href="<?php echo BASE_URL; ?>contact.php#team-enquiry" class="cta-button cta-button-outline">Become Part of the Team</a>
        </p>
    </section>

</main>

<?php include 'includes/footer.php'; ?>