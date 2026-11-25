<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cid = (int)post('category_id');
    try {
        if (post('action') === 'delete') { q_exec('DELETE FROM donation_categories WHERE category_id = ?', 'i', [$cid]); flash('success', 'Category deleted.'); }
        elseif (post('action') === 'toggle') { q_exec('UPDATE donation_categories SET is_active = 1 - is_active WHERE category_id = ?', 'i', [$cid]); flash('success', 'Category status changed.'); }
    } catch (mysqli_sql_exception $e) { flash('danger', db_message($e)); }
    redirect('categories.php');
}
$rows = q_all('SELECT c.category_id, c.category_name, c.unit, c.description, c.is_active, COUNT(d.donation_id) AS donation_count
               FROM donation_categories c LEFT JOIN donations d ON d.category_id = c.category_id
               GROUP BY c.category_id, c.category_name, c.unit, c.description, c.is_active ORDER BY c.category_name');
$pageTitle = 'Categories';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Donation categories</h1><a class="btn btn-primary" href="category_form.php">+ Add category</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Name</th><th>Unit</th><th>Description</th><th class="text-center">Donations</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['category_name']) ?></td><td><?= e($r['unit']) ?></td><td><?= e($r['description']) ?></td><td class="text-center"><?= (int)$r['donation_count'] ?></td>
      <td><?= (int)$r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
      <td class="text-nowrap text-end"><a class="btn btn-sm btn-outline-primary" href="category_form.php?id=<?= (int)$r['category_id'] ?>">Edit</a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="category_id" value="<?= (int)$r['category_id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= (int)$r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this category?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= (int)$r['category_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
