<?php
admin_header("Network");

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM subscribers WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
}

$subs = $conn->query("SELECT * FROM subscribers ORDER BY created_at DESC")->fetchAll();

?>
<h1 class="font-condensed fw-black italic text-white display-5 mb-5">SYNDICATION <span class="text-danger">NETWORK</span></h1>

<div class="bg-[#0a0e17] rounded-3xl border border-white/5 overflow-hidden shadow-2xl">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead class="bg-black">
                <tr>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Target Address</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Registry Date</th>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subs as $s): ?>
                <tr>
                    <td class="px-5 py-4 border-white border-opacity-5">
                        <div class="text-white font-mono small"><?php echo $s['email']; ?></div>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <span class="text-white-50 font-monospace small"><?php echo date('Y-m-d H:i', strtotime($s['created_at'])); ?></span>
                    </td>
                    <td class="px-5 py-4 border-white border-opacity-5 text-end">
                        <a href="?delete=<?php echo $s['id']; ?>" class="text-danger" onclick="return confirm('Decommission target?')"><i class="bi bi-trash fs-5"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php admin_footer();