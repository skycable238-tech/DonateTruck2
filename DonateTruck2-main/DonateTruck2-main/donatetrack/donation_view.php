<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/donation_logic.php';
$u = require_role(['COORDINATOR', 'VOLUNTEER']);

$id = (int)get('id');
$d = q_one('SELECT d.*, dn.donor_name, dn.email, c.category_name, c.unit, u.full_name AS recorded_by_name
            FROM donations d
            JOIN donors dn ON dn.donor_id = d.donor_id
            JOIN donation_categories c ON c.category_id = d.category_id
            JOIN users u ON u.user_id = d.recorded_by
            WHERE d.donation_id = ?', 'i', [$id]);
if (!$d) { flash('danger', 'Donation not found.'); redirect('donations.php'); }

$history = q_all('SELECT x.distribution_date, x.recipient_name, x.recipient_area, x.quantity_released, x.remarks, u.full_name AS released_by_name
                  FROM distributions x JOIN users u ON u.user_id = x.released_by
                  WHERE x.donation_id = ? ORDER BY x.distribution_date, x.distribution_id', 'i', [$id]);
$released  = array_sum(array_map(fn($r) => (float)$r['quantity_released'], $history));
$remaining = remaining_of($id);

$isOwner   = (int)$d['recorded_by'] === $u['user_id'];
$canEdit   = $d['status'] === 'RECORDED' && (is_coord() || $isOwner);
$canDelete = $canEdit;
$canVerify = is_coord() && $d['status'] === 'RECORDED';
$canCancel = is_coord() && in_array($d['status'], ['RECORDED', 'VERIFIED'], true);
$canDist   = is_coord() && in_array($d['status'], ['VERIFIED', 'PARTIAL'], true);

$pageTitle = "Donation #$id";
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Donation #<?= (int)$d['donation_id'] ?> <?= status_badge($d['status']) ?></h1>
  <a class="btn btn-outline-secondary" href="donations.php">&larr; Back to list</a>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header"><strong>Donation details</strong></div>
      <dl class="row m-0 p-3">
        <dt class="col-sm-4">Donor</dt><dd class="col-sm-8"><?= e($d['donor_name']) ?><?= $d['email'] ? ' <span class="text-muted">(' . e($d['email']) . ')</span>' : '' ?></dd>
        <dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?= e($d['category_name']) ?></dd>
        <dt class="col-sm-4">Date received</dt><dd class="col-sm-8"><?= e($d['donation_date']) ?></dd>
        <dt class="col-sm-4">Quantity</dt><dd class="col-sm-8"><?= fmt_qty($d['quantity']) ?> <?= e($d['unit']) ?></dd>
        <dt class="col-sm-4">Distributed</dt><dd class="col-sm-8"><?= fmt_qty($released) ?> <?= e($d['unit']) ?></dd>
        <dt class="col-sm-4">Remaining</dt><dd class="col-sm-8"><strong><?= $d['status'] === 'CANCELLED' ? '-' : fmt_qty($remaining) . ' ' . e($d['unit']) ?></strong></dd>
        <dt class="col-sm-4">Recorded by</dt><dd class="col-sm-8"><?= e($d['recorded_by_name']) ?> <span class="text-muted small">on <?= e($d['created_at']) ?></span></dd>
        <dt class="col-sm-4">Notes</dt><dd class="col-sm-8"><?= e($d['notes'] ?: '-') ?></dd>
        <?php if ($d['status'] === 'CANCELLED'): ?><dt class="col-sm-4">Cancel reason</dt><dd class="col-sm-8 text-danger"><?= e($d['cancel_reason']) ?></dd><?php endif; ?>
      </dl>
      <?php if ($canEdit || $canVerify || $canCancel): ?>
      <div class="card-footer d-flex flex-wrap gap-2">
        <?php if ($canEdit): ?><a class="btn btn-outline-primary btn-sm" href="donation_form.php?id=<?= $id ?>">Edit</a><?php endif; ?>
        <?php if ($canVerify): ?><form method="post" action="donation_action.php"><?= csrf_field() ?><input type="hidden" name="donation_id" value="<?= $id ?>"><input type="hidden" name="action" value="verify"><button class="btn btn-primary btn-sm">Verify</button></form><?php endif; ?>
        <?php if ($canDelete): ?><form method="post" action="donation_action.php" onsubmit="return confirm('Delete this donation?');"><?= csrf_field() ?><input type="hidden" name="donation_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm">Delete</button></form><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <?php if ($canDist): ?>
    <div class="card mb-3"><div class="card-header"><strong>Distribute to a recipient</strong> <span class="text-muted small">(remaining: <?= fmt_qty($remaining) ?> <?= e($d['unit']) ?>)</span></div>
      <form method="post" action="donation_action.php" class="card-body" novalidate>
        <?= csrf_field() ?><input type="hidden" name="donation_id" value="<?= $id ?>"><input type="hidden" name="action" value="distribute">
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label small mb-0" for="recipient_name">Recipient *</label><input class="form-control" id="recipient_name" name="recipient_name" maxlength="120" required></div>
          <div class="col-md-6"><label class="form-label small mb-0" for="recipient_area">Area *</label><input class="form-control" id="recipient_area" name="recipient_area" maxlength="120" required></div>
          <div class="col-md-6"><label class="form-label small mb-0" for="quantity">Quantity (<?= e($d['unit']) ?>) *</label><input class="form-control" id="quantity" name="quantity" inputmode="decimal" required></div>
          <div class="col-md-6"><label class="form-label small mb-0" for="distribution_date">Date *</label><input class="form-control" type="date" id="distribution_date" name="distribution_date" min="<?= e($d['donation_date']) ?>" value="<?= e(max(today(), $d['donation_date'])) ?>" required></div>
          <div class="col-12"><label class="form-label small mb-0" for="remarks">Remarks</label><input class="form-control" id="remarks" name="remarks" maxlength="255"></div>
        </div>
        <button class="btn btn-success mt-3">Save distribution</button>
      </form></div>
    <?php endif; ?>
    <?php if ($canCancel): ?>
    <div class="card mb-3"><div class="card-body">
      <form method="post" action="donation_action.php" class="row g-2 align-items-end" onsubmit="return confirm('Cancel this donation? This is final.');">
        <?= csrf_field() ?><input type="hidden" name="donation_id" value="<?= $id ?>"><input type="hidden" name="action" value="cancel">
        <div class="col-md-8"><label class="form-label small mb-0" for="cancel_reason">Cancel reason *</label><input class="form-control" id="cancel_reason" name="cancel_reason" maxlength="255" required></div>
        <div class="col-md-4"><button class="btn btn-outline-danger w-100">Cancel donation</button></div>
      </form></div></div>
    <?php endif; ?>
    <div class="card"><div class="card-header"><strong>Distribution history</strong></div>
      <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Date</th><th>Recipient</th><th>Area</th><th class="text-end">Qty</th><th>Released by</th></tr></thead><tbody>
        <?php foreach ($history as $h): ?><tr><td><?= e($h['distribution_date']) ?></td><td><?= e($h['recipient_name']) ?></td><td><?= e($h['recipient_area']) ?></td>
          <td class="text-end"><?= fmt_qty($h['quantity_released']) ?></td><td><?= e($h['released_by_name']) ?></td></tr>
        <?php endforeach; if (!$history): ?><tr><td colspan="5" class="text-center text-muted py-3">No distributions yet.</td></tr><?php endif; ?>
        </tbody></table></div></div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
