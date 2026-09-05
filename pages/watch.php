<?php
$custom_meta_title = "Live Centre | " . ($settings['name'] ?? 'Football Intelligence');
include __DIR__ . '/../includes/header.php';

$leagues = [
    ['id' => '7', 'name' => 'Premier League'],
    ['id' => '572', 'name' => 'Champions League'],
    ['id' => '11', 'name' => 'La Liga'],
    ['id' => '17', 'name' => 'Serie A'],
    ['id' => '9', 'name' => 'Bundesliga'],
    ['id' => '8', 'name' => 'Ligue 1'],
    ['id' => '573', 'name' => 'Europa League'],
    ['id' => '15', 'name' => 'Eredivisie'],
    ['id' => '637', 'name' => 'Major League Soccer'],
    ['id' => '557', 'name' => 'Saudi Pro League']
];
?>
<div class="container py-5 px-4">
    <h1 class="font-condensed fw-black italic text-white display-5 display-md-3 mb-5 border-bottom border-white border-opacity-10 pb-3 text-sharp uppercase">Live <span class="text-danger">Centre</span></h1>

    <div class="elite-tabs-container">
        <!-- Tabs Header -->
        <div class="elite-tabs-nav no-scrollbar mb-4">
            <?php foreach ($leagues as $index => $league): ?>
                <button class="elite-tab-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-target="#scores-<?php echo $league['id']; ?>">
                    <?php echo $league['name']; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Tabs Content -->
        <div class="elite-tabs-content">
            <?php foreach ($leagues as $index => $league): ?>
                <div class="elite-tab-pane <?php echo $index === 0 ? 'active' : ''; ?>" id="scores-<?php echo $league['id']; ?>">
                    <div class="bg-[#0a0e17] p-2 p-md-4 rounded-4 border border-white border-opacity-5 shadow-2xl overflow-x-auto">
                        <div data-widget-type="entityScores"
                             data-entity-type="league"
                             data-entity-id="<?php echo $league['id']; ?>"
                             data-lang="en"
                             data-widget-id="scores-<?php echo $league['id']; ?>"
                             data-theme="dark">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
    .elite-tabs-nav {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .elite-tab-btn {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.1);
        color: #64748b;
        padding: 12px 25px;
        border-radius: 12px;
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        text-transform: uppercase;
        font-style: italic;
        letter-spacing: 1px;
        white-space: nowrap;
        transition: all 0.3s ease;
    }
    .elite-tab-btn:hover {
        background: rgba(255,255,255,0.08);
        color: #fff;
    }
    .elite-tab-btn.active {
        background: var(--electric-red);
        border-color: var(--electric-red);
        color: #fff;
        box-shadow: 0 0 20px rgba(255,62,62,0.3);
    }
    .elite-tab-pane {
        display: none;
        animation: fadeIn 0.5s ease;
    }
    .elite-tab-pane.active {
        display: block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<script src="https://widgets.365scores.com/main.js"></script>
<script>
    document.querySelectorAll('.elite-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-target');

            // Update buttons
            document.querySelectorAll('.elite-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Update panes
            document.querySelectorAll('.elite-tab-pane').forEach(p => p.classList.remove('active'));
            document.querySelector(target).classList.add('active');
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php';