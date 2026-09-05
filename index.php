<?php
ob_start();
if (!file_exists(__DIR__ . '/includes/config.php')) {
    header("Location: /install/");
    exit;
}

require_once __DIR__ . '/includes/config.php';

if (defined('INSTALLED') && !INSTALLED) {
    header("Location: /install/");
    exit;
}

require_once __DIR__ . '/includes/db.php';

// Simple Router
$request = $_SERVER['REQUEST_URI'] ?? '/';
$path = rtrim(strtok($request, '?'), '/');

if ($path !== '/install' && strpos($path, '/install/') !== 0) {
    require_once __DIR__ . '/includes/db.php';
    $conn = get_db_connection();
    if (!$conn) {
        header("Location: /install/");
        exit;
    }

    try {
        $count = $conn->query("SELECT COUNT(*) FROM site_settings")->fetchColumn();
        if ($count == 0) {
            header("Location: /install/");
            exit;
        }
    } catch (Exception $e) {
        header("Location: /install/");
        exit;
    }
}

if (empty($path)) $path = '/';

if ($path == '/' || $path == '' || empty($path)) {
    include __DIR__ . '/pages/home.php';
} elseif (preg_match('/^\/post\/([^\/]+)$/', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    include __DIR__ . '/pages/post_detail.php';
} elseif (preg_match('/^\/author\/([^\/]+)$/', $path, $matches)) {
    $_GET['name'] = urldecode($matches[1]);
    include __DIR__ . '/pages/author.php';
} elseif ($path == '/watch') {
    include __DIR__ . '/pages/watch.php';
} elseif ($path == '/betting') {
    include __DIR__ . '/pages/betting.php';
} elseif ($path == '/tables' || $path == '/standings') {
    include __DIR__ . '/pages/tables.php';
} elseif (preg_match('/^\/category\/([^\/]+)$/', $path, $matches)) {
    $cat_identifier = $matches[1];
    // Check if it's a slug or name
    $conn = get_db_connection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT name FROM categories WHERE slug = ? OR name = ?");
        $stmt->execute([$cat_identifier, urldecode($cat_identifier)]);
        $cat = $stmt->fetch();
        if ($cat) {
            $_GET['category'] = $cat['name'];
        } else {
            $_GET['category'] = urldecode($cat_identifier);
        }
    } else {
        $_GET['category'] = urldecode($cat_identifier);
    }
    include __DIR__ . '/pages/home.php';
} elseif ($path == '/subscribe' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $conn = get_db_connection();
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO subscribers (email) VALUES (?)" : "INSERT IGNORE INTO subscribers (email) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        header('Location: /?subscribed=true');
        exit;
    } else {
        header('Location: /?error=invalid_email');
        exit;
    }
} elseif ($path == '/llms.txt') {
    if (!file_exists(__DIR__ . '/llms.txt')) {
        update_llms_txt();
    }
    header('Content-Type: text/plain; charset=utf-8');
    readfile(__DIR__ . '/llms.txt');
    exit;
} elseif ($path == '/llms-full.txt') {
    if (!file_exists(__DIR__ . '/llms-full.txt')) {
        update_llms_txt();
    }
    header('Content-Type: text/plain; charset=utf-8');
    readfile(__DIR__ . '/llms-full.txt');
    exit;
} elseif ($path == '/sitemap.xml') {
    if (!file_exists(__DIR__ . '/sitemap.xml')) {
        update_sitemap();
    }
    header('Content-Type: application/xml; charset=utf-8');
    readfile(__DIR__ . '/sitemap.xml');
    exit;
} elseif ($path == '/admin/login' || $path == '/admin/login.php') {
    include __DIR__ . '/admin/login.php';
} elseif (strpos($path, '/admin') === 0) {
    include __DIR__ . '/admin/index.php';
} elseif ($path == '/install' || strpos($path, '/install/') === 0) {
    include __DIR__ . '/install/index.php';
} else {
    // Check if it's a CMS page slug
    $conn = get_db_connection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT * FROM pages WHERE slug = ? AND is_visible = 1");
        $stmt->execute([trim($path, '/')]);
        $page = $stmt->fetch();
        if ($page) {
            $_GET['page_id'] = $page['id'];
            include __DIR__ . '/pages/cms_page.php';
        } else {
            if (!headers_sent()) http_response_code(404);
            include __DIR__ . '/pages/404.php';
        }
    } else {
        if (!headers_sent()) http_response_code(404);
        include __DIR__ . '/pages/404.php';
    }
}