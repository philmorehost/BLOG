<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Running AI Visibility (llms.txt / llms-full.txt) Test...\n";

$conn = get_db_connection();

// Insert unique test post and test page
$test_slug = "ai-test-post-" . time();
$test_page_slug = "ai-test-page-" . time();

$stmt1 = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, category, author) VALUES (?, ?, ?, ?, ?, ?)");
$stmt1->execute(["AI Test Article Title", $test_slug, "An excerpt for AI test", "Full body content for AI test article.", "Football News", "AI Tester"]);

$stmt2 = $conn->prepare("INSERT INTO pages (title, slug, content, is_visible, position) VALUES (?, ?, ?, ?, ?)");
$stmt2->execute(["AI Test Page Title", $test_page_slug, "Page content for AI test", 1, "main"]);

// Trigger sitemap/LLM generation
update_sitemap();

// Check llms.txt content
$llms_txt = file_get_contents(__DIR__ . '/../llms.txt');
assert(strpos($llms_txt, "AI Test Article Title") !== false, "llms.txt should contain new post title.");
assert(strpos($llms_txt, "AI Test Page Title") !== false, "llms.txt should contain new page title.");
echo "✓ llms.txt auto-fetching posts and pages verified.\n";

// Check llms-full.txt content
$llms_full_txt = file_get_contents(__DIR__ . '/../llms-full.txt');
assert(strpos($llms_full_txt, "AI Test Article Title") !== false, "llms-full.txt should contain new post title.");
assert(strpos($llms_full_txt, "Full body content for AI test article.") !== false, "llms-full.txt should contain post full content.");
echo "✓ llms-full.txt auto-fetching full post/page content verified.\n";

// Clean up
$conn->exec("DELETE FROM posts WHERE slug = '$test_slug'");
$conn->exec("DELETE FROM pages WHERE slug = '$test_page_slug'");
update_sitemap();

echo "ALL AI VISIBILITY TESTS PASSED SUCCESSFULLY!\n";
