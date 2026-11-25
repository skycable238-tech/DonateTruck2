<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { redirect(home_for(current_user()['role'])); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post('username');
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Enter both username and password.';
    } else {
        try {
            $row = q_one('SELECT user_id, username, password_hash, full_name, role, is_active FROM users WHERE username = ?', 's', [$username]);
        } catch (mysqli_sql_exception $e) { $row = null; $error = db_message($e); }
        if (!$error) {
            if (!$row || !password_verify($password, $row['password_hash'])) {
                $error = 'Invalid username or password.';
            } elseif (!(int)$row['is_active']) {
                $error = 'This account is deactivated. Contact the coordinator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = ['user_id' => (int)$row['user_id'], 'username' => $row['username'],
                                     'full_name' => $row['full_name'], 'role' => $row['role']];
                redirect(home_for($row['role']));
            }
        }
    }
}
$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm mt-4">
      <div class="card-body p-4">
        <h1 class="h4 mb-1">DonateTrack</h1>
        <p class="text-muted small">Community Donation Tracking and Distribution System</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label" for="username">Username</label>
            <input class="form-control" id="username" name="username" value="<?= e(post('username')) ?>" required autofocus></div>
          <div class="mb-3"><label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" required></div>
          <button class="btn btn-primary w-100">Log in</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
