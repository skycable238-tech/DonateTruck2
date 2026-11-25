<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_role(['COORDINATOR', 'VOLUNTEER']);

$search = get('q'); $status = get('status'); $cat = (int)get('category_id'); $donor = (int)get('donor_id');
$from = get('from'); $to = get('to'); $page = max(1, (int)get('page')); $per = cfg('per_page');

// Whitelisted sort columns (never put user text into ORDER BY)
$sortMap = ['donation_id' => 'd.donation_id', 'donation_date' => 'd.donation_date', 'donor_name' => 'dn.donor_name',
            'category_name' => 'c.category_name', 'quantity' => 'd.quantity', 'remaining' => 'remaining', 'status' => 'd.status'];
$sort = array_key_exists(get('sort'), $sortMap) ? get('sort') : 'donation_date';
$dir  = strtolower(get('dir')) === 'asc' ? 'ASC' : 'DESC';

$where = ['1=1']; $types = ''; $params = [];
if ($search !== '') { $where[] = "dn.donor_name LIKE CONCAT('%', ?, '%')"; $types .= 's'; $params[] = $search; }
if (in_array($status, ['RECORDED', 'VERIFIED', 'PARTIAL', 'DISTRIBUTED', 'CANCELLED'], true)) { $where[] = 'd.status = ?'; $types .= 's'; $params[] = $status; }
if ($cat > 0)   { $where[] = 'd.category_id = ?'; $types .= 'i'; $params[] = $cat; }
if ($donor > 0) { $where[] = 'd.donor_id = ?';    $types .= 'i'; $params[] = $donor; }
$filterErr = '';
if ($from !== '' && !valid_date($from)) { $filterErr = 'The "from" date is not valid and was ignored.'; $from = ''; }
if ($to !== '' && !valid_date($to))     { $filterErr = 'The "to" date is not valid and was ignored.'; $to = ''; }
if ($from !== '') { $where[] = 'd.donation_date >= ?'; $types .= 's'; $params[] = $from; }
if ($to !== '')   { $where[] = 'd.donation_date <= ?'; $types .= 's'; $params[] = $to; }
$w = implode(' AND ', $where);

$total = (int)q_one("SELECT COUNT(*) AS n FROM donations d JOIN donors dn ON dn.donor_id = d.donor_id WHERE $w", $types, $params)['n'];
$pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages); $off = ($page - 1) * $per;

$rows = q_all(
    "SELECT d.donation_id, d.donation_date, dn.donor_name, c.category_name, c.unit, d.quantity,
            d.quantity - COALESCE(SUM(x.quantity_released), 0) AS remaining, d.status
     FROM donations d
     JOIN donors dn ON dn.donor_id = d.donor_id
     JOIN donation_categories c ON c.category_id = d.category_id
     LEFT JOIN distributions x ON x.donation_id = d.donation_id
     WHERE $w
     GROUP BY d.donation_id, d.donation_date, dn.donor_name, c.category_name, c.unit, d.quantity, d.status
     ORDER BY {$sortMap[$sort]} $dir, d.donation_id DESC
     LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$per, $off]));

$allCats   = q_all('SELECT category_id, category_name FROM donation_categories ORDER BY category_name');
$allDonors = q_all('SELECT donor_id, donor_name FROM donors ORDER BY donor_name');

function sort_link(string $col, string $label, string $cur, string $dir): string {
    $nd = ($cur === $col && $dir === 'ASC') ? 'desc' : 'asc';
    $arrow = $cur === $col ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a class="sort" href="?' . e(qs(['sort' => $col, 'dir' => $nd, 'page' => 1])) . '">' . e($label) . $arrow . '</a>';
}
$pageTitle = 'Donations';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Donations</h1><a class="btn btn-primary" href="donation_form.php">+ Record donation</a>
</div>
<?php if ($filterErr): ?><div class="alert alert-warning py-2"><?= e($filterErr) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><input class="form-control" name="q" placeholder="Search donor name" value="<?= e($search) ?>"></div>
  <div class="col-md-2"><select class="form-select" name="donor_id"><option value="">All donors</option>
    <?php foreach ($allDonors as $d): ?><option value="<?= (int)$d['donor_id'] ?>" <?= $donor === (int)$d['donor_id'] ? 'selected' : '' ?>><?= e($d['donor_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select" name="category_id"><option value="">All categories</option>
    <?php foreach ($allCats as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= $cat === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select" name="status"><option value="">All statuses</option>
    <?php foreach (['RECORDED', 'VERIFIED', 'PARTIAL', 'DISTRIBUTED', 'CANCELLED'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3 d-flex gap-1"><input class="form-control" type="date" name="from" value="<?= e($from) ?>" title="From date"><input class="form-control" type="date" name="to" value="<?= e($to) ?>" title="To date"></div>
  <div class="col-12"><button class="btn btn-secondary">Apply filters</button> <a class="btn btn-outline-secondary" href="donations.php">Reset</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr>
    <th><?= sort_link('donation_id', '#', $sort, $dir) ?></th><th><?= sort_link('donation_date', 'Date', $sort, $dir) ?></th>
    <th><?= sort_link('donor_name', 'Donor', $sort, $dir) ?></th><th><?= sort_link('category_name', 'Category', $sort, $dir) ?></th>
    <th class="text-end"><?= sort_link('quantity', 'Quantity', $sort, $dir) ?></th><th class="text-end"><?= sort_link('remaining', 'Remaining', $sort, $dir) ?></th>
    <th><?= sort_link('status', 'Status', $sort, $dir) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= (int)$r['donation_id'] ?></td><td><?= e($r['donation_date']) ?></td><td><?= e($r['donor_name']) ?></td><td><?= e($r['category_name']) ?></td>
      <td class="text-end"><?= fmt_qty($r['quantity']) ?> <?= e($r['unit']) ?></td>
      <td class="text-end"><?= $r['status'] === 'CANCELLED' ? '-' : fmt_qty($r['remaining']) . ' ' . e($r['unit']) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="donation_view.php?id=<?= (int)$r['donation_id'] ?>">View</a></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No donations match your filters.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/includes/pager.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
