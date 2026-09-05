<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include __DIR__ . '/../includes/header.php';

$category = $_GET['category'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$conn = get_db_connection();
$posts = [];
$featuredNews = [];
$transferUpdates = [];
$footballReports = [];
$totalPosts = 0;
$totalPages = 0;

if ($conn) {
    clearstatcache();
    // Determine the correct date comparison function based on driver
    $is_sqlite = ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
    $now = $is_sqlite ? "datetime('now', 'localtime')" : "NOW()";

    if ($category) {
        // CATEGORY PAGE: Display all posts in this category
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM posts WHERE LOWER(category) = LOWER(?) AND (is_scheduled = 0 OR publish_date <= $now)");
        $stmt_count->execute([$category]);
        $totalPosts = (int)$stmt_count->fetchColumn();
        $totalPages = ceil($totalPosts / $limit);

        $stmt = $conn->prepare("SELECT * FROM posts WHERE LOWER(category) = LOWER(?) AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $category, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();
    } else {
        // HOME PAGE: Intelligence Network Layout

        // 1. Hero: Top Story (priority to is_top_story, but fallback to latest)
        $stmt_hero = $conn->query("SELECT * FROM posts WHERE (is_scheduled = 0 OR publish_date <= $now) ORDER BY is_top_story DESC, publish_date DESC LIMIT 1");
        $hero = $stmt_hero->fetch();

        // 2. Latest Intelligence (Sidebar + Grid)
        $sidebarNews = [];
        if ($hero) {
            $stmt_sidebar = $conn->prepare("SELECT * FROM posts WHERE id != ? AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4");
            $stmt_sidebar->execute([$hero['id']]);
            $sidebarNews = $stmt_sidebar->fetchAll();

        }

        // 3. Category Specific Feeds
        $primary_cat = !empty($settings['section_primary_cat']) ? $settings['section_primary_cat'] : 'Football News';
        $secondary_cat = !empty($settings['section_secondary_cat']) ? $settings['section_secondary_cat'] : 'Transfer News';
        $third_cat = !empty($settings['section_third_cat']) ? $settings['section_third_cat'] : null;
        $fourth_cat = !empty($settings['section_fourth_cat']) ? $settings['section_fourth_cat'] : null;

        // Primary Category Section
        $stmt_primary = $conn->prepare("SELECT * FROM posts WHERE LOWER(category) = LOWER(?) AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4");
        $stmt_primary->execute([$primary_cat]);
        $primaryReports = $stmt_primary->fetchAll();
        if (empty($primaryReports)) {
            $primaryReports = $conn->query("SELECT * FROM posts WHERE (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4")->fetchAll();
        }

        // Secondary Category Section
        $stmt_secondary = $conn->prepare("SELECT * FROM posts WHERE LOWER(category) = LOWER(?) AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4");
        $stmt_secondary->execute([$secondary_cat]);
        $secondaryReports = $stmt_secondary->fetchAll();
        if (empty($secondaryReports)) {
            $secondaryReports = $conn->query("SELECT * FROM posts WHERE (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4")->fetchAll();
        }

        // Third Category Section
        $thirdReports = [];
        if ($third_cat) {
            $stmt_third = $conn->prepare("SELECT * FROM posts WHERE LOWER(category) = LOWER(?) AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4");
            $stmt_third->execute([$third_cat]);
            $thirdReports = $stmt_third->fetchAll();
        }

        // Fourth Category Section
        $fourthReports = [];
        if ($fourth_cat) {
            $stmt_fourth = $conn->prepare("SELECT * FROM posts WHERE LOWER(category) = LOWER(?) AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4");
            $stmt_fourth->execute([$fourth_cat]);
            $fourthReports = $stmt_fourth->fetchAll();
        }
    }
}

if ($category) {
    $custom_meta_title = "Category: " . htmlspecialchars($category) . " | " . ($settings['name'] ?? 'Football Intelligence');
}
?>

<div class="container-fluid pt-0 px-0 bg-black overflow-x-hidden">
    <?php if (!$category): ?>
        <!-- MAIN LANDING HERO GRID -->
        <?php if ($hero): ?>
        <section class="bg-black border-b border-white/10 py-6">
            <div class="container-fluid px-4 px-md-8">
                <div class="row g-4 align-items-stretch">
                    <!-- Main Hero Lead Article -->
                    <div class="col-lg-8">
                        <article class="relative h-100 min-h-[480px] rounded-3xl overflow-hidden border border-white/10 group flex flex-col justify-end bg-[#0a0e17]">
                            <?php $hero_img = !empty($hero['image']) ? $hero['image'] : '/assets/images/default.jpg'; ?>
                            <div class="absolute inset-0 z-0">
                                <img src="<?php echo $hero_img; ?>" class="w-full h-full object-cover opacity-60 transition-transform duration-700 group-hover:scale-105" alt="<?php echo htmlspecialchars($hero['title']); ?>">
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent"></div>
                            </div>

                            <div class="relative z-10 p-6 p-md-10">
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="bg-electric-red text-white font-condensed fw-black italic px-3 py-1 uppercase tracking-widest text-xs rounded"><?php echo htmlspecialchars($settings['section_priority_title'] ?? 'Priority Intelligence'); ?></span>
                                    <span class="text-white/60 font-monospace text-xs uppercase"><?php echo date('M d, Y // H:i T', strtotime($hero['publish_date'])); ?></span>
                                </div>
                                <h1 class="text-3xl md:text-5xl font-condensed fw-black text-white italic uppercase leading-tight mb-4 tracking-tight">
                                    <a href="/post/<?php echo $hero['slug']; ?>" class="text-inherit text-decoration-none hover:text-electric-red transition-colors"><?php echo $hero['title']; ?></a>
                                </h1>
                                <p class="text-sm md:text-base text-white/80 font-medium leading-relaxed mb-6 line-clamp-2 max-w-3xl">
                                    <?php echo $hero['excerpt']; ?>
                                </p>
                                <a href="/post/<?php echo $hero['slug']; ?>" class="inline-flex items-center gap-2 bg-electric-red text-white px-6 py-2.5 rounded-xl font-condensed fw-black italic text-sm uppercase tracking-wider text-decoration-none hover:bg-white hover:text-black transition-all">
                                    Read Full Story <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    </div>

                    <!-- Latest News Sidebar Grid -->
                    <div class="col-lg-4">
                        <div class="bg-[#0a0e17] rounded-3xl border border-white/10 p-5 h-100 flex flex-col">
                            <div class="pb-3 mb-4 border-b border-white/10 flex items-center justify-between">
                                <h2 class="text-lg font-condensed fw-black italic text-white uppercase mb-0 tracking-wider flex items-center gap-2">
                                    <span class="w-2 h-4 bg-electric-red rounded-sm"></span>
                                    <?php echo htmlspecialchars($settings['section_latest_title'] ?? 'Latest Intelligence'); ?>
                                </h2>
                                <span class="badge bg-electric-red/10 text-electric-red border border-electric-red/20 uppercase font-mono text-[10px]">Live Feed</span>
                            </div>

                            <div class="space-y-4 flex-grow">
                                <?php foreach ($sidebarNews as $sn): ?>
                                    <?php $sn_img = !empty($sn['image']) ? $sn['image'] : '/assets/images/default.jpg'; ?>
                                    <a href="/post/<?php echo $sn['slug']; ?>" class="flex gap-4 items-center group text-decoration-none p-2.5 rounded-2xl hover:bg-white/5 transition-all">
                                        <div class="w-24 h-20 flex-shrink-0 overflow-hidden rounded-xl border border-white/10 relative">
                                            <img src="<?php echo $sn_img; ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($sn['title']); ?>">
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-[9px] font-black uppercase text-electric-red"><?php echo htmlspecialchars($sn['category'] ?? 'General'); ?></span>
                                                <span class="text-[9px] font-monospace text-white/40">• <?php echo date('H:i', strtotime($sn['publish_date'])); ?></span>
                                            </div>
                                            <h3 class="text-xs md:text-sm font-condensed fw-bold italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-snug mb-0">
                                                <?php echo $sn['title']; ?>
                                            </h3>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>


        <!-- SPECIALIZED SECTIONS -->
        <section class="py-20 bg-[#05070a] border-y border-white/5">
            <div class="container-fluid px-4 px-md-10">
                <div class="row g-10">
                    <!-- Primary Category Section -->
                    <div class="col-lg-6">
                        <div class="flex items-center justify-between mb-10">
                            <h3 class="text-2xl font-condensed fw-black italic text-white uppercase border-l-4 border-electric-red pl-4"><?php echo htmlspecialchars($settings['section_football_title'] ?? 'Football News'); ?></h3>
                            <a href="/category/<?php echo urlencode(strtolower(str_replace(' ', '-', $primary_cat))); ?>" class="text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none hover:text-white transition-colors">Full Archive →</a>
                        </div>
                        <div class="space-y-8">
                            <?php foreach ($primaryReports as $fr): ?>
                                <a href="/post/<?php echo $fr['slug']; ?>" class="flex gap-6 group text-decoration-none">
                                    <div class="w-32 h-24 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                        <img src="<?php echo $fr['image']; ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($fr['title']); ?>">
                                    </div>
                                    <div class="flex-grow py-1">
                                        <h4 class="text-lg font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-tight mb-2"><?php echo $fr['title']; ?></h4>
                                        <div class="text-[10px] font-monospace text-white/30 uppercase"><?php echo date('M d, H:i', strtotime($fr['publish_date'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Secondary Category Section -->
                    <div class="col-lg-6">
                        <div class="flex items-center justify-between mb-10">
                            <h3 class="text-2xl font-condensed fw-black italic text-white uppercase border-l-4 border-electric-red pl-4"><?php echo htmlspecialchars($settings['section_transfer_title'] ?? 'Transfer Intelligence'); ?></h3>
                            <a href="/category/<?php echo urlencode(strtolower(str_replace(' ', '-', $secondary_cat))); ?>" class="text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none hover:text-white transition-colors">Full Archive →</a>
                        </div>
                        <div class="space-y-8">
                            <?php foreach ($secondaryReports as $tu): ?>
                                <a href="/post/<?php echo $tu['slug']; ?>" class="flex gap-6 group text-decoration-none">
                                    <div class="w-32 h-24 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                        <img src="<?php echo $tu['image']; ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($tu['title']); ?>">
                                    </div>
                                    <div class="flex-grow py-1">
                                        <h4 class="text-lg font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-tight mb-2"><?php echo $tu['title']; ?></h4>
                                        <div class="text-[10px] font-monospace text-white/30 uppercase"><?php echo date('M d, H:i', strtotime($tu['publish_date'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Section 3 (if configured) -->
                    <?php if (!empty($thirdReports)): ?>
                    <div class="col-lg-6 mt-12">
                        <div class="flex items-center justify-between mb-10">
                            <h3 class="text-2xl font-condensed fw-black italic text-white uppercase border-l-4 border-electric-red pl-4"><?php echo htmlspecialchars($settings['section_third_title'] ?? 'Featured Reports'); ?></h3>
                            <a href="/category/<?php echo urlencode(strtolower(str_replace(' ', '-', $third_cat))); ?>" class="text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none hover:text-white transition-colors">Full Archive →</a>
                        </div>
                        <div class="space-y-8">
                            <?php foreach ($thirdReports as $tr): ?>
                                <a href="/post/<?php echo $tr['slug']; ?>" class="flex gap-6 group text-decoration-none">
                                    <div class="w-32 h-24 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                        <img src="<?php echo $tr['image']; ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($tr['title']); ?>">
                                    </div>
                                    <div class="flex-grow py-1">
                                        <h4 class="text-lg font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-tight mb-2"><?php echo $tr['title']; ?></h4>
                                        <div class="text-[10px] font-monospace text-white/30 uppercase"><?php echo date('M d, H:i', strtotime($tr['publish_date'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Section 4 (if configured) -->
                    <?php if (!empty($fourthReports)): ?>
                    <div class="col-lg-6 mt-12">
                        <div class="flex items-center justify-between mb-10">
                            <h3 class="text-2xl font-condensed fw-black italic text-white uppercase border-l-4 border-electric-red pl-4"><?php echo htmlspecialchars($settings['section_fourth_title'] ?? 'Analysis & Insights'); ?></h3>
                            <a href="/category/<?php echo urlencode(strtolower(str_replace(' ', '-', $fourth_cat))); ?>" class="text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none hover:text-white transition-colors">Full Archive →</a>
                        </div>
                        <div class="space-y-8">
                            <?php foreach ($fourthReports as $fo): ?>
                                <a href="/post/<?php echo $fo['slug']; ?>" class="flex gap-6 group text-decoration-none">
                                    <div class="w-32 h-24 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                        <img src="<?php echo $fo['image']; ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($fo['title']); ?>">
                                    </div>
                                    <div class="flex-grow py-1">
                                        <h4 class="text-lg font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-tight mb-2"><?php echo $fo['title']; ?></h4>
                                        <div class="text-[10px] font-monospace text-white/30 uppercase"><?php echo date('M d, H:i', strtotime($fo['publish_date'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <?php else: ?>
        <!-- CATEGORY ARCHIVE CONTENT -->
        <section class="py-20 bg-black min-h-screen">
            <div class="container-fluid px-4 px-md-10">
                <div class="flex items-center gap-6 mb-20 border-b border-white/10 pb-10">
                    <div class="w-3 h-16 bg-electric-red"></div>
                    <div>
                        <div class="text-electric-red font-condensed fw-black italic uppercase tracking-widest text-sm mb-2">Operational Taxonomy</div>
                        <h1 class="text-6xl md:text-8xl font-condensed fw-black italic text-white uppercase mb-0 tracking-tighter"><?php echo $category; ?></h1>
                    </div>
                </div>

                <?php if (!empty($posts)): ?>
                    <div class="row g-6">
                        <?php foreach ($posts as $post): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <article class="group h-full flex flex-col bg-[#0a0e17] rounded-3xl border border-white/5 overflow-hidden transition-all hover:border-electric-red/30">
                                    <a href="/post/<?php echo $post['slug']; ?>" class="block relative aspect-[4/3] overflow-hidden">
                                        <img src="<?php echo $post['image']; ?>" class="w-full h-full object-fit-cover transition-transform duration-700 group-hover:scale-110" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                    </a>
                                    <div class="p-8 flex-grow flex flex-col">
                                        <div class="text-[10px] font-monospace text-white/30 uppercase mb-4"><?php echo date('d M Y // H:i', strtotime($post['publish_date'])); ?></div>
                                        <h3 class="text-xl font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors mb-4 line-clamp-3 leading-tight">
                                            <a href="/post/<?php echo $post['slug']; ?>" class="text-inherit text-decoration-none"><?php echo $post['title']; ?></a>
                                        </h3>
                                        <p class="text-white/50 text-sm leading-relaxed line-clamp-3 mb-6">
                                            <?php echo $post['excerpt']; ?>
                                        </p>
                                        <a href="/post/<?php echo $post['slug']; ?>" class="mt-auto text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none flex items-center gap-2">
                                            Access Report <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="mt-20 flex justify-center gap-3">
                        <?php $base_url = "/category/" . urlencode($category) . "?"; ?>
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="w-12 h-12 flex items-center justify-center bg-white/5 border border-white/10 rounded-xl text-white hover:bg-electric-red transition-all"><i class="bi bi-chevron-left"></i></a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $i; ?>" class="w-12 h-12 flex items-center justify-center <?php echo $page == $i ? 'bg-electric-red border-electric-red' : 'bg-white/5 border-white/10'; ?> border rounded-xl text-white hover:bg-electric-red transition-all font-condensed italic fw-black"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="w-12 h-12 flex items-center justify-center bg-white/5 border border-white/10 rounded-xl text-white hover:bg-electric-red transition-all"><i class="bi bi-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="py-40 text-center border border-dashed border-white/10 rounded-3xl">
                        <div class="text-white/20 mb-6"><i class="bi bi-folder-x display-1"></i></div>
                        <p class="font-condensed italic uppercase tracking-[0.3em] text-white/50 fs-4">No reports discovered in this taxonomy</p>
                        <a href="/" class="btn btn-outline-danger mt-10 px-12 py-4 font-black italic rounded-0 tracking-widest">Return to Base</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<style>
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>