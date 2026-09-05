<?php
admin_header("Post");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed");
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    $success = "Report decommissioned.";
}

// Handle Bulk Deletion
if (isset($_POST['bulk_delete']) && !empty($_POST['selected_posts'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed");
    }
    $ids = array_map('intval', $_POST['selected_posts']);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $success = count($ids) . " reports decommissioned in bulk.";
    }
}

// Handle Manual Post Submission
if (isset($_POST['save_manual'])) {
    $title = sanitize($_POST['title']);
    $cat = sanitize($_POST['cat']);
    $content = $_POST['content'];

    // Auto-generate excerpt: first 150 chars
    $excerpt = sanitize(substr(strip_tags($content), 0, 150)) . '...';

    $image = sanitize($_POST['image']);
    $author = sanitize($_POST['author'] ?? 'STAFF');

    $tags = sanitize($_POST['tags'] ?? '');
    $meta_title = sanitize($_POST['meta_title'] ?? '');
    $meta_desc = sanitize($_POST['meta_description'] ?? '');
    $meta_keys = sanitize($_POST['meta_keywords'] ?? '');
    $is_top = isset($_POST['is_top_story']) ? 1 : 0;
    $video_url = sanitize($_POST['video_url'] ?? '');

    $is_scheduled = !empty($_POST['publish_date']) ? 1 : 0;
    $publish_date = !empty($_POST['publish_date']) ? $_POST['publish_date'] : date('Y-m-d H:i:s');

    // Handle Image Upload
    $uploaded_image = upload_image($_FILES['image_file']);
    if ($uploaded_image) {
        $image = $uploaded_image;
    }

    $slug = strtolower(str_replace(' ', '-', $title)) . '-' . time();
    $stmt = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, category, author, image, video_url, is_scheduled, publish_date, tags, meta_title, meta_description, meta_keywords, is_top_story) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$title, $slug, $excerpt, $content, $cat, $author, $image, $video_url, $is_scheduled, $publish_date, $tags, $meta_title, $meta_desc, $meta_keys, $is_top])) {
        $post_id = $conn->lastInsertId();
        if (!$is_scheduled || strtotime($publish_date) <= time()) {
            broadcast_to_social($post_id);
            notify_subscribers([$post_id]);
            update_sitemap();
            $success = "Intelligence report deployed and broadcasted.";
        } else {
            $success = "Intelligence report scheduled for $publish_date.";
        }
    } else {
        $error = "Failed to deploy report.";
    }
}

// Handle Manual Update
if (isset($_POST['update_manual'])) {
    $id = (int)$_POST['post_id'];
    $title = sanitize($_POST['title']);
    $cat = sanitize($_POST['cat']);
    $content = $_POST['content'];
    $excerpt = sanitize(substr(strip_tags($content), 0, 150)) . '...';
    $author = sanitize($_POST['author'] ?? 'STAFF');
    $image = sanitize($_POST['image']);

    $tags = sanitize($_POST['tags'] ?? '');
    $meta_title = sanitize($_POST['meta_title'] ?? '');
    $meta_desc = sanitize($_POST['meta_description'] ?? '');
    $meta_keys = sanitize($_POST['meta_keywords'] ?? '');
    $is_top = isset($_POST['is_top_story']) ? 1 : 0;
    $video_url = sanitize($_POST['video_url'] ?? '');

    $is_scheduled = !empty($_POST['publish_date']) ? 1 : 0;
    $publish_date = !empty($_POST['publish_date']) ? $_POST['publish_date'] : date('Y-m-d H:i:s');

    $uploaded_image = upload_image($_FILES['image_file']);
    if ($uploaded_image) {
        $image = $uploaded_image;
    }

    $stmt = $conn->prepare("UPDATE posts SET title = ?, excerpt = ?, content = ?, category = ?, author = ?, image = ?, video_url = ?, is_scheduled = ?, publish_date = ?, tags = ?, meta_title = ?, meta_description = ?, meta_keywords = ?, is_top_story = ? WHERE id = ?");
    if ($stmt->execute([$title, $excerpt, $content, $cat, $author, $image, $video_url, $is_scheduled, $publish_date, $tags, $meta_title, $meta_desc, $meta_keys, $is_top, $id])) {
        $success = "Report updated successfully.";
    } else {
        $error = "Failed to update report.";
    }
}

// Handle Auto-generation from AI
if (isset($_POST['generate_ai'])) {
    $topic = sanitize($_POST['topic']);
    $cat = sanitize($_POST['cat']);
    $is_scheduled = !empty($_POST['publish_date']) ? 1 : 0;
    $publish_date = !empty($_POST['publish_date']) ? $_POST['publish_date'] : date('Y-m-d H:i:s');
    $is_top = 1; // AI generated posts are promoted by default

    $prompt = "Act as a Senior European Football Columnist for '{$settings['name']}'. You are an expert analyst with a deep understanding of the tactical and emotional nuances of the beautiful game.
               Generate a professional sports news article about '$topic' in the category '$cat'.

               STYLE: Professional British Standard English. Authoritative, insightful, and highly engaging. Think of a blend between a high-end broadsheet sports page and an expert fan-led editorial.

               STRICT LINGUISTIC GUIDELINES (0% AI DETECTION - 100% HUMAN):
               1. TITLE: Create a strong, punchy, and professional headline. Avoid clichés.
               2. VOCABULARY: Use 'Football' (never soccer), 'Pitch' (not field), 'Kit' (not uniform). Use expert terminology: 'low block', 'transitional play', 'clinical finishing', 'tactical flexibility'.
               3. PERSPECTIVE: Write as an insider. Use occasional rhetorical questions to engage the reader.
               4. PUNCTUATION: Use flowing prose and proper sentence breaks. ABSOLUTELY NO em-dashes (—/–), NO HYPHENS (-), and NO AI-style bullet points.
               5. STRUCTURE: Organize the content into 3-6 clearly defined paragraphs. Use DOUBLE NEWLINES (\n\n) between paragraphs.
               6. SENTENCE VARIETY: Vary sentence lengths and structures significantly. Mix short, impactful sentences with longer, more detailed observations (burstiness).
               6. HUMAN TOUCH: Use colloquialisms common in football culture (e.g., 'bottled it', 'in the mixer', 'squeaky bum time', 'parked the bus') sparingly but effectively to establish authenticity.
               7. NO HALLUCINATIONS: Stick to the factual context of the topic but you MAY add expert analysis and fan-perspective commentary.

               BANNED PHRASES/AI TELLS (STRICTLY FORBIDDEN):
               - NO: 'pivotal moment', 'vital role', 'testament', 'underscores', 'evolving landscape', 'indelible mark', 'shaping the', 'setting the stage', 'tapestry', 'delve', 'unleash', 'comprehensive', 'ultimate guide'.
               - NO '-ing' depth: 'highlighting...', 'symbolizing...', 'reflecting...', 'showcasing...'.
               - NO Ad-speak: 'groundbreaking', 'transformative', 'cutting-edge', 'seamless', 'robust', 'world-class'.
               - NO Filler: 'At its core', 'In today\'s world', 'It\'s worth noting', 'Needless to say', 'That being said'.

               Return JSON with:
               - 'title': The professional, rewritten headline.
               - 'content': Rewritten expert report (flowing prose, no em-dashes or hyphens).
               - 'image_keyword': 3-5 highly specific keywords for an exact image matching this story.
               - 'tags': 6-10 SEO tags.
               - 'meta_title': Professional invitation to read.
               - 'meta_description': Concise, punchy summary for search engines.
               - 'meta_keywords': High ranking keywords for this specific news.
               Ensure the response is a valid JSON object.";
    $raw = get_ai_insight($prompt);

    $data = extract_json($raw, false);

    if ($data && !empty($data['title']) && !empty($data['content'])) {
        $title = sanitize($data['title']);
        $content = $data['content'];
        $excerpt = sanitize(substr(strip_tags($content), 0, 150)) . '...';

        $tags = sanitize($data['tags'] ?? '');
        $meta_title = sanitize($data['meta_title'] ?? $title);
        $meta_desc = sanitize($data['meta_description'] ?? $excerpt);
        $meta_keys = sanitize($data['meta_keywords'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();

        // Handle Image - Highly specific search
        $keyword = urlencode(str_replace(' ', ',', ($data['image_keyword'] ?? $topic)) . ",football,soccer");
        $image_url = "https://loremflickr.com/1200/800/" . $keyword . "/all";

        $target_dir = __DIR__ . "/../assets/uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $img_data = fetch_image($image_url);

        $image_filename = "ai_" . time() . ".jpg";
        $db_image = "/assets/uploads/" . $image_filename;
        if ($img_data) {
            file_put_contents($target_dir . $image_filename, $img_data);
        } else {
            $db_image = "https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&q=80&w=1600";
        }

        // White-Label Post-Processing (PHP Safety Sweep)
        $site_name = $settings['name'] ?? 'The Sports Network';
        $banned_sources = [
            'BBC Sport', 'BBC', 'Sky Sports', 'Sky Sport', 'Sky', 'ESPN FC', 'ESPN', 'SuperSport',
            'France 24', 'France24', 'TalkSport', 'CaughtOffside', 'Football Espana', 'Football Italia',
            'The Guardian', 'The Sun', 'Daily Mail', 'Mirror Sport', 'MARCA', 'AS.com', 'Gazzetta'
        ];

        foreach ($banned_sources as $source) {
            $title = str_ireplace($source, $site_name, $title);
            $content = str_ireplace($source, $site_name, $content);
        }

        $title = clean_utf8($title);
        $content = clean_utf8($content);

        // Punctuation Cleanup (Remove AI-style em-dashes, hyphens and fix spacing)
        $title = preg_replace('/(\s*[\-\–\—]\s*)/u', ' ', $title);
        $content = preg_replace('/(\s*[\-\–\—]\s*)/u', '. ', $content);

        // Remove stray '?' that often appear from encoding errors
        $title = str_replace('?', '', $title);
        $content = str_replace('?', '', $content);

        $content = str_replace(['. .', '. . '], '. ', $content);
        // Standardize newlines and then remove excess but keep double newlines for paragraphs
        $content = str_replace("\r", "", $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        $stmt = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, category, author, image, is_scheduled, publish_date, tags, meta_title, meta_description, meta_keywords, is_top_story) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $slug, $excerpt, $content, $cat, $author, $db_image, $is_scheduled, $publish_date, $tags, $meta_title, $meta_desc, $meta_keys, $is_top])) {
            $post_id = $conn->lastInsertId();
            if (!$is_scheduled || strtotime($publish_date) <= time()) {
                broadcast_to_social($post_id);
                notify_subscribers([$post_id]);
                update_sitemap();
                $success = "AI Intelligence generated, deployed and broadcasted: " . $title;
            } else {
                $success = "AI Intelligence generated and scheduled for $publish_date: " . $title;
            }
        } else {
            $error = "Database insertion failed: " . implode(":", $stmt->errorInfo());
        }
    } else {
        $error = "AI extraction failed. Raw Response: " . htmlspecialchars($raw);
    }
}

// Pagination & Search Logic
$search = sanitize($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = "1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (title LIKE ? OR content LIKE ? OR author LIKE ? OR category LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$total_stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE $where");
$total_stmt->execute($params);
$total_posts = $total_stmt->fetchColumn();
$total_pages = ceil($total_posts / $perPage);

$stmt = $conn->prepare("SELECT * FROM posts WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$posts = $stmt->fetchAll();

$categories = $conn->query("SELECT * FROM categories")->fetchAll();

// Dashboard Stats
try {
    $total_reports = $conn->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $total_comments = $conn->query("SELECT COUNT(*) FROM comments")->fetchColumn();
    $total_subscribers = $conn->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
} catch (Exception $e) {
    $total_reports = $total_comments = $total_subscribers = 0;
}

?>
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="bg-[#0a0e17] rounded-3xl p-4 border border-white/5 shadow-xl">
            <div class="d-flex align-items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-danger/10 flex items-center justify-center">
                    <i class="bi bi-file-earmark-text text-danger fs-3"></i>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase text-gray-500 tracking-widest mb-1">Total Reports</div>
                    <div class="text-3xl font-black text-white italic"><?php echo number_format($total_reports); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-[#0a0e17] rounded-3xl p-4 border border-white/5 shadow-xl">
            <div class="d-flex align-items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center">
                    <i class="bi bi-chat-dots text-primary fs-3"></i>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase text-gray-500 tracking-widest mb-1">Total Comments</div>
                    <div class="text-3xl font-black text-white italic"><?php echo number_format($total_comments); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-[#0a0e17] rounded-3xl p-4 border border-white/5 shadow-xl">
            <div class="d-flex align-items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-success/10 flex items-center justify-center">
                    <i class="bi bi-people text-success fs-3"></i>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase text-gray-500 tracking-widest mb-1">Syndication Network</div>
                    <div class="text-3xl font-black text-white italic"><?php echo number_format($total_subscribers); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 mb-5">
    <div>
        <h1 class="font-condensed fw-black italic text-white display-5 mb-0">POST <span class="text-danger">REGISTRY</span></h1>
        <p class="text-white-50 small font-condensed italic uppercase mb-0"><?php echo $total_posts; ?> Reports Discovered</p>
    </div>
    <div class="d-flex flex-wrap gap-3">
        <form method="GET" class="position-relative">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="SEARCH REPORTS..." class="bg-black border border-white/10 rounded-xl px-4 py-2 text-white font-condensed italic small w-64 focus:border-danger outline-none transition-all">
            <button type="submit" class="position-absolute end-0 top-0 h-100 px-3 text-white-50 hover:text-danger"><i class="bi bi-search"></i></button>
        </form>
        <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger font-condensed fw-black italic px-4 py-2 d-none" onclick="confirmBulkDelete()">BULK DELETE</button>
        <a href="/automation/news_engine.php" target="_blank" class="btn btn-outline-primary font-condensed fw-black italic px-4 py-2">TRIGGER DISCOVERY</a>
        <button class="btn btn-outline-secondary font-condensed fw-black italic px-4 py-2" data-bs-toggle="modal" data-bs-target="#manualModal">CREATE NEW POST</button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success bg-green-900 bg-opacity-10 border-green-500 border-opacity-20 text-green-500 font-condensed italic uppercase mb-5"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger bg-red-900 bg-opacity-10 border-red-500 border-opacity-20 text-red-500 font-condensed italic uppercase mb-5"><?php echo $error; ?></div>
<?php endif; ?>

<div class="bg-[#0a0e17] rounded-3xl border border-white/5 overflow-hidden shadow-2xl">
    <form id="bulkForm" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="bulk_delete" value="1">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead class="bg-black">
                <tr>
                    <th class="ps-5 py-4 border-0" style="width: 40px;">
                        <input type="checkbox" id="selectAll" class="form-check-input bg-black border-white/20">
                    </th>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Report</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Taxonomy</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Operator</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Source</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Timestamp</th>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td class="ps-5 py-4 border-white border-opacity-5">
                        <input type="checkbox" name="selected_posts[]" value="<?php echo $post['id']; ?>" class="form-check-input bg-black border-white/20 post-checkbox">
                    </td>
                    <td class="px-5 py-4 border-white border-opacity-5">
                        <div class="d-flex align-items-center">
                            <img src="<?php echo $post['image']; ?>" class="rounded-2 me-3" style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <div class="text-white font-bold small uppercase italic"><?php echo htmlspecialchars($post['title']); ?></div>
                                <div class="text-[9px] text-white-50 font-monospace opacity-50">/<?php echo htmlspecialchars($post['slug']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <span class="badge bg-white bg-opacity-5 text-white-50 font-condensed italic px-2 py-1"><?php echo strtoupper($post['category']); ?></span>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <span class="text-white-50 small font-bold italic"><?php echo $post['author']; ?></span>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <?php if (!empty($post['source_url'])): ?>
                            <a href="<?php echo $post['source_url']; ?>" target="_blank" class="text-info small font-monospace" style="font-size: 9px;">LINK</a>
                        <?php else: ?>
                            <span class="text-white-50 small italic opacity-30">INTERNAL</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <span class="text-white-50 font-monospace small"><?php echo date('Y-m-d', strtotime($post['publish_date'] ?: $post['created_at'])); ?></span>
                        <?php if ($post['is_scheduled'] && strtotime($post['publish_date']) > time()): ?>
                            <div class="text-danger font-black uppercase italic" style="font-size: 8px;">SCHEDULED</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 border-white border-opacity-5 text-end">
                        <button type="button" class="btn btn-link text-white-50 hover:text-white p-0 me-3 edit-post"
                            data-id="<?php echo $post['id']; ?>"
                            data-title="<?php echo htmlspecialchars($post['title']); ?>"
                            data-cat="<?php echo htmlspecialchars($post['category']); ?>"
                            data-author="<?php echo htmlspecialchars($post['author']); ?>"
                            data-image="<?php echo htmlspecialchars($post['image']); ?>"
                            data-content="<?php echo htmlspecialchars($post['content']); ?>"
                            data-date="<?php echo $post['publish_date'] ? date('Y-m-d\TH:i', strtotime($post['publish_date'])) : ''; ?>"
                            data-tags="<?php echo htmlspecialchars($post['tags'] ?? ''); ?>"
                            data-mtitle="<?php echo htmlspecialchars($post['meta_title'] ?? ''); ?>"
                            data-mdesc="<?php echo htmlspecialchars($post['meta_description'] ?? ''); ?>"
                            data-mkeys="<?php echo htmlspecialchars($post['meta_keywords'] ?? ''); ?>"
                            data-top="<?php echo $post['is_top_story']; ?>"
                            data-source="<?php echo htmlspecialchars($post['source_url'] ?? ''); ?>"
                            data-video="<?php echo htmlspecialchars($post['video_url'] ?? ''); ?>"
                            data-bs-toggle="modal" data-bs-target="#editModal">
                            <i class="bi bi-pencil-square fs-5"></i>
                        </button>
                        <a href="?delete=<?php echo $post['id']; ?>" class="text-danger hover:text-white transition-all" onclick="return confirm('Decommission this report permanently?')"><i class="bi bi-trash fs-5"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-5 py-4 border-top border-white/5 bg-black/20">
        <nav>
            <ul class="pagination pagination-sm mb-0 gap-2 justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link bg-black border-white/10 text-white rounded-lg px-3" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">PREV</a></li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link <?php echo $i == $page ? 'bg-danger border-danger' : 'bg-black border-white/10'; ?> text-white rounded-lg px-3" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link bg-black border-white/10 text-white rounded-lg px-3" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">NEXT</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<style>
.pagination .page-link:hover {
    background-color: #ff3e3e;
    border-color: #ff3e3e;
    color: white;
}
.pagination .page-item.active .page-link {
    background-color: #ff3e3e;
    border-color: #ff3e3e;
}
</style>

<!-- Create New Post Modal -->
<div class="modal fade" id="manualModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary rounded-4">
            <div class="modal-header border-white border-opacity-10">
                <h5 class="modal-title font-condensed fw-black italic text-white uppercase">Create New Post</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <label class="form-label text-white-50 small uppercase font-black">Title</label>
                            <input type="text" name="title" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small uppercase font-black">Category</label>
                            <select name="cat" class="form-select bg-black border-white border-opacity-10 text-white rounded-xl">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo $c['name']; ?>"><?php echo $c['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-white-50 small uppercase font-black">Operator/Author</label>
                            <input type="text" name="author" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" placeholder="STAFF">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Image URL</label>
                            <input type="text" name="image" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" placeholder="https://unsplash.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">OR Upload Image</label>
                            <input type="file" name="image_file" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Schedule Deployment (Optional)</label>
                            <input type="datetime-local" name="publish_date" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">YouTube Video URL</label>
                            <input type="text" name="video_url" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" placeholder="https://www.youtube.com/watch?v=...">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="is_top_story" class="form-check-input bg-black border-white border-opacity-10" id="isTopManual">
                                <label class="form-check-label text-white-50 small uppercase font-black" for="isTopManual">Promote to Top Story</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-white-50 small uppercase font-black">Content (Markdown & HTML supported)</label>
                            <textarea name="content" rows="10" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl font-mono" required></textarea>
                        </div>
                        <div class="col-12 mt-4">
                            <h6 class="text-danger font-condensed italic fw-black border-bottom border-white border-opacity-10 pb-2 mb-3">SEO METADATA & TAGS</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Tags (Comma separated)</label>
                            <input type="text" name="tags" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Title</label>
                            <input type="text" name="meta_title" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Description</label>
                            <textarea name="meta_description" rows="3" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Keywords</label>
                            <textarea name="meta_keywords" rows="3" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-white border-opacity-10">
                    <button type="submit" name="save_manual" class="btn btn-danger w-100 py-3 rounded-xl font-condensed italic fw-black">DEPLOY REPORT</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary rounded-4">
            <div class="modal-header border-white border-opacity-10">
                <h5 class="modal-title font-condensed fw-black italic text-white uppercase">Edit Post Registry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="post_id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <label class="form-label text-white-50 small uppercase font-black">Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small uppercase font-black">Category</label>
                            <select name="cat" id="edit_cat" class="form-select bg-black border-white border-opacity-10 text-white rounded-xl">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo $c['name']; ?>"><?php echo $c['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-white-50 small uppercase font-black">Operator/Author</label>
                            <input type="text" name="author" id="edit_author" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Image URL</label>
                            <input type="text" name="image" id="edit_image" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Update Image File</label>
                            <input type="file" name="image_file" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Schedule Deployment</label>
                            <input type="datetime-local" name="publish_date" id="edit_date" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Source URL (Factual Origin)</label>
                            <input type="text" name="source_url" id="edit_source" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">YouTube Video URL</label>
                            <input type="text" name="video_url" id="edit_video" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" placeholder="https://www.youtube.com/watch?v=...">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="is_top_story" class="form-check-input bg-black border-white border-opacity-10" id="edit_top">
                                <label class="form-check-label text-white-50 small uppercase font-black" for="edit_top">Promote to Top Story</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-white-50 small uppercase font-black">Content (Markdown & HTML supported)</label>
                            <textarea name="content" id="edit_content" rows="10" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl font-mono" required></textarea>
                        </div>
                        <div class="col-12 mt-4">
                            <h6 class="text-danger font-condensed italic fw-black border-bottom border-white border-opacity-10 pb-2 mb-3">SEO METADATA & TAGS</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Tags (Comma separated)</label>
                            <input type="text" name="tags" id="edit_tags" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Title</label>
                            <input type="text" name="meta_title" id="edit_mtitle" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Description</label>
                            <textarea name="meta_description" id="edit_mdesc" rows="3" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Keywords</label>
                            <textarea name="meta_keywords" id="edit_mkeys" rows="3" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-white border-opacity-10">
                    <button type="submit" name="update_manual" class="btn btn-danger w-100 py-3 rounded-xl font-condensed italic fw-black">SAVE CHANGES</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const selectAll = document.getElementById('selectAll');
const checkboxes = document.querySelectorAll('.post-checkbox');
const bulkBtn = document.getElementById('bulkDeleteBtn');
const bulkForm = document.getElementById('bulkForm');

selectAll.addEventListener('change', function() {
    checkboxes.forEach(cb => cb.checked = this.checked);
    toggleBulkBtn();
});

checkboxes.forEach(cb => {
    cb.addEventListener('change', toggleBulkBtn);
});

function toggleBulkBtn() {
    const checkedCount = document.querySelectorAll('.post-checkbox:checked').length;
    if (checkedCount > 0) {
        bulkBtn.classList.remove('d-none');
    } else {
        bulkBtn.classList.add('d-none');
    }
}

function confirmBulkDelete() {
    const checkedCount = document.querySelectorAll('.post-checkbox:checked').length;
    if (confirm(`Are you sure you want to permanently delete ${checkedCount} reports?`)) {
        bulkForm.submit();
    }
}

document.querySelectorAll('.edit-post').forEach(btn => {
    btn.onclick = function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_title').value = this.dataset.title;
        document.getElementById('edit_cat').value = this.dataset.cat;
        document.getElementById('edit_author').value = this.dataset.author;
        document.getElementById('edit_image').value = this.dataset.image;
        document.getElementById('edit_content').value = this.dataset.content;
        document.getElementById('edit_date').value = this.dataset.date;
        document.getElementById('edit_tags').value = this.dataset.tags;
        document.getElementById('edit_mtitle').value = this.dataset.mtitle;
        document.getElementById('edit_mdesc').value = this.dataset.mdesc;
        document.getElementById('edit_mkeys').value = this.dataset.mkeys;
        document.getElementById('edit_source').value = this.dataset.source;
        document.getElementById('edit_video').value = this.dataset.video;
        document.getElementById('edit_top').checked = this.dataset.top == "1";
    };
});
</script>

<?php admin_footer();