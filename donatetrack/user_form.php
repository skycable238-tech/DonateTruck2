<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_role(['COORDINATOR']);

$id = (int)get('id');
$f = ['username' => '', 'full_name' => '', 'role' => 'VOLUNTEER', 'is_active' => 1];
if ($id) { $row = q_one('SELECT user_id, username, full_name, role, is_active FROM users WHERE user_id = ?', 'i', [$id]); if (!$row) { flash('danger', 'User not found.'); redirect('users.php'); } $f = $row; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = ['username' => post('username'), 'full_name' => post('full_name'), 'role' => post('role'), 'is_active' => post('is_active') === '1' ? 1 : 0];
    $pw = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $f['username'])) $errors[] = 'Username must be 3-50 characters (letters, numbers, dot, dash, underscore).';
    if ($f['full_name'] === '' || mb_strlen($f['full_name']) > 100) $errors[] = 'Full name is required (max 100 characters).';
    if (!in_array($f['role'], ['COORDINATOR', 'VOLUNTEER', 'DONOR'], true)) $errors[] = 'Choose a valid role.';
    if (!$id && $pw === '') $errors[] = 'Password is required for a new account.';
    if ($pw !== '' && strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($id === $me['user_id'] && ($f['role'] !== 'COORDINATOR' || !$f['is_active'])) $errors[] = 'You cannot change your own role or deactivate yourself.';
    if (!$errors) {
        try {
            if ($id) {
                if ($pw !== '') q_exec('UPDATE users SET username=?, full_name=?, role=?, is_active=?, password_hash=? WHERE user_id=?', 'sssisi', [$f['username'], $f['full_name'], $f['role'], $f['is_active'], password_hash($pw, PASSWORD_DEFAULT), $id]);
                else            q_exec('UPDATE users SET username=?, full_name=?, role=?, is_active=? WHERE user_id=?', 'sssii', [$f['username'], $f['full_name'], $f['role'], $f['is_active'], $id]);
            } else {
                q_exec('INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?,?,?,?,?)', 'ssssi', [$f['username'], password_hash($pw, PASSWORD_DEFAULT), $f['full_name'], $f['role'], $f['is_active']]);
            }
            flash('success', $id ? 'User updated.' : 'User created.');
            redirect('users.php');
        } catch (mysqli_sql_exception $e) { $errors[] = db_message($e, 'That username is already taken.'); }
    }
}
$pageTitle = $id ? 'Edit user' : 'Add user';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($pageTitle) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card card-body" novalidate autocomplete="off"><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="username">Username *</label><input class="form-control" id="username" name="username" maxlength="50" value="<?= e($f['username']) ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="full_name">Full name *</label><input class="form-control" id="full_name" name="full_name" maxlength="100" value="<?= e($f['full_name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="role">Role *</label><select class="form-select" id="role" name="role">
      <?php foreach (['COORDINATOR', 'VOLUNTEER', 'DONOR'] as $r): ?><option <?= $f['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label" for="password">Password <?= $id ? '(leave blank to keep)' : '*' ?></label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password"></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$f['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Active</label></div></div>
  </div>
  <div class="mt-4"><button class="btn btn-primary">Save user</button> <a class="btn btn-outline-secondary" href="users.php">Cancel</a></div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
