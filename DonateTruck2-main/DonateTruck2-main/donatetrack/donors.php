<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['COORDINATOR', 'VOLUNTEER']);

// Delete (BR-06: the foreign keys refuse it when the donor already has donations)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    csrf_check();
    try {
        q_exec('DELETE FROM donors WHERE donor_id = ?', 'i', [(int)post('donor_id')]);
        flash('success', 'Donor deleted.');
    } catch (mysqli_sql_exception $e) { flash('danger', db_message($e)); }
    redirect('donors.php?' . qs());
}
// Activate / deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'toggle') {
    csrf_check();
    try {
        q_exec('UPDATE donors SET is_active = 1 - is_active WHERE donor_id = ?', 'i', [(int)post('donor_id')]);
        flash('success', 'Donor status changed.');
    } catch (mysqli_sql_exception $e) { flash('danger', db_message($e)); }
    redirect('donors.php?' . qs());
}

$search = get('q');
$type   = get('type');
$page   = max(1, (int)get('page'));
$per    = cfg('per_page');

$where = ['1=1']; $types = ''; $params = [];
if ($search !== '') {
    $where[] = '(dn.donor_name LIKE CONCAT(\'%\', ?, \'%\') OR dn.email LIKE CONCAT(\'%\', ?, \'%\'))';
    $types .= 'ss'; array_push($params, $search, $search);
}
if (in_array($type, ['INDIVIDUAL', 'ORGANIZATION', 'BUSINESS'], true)) { $where[] = 'dn.donor_type = ?'; $types .= 's'; $params[] = $type; }
$w = implode(' AND ', $where);

$total = (int)q_one("SELECT COUNT(*) AS n FROM donors dn WHERE $w", $types, $params)['n'];
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$off   = ($page - 1) * $per;

$rows = q_all(
    "SELECT dn.donor_id, dn.donor_name, dn.donor_type, dn.email, dn.phone, dn.is_active,
            u.username AS portal_user, COUNT(d.donation_id) AS donation_count
     FROM donors dn
     LEFT JOIN users u ON u.user_id = dn.user_id
     LEFT JOIN donations d ON d.donor_id = dn.donor_id
     WHERE $w
     GROUP BY dn.donor_id, dn.donor_name, dn.donor_type, dn.email, dn.phone, dn.is_active, u.username
     ORDER BY dn.donor_name
     LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$per, $off]));

$pageTitle = 'Donors';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Donors</h1><a class="btn btn-primary" href="donor_form.php">+ Add donor</a>
</div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input class="form-control" name="q" placeholder="Search name or email" value="<?= e($search) ?>"></div>
  <div class="col-md-3"><select class="form-select" name="type"><option value="">All types</option>
    <?php foreach (['INDIVIDUAL', 'ORGANIZATION', 'BUSINESS'] as $t): ?>
      <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst(strtolower($t)) ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-secondary">Search</button> <a class="btn btn-outline-secondary" href="donors.php">Reset</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Name</th><th>Type</th><th>Email</th><th>Phone</th><th>Portal</th><th class="text-center">Donations</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['donor_name']) ?></td><td><?= e(ucfirst(strtolower($r['donor_type']))) ?></td><td><?= e($r['email']) ?></td>
      <td><?= e($r['phone']) ?></td><td><?= e($r['portal_user'] ?? '-') ?></td><td class="text-center"><?= (int)$r['donation_count'] ?></td>
      <td><?= (int)$r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
      <td class="text-nowrap text-end">
        <a class="btn btn-sm btn-outline-primary" href="donor_form.php?id=<?= (int)$r['donor_id'] ?>">Edit</a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="donor_id" value="<?= (int)$r['donor_id'] ?>">
          <button class="btn btn-sm btn-outline-secondary"><?= (int)$r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this donor?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="donor_id" value="<?= (int)$r['donor_id'] ?>">
          <button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No donors found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/includes/pager.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
