<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['DONOR']);

// Query 4: a donor only ever sees rows linked to their own account (donors.user_id)
$rows = q_all(
    'SELECT d.donation_id, d.donation_date, c.category_name, c.unit, d.quantity, d.status,
            COALESCE(SUM(x.quantity_released), 0) AS distributed
     FROM donations d
     JOIN donors dn ON dn.donor_id = d.donor_id
     JOIN donation_categories c ON c.category_id = d.category_id
     LEFT JOIN distributions x ON x.donation_id = d.donation_id
     WHERE dn.user_id = ?
     GROUP BY d.donation_id, d.donation_date, c.category_name, c.unit, d.quantity, d.status
     ORDER BY d.donation_date DESC', 'i', [$u['user_id']]);
$pageTitle = 'My Donations';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3">My donations</h1>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>#</th><th>Date</th><th>Category</th><th class="text-end">Quantity</th><th class="text-end">Distributed</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= (int)$r['donation_id'] ?></td><td><?= e($r['donation_date']) ?></td><td><?= e($r['category_name']) ?></td>
      <td class="text-end"><?= fmt_qty($r['quantity']) ?> <?= e($r['unit']) ?></td><td class="text-end"><?= fmt_qty($r['distributed']) ?> <?= e($r['unit']) ?></td><td><?= status_badge($r['status']) ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No donations are linked to your account yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
