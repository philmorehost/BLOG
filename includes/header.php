<?php
require_once __DIR__ . '/functions.php';
$settings = get_settings();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($custom_meta_title) ? $custom_meta_title : htmlspecialchars(get_site_name($settings)) . ' | ' . htmlspecialchars($settings['tagline'] ?? 'News and Insights'); ?></title>
    <?php if (isset($custom_meta_description)): ?>
    <meta name="description" content="<?php echo $custom_meta_description; ?>">
    <?php endif; ?>
    <?php if (isset($custom_meta_keywords)): ?>
    <meta name="keywords" content="<?php echo $custom_meta_keywords; ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
    <?php if (!empty($settings['favicon'])): ?>
    <link rel="shortcut icon" href="<?php echo $settings['favicon']; ?>" type="image/x-icon">
    <?php endif; ?>
    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(version_image_url('/assets/css/site.css')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
      :root {
        --pitch-dark: #05070a;
        --electric-red: #ff3e3e;
      }

      html, body {
        width: 100%;
        overflow-x: hidden;
        font-family: 'Inter', sans-serif;
        background-color: var(--pitch-dark);
        color: #fff;
      }

      /* Stable media sizing: prevents intrinsic image dimensions from
         stretching cards or rendering only a partial image during loading. */
      img { display: block; max-width: 100%; }
      img.object-fit-cover { width: 100%; height: 100%; object-fit: cover; object-position: center; }
      /* Contained images must keep their intrinsic height unless their parent
         explicitly provides a fixed media box. */
      img.object-fit-contain { display: block; width: 100%; height: auto !important; max-width: 100%; object-fit: contain; object-position: center; }
      .responsive-media { width: 100%; height: auto; aspect-ratio: 16 / 9; object-fit: cover; }
      .hero-media { min-height: 360px; }
      #cookie-consent { z-index: 9999 !important; pointer-events: auto; }
      #cookie-consent button { position: relative; z-index: 1; pointer-events: auto; }
      #cookie-consent.cookie-consent-banner { transform: translateY(110%); visibility: hidden; transition: transform .35s ease, visibility .35s ease; }
      #cookie-consent.cookie-consent-banner.is-visible { transform: translateY(0); visibility: visible; }
      @media (max-width: 767.98px) {
        .hero-media { min-height: 300px; }
        .container-fluid { max-width: 100%; }
        .navbar-brand { max-width: 72vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      }

      h1, h2, h3, h4, h5, h6, .font-condensed {
        font-family: 'Barlow Condensed', sans-serif;
        text-transform: uppercase;
        letter-spacing: -0.01em;
      }

      .text-electric-red { color: var(--electric-red); }
      .bg-electric-red { background-color: var(--electric-red); }
      .border-electric-red { border-color: var(--electric-red); }

      /* Custom Scrollbar */
      ::-webkit-scrollbar { width: 6px; height: 6px; }
      ::-webkit-scrollbar-track { background: #0a0e17; }
      ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
      ::-webkit-scrollbar-thumb:hover { background: var(--electric-red); }

      .no-scrollbar::-webkit-scrollbar { display: none; }
      .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

      .card { background-color: #0a0e17; border: 1px solid rgba(255,255,255,0.05); }
      .btn-primary { background-color: var(--electric-red); border-color: var(--electric-red); }
      .btn-primary:hover { background-color: #d32f2f; border-color: #d32f2f; }

      /* Improved Readability & Sharpness */
      body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; }
      .bg-white, .bg-white *, .alert-light, .alert-light *, .scoreaxis-widget, .scoreaxis-widget * { color: initial !important; }
      .bg-white, .bg-white p, .bg-white h1, .bg-white h2, .bg-white h3, .bg-white span { color: #000 !important; }
      .alert { border-radius: 15px; border-opacity: 0.2; }

      /* Fix Tailwind/Bootstrap .collapse conflict */
      .navbar-collapse.collapse { visibility: visible !important; }

    </style>
    <?php if (!empty($settings['header_code'])): ?>
        <?php echo $settings['header_code']; ?>
    <?php endif; ?>
    <?php if (isset($header_code)): ?>
        <?php echo $header_code; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Main Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-black border-bottom border-white border-opacity-10 py-2 sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-condensed fw-black italic tracking-tighter fs-3 me-4" href="/">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?php echo htmlspecialchars(version_image_url($settings['logo'])); ?>" alt="<?php echo htmlspecialchars(get_site_name($settings)); ?> logo" style="max-height: 80px; width: auto; height: auto;" class="d-inline-block align-middle">
                <?php else: ?>
                    <?php echo format_site_title(get_site_name($settings)); ?>
                <?php endif; ?>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav font-condensed fw-bold uppercase small italic align-items-lg-center me-auto">
                    <?php $current_path = $_SERVER['REQUEST_URI']; ?>
                    <li class="nav-item"><a class="nav-link px-2 <?php echo ($current_path == '/' || $current_path == '/index.php') ? 'active text-electric-red' : ''; ?>" href="/" style="color: #fff;">Home</a></li>

                    <?php
                    $conn = get_db_connection();
                    if ($conn) {
                        $pages = $conn->query("SELECT title, slug, is_external, external_url FROM pages WHERE is_visible = 1 AND position = 'main'")->fetchAll();
                        foreach ($pages as $p) {
                            $url = $p['is_external'] ? $p['external_url'] : '/'.$p['slug'];
                            $active = ($current_path == '/'.$p['slug']) ? 'active text-electric-red' : '';
                            echo '<li class="nav-item"><a class="nav-link px-2 '.$active.'" href="'.$url.'" style="color: #fff;">'.$p['title'].'</a></li>';
                        }
                    }
                    ?>
                    <?php if (!empty($settings['enable_live_feed'])): ?>
                    <li class="nav-item"><a class="nav-link px-2 <?php echo ($current_path == '/watch') ? 'active text-electric-red' : ''; ?>" href="/watch" style="color: #fff;">Live Feed</a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['enable_standings'])): ?>
                    <li class="nav-item"><a class="nav-link px-2 <?php echo ($current_path == '/tables' || $current_path == '/standings') ? 'active text-electric-red' : ''; ?>" href="/tables" style="color: #fff;">Standings</a></li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav font-condensed fw-bold uppercase small align-items-lg-center no-scrollbar flex-nowrap overflow-x-auto">
                    <?php
                    $categories = get_categories_with_counts();
                    foreach ($categories as $c) {
                        $cat_identifier = !empty($c['slug']) ? $c['slug'] : urlencode($c['name']);
                        $cat_url = '/category/' . $cat_identifier;
                        $active = (strpos($current_path, '/category/'.$cat_identifier) !== false) ? 'active text-electric-red' : '';
                        echo '<li class="nav-item">
                                <a class="nav-link px-2 '.$active.'" href="'.$cat_url.'" style="color: rgba(255,255,255,0.7);">
                                    '.$c['name'].' <span class="post-count" style="font-size: 8px; background: rgba(255,62,62,0.1); color: #ff3e3e; padding: 1px 4px; border-radius: 3px;">'.$c['post_count'].'</span>
                                </a>
                              </li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
    </nav>
    <main>
