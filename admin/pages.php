<?php
admin_header("CMS Pages");

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM pages WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    $success = "Page decommissioned.";
}

// Handle Bulk Deletion
if (isset($_POST['bulk_delete_pages']) && !empty($_POST['selected_pages'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed");
    }
    $ids = array_map('intval', $_POST['selected_pages']);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM pages WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $success = count($ids) . " pages decommissioned in bulk.";
    }
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_page'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed");
    }

    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $title = sanitize($_POST['title']);
    $slug = sanitize($_POST['slug']);
    $content = $_POST['content']; // Markdown content
    $position = $_POST['position'];
    $is_visible = isset($_POST['is_visible']) ? 1 : 0;
    $is_external = isset($_POST['is_external']) ? 1 : 0;
    $external_url = sanitize($_POST['external_url']);

    $meta_title = sanitize($_POST['meta_title']);
    $meta_desc = sanitize($_POST['meta_description']);
    $meta_keys = sanitize($_POST['meta_keywords']);

    if ($id) {
        $stmt = $conn->prepare("UPDATE pages SET title = ?, slug = ?, content = ?, position = ?, is_visible = ?, is_external = ?, external_url = ?, meta_title = ?, meta_description = ?, meta_keywords = ? WHERE id = ?");
        $stmt->execute([$title, $slug, $content, $position, $is_visible, $is_external, $external_url, $meta_title, $meta_desc, $meta_keys, $id]);
        $success = "Page updated.";
    } else {
        $stmt = $conn->prepare("INSERT INTO pages (title, slug, content, position, is_visible, is_external, external_url, meta_title, meta_description, meta_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $slug, $content, $position, $is_visible, $is_external, $external_url, $meta_title, $meta_desc, $meta_keys]);
        $success = "New page published.";
    }
}

// Search logic
$search = sanitize($_GET['search'] ?? '');
$where = "1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (title LIKE ? OR content LIKE ? OR slug LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$stmt = $conn->prepare("SELECT * FROM pages WHERE $where ORDER BY position, title");
$stmt->execute($params);
$pages = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 mb-5">
    <div>
        <h1 class="font-condensed fw-black italic text-white display-5 mb-0">CMS <span class="text-danger">PAGES</span></h1>
        <p class="text-white-50 small font-condensed italic uppercase mb-0"><?php echo count($pages); ?> Sections Identified</p>
    </div>
    <div class="d-flex flex-wrap gap-3">
        <form method="GET" class="position-relative">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="SEARCH PAGES..." class="bg-black border border-white/10 rounded-xl px-4 py-2 text-white font-condensed italic small w-64 focus:border-danger outline-none transition-all">
            <button type="submit" class="position-absolute end-0 top-0 h-100 px-3 text-white-50 hover:text-danger"><i class="bi bi-search"></i></button>
        </form>
        <button type="button" id="bulkDeletePagesBtn" class="btn btn-outline-danger font-condensed fw-black italic px-4 py-2 d-none" onclick="confirmBulkDeletePages()">BULK DELETE</button>
        <button class="btn btn-outline-danger font-condensed fw-black italic px-4 py-2" data-bs-toggle="modal" data-bs-target="#pageModal">NEW PAGE</button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success bg-green-900 bg-opacity-10 border-green-500 border-opacity-20 text-green-500 font-condensed italic uppercase mb-5"><?php echo $success; ?></div>
<?php endif; ?>

<div class="bg-[#0a0e17] rounded-3xl border border-white/5 overflow-hidden shadow-2xl">
    <form id="bulkFormPages" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="bulk_delete_pages" value="1">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead class="bg-black">
                <tr>
                    <th class="ps-5 py-4 border-0" style="width: 40px;">
                        <input type="checkbox" id="selectAllPages" class="form-check-input bg-black border-white/20">
                    </th>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Title / Slug</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0">Position</th>
                    <th class="px-4 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0 text-center">Status</th>
                    <th class="px-5 py-4 text-[10px] font-black uppercase text-secondary tracking-widest border-0 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td class="ps-5 py-4 border-white border-opacity-5">
                        <input type="checkbox" name="selected_pages[]" value="<?php echo $p['id']; ?>" class="form-check-input bg-black border-white/20 page-checkbox">
                    </td>
                    <td class="px-5 py-4 border-white border-opacity-5">
                        <div class="text-white font-bold small uppercase italic"><?php echo $p['title']; ?></div>
                        <div class="text-[10px] text-gray-500 font-mono italic">/<?php echo $p['slug']; ?></div>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5">
                        <span class="text-[10px] font-black uppercase px-2 py-1 rounded bg-white/5 text-gray-400 border border-white/10"><?php echo $p['position']; ?></span>
                    </td>
                    <td class="px-4 py-4 border-white border-opacity-5 text-center">
                        <?php if ($p['is_visible']): ?>
                            <span class="badge bg-success bg-opacity-10 text-success font-condensed px-3 py-1">VISIBLE</span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary font-condensed px-3 py-1">HIDDEN</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 border-white border-opacity-5 text-end">
                        <div class="d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-sm btn-outline-light border-0 edit-page"
                                data-id="<?php echo $p['id']; ?>"
                                data-title="<?php echo htmlspecialchars($p['title']); ?>"
                                data-slug="<?php echo htmlspecialchars($p['slug']); ?>"
                                data-content="<?php echo htmlspecialchars($p['content']); ?>"
                                data-position="<?php echo $p['position']; ?>"
                                data-visible="<?php echo $p['is_visible']; ?>"
                                data-external="<?php echo $p['is_external']; ?>"
                                data-url="<?php echo htmlspecialchars($p['external_url'] ?? ''); ?>"
                                data-mtitle="<?php echo htmlspecialchars($p['meta_title'] ?? ''); ?>"
                                data-mdesc="<?php echo htmlspecialchars($p['meta_description'] ?? ''); ?>"
                                data-mkeys="<?php echo htmlspecialchars($p['meta_keywords'] ?? ''); ?>"
                                data-bs-toggle="modal" data-bs-target="#pageModal">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <a href="/admin/pages?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Decommission this page permanently?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </form>
</div>

<!-- Page Modal -->
<div class="modal fade" id="pageModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary rounded-4">
            <div class="modal-header border-white border-opacity-10">
                <h5 class="modal-title font-condensed fw-black italic text-white uppercase" id="pageModalLabel">Publish CMS Content</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="id" id="page_id">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Page Title</label>
                            <input type="text" name="title" id="page_title" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">URL Slug</label>
                            <input type="text" name="slug" id="page_slug" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-white-50 small uppercase font-black">Content (Markdown & HTML Supported)</label>
                            <textarea name="content" id="page_content" rows="10" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl font-mono"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small uppercase font-black">Menu Position</label>
                            <select name="position" id="page_position" class="form-select bg-black border-white border-opacity-10 text-white rounded-xl">
                                <option value="top">Top Menu</option>
                                <option value="main">Main Menu</option>
                                <option value="footer">Footer Menu</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_visible" id="page_visible" checked>
                                <label class="form-check-label text-white-50 small uppercase font-black ms-2">Visible to Public</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_external" id="page_external">
                                <label class="form-check-label text-white-50 small uppercase font-black ms-2">External Link</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-white-50 small uppercase font-black">External URL (If enabled)</label>
                            <input type="url" name="external_url" id="page_url" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl" placeholder="https://...">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="text-danger font-condensed italic fw-black border-bottom border-white border-opacity-10 pb-2 mb-3">SEO METADATA</h6>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Title</label>
                            <input type="text" name="meta_title" id="page_mtitle" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Description</label>
                            <textarea name="meta_description" id="page_mdesc" rows="3" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small uppercase font-black">Meta Keywords</label>
                            <textarea name="meta_keywords" id="page_mkeys" rows="3" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-white border-opacity-10">
                    <button type="submit" name="save_page" class="btn btn-danger w-100 py-3 rounded-xl font-condensed italic fw-black">COMMIT CONTENT</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const selectAllPages = document.getElementById('selectAllPages');
const pageCheckboxes = document.querySelectorAll('.page-checkbox');
const bulkDeletePagesBtn = document.getElementById('bulkDeletePagesBtn');
const bulkFormPages = document.getElementById('bulkFormPages');

if (selectAllPages) {
    selectAllPages.addEventListener('change', function() {
        pageCheckboxes.forEach(cb => cb.checked = this.checked);
        toggleBulkDeletePagesBtn();
    });
}

pageCheckboxes.forEach(cb => {
    cb.addEventListener('change', toggleBulkDeletePagesBtn);
});

function toggleBulkDeletePagesBtn() {
    const checkedCount = document.querySelectorAll('.page-checkbox:checked').length;
    if (checkedCount > 0) {
        bulkDeletePagesBtn.classList.remove('d-none');
    } else {
        bulkDeletePagesBtn.classList.add('d-none');
    }
}

function confirmBulkDeletePages() {
    const checkedCount = document.querySelectorAll('.page-checkbox:checked').length;
    if (confirm(`Are you sure you want to permanently delete ${checkedCount} pages?`)) {
        bulkFormPages.submit();
    }
}

document.querySelectorAll('.edit-page').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('page_id').value = this.dataset.id;
        document.getElementById('page_title').value = this.dataset.title;
        document.getElementById('page_slug').value = this.dataset.slug;
        document.getElementById('page_content').value = this.dataset.content;
        document.getElementById('page_position').value = this.dataset.position;
        document.getElementById('page_visible').checked = this.dataset.visible == '1';
        document.getElementById('page_external').checked = this.dataset.external == '1';
        document.getElementById('page_url').value = this.dataset.url;
        document.getElementById('page_mtitle').value = this.dataset.mtitle;
        document.getElementById('page_mdesc').value = this.dataset.mdesc;
        document.getElementById('page_mkeys').value = this.dataset.mkeys;
        document.getElementById('pageModalLabel').innerText = 'Update CMS Content';
    });
});
document.getElementById('pageModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('page_id').value = '';
    document.getElementById('page_title').value = '';
    document.getElementById('page_slug').value = '';
    document.getElementById('page_content').value = '';
    document.getElementById('page_position').value = 'main';
    document.getElementById('page_visible').checked = true;
    document.getElementById('page_external').checked = false;
    document.getElementById('page_url').value = '';
    document.getElementById('page_mtitle').value = '';
    document.getElementById('page_mdesc').value = '';
    document.getElementById('page_mkeys').value = '';
    document.getElementById('pageModalLabel').innerText = 'Publish CMS Content';
});
</script>

<?php admin_footer();