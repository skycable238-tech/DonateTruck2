<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/donation_logic.php';
$u = require_role(['COORDINATOR', 'VOLUNTEER']);

$id = (int)get('id');
$in = ['donor_id' => (int)get('donor_id'), 'category_id' => 0, 'quantity' => '', 'donation_date' => today(), 'notes' => ''];
if ($id) {
    $row = q_one('SELECT * FROM donations WHERE donation_id = ?', 'i', [$id]);
    if (!$row) { flash('danger', 'Donation not found.'); redirect('donations.php'); }
    // Volunteers may edit only their own donations, and only while RECORDED (BR-08)
    if ($row['status'] !== 'RECORDED' || (!is_coord() && (int)$row['recorded_by'] !== $u['user_id'])) {
        if ($row['status'] !== 'RECORDED') { flash('warning', 'Only RECORDED donations can be edited.'); redirect('donation_view.php?id=' . $id); }
        deny();
    }
    $in = ['donor_id' => (int)$row['donor_id'], 'category_id' => (int)$row['category_id'], 'quantity' => fmt_qty($row['quantity']),
           'donation_date' => $row['donation_date'], 'notes' => (string)$row['notes']];
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $in = ['donor_id' => (int)post('donor_id'), 'category_id' => (int)post('category_id'), 'quantity' => str_replace(',', '', post('quantity')),
           'donation_date' => post('donation_date'), 'notes' => post('notes')];
    try {
        $errors = validate_donation($in);
        if (!$errors) {
            if ($id) {
                with_transaction(function () use ($id, $in) {
                    $cur = lock_donation($id);
                    if ($cur['status'] !== 'RECORDED') throw new BusinessRuleError('Only RECORDED donations can be edited.');
                    $notes = $in['notes'] === '' ? null : $in['notes'];
                    q_exec('UPDATE donations SET donor_id=?, category_id=?, donation_date=?, quantity=?, notes=? WHERE donation_id=?',
                        'iisdsi', [$in['donor_id'], $in['category_id'], $in['donation_date'], $in['quantity'], $notes, $id]);
                });
                flash('success', "Donation #$id updated.");
                redirect('donation_view.php?id=' . $id);
            } else {
                $newId = record_donation($in, $u['user_id']);
                flash('success', "Donation #$newId recorded with status RECORDED.");
                redirect('donation_view.php?id=' . $newId);
            }
        }
    } catch (BusinessRuleError $e) { $errors[] = $e->getMessage(); }
      catch (mysqli_sql_exception $e) { $errors[] = db_message($e); }
}
$donors = q_all('SELECT donor_id, donor_name, is_active FROM donors WHERE is_active = 1 OR donor_id = ? ORDER BY donor_name', 'i', [$in['donor_id']]);
$cats   = q_all('SELECT category_id, category_name, unit, is_active FROM donation_categories WHERE is_active = 1 OR category_id = ? ORDER BY category_name', 'i', [$in['category_id']]);

$pageTitle = $id ? "Edit donation #$id" : 'Record donation';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($pageTitle) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card card-body" novalidate>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="donor_id">Donor *</label>
      <select class="form-select" id="donor_id" name="donor_id" required><option value="">Select donor</option>
        <?php foreach ($donors as $d): ?><option value="<?= (int)$d['donor_id'] ?>" <?= $in['donor_id'] === (int)$d['donor_id'] ? 'selected' : '' ?>><?= e($d['donor_name']) ?><?= (int)$d['is_active'] ? '' : ' (inactive)' ?></option><?php endforeach; ?></select>
      <div class="form-text">Not listed? <a href="donor_form.php">Add a donor</a> first.</div></div>
    <div class="col-md-6"><label class="form-label" for="category_id">Category *</label>
      <select class="form-select" id="category_id" name="category_id" required><option value="">Select category</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= $in['category_id'] === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?> (<?= e($c['unit']) ?>)<?= (int)$c['is_active'] ? '' : ' - inactive' ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label" for="quantity">Quantity *</label>
      <input class="form-control" id="quantity" name="quantity" inputmode="decimal" value="<?= e($in['quantity']) ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="donation_date">Date received *</label>
      <input class="form-control" type="date" id="donation_date" name="donation_date" max="<?= e(today()) ?>" value="<?= e($in['donation_date']) ?>" required></div>
    <div class="col-12"><label class="form-label" for="notes">Notes</label>
      <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="500"><?= e($in['notes']) ?></textarea></div>
  </div>
  <div class="mt-4"><button class="btn btn-primary">Save as RECORDED</button> <a class="btn btn-outline-secondary" href="donations.php">Cancel</a></div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
