<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/donation_logic.php';
$me = require_role(['COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $uid = (int)post('user_id');
    try {
        if ($uid === $me['user_id']) { throw new BusinessRuleError('You cannot deactivate or delete your own account.'); }
        if (post('action') === 'delete') { q_exec('DELETE FROM users WHERE user_id = ?', 'i', [$uid]); flash('success', 'User deleted.'); }
        elseif (post('action') === 'toggle') { q_exec('UPDATE users SET is_active = 1 - is_active WHERE user_id = ?', 'i', [$uid]); flash('success', 'Account status changed.'); }
    } catch (BusinessRuleError $e) { flash('danger', $e->getMessage()); }
      catch (mysqli_sql_exception $e) { flash('danger', db_message($e)); }
    redirect('users.php');
}

$rows = q_all('SELECT u.user_id, u.username, u.full_name, u.role, u.is_active, d.donor_name
               FROM users u LEFT JOIN donors d ON d.user_id = u.user_id ORDER BY u.role, u.username');
$pageTitle = 'Users';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">User accounts</h1><a class="btn btn-primary" href="user_form.php">+ Add user</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Username</th><th>Full name</th><th>Role</th><th>Linked donor</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['username']) ?></td><td><?= e($r['full_name']) ?></td><td><span class="badge bg-info text-dark"><?= e($r['role']) ?></span></td><td><?= e($r['donor_name'] ?? '-') ?></td>
      <td><?= (int)$r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
      <td class="text-nowrap text-end"><a class="btn btn-sm btn-outline-primary" href="user_form.php?id=<?= (int)$r['user_id'] ?>">Edit</a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= (int)$r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
