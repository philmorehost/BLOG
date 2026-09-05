<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/social_poster.php';

function get_settings() {
    static $settings = null;
    if ($settings !== null) return $settings;

    $conn = get_db_connection();
    if (!$conn) return [
        'name' => 'FOOTBALL INTELLIGENCE',
        'logo' => '',
        'favicon' => ''
    ];
    try {
        $stmt = $conn->query("SELECT * FROM site_settings WHERE id = 1");
        $settings = $stmt->fetch();
    } catch (PDOException $e) {
        // Table site_settings does not exist or database is uninitialized.
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite' && file_exists(__DIR__ . '/../install/schema_sqlite.sql')) {
                $schema = file_get_contents(__DIR__ . '/../install/schema_sqlite.sql');
                $conn->exec($schema);
            } elseif ($driver === 'mysql') {
                if (file_exists(__DIR__ . '/../install/schema.sql')) {
                    $schema = file_get_contents(__DIR__ . '/../install/schema.sql');
                    $conn->exec($schema);
                }
                if (file_exists(__DIR__ . '/../install/data.sql')) {
                    $data = file_get_contents(__DIR__ . '/../install/data.sql');
                    $conn->exec($data);
                }
            }
            $stmt = $conn->query("SELECT * FROM site_settings WHERE id = 1");
            $settings = $stmt->fetch();
        } catch (Exception $ex) {
            $settings = false;
        }
    }

    if ($settings && !array_key_exists('header_code', $settings)) {
        try {
            $conn->exec("ALTER TABLE site_settings ADD COLUMN header_code TEXT");
            $conn->exec("ALTER TABLE site_settings ADD COLUMN footer_code TEXT");
            // Refetch settings after migration
            $stmt = $conn->query("SELECT * FROM site_settings WHERE id = 1");
            $settings = $stmt->fetch();
        } catch (Exception $e) {
            error_log("Code injection migration failed: " . $e->getMessage());
        }
    }


    // Auto-migration for posts source_url and video_url
    try {
        $conn->query("SELECT source_url FROM posts LIMIT 1");
    } catch (Exception $e) {
        try {
            $conn->exec("ALTER TABLE posts ADD COLUMN source_url VARCHAR(255)");
        } catch (Exception $ex) {}
    }

    try {
        $conn->query("SELECT video_url FROM posts LIMIT 1");
    } catch (Exception $e) {
        try {
            $conn->exec("ALTER TABLE posts ADD COLUMN video_url VARCHAR(255)");
        } catch (Exception $ex) {}
    }

    // Auto-migration for categories slug
    try {
        $conn->query("SELECT slug FROM categories LIMIT 1");
    } catch (Exception $e) {
        try {
            $conn->exec("ALTER TABLE categories ADD COLUMN slug VARCHAR(100)");
            // Populate slugs for existing categories
            $all_cats = $conn->query("SELECT id, name FROM categories")->fetchAll();
            foreach ($all_cats as $c) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $c['name'])));
                $conn->prepare("UPDATE categories SET slug = ? WHERE id = ?")->execute([$slug, $c['id']]);
            }
        } catch (Exception $ex) {}
    }

    // Auto-migration for pages is_external and external_url
    try {
        $conn->query("SELECT is_external FROM pages LIMIT 1");
    } catch (Exception $e) {
        try {
            $conn->exec("ALTER TABLE pages ADD COLUMN is_external BOOLEAN DEFAULT FALSE");
            $conn->exec("ALTER TABLE pages ADD COLUMN external_url VARCHAR(255)");
        } catch (Exception $ex) {}
    }

    // Auto-migration for users bio and social links
    try {
        $conn->query("SELECT bio FROM users LIMIT 1");
    } catch (Exception $e) {
        try {
            $conn->exec("ALTER TABLE users ADD COLUMN bio TEXT");
            $conn->exec("ALTER TABLE users ADD COLUMN twitter_url VARCHAR(255)");
            $conn->exec("ALTER TABLE users ADD COLUMN linkedin_url VARCHAR(255)");
            $conn->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255)");
        } catch (Exception $ex) {}
    }

    // Category Consolidation & Data Integrity Migration
    if ($settings && empty($settings['taxonomy_migrated'])) {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO categories (name, slug) VALUES " : "INSERT IGNORE INTO categories (name, slug) VALUES ";

            // Ensure categories exist
            $conn->exec($sql . "('Football News', 'football-news')");
            $conn->exec($sql . "('Transfer News', 'transfer-news')");

            // Update posts to new categories
            $conn->exec("UPDATE posts SET category = 'Transfer News' WHERE category LIKE '%Transfer%'");
            $conn->exec("UPDATE posts SET category = 'Football News' WHERE category != 'Transfer News'");

            // Fix NULLs
            $conn->exec("UPDATE posts SET is_scheduled = 0 WHERE is_scheduled IS NULL");
            $conn->exec("UPDATE posts SET is_top_story = 0 WHERE is_top_story IS NULL");
            $conn->exec("UPDATE posts SET publish_date = created_at WHERE publish_date IS NULL");

            // Clean up old categories
            $conn->exec("DELETE FROM categories WHERE name NOT IN ('Football News', 'Transfer News')");

            // Ensure About Us page exists
            $check_about = $conn->query("SELECT id FROM pages WHERE slug = 'about-us'")->fetch();
            if (!$check_about) {
                $conn->prepare("INSERT INTO pages (title, slug, content, is_visible, position) VALUES (?, ?, ?, 1, 'footer')")
                     ->execute(['About Us', 'about-us', '# About Football Intelligence Network\n\nWelcome to the most advanced football intelligence hub.\n\n## Our Mission\nOur mission is to provide real-time, professional-grade football intelligence and transfer updates to fans globally. We leverage expert insights to bring you the stories that matter.\n\n## The Team\nOur team consists of veteran sports journalists and data analysts dedicated to 100 percent human-verified reporting.']);
            }

            // Mark as migrated
            $conn->exec("UPDATE site_settings SET taxonomy_migrated = 1 WHERE id = 1");
            $settings['taxonomy_migrated'] = 1;
        } catch (Exception $e) {
            // Silently fail if columns/tables don't exist yet
        }
    }

    $settings = $settings ?: [
        'name' => 'FOOTBALL INTELLIGENCE',
        'logo' => '',
        'favicon' => ''
    ];
    return $settings;
}

function get_categories_with_counts() {
    static $categories = null;
    if ($categories !== null) return $categories;

    $conn = get_db_connection();
    if (!$conn) return [
        ['name' => 'PREMIER LEAGUE', 'post_count' => 5],
        ['name' => 'TRANSFER NEWS', 'post_count' => 3],
        ['name' => 'MATCH ANALYSIS', 'post_count' => 8]
    ];
    $stmt = $conn->query("SELECT c.id, c.name, c.slug, COUNT(p.id) as post_count
                          FROM categories c
                          LEFT JOIN posts p ON c.name = p.category
                          GROUP BY c.id, c.name, c.slug
                          ORDER BY c.name ASC");
    $categories = $stmt->fetchAll();
    return $categories;
}

function format_site_title($name, $primary_class = 'text-electric-red') {
    $name = trim($name);
    // Find all capital letters
    preg_match_all('/[A-Z]/', $name, $matches, PREG_OFFSET_CAPTURE);

    // If there's at least two capital letters, split at the second one
    if (count($matches[0]) >= 2) {
        $split_pos = $matches[0][1][1];
        $first = substr($name, 0, $split_pos);
        $second = substr($name, $split_pos);
        return htmlspecialchars($first) . '<span class="' . $primary_class . '">' . htmlspecialchars($second) . '</span>';
    }

    // Fallback if CamelCase not detected: split by first space
    $parts = explode(' ', $name, 2);
    if (count($parts) > 1) {
        return htmlspecialchars($parts[0]) . ' <span class="' . $primary_class . '">' . htmlspecialchars($parts[1]) . '</span>';
    }

    return htmlspecialchars($name);
}

function clean_utf8($string) {
    if (!is_string($string)) return $string;

    // Remove UTF-8 BOM if present
    $string = str_replace("\xEF\xBB\xBF", '', $string);

    // Map common UTF-8 "smart" characters to their ASCII equivalents BEFORE encoding conversion
    $utf8_map = [
        "\xe2\x80\x98" => "'", "\xe2\x80\x99" => "'", // Smart single quotes
        "\xe2\x80\x9c" => '"', "\xe2\x80\x9d" => '"', // Smart double quotes
        "\xe2\x80\x93" => '-', "\xe2\x80\x94" => '-', // En/Em dashes
        "\xe2\x80\xa6" => '...', // Ellipsis
    ];
    $string = strtr($string, $utf8_map);

    // Force valid UTF-8 and remove invalid sequences
    $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');

    // Specifically remove the replacement character (U+FFFD) which often shows as '?'
    $string = str_replace("\xEF\xBF\xBD", '', $string);

    // Replace common Windows-1252 / CP1252 characters
    $map = [
        chr(0x80) => '€', chr(0x82) => '‚', chr(0x83) => 'ƒ', chr(0x84) => '„',
        chr(0x85) => '...', chr(0x86) => '†', chr(0x87) => '‡', chr(0x88) => 'ˆ',
        chr(0x89) => '‰', chr(0x8A) => 'Š', chr(0x8B) => '‹', chr(0x8C) => 'Œ',
        chr(0x8E) => 'Ž', chr(0x91) => "'", chr(0x92) => "'", chr(0x93) => '"',
        chr(0x94) => '"', chr(0x95) => '•', chr(0x96) => '-', chr(0x97) => '-',
        chr(0x98) => '~', chr(0x99) => '™', chr(0x9A) => 'š', chr(0x9B) => '›',
        chr(0x9C) => 'œ', chr(0x9E) => 'ž', chr(0x9F) => 'Ÿ',
    ];
    $string = strtr($string, $map);

    // Remove any remaining non-printable characters, keeping common accented letters and symbols
    // Also explicitly strip literal '?' if they are likely remnants of failed encoding
    $cleaned = preg_replace('/[^\x20-\x7E\xA0-\xFF\x{0100}-\x{FFFF}]/u', '', $string);

    return ($cleaned !== null) ? $cleaned : $string;
}

function sanitize($data) {
    if (is_array($data)) {
        $data = implode(', ', $data);
    }
    $data = clean_utf8($data);
    return htmlspecialchars(strip_tags(trim($data)));
}

function is_admin() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin';
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . $url . '";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
        echo '</noscript>';
    }
    exit;
}

function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// SMTP Mail Function
function send_mail($to, $subject, $message) {
    $settings = get_settings();
    if (empty($settings['smtp_host'])) {
        $headers = "From: " . ($settings['smtp_sender_name'] ?: ($settings['name'] ?? 'Football Intelligence')) . " <" . ($settings['smtp_sender_email'] ?: 'noreply@intelligence.com') . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return mail($to, $subject, $message, $headers);
    }

    $host = $settings['smtp_host'];
    $port = $settings['smtp_port'];
    $user = $settings['smtp_user'];
    $pass = $settings['smtp_pass'];
    $from = $settings['smtp_sender_email'];
    $name = $settings['smtp_sender_name'];

    // Prepend ssl:// for port 465 if no scheme is provided
    if ($port == 465 && strpos($host, '://') === false) {
        $host = "ssl://" . $host;
    }

    try {
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) throw new Exception("Could not connect to SMTP host: $errstr ($errno)");

        $getResponse = function($socket) {
            $response = "";
            stream_set_timeout($socket, 5);
            while ($line = @fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == " ") break;
                $info = stream_get_meta_data($socket);
                if ($info['timed_out']) throw new Exception("SMTP Response Timeout");
            }
            return $response;
        };

        $write = function($socket, $cmd) {
            if (@fwrite($socket, $cmd) === false) throw new Exception("Failed to write to SMTP socket");
        };

        $getResponse($socket);
        $write($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $ehlo_resp = $getResponse($socket);

        // Try STARTTLS if on 587
        if ($port == 587 && strpos($ehlo_resp, 'STARTTLS') !== false) {
            $write($socket, "STARTTLS\r\n");
            $getResponse($socket);
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("STARTTLS failed");
            }
            $write($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $getResponse($socket);
        }

        if (!empty($user) && !empty($pass)) {
            $write($socket, "AUTH LOGIN\r\n");
            $getResponse($socket);
            $write($socket, base64_encode($user) . "\r\n");
            $getResponse($socket);
            $write($socket, base64_encode($pass) . "\r\n");
            $getResponse($socket);
        }

        $write($socket, "MAIL FROM: <$from>\r\n");
        $getResponse($socket);
        $write($socket, "RCPT TO: <$to>\r\n");
        $getResponse($socket);
        $write($socket, "DATA\r\n");
        $getResponse($socket);

        $headers = "To: $to\r\n";
        $headers .= "From: $name <$from>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";

        $write($socket, $headers . $message . "\r\n.\r\n");
        $getResponse($socket);
        $write($socket, "QUIT\r\n");
        @fclose($socket);
        return true;
    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        return false;
    }
}

function render_email_template($content, $subtitle = 'Intelligence Protocol Active') {
    $settings = get_settings();
    $site_name = $settings['name'] ?? 'FOOTBALL INTELLIGENCE';
    $year = date('Y');

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { margin: 0; padding: 0; background-color: #05070a; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
            .wrapper { width: 100%; table-layout: fixed; background-color: #05070a; padding-bottom: 40px; }
            .main { background-color: #0a0e17; margin: 0 auto; width: 100%; max-width: 600px; border-spacing: 0; color: #ffffff; }
            .header { background-color: #000000; padding: 40px; text-align: center; border-bottom: 2px solid #ff3e3e; }
            .content { padding: 40px; }
            .news-item { margin-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 30px; }
            .news-title { font-size: 24px; font-weight: bold; color: #ffffff; text-decoration: none; line-height: 1.3; text-transform: uppercase; font-style: italic; display: block; }
            .news-excerpt { font-size: 16px; color: #a0aec0; line-height: 1.6; margin: 15px 0; }
            .btn { display: inline-block; background-color: #ff3e3e; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
            .footer { padding: 30px; text-align: center; font-size: 12px; color: #4a5568; }
            p { margin: 0 0 20px; }
            a { color: #ff3e3e; text-decoration: none; }
        </style>
    </head>
    <body>
        <center class='wrapper'>
            <table class='main' width='100%'>
                <tr>
                    <td class='header'>
                        <h1 style='margin:0; color:#ffffff; letter-spacing:-1px; text-transform:uppercase; font-style:italic; font-size: 32px;'>$site_name</h1>
                        <p style='margin:10px 0 0; color:#ff3e3e; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:2px;'>$subtitle</p>
                    </td>
                </tr>
                <tr>
                    <td class='content'>
                        $content
                    </td>
                </tr>
                <tr>
                    <td class='footer'>
                        <p>&copy; $year $site_name. All Systems Secure.</p>
                        <p>This is an automated encrypted transmission from the core network.</p>
                    </td>
                </tr>
            </table>
        </center>
    </body>
    </html>";
}

function log_activity($message) {
    $settings = get_settings();
    $site_name = $settings['name'] ?? 'Football Intelligence';
    if (!empty($settings['admin_email'])) {
        $html = render_email_template("<p>$message</p>", "Security Alert");
        send_mail($settings['admin_email'], $site_name . " System Alert", $html);
    }
}

/**
 * Notifies all subscribers about new intelligence reports.
 * @param array $post_ids
 * @return void
 */
function notify_subscribers($post_ids) {
    if (empty($post_ids)) return;

    $conn = get_db_connection();
    if (!$conn) return;

    // Fetch posts
    $placeholders = implode(',', array_fill(0, count($post_ids), '?'));
    $stmt = $conn->prepare("SELECT title, slug, excerpt FROM posts WHERE id IN ($placeholders)");
    $stmt->execute($post_ids);
    $posts = $stmt->fetchAll();

    if (empty($posts)) return;

    // Fetch subscribers
    $subscribers = $conn->query("SELECT email FROM subscribers")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($subscribers)) return;

    $settings = get_settings();
    $site_name = $settings['name'] ?? 'Football Intelligence';

    $subject = "Intelligence Alert: New Reports Published - " . $site_name;

    $content = "<p style='font-size:18px; color:#ff3e3e; font-weight:bold; margin-bottom:30px; text-transform:uppercase;'>New Intelligence Reports:</p>";

    foreach ($posts as $post) {
        $post_url = SITE_URL . "/post/" . $post['slug'];
        $content .= "
            <div class='news-item'>
                <a href='$post_url' class='news-title'>{$post['title']}</a>
                <p class='news-excerpt'>{$post['excerpt']}</p>
                <a href='$post_url' class='btn'>Read Full Report</a>
            </div>
        ";
    }

    $message = render_email_template($content, "New Intelligence Dispatch");

    foreach ($subscribers as $email) {
        send_mail($email, $subject, $message);
    }
}

function get_ai_insight($prompt) {
    $settings = get_settings();
    $model = $settings['selected_model'] ?? 'deepseek-chat';
    $apiKey = $settings['deepseek_api_key'] ?? '';

    // If model is legacy (Gemini/Groq), force to DeepSeek
    if (strpos($model, 'gemini') !== false || strpos($model, 'groq') !== false) {
        $model = 'deepseek-chat';
    }

    if (empty($apiKey)) return "DeepSeek API Key missing.";

    $url = "https://api.deepseek.com/chat/completions";
    $data = [
        "model" => $model,
        "messages" => [["role" => "user", "content" => $prompt]],
        "max_tokens" => 8192
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    $response = curl_exec($ch);
    $curl_err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }

    if (isset($result['error'])) {
        return "AI Error: DeepSeek - " . ($result['error']['message'] ?? 'Unknown');
    }

    if (!empty($curl_err)) {
        return "AI Error: Connection failed - " . $curl_err;
    }

    return "AI Error: Intelligence gathering failed (HTTP $httpCode). Response: " . substr($response, 0, 100);
}

/**
 * Centralized registry of sports news RSS feeds.
 * @return array
 */
function get_rss_feed_urls() {
    return [
        'https://www.skysports.com/rss/12040', // Sky Sports Football
        'https://www.espn.com/espn/rss/soccer/news', // ESPN Soccer
        'https://www.bbc.com/sport/football/rss.xml', // BBC Football
        'https://www.theguardian.com/football/rss', // The Guardian Football
        'https://sport.sky.ch/feed', // Sky Sport CH (Euro focus)
        'https://www.france24.com/en/sports/rss', // France24 Sports
        'https://talksport.com/football/feed/', // TalkSport Football
        'https://www.caughtoffside.com/feed/', // CaughtOffside (Transfer Rumours)
        'https://www.football-espana.net/feed', // Football Espana
        'https://www.football-italia.net/feed', // Football Italia
        'https://news.google.com/rss/search?q=football+transfers+premier+league+la+liga+serie+a+ligue+1+bundesliga&hl=en-GB&gl=GB&ceid=GB:en' // Google News Football Search
    ];
}

/**
 * Fetches and parses RSS/Atom feeds from multiple sources.
 * @param array $urls
 * @return array
 */
function get_rss_news($urls) {
    $all_items = [];
    $seen_links = [];

    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        $xml_data = curl_exec($ch);
        curl_close($ch);

        if (!$xml_data) continue;

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string(trim($xml_data), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) {
                libxml_clear_errors();
                continue;
            }

            // Handle standard RSS <item> and Atom <entry>
            $items = $xml->xpath('//item') ?: $xml->xpath('//atom:entry') ?: $xml->xpath('//entry');
            if (!$items) continue;

            foreach ($items as $item) {
                $title = (string)($item->title ?? $item->children('atom', true)->title);
                $link = (string)($item->link['href'] ?? $item->link ?? $item->children('atom', true)->link->attributes()->href);
                if (empty($link)) $link = (string)$item->guid;

                if (empty($title) || empty($link) || in_array($link, $seen_links)) continue;

                $pubDate = (string)($item->pubDate ?? $item->published ?? $item->updated ?? $item->children('dc', true)->date);
                $timestamp = strtotime($pubDate);
                if (!$timestamp) continue;

                // 30 minute window (1800s) for strict real-time relevance as per directive
                if ($timestamp > (time() - 1800)) {
                    $description = (string)($item->description ?? $item->summary ?? $item->content ?? '');
                    $all_items[] = [
                        'title' => trim($title),
                        'description' => strip_tags(trim($description)),
                        'link' => trim($link),
                        'pubDate' => $pubDate,
                        'timestamp' => $timestamp,
                        'source' => parse_url($url, PHP_URL_HOST)
                    ];
                    $seen_links[] = $link;
                }
            }
            libxml_clear_errors();
        } catch (Exception $e) {
            error_log("RSS Parse Error ($url): " . $e->getMessage());
        }
    }

    usort($all_items, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });
    return $all_items;
}


function get_suggested_topics() {
    $today = date('D d M Y');
    $prompt = "Suggest 5 trending football news subjects/headlines for today, $today. Return them as a JSON array of strings ONLY. Example format: [\"Subject 1\", \"Subject 2\"]. Be very specific about current teams, transfers and players.";
    $raw = get_ai_insight($prompt);

    $topics = extract_json($raw, true);
    if (is_array($topics)) return array_slice($topics, 0, 5);

    // Fallback: If no JSON array found, try to split by lines if it looks like a list
    $lines = explode("\n", $raw);
    $topics = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/^\d+\.\s*/', '', $line)); // Remove numbering
        if (!empty($line) && strlen($line) > 10 && count($topics) < 5) {
            $topics[] = $line;
        }
    }

    return $topics;
}

/**
 * Robustly extracts JSON from a string that may contain markdown or other text.
 * @param string $raw
 * @param bool $as_array If true, expects a JSON array. If false, expects a JSON object.
 * @return mixed|null
 */
function extract_json($raw, $as_array = false) {
    $start_char = $as_array ? '[' : '{';
    $end_char = $as_array ? ']' : '}';

    $start_pos = strpos($raw, $start_char);
    $end_pos = strrpos($raw, $end_char);

    if ($start_pos === false || $end_pos === false || $end_pos < $start_pos) {
        return null;
    }

    $json_str = substr($raw, $start_pos, $end_pos - $start_pos + 1);

    // 1. Direct attempt
    $json = json_decode($json_str, true);
    if ($json !== null) return $json;

    // 2. Try to escape literal newlines inside strings
    $escaped = preg_replace_callback('/"([^"\\\\]|\\\\.)*"/u', function($matches) {
        return str_replace(["\n", "\r"], ["\\n", "\\r"], $matches[0]);
    }, $json_str);
    $json = json_decode($escaped, true);
    if ($json !== null) return $json;

    // 3. Last resort: Clean all literal control characters
    $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', '', $json_str);
    $json = json_decode($cleaned, true);
    return $json;
}

/**
 * Robustly fetches an image using cURL and returns the data only if it is a valid image.
 * @param string $url
 * @return string|false
 */
function fetch_image($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode == 200 && strpos($contentType, 'image/') !== false) {
        return $data;
    }

    return false;
}

/**
 * Safely handles image uploads with extension validation and unique renaming.
 * @param array $file The $_FILES element
 * @param string $target_subpath Subdirectory in assets/
 * @return string|false Path to uploaded file relative to root, or false on failure.
 */
function upload_image($file, $target_subpath = 'uploads/') {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return false;

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) return false;

    $target_dir = __DIR__ . "/../assets/" . $target_subpath;
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

    // Unique filename to prevent overwrites and hide original name
    $filename = bin2hex(random_bytes(8)) . "_" . time() . '.' . $ext;
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return "/assets/" . $target_subpath . $filename;
    }

    return false;
}

// Basic Markdown to HTML
function parse_markdown($text) {
    if (empty($text)) return '';
    $text = htmlspecialchars($text);

    // Headers
    $text = preg_replace('/^# (.*$)/m', '<h2 class="h3 font-condensed fw-black text-electric-red mt-4 mb-3 uppercase italic">$1</h2>', $text);
    $text = preg_replace('/^## (.*$)/m', '<h3 class="h4 font-condensed fw-black text-white mt-4 mb-2 uppercase italic">$1</h3>', $text);

    // Bold
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

    // Paragraphs: Split by double newlines and wrap in <p> if not already a block element
    $blocks = explode("\n\n", $text);
    $html_blocks = [];
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;

        // If it doesn't start with a header tag or table, wrap in <p>
        if (!preg_match('/^<(h2|h3|div|table)/i', $block)) {
            $html_blocks[] = '<p class="mb-4 leading-relaxed">' . nl2br($block) . '</p>';
        } else {
            $html_blocks[] = $block;
        }
    }
    $text = implode("\n", $html_blocks);

    // Simple table parser
    if (strpos($text, '|') !== false) {
        $lines = explode("\n", $text);
        $html = '';
        $inTable = false;
        foreach ($lines as $line) {
            if (trim($line) && strpos($line, '|') !== false) {
                $cells = array_filter(array_map('trim', explode('|', $line)));
                if (!$inTable) {
                    $html .= '<div class="table-responsive my-4"><table class="table table-dark table-hover mb-0">';
                    $html .= '<thead><tr>';
                    foreach ($cells as $c) $html .= "<th>$c</th>";
                    $html .= '</tr></thead><tbody>';
                    $inTable = true;
                } else {
                    if (strpos($line, '---') === false) {
                        $html .= '<tr>';
                        foreach ($cells as $c) $html .= "<td>$c</td>";
                        $html .= '</tr>';
                    }
                }
            } else {
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                if (trim($line)) $html .= "<p>$line</p>";
            }
        }
        if ($inTable) $html .= '</tbody></table></div>';
        return $html;
    }
    return $text;
}

/**
 * Generates and updates the sitemap.xml file.
 */
function update_sitemap() {
    $settings = get_settings();
    $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://goalzaza.com';
    $conn = get_db_connection();
    if (!$conn) return;

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;

    $now = date('c');

    // Homepage
    $xml .= '  <url>' . PHP_EOL;
    $xml .= '    <loc>' . $site_url . '/</loc>' . PHP_EOL;
    $xml .= '    <lastmod>' . $now . '</lastmod>' . PHP_EOL;
    $xml .= '    <priority>1.00</priority>' . PHP_EOL;
    $xml .= '  </url>' . PHP_EOL;

    // Static / Core Pages
    $core_pages = ['watch', 'tables', 'privacy-policy', 'about-us'];
    foreach ($core_pages as $cp) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . $site_url . '/' . $cp . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . $now . '</lastmod>' . PHP_EOL;
        $xml .= '    <priority>0.80</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }

    // CMS Pages
    $pages = $conn->query("SELECT slug, created_at FROM pages WHERE is_visible = 1 AND is_external = 0")->fetchAll();
    foreach ($pages as $p) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . $site_url . '/' . $p['slug'] . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . date('c', strtotime($p['created_at'])) . '</lastmod>' . PHP_EOL;
        $xml .= '    <priority>0.70</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }

    // Categories
    $categories = $conn->query("SELECT slug FROM categories")->fetchAll();
    foreach ($categories as $cat) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . $site_url . '/category/' . $cat['slug'] . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . $now . '</lastmod>' . PHP_EOL;
        $xml .= '    <priority>0.60</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }

    // Authors
    $authors = $conn->query("SELECT username FROM users")->fetchAll();
    foreach ($authors as $a) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . $site_url . '/author/' . urlencode($a['username']) . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . $now . '</lastmod>' . PHP_EOL;
        $xml .= '    <priority>0.50</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }

    // Posts
    $posts = $conn->query("SELECT slug, publish_date FROM posts WHERE is_scheduled = 0 OR publish_date <= CURRENT_TIMESTAMP ORDER BY publish_date DESC")->fetchAll();
    foreach ($posts as $post) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . $site_url . '/post/' . $post['slug'] . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . date('c', strtotime($post['publish_date'])) . '</lastmod>' . PHP_EOL;
        $xml .= '    <priority>0.50</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }

    $xml .= '</urlset>';

    file_put_contents(__DIR__ . '/../sitemap.xml', $xml);
}