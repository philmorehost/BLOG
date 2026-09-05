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
        $stmt_transfer = $conn->query("SELECT * FROM posts WHERE category = 'Transfer News' AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4");
        $transferUpdates = $stmt_transfer->fetchAll();

        $stmt_football = $conn->query("SELECT * FROM posts WHERE category = 'Football News' AND (is_scheduled = 0 OR publish_date <= $now) ORDER BY publish_date DESC LIMIT 4");
        $footballReports = $stmt_football->fetchAll();
    }
}

if ($category) {
    $custom_meta_title = "Category: " . htmlspecialchars($category) . " | " . ($settings['name'] ?? 'Football Intelligence');
}
?>

<div class="container-fluid pt-0 px-0 bg-black overflow-x-hidden">
    <?php if (!$category): ?>
        <!-- INTELLIGENCE HERO -->
        <?php if ($hero): ?>
        <section class="bg-black border-b border-white/10 overflow-hidden">
            <div class="container-fluid px-0">
                <div class="row g-0">
                    <!-- Main Hero Column -->
                    <div class="col-lg-8 border-r border-white/10">
                        <div class="relative h-[60vh] md:h-[85vh] flex items-end">
                            <div class="absolute inset-0 z-0">
                                <img src="<?php echo $hero['image']; ?>" class="w-full h-full object-fit-cover opacity-60" alt="<?php echo htmlspecialchars($hero['title']); ?>">
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
                            </div>

                            <div class="px-4 px-md-10 py-10 py-md-15 relative z-10 w-full">
                                <div class="max-w-3xl">
                                    <div class="flex items-center gap-3 mb-4">
                                        <span class="bg-electric-red text-white font-condensed fw-black italic px-4 py-1 uppercase tracking-widest text-xs"><?php echo htmlspecialchars($settings['section_priority_title'] ?? 'Priority Intelligence'); ?></span>
                                        <span class="text-white/50 font-monospace text-[10px] uppercase tracking-tighter"><?php echo date('H:i:s T', strtotime($hero['publish_date'])); ?></span>
                                    </div>
                                    <h1 class="text-4xl md:text-7xl font-condensed fw-black text-white italic uppercase lh-1 mb-6 tracking-tighter">
                                        <a href="/post/<?php echo $hero['slug']; ?>" class="text-inherit text-decoration-none"><?php echo $hero['title']; ?></a>
                                    </h1>
                                    <p class="text-lg text-white/70 font-medium leading-relaxed mb-8 max-w-2xl hidden md:block">
                                        <?php echo $hero['excerpt']; ?>
                                    </p>
                                    <a href="/post/<?php echo $hero['slug']; ?>" class="btn btn-primary rounded-0 font-condensed fw-black italic px-8 py-3 uppercase tracking-widest hover:bg-white hover:text-black transition-all">
                                        Read Full Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Sidebar: 4 Latest Reports -->
                    <div class="col-lg-4 bg-[#0a0e17]">
                        <div class="h-full flex flex-col">
                            <div class="p-4 border-b border-white/5 bg-black/40">
                                <h2 class="text-lg font-condensed fw-black italic text-white uppercase mb-0 tracking-widest flex items-center gap-3">
                                    <span class="w-1.5 h-4 bg-electric-red"></span>
                                    <?php echo htmlspecialchars($settings['section_latest_title'] ?? 'Latest Intelligence'); ?>
                                </h2>
                            </div>
                            <div class="flex-grow overflow-y-auto no-scrollbar" style="max-height: calc(85vh - 60px);">
                                <?php foreach ($sidebarNews as $idx => $sn): ?>
                                    <a href="/post/<?php echo $sn['slug']; ?>" class="block group text-decoration-none border-b border-white/5 p-4 md:p-5 hover:bg-white/5 transition-all">
                                        <div class="flex gap-4">
                                            <div class="w-20 h-20 md:w-24 md:h-20 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                                <img src="<?php echo $sn['image']; ?>" class="w-full h-full object-fit-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($sn['title']); ?>">
                                            </div>
                                            <div class="flex-grow">
                                                <div class="flex items-center gap-2 mb-1.5">
                                                    <span class="text-[7px] font-black uppercase px-1.5 py-0.5 rounded bg-electric-red/10 text-electric-red border border-electric-red/20"><?php echo $sn['category']; ?></span>
                                                    <span class="text-[8px] font-monospace text-white/30"><?php echo date('H:i', strtotime($sn['publish_date'])); ?></span>
                                                </div>
                                                <h3 class="text-xs md:text-sm font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-tight mb-0">
                                                    <?php echo $sn['title']; ?>
                                                </h3>
                                            </div>
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
                    <!-- Football News -->
                    <div class="col-lg-6">
                        <div class="flex items-center justify-between mb-10">
                            <h3 class="text-2xl font-condensed fw-black italic text-white uppercase border-l-4 border-electric-red pl-4"><?php echo htmlspecialchars($settings['section_football_title'] ?? 'Football News'); ?></h3>
                            <a href="/category/football-news" class="text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none hover:text-white transition-colors">Full Archive →</a>
                        </div>
                        <div class="space-y-8">
                            <?php foreach ($footballReports as $fr): ?>
                                <a href="/post/<?php echo $fr['slug']; ?>" class="flex gap-6 group text-decoration-none">
                                    <div class="w-32 h-24 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                        <img src="<?php echo $fr['image']; ?>" class="w-full h-full object-fit-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($fr['title']); ?>">
                                    </div>
                                    <div class="flex-grow py-1">
                                        <h4 class="text-lg font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-tight mb-2"><?php echo $fr['title']; ?></h4>
                                        <div class="text-[10px] font-monospace text-white/30 uppercase"><?php echo date('M d, H:i', strtotime($fr['publish_date'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Transfer News -->
                    <div class="col-lg-6">
                        <div class="flex items-center justify-between mb-10">
                            <h3 class="text-2xl font-condensed fw-black italic text-white uppercase border-l-4 border-electric-red pl-4"><?php echo htmlspecialchars($settings['section_transfer_title'] ?? 'Transfer Intelligence'); ?></h3>
                            <a href="/category/transfer-news" class="text-electric-red font-condensed fw-black italic uppercase text-xs text-decoration-none hover:text-white transition-colors">Full Archive →</a>
                        </div>
                        <div class="space-y-8">
                            <?php foreach ($transferUpdates as $tu): ?>
                                <a href="/post/<?php echo $tu['slug']; ?>" class="flex gap-6 group text-decoration-none">
                                    <div class="w-32 h-24 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                        <img src="<?php echo $tu['image']; ?>" class="w-full h-full object-fit-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($tu['title']); ?>">
                                    </div>
                                    <div class="flex-grow py-1">
                                        <h4 class="text-lg font-condensed fw-black italic text-white uppercase group-hover:text-electric-red transition-colors line-clamp-2 leading-tight mb-2"><?php echo $tu['title']; ?></h4>
                                        <div class="text-[10px] font-monospace text-white/30 uppercase"><?php echo date('M d, H:i', strtotime($tu['publish_date'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
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