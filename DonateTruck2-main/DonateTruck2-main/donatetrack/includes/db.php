<?php
// mysqli connection in exception mode (errors are caught in each page).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function cfg(string $key) {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/../config/config.php'; }
    return $c[$key];
}

function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new mysqli(cfg('db_host'), cfg('db_user'), cfg('db_pass'), cfg('db_name'), cfg('db_port'));
            $conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            error_log('DB connect failed: ' . $e->getMessage());
            http_response_code(500);
            exit('The database is unavailable. Please check config/config.php and that MySQL is running.');
        }
    }
    return $conn;
}

/**
 * Run a prepared statement and return all rows (SELECT).
 * $types uses mysqli letters (s, i, d); pass '' when there are no parameters.
 */
function q_all(string $sql, string $types = '', array $params = []): array {
    $st = db()->prepare($sql);
    if ($types !== '') { $st->bind_param($types, ...$params); }
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function q_one(string $sql, string $types = '', array $params = []): ?array {
    $rows = q_all($sql, $types, $params);
    return $rows[0] ?? null;
}

/** Run INSERT/UPDATE/DELETE; returns [affected_rows, insert_id]. */
function q_exec(string $sql, string $types = '', array $params = []): array {
    $st = db()->prepare($sql);
    if ($types !== '') { $st->bind_param($types, ...$params); }
    $st->execute();
    $r = [$st->affected_rows, $st->insert_id];
    $st->close();
    return $r;
}

/** Translate a MySQL/MariaDB exception into a clear, user-friendly message. */
function db_message(mysqli_sql_exception $e, string $dupMsg = 'That value already exists.'): string {
    $code = (int)$e->getCode();
    error_log('[DB ' . $code . '] ' . $e->getMessage());
    switch ($code) {
        case 1062: return $dupMsg;
        case 1451: return 'This record is used by other records and cannot be deleted. Cancel or deactivate it instead.';
        case 1452: return 'A selected related record does not exist.';
        case 3819:  // MySQL CHECK violation
        case 4025: return 'A value is outside the allowed range (for example, quantity must be greater than 0).';
        case 1048: return 'A required field was left empty.';
        case 1406: return 'A value is too long for its field.';
    }
    if ($e->getSqlState() === '45000') { return $e->getMessage(); }
    return 'A database error occurred. Please try again or contact the coordinator.';
}
