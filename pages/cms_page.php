<?php
require_once __DIR__ . '/../includes/functions.php';
$page_id = $_GET['page_id'] ?? null;
$conn = get_db_connection();
$page = null;
if ($conn && $page_id) {
    $stmt = $conn->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch();
}

if (!$page) {
    redirect('/404');
}

if ($page['is_external'] && !empty($page['external_url'])) {
    redirect($page['external_url']);
}

// Set dynamic meta tags for header
$custom_meta_title = !empty($page['meta_title']) ? $page['meta_title'] : $page['title'];
$custom_meta_description = !empty($page['meta_description']) ? $page['meta_description'] : '';
$custom_meta_keywords = !empty($page['meta_keywords']) ? $page['meta_keywords'] : '';

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5 px-4">
    <h1 class="font-condensed fw-black italic text-white display-5 display-md-3 mb-5 border-bottom border-white border-opacity-10 pb-3 uppercase"><?php echo $page['title']; ?></h1>
    <div class="bg-[#0a0e17] p-4 p-md-5 rounded-4 border border-white border-opacity-5 shadow-2xl min-vh-60 overflow-x-hidden">
        <div class="markdown-content text-white opacity-90 fs-5 leading-relaxed">
            <?php echo parse_markdown($page['content']); ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php';