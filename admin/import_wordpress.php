<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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

// Prepare Media Upload Directory Helper
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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

// --------------------------------------------------------------------------
// OPTION A: REMOTE REST API IMPORTER
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_method']) && $_POST['import_method'] === 'remote_api') {
    set_time_limit(600);
    ini_set('memory_limit', '512M');

    $site_url = trim($_POST['remote_url'] ?? '');
    $username = trim($_POST['remote_user'] ?? '');
    $password = trim($_POST['remote_pass'] ?? '');

    if (empty($site_url)) {
        $error = "Please enter a valid WordPress site URL.";
    } else {
        $site_url = rtrim($site_url, '/');
        if (!preg_match('~^https?://~i', $site_url)) {
            $site_url = 'https://' . $site_url;
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ];

        if (!empty($username) && !empty($password)) {
            $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
        }

        $fetch_api = function($endpoint) use ($headers) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300 && $response) {
                return json_decode($response, true);
            }
            return null;
        };

        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Step 1: Import Categories via REST API
            $cat_endpoint = $site_url . '/wp-json/wp/v2/categories?per_page=100';
            $categories_data = $fetch_api($cat_endpoint);

            $sql_cat = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO categories (name, slug) VALUES (?, ?)" : "INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)";
            $cat_stmt = $conn->prepare($sql_cat);

            $cat_map = []; // [id => name]
            if (is_array($categories_data)) {
                foreach ($categories_data as $cat) {
                    $c_id = $cat['id'] ?? 0;
                    $c_name = html_entity_decode(trim($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $c_slug = trim($cat['slug'] ?? '');
                    if (!empty($c_name)) {
                        if (empty($c_slug)) {
                            $c_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $c_name)));
                        }
                        $cat_stmt->execute([$c_name, $c_slug]);
                        $imported_cats++;
                        $cat_map[$c_id] = $c_name;
                    }
                }
            }

            // Step 2: Prepare DB Statements
            $check_post = $conn->prepare("SELECT id FROM posts WHERE slug = ? OR title = ?");
            $insert_post = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, category, author, image, publish_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $check_page = $conn->prepare("SELECT id FROM pages WHERE slug = ?");
            $insert_page = $conn->prepare("INSERT INTO pages (title, slug, content, is_visible, position) VALUES (?, ?, ?, 1, 'main')");

            // Step 3: Fetch Posts with Embeds
            $page_num = 1;
            $max_pages = 10; // Fetch up to 1,000 posts

            while ($page_num <= $max_pages) {
                $posts_endpoint = $site_url . "/wp-json/wp/v2/posts?per_page=100&page={$page_num}&_embed";
                $posts_data = $fetch_api($posts_endpoint);

                if (!is_array($posts_data) || empty($posts_data)) {
                    break;
                }

                foreach ($posts_data as $p) {
                    $title = html_entity_decode(trim($p['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $slug = trim($p['slug'] ?? '');
                    if (empty($slug)) {
                        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
                    }

                    $content_raw = $p['content']['rendered'] ?? '';
                    $excerpt_raw = html_entity_decode(strip_tags($p['excerpt']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8');
                    if (empty($excerpt_raw)) {
                        $excerpt_raw = substr(strip_tags($content_raw), 0, 200);
                    }

                    $pub_date = date('Y-m-d H:i:s', strtotime($p['date'] ?? 'now'));

                    // Extract Author
                    $author = 'WordPress';
                    if (isset($p['_embedded']['author'][0]['name'])) {
                        $author = html_entity_decode($p['_embedded']['author'][0]['name'], ENT_QUOTES, 'UTF-8');
                    }

                    // Extract Category
                    $post_category = 'General';
                    if (isset($p['categories'][0]) && isset($cat_map[$p['categories'][0]])) {
                        $post_category = $cat_map[$p['categories'][0]];
                    } elseif (isset($p['_embedded']['wp:term'][0][0]['name'])) {
                        $post_category = html_entity_decode($p['_embedded']['wp:term'][0][0]['name'], ENT_QUOTES, 'UTF-8');
                    }

                    // Extract Featured Image
                    $image_url = '';
                    if (isset($p['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                        $image_url = $p['_embedded']['wp:featuredmedia'][0]['source_url'];
                    }

                    if (empty($image_url) && preg_match('/<img[^>]+src=["\']([^"\'\s>]+)["\']/i', $content_raw, $img_match)) {
                        $image_url = $img_match[1];
                    }

                    $local_image = '';
                    if (!empty($image_url)) {
                        $local_image = $download_image($image_url);
                    }

                    $check_post->execute([$slug, $title]);
                    if (!$check_post->fetch()) {
                        $insert_post->execute([
                            $title,
                            $slug,
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

                $page_num++;
            }

            // Step 4: Fetch Pages
            $pages_endpoint = $site_url . '/wp-json/wp/v2/pages?per_page=100&_embed';
            $pages_data = $fetch_api($pages_endpoint);

            if (is_array($pages_data)) {
                foreach ($pages_data as $pg) {
                    $title = html_entity_decode(trim($pg['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $slug = trim($pg['slug'] ?? '');
                    if (empty($slug)) {
                        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
                    }
                    $content_raw = $pg['content']['rendered'] ?? '';

                    $check_page->execute([$slug]);
                    if (!$check_page->fetch()) {
                        $insert_page->execute([$title, $slug, $content_raw]);
                        $imported_count++;
                    }
                }
            }

            update_sitemap();
            $message = "Direct Remote Import Completed! Successfully imported $imported_count posts/pages, $imported_cats categories, and $imported_media media files from $site_url.";

        } catch (Exception $ex) {
            $error = "Error during remote import: " . $ex->getMessage();
        }
    }
}

// --------------------------------------------------------------------------
// OPTION B: WXR XML FILE UPLOAD IMPORTER
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    if ($_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed. Please try again.";
    } else {
        $tmp_path = $_FILES['xml_file']['tmp_name'];
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($tmp_path, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml) {
                $error = "Failed to parse WordPress XML export file. Ensure it is a valid WXR XML file.";
            } else {
                $namespaces = $xml->getNamespaces(true);
                $wp_ns = $namespaces['wp'] ?? 'http://wordpress.org/export/1.2/';
                $content_ns = $namespaces['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
                $excerpt_ns = $namespaces['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/';

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

                update_sitemap();
                $message = "XML File Import Completed! Successfully imported $imported_count posts/pages, $imported_cats categories, and $imported_media media files.";
            }
        } catch (Exception $ex) {
            $error = "Error during XML import: " . $ex->getMessage();
        }
    }
}

include __DIR__ . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-9 mx-auto">
            <div class="card bg-dark text-white border-secondary shadow-lg">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center py-3">
                    <h4 class="m-0 font-condensed uppercase italic"><i class="bi bi-wordpress text-primary me-2"></i>WordPress Importer Engine</h4>
                    <span class="badge bg-primary px-3 py-2">REST API & XML Dual Engine</span>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <p class="text-white-50 mb-4">Seamlessly import all posts, pages, categories, authors, and featured media files into BLOGEASY either directly via WordPress Website URL or via WXR XML export files.</p>

                    <!-- Navigation Tabs -->
                    <ul class="nav nav-pills nav-fill bg-black p-2 border border-secondary rounded mb-4" id="importTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active font-condensed uppercase text-white fw-bold" id="url-tab" data-bs-toggle="tab" data-bs-target="#url-tab-pane" type="button" role="tab">
                                <i class="bi bi-globe me-2 text-primary"></i>1. Direct Website URL Import (REST API)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-condensed uppercase text-white fw-bold" id="xml-tab" data-bs-toggle="tab" data-bs-target="#xml-tab-pane" type="button" role="tab">
                                <i class="bi bi-file-earmark-code me-2 text-primary"></i>2. WXR XML File Upload
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="importTabContent">
                        <!-- TAB 1: REMOTE WEBSITE URL -->
                        <div class="tab-pane fade show active" id="url-tab-pane" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="import_method" value="remote_api">
                                <div class="mb-3">
                                    <label class="form-label text-white-50 small uppercase font-black">WordPress Website URL</label>
                                    <input type="url" name="remote_url" class="form-control bg-dark text-white border-secondary py-2" placeholder="https://example.com" required value="<?php echo htmlspecialchars($_POST['remote_url'] ?? ''); ?>">
                                    <div class="form-text text-muted">Enter the public address of your WordPress website.</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-white-50 small uppercase font-black">Admin Username / Email <span class="text-muted fw-normal">(Optional for private posts)</span></label>
                                        <input type="text" name="remote_user" class="form-control bg-dark text-white border-secondary py-2" placeholder="admin" value="<?php echo htmlspecialchars($_POST['remote_user'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-white-50 small uppercase font-black">Admin Password / App Password <span class="text-muted fw-normal">(Optional)</span></label>
                                        <input type="password" name="remote_pass" class="form-control bg-dark text-white border-secondary py-2" placeholder="Application Password or Password">
                                    </div>
                                </div>

                                <div class="card bg-black border-secondary p-3 mb-4">
                                    <h6 class="font-condensed uppercase text-electric-red mb-2"><i class="bi bi-cpu me-1"></i>Direct REST API Engine Features:</h6>
                                    <ul class="small text-white-50 m-0 ps-3">
                                        <li>Fetches posts, pages, and categories dynamically via WordPress REST API (`/wp-json/wp/v2/`).</li>
                                        <li>Supports both public sites and credential-authenticated private sites.</li>
                                        <li>Automatically downloads featured media & inline post images to local server storage.</li>
                                        <li>Prevents duplicates by automatically checking post slugs and titles.</li>
                                    </ul>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-3 uppercase font-condensed fw-bold">
                                    <i class="bi bi-cloud-download me-2"></i>Start Direct URL Import
                                </button>
                            </form>
                        </div>

                        <!-- TAB 2: WXR XML FILE UPLOAD -->
                        <div class="tab-pane fade" id="xml-tab-pane" role="tabpanel">
                            <form id="xmlImportForm" method="POST" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label class="form-label text-white-50 small uppercase font-black">Select WordPress Export XML File (.xml)</label>
                                    <input type="file" name="xml_file" id="xmlFileInput" class="form-control bg-dark text-white border-secondary" accept=".xml" required>
                                    <div class="form-text text-muted">Go to your WordPress Admin -> Tools -> Export -> All Content to download your XML export file.</div>
                                </div>

                                <!-- Real-time Upload Progress Bar -->
                                <div id="uploadProgressContainer" class="d-none mb-4">
                                    <div class="d-flex justify-content-between text-white-50 small mb-1">
                                        <span id="uploadStatusText" class="fw-bold font-condensed uppercase text-primary"><i class="bi bi-cloud-arrow-up me-1"></i>Uploading XML File...</span>
                                        <span id="uploadPercentText" class="fw-bold font-monospace">0%</span>
                                    </div>
                                    <div class="progress bg-black border border-secondary" style="height: 18px; border-radius: 9px;">
                                        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
                                    </div>
                                </div>

                                <div class="card bg-black border-secondary p-3 mb-4">
                                    <h6 class="font-condensed uppercase text-electric-red mb-2"><i class="bi bi-file-earmark-arrow-up me-1"></i>WXR XML Import Capabilities:</h6>
                                    <ul class="small text-white-50 m-0 ps-3">
                                        <li>Parses standard WordPress WXR (.xml) files.</li>
                                        <li>Imports posts, pages, categories, and tags.</li>
                                        <li>Extracts thumbnail attachments and downloads featured media.</li>
                                    </ul>
                                </div>

                                <button type="submit" id="xmlSubmitBtn" class="btn btn-primary w-100 py-3 uppercase font-condensed fw-bold">
                                    <i class="bi bi-cloud-upload me-2"></i>Start XML File Import
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const xmlForm = document.getElementById('xmlImportForm');
    if (xmlForm) {
        xmlForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('xmlFileInput');
            if (!fileInput.files || fileInput.files.length === 0) return;

            e.preventDefault();
            const formData = new FormData(xmlForm);
            const xhr = new XMLHttpRequest();

            const progressContainer = document.getElementById('uploadProgressContainer');
            const progressBar = document.getElementById('uploadProgressBar');
            const percentText = document.getElementById('uploadPercentText');
            const statusText = document.getElementById('uploadStatusText');
            const submitBtn = document.getElementById('xmlSubmitBtn');

            progressContainer.classList.remove('d-none');
            submitBtn.disabled = true;

            xhr.upload.addEventListener('progress', function(event) {
                if (event.lengthComputable) {
                    const percent = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percent + '%';
                    percentText.textContent = percent + '%';
                    if (percent === 100) {
                        statusText.innerHTML = '<i class="bi bi-gear-wide-connected me-1 spinner-border spinner-border-sm"></i> File Uploaded! Processing and Importing XML Content...';
                        progressBar.classList.add('bg-success');
                    }
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    // Replace body content with server response
                    document.open();
                    document.write(xhr.responseText);
                    document.close();
                } else {
                    alert('An error occurred during file upload. Please try again.');
                    submitBtn.disabled = false;
                }
            });

            xhr.addEventListener('error', function() {
                alert('Upload failed due to a network error.');
                submitBtn.disabled = false;
            });

            xhr.open('POST', window.location.href, true);
            xhr.send(formData);
        });
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
