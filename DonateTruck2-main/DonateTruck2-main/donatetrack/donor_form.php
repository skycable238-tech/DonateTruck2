<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['COORDINATOR', 'VOLUNTEER']);

$id = (int)get('id');
$donor = ['donor_name' => '', 'donor_type' => 'INDIVIDUAL', 'email' => '', 'phone' => '', 'address' => '', 'is_active' => 1, 'user_id' => null];
if ($id) {
    $row = q_one('SELECT * FROM donors WHERE donor_id = ?', 'i', [$id]);
    if (!$row) { flash('danger', 'Donor not found.'); redirect('donors.php'); }
    $donor = $row;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $donor['donor_name'] = post('donor_name');
    $donor['donor_type'] = post('donor_type');
    $donor['email']      = post('email');
    $donor['phone']      = post('phone');
    $donor['address']    = post('address');
    $donor['is_active']  = post('is_active') === '1' ? 1 : 0;
    if (is_coord()) { $donor['user_id'] = post('user_id') === '' ? null : (int)post('user_id'); }

    if ($donor['donor_name'] === '') $errors[] = 'Donor name is required.';
    elseif (mb_strlen($donor['donor_name']) > 120) $errors[] = 'Donor name must be 120 characters or fewer.';
    if (!in_array($donor['donor_type'], ['INDIVIDUAL', 'ORGANIZATION', 'BUSINESS'], true)) $errors[] = 'Choose a valid donor type.';
    if ($donor['email'] !== '' && !filter_var($donor['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (mb_strlen($donor['phone']) > 30) $errors[] = 'Phone must be 30 characters or fewer.';
    if (mb_strlen($donor['address']) > 255) $errors[] = 'Address must be 255 characters or fewer.';

    if (!$errors) {
        $email = $donor['email'] === '' ? null : $donor['email'];     // NULL so UNIQUE allows many blanks
        $phone = $donor['phone'] === '' ? null : $donor['phone'];
        $addr  = $donor['address'] === '' ? null : $donor['address'];
        try {
            if ($id) {
                if (is_coord()) {
                    q_exec('UPDATE donors SET donor_name=?, donor_type=?, email=?, phone=?, address=?, is_active=?, user_id=? WHERE donor_id=?',
                        'sssssiii', [$donor['donor_name'], $donor['donor_type'], $email, $phone, $addr, $donor['is_active'], $donor['user_id'], $id]);
                } else {
                    q_exec('UPDATE donors SET donor_name=?, donor_type=?, email=?, phone=?, address=?, is_active=? WHERE donor_id=?',
                        'sssssii', [$donor['donor_name'], $donor['donor_type'], $email, $phone, $addr, $donor['is_active'], $id]);
                }
                flash('success', 'Donor updated.');
            } else {
                q_exec('INSERT INTO donors (donor_name, donor_type, email, phone, address, is_active, user_id) VALUES (?,?,?,?,?,?,?)',
                    'sssssii', [$donor['donor_name'], $donor['donor_type'], $email, $phone, $addr, $donor['is_active'], $donor['user_id']]);
                flash('success', 'Donor added.');
            }
            redirect('donors.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = db_message($e, 'A donor with this email already exists.');
            if ((int)$e->getCode() === 1062 && stripos($e->getMessage(), 'uq_donors_user') !== false) {
                $errors = ['That portal account is already linked to another donor.'];
            }
        }
    }
}
$portalUsers = is_coord() ? q_all(
    "SELECT u.user_id, u.username, u.full_name FROM users u
     LEFT JOIN donors d ON d.user_id = u.user_id
     WHERE u.role = 'DONOR' AND (d.donor_id IS NULL OR d.donor_id = ?) ORDER BY u.username", 'i', [$id]) : [];

$pageTitle = $id ? 'Edit donor' : 'Add donor';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($pageTitle) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card card-body" novalidate>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label" for="donor_name">Donor name *</label>
      <input class="form-control" id="donor_name" name="donor_name" maxlength="120" value="<?= e($donor['donor_name']) ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="donor_type">Type *</label>
      <select class="form-select" id="donor_type" name="donor_type">
        <?php foreach (['INDIVIDUAL', 'ORGANIZATION', 'BUSINESS'] as $t): ?>
          <option value="<?= $t ?>" <?= $donor['donor_type'] === $t ? 'selected' : '' ?>><?= ucfirst(strtolower($t)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label" for="email">Email</label>
      <input class="form-control" id="email" name="email" type="email" maxlength="120" value="<?= e($donor['email']) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="phone">Phone</label>
      <input class="form-control" id="phone" name="phone" maxlength="30" value="<?= e($donor['phone']) ?>"></div>
    <div class="col-12"><label class="form-label" for="address">Address</label>
      <input class="form-control" id="address" name="address" maxlength="255" value="<?= e($donor['address']) ?>"></div>
    <?php if (is_coord()): ?>
    <div class="col-md-6"><label class="form-label" for="user_id">Donor portal account</label>
      <select class="form-select" id="user_id" name="user_id"><option value="">None</option>
        <?php foreach ($portalUsers as $p): ?><option value="<?= (int)$p['user_id'] ?>" <?= (int)$donor['user_id'] === (int)$p['user_id'] ? 'selected' : '' ?>><?= e($p['username']) ?> (<?= e($p['full_name']) ?>)</option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="col-md-6 d-flex align-items-end"><div class="form-check">
      <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$donor['is_active'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="is_active">Active</label></div></div>
  </div>
  <div class="mt-4"><button class="btn btn-primary">Save donor</button> <a class="btn btn-outline-secondary" href="donors.php">Cancel</a></div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
