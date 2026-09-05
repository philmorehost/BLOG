<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) {
    die("Unauthorized");
}

$platform = $_GET['platform'] ?? '';
$settings = get_settings();

// Base URL for callback
$callback_url = SITE_URL . "/admin/social_callback.php?platform=" . $platform;

if ($platform == 'facebook') {
    $app_id = $settings['fb_app_id'] ?? '';
    if (empty($app_id)) die("Facebook App ID missing in settings.");

    $scope = "pages_manage_posts,pages_read_engagement,instagram_basic,instagram_content_publish,business_management";
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $url = "https://www.facebook.com/v19.0/dialog/oauth?" . http_build_query([
        'client_id' => $app_id,
        'redirect_uri' => $callback_url,
        'state' => $state,
        'scope' => $scope
    ]);
    header("Location: $url");
    exit;

} elseif ($platform == 'x') {
    $client_id = $settings['tw_api_key'] ?? ''; // X uses API Key as Client ID in 2.0 sometimes, but usually Client ID is different.
    // Actually for X v2 OAuth 2.0:
    $client_id = $settings['tw_client_id'] ?? '';
    if (empty($client_id)) die("X Client ID missing in settings.");

    $state = bin2hex(random_bytes(16));
    $code_verifier = bin2hex(random_bytes(32));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_code_verifier'] = $code_verifier;

    $code_challenge = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash('sha256', $code_verifier, true)));

    $url = "https://twitter.com/i/oauth2/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $client_id,
        'redirect_uri' => $callback_url,
        'scope' => 'tweet.read tweet.write users.read offline.access',
        'state' => $state,
        'code_challenge' => $code_challenge,
        'code_challenge_method' => 'S256'
    ]);
    header("Location: $url");
    exit;

} elseif ($platform == 'tiktok') {
    $client_key = $settings['tt_client_key'] ?? '';
    if (empty($client_key)) die("TikTok Client Key missing in settings.");

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $url = "https://www.tiktok.com/v2/auth/authorize/?" . http_build_query([
        'client_key' => $client_key,
        'scope' => 'video.publish,video.upload,user.info.basic',
        'response_type' => 'code',
        'redirect_uri' => $callback_url,
        'state' => $state
    ]);
    header("Location: $url");
    exit;
}

die("Invalid platform");