<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? null;
$conn = get_db_connection();
$post = null;
if ($conn && $slug) {
    $stmt = $conn->prepare("SELECT * FROM posts WHERE slug = ? AND (is_scheduled = 0 OR publish_date <= CURRENT_TIMESTAMP)");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
}

if (!$post) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container py-5 text-center"><h1>Post Not Found</h1><a href="/" class="btn btn-primary mt-4">Back to Broadcast</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Set dynamic meta tags for header
$custom_meta_title = !empty($post['meta_title']) ? $post['meta_title'] : $post['title'];
$custom_meta_description = !empty($post['meta_description']) ? $post['meta_description'] : $post['excerpt'];
$custom_meta_keywords = $post['meta_keywords'] ?? '';

// Schema.org Structured Data
$post_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$json_ld = [
    "@context" => "https://schema.org",
    "@type" => "NewsArticle",
    "headline" => $post['title'],
    "image" => [strpos($post['image'], 'http') === 0 ? $post['image'] : SITE_URL . $post['image']],
    "datePublished" => date('c', strtotime($post['publish_date'] ?: $post['created_at'])),
    "dateModified" => date('c', strtotime($post['created_at'])),
    "author" => [
        "@type" => "Person",
        "name" => $post['author'],
        "url" => SITE_URL . "/author/" . urlencode($post['author'])
    ],
    "publisher" => [
        "@type" => "Organization",
        "name" => $settings['name'] ?? 'Football Intelligence',
        "logo" => [
            "@type" => "ImageObject",
            "url" => !empty($settings['logo']) ? (strpos($settings['logo'], 'http') === 0 ? $settings['logo'] : SITE_URL . $settings['logo']) : ""
        ]
    ]
];

$breadcrumb_ld = [
    "@context" => "https://schema.org",
    "@type" => "BreadcrumbList",
    "itemListElement" => [
        [
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => SITE_URL
        ],
        [
            "@type" => "ListItem",
            "position" => 2,
            "name" => $post['category'],
            "item" => SITE_URL . "/category/" . urlencode($post['category'])
        ],
        [
            "@type" => "ListItem",
            "position" => 3,
            "name" => $post['title'],
            "item" => $post_url
        ]
    ]
];

$header_code = '<script type="application/ld+json">' . json_encode($json_ld) . '</script>';
$header_code .= '<script type="application/ld+json">' . json_encode($breadcrumb_ld) . '</script>';

include __DIR__ . '/../includes/header.php';

// Handle Comment Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment_text'])) {
    $user_captcha = (int)($_POST['captcha_answer'] ?? 0);
    $saved_captcha = (int)($_SESSION['captcha_sum'] ?? -1);

    if ($user_captcha !== $saved_captcha) {
        $comment_error = "INCIDENT REPORTED: Security challenge failed. Pulse-check required.";
    } else {
        $author = sanitize($_POST['comment_author']);
        $text = sanitize($_POST['comment_text']);
        $stmt = $conn->prepare("INSERT INTO comments (post_id, author, text, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$post['id'], $author, $text]);
        $comment_msg = "Intelligence report received. Awaiting clearance.";
        unset($_SESSION['captcha_sum']);
    }
}

// Generate Arithmetic Captcha
$c_num1 = rand(2, 9);
$c_num2 = rand(2, 9);
$_SESSION['captcha_sum'] = $c_num1 + $c_num2;

// Get Approved Comments
$stmt = $conn->prepare("SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at DESC");
$stmt->execute([$post['id']]);
$comments = $stmt->fetchAll();

// Read Also - Similar News
$stmt_related = $conn->prepare("SELECT * FROM posts WHERE category = ? AND id != ? AND (is_scheduled = 0 OR publish_date <= CURRENT_TIMESTAMP) ORDER BY publish_date DESC LIMIT 4");
$stmt_related->execute([$post['category'], $post['id']]);
$relatedPosts = $stmt_related->fetchAll();

?>
<div class="bg-black text-white min-h-screen">
    <!-- Header Section -->
    <div class="py-8 md:py-12 bg-[#05070a] border-bottom border-white/5">
        <div class="container mx-auto px-4 px-md-6">
            <div class="max-w-4xl mx-auto">
                <span class="bg-electric-red text-white px-3 py-1 font-condensed fw-black italic uppercase text-[10px] mb-4 inline-block tracking-[0.2em] rounded">
                    <?php echo strtoupper($post['category']); ?>
                </span>
                <h1 class="font-condensed fw-black italic text-white uppercase tracking-tight text-3xl md:text-5xl leading-tight mb-6">
                    <?php echo $post['title']; ?>
                </h1>

                <div class="flex flex-wrap items-center justify-between gap-4 py-4 border-t border-b border-white/10 text-white-50 font-monospace text-[11px] uppercase tracking-wider mb-8">
                    <div class="flex flex-wrap items-center gap-4">
                        <a href="/author/<?php echo urlencode($post['author']); ?>" class="flex items-center gap-2 text-decoration-none text-inherit">
                            <div class="w-7 h-7 rounded-full bg-electric-red flex items-center justify-center text-white fw-bold italic font-condensed text-xs">
                                <?php echo substr($post['author'], 0, 1); ?>
                            </div>
                            <span>BY <span class="text-white fw-bold"><?php echo $post['author']; ?></span></span>
                        </a>
                        <span class="text-white/20">•</span>
                        <div class="flex items-center gap-2">
                            <i class="bi bi-calendar3 text-electric-red"></i>
                            <span><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                        </div>
                    </div>

                    <!-- Compact Quick Share -->
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] text-gray-500 font-bold me-1">SHARE:</span>
                        <?php
                            $p_url = urlencode(SITE_URL . "/post/" . $post['slug']);
                            $p_title = urlencode($post['title']);
                        ?>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $p_url; ?>" target="_blank" class="text-white-50 hover:text-white transition-colors" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo $p_url; ?>&text=<?php echo $p_title; ?>" target="_blank" class="text-white-50 hover:text-white transition-colors ms-2" title="X"><i class="bi bi-twitter-x"></i></a>
                        <a href="https://api.whatsapp.com/send?text=<?php echo $p_title . '%20' . $p_url; ?>" target="_blank" class="text-white-50 hover:text-white transition-colors ms-2" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    </div>
                </div>

                <?php if (!empty($post['image'])): ?>
                <!-- Featured Image - Compact & Responsive -->
                <div class="rounded-2xl overflow-hidden border border-white/10 shadow-2xl bg-[#0a0e17] mb-8 max-w-3xl mx-auto">
                    <img src="<?php echo $post['image']; ?>" class="w-full h-auto max-h-[360px] object-cover mx-auto block" alt="<?php echo htmlspecialchars($post['title']); ?>">
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 px-md-6 py-8">
        <div class="row g-5">
            <div class="col-lg-8">
                <article class="prose prose-invert prose-red max-w-none">
                    <p class="lead text-lg text-md-xl text-white-50 font-medium italic mb-8 border-l-4 border-electric-red pl-4 md:pl-6"><?php echo $post['excerpt']; ?></p>

                    <?php if (!empty($post['video_url'])):
                        $video_id = '';
                        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $post['video_url'], $match)) {
                            $video_id = $match[1];
                        }
                        if ($video_id):
                    ?>
                        <div class="ratio ratio-16x9 mb-10 shadow-2xl rounded-4 overflow-hidden border border-white/10">
                            <iframe src="https://www.youtube.com/embed/<?php echo $video_id; ?>" allowfullscreen></iframe>
                        </div>
                    <?php endif; endif; ?>

                    <div class="markdown-content text-lg leading-relaxed text-white-90 opacity-90 mb-12">
                        <?php echo parse_markdown($post['content']); ?>
                    </div>

                    <!-- Social Share Buttons -->
                    <div class="border-t border-b border-white/5 py-8 mb-10">
                        <h4 class="text-[10px] font-black uppercase text-gray-500 tracking-widest mb-4">Share Intelligence</h4>
                        <div class="flex flex-wrap gap-3">
                            <?php
                                $post_url = SITE_URL . "/post/" . $post['slug'];
                                $encoded_url = urlencode($post_url);
                                $encoded_title = urlencode($post['title']);
                            ?>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $encoded_url; ?>" target="_blank" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white-50 hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all">
                                <i class="bi bi-facebook fs-5"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo $encoded_url; ?>&text=<?php echo $encoded_title; ?>" target="_blank" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white-50 hover:bg-black hover:text-white hover:border-black transition-all">
                                <i class="bi bi-twitter-x fs-5"></i>
                            </a>
                            <a href="https://api.whatsapp.com/send?text=<?php echo $encoded_title . '%20' . $encoded_url; ?>" target="_blank" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white-50 hover:bg-green-600 hover:text-white hover:border-green-600 transition-all">
                                <i class="bi bi-whatsapp fs-5"></i>
                            </a>
                            <a href="https://t.me/share/url?url=<?php echo $encoded_url; ?>&text=<?php echo $encoded_title; ?>" target="_blank" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white-50 hover:bg-sky-500 hover:text-white hover:border-sky-500 transition-all">
                                <i class="bi bi-telegram fs-5"></i>
                            </a>
                            <a href="mailto:?subject=<?php echo $encoded_title; ?>&body=Check this out: <?php echo $encoded_url; ?>" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white-50 hover:bg-electric-red hover:text-white hover:border-electric-red transition-all">
                                <i class="bi bi-envelope-fill fs-5"></i>
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($post['tags'])): ?>
                    <div class="mt-10 flex flex-wrap gap-2">
                        <?php foreach (explode(',', $post['tags']) as $tag): ?>
                            <span class="bg-white/5 border border-white/10 text-white-50 px-3 py-1 rounded-full text-[10px] uppercase font-bold italic">#<?php echo trim($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </article>

                <!-- Comments Section -->
                <div class="mt-16 border-t border-white/10 pt-12">
                    <h3 class="font-condensed fw-black italic text-white text-3xl mb-8 uppercase">Comments</h3>

                    <?php if (isset($comment_msg)): ?>
                        <div class="alert alert-success bg-green-900/20 border-green-500/50 text-green-500 rounded-xl font-condensed italic uppercase"><?php echo $comment_msg; ?></div>
                    <?php endif; ?>

                    <?php if (isset($comment_error)): ?>
                        <div class="alert alert-danger bg-red-900/20 border-red-500/50 text-danger rounded-xl font-condensed italic uppercase"><?php echo $comment_error; ?></div>
                    <?php endif; ?>

                    <form method="POST" class="mb-12 bg-[#0a0e17] p-4 p-md-8 border border-white/5 rounded-2xl shadow-2xl">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="block text-[10px] font-black uppercase text-gray-500 tracking-widest mb-2">Author ID</label>
                                <input type="text" name="comment_author" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" required placeholder="Codename or Identity">
                            </div>
                            <div class="col-md-6">
                                <label class="block text-[10px] font-black uppercase text-gray-500 tracking-widest mb-2">Security Verification: <?php echo $c_num1; ?> + <?php echo $c_num2; ?> = ?</label>
                                <input type="number" name="captcha_answer" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-bold" required placeholder="Prove you are human">
                            </div>
                            <div class="col-12">
                                <label class="block text-[10px] font-black uppercase text-gray-500 tracking-widest mb-2">Message Payload</label>
                                <textarea name="comment_text" rows="4" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white" required placeholder="Input tactical assessment..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="mt-6 bg-electric-red text-white px-10 py-3 rounded-xl font-black uppercase italic tracking-widest hover:bg-white hover:text-electric-red transition-all">Transmit Message</button>
                    </form>

                    <div class="space-y-6">
                        <?php foreach ($comments as $comment): ?>
                            <div class="bg-white/5 p-6 border-l-2 border-electric-red">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-condensed fw-black italic text-white uppercase"><?php echo $comment['author']; ?></span>
                                    <span class="text-[9px] text-white-50 font-monospace uppercase"><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></span>
                                </div>
                                <p class="text-white-50 mb-0"><?php echo nl2br($comment['text']); ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($comments)): ?>
                            <p class="text-white-50 italic opacity-40 font-condensed uppercase tracking-widest text-center py-10">Waiting for intelligence input...</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="sticky-top" style="top: 100px;">
                    <div class="bg-[#0a0e17] p-8 border border-white/5 rounded-3xl mb-8">
                        <h4 class="font-condensed fw-black italic text-electric-red text-xl mb-4 uppercase">Newsletter Syndication</h4>
                        <p class="text-white-50 small mb-6">Receive real-time intelligence directly to your secure inbox.</p>
                        <form action="/subscribe" method="POST">
                            <input type="email" name="email" placeholder="SECURE EMAIL ADDRESS" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white font-monospace text-xs mb-4" required>
                            <button type="submit" class="w-full bg-white text-black px-6 py-3 rounded-xl font-black uppercase italic tracking-widest hover:bg-electric-red hover:text-white transition-all">Secure Subscription</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Read Also Section -->
    <?php if (count($relatedPosts) > 0): ?>
    <div class="container mx-auto px-4 px-md-6 py-12 border-t border-white/10">
        <h3 class="font-condensed fw-black italic text-white text-3xl mb-8 uppercase">Read Also</h3>
        <div class="row g-4">
            <?php foreach ($relatedPosts as $rp): ?>
                <div class="col-md-3">
                    <a href="/post/<?php echo $rp['slug']; ?>" class="card h-100 bg-transparent border-0 group text-decoration-none">
                        <div class="ratio ratio-16x9 mb-3 overflow-hidden rounded-3 border border-white/10">
                                <img src="<?php echo $rp['image']; ?>" class="object-fit-cover transition-all duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($rp['title']); ?>">
                        </div>
                        <h4 class="text-white font-condensed fw-black italic uppercase fs-5 leading-tight group-hover:text-electric-red transition-all"><?php echo $rp['title']; ?></h4>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php';