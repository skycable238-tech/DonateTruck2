<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['COORDINATOR']);

$id = (int)get('id');
$c = ['category_name' => '', 'unit' => '', 'description' => '', 'is_active' => 1];
if ($id) { $row = q_one('SELECT * FROM donation_categories WHERE category_id = ?', 'i', [$id]); if (!$row) { flash('danger', 'Category not found.'); redirect('categories.php'); } $c = $row; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $c = ['category_name' => post('category_name'), 'unit' => post('unit'), 'description' => post('description'), 'is_active' => post('is_active') === '1' ? 1 : 0];
    if ($c['category_name'] === '') $errors[] = 'Category name is required.'; elseif (mb_strlen($c['category_name']) > 80) $errors[] = 'Name must be 80 characters or fewer.';
    if ($c['unit'] === '') $errors[] = 'Unit of measure is required.'; elseif (mb_strlen($c['unit']) > 20) $errors[] = 'Unit must be 20 characters or fewer.';
    if (mb_strlen($c['description']) > 255) $errors[] = 'Description must be 255 characters or fewer.';
    if (!$errors) {
        $desc = $c['description'] === '' ? null : $c['description'];
        try {
            if ($id) q_exec('UPDATE donation_categories SET category_name=?, unit=?, description=?, is_active=? WHERE category_id=?', 'sssii', [$c['category_name'], $c['unit'], $desc, $c['is_active'], $id]);
            else     q_exec('INSERT INTO donation_categories (category_name, unit, description, is_active) VALUES (?,?,?,?)', 'sssi', [$c['category_name'], $c['unit'], $desc, $c['is_active']]);
            flash('success', $id ? 'Category updated.' : 'Category added.');
            redirect('categories.php');
        } catch (mysqli_sql_exception $e) { $errors[] = db_message($e, 'A category with this name already exists.'); }
    }
}
$pageTitle = $id ? 'Edit category' : 'Add category';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($pageTitle) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card card-body" novalidate><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label" for="category_name">Category name *</label><input class="form-control" id="category_name" name="category_name" maxlength="80" value="<?= e($c['category_name']) ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="unit">Unit of measure *</label><input class="form-control" id="unit" name="unit" maxlength="20" placeholder="kg, packs, pieces" value="<?= e($c['unit']) ?>" required></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><input class="form-control" id="description" name="description" maxlength="255" value="<?= e($c['description']) ?>"></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$c['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Active (can receive donations)</label></div></div>
  </div>
  <div class="mt-4"><button class="btn btn-primary">Save category</button> <a class="btn btn-outline-secondary" href="categories.php">Cancel</a></div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
