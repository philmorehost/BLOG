<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $settings['name'] ?? 'Football Intelligence'; ?> | SYSTEM CONTROL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Barlow+Condensed:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #05070a; color: #fff; font-family: 'Inter', sans-serif; }
        .font-condensed { font-family: 'Barlow Condensed'; text-transform: uppercase; }
        .sidebar { background: #0a0e17; border-right: 1px solid rgba(255,255,255,0.05); min-height: 100vh; }
        .nav-link { color: #64748b; font-weight: 900; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; padding: 15px 25px; border-left: 3px solid transparent; }
        .nav-link.active { color: #ff3e3e; border-left-color: #ff3e3e; background: rgba(255,62,62,0.05); }
        .nav-link:hover { color: #fff; }

        /* Improved Readability & Sharpness */
        body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; }
        .bg-white, .modal-content, .alert-light { color: #000 !important; }
        .bg-white h1, .bg-white h2, .bg-white h3, .bg-white p { color: #000 !important; }
        .text-sharp { text-shadow: 0 0 1px rgba(255,255,255,0.1); }

        /* Fix Tailwind/Bootstrap .collapse conflict */
        .collapse.show { visibility: visible !important; }
    </style>
    <?php if (!empty($settings['header_code'])): ?>
        <?php echo $settings['header_code']; ?>
    <?php endif; ?>
</head>
<body>
<!-- Mobile Header -->
<div class="d-md-none bg-black border-bottom border-white border-opacity-10 p-3 sticky-top" style="z-index: 1050;">
    <div class="d-flex justify-content-between align-items-center">
        <a href="/" class="font-condensed italic fw-black text-white text-decoration-none fs-4">
            <?php if (!empty($settings['logo'])): ?>
                <img src="<?php echo $settings['logo']; ?>" alt="Logo" style="max-height: 65px;">
            <?php else: ?>
                <?php echo format_site_title($settings['name'] ?? 'FootballIntelligence', 'text-danger'); ?>
            <?php endif; ?>
        </a>
        <button class="btn btn-outline-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar">
            <i class="bi bi-list fs-3"></i>
        </button>
    </div>
    <div class="collapse mt-3" id="adminSidebar">
        <nav class="nav flex-column bg-[#0a0e17] rounded-3 border border-white border-opacity-5">
            <a class="nav-link <?php echo ($path == '' || $path == '/') ? 'active' : ''; ?>" href="/admin/"><i class="bi bi-grid-fill me-3"></i> POST REGISTRY</a>
            <a class="nav-link <?php echo $path == '/comments' ? 'active' : ''; ?>" href="/admin/comments"><i class="bi bi-chat-dots-fill me-3"></i> COMMENTS</a>
            <a class="nav-link <?php echo $path == '/subscribers' ? 'active' : ''; ?>" href="/admin/subscribers"><i class="bi bi-people-fill me-3"></i> SUBSCRIBERS</a>
            <a class="nav-link <?php echo $path == '/categories' ? 'active' : ''; ?>" href="/admin/categories"><i class="bi bi-tags-fill me-3"></i> CATEGORIES</a>
            <a class="nav-link <?php echo $path == '/pages' ? 'active' : ''; ?>" href="/admin/pages"><i class="bi bi-file-earmark-text-fill me-3"></i> CMS PAGES</a>
            <a class="nav-link <?php echo $path == '/settings' ? 'active' : ''; ?>" href="/admin/settings"><i class="bi bi-sliders me-3"></i> SETTINGS</a>
            <a class="nav-link <?php echo $path == '/profile' ? 'active' : ''; ?>" href="/admin/profile"><i class="bi bi-person-badge-fill me-3"></i> PROFILE</a>
            <hr class="border-white border-opacity-5 mx-4 my-2">
            <a class="nav-link text-danger" href="/admin/logout"><i class="bi bi-power me-3"></i> LOGOUT</a>
        </nav>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 px-0 sidebar d-none d-md-block position-fixed h-full bg-[#0a0e17] border-r border-white/5" style="z-index: 1000;">
            <div class="p-4 mb-4">
                <a href="/" class="font-condensed italic fw-black text-white text-decoration-none fs-4">
                    <?php if (!empty($settings['logo'])): ?>
                        <img src="<?php echo $settings['logo']; ?>" alt="Logo" style="max-height: 90px;">
                    <?php else: ?>
                        <?php echo format_site_title($settings['name'] ?? 'FootballIntelligence', 'text-danger'); ?>
                    <?php endif; ?>
                </a>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link <?php echo ($path == '' || $path == '/' || $path == '/posts') ? 'active' : ''; ?>" href="<?php echo $admin_base; ?>/"><i class="bi bi-grid-fill me-3"></i> POST REGISTRY</a>
                <a class="nav-link <?php echo strpos($path, '/comments') === 0 ? 'active' : ''; ?>" href="<?php echo $admin_base; ?>/comments"><i class="bi bi-chat-dots-fill me-3"></i> COMMENTS</a>
                <a class="nav-link <?php echo strpos($path, '/subscribers') === 0 ? 'active' : ''; ?>" href="<?php echo $admin_base; ?>/subscribers"><i class="bi bi-people-fill me-3"></i> SUBSCRIBERS</a>
                <a class="nav-link <?php echo strpos($path, '/categories') === 0 ? 'active' : ''; ?>" href="<?php echo $admin_base; ?>/categories"><i class="bi bi-tags-fill me-3"></i> CATEGORIES</a>
                <a class="nav-link <?php echo strpos($path, '/pages') === 0 ? 'active' : ''; ?>" href="<?php echo $admin_base; ?>/pages"><i class="bi bi-file-earmark-text-fill me-3"></i> CMS PAGES</a>
                <a class="nav-link <?php echo strpos($path, '/settings') === 0 ? 'active' : ''; ?>" href="<?php echo $admin_base; ?>/settings"><i class="bi bi-sliders me-3"></i> SETTINGS</a>
                <a class="nav-link <?php echo strpos($path, '/profile') === 0 ? 'active' : ''; ?>" href="<?php echo $admin_base; ?>/profile"><i class="bi bi-person-badge-fill me-3"></i> PROFILE</a>
                <hr class="border-white border-opacity-5 mx-4 my-4">
                <a class="nav-link text-danger" href="<?php echo $admin_base; ?>/logout"><i class="bi bi-power me-3"></i> LOGOUT</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-12 col-md-9 col-lg-10 offset-md-3 offset-lg-2 px-4 py-5 min-vh-100">