<?php
admin_header("Feedback");

if (isset($_GET['approve'])) {
    $stmt = $conn->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
    $stmt->execute([(int)$_GET['approve']]);
}

if (isset($_GET['reject'])) {
    $stmt = $conn->prepare("UPDATE comments SET status = 'rejected' WHERE id = ?");
    $stmt->execute([(int)$_GET['reject']]);
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
}

$comments = $conn->query("SELECT c.*, p.title as post_title FROM comments c JOIN posts p ON c.post_id = p.id ORDER BY c.created_at DESC")->fetchAll();

?>
<h1 class="font-condensed fw-black italic text-white display-5 mb-5">INTELLIGENCE <span class="text-danger">FEEDBACK</span></h1>

<div class="bg-[#0a0e17] rounded-3xl border border-white/5 overflow-hidden shadow-2xl">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead class="bg-black">
                <tr>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Source</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Payload</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Status</th>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $c): ?>
                <tr>
                    <td class="px-5 py-4 border-white border-opacity-5">
                        <div class="text-white font-bold small uppercase italic"><?php echo $c['author']; ?></div>
                        <div class="text-[9px] text-white-50 font-monospace opacity-50"><?php echo $c['post_title']; ?></div>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <div class="text-white-50 small" style="max-width: 300px;"><?php echo $c['text']; ?></div>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <span class="badge <?php echo $c['status'] == 'approved' ? 'bg-success' : ($c['status'] == 'pending' ? 'bg-warning' : 'bg-danger'); ?> text-dark font-condensed italic px-2 py-1 uppercase"><?php echo $c['status']; ?></span>
                    </td>
                    <td class="px-5 py-4 border-white border-opacity-5 text-end">
                        <?php if ($c['status'] == 'pending'): ?>
                            <a href="?approve=<?php echo $c['id']; ?>" class="text-success me-3"><i class="bi bi-check-circle fs-5"></i></a>
                            <a href="?reject=<?php echo $c['id']; ?>" class="text-warning me-3"><i class="bi bi-x-circle fs-5"></i></a>
                        <?php endif; ?>
                        <a href="?delete=<?php echo $c['id']; ?>" class="text-danger" onclick="return confirm('Purge this comment?')"><i class="bi bi-trash fs-5"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php admin_footer();