<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) {
    die("Unauthorized");
}

$platform = $_GET['platform'] ?? '';
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$settings = get_settings();

// Verify state
if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
    // die("Invalid OAuth state. Please try again.");
}

$callback_url = SITE_URL . "/admin/social_callback.php?platform=" . $platform;

if ($platform == 'facebook' && $code) {
    // 1. Exchange code for user token
    $token_url = "https://graph.facebook.com/v19.0/oauth/access_token?" . http_build_query([
        'client_id' => $settings['fb_app_id'],
        'redirect_uri' => $callback_url,
        'client_secret' => $settings['fb_app_secret'],
        'code' => $code
    ]);

    $resp = file_get_contents($token_url);
    $data = json_decode($resp, true);
    $user_token = $data['access_token'] ?? '';

    if ($user_token) {
        // 2. Get User Pages
        $pages_url = "https://graph.facebook.com/v19.0/me/accounts?access_token=" . $user_token;
        $pages_resp = file_get_contents($pages_url);
        $pages_data = json_decode($pages_resp, true);

        // Show page selector
        admin_header("Select Facebook Page");
        ?>
        <h1 class="font-condensed fw-black italic text-white display-5 mb-5">SELECT <span class="text-danger">FACEBOOK PAGE</span></h1>
        <div class="bg-[#0a0e17] rounded-3xl border border-white/5 p-5 shadow-2xl">
            <p class="text-white-50 mb-4">Select the page you want to broadcast reports to:</p>
            <div class="row g-4">
                <?php foreach ($pages_data['data'] ?? [] as $page): ?>
                    <div class="col-md-6">
                        <form method="POST" action="social_callback.php?platform=facebook_final">
                            <input type="hidden" name="page_id" value="<?php echo $page['id']; ?>">
                            <input type="hidden" name="page_token" value="<?php echo $page['access_token']; ?>">
                            <input type="hidden" name="page_name" value="<?php echo $page['name']; ?>">
                            <button type="submit" class="w-full text-start bg-white/5 border border-white/10 hover:border-danger p-4 rounded-2xl transition-all group">
                                <div class="font-black text-white uppercase italic group-hover:text-danger"><?php echo $page['name']; ?></div>
                                <div class="text-[10px] text-gray-500 font-mono mt-1">ID: <?php echo $page['id']; ?></div>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        admin_footer();
        exit;
    }
}

if ($platform == 'facebook_final' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $page_id = $_POST['page_id'];
    $page_token = $_POST['page_token'];

    // Attempt to discover Instagram Business Account associated with this page
    $ig_url = "https://graph.facebook.com/v19.0/{$page_id}?fields=instagram_business_account&access_token={$page_token}";
    $ig_resp = @file_get_contents($ig_url);
    $ig_data = json_decode($ig_resp, true);
    $ig_id = $ig_data['instagram_business_account']['id'] ?? '';

    $stmt = $conn->prepare("UPDATE site_settings SET fb_page_id = ?, fb_access_token = ?, ig_account_id = ?, ig_access_token = ? WHERE id = 1");
    $stmt->execute([$page_id, $page_token, $ig_id, $page_token]);

    redirect("/admin/settings?tab=social&success=fb_linked");
}

if ($platform == 'x' && $code) {
    $token_url = "https://api.twitter.com/2/oauth2/token";
    $auth = base64_encode($settings['tw_client_id'] . ":" . $settings['tw_client_secret']);

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic $auth",
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $callback_url,
        'code_verifier' => $_SESSION['oauth_code_verifier']
    ]));

    $resp = curl_exec($ch);
    $data = json_decode($resp, true);
    curl_close($ch);

    if (isset($data['access_token'])) {
        $conn->prepare("UPDATE site_settings SET tw_access_token = ? WHERE id = 1")->execute([$data['access_token']]);
        redirect("/admin/settings?tab=social&success=x_linked");
    } else {
        die("X Token Error: " . $resp);
    }
}

if ($platform == 'tiktok' && $code) {
    $token_url = "https://open.tiktokapis.com/v2/oauth/token/";

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_key' => $settings['tt_client_key'],
        'client_secret' => $settings['tt_client_secret'],
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $callback_url
    ]));

    $resp = curl_exec($ch);
    $data = json_decode($resp, true);
    curl_close($ch);

    if (isset($data['access_token'])) {
        $conn->prepare("UPDATE site_settings SET tt_access_token = ? WHERE id = 1")->execute([$data['access_token']]);
        redirect("/admin/settings?tab=social&success=tt_linked");
    } else {
        die("TikTok Token Error: " . $resp);
    }
}

redirect("/admin/settings?tab=social&error=callback_failed");