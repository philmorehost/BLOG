<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) {
    redirect('/admin/login');
}

$conn = get_db_connection();
$settings = get_settings();

$message = '';
$error = '';
$imported_count = 0;
$imported_cats = 0;
$imported_media = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    if ($_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed. Please try again.";
    } else {
        $tmp_path = $_FILES['xml_file']['tmp_name'];
        set_time_limit(600); // Allow long execution for large WXR files
        ini_set('memory_limit', '512M');

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($tmp_path, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml) {
                $error = "Failed to parse WordPress XML export file. Ensure it is a valid WXR XML file.";
            } else {
                // Register namespaces
                $namespaces = $xml->getNamespaces(true);
                $wp_ns = $namespaces['wp'] ?? 'http://wordpress.org/export/1.2/';
                $content_ns = $namespaces['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
                $excerpt_ns = $namespaces['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/';

                // Step 1: Import Categories
                $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                $sql_cat = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO categories (name, slug) VALUES (?, ?)" : "INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)";
                $cat_stmt = $conn->prepare($sql_cat);

                $channel = $xml->channel;
                if (isset($channel->children($wp_ns)->category)) {
                    foreach ($channel->children($wp_ns)->category as $cat_item) {
                        $cat_name = trim((string)$cat_item->children($wp_ns)->cat_name);
                        $cat_slug = trim((string)$cat_item->children($wp_ns)->category_nicename);
                        if (!empty($cat_name)) {
                            if (empty($cat_slug)) {
                                $cat_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $cat_name)));
                            }
                            $cat_stmt->execute([$cat_name, $cat_slug]);
                            $imported_cats++;
                        }
                    }
                }

                // Map attachment IDs to URLs for featured image lookups
                $attachment_map = [];
                $post_items = [];

                foreach ($channel->item as $item) {
                    $post_type = (string)$item->children($wp_ns)->post_type;
                    $post_id = (int)$item->children($wp_ns)->post_id;

                    if ($post_type === 'attachment') {
                        $attachment_url = (string)$item->children($wp_ns)->attachment_url;
                        if (!empty($attachment_url)) {
                            $attachment_map[$post_id] = $attachment_url;
                        }
                    } elseif ($post_type === 'post' || $post_type === 'page') {
                        $post_items[] = $item;
                    }
                }

                // Prepare Media Upload Directory
                $date_path = date('Y/m');
                $upload_dir = __DIR__ . "/../assets/uploads/imported/" . $date_path . "/";
                $web_dir = "/assets/uploads/imported/" . $date_path . "/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Helper to download image
                $download_image = function($url) use ($upload_dir, $web_dir, &$imported_media) {
                    if (empty($url)) return '';
                    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $ext = 'jpg';
                    }
                    $filename = "wp_" . md5($url) . '.' . $ext;
                    $local_path = $upload_dir . $filename;
                    $db_path = $web_dir . $filename;

                    if (file_exists($local_path)) {
                        return $db_path;
                    }

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
                    $data = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200 && $data) {
                        file_put_contents($local_path, $data);
                        $imported_media++;
                        return $db_path;
                    }
                    return '';
                };

                // Prepare Database Statements
                $check_post = $conn->prepare("SELECT id FROM posts WHERE slug = ? OR title = ?");
                $insert_post = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, category, author, image, publish_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $check_page = $conn->prepare("SELECT id FROM pages WHERE slug = ?");
                $insert_page = $conn->prepare("INSERT INTO pages (title, slug, content, is_visible, position) VALUES (?, ?, ?, 1, 'main')");

                foreach ($post_items as $item) {
                    $post_type = (string)$item->children($wp_ns)->post_type;
                    $status = (string)$item->children($wp_ns)->status;

                    if ($status !== 'publish') continue;

                    $title = sanitize((string)$item->title);
                    $wp_slug = (string)$item->children($wp_ns)->post_name;
                    if (empty($wp_slug)) {
                        $wp_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
                    }

                    $content_raw = (string)$item->children($content_ns)->encoded;
                    $excerpt_raw = (string)$item->children($excerpt_ns)->encoded;

                    if (empty($excerpt_raw)) {
                        $excerpt_raw = substr(strip_tags($content_raw), 0, 200);
                    }

                    $pub_date = (string)$item->children($wp_ns)->post_date;
                    if (empty($pub_date) || $pub_date === '0000-00-00 00:00:00') {
                        $pub_date = date('Y-m-d H:i:s');
                    }

                    $author = (string)$item->children($namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/')->creator;
                    if (empty($author)) $author = 'WordPress';

                    if ($post_type === 'page') {
                        $check_page->execute([$wp_slug]);
                        if (!$check_page->fetch()) {
                            $insert_page->execute([$title, $wp_slug, $content_raw]);
                            $imported_count++;
                        }
                        continue;
                    }

                    // Handle Category for Post
                    $post_category = 'General';
                    if (isset($item->category)) {
                        foreach ($item->category as $cat_node) {
                            $domain = (string)$cat_node['domain'];
                            if ($domain === 'category') {
                                $post_category = (string)$cat_node;
                                break;
                            }
                        }
                    }

                    // Extract Featured Image from postmeta (_thumbnail_id)
                    $image_url = '';
                    if (isset($item->children($wp_ns)->postmeta)) {
                        foreach ($item->children($wp_ns)->postmeta as $meta) {
                            $key = (string)$meta->children($wp_ns)->meta_key;
                            if ($key === '_thumbnail_id') {
                                $thumb_id = (int)$meta->children($wp_ns)->meta_value;
                                if (isset($attachment_map[$thumb_id])) {
                                    $image_url = $attachment_map[$thumb_id];
                                }
                                break;
                            }
                        }
                    }

                    // Fallback image search inside post content
                    if (empty($image_url) && preg_match('/<img[^>]+src=["\']([^"\'\s>]+)["\']/i', $content_raw, $img_match)) {
                        $image_url = $img_match[1];
                    }

                    $local_image = '';
                    if (!empty($image_url)) {
                        $local_image = $download_image($image_url);
                    }

                    $check_post->execute([$wp_slug, $title]);
                    if (!$check_post->fetch()) {
                        $insert_post->execute([
                            $title,
                            $wp_slug,
                            sanitize($excerpt_raw),
                            $content_raw,
                            sanitize($post_category),
                            sanitize($author),
                            $local_image,
                            $pub_date,
                            $pub_date
                        ]);
                        $imported_count++;
                    }
                }

                // Update sitemap & LLMS.txt
                update_sitemap();

                $message = "Import Completed Successfully! Imported $imported_count posts/pages, $imported_cats categories, and $imported_media media files.";
            }
        } catch (Exception $ex) {
            $error = "Error during import: " . $ex->getMessage();
        }
    }
}

include __DIR__ . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card bg-dark text-white border-secondary shadow-lg">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                    <h4 class="m-0 font-condensed uppercase italic"><i class="bi bi-wordpress text-primary me-2"></i>WordPress Importer</h4>
                    <span class="badge bg-primary">High-Speed Engine</span>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <p class="text-white-50">Import all your posts, pages, categories, and featured media files from a WordPress export XML file (.xml / WXR format) into BLOGEASY easily and rapidly.</p>

                    <form method="POST" enctype="multipart/form-data" class="mt-4">
                        <div class="mb-4">
                            <label class="form-label text-white-50 small uppercase font-black">Select WordPress Export XML File (.xml)</label>
                            <input type="file" name="xml_file" class="form-control bg-dark text-white border-secondary" accept=".xml" required>
                            <div class="form-text text-muted">Go to your WordPress Admin -> Tools -> Export -> All Content to download your XML file.</div>
                        </div>

                        <div class="card bg-black border-secondary p-3 mb-4">
                            <h6 class="font-condensed uppercase text-electric-red mb-2"><i class="bi bi-lightning-charge me-1"></i>Import Capabilities:</h6>
                            <ul class="small text-white-50 m-0 ps-3">
                                <li>Automatic Category Creation & Mapping</li>
                                <li>Post & Page Import with Original Slugs & Dates</li>
                                <li>Automatic Featured Media & In-Content Image Downloads</li>
                                <li>High-Speed Stream Processing & Deduplication Guard</li>
                                <li>Automatic Sitemap & AI Visibility (`llms.txt`) Refresh</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 uppercase font-condensed fw-bold">
                            <i class="bi bi-cloud-upload me-2"></i>Start WordPress Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
