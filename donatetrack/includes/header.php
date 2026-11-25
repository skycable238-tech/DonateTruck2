<?php
$u = current_user();
$pageTitle = $pageTitle ?? 'DonateTrack';
$self = basename($_SERVER['SCRIPT_NAME']);
function nav_item(string $file, string $label, string $self, array $also = []): string {
    $active = ($self === $file || in_array($self, $also, true)) ? ' active' : '';
    return '<li class="nav-item"><a class="nav-link' . $active . '" href="' . e($file) . '">' . e($label) . '</a></li>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | DonateTrack</title>
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= $u ? e(home_for($u['role'])) : 'login.php' ?>">DonateTrack</a>
    <?php if ($u): ?>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <?php if ($u['role'] === 'DONOR'): ?>
          <?= nav_item('my_donations.php', 'My Donations', $self) ?>
        <?php else: ?>
          <?= nav_item('dashboard.php', 'Dashboard', $self) ?>
          <?= nav_item('donors.php', 'Donors', $self, ['donor_form.php']) ?>
          <?= nav_item('donation_form.php', 'Record Donation', $self) ?>
          <?= nav_item('donations.php', 'Donations', $self, ['donation_view.php']) ?>
          <?php if ($u['role'] === 'COORDINATOR'): ?>
            <?= nav_item('report_totals.php', 'Category Totals', $self) ?>
            <?= nav_item('categories.php', 'Categories', $self, ['category_form.php']) ?>
            <?= nav_item('users.php', 'Users', $self, ['user_form.php']) ?>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
      <span class="navbar-text me-3 small"><?= e($u['full_name']) ?> <span class="badge bg-info text-dark"><?= e($u['role']) ?></span></span>
      <a class="btn btn-outline-light btn-sm" href="logout.php">Log out</a>
    </div>
    <?php endif; ?>
  </div>
</nav>
<main class="container py-4">
<?php foreach (take_flash() as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?> alert-dismissible fade show" role="alert"><?= e($m) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
