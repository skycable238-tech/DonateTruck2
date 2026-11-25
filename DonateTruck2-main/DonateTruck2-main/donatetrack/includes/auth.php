<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
start_session();

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function home_for(string $role): string {
    return $role === 'DONOR' ? 'my_donations.php' : 'dashboard.php';
}

/** Require a logged-in user whose role is in $roles (empty = any role). */
function require_role(array $roles = []): array {
    $u = current_user();
    if (!$u) { redirect('login.php'); }
    // Re-check the account on every request so deactivation or a role change applies immediately.
    $fresh = q_one('SELECT full_name, role, is_active FROM users WHERE user_id = ?', 'i', [$u['user_id']]);
    if (!$fresh || !(int)$fresh['is_active']) {
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('warning', 'Your account is no longer active.');
        redirect('login.php');
    }
    $_SESSION['user']['role'] = $fresh['role'];
    $_SESSION['user']['full_name'] = $fresh['full_name'];
    $u = $_SESSION['user'];
    if ($roles && !in_array($u['role'], $roles, true)) { deny(); }
    return $u;
}

/** BR-08: unauthorized action -> HTTP 403 "Access denied" page. */
function deny(): never {
    http_response_code(403);
    $pageTitle = 'Access denied';
    include __DIR__ . '/header.php';
    echo '<div class="alert alert-danger mt-4"><h4 class="alert-heading">Access denied</h4>'
       . '<p class="mb-0">Your role is not allowed to perform this action or open this page.</p></div>';
    include __DIR__ . '/footer.php';
    exit;
}

function is_coord(): bool { return (current_user()['role'] ?? '') === 'COORDINATOR'; }
