<?php
require_once __DIR__ . '/../includes/functions.php';
$author_name = $_GET['name'] ?? null;
$conn = get_db_connection();
$author = null;

if ($conn && $author_name) {
    $stmt = $conn->prepare("SELECT username, bio, twitter_url, linkedin_url, avatar FROM users WHERE username = ?");
    $stmt->execute([$author_name]);
    $author = $stmt->fetch();
}

if (!$author) {
    // Fallback if not in users table (for AI or manual names not linked to users)
    $author = [
        'username' => htmlspecialchars($author_name),
        'bio' => 'Senior Intelligence Analyst for the Football Intelligence Network.',
        'avatar' => '/assets/img/default-avatar.jpg'
    ];
}

$custom_meta_title = "Intelligence Profile: " . $author['username'] . " | " . ($settings['name'] ?? 'Football Intelligence');

include __DIR__ . '/../includes/header.php';

// Fetch author posts
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$stmt_posts = $conn->prepare("SELECT * FROM posts WHERE author = ? AND (is_scheduled = 0 OR publish_date <= CURRENT_TIMESTAMP) ORDER BY publish_date DESC LIMIT ? OFFSET ?");
$stmt_posts->bindValue(1, $author['username'], PDO::PARAM_STR);
$stmt_posts->bindValue(2, $limit, PDO::PARAM_INT);
$stmt_posts->bindValue(3, $offset, PDO::PARAM_INT);
$stmt_posts->execute();
$posts = $stmt_posts->fetchAll();

$stmt_count = $conn->prepare("SELECT COUNT(*) FROM posts WHERE author = ? AND (is_scheduled = 0 OR publish_date <= CURRENT_TIMESTAMP)");
$stmt_count->execute([$author['username']]);
$total_posts = $stmt_count->fetchColumn();
$total_pages = ceil($total_posts / $limit);

?>
<div class="bg-black text-white min-h-screen">
    <div class="container py-20 px-4 px-md-10">
        <div class="bg-[#0a0e17] rounded-4xl border border-white/5 p-8 p-md-12 mb-20 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-10 opacity-5">
                <i class="bi bi-person-badge display-1"></i>
            </div>
            <div class="row align-items-center g-8">
                <div class="col-md-3 text-center">
                    <img src="<?php echo !empty($author['avatar']) ? $author['avatar'] : '/assets/img/default-avatar.jpg'; ?>" class="rounded-full w-48 h-48 object-cover border-4 border-electric-red mx-auto shadow-2xl" alt="<?php echo $author['username']; ?>">
                </div>
                <div class="col-md-9">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="bg-electric-red text-white px-3 py-1 font-condensed fw-black italic uppercase text-[10px] tracking-widest">Verified Intelligence Analyst</span>
                    </div>
                    <h1 class="display-4 font-condensed fw-black italic text-white uppercase mb-6 tracking-tighter"><?php echo $author['username']; ?></h1>
                    <p class="text-xl text-white-50 leading-relaxed mb-8 max-w-3xl"><?php echo nl2br($author['bio']); ?></p>
                    <div class="flex gap-4">
                        <?php if (!empty($author['twitter_url'])): ?>
                            <a href="<?php echo $author['twitter_url']; ?>" target="_blank" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white-50 hover:bg-black hover:text-white transition-all"><i class="bi bi-twitter-x"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($author['linkedin_url'])): ?>
                            <a href="<?php echo $author['linkedin_url']; ?>" target="_blank" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white-50 hover:bg-blue-700 hover:text-white transition-all"><i class="bi bi-linkedin"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="font-condensed fw-black italic text-white text-3xl mb-12 uppercase border-l-4 border-electric-red pl-6">Intelligence Reports Filed</h2>

        <?php if (!empty($posts)): ?>
            <div class="row g-6">
                <?php foreach ($posts as $post): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <article class="group h-full flex flex-col bg-[#0a0e17] rounded-3xl border border-white/5 overflow-hidden transition-all hover:border-electric-red/30">
                            <a href="/post/<?php echo $post['slug']; ?>" class="block relative aspect-[4/3] overflow-hidden">
                                <img src="<?php echo $post['image']; ?>" class="w-full h-full object-fit-cover transition-transform duration-700 group-hover:scale-110" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            </a>
                            <div class="p-8 flex-grow flex flex-col">
                                <div class="text-[10px] font-monospace text-white/30 uppercase mb-4"><?php echo date('d M Y', strtotime($post['publish_date'])); ?></div>
                                <h3 class="text-xl font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors mb-4 line-clamp-3 leading-tight">
                                    <a href="/post/<?php echo $post['slug']; ?>" class="text-inherit text-decoration-none"><?php echo $post['title']; ?></a>
                                </h3>
                                <a href="/post/<?php echo $post['slug']; ?>" class="mt-auto text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none flex items-center gap-2">
                                    Access Report <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-20 flex justify-center gap-3">
                    <?php $base_url = "/author/" . urlencode($author['username']) . "?"; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="<?php echo $base_url; ?>page=<?php echo $i; ?>" class="w-12 h-12 flex items-center justify-center <?php echo $page == $i ? 'bg-electric-red' : 'bg-white/5'; ?> border border-white/10 rounded-xl text-white hover:bg-electric-red transition-all font-condensed italic fw-black"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-white-50 italic opacity-40 font-condensed uppercase tracking-widest py-20 text-center border border-dashed border-white/10 rounded-3xl">No reports discovered for this analyst</p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
