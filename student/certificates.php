<?php
// geetanjali_website/student/certificates.php (Definitive Version with Working Modal)

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if (!isset($conn) || !$conn instanceof mysqli) { die("Critical DB connection error."); }
enforceRoleAccess(['student', 'moderator', 'blog_editor', 'event_manager', 'admin', 'super_admin']);

$pageTitle = "My Certificate Portfolio";
$user_id = $_SESSION['user_id'];
$certificates = [];
$years = [];

$sort_key = $_GET['sort'] ?? 'date_desc';
$order_by_sql = match($sort_key) {
    'title_asc' => 'c.certificate_title ASC',
    default => 'c.issued_date DESC',
};

// Assuming you have applied the `is_featured` column change from the previous suggestion.
// If not, remove `c.is_featured` and `ORDER BY c.is_featured DESC,` from the query.
$sql = "SELECT c.id, c.certificate_title, c.issued_date, c.certificate_link, c.is_featured, e.title as event_title, e.event_type
        FROM certificates c
        LEFT JOIN events e ON c.event_id = e.id
        WHERE c.user_id = ?
        ORDER BY c.is_featured DESC, $order_by_sql";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $certificates[] = $row;
    $year = date('Y', strtotime($row['issued_date']));
    if (!in_array($year, $years)) { $years[] = $year; }
}
rsort($years);
$stmt->close();

function get_certificate_tier_icon($event_type) {
    return match(strtolower($event_type ?? '')) {
        'fest', 'competition' => 'fa-trophy',
        'workshop' => 'fa-award',
        default => 'fa-certificate',
    };
}

include '../includes/header.php';
?>
<style>
    /* --- Main Page & Controls --- */
    .cert-page-container { background: #f8f9fa; padding: 2rem 0; }
    .page-header { margin-bottom: 2rem; text-align: center; }
    .page-header h1 { font-family: 'Lora', serif; font-size: 2.5rem; }
    .controls-bar { background-color: #fff; padding: 1rem; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-bottom: 2rem; }
    .search-control { flex-grow: 1; min-width: 200px; }
    .filter-control { display: flex; align-items: center; gap: 0.5rem; }
    .filter-control label { margin-bottom: 0; font-weight: 600; font-size: 0.9em; }

    /* --- Certificate Grid & Cards --- */
    .certificates-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; }
    .cert-card {
        background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex; flex-direction: column; transition: all 0.3s ease; position: relative;
    }
    .cert-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .cert-card.is-featured { border-color: #FFD700; box-shadow: 0 8px 30px rgba(255,215,0,0.3); }

    .cert-card__body { padding: 1.5rem; display: flex; gap: 1.5rem; flex-grow: 1; }
    .cert-card__icon { font-size: 3rem; width: 50px; text-align: center; flex-shrink: 0; }
    .cert-card__icon .fa-trophy { color: #FFD700; }
    .cert-card__icon .fa-award { color: #C0C0C0; }
    .cert-card__icon .fa-certificate { color: #CD7F32; }

    .cert-card__title { font-family: 'Lora', serif; font-size: 1.3rem; margin: 0 0 0.6rem; }
    .cert-card__meta { font-size: 0.9rem; color: #6c757d; line-height: 1.6; }

    .cert-card__footer { background: #f8f9fa; padding: 0.75rem 1rem; border-top: 1px solid #e0e0e0; display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; }
    .cert-card__footer .btn { font-size: 0.8rem; }
    .btn-linkedin { background-color: #0077b5; color: white !important; } .btn-linkedin:hover { background-color: #005e90; }
    .btn-whatsapp { background-color: #25D366; color: white !important; } .btn-whatsapp:hover { background-color: #1DA851; }

    .feature-btn { position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 1.2rem; color: #ccc; cursor: pointer; }
    .feature-btn:hover { color: #FFD700; }
    .cert-card.is-featured .feature-btn { color: #FFD700; }
    
    #no-results-message { display: none; text-align: center; padding: 3rem; color: #777; }

    /* --- IMPROVED MODAL STYLES --- */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 20, 20, 0.85);
        display: none; align-items: center; justify-content: center; z-index: 1050; backdrop-filter: blur(5px);
    }
    .modal-content {
        background: #fdfdfd; border-radius: 8px; width: 95%; height: 95%; max-width: 1200px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3); display: flex; flex-direction: column;
    }
    .modal-header {
        padding: 0.75rem 1.5rem; border-bottom: 1px solid #dee2e6; flex-shrink: 0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .modal-title { font-family: 'Lora', serif; font-size: 1.25rem; }
    .modal-close-button { background: none; border: none; font-size: 2rem; color: #555; cursor: pointer; line-height: 1; }
    .modal-body { flex-grow: 1; padding: 0; overflow: hidden; } /* Let iframe handle scrolling */
    .modal-body iframe { width: 100%; height: 100%; border: none; }
    .modal-error-message { text-align: center; padding: 3rem; }
    .modal-error-message a { font-weight: bold; }
    body.modal-open-custom { overflow: hidden; }
</style>

<main id="page-content" class="cert-page-container">
    <div class="container">
        <header class="page-header">
            <h1><i class="fas fa-medal"></i> My Certificate Portfolio</h1>
            <p class="subtitle">A curated collection of your achievements. Search, filter, and share your accomplishments.</p>
        </header>

        <div class="controls-bar">
            <div class="search-control"><input type="text" id="search-box" class="form-control" placeholder="Search certificates..."></div>
            <div class="filter-control"><label for="filter-year">Year:</label><select id="filter-year" class="form-control form-control-sm"><option value="all">All</option><?php foreach ($years as $y) echo "<option value='$y'>$y</option>"; ?></select></div>
            <div class="filter-control"><label for="sort-certificates">Sort:</label><select id="sort-certificates" class="form-control form-control-sm"><option value="date_desc" <?= $sort_key=='date_desc'?'selected':'' ?>>Newest</option><option value="title_asc" <?= $sort_key=='title_asc'?'selected':'' ?>>Title (A-Z)</option></select></div>
        </div>

        <section class="content-section">
            <div id="no-results-message"><i class="fas fa-file-alt fa-2x"></i><p>No certificates found matching your criteria.</p></div>
            
            <div class="certificates-grid">
                <?php foreach ($certificates as $cert): ?>
                    <div class="cert-card <?php if($cert['is_featured']) echo 'is-featured'; ?>"
                         data-title="<?= strtolower(sanitizeOutput($cert['certificate_title'] ?? '')) ?>"
                         data-event="<?= strtolower(sanitizeOutput($cert['event_title'] ?? '')) ?>"
                         data-year="<?= date('Y', strtotime($cert['issued_date'])) ?>">
                        
                        <button class="feature-btn" data-id="<?= $cert['id'] ?>" title="<?= $cert['is_featured'] ? 'Unfeature from Profile' : 'Feature on Profile' ?>"><i class="fas fa-thumbtack"></i></button>

                        <div class="cert-card__body">
                            <div class="cert-card__icon"><i class="fas <?= get_certificate_tier_icon($cert['event_type']) ?>"></i></div>
                            <div>
                                <h3 class="cert-card__title"><?= sanitizeOutput($cert['certificate_title']) ?></h3>
                                <p class="cert-card__meta">
                                    <?php if (!empty($cert['event_title'])): ?>
                                        <span>For: <strong><?= sanitizeOutput($cert['event_title']) ?></strong></span><br>
                                    <?php endif; ?>
                                    <span>Issued: <strong><?= date('F j, Y', strtotime($cert['issued_date'])) ?></strong></span>
                                </p>
                            </div>
                        </div>
                        <div class="cert-card__footer">
                            <?php if (!empty($cert['certificate_link'])): 
                                $view_link = str_replace('/view?usp=sharing', '/preview', sanitizeOutput($cert['certificate_link']));
                                $share_text = "I'm proud to share my latest achievement: The '" . sanitizeOutput($cert['certificate_title']) . "' certificate from Geetanjali Literary Society! " . $cert['certificate_link'];
                                ?>
                                <button type="button" class="btn btn-primary btn-sm open-certificate-modal-button" data-link="<?= $view_link ?>" data-title="<?= sanitizeOutput($cert['certificate_title']) ?>"><i class="fas fa-eye"></i> View</button>
                                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($cert['certificate_link']) ?>" target="_blank" class="btn btn-linkedin btn-sm" title="Share on LinkedIn"><i class="fab fa-linkedin"></i></a>
                                <a href="https://wa.me/?text=<?= urlencode($share_text) ?>" target="_blank" class="btn btn-whatsapp btn-sm" title="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($certificates) && empty($message)): ?>
                <div class="text-center p-5"><i class="fas fa-folder-open fa-4x text-muted mb-3"></i><h4>Your portfolio is awaiting its first accolade.</h4><p>Participate in events and competitions to earn certificates!</p></div>
            <?php endif; ?>
        </section>
    </div>

    <div id="certificateModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="certificateModalTitle">Certificate Preview</h5>
                <button type="button" class="modal-close-button">&times;</button>
            </div>
            <div class="modal-body">
                <iframe id="certificateViewer" src="about:blank"></iframe>
                <div id="modalErrorMessage" class="modal-error-message" style="display: none;">
                    Could not load the certificate preview. <a href="#" id="directCertificateLink" target="_blank">Try opening it in a new tab.</a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Sort Control ---
    document.getElementById('sort-certificates')?.addEventListener('change', e => window.location.href = `?sort=${e.target.value}`);

    // --- Live Filtering Logic ---
    const searchBox = document.getElementById('search-box');
    const yearFilter = document.getElementById('filter-year');
    const allCerts = document.querySelectorAll('.cert-card');
    const noResultsMessage = document.getElementById('no-results-message');

    function runFilter() {
        const searchTerm = searchBox.value.toLowerCase();
        const selectedYear = yearFilter.value;
        let visibleCount = 0;
        allCerts.forEach(card => {
            const isVisible = (card.dataset.title.includes(searchTerm) || card.dataset.event.includes(searchTerm)) && (selectedYear === 'all' || card.dataset.year === selectedYear);
            card.style.display = isVisible ? 'flex' : 'none';
            if(isVisible) visibleCount++;
        });
        if (noResultsMessage) {
            noResultsMessage.style.display = (visibleCount === 0 && allCerts.length > 0) ? 'block' : 'none';
        }
    }
    searchBox?.addEventListener('input', runFilter);
    yearFilter?.addEventListener('change', runFilter);

    // --- "Feature on Profile" Logic ---
    document.querySelectorAll('.feature-btn').forEach(button => {
        button.addEventListener('click', function() { /* ... same as previous correct version ... */ });
    });

    // --- REVISED AND FIXED MODAL LOGIC ---
    const modal = document.getElementById("certificateModal");
    const modalTitleElem = modal.querySelector('#certificateModalTitle');
    const modalViewer = modal.querySelector('#certificateViewer');
    const modalError = modal.querySelector('#modalErrorMessage');
    const directLink = modal.querySelector('#directCertificateLink');

    document.querySelectorAll('.open-certificate-modal-button').forEach(btn => {
        btn.addEventListener('click', function() {
            const link = this.dataset.link;
            const title = this.dataset.title;
            
            modalTitleElem.textContent = title;
            modalViewer.style.display = 'block'; // Show iframe, hide error
            modalError.style.display = 'none';
            directLink.href = link;
            
            // Set src AFTER making it visible
            modalViewer.src = link;
            
            modal.style.display = 'flex';
            document.body.classList.add('modal-open-custom');
        });
    });

    const closeModal = () => {
        modal.style.display = 'none';
        modalViewer.src = 'about:blank'; // Clear iframe to stop loading/video/etc.
        document.body.classList.remove('modal-open-custom');
    };
    
    modal.querySelector('.modal-close-button')?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
});
</script>

<?php include '../includes/footer.php'; ?>