<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['COORDINATOR']);

$min = q_one('SELECT MIN(donation_date) AS d FROM donations')['d'] ?? today_default();
function today_default(): string { return date('Y-m-d'); }
$from = get('from') !== '' ? get('from') : $min;
$to   = get('to')   !== '' ? get('to')   : date('Y-m-d');
$err = '';
if (!valid_date($from) || !valid_date($to)) { $err = 'Enter valid dates.'; }
elseif ($from > $to) { $err = 'The "from" date cannot be later than the "to" date.'; }

$rows = [];
if (!$err) {
    $rows = q_all(
        "SELECT c.category_name, c.unit,
           (SELECT COALESCE(SUM(d.quantity), 0) FROM donations d
             WHERE d.category_id = c.category_id AND d.status <> 'CANCELLED'
               AND d.donation_date BETWEEN ? AND ?) AS received,
           (SELECT COALESCE(SUM(x.quantity_released), 0) FROM distributions x
             JOIN donations d ON d.donation_id = x.donation_id
             WHERE d.category_id = c.category_id
               AND x.distribution_date BETWEEN ? AND ?) AS distributed
         FROM donation_categories c
         ORDER BY c.category_name", 'ssss', [$from, $to, $from, $to]);
}
$pageTitle = 'Category Totals';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3">Category totals</h1>
<form class="row g-2 mb-3 align-items-end" method="get">
  <div class="col-auto"><label class="form-label small mb-0" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
  <div class="col-auto"><label class="form-label small mb-0" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
  <div class="col-auto"><button class="btn btn-secondary">Show totals</button></div>
</form>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Category</th><th>Unit</th><th class="text-end">Received</th><th class="text-end">Distributed</th><th class="text-end">Remaining</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $rem = (float)$r['received'] - (float)$r['distributed']; ?>
    <tr><td><?= e($r['category_name']) ?></td><td><?= e($r['unit']) ?></td><td class="text-end"><?= fmt_qty($r['received']) ?></td>
      <td class="text-end"><?= fmt_qty($r['distributed']) ?></td><td class="text-end fw-semibold"><?= fmt_qty($rem) ?></td></tr>
  <?php endforeach; if (!$rows && !$err): ?><tr><td colspan="5" class="text-center text-muted py-4">No categories.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<p class="text-muted small mt-2">Received counts non-cancelled donations dated within the range. Distributed counts releases dated within the range.
Remaining = Received &minus; Distributed for the chosen range.</p>
<?php include __DIR__ . '/includes/footer.php'; ?>
