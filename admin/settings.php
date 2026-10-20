<?php
admin_header("Settings");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['save_general']) || isset($_POST['save_sections']))) {
    $name = sanitize($_POST['name'] ?? $settings['name']);
    $tagline = sanitize($_POST['tagline'] ?? $settings['tagline']);
    $admin_email = sanitize($_POST['admin_email'] ?? $settings['admin_email']);
    $whatsapp = sanitize($_POST['whatsapp_number'] ?? $settings['whatsapp_number']);
    $theme = sanitize($_POST['theme'] ?? $settings['theme'] ?? 'news');

    $section_priority_title = sanitize($_POST['section_priority_title'] ?? 'Priority Intelligence');
    $section_latest_title = sanitize($_POST['section_latest_title'] ?? 'Latest Intelligence');
    $section_football_title = sanitize($_POST['section_football_title'] ?? 'Football News');
    $section_transfer_title = sanitize($_POST['section_transfer_title'] ?? 'Transfer Intelligence');
    $section_third_title = sanitize($_POST['section_third_title'] ?? 'Featured Reports');
    $section_fourth_title = sanitize($_POST['section_fourth_title'] ?? 'Analysis & Insights');

    $section_primary_cat = sanitize($_POST['section_primary_cat'] ?? '');
    $section_secondary_cat = sanitize($_POST['section_secondary_cat'] ?? '');
    $section_third_cat = sanitize($_POST['section_third_cat'] ?? '');
    $section_fourth_cat = sanitize($_POST['section_fourth_cat'] ?? '');

    $featured_cat1_name = sanitize($_POST['featured_cat1_name'] ?? '');
    $featured_cat1_title = sanitize($_POST['featured_cat1_title'] ?? '');
    $featured_cat1_image = sanitize($_POST['featured_cat1_image_url'] ?? ($settings['featured_cat1_image'] ?? ''));

    $featured_cat2_name = sanitize($_POST['featured_cat2_name'] ?? '');
    $featured_cat2_title = sanitize($_POST['featured_cat2_title'] ?? '');
    $featured_cat2_image = sanitize($_POST['featured_cat2_image_url'] ?? ($settings['featured_cat2_image'] ?? ''));

    $featured_cat3_name = sanitize($_POST['featured_cat3_name'] ?? '');
    $featured_cat3_title = sanitize($_POST['featured_cat3_title'] ?? '');
    $featured_cat3_image = sanitize($_POST['featured_cat3_image_url'] ?? ($settings['featured_cat3_image'] ?? ''));

    $featured_cat4_name = sanitize($_POST['featured_cat4_name'] ?? '');
    $featured_cat4_title = sanitize($_POST['featured_cat4_title'] ?? '');
    $featured_cat4_image = sanitize($_POST['featured_cat4_image_url'] ?? ($settings['featured_cat4_image'] ?? ''));

    for ($i = 1; $i <= 4; $i++) {
        if (isset($_FILES["featured_cat{$i}_file"]) && !empty($_FILES["featured_cat{$i}_file"]['name'])) {
            $uploaded = upload_image($_FILES["featured_cat{$i}_file"]);
            if ($uploaded) {
                ${"featured_cat{$i}_image"} = $uploaded;
            }
        }
    }

    // Banner Section 1 inputs
    $banner_sec1_title = sanitize($_POST['banner_sec1_title'] ?? ($settings['banner_sec1_title'] ?? ''));
    $banner_sec1_text = sanitize($_POST['banner_sec1_text'] ?? ($settings['banner_sec1_text'] ?? ''));
    $banner_sec1_image = sanitize($_POST['banner_sec1_image_url'] ?? ($settings['banner_sec1_image'] ?? ''));
    if (isset($_FILES['banner_sec1_file']) && !empty($_FILES['banner_sec1_file']['name'])) {
        $uploaded = upload_image($_FILES['banner_sec1_file']);
        if ($uploaded) $banner_sec1_image = $uploaded;
    }
    $banner_sec1_btn1_text = sanitize($_POST['banner_sec1_btn1_text'] ?? ($settings['banner_sec1_btn1_text'] ?? ''));
    $banner_sec1_btn1_url = sanitize($_POST['banner_sec1_btn1_url'] ?? ($settings['banner_sec1_btn1_url'] ?? ''));
    $banner_sec1_btn2_text = sanitize($_POST['banner_sec1_btn2_text'] ?? ($settings['banner_sec1_btn2_text'] ?? ''));
    $banner_sec1_btn2_url = sanitize($_POST['banner_sec1_btn2_url'] ?? ($settings['banner_sec1_btn2_url'] ?? ''));

    // Banner Section 2 inputs
    $banner_sec2_title = sanitize($_POST['banner_sec2_title'] ?? ($settings['banner_sec2_title'] ?? ''));
    $banner_sec2_text = sanitize($_POST['banner_sec2_text'] ?? ($settings['banner_sec2_text'] ?? ''));
    $banner_sec2_image = sanitize($_POST['banner_sec2_image_url'] ?? ($settings['banner_sec2_image'] ?? ''));
    if (isset($_FILES['banner_sec2_file']) && !empty($_FILES['banner_sec2_file']['name'])) {
        $uploaded = upload_image($_FILES['banner_sec2_file']);
        if ($uploaded) $banner_sec2_image = $uploaded;
    }
    $banner_sec2_btn1_text = sanitize($_POST['banner_sec2_btn1_text'] ?? ($settings['banner_sec2_btn1_text'] ?? ''));
    $banner_sec2_btn1_url = sanitize($_POST['banner_sec2_btn1_url'] ?? ($settings['banner_sec2_btn1_url'] ?? ''));
    $banner_sec2_btn2_text = sanitize($_POST['banner_sec2_btn2_text'] ?? ($settings['banner_sec2_btn2_text'] ?? ''));
    $banner_sec2_btn2_url = sanitize($_POST['banner_sec2_btn2_url'] ?? ($settings['banner_sec2_btn2_url'] ?? ''));

    $enable_live_feed = isset($_POST['enable_live_feed']) ? 1 : 0;
    $enable_standings = isset($_POST['enable_standings']) ? 1 : 0;

    $header_code = $_POST['header_code'] ?? $settings['header_code'];
    $footer_code = $_POST['footer_code'] ?? $settings['footer_code'];
    $footer_about = sanitize($_POST['footer_about'] ?? ($settings['footer_about'] ?? ''));

    $stmt = $conn->prepare("UPDATE site_settings SET name = ?, tagline = ?, admin_email = ?, whatsapp_number = ?, header_code = ?, footer_code = ?, footer_about = ?, theme = ?, section_priority_title = ?, section_latest_title = ?, section_football_title = ?, section_transfer_title = ?, section_third_title = ?, section_fourth_title = ?, section_primary_cat = ?, section_secondary_cat = ?, section_third_cat = ?, section_fourth_cat = ?, featured_cat1_name = ?, featured_cat1_title = ?, featured_cat1_image = ?, featured_cat2_name = ?, featured_cat2_title = ?, featured_cat2_image = ?, featured_cat3_name = ?, featured_cat3_title = ?, featured_cat3_image = ?, featured_cat4_name = ?, featured_cat4_title = ?, featured_cat4_image = ?, enable_live_feed = ?, enable_standings = ?, banner_sec1_title = ?, banner_sec1_text = ?, banner_sec1_image = ?, banner_sec1_btn1_text = ?, banner_sec1_btn1_url = ?, banner_sec1_btn2_text = ?, banner_sec1_btn2_url = ?, banner_sec2_title = ?, banner_sec2_text = ?, banner_sec2_image = ?, banner_sec2_btn1_text = ?, banner_sec2_btn1_url = ?, banner_sec2_btn2_text = ?, banner_sec2_btn2_url = ? WHERE id = 1");
    $stmt->execute([$name, $tagline, $admin_email, $whatsapp, $header_code, $footer_code, $footer_about, $theme, $section_priority_title, $section_latest_title, $section_football_title, $section_transfer_title, $section_third_title, $section_fourth_title, $section_primary_cat, $section_secondary_cat, $section_third_cat, $section_fourth_cat, $featured_cat1_name, $featured_cat1_title, $featured_cat1_image, $featured_cat2_name, $featured_cat2_title, $featured_cat2_image, $featured_cat3_name, $featured_cat3_title, $featured_cat3_image, $featured_cat4_name, $featured_cat4_title, $featured_cat4_image, $enable_live_feed, $enable_standings, $banner_sec1_title, $banner_sec1_text, $banner_sec1_image, $banner_sec1_btn1_text, $banner_sec1_btn1_url, $banner_sec1_btn2_text, $banner_sec1_btn2_url, $banner_sec2_title, $banner_sec2_text, $banner_sec2_image, $banner_sec2_btn1_text, $banner_sec2_btn1_url, $banner_sec2_btn2_text, $banner_sec2_btn2_url]);

    // Handle Logo Upload
    if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
        $logo_path = upload_image($_FILES['logo']);
        if ($logo_path) {
            $conn->prepare("UPDATE site_settings SET logo = ? WHERE id = 1")->execute([$logo_path]);
        }
    }

    // Handle Favicon Upload
    if (isset($_FILES['favicon']) && !empty($_FILES['favicon']['name'])) {
        $favicon_path = upload_image($_FILES['favicon']);
        if ($favicon_path) {
            $conn->prepare("UPDATE site_settings SET favicon = ? WHERE id = 1")->execute([$favicon_path]);
        }
    }

    $success = "General settings synchronized.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_ai'])) {
    $stmt = $conn->prepare("UPDATE site_settings SET deepseek_api_key = ?, selected_model = ? WHERE id = 1");
    $stmt->execute([
        $_POST['deepseek_api_key'] ?? '',
        $_POST['selected_model'] ?? $settings['selected_model']
    ]);
    $success = "AI logic updated.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_smtp'])) {
    $stmt = $conn->prepare("UPDATE site_settings SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_sender_email = ?, smtp_sender_name = ? WHERE id = 1");
    $stmt->execute([
        sanitize($_POST['smtp_host']),
        sanitize($_POST['smtp_port']),
        sanitize($_POST['smtp_user']),
        $_POST['smtp_pass'],
        sanitize($_POST['smtp_sender_email']),
        sanitize($_POST['smtp_sender_name'])
    ]);
    $success = "SMTP cluster reconfigured.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_security'])) {
    $pin_enabled = isset($_POST['pin_enabled']) ? 1 : 0;
    $new_pin = $_POST['admin_pin'];

    if ($pin_enabled && !empty($new_pin)) {
        $hashed_pin = password_hash($new_pin, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE site_settings SET pin_enabled = ?, admin_pin = ? WHERE id = 1");
        $stmt->execute([$pin_enabled, $hashed_pin]);
    } else {
        $stmt = $conn->prepare("UPDATE site_settings SET pin_enabled = ? WHERE id = 1");
        $stmt->execute([$pin_enabled]);
    }
    $success = "Security protocols updated.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_social'])) {
    $stmt = $conn->prepare("UPDATE site_settings SET
        tw_url = ?, fb_url = ?, ig_url = ?, yt_url = ?,
        fb_app_id = ?, fb_app_secret = ?, fb_page_id = ?, fb_access_token = ?,
        tw_client_id = ?, tw_client_secret = ?, tw_api_key = ?, tw_api_secret = ?, tw_access_token = ?, tw_access_secret = ?,
        ig_account_id = ?, ig_access_token = ?,
        tt_client_key = ?, tt_client_secret = ?, tt_access_token = ?
        WHERE id = 1");
    $stmt->execute([
        sanitize($_POST['tw_url']), sanitize($_POST['fb_url']), sanitize($_POST['ig_url']), sanitize($_POST['yt_url']),
        sanitize($_POST['fb_app_id']), $_POST['fb_app_secret'], sanitize($_POST['fb_page_id']), $_POST['fb_access_token'],
        sanitize($_POST['tw_client_id']), $_POST['tw_client_secret'], sanitize($_POST['tw_api_key']), sanitize($_POST['tw_api_secret']), sanitize($_POST['tw_access_token']), sanitize($_POST['tw_access_secret']),
        sanitize($_POST['ig_account_id']), $_POST['ig_access_token'],
        sanitize($_POST['tt_client_key']), $_POST['tt_client_secret'], $_POST['tt_access_token']
    ]);
    $success = "Social API Hub updated.";
}

$settings = get_settings();
$activeTab = $_GET['tab'] ?? 'general';
$all_categories = get_categories_with_counts();

?>
<h1 class="font-condensed fw-black italic text-white display-5 mb-5">SITE <span class="text-danger">SETTINGS</span></h1>

<?php if (isset($success)): ?>
    <div class="alert alert-success bg-green-900 bg-opacity-10 border-green-500 border-opacity-20 text-green-500 font-condensed italic uppercase mb-5"><?php echo $success; ?></div>
<?php endif; ?>

<div class="bg-[#0a0e17] rounded-3xl border border-white/5 overflow-hidden shadow-2xl">
    <div class="d-flex flex-wrap border-bottom border-white/5 bg-black">
        <a href="?tab=general" class="flex-grow-1 text-center py-4 text-[10px] font-black uppercase tracking-widest text-decoration-none border-bottom-2 <?php echo $activeTab == 'general' ? 'text-danger border-danger' : 'text-secondary border-transparent'; ?>">General</a>
        <a href="?tab=sections" class="flex-grow-1 text-center py-4 text-[10px] font-black uppercase tracking-widest text-decoration-none border-bottom-2 <?php echo $activeTab == 'sections' ? 'text-danger border-danger' : 'text-secondary border-transparent'; ?>"><i class="bi bi-fonts me-1"></i> Section Titles</a>
        <a href="?tab=ai" class="flex-grow-1 text-center py-4 text-[10px] font-black uppercase tracking-widest text-decoration-none border-bottom-2 <?php echo $activeTab == 'ai' ? 'text-danger border-danger' : 'text-secondary border-transparent'; ?>">AI Core</a>
        <a href="?tab=smtp" class="flex-grow-1 text-center py-4 text-[10px] font-black uppercase tracking-widest text-decoration-none border-bottom-2 <?php echo $activeTab == 'smtp' ? 'text-danger border-danger' : 'text-secondary border-transparent'; ?>">SMTP</a>
        <a href="?tab=security" class="flex-grow-1 text-center py-4 text-[10px] font-black uppercase tracking-widest text-decoration-none border-bottom-2 <?php echo $activeTab == 'security' ? 'text-danger border-danger' : 'text-secondary border-transparent'; ?>">Security</a>
        <a href="?tab=automation" class="flex-grow-1 text-center py-4 text-[10px] font-black uppercase tracking-widest text-decoration-none border-bottom-2 <?php echo $activeTab == 'automation' ? 'text-danger border-danger' : 'text-secondary border-transparent'; ?>">Automation</a>
        <a href="?tab=social" class="flex-grow-1 text-center py-4 text-[10px] font-black uppercase tracking-widest text-decoration-none border-bottom-2 <?php echo $activeTab == 'social' ? 'text-danger border-danger' : 'text-secondary border-transparent'; ?>">Social API Hub</a>
    </div>

    <div class="p-5 p-md-5">
        <?php if ($activeTab == 'sections'): ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <h4 class="text-white font-black uppercase italic mb-6 small text-danger"><i class="bi bi-fonts me-2"></i> Landing Page Category Sections Customization</h4>
                    <p class="text-white-50 text-xs mb-6">Select categories and customize section titles for landing page content blocks.</p>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Priority Section Title (Hero Tag)</label>
                            <input type="text" name="section_priority_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_priority_title'] ?? 'Priority Intelligence'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Latest Section Title (Hero Sidebar Header)</label>
                            <input type="text" name="section_latest_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_latest_title'] ?? 'Latest Intelligence'); ?>">
                        </div>

                        <!-- Primary Section -->
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Primary Section Title</label>
                            <input type="text" name="section_football_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_football_title'] ?? 'Football News'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Primary Category Selector</label>
                            <select name="section_primary_cat" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold">
                                <option value="">-- All / Default --</option>
                                <?php foreach ($all_categories as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['name']); ?>" <?php echo (($settings['section_primary_cat'] ?? 'Football News') === $c['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Secondary Section -->
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Secondary Section Title</label>
                            <input type="text" name="section_transfer_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_transfer_title'] ?? 'Transfer Intelligence'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Secondary Category Selector</label>
                            <select name="section_secondary_cat" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold">
                                <option value="">-- All / Default --</option>
                                <?php foreach ($all_categories as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['name']); ?>" <?php echo (($settings['section_secondary_cat'] ?? 'Transfer News') === $c['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Section 3 -->
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Section 3 Title</label>
                            <input type="text" name="section_third_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_third_title'] ?? 'Featured Reports'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Section 3 Category Selector</label>
                            <select name="section_third_cat" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($all_categories as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['name']); ?>" <?php echo (($settings['section_third_cat'] ?? '') === $c['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Section 4 -->
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Section 4 Title</label>
                            <input type="text" name="section_fourth_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_fourth_title'] ?? 'Analysis & Insights'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Section 4 Category Selector</label>
                            <select name="section_fourth_cat" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($all_categories as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['name']); ?>" <?php echo (($settings['section_fourth_cat'] ?? '') === $c['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Custom Landing Page Banner Sections -->
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5 mt-8">
                    <h4 class="text-white font-black uppercase italic mb-6 small text-danger"><i class="bi bi-layout-split me-2"></i> Custom Landing Page Banner Sections</h4>
                    <p class="text-white-50 text-xs mb-6">Configure 2 customizable article banner sections displayed on the home page below the hero grid.</p>

                    <div class="row g-6">
                        <!-- Section 1: Text Left, Image Right -->
                        <div class="col-lg-6">
                            <div class="bg-black/40 p-6 rounded-2xl border border-white/10 h-full">
                                <h5 class="text-white font-black uppercase italic mb-4 text-sm text-electric-red"><i class="bi bi-card-text me-2"></i> Section 1 (Text Left, Image Right)</h5>

                                <div class="mb-4">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Headline / Title</label>
                                    <input type="text" name="banner_sec1_title" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-white font-bold text-sm" placeholder="e.g. Turn Your Income Goals Into Action" value="<?php echo htmlspecialchars($settings['banner_sec1_title'] ?? ''); ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Article / Description Text</label>
                                    <textarea name="banner_sec1_text" rows="3" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-white text-xs leading-relaxed" placeholder="e.g. Discover practical ideas, trusted resources and step-by-step guides..."><?php echo htmlspecialchars($settings['banner_sec1_text'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Image Upload</label>
                                    <input type="file" name="banner_sec1_file" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">OR Image URL</label>
                                    <input type="text" name="banner_sec1_image_url" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="https://..." value="<?php echo htmlspecialchars($settings['banner_sec1_image'] ?? ''); ?>">
                                </div>

                                <?php if (!empty($settings['banner_sec1_image'])): ?>
                                    <div class="mb-4">
                                        <img src="<?php echo $settings['banner_sec1_image']; ?>" class="rounded-xl border border-white/10 max-h-32 object-cover">
                                    </div>
                                <?php endif; ?>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 1 Label</label>
                                        <input type="text" name="banner_sec1_btn1_text" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. Make extra money" value="<?php echo htmlspecialchars($settings['banner_sec1_btn1_text'] ?? ''); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 1 Target URL / Link</label>
                                        <input type="text" name="banner_sec1_btn1_url" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. /category/business or https://..." value="<?php echo htmlspecialchars($settings['banner_sec1_btn1_url'] ?? ''); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 2 Label</label>
                                        <input type="text" name="banner_sec1_btn2_text" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. Find your next job" value="<?php echo htmlspecialchars($settings['banner_sec1_btn2_text'] ?? ''); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 2 Target URL / Link</label>
                                        <input type="text" name="banner_sec1_btn2_url" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. /category/careers or https://..." value="<?php echo htmlspecialchars($settings['banner_sec1_btn2_url'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Image Left, Text Right -->
                        <div class="col-lg-6">
                            <div class="bg-black/40 p-6 rounded-2xl border border-white/10 h-full">
                                <h5 class="text-white font-black uppercase italic mb-4 text-sm text-electric-red"><i class="bi bi-card-text me-2"></i> Section 2 (Image Left, Text Right)</h5>

                                <div class="mb-4">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Headline / Title</label>
                                    <input type="text" name="banner_sec2_title" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-white font-bold text-sm" placeholder="e.g. Build Your Strategic Vision" value="<?php echo htmlspecialchars($settings['banner_sec2_title'] ?? ''); ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Article / Description Text</label>
                                    <textarea name="banner_sec2_text" rows="3" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-white text-xs leading-relaxed" placeholder="e.g. Explore tactical deep dives and real-time market movements..."><?php echo htmlspecialchars($settings['banner_sec2_text'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Image Upload</label>
                                    <input type="file" name="banner_sec2_file" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">OR Image URL</label>
                                    <input type="text" name="banner_sec2_image_url" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="https://..." value="<?php echo htmlspecialchars($settings['banner_sec2_image'] ?? ''); ?>">
                                </div>

                                <?php if (!empty($settings['banner_sec2_image'])): ?>
                                    <div class="mb-4">
                                        <img src="<?php echo $settings['banner_sec2_image']; ?>" class="rounded-xl border border-white/10 max-h-32 object-cover">
                                    </div>
                                <?php endif; ?>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 1 Label</label>
                                        <input type="text" name="banner_sec2_btn1_text" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. Explore Reports" value="<?php echo htmlspecialchars($settings['banner_sec2_btn1_text'] ?? ''); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 1 Target URL / Link</label>
                                        <input type="text" name="banner_sec2_btn1_url" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. /category/analysis or https://..." value="<?php echo htmlspecialchars($settings['banner_sec2_btn1_url'] ?? ''); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 2 Label</label>
                                        <input type="text" name="banner_sec2_btn2_text" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. Join Network" value="<?php echo htmlspecialchars($settings['banner_sec2_btn2_text'] ?? ''); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Button 2 Target URL / Link</label>
                                        <input type="text" name="banner_sec2_btn2_url" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="e.g. /about-us or https://..." value="<?php echo htmlspecialchars($settings['banner_sec2_btn2_url'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Featured Category Cards Settings -->
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5 mt-8">
                    <h4 class="text-white font-black uppercase italic mb-6 small text-danger"><i class="bi bi-grid-3x3-gap me-2"></i> Featured Category Cards (Visual Blocks)</h4>
                    <p class="text-white-50 text-xs mb-6">Configure 4 featured category cards with custom images and titles to display prominently on the landing page.</p>
                    <div class="row g-4">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="col-md-6">
                            <div class="bg-black/40 p-5 rounded-2xl border border-white/10 h-full">
                                <h6 class="text-white font-black uppercase italic mb-3 text-sm text-electric-red">Card <?php echo $i; ?></h6>

                                <div class="mb-3">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Target Category</label>
                                    <select name="featured_cat<?php echo $i; ?>_name" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs font-bold">
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($all_categories as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['name']); ?>" <?php echo (($settings["featured_cat{$i}_name"] ?? '') === $c['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Display Title</label>
                                    <input type="text" name="featured_cat<?php echo $i; ?>_title" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs font-bold" placeholder="e.g. Start a business" value="<?php echo htmlspecialchars($settings["featured_cat{$i}_title"] ?? ''); ?>">
                                </div>

                                <div class="mb-2">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Image File Upload</label>
                                    <input type="file" name="featured_cat<?php echo $i; ?>_file" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs">
                                </div>

                                <div class="mb-2">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">OR Image URL</label>
                                    <input type="text" name="featured_cat<?php echo $i; ?>_image_url" class="w-full bg-black border border-white/10 rounded-xl px-3 py-2 text-white text-xs" placeholder="https://..." value="<?php echo htmlspecialchars($settings["featured_cat{$i}_image"] ?? ''); ?>">
                                </div>

                                <?php if (!empty($settings["featured_cat{$i}_image"])): ?>
                                    <div class="mt-3">
                                        <img src="<?php echo $settings["featured_cat{$i}_image"]; ?>" class="rounded-xl border border-white/10 max-h-28 object-cover">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <button type="submit" name="save_sections" class="mt-8 bg-danger text-white px-10 py-3 rounded-2xl font-black uppercase italic tracking-widest hover:bg-white hover:text-danger transition-all">Save Section Settings</button>
            </form>

        <?php elseif ($activeTab == 'general'): ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Broadcaster Identity</label>
                        <input type="text" name="name" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $settings['name']; ?>">
                        <p class="text-[9px] text-white-50 mt-2 italic opacity-60">TIP: Use <strong>CamelCase</strong> (e.g. <strong>GoalZaza</strong>) to achieve double-color branding. The color change will trigger at the second capital letter.</p>
                    </div>
                    <div class="col-md-6">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Tagline</label>
                        <input type="text" name="tagline" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $settings['tagline']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Admin Email</label>
                        <input type="email" name="admin_email" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $settings['admin_email']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $settings['whatsapp_number']; ?>">
                    </div>

                    <!-- Module Toggles -->
                    <div class="col-12 mt-6">
                        <div class="bg-white/5 p-6 rounded-2xl border border-white/10">
                            <h4 class="text-white font-black uppercase italic mb-4 small text-danger"><i class="bi bi-toggle-on me-2"></i> Module Visibility</h4>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-check form-switch pt-2">
                                        <input class="form-check-input" type="checkbox" name="enable_live_feed" id="enable_live_feed" <?php echo ($settings['enable_live_feed'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-white font-bold text-sm ms-2" for="enable_live_feed">Enable Live Feed Module</label>
                                        <p class="text-[10px] text-white-50 mt-1 italic">Show "Live Feed" in navbar, footer, and routing.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch pt-2">
                                        <input class="form-check-input" type="checkbox" name="enable_standings" id="enable_standings" <?php echo ($settings['enable_standings'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-white font-bold text-sm ms-2" for="enable_standings">Enable Standings / League Tables Module</label>
                                        <p class="text-[10px] text-white-50 mt-1 italic">Show "Standings" in navbar, footer, and routing.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Landing Page Section Titles -->
                    <div class="col-12 mt-6">
                        <div class="bg-white/5 p-6 rounded-2xl border border-white/10">
                            <h4 class="text-white font-black uppercase italic mb-4 small text-danger"><i class="bi bi-fonts me-2"></i> Landing Page Section Titles</h4>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Priority Section Title</label>
                                    <input type="text" name="section_priority_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_priority_title'] ?? 'Priority Intelligence'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Latest Section Title</label>
                                    <input type="text" name="section_latest_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_latest_title'] ?? 'Latest Intelligence'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Primary Category Section Title</label>
                                    <input type="text" name="section_football_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_football_title'] ?? 'Football News'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Secondary Category Section Title</label>
                                    <input type="text" name="section_transfer_title" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" value="<?php echo htmlspecialchars($settings['section_transfer_title'] ?? 'Transfer Intelligence'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Footer Description / About Text</label>
                        <textarea name="footer_about" rows="3" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" placeholder="The world's most advanced football intelligence network..."><?php echo htmlspecialchars($settings['footer_about'] ?? 'The world\'s most advanced football intelligence network. Real-time decryption of global sports data and tactical analysis.'); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Custom Header Code (JS/CSS/Meta)</label>
                        <textarea name="header_code" rows="5" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-mono text-sm" placeholder="Paste code here to appear in <head>"><?php echo htmlspecialchars($settings['header_code'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Custom Footer Code (JS/Tracking Pixels)</label>
                        <textarea name="footer_code" rows="5" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-mono text-sm" placeholder="Paste code here to appear before </body>"><?php echo htmlspecialchars($settings['footer_code'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Site Logo (Any format)</label>
                        <input type="file" name="logo" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white">
                        <?php if (!empty($settings['logo'])): ?>
                            <img src="<?php echo $settings['logo']; ?>" class="mt-2 rounded" style="max-height: 50px;">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Favicon (Any format)</label>
                        <input type="file" name="favicon" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white">
                        <?php if (!empty($settings['favicon'])): ?>
                            <img src="<?php echo $settings['favicon']; ?>" class="mt-2 rounded" style="max-height: 30px;">
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" name="save_general" class="mt-8 bg-danger text-white px-10 py-3 rounded-2xl font-black uppercase italic tracking-widest hover:bg-white hover:text-danger transition-all">Commit Settings</button>
            </form>

        <?php elseif ($activeTab == 'ai'): ?>
            <div class="alert alert-info bg-blue-900 bg-opacity-10 border-blue-500 border-opacity-20 text-info font-condensed italic uppercase mb-5 p-4 rounded-3xl">
                <h5 class="fw-black mb-3">Intelligence Acquisition Guide</h5>
                <div class="row g-4 small">
                    <div class="col-12">
                        <p class="mb-2"><strong>DeepSeek API:</strong></p>
                        <ol class="ps-3 opacity-75">
                            <li>Visit <a href="https://platform.deepseek.com/" target="_blank" class="text-info">DeepSeek Platform</a>.</li>
                            <li>Navigate to the "API Keys" section.</li>
                            <li>Generate a new key and add balance to your account.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <form method="POST" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <div class="bg-white/5 p-6 rounded-2xl border border-white/10 shadow-inner">
                            <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-3 block">DeepSeek API Key</span>
                            <input type="password" name="deepseek_api_key" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs text-white" value="<?php echo $settings['deepseek_api_key']; ?>">
                        </div>
                    </div>
                </div>
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5 mb-8">
                    <h4 class="text-white font-black uppercase italic mb-4 small">News Discovery Protocol</h4>
                    <p class="text-gray-500 text-[10px] uppercase font-bold tracking-widest mb-6">System is currently configured for autonomous factual news discovery via encrypted RSS channels.</p>

                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Discovery Source</label>
                            <div class="bg-black/40 border border-white/10 rounded-xl px-6 py-4 text-white font-bold opacity-60 cursor-not-allowed">
                                GLOBAL RSS FEDERATION (Sky Sports, ESPN, SuperSport, BBC)
                            </div>
                            <p class="text-[9px] text-white-50 mt-3 italic opacity-60">Optimized for 100% factual accuracy from official sports broadcasting networks.</p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <span class="text-[10px] font-black uppercase text-gray-500 tracking-widest block mb-4">Article Drafting Model (Writing Engine)</span>
                    <?php
                    $models = [
                        ['id' => 'deepseek-chat', 'name' => 'DeepSeek-V3 (Chat)', 'provider' => 'DeepSeek'],
                        ['id' => 'deepseek-reasoner', 'name' => 'DeepSeek-R1 (Reasoner)', 'provider' => 'DeepSeek'],
                    ];
                    foreach ($models as $m):
                    ?>
                        <label class="d-flex align-items-center justify-content-between p-4 rounded-2xl border w-full mb-3 cursor-pointer transition-all <?php echo $settings['selected_model'] == $m['id'] ? 'border-danger bg-danger bg-opacity-5' : 'border-white/5 bg-white/5'; ?>">
                            <div class="text-start">
                                <span class="text-[8px] font-black px-1.5 py-0.5 rounded uppercase bg-white/10 text-gray-400 mr-2"><?php echo $m['provider']; ?></span>
                                <span class="text-sm font-black text-white uppercase italic"><?php echo $m['name']; ?></span>
                            </div>
                            <input type="radio" name="selected_model" value="<?php echo $m['id']; ?>" <?php echo $settings['selected_model'] == $m['id'] ? 'checked' : ''; ?> class="form-check-input bg-danger border-0">
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="save_ai" class="bg-danger text-white px-10 py-3 rounded-2xl font-black uppercase italic tracking-widest hover:bg-white hover:text-danger transition-all">Update AI Logic</button>
            </form>

        <?php elseif ($activeTab == 'security'): ?>
            <form method="POST" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h4 class="text-white font-black uppercase italic mb-1">Security PIN (2FA)</h4>
                            <p class="text-gray-500 text-[10px] uppercase font-bold tracking-widest">Enhanced biometric-style cipher protection</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="pin_enabled" class="sr-only peer" <?php echo ($settings['pin_enabled'] ?? false) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:bg-danger after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Set New Security PIN</label>
                        <input type="password" name="admin_pin" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-mono tracking-[1em]" placeholder="••••" inputmode="numeric" pattern="[0-9]*">
                        <p class="text-[9px] text-white-50 mt-2 italic opacity-40">Only required if enabling for the first time or changing the PIN.</p>
                    </div>
                </div>
                <button type="submit" name="save_security" class="bg-danger text-white px-10 py-3 rounded-2xl font-black uppercase italic tracking-widest hover:bg-white hover:text-danger transition-all">Apply Security Logic</button>
            </form>

        <?php elseif ($activeTab == 'automation'): ?>
            <div class="space-y-8">
                <div class="alert alert-warning bg-orange-900 bg-opacity-10 border-orange-500 border-opacity-20 text-orange-500 font-condensed italic uppercase p-4 rounded-3xl">
                    <h5 class="fw-black mb-3">Cron Job Configuration</h5>
                    <p class="small mb-4 opacity-75">To enable real-time news automation, you must configure a cron job on your server (cPanel/VPS). This job should trigger the news engine every 30 minutes to capture the latest intelligence.</p>

                    <div class="bg-black/50 p-4 rounded-2xl mb-4 border border-white/10">
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2">CRON JOB COMMAND</span>
                        <code class="text-white small">*/30 * * * * /usr/bin/php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/automation/news_engine.php</code>
                    </div>

                    <h6 class="fw-black small uppercase mb-2">Instructions for cPanel:</h6>
                    <ol class="small ps-3 opacity-75">
                        <li>Log in to your <strong>cPanel</strong> account.</li>
                        <li>Search for <strong>"Cron Jobs"</strong> in the search bar.</li>
                        <li>Under "Add New Cron Job", select <strong>"Once Per Day"</strong> from Common Settings.</li>
                        <li>In the "Command" field, paste the command shown above.</li>
                        <li>Click <strong>"Add New Cron Job"</strong> to finalize.</li>
                    </ol>
                </div>

                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <h4 class="text-white font-black uppercase italic mb-3 small">Operational Status</h4>
                    <div class="d-flex align-items-center gap-3">
                        <div class="w-3 h-3 rounded-full bg-success shadow-[0_0_10px_#198754]"></div>
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Automation Engine Ready</span>
                    </div>
                    <p class="text-gray-500 text-[9px] uppercase font-bold mt-4">Note: Ensure your DeepSeek API key is configured in the "AI Core" tab.</p>
                </div>

                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <h4 class="text-white font-black uppercase italic mb-6 small">Intelligence Feed Registry</h4>
                    <div class="space-y-4">
                        <?php
                        $feeds = get_rss_feed_urls();
                        foreach ($feeds as $feed):
                        ?>
                            <div class="d-flex align-items-center justify-content-between p-3 bg-black/40 rounded-xl border border-white/5">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-danger animate-pulse"></div>
                                    <span class="text-white font-monospace small" style="font-size: 10px;"><?php echo $feed; ?></span>
                                </div>
                                <span class="badge bg-danger bg-opacity-10 text-danger font-black uppercase italic" style="font-size: 8px;">ACTIVE MONITORING</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab == 'smtp'): ?>
            <form method="POST" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Host Address</label>
                        <input type="text" name="smtp_host" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white font-mono" value="<?php echo $settings['smtp_host']; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Port</label>
                        <input type="text" name="smtp_port" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white font-mono" value="<?php echo $settings['smtp_port']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Auth Username</label>
                        <input type="text" name="smtp_user" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['smtp_user']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Auth Password</label>
                        <input type="password" name="smtp_pass" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['smtp_pass']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Sender Email</label>
                        <input type="email" name="smtp_sender_email" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['smtp_sender_email']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Sender Name</label>
                        <input type="text" name="smtp_sender_name" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['smtp_sender_name']; ?>">
                    </div>
                </div>
                <button type="submit" name="save_smtp" class="mt-8 bg-danger text-white px-10 py-3 rounded-2xl font-black uppercase italic tracking-widest hover:bg-white hover:text-danger transition-all">Apply SMTP Config</button>
            </form>

        <?php elseif ($activeTab == 'social'): ?>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success bg-green-900 bg-opacity-20 border-green-500 text-green-500 font-black uppercase italic mb-8 p-4 rounded-3xl">Account Successfully Linked!</div>
            <?php endif; ?>
            <div class="alert alert-info bg-blue-900 bg-opacity-10 border-blue-500 border-opacity-20 text-info font-condensed italic uppercase mb-8 p-4 rounded-3xl">
                <h5 class="fw-black mb-3">Automatic Link-Up Instructions</h5>
                <p class="small opacity-75 mb-0">To enable automatic posting, follow these simple steps for each platform:</p>
                <div class="row g-4 mt-1 small">
                    <div class="col-md-6">
                        <p class="mb-1 text-white"><strong>Facebook / Instagram:</strong></p>
                        <ol class="ps-3 opacity-75">
                            <li>Visit <a href="https://developers.facebook.com" class="text-info">Meta for Developers</a> and create an App.</li>
                            <li>Add "Facebook Login" and "Instagram Graph API".</li>
                            <li>In "App Settings", get your <strong>App ID</strong>.</li>
                            <li>Use the "Graph API Explorer" to generate a <strong>Permanent Page Access Token</strong>. <span class="text-white-50">(Tip: This prevents the link from breaking every 60 days)</span></li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1 text-white"><strong>X (Twitter):</strong></p>
                        <ol class="ps-3 opacity-75">
                            <li>Visit <a href="https://developer.x.com" class="text-info">X Developer Portal</a>.</li>
                            <li>Create a project and app. <strong>IMPORTANT:</strong> Set User Authentication to <strong>Read and Write</strong>.</li>
                            <li>Copy your API Key, Secret, Access Token, and Secret from the "Keys and Tokens" tab.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <form method="POST" class="space-y-12">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- Public URLs -->
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <h4 class="text-white font-black uppercase italic mb-6 small">Public Profile Links</h4>
                    <div class="row g-4">
                        <div class="col-md-3"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">X URL</label><input type="url" name="tw_url" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['tw_url'] ?? ''; ?>"></div>
                        <div class="col-md-3"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Facebook URL</label><input type="url" name="fb_url" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['fb_url'] ?? ''; ?>"></div>
                        <div class="col-md-3"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Instagram URL</label><input type="url" name="ig_url" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['ig_url'] ?? ''; ?>"></div>
                        <div class="col-md-3"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">YouTube URL</label><input type="url" name="yt_url" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['yt_url'] ?? ''; ?>"></div>
                    </div>
                </div>

                <!-- Facebook API -->
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <div class="d-flex justify-content-between align-items-center mb-6">
                        <h4 class="text-white font-black uppercase italic mb-0 small">Facebook Automation</h4>
                        <a href="social_connect.php?platform=facebook" class="btn btn-sm btn-primary font-condensed fw-black italic uppercase px-4">Link Facebook</a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">App ID</label><input type="text" name="fb_app_id" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['fb_app_id'] ?? ''; ?>"></div>
                        <div class="col-md-6"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">App Secret</label><input type="password" name="fb_app_secret" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['fb_app_secret'] ?? ''; ?>"></div>
                        <div class="col-md-4"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Linked Page ID</label><input type="text" name="fb_page_id" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['fb_page_id'] ?? ''; ?>" readonly placeholder="Auto-populated after linking"></div>
                        <div class="col-md-8"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Page Access Token</label><input type="password" name="fb_access_token" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['fb_access_token'] ?? ''; ?>" readonly placeholder="Auto-populated after linking"></div>
                    </div>
                </div>

                <!-- X API -->
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <div class="d-flex justify-content-between align-items-center mb-6">
                        <h4 class="text-white font-black uppercase italic mb-0 small">X (Twitter) Automation</h4>
                        <a href="social_connect.php?platform=x" class="btn btn-sm btn-primary font-condensed fw-black italic uppercase px-4">Link X Account</a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Client ID</label><input type="text" name="tw_client_id" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['tw_client_id'] ?? ''; ?>"></div>
                        <div class="col-md-6"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Client Secret</label><input type="password" name="tw_client_secret" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['tw_client_secret'] ?? ''; ?>"></div>
                        <div class="col-md-12"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Access Token</label><input type="text" name="tw_access_token" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['tw_access_token'] ?? ''; ?>" readonly placeholder="Auto-populated after linking"></div>
                    </div>
                </div>

                <!-- Instagram API -->
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <div class="d-flex justify-content-between align-items-center mb-6">
                        <h4 class="text-white font-black uppercase italic mb-0 small">Instagram Automation</h4>
                        <a href="social_connect.php?platform=facebook" class="btn btn-sm btn-primary font-condensed fw-black italic uppercase px-4">Link Instagram</a>
                    </div>
                    <p class="text-[10px] text-white-50 mb-4 italic uppercase">Linked via Facebook. Ensure your Instagram Business account is connected to your Facebook Page.</p>
                    <div class="row g-4">
                        <div class="col-md-4"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Business Account ID</label><input type="text" name="ig_account_id" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['ig_account_id'] ?? ''; ?>"></div>
                        <div class="col-md-8"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Access Token</label><input type="password" name="ig_access_token" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['ig_access_token'] ?? ''; ?>"></div>
                    </div>
                </div>

                <!-- TikTok API -->
                <div class="bg-white/5 p-8 rounded-3xl border border-white/5">
                    <div class="d-flex justify-content-between align-items-center mb-6">
                        <h4 class="text-white font-black uppercase italic mb-0 small">TikTok Automation</h4>
                        <a href="social_connect.php?platform=tiktok" class="btn btn-sm btn-primary font-condensed fw-black italic uppercase px-4">Link TikTok</a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Client Key</label><input type="text" name="tt_client_key" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['tt_client_key'] ?? ''; ?>"></div>
                        <div class="col-md-6"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">Client Secret</label><input type="password" name="tt_client_secret" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['tt_client_secret'] ?? ''; ?>"></div>
                        <div class="col-12"><label class="text-[9px] uppercase font-black text-gray-500 block mb-2">TikTok Access Token</label><input type="password" name="tt_access_token" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" value="<?php echo $settings['tt_access_token'] ?? ''; ?>" readonly placeholder="Auto-populated after linking"></div>
                    </div>
                </div>

                <button type="submit" name="save_social" class="bg-danger text-white px-10 py-3 rounded-2xl font-black uppercase italic tracking-widest hover:bg-white hover:text-danger transition-all">Synchronize Social API</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php admin_footer();