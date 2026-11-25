<?php
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function flash(string $type, string $msg): void { start_session(); $_SESSION['flash'][] = [$type, $msg]; }
function take_flash(): array { start_session(); $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function redirect(string $url): never { header('Location: ' . $url); exit; }

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    start_session();
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) { http_response_code(400); exit('Invalid or expired form. Go back, refresh the page and try again.'); }
}

function post(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function get(string $k): string { return trim((string)($_GET[$k] ?? '')); }

function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

/** Accepts a positive number with up to 2 decimals. */
function valid_qty(string $q): bool {
    return preg_match('/^\d{1,10}(\.\d{1,2})?$/', $q) === 1 && (float)$q > 0;
}

function fmt_qty($n): string {
    $s = number_format((float)$n, 2, '.', ',');
    return rtrim(rtrim($s, '0'), '.');
}

function status_badge(string $s): string {
    $map = ['RECORDED' => 'secondary', 'VERIFIED' => 'primary', 'PARTIAL' => 'warning text-dark',
            'DISTRIBUTED' => 'success', 'CANCELLED' => 'danger'];
    return '<span class="badge bg-' . ($map[$s] ?? 'secondary') . '">' . e($s) . '</span>';
}

/** Allowed status transitions (BR-03). In Phase 3 a trigger repeats this rule. */
function transition_allowed(string $from, string $to): bool {
    $allowed = [
        'RECORDED' => ['VERIFIED', 'CANCELLED'],
        'VERIFIED' => ['PARTIAL', 'DISTRIBUTED', 'CANCELLED'],
        'PARTIAL'  => ['DISTRIBUTED'],
    ];
    return in_array($to, $allowed[$from] ?? [], true);
}

/** Build a query string that keeps current filters but changes some keys. */
function qs(array $change = []): string {
    $q = array_merge($_GET, $change);
    foreach ($q as $k => $v) { if ($v === '' || $v === null) unset($q[$k]); }
    return http_build_query($q);
}
