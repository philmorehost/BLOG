<?php
include __DIR__ . '/../includes/header.php';
$today = date('l, d F Y');
$oddsData = get_ai_insight("Provide a detailed market prices and betting odds report for major upcoming football matches for today, $today. Use Markdown tables and include match dates and times.");
?>
<div class="container py-5">
    <h1 class="font-condensed fw-black italic text-white display-3 mb-5">MARKET <span class="text-danger">PRICES</span></h1>
    <div class="bg-[#0a0e17] p-5 rounded-4 border border-white border-opacity-5 shadow-2xl min-vh-60">
        <div class="intelligence-content text-white opacity-90 fs-6">
            <?php echo parse_markdown($oddsData); ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php';