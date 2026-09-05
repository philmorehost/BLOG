<?php
/**
 * Social Media Broadcaster
 * Handles automatic posting to Facebook, X, Instagram, and TikTok
 */

function broadcast_to_social($post_id) {
    $conn = get_db_connection();
    $settings = get_settings();

    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) return;

    $site_url = rtrim(SITE_URL, '/');
    $post_url = $site_url . "/post/" . $post['slug'];
    $message = $post['title'] . "\n\n" . $post['excerpt'] . "\n\nRead more: " . $post_url;
    $image_url = $site_url . $post['image'];

    // 1. Facebook Post
    if (!empty($settings['fb_page_id']) && !empty($settings['fb_access_token'])) {
        post_to_facebook($settings['fb_page_id'], $settings['fb_access_token'], $message, $post_url);
    }

    // 2. X (Twitter) Post
    if (!empty($settings['tw_api_key']) && !empty($settings['tw_access_token'])) {
        post_to_x($settings, $message);
    }

    // 3. Instagram Post (Requires Business Account & Image)
    if (!empty($settings['ig_account_id']) && !empty($settings['ig_access_token'])) {
        post_to_instagram($settings['ig_account_id'], $settings['ig_access_token'], $image_url, $message);
    }

    // 4. TikTok Post (Direct Video/Photo API)
    if (!empty($settings['tt_access_token'])) {
        post_to_tiktok($settings['tt_access_token'], $image_url, $message);
    }
}

function post_to_facebook($page_id, $token, $message, $link) {
    $url = "https://graph.facebook.com/v19.0/{$page_id}/feed";
    $params = [
        'message' => $message,
        'link' => $link,
        'access_token' => $token
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function post_to_x($settings, $message) {
    // Note: X API v2 requires OAuth 1.0a or 2.0.
    // This is a simplified representation of the POST /2/tweets endpoint call.
    $url = "https://api.twitter.com/2/tweets";
    $data = json_encode(['text' => $message]);

    // For a real implementation, a library like abraham/twitteroauth would be used.
    // Here we assume the Bearer token or pre-signed auth.
    $headers = [
        "Authorization: Bearer " . $settings['tw_access_token'],
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function post_to_instagram($account_id, $token, $image_url, $caption) {
    // Step 1: Container Creation
    $url = "https://graph.facebook.com/v19.0/{$account_id}/media";
    $params = [
        'image_url' => $image_url,
        'caption' => $caption,
        'access_token' => $token
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (isset($data['id'])) {
        // Step 2: Media Publish
        $publish_url = "https://graph.facebook.com/v19.0/{$account_id}/media_publish";
        $p_params = [
            'creation_id' => $data['id'],
            'access_token' => $token
        ];
        $ch2 = curl_init($publish_url);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($p_params));
        curl_exec($ch2);
        curl_close($ch2);
    }
}

function post_to_tiktok($token, $image_url, $description) {
    // TikTok Content Posting API (simplified)
    $url = "https://open.tiktokapis.com/v2/post/publish/content/init/";
    $headers = [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json"
    ];
    $data = json_encode([
        "post_info" => [
            "title" => substr($description, 0, 100),
            "description" => $description
        ],
        "source_info" => [
            "source" => "FILE_UPLOAD",
            "video_size" => 0 // This would be more complex for real use
        ],
        "post_settings" => ["privacy_level" => "PUBLIC_TO_EVERYONE"]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}