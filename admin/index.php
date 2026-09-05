<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

// Consistent Admin Base Detection
$admin_root = '/admin';
$request_uri = $_SERVER['REQUEST_URI'];
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$admin_base = $base_url . $admin_root;

if (!is_admin()) {
    redirect($admin_base . '/login');
}

require_once __DIR__ . '/../includes/social_poster.php';
if (!_sys_verify_token()) {
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <title>License Verification Required</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>body{background:#05070a;color:#fff;font-family:sans-serif;padding-top:100px;}</style>
    </head>
    <body>
    <div class="container text-center col-md-6">
        <div class="card bg-dark border-danger p-5 shadow-lg">
            <div class="display-1 text-danger mb-3">⚠️</div>
            <h2 class="text-white mb-3">Invalid or Expired License</h2>
            <p class="text-white-50">System access is restricted due to an invalid or missing license key for domain <code><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost'); ?></code>.</p>
            <hr class="border-secondary my-4">
            <p class="small text-muted">Please contact support or activate a valid license key for this domain.</p>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$conn = get_db_connection();
$settings = get_settings();

// Security PIN enforcement
if (!empty($settings['pin_enabled'])) {
    if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true ||
        (time() - ($_SESSION['pin_verified_at'] ?? 0)) > 86400) {

        // Don't redirect if already on pin_verify page
        if (strpos($request_uri, $admin_root . '/pin_verify') === false) {
            redirect($admin_base . '/pin_verify');
        }
    }
}

// Extract path after /admin
$path = '/';
$admin_pos = strpos($request_uri, $admin_root);
if ($admin_pos !== false) {
    $after_admin = substr($request_uri, $admin_pos + strlen($admin_root));
    $path = rtrim(strtok($after_admin, '?'), '/');
}
if (empty($path)) $path = '/';

// Layout helper
function admin_header($title = "Dashboard") {
    global $settings, $path, $admin_base;
    include __DIR__ . '/header.php';
}

function admin_footer() {
    include __DIR__ . '/footer.php';
}

// Router Logic
if ($path == '/' || $path == '' || $path == '/posts') {
    include __DIR__ . '/posts.php';
} elseif (strpos($path, '/settings') === 0) {
    include __DIR__ . '/settings.php';
} elseif (strpos($path, '/comments') === 0) {
    include __DIR__ . '/comments.php';
} elseif (strpos($path, '/subscribers') === 0) {
    include __DIR__ . '/subscribers.php';
} elseif (strpos($path, '/categories') === 0) {
    include __DIR__ . '/categories.php';
} elseif (strpos($path, '/pages') === 0) {
    include __DIR__ . '/pages.php';
} elseif (strpos($path, '/import_wordpress') === 0) {
    include __DIR__ . '/import_wordpress.php';
} elseif (strpos($path, '/profile') === 0) {
    include __DIR__ . '/profile.php';
} elseif (strpos($path, '/pin_verify') === 0) {
    include __DIR__ . '/pin_verify.php';
} elseif (strpos($path, '/ajax_suggest') === 0) {
    include __DIR__ . '/ajax_suggest.php';
} elseif (strpos($path, '/logout') === 0) {
    session_destroy();
    redirect('/admin/login');
} else {
    // Fallback or specific file handling
    $file = __DIR__ . $path . '.php';
    if (file_exists($file)) {
        include $file;
    } else {
        redirect('/admin/');
    }
}