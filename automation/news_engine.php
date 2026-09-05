<?php
// Football Intelligence News Automation Engine - AI ONLY (NO NewsAPI)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = get_db_connection();
$settings = get_settings();

$apiKey = $settings['deepseek_api_key'] ?? '';

if (empty($apiKey)) {
    die("Error: DeepSeek API Key missing. Configure it in Admin -> Settings.\n");
}

echo "Starting AI-Powered News Discovery...\n";

// Fetch current categories from DB - Strictly restricted
$available_categories_data = [
    ['name' => 'Football News', 'slug' => 'football-news'],
    ['name' => 'Transfer News', 'slug' => 'transfer-news']
];
$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
$sql = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO categories (name, slug) VALUES (?, ?)" : "INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)";
foreach ($available_categories_data as $cat) {
    $conn->prepare($sql)->execute([$cat['name'], $cat['slug']]);
}
$cat_list = 'Football News, Transfer News';

// 1. Discovery Stage: Fetch factual news from RSS Feeds
$today = date('D d M Y H:i');
echo "Stage 1: Discovering factual sports stories from RSS for $today...\n";

$rss_urls = get_rss_feed_urls();

$discovered_items = [];
$rss_results = get_rss_news($rss_urls);

if (!empty($rss_results)) {
    echo "Using RSS Feeds for 100% factual discovery...\n";
    $seen_descriptions = [];
    foreach ($rss_results as $res) {
        if (count($discovered_items) >= 60) break;

        // Immediate rejection of non-European football / American sports content
        $lower_title = strtolower($res['title']);
        $lower_desc = strtolower($res['description']);

        $banned = ['nfl', 'nba', 'mlb', 'nhl', 'mls', 'baseball', 'basketball', 'college football', 'nascar', 'wnba', 'cricket', 'rugby', 'golf', 'tennis', 'f1 ', 'formula 1', 'boxing', 'ufc', 'mma', 'horse racing', 'super bowl', 'playoffs', 'touchdown', 'homerun', 'yankees', 'lakers', 'ncaa', 'gridiron', 'quarterback', 'field goal', 'world series', 'stanley cup', 'nba finals', 'indiana jones', 'hollywood', 'broadway'];
        $is_banned = false;
        foreach ($banned as $b) {
            if (strpos($lower_title, $b) !== false || strpos($lower_desc, $b) !== false) {
                $is_banned = true;
                break;
            }
        }
        if ($is_banned) continue;

        // Content Deduplication via Description Hash
        $desc_hash = md5($res['description']);
        if (in_array($desc_hash, $seen_descriptions)) continue;
        $seen_descriptions[] = $desc_hash;

        $discovered_items[] = [
            'title' => $res['title'],
            'description' => $res['description'],
            'source_link' => $res['link'],
            'category' => 'Football News',
            'image_keyword' => $res['title']
        ];
    }
}

if (empty($discovered_items)) {
    die("Intelligence Notice: No new European football reports published in the last 30 minutes across monitoring channels. Monitoring continues...\n");
}

// 1.5 Deduplication Stage: Use AI to identify and remove redundant stories
echo "Stage 1.5: Filtering redundant stories via AI...\n";
echo "Stage 1.1: Pre-filtering existing database content...\n";
$filtered_discovery = [];
foreach ($discovered_items as $item) {
    $safe_title = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $item['title'])));
    $title_prefix = substr($item['title'], 0, 20) . '%';

    $check = $conn->prepare("SELECT id FROM posts WHERE title = ? OR slug LIKE ? OR source_url = ? OR title LIKE ?");
    $check->execute([$item['title'], $safe_title . '%', $item['source_link'], $title_prefix]);
    if ($check->fetch()) {
        echo "Pre-filtered duplicate: " . $item['title'] . "\n";
        continue;
    }
    $filtered_discovery[] = $item;
}
$discovered_items = $filtered_discovery;

echo "Stage 1.2: Streamlined League & Cross-Run Filtering via AI...\n";
// Fetch recent headlines from DB to prevent cross-run duplication
$recent_db_posts = $conn->query("SELECT title FROM posts ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
$db_headlines_str = implode("\n", array_map(function($t) { return "- " . $t; }, $recent_db_posts));

$headlines_to_filter = "";
foreach ($discovered_items as $idx => $item) {
    $headlines_to_filter .= "$idx: {$item['title']}\n";
}

$filter_prompt = "Act as a Content Curator for a European Football Intelligence Network.
I have a list of discovered headlines from RSS feeds.
You MUST filter this list and return a JSON array of indices (integers) that I should KEEP.

STRICT FILTERING RULES:
1. KEEP only news related to top-tier European Football (Premier League, La Liga, Serie A, Ligue 1, Bundesliga, Champions League, Europa League, Conference League) and associated transfers.
2. STERNLY REMOVE all American sports content (NFL, NBA, MLB, NHL, MLS) and minor leagues.
3. ABSOLUTELY REMOVE headlines that refer to the SAME event or story already present in our database (cross-check against recent headlines provided).
4. If multiple headlines in the current list refer to the SAME story, KEEP only the ONE most descriptive one.
5. If a headline is about generic sports, multi-sport events, or non-football topics, REMOVE it.

RECENT DATABASE HEADLINES (ALREADY PUBLISHED):
$db_headlines_str

DISCOVERED HEADLINES TO FILTER:
$headlines_to_filter

Return ONLY a valid JSON array of integers. Example: [0, 3, 4]";

$raw_filter = get_ai_insight($filter_prompt);
$keep_indices = extract_json($raw_filter, true);

if (is_array($keep_indices)) {
    $filtered_items = [];
    foreach ($keep_indices as $idx) {
        if (isset($discovered_items[$idx])) {
            $filtered_items[] = $discovered_items[$idx];
        }
    }
    $discovered_items = $filtered_items;
    echo "AI Filtering complete. " . count($discovered_items) . " high-quality unique stories remaining.\n";
}

$date_path = date('Y/m/d');
$upload_dir = __DIR__ . "/../assets/uploads/news/" . $date_path . "/";
$web_dir = "/assets/uploads/news/" . $date_path . "/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$count = 0;
$loop_idx = 0;
$fetched_hashes = [];
$published_post_ids = [];
foreach ($discovered_items as $item) {
    $loop_idx++;
    if ($count >= 10) break;

    // Skip if already exists or similar slug found (prevent duplicates)
    $safe_title = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $item['title'])));
    $source_url = $item['source_link'] ?? '';

    // Robust check: Exact title, Slug prefix, Source URL, or Title prefix (first 25 chars)
    $title_prefix = substr($item['title'], 0, 25) . '%';

    $check_stmt = $conn->prepare("SELECT id FROM posts WHERE title = ? OR slug LIKE ? OR (source_url != '' AND source_url = ?) OR title LIKE ?");
    $check_stmt->execute([$item['title'], $safe_title . '%', $source_url, $title_prefix]);
    if ($check_stmt->fetch()) {
        echo "Skipping existing or duplicate post: " . $item['title'] . "\n";
        continue;
    }

    echo "\n--- Processing Story $loop_idx: " . $item['title'] . " ---\n";

    // Stage 2: Content Generation for this specific story
    echo "Stage 2: Factual Rewriting and SEO metadata generation...\n";
    $target_cat = $item['category'];
    $content_prompt = "Act as a Senior European Football Columnist for '{$settings['name']}'. You are an expert analyst with a deep understanding of the tactical and emotional nuances of the beautiful game.

    SOURCE DATA:
    Headline: '{$item['title']}'
    Factual Summary: '{$item['description']}'

    STYLE: Professional British Standard English. Authoritative, insightful, and highly engaging. Think of a blend between a high-end broadsheet sports page and an expert fan-led editorial.

    STRICT LINGUISTIC GUIDELINES (0% AI DETECTION - 100% HUMAN):
    1. REWRITE THE TITLE: Create a strong, punchy, and professional headline. Avoid clichés.
    2. WHITE-LABEL: Replace ALL mentions of external sources (BBC, Sky, ESPN, etc.) with '{$settings['name']}'.
    3. VOCABULARY: Use 'Football' (never soccer), 'Pitch' (not field), 'Kit' (not uniform). Use expert terminology: 'low block', 'transitional play', 'clinical finishing', 'tactical flexibility'.
    4. PERSPECTIVE: Write as an insider. Use occasional rhetorical questions to engage the reader.
    5. PUNCTUATION: Use flowing prose and proper sentence breaks. ABSOLUTELY NO em-dashes (—/–), NO HYPHENS (-), and NO AI-style bullet points.
    6. STRUCTURE: Organize the content into 3-6 clearly defined paragraphs. Use DOUBLE NEWLINES (\n\n) between paragraphs.
    7. SENTENCE VARIETY: Vary sentence lengths and structures significantly. Mix short, impactful sentences with longer, more detailed observations (burstiness).
    7. HUMAN TOUCH: Use colloquialisms common in football culture (e.g., 'bottled it', 'in the mixer', 'squeaky bum time', 'parked the bus') sparingly but effectively to establish authenticity.
    8. NO HALLUCINATIONS: Do not add external facts not present in the summary, but you MAY add expert analysis and fan-perspective commentary based ONLY on the provided summary.

    BANNED PHRASES/AI TELLS (STRICTLY FORBIDDEN):
    - NO: 'pivotal moment', 'vital role', 'testament', 'underscores', 'evolving landscape', 'indelible mark', 'shaping the', 'setting the stage', 'tapestry', 'delve', 'unleash', 'comprehensive', 'ultimate guide'.
    - NO '-ing' depth: 'highlighting...', 'symbolizing...', 'reflecting...', 'showcasing...'.
    - NO Ad-speak: 'groundbreaking', 'transformative', 'cutting-edge', 'seamless', 'robust', 'world-class'.
    - NO Vague attributions: 'experts say', 'it has been reported'.
    - NO Copula avoidance: Do not use 'serves as', 'functions as', 'stands as'. Just use 'is' or 'are'.
    - NO Filler: 'At its core', 'In today\'s world', 'It\'s worth noting', 'Needless to say', 'That being said'.
    - NO Signposting: 'Let\'s dive in', 'Without further ado'.
    - NO generic closings: 'The future looks bright', 'Exciting times lie ahead'.

    CATEGORY SELECTION:
    - Categorize strictly into one of: ($cat_list).

    Return ONLY a valid JSON object with:
    - 'title': The professional, rewritten headline.
    - 'category': Chosen category.
    - 'content': Rewritten expert report (flowing prose, no em-dashes or hyphens).
    - 'tags': 6-10 SEO tags.
    - 'meta_title': Professional invitation to read.
    - 'meta_description': Concise, punchy summary for search engines.

    No other text.";

    $raw_content = get_ai_insight($content_prompt);
    if (!$raw_content || strpos($raw_content, 'AI Error:') === 0) {
        echo "Error: Content generation failed for this item. AI Response: " . ($raw_content ?: 'Empty') . ". Skipping.\n";
        continue;
    }

    $content_data = extract_json($raw_content, false);
    if (!$content_data) {
        echo "Error: Could not parse content data. Skipping.\n";
        continue;
    }

    // 3. Fetch Image - Multi-Source Unique Discovery
    $specific_keyword = urlencode($item['image_keyword'] . " " . rand(100, 999));
    $category_keyword = urlencode($item['category'] . " " . $item['title']);

    $image_sources = [];
    if (!empty($item['image_url'])) $image_sources[] = $item['image_url'];

    $image_sources[] = "https://tse1.mm.bing.net/th?q=" . $specific_keyword . "&w=1200&h=800&c=7&rs=1&p=0&dpr=1&pid=Api";
    $image_sources[] = "https://tse1.mm.bing.net/th?q=" . urlencode($item['title'] . " sports photography") . "&w=1200&h=800&c=7&rs=1&p=0&dpr=1&pid=Api";
    $image_sources[] = "https://loremflickr.com/1200/800/" . urlencode(str_replace(' ', ',', $item['image_keyword'])) . "/all?lock=" . rand(1, 99999);
    $image_sources[] = "https://tse1.mm.bing.net/th?q=" . $category_keyword . "&w=1200&h=800&c=7&rs=1&p=0&dpr=1&pid=Api";

    $img_data = null;
    foreach ($image_sources as $source_url) {
        echo "Attempting image fetch from source...\n";
        $temp_data = fetch_image($source_url);
        if ($temp_data && strlen($temp_data) > 8000) {
            $temp_hash = md5($temp_data);
            if (!in_array($temp_hash, $fetched_hashes)) {
                $img_data = $temp_data;
                $fetched_hashes[] = $temp_hash;
                echo "Unique image binary acquired.\n";
                break;
            }
        }
    }

    sleep(1);

    $filename = $safe_title . "-" . time() . ".jpg";
    $local_img_path = $upload_dir . $filename;
    $db_img_path = $web_dir . $filename;

    if ($img_data) {
        file_put_contents($local_img_path, $img_data);
        echo "Saved unique image for: " . $item['title'] . "\n";
    } else {
        echo "Failed to get unique image. Using default.\n";
        $db_img_path = "/assets/img/default-news.jpg";
    }

    // 4. White-Label Post-Processing (PHP Safety Sweep)
    $site_name = $settings['name'] ?? 'The Sports Network';
    $banned_sources = [
        'BBC Sport', 'BBC', 'Sky Sports', 'Sky Sport', 'Sky', 'ESPN FC', 'ESPN', 'SuperSport',
        'France 24', 'France24', 'TalkSport', 'CaughtOffside', 'Football Espana', 'Football Italia',
        'The Guardian', 'The Sun', 'Daily Mail', 'Mirror Sport', 'MARCA', 'AS.com', 'Gazzetta'
    ];

    $generated_title = clean_utf8($content_data['title'] ?? $item['title']);
    $generated_content = clean_utf8($content_data['content']);

    foreach ($banned_sources as $source) {
        $generated_title = str_ireplace($source, $site_name, $generated_title);
        $generated_content = str_ireplace($source, $site_name, $generated_content);
    }

    // Punctuation Cleanup (Remove AI-style em-dashes, hyphens and fix spacing)
    // Replace all dash variations with proper punctuation or spaces
    $generated_title = preg_replace('/(\s*[\-\–\—]\s*)/u', ' ', $generated_title);
    $generated_content = preg_replace('/(\s*[\-\–\—]\s*)/u', '. ', $generated_content);

    // Remove stray '?' that often appear from encoding errors
    $generated_title = str_replace('?', '', $generated_title);
    $generated_content = str_replace('?', '', $generated_content);

    $generated_content = str_replace(['. .', '. . '], '. ', $generated_content);
    // Standardize newlines and then remove excess but keep double newlines for paragraphs
    $generated_content = str_replace("\r", "", $generated_content);
    $generated_content = preg_replace("/\n{3,}/", "\n\n", $generated_content);

    // 4. Save to Database
    $title = sanitize($generated_title);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();
    $content = $generated_content;
    $excerpt = sanitize(substr(strip_tags($content), 0, 150)) . '...';
    $category = $content_data['category'] ?? $item['category'];
    $author = $settings['name'] ?? 'STAFF';

    $tags = sanitize($content_data['tags'] ?? '');
    $meta_title = sanitize($content_data['meta_title'] ?? $title);
    $meta_desc = sanitize($content_data['meta_description'] ?? $excerpt);
    $meta_keys = sanitize($content_data['meta_keywords'] ?? '');
    $publish_date = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, category, author, image, source_url, is_top_story, publish_date, tags, meta_title, meta_description, meta_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$title, $slug, $excerpt, $content, $category, $author, $db_img_path, $source_url, $publish_date, $tags, $meta_title, $meta_desc, $meta_keys])) {
        $post_id = $conn->lastInsertId();
        echo "Successfully published: $title\n";

        echo "Broadcasting to social media...\n";
        broadcast_to_social($post_id);

        echo "Updating sitemap...\n";
        update_sitemap();

        $published_post_ids[] = $post_id;

        $count++;
    } else {
        echo "Database error.\n";
    }
}

echo "\nAI Automation complete. $count posts published.\n";

// 5. Notify Subscribers
if ($count > 0) {
    echo "Notifying subscribers via centralized system...\n";
    notify_subscribers($published_post_ids);
}