<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['COORDINATOR', 'VOLUNTEER']);

$counts = array_fill_keys(['RECORDED', 'VERIFIED', 'PARTIAL', 'DISTRIBUTED', 'CANCELLED'], 0);
foreach (q_all('SELECT status, COUNT(*) AS n FROM donations GROUP BY status') as $r) { $counts[$r['status']] = (int)$r['n']; }

$recentDonations = q_all(
    'SELECT d.donation_id, d.donation_date, dn.donor_name, c.category_name, c.unit, d.quantity, d.status
     FROM donations d
     JOIN donors dn ON dn.donor_id = d.donor_id
     JOIN donation_categories c ON c.category_id = d.category_id
     ORDER BY d.created_at DESC, d.donation_id DESC LIMIT 5');
$recentReleases = q_all(
    'SELECT x.distribution_id, x.distribution_date, x.recipient_name, x.recipient_area, x.quantity_released, c.unit,
            dn.donor_name, x.donation_id
     FROM distributions x
     JOIN donations d ON d.donation_id = x.donation_id
     JOIN donors dn ON dn.donor_id = d.donor_id
     JOIN donation_categories c ON c.category_id = d.category_id
     ORDER BY x.distribution_date DESC, x.distribution_id DESC LIMIT 5');

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3">Dashboard</h1>
<div class="row g-3 mb-4">
<?php foreach ($counts as $status => $n): ?>
  <div class="col-6 col-md"><a class="text-decoration-none" href="donations.php?status=<?= e($status) ?>">
    <div class="card card-stat text-center h-100"><div class="card-body py-3">
      <div class="display-6"><?= $n ?></div><?= status_badge($status) ?></div></div></a></div>
<?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card"><div class="card-header d-flex justify-content-between"><strong>Recent donations</strong>
      <a href="donation_form.php" class="btn btn-sm btn-primary">+ Record donation</a></div>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>#</th><th>Date</th><th>Donor</th><th>Category</th><th class="text-end">Qty</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($recentDonations as $r): ?>
          <tr><td><a href="donation_view.php?id=<?= (int)$r['donation_id'] ?>"><?= (int)$r['donation_id'] ?></a></td>
            <td><?= e($r['donation_date']) ?></td><td><?= e($r['donor_name']) ?></td><td><?= e($r['category_name']) ?></td>
            <td class="text-end"><?= fmt_qty($r['quantity']) ?> <?= e($r['unit']) ?></td><td><?= status_badge($r['status']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div></div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-header"><strong>Recent distributions</strong></div>
      <ul class="list-group list-group-flush">
      <?php foreach ($recentReleases as $r): ?>
        <li class="list-group-item small"><a href="donation_view.php?id=<?= (int)$r['donation_id'] ?>">Donation #<?= (int)$r['donation_id'] ?></a>
          &rarr; <?= fmt_qty($r['quantity_released']) ?> <?= e($r['unit']) ?> to <strong><?= e($r['recipient_name']) ?></strong>
          (<?= e($r['recipient_area']) ?>) <span class="text-muted">on <?= e($r['distribution_date']) ?></span></li>
      <?php endforeach; if (!$recentReleases): ?><li class="list-group-item text-muted">No distributions yet.</li><?php endif; ?>
      </ul></div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
