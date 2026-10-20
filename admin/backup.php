<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) {
    redirect('/admin/login');
}

$conn = get_db_connection();
$message = '';
$error = '';

// Check ZipArchive availability
$zip_available = class_exists('ZipArchive');

// --------------------------------------------------------------------------
// HANDLE EXPORT (DOWNLOAD BACKUP)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    try {
        $export_preset = $_POST['preset'] ?? 'full';
        $export_format = $_POST['format'] ?? 'zip';

        // Content selections
        $inc_posts = ($export_preset === 'custom') ? isset($_POST['inc_posts']) : true;
        $inc_categories = ($export_preset === 'custom') ? isset($_POST['inc_categories']) : true;
        $inc_pages = ($export_preset === 'custom') ? isset($_POST['inc_pages']) : true;
        $inc_comments = ($export_preset === 'custom') ? isset($_POST['inc_comments']) : true;
        $inc_subscribers = ($export_preset === 'custom') ? isset($_POST['inc_subscribers']) : true;
        $inc_settings = ($export_preset === 'custom') ? isset($_POST['inc_settings']) : true;
        $inc_users = ($export_preset === 'custom') ? isset($_POST['inc_users']) : true;
        $inc_media = ($export_preset === 'custom') ? isset($_POST['inc_media']) : ($export_preset === 'full');

        // Gather Database Content based on selection
        $export_data = [
            'version' => '2.0',
            'exported_at' => date('c'),
            'preset' => $export_preset
        ];

        if ($inc_settings) $export_data['site_settings'] = $conn->query("SELECT * FROM site_settings")->fetchAll(PDO::FETCH_ASSOC);
        if ($inc_users) $export_data['users'] = $conn->query("SELECT id, username, password, email, role, created_at FROM users")->fetchAll(PDO::FETCH_ASSOC);
        if ($inc_categories) $export_data['categories'] = $conn->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
        if ($inc_posts) $export_data['posts'] = $conn->query("SELECT * FROM posts")->fetchAll(PDO::FETCH_ASSOC);
        if ($inc_pages) $export_data['pages'] = $conn->query("SELECT * FROM pages")->fetchAll(PDO::FETCH_ASSOC);
        if ($inc_comments) $export_data['comments'] = $conn->query("SELECT * FROM comments")->fetchAll(PDO::FETCH_ASSOC);
        if ($inc_subscribers) $export_data['subscribers'] = $conn->query("SELECT * FROM subscribers")->fetchAll(PDO::FETCH_ASSOC);

        $json_content = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $date_suffix = date('Y-m-d_H-i-s');

        if ($export_format === 'json' || !$zip_available || !$inc_media) {
            // Serve JSON backup
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="blog_backup_' . $date_suffix . '.json"');
            header('Content-Length: ' . strlen($json_content));
            echo $json_content;
            exit;
        } else {
            // Serve ZIP archive with DB JSON and uploads directory files
            $tmp_zip_file = tempnam(sys_get_temp_dir(), 'backup_') . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($tmp_zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $zip->addFromString('backup_data.json', $json_content);

                if ($inc_media) {
                    $upload_paths = [
                        __DIR__ . '/../uploads',
                        __DIR__ . '/../assets/uploads'
                    ];

                    foreach ($upload_paths as $base_dir) {
                        if (is_dir($base_dir)) {
                            $files = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::LEAVES_ONLY
                            );

                            foreach ($files as $file) {
                                if (!$file->isDir()) {
                                    $filePath = $file->getRealPath();
                                    $relativePath = 'media/' . ltrim(substr($filePath, strlen(realpath($base_dir))), '/\\');
                                    $zip->addFile($filePath, $relativePath);
                                }
                            }
                        }
                    }
                }

                $zip->close();

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="blog_full_backup_' . $date_suffix . '.zip"');
                header('Content-Length: ' . filesize($tmp_zip_file));
                readfile($tmp_zip_file);
                @unlink($tmp_zip_file);
                exit;
            } else {
                $error = "Failed to create ZIP archive. Downloading JSON data instead.";
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="blog_backup_' . $date_suffix . '.json"');
                header('Content-Length: ' . strlen($json_content));
                echo $json_content;
                exit;
            }
        }
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// --------------------------------------------------------------------------
// HANDLE RESTORE / IMPORT BACKUP
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (empty($_FILES['backup_file']['tmp_name']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid backup file (.zip or .json).";
    } else {
        $file_tmp = $_FILES['backup_file']['tmp_name'];
        $file_name = $_FILES['backup_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $restore_mode = $_POST['restore_mode'] ?? 'merge'; // 'merge' or 'wipe'
        $overwrite_admin = isset($_POST['overwrite_admin']); // Checkbox for admin details overwrite

        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $json_data = null;

        try {
            if ($file_ext === 'json') {
                $raw_json = file_get_contents($file_tmp);
                $json_data = json_decode($raw_json, true);
            } elseif ($file_ext === 'zip' && $zip_available) {
                $zip = new ZipArchive();
                if ($zip->open($file_tmp) === TRUE) {
                    $json_str = $zip->getFromName('backup_data.json');
                    if ($json_str) {
                        $json_data = json_decode($json_str, true);
                    }

                    // Extract media files to assets/uploads/
                    $target_uploads_dir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($target_uploads_dir)) {
                        mkdir($target_uploads_dir, 0755, true);
                    }

                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        $filename = $stat['name'];

                        if (strpos($filename, 'media/') === 0) {
                            $sub_path = substr($filename, 6); // Strip 'media/'
                            if (!empty($sub_path) && substr($sub_path, -1) !== '/') {
                                $dest = $target_uploads_dir . $sub_path;
                                $dest_dir = dirname($dest);
                                if (!is_dir($dest_dir)) {
                                    mkdir($dest_dir, 0755, true);
                                }
                                copy("zip://" . $file_tmp . "#" . $filename, $dest);
                            }
                        }
                    }
                    $zip->close();
                } else {
                    $error = "Failed to open ZIP backup archive.";
                }
            } else {
                $error = "Unsupported backup format. Please upload a .json or .zip backup file.";
            }

            if (!empty($json_data) && is_array($json_data)) {
                $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

                // Disable foreign key constraints during restore
                if ($driver === 'sqlite') {
                    $conn->exec("PRAGMA foreign_keys = OFF;");
                } else {
                    $conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
                }

                // If Wipe & Restore mode is selected, truncate/clear tables
                if ($restore_mode === 'wipe') {
                    $conn->exec("DELETE FROM comments;");
                    $conn->exec("DELETE FROM posts;");
                    $conn->exec("DELETE FROM categories;");
                    $conn->exec("DELETE FROM pages;");
                    $conn->exec("DELETE FROM subscribers;");
                    if ($overwrite_admin) {
                        $conn->exec("DELETE FROM users;");
                    }
                }

                $imported_counts = ['categories' => 0, 'posts' => 0, 'pages' => 0, 'comments' => 0, 'subscribers' => 0, 'users' => 0];

                // 1. Restore Admin Users (if overwrite checkbox is checked)
                if ($overwrite_admin && !empty($json_data['users']) && is_array($json_data['users'])) {
                    $stmt_u = $conn->prepare("INSERT INTO users (username, password, email, role, created_at) VALUES (?, ?, ?, ?, ?)");
                    $check_u = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");

                    foreach ($json_data['users'] as $u) {
                        if (empty($u['username']) || empty($u['password'])) continue;

                        $check_u->execute([$u['username'], $u['email'] ?? '']);
                        if (!$check_u->fetch() || $restore_mode === 'wipe') {
                            $stmt_u->execute([
                                $u['username'],
                                $u['password'],
                                $u['email'] ?? ($u['username'] . '@localhost'),
                                $u['role'] ?? 'admin',
                                $u['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $imported_counts['users']++;
                        }
                    }
                }

                // 2. Restore Categories
                if (!empty($json_data['categories']) && is_array($json_data['categories'])) {
                    $sql_cat = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO categories (name, slug) VALUES (?, ?)" : "INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)";
                    $stmt_cat = $conn->prepare($sql_cat);
                    foreach ($json_data['categories'] as $cat) {
                        $name = $cat['name'] ?? '';
                        $slug = $cat['slug'] ?? '';
                        if (!empty($name)) {
                            if (empty($slug)) $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                            $stmt_cat->execute([$name, $slug]);
                            $imported_counts['categories']++;
                        }
                    }
                }

                // 3. Restore Posts
                if (!empty($json_data['posts']) && is_array($json_data['posts'])) {
                    $check_post = $conn->prepare("SELECT id FROM posts WHERE slug = ? OR title = ?");
                    $stmt_post = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, category, author, image, video_url, source_url, is_top_story, is_scheduled, publish_date, tags, meta_title, meta_description, meta_keywords, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($json_data['posts'] as $p) {
                        $title = $p['title'] ?? '';
                        $slug = $p['slug'] ?? '';
                        if (empty($title)) continue;
                        if (empty($slug)) $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

                        $check_post->execute([$slug, $title]);
                        if (!$check_post->fetch() || $restore_mode === 'wipe') {
                            $stmt_post->execute([
                                $title,
                                $slug,
                                $p['excerpt'] ?? '',
                                $p['content'] ?? '',
                                $p['category'] ?? 'General',
                                $p['author'] ?? 'Admin',
                                $p['image'] ?? '',
                                $p['video_url'] ?? '',
                                $p['source_url'] ?? '',
                                $p['is_top_story'] ?? 0,
                                $p['is_scheduled'] ?? 0,
                                $p['publish_date'] ?? date('Y-m-d H:i:s'),
                                $p['tags'] ?? '',
                                $p['meta_title'] ?? '',
                                $p['meta_description'] ?? '',
                                $p['meta_keywords'] ?? '',
                                $p['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $imported_counts['posts']++;
                        }
                    }
                }

                // 4. Restore Pages
                if (!empty($json_data['pages']) && is_array($json_data['pages'])) {
                    $check_page = $conn->prepare("SELECT id FROM pages WHERE slug = ?");
                    $stmt_page = $conn->prepare("INSERT INTO pages (title, slug, content, is_external, external_url, is_visible, position, meta_title, meta_description, meta_keywords, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($json_data['pages'] as $pg) {
                        $title = $pg['title'] ?? '';
                        $slug = $pg['slug'] ?? '';
                        if (empty($title)) continue;
                        if (empty($slug)) $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

                        $check_page->execute([$slug]);
                        if (!$check_page->fetch() || $restore_mode === 'wipe') {
                            $stmt_page->execute([
                                $title,
                                $slug,
                                $pg['content'] ?? '',
                                $pg['is_external'] ?? 0,
                                $pg['external_url'] ?? '',
                                $pg['is_visible'] ?? 1,
                                $pg['position'] ?? 'main',
                                $pg['meta_title'] ?? '',
                                $pg['meta_description'] ?? '',
                                $pg['meta_keywords'] ?? '',
                                $pg['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $imported_counts['pages']++;
                        }
                    }
                }

                // 5. Restore Comments
                if (!empty($json_data['comments']) && is_array($json_data['comments'])) {
                    $stmt_cmt = $conn->prepare("INSERT INTO comments (post_id, author, text, status, created_at) VALUES (?, ?, ?, ?, ?)");
                    foreach ($json_data['comments'] as $c) {
                        if (!empty($c['post_id']) && !empty($c['text'])) {
                            $stmt_cmt->execute([
                                $c['post_id'],
                                $c['author'] ?? 'Anonymous',
                                $c['text'],
                                $c['status'] ?? 'approved',
                                $c['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $imported_counts['comments']++;
                        }
                    }
                }

                // 6. Restore Subscribers
                if (!empty($json_data['subscribers']) && is_array($json_data['subscribers'])) {
                    $sql_sub = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO subscribers (email, created_at) VALUES (?, ?)" : "INSERT IGNORE INTO subscribers (email, created_at) VALUES (?, ?)";
                    $stmt_sub = $conn->prepare($sql_sub);
                    foreach ($json_data['subscribers'] as $s) {
                        if (!empty($s['email'])) {
                            $stmt_sub->execute([
                                $s['email'],
                                $s['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $imported_counts['subscribers']++;
                        }
                    }
                }

                // 7. Restore Settings (Update site_settings if provided)
                if (!empty($json_data['site_settings'][0]) && is_array($json_data['site_settings'][0])) {
                    $sett = $json_data['site_settings'][0];
                    unset($sett['id']); // Do not overwrite primary key ID
                    $fields = [];
                    $values = [];
                    foreach ($sett as $col => $val) {
                        if ($val !== null) {
                            $fields[] = "{$col} = ?";
                            $values[] = $val;
                        }
                    }
                    if (!empty($fields)) {
                        $sql_sett = "UPDATE site_settings SET " . implode(', ', $fields) . " WHERE id = 1";
                        $stmt_sett = $conn->prepare($sql_sett);
                        $stmt_sett->execute($values);
                    }
                }

                // Re-enable foreign key constraints
                if ($driver === 'sqlite') {
                    $conn->exec("PRAGMA foreign_keys = ON;");
                } else {
                    $conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }

                update_sitemap();

                $admin_msg = $overwrite_admin ? " (including {$imported_counts['users']} admin accounts)" : " (admin login credentials preserved unchanged)";
                $message = "Website Restore Completed Successfully! Restored: " .
                    "{$imported_counts['posts']} posts, " .
                    "{$imported_counts['pages']} pages, " .
                    "{$imported_counts['categories']} categories, " .
                    "{$imported_counts['comments']} comments, " .
                    "and {$imported_counts['subscribers']} subscribers" . $admin_msg . ".";
            } else {
                if (empty($error)) {
                    $error = "Invalid or empty backup data. Could not restore site content.";
                }
            }
        } catch (Exception $e) {
            $error = "Restore failed: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-white font-condensed uppercase italic m-0"><i class="bi bi-box-seam text-danger me-2"></i>Backup & Restore Center</h2>
                    <p class="text-white-50 small m-0 mt-1">Full-featured backup engine to export and restore posts, categories, pages, media uploads, site settings, and admin user credentials.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-lg border-0 bg-success text-white">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i><?php echo $message; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-lg border-0 bg-danger text-white">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i><?php echo $error; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- EXPORT CARD -->
                <div class="col-md-6">
                    <div class="card bg-dark text-white border-secondary h-100 shadow-lg">
                        <div class="card-header border-secondary bg-black py-3">
                            <h5 class="m-0 font-condensed uppercase italic text-danger"><i class="bi bi-download me-2"></i>1. Export Website Backup</h5>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <form method="POST">
                                <input type="hidden" name="action" value="export">

                                <div class="mb-3">
                                    <label class="form-label text-white-50 small uppercase font-black">Backup Scope & Preset</label>
                                    <select name="preset" id="presetSelect" class="form-select bg-dark text-white border-secondary py-2" onchange="toggleCustomExportOptions()">
                                        <option value="full" selected>Full Website Backup (Database + Uploaded Media)</option>
                                        <option value="db_only">Database Only (All Tables as JSON)</option>
                                        <option value="custom">Custom Selection (Choose Specific Items)</option>
                                    </select>
                                </div>

                                <!-- Custom Selection Box (Hidden by default) -->
                                <div id="customExportOptions" class="card bg-black border-secondary p-3 mb-3 d-none">
                                    <h6 class="font-condensed uppercase text-primary small font-bold mb-3"><i class="bi bi-check2-square me-1"></i>Select Content To Include:</h6>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_posts" value="1" id="chk_posts" checked>
                                                <label class="form-check-label text-white small" for="chk_posts">Posts & Excerpts</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_categories" value="1" id="chk_cats" checked>
                                                <label class="form-check-label text-white small" for="chk_cats">Categories</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_pages" value="1" id="chk_pages" checked>
                                                <label class="form-check-label text-white small" for="chk_pages">CMS Pages</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_comments" value="1" id="chk_cmts" checked>
                                                <label class="form-check-label text-white small" for="chk_cmts">Comments</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_subscribers" value="1" id="chk_subs" checked>
                                                <label class="form-check-label text-white small" for="chk_subs">Subscribers</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_settings" value="1" id="chk_settings" checked>
                                                <label class="form-check-label text-white small" for="chk_settings">Site Settings</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_users" value="1" id="chk_users" checked>
                                                <label class="form-check-label text-white small" for="chk_users">Admin Credentials</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="inc_media" value="1" id="chk_media" checked>
                                                <label class="form-check-label text-white small" for="chk_media">Media Files</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-white-50 small uppercase font-black">Export Format</label>
                                    <select name="format" class="form-select bg-dark text-white border-secondary py-2">
                                        <option value="zip" <?php echo $zip_available ? 'selected' : ''; ?>>Full Archive (.zip) - Includes Media Files</option>
                                        <option value="json">Database JSON Only (.json)</option>
                                    </select>
                                    <?php if (!$zip_available): ?>
                                        <div class="form-text text-warning mt-1"><i class="bi bi-info-circle me-1"></i>ZipArchive extension not enabled. Defaulting to JSON.</div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-danger w-100 py-3 uppercase font-condensed fw-bold">
                                    <i class="bi bi-download me-2"></i>Download Site Backup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- RESTORE CARD -->
                <div class="col-md-6">
                    <div class="card bg-dark text-white border-secondary h-100 shadow-lg">
                        <div class="card-header border-secondary bg-black py-3">
                            <h5 class="m-0 font-condensed uppercase italic text-primary"><i class="bi bi-upload me-2"></i>2. Restore / Import Backup</h5>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="restore">

                                <div class="mb-3">
                                    <label class="form-label text-white-50 small uppercase font-black">Select Backup File (.zip or .json)</label>
                                    <input type="file" name="backup_file" class="form-control bg-dark text-white border-secondary" accept=".zip,.json" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-white-50 small uppercase font-black">Restore Strategy</label>
                                    <select name="restore_mode" class="form-select bg-dark text-white border-secondary py-2">
                                        <option value="merge" selected>Merge / Append (Skip existing duplicate titles/slugs)</option>
                                        <option value="wipe">Wipe Existing Content & Fresh Restore</option>
                                    </select>
                                </div>

                                <!-- Admin Credentials Overwrite Warning & Control -->
                                <div class="card bg-black border-warning p-3 mb-4">
                                    <h6 class="font-condensed uppercase text-warning mb-2"><i class="bi bi-shield-lock-fill me-1"></i>Admin Credentials Protection</h6>
                                    <p class="small text-white-50 mb-2">Backups include administrator login credentials. By default, existing admin accounts remain unchanged so you don't lock yourself out.</p>

                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="overwrite_admin" id="chk_overwrite_admin" value="1">
                                        <label class="form-check-label text-warning font-bold small" for="chk_overwrite_admin">
                                            Overwrite Existing Admin Credentials with Backup Users
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-3 uppercase font-condensed fw-bold" onclick="return confirm('Are you sure you want to proceed with the restore process?');">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>Start Website Restore
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
function toggleCustomExportOptions() {
    const select = document.getElementById('presetSelect');
    const customBox = document.getElementById('customExportOptions');
    if (select.value === 'custom') {
        customBox.classList.remove('d-none');
    } else {
        customBox.classList.add('d-none');
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
