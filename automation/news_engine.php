<?php
// BLOGEASY News Automation Engine - High-Performance AI Rewriter & Feed Aggregator
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

// Fetch current categories from DB - General News Focus
$available_categories_data = [
    ['name' => 'Nigeria News', 'slug' => 'nigeria-news'],
    ['name' => 'World News', 'slug' => 'world-news'],
    ['name' => 'Politics', 'slug' => 'politics'],
    ['name' => 'Business', 'slug' => 'business'],
    ['name' => 'Sports', 'slug' => 'sports'],
    ['name' => 'Entertainment', 'slug' => 'entertainment'],
    ['name' => 'Tech', 'slug' => 'tech']
];
$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
$sql = ($driver === 'sqlite') ? "INSERT OR IGNORE INTO categories (name, slug) VALUES (?, ?)" : "INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)";
foreach ($available_categories_data as $cat) {
    $conn->prepare($sql)->execute([$cat['name'], $cat['slug']]);
}
$cat_list = 'Nigeria News, World News, Politics, Business, Sports, Entertainment, Tech';

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

        $lower_title = strtolower($res['title']);
        $lower_desc = strtolower($res['description']);

        // Content Deduplication via Description Hash & Title Hash
        $desc_hash = md5(trim($res['title'] . $res['description']));
        if (in_array($desc_hash, $seen_descriptions)) continue;
        $seen_descriptions[] = $desc_hash;

        // Categorize based on source / keywords
        $category = 'World News';
        if (strpos($res['source'], 'vanguard') !== false || strpos($res['source'], 'punch') !== false || strpos($res['source'], 'premiumtimes') !== false || strpos($res['source'], 'dailytrust') !== false || strpos($res['source'], 'channels') !== false || strpos($res['source'], 'guardian.ng') !== false || strpos($lower_title, 'nigeria') !== false || strpos($lower_desc, 'nigeria') !== false || strpos($lower_title, 'tinubu') !== false) {
            $category = 'Nigeria News';
        } elseif (strpos($lower_title, 'election') !== false || strpos($lower_title, 'politics') !== false || strpos($lower_title, 'senate') !== false || strpos($lower_title, 'governor') !== false || strpos($lower_title, 'president') !== false) {
            $category = 'Politics';
        } elseif (strpos($lower_title, 'business') !== false || strpos($lower_title, 'naira') !== false || strpos($lower_title, 'dollar') !== false || strpos($lower_title, 'economy') !== false || strpos($lower_title, 'bank') !== false || strpos($lower_title, 'market') !== false) {
            $category = 'Business';
        } elseif (strpos($lower_title, 'football') !== false || strpos($lower_title, 'match') !== false || strpos($lower_title, 'league') !== false || strpos($lower_title, 'cup') !== false || strpos($lower_title, 'super eagles') !== false) {
            $category = 'Sports';
        } elseif (strpos($lower_title, 'movie') !== false || strpos($lower_title, 'music') !== false || strpos($lower_title, 'afrobeats') !== false || strpos($lower_title, 'nollywood') !== false || strpos($lower_title, 'actor') !== false) {
            $category = 'Entertainment';
        } elseif (strpos($lower_title, 'tech') !== false || strpos($lower_title, 'ai ') !== false || strpos($lower_title, 'google') !== false || strpos($lower_title, 'apple') !== false || strpos($lower_title, 'crypto') !== false) {
            $category = 'Tech';
        }

        $discovered_items[] = [
            'title' => $res['title'],
            'description' => $res['description'],
            'source_link' => $res['link'],
            'category' => $category,
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

$filter_prompt = "Act as Chief Content Curator for BLOGEASY General News Network.
I have a list of discovered news headlines from various RSS feeds (Nigeria News, World News, Politics, Business, Sports, Entertainment, Tech).
You MUST filter this list and return a JSON array of indices (integers) that I should KEEP.

STRICT FILTERING RULES:
1. ABSOLUTELY REMOVE duplicate stories or headlines that refer to the SAME event already covered in the recent database headlines.
2. If multiple headlines in the current list refer to the SAME story or event (even from different RSS feeds), KEEP only the ONE most comprehensive headline and discard the rest.
3. REMOVE low-quality clickbait or non-news promotional headlines.
4. ENSURE a rich mix of Nigeria News, World News, Business, Tech, Politics, and Sports.

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
    $content_prompt = "Act as a Senior Investigative Journalist and Lead Editor for '{$settings['name']}'. You are writing a 100% humanized, original news report based on raw factual summary input.

    SOURCE DATA:
    Headline: '{$item['title']}'
    Factual Summary: '{$item['description']}'

    STYLE & TONE: Standard Journalism. Objective, authoritative, highly engaging, and clear. Write like a veteran journalist from Reuters, Associated Press, or Punch Newspaper.

    STRICT LINGUISTIC GUIDELINES (0% AI DETECTION - 100% HUMAN):
    1. REWRITE THE TITLE: Create a brand new, compelling, non-word-for-word headline that accurately captures the story without plagiarizing the original.
    2. WHITE-LABEL & ORIGINAL REPORTING: Replace ALL mentions of external sources (BBC, Vanguard, Punch, CNN, Al Jazeera, Reuters, etc.) with '{$settings['name']}' or general phrases like 'official statements' / 'reports'.
    3. NO WORD-FOR-WORD PLAGIARISM: Completely rephrase every single sentence. Do not copy exact sentences from the summary.
    4. PUNCTUATION: Use clean prose. ABSOLUTELY NO em-dashes (—/–), NO hyphens (-), and NO AI-style bullet points or markdown lists.
    5. STRUCTURE: Organize into 4-6 detailed, well-developed paragraphs. Use DOUBLE NEWLINES (\n\n) between paragraphs.
    6. SENTENCE VARIETY: Mix short impactful statements with longer detailed explanations (burstiness and perplexity).
    7. ACCURACY: Ensure key entities, names, locations, and factual events match 100% with the provided summary.

    BANNED PHRASES/AI TELLS (STRICTLY FORBIDDEN):
    - NO: 'pivotal moment', 'vital role', 'testament', 'underscores', 'evolving landscape', 'indelible mark', 'shaping the', 'setting the stage', 'tapestry', 'delve', 'unleash', 'comprehensive', 'ultimate guide'.
    - NO Filler: 'At its core', 'In today\'s world', 'It\'s worth noting', 'Needless to say', 'That being said'.
    - NO Signposting: 'Let\'s dive in', 'Without further ado'.
    - NO generic closings: 'The future looks bright', 'Exciting times lie ahead'.

    CATEGORY SELECTION:
    - Categorize strictly into one of: ($cat_list). Default to '{$target_cat}'.

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

    // 3. Fetch Image - High Accuracy Keyword Matching
    // Extract key nouns/entities from headline for 100% accurate image query
    $clean_title = preg_replace('/[^A-Za-z0-9\s]/', '', $generated_title);
    $title_words = array_filter(explode(' ', $clean_title), function($w) {
        return strlen($w) > 3 && !in_array(strtolower($w), ['this', 'that', 'with', 'from', 'have', 'more', 'about', 'after', 'over', 'into', 'under', 'will']);
    });
    $top_keywords = implode(' ', array_slice($title_words, 0, 4));

    $specific_keyword = urlencode($top_keywords . " " . $item['category'] . " news");

    $image_sources = [];
    if (!empty($item['image_url'])) $image_sources[] = $item['image_url'];

    $image_sources[] = "https://tse1.mm.bing.net/th?q=" . $specific_keyword . "&w=1200&h=800&c=7&rs=1&p=0&dpr=1&pid=Api";
    $image_sources[] = "https://tse1.mm.bing.net/th?q=" . urlencode(implode(' ', array_slice($title_words, 0, 3)) . " photography") . "&w=1200&h=800&c=7&rs=1&p=0&dpr=1&pid=Api";
    $image_sources[] = "https://loremflickr.com/1200/800/" . urlencode(implode(',', array_slice($title_words, 0, 2))) . "/all?lock=" . rand(1, 99999);

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