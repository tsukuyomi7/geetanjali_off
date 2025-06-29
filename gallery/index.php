<?php
// geetanjali_website/public/gallery.php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Constants for file paths
define('GALLERY_UPLOAD_DIR', './Uploads/gallery/');
define('THUMBNAIL_UPLOAD_DIR', './Uploads/gallery/thumbs/');

$pageTitle = "Image Gallery";

// Fetch gallery images
$items_per_page = (int)getSetting($conn, 'public_items_per_page', 12);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$total_images_sql = "SELECT COUNT(id) as total FROM gallery";
$stmt_total = $conn->query($total_images_sql);
$total_gallery_items = $stmt_total ? (int)$stmt_total->fetch_assoc()['total'] : 0;

$total_pages = ($items_per_page > 0 && $total_gallery_items > 0) ? ceil($total_gallery_items / $items_per_page) : 1;
if ($current_page > $total_pages) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

$gallery_items = [];
$sql_list_gallery = "SELECT g.id, g.file_path, g.caption, g.upload_date, g.event_id, e.title as event_title 
                    FROM gallery g 
                    LEFT JOIN events e ON g.event_id = e.id 
                    ORDER BY g.upload_date DESC LIMIT ? OFFSET ?";
$stmt_list_gallery = $conn->prepare($sql_list_gallery);
if ($stmt_list_gallery) {
    $stmt_list_gallery->bind_param("ii", $items_per_page, $offset);
    if ($stmt_list_gallery->execute()) {
        $res_list_gallery = $stmt_list_gallery->get_result();
        while ($row = $res_list_gallery->fetch_assoc()) {
            $gallery_items[] = $row;
        }
    } else {
        error_log("Gallery fetch execute error: " . $stmt_list_gallery->error);
    }
    $stmt_list_gallery->close();
} else {
    error_log("Gallery fetch prepare error: " . $conn->error);
}

include '../includes/header.php';
?>

<style>
.gallery-container {
    max-width: 100%;
    padding: 20px 0;
}
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    padding: 0 15px;
}
.gallery-item {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.gallery-item:hover {
    transform: scale(1.02);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}
.gallery-item img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    display: block;
}
.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: #fff;
    padding: 15px;
    text-align: center;
}
.gallery-item:hover .gallery-overlay {
    opacity: 1;
}
.gallery-overlay p {
    margin: 5px 0;
    font-size: 1em;
    font-weight: 300;
}
.event-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #28a745;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.9em;
    font-weight: 500;
}
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 30px;
    gap: 10px;
}
.pagination a {
    padding: 10px 15px;
    background: #007bff;
    color: white;
    border-radius: 5px;
    text-decoration: none;
    transition: background 0.3s ease;
}
.pagination a:hover {
    background: #0056b3;
}
.pagination a.disabled {
    background: #ccc;
    pointer-events: none;
}
.no-results {
    text-align: center;
    font-size: 1.2em;
    color: #666;
    padding: 50px 0;
}
@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    .gallery-item img {
        height: 180px;
    }
    .gallery-overlay p {
        font-size: 0.9em;
    }
}
@media (max-width: 480px) {
    .gallery-grid {
        grid-template-columns: 1fr;
    }
    .gallery-item img {
        height: 200px;
    }
}
</style>

<main id="page-content" class="gallery-container">
    <header class="page-title-section">
        <h1><i class="fas fa-images"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
        <p class="subtitle">Explore our collection of images from various events.</p>
    </header>

    <div class="gallery-grid">
        <?php if (!empty($gallery_items)): ?>
            <?php foreach ($gallery_items as $item): ?>
                <?php
                $image_path = file_exists(THUMBNAIL_UPLOAD_DIR . 'thumb_' . $item['file_path'])
                    ? BASE_URL . THUMBNAIL_UPLOAD_DIR . 'thumb_' . sanitizeOutput($item['file_path'])
                    : BASE_URL . GALLERY_UPLOAD_DIR . sanitizeOutput($item['file_path']);
                if (!file_exists(str_replace(BASE_URL, '../', $image_path))) {
                    error_log("Missing gallery image: " . $image_path);
                    continue; // Skip if image is missing
                }
                ?>
                <div class="gallery-item">
                    <a href="<?php echo BASE_URL . GALLERY_UPLOAD_DIR . sanitizeOutput($item['file_path']); ?>" data-lightbox="gallery" data-title="<?php echo sanitizeOutput($item['caption'] ?? ''); ?>">
                        <img src="<?php echo $image_path; ?>" alt="<?php echo sanitizeOutput($item['caption'] ?? 'Gallery Image'); ?>" loading="lazy">
                        <?php if ($item['event_id']): ?>
                            <span class="event-badge">Event: <?php echo sanitizeOutput($item['event_title']); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="gallery-overlay">
                        <p><?php echo sanitizeOutput($item['caption'] ? substr($item['caption'], 0, 100) . '...' : 'No Caption'); ?></p>
                        <p><?php echo date('M j, Y', strtotime($item['upload_date'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-results">No images found in the gallery.</p>
        <?php endif; ?>
    </div>

    <?php
    $pagination_query_params = $_GET;
    unset($pagination_query_params['page']);
    echo generatePaginationLinks($current_page, $total_pages, BASE_URL . 'public/gallery.php', $pagination_query_params);
    ?>
</main>

<?php include '../includes/footer.php'; ?>