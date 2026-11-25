<?php
/**
 * Business logic for Phase 2: each workflow runs inside a mysqli transaction.
 * (Phase 3 moves record/distribute into stored procedures and the status rules into triggers.)
 */
class BusinessRuleError extends RuntimeException {}

function today(): string { return date('Y-m-d'); }

/** Validate donation form input (BR-01, BR-05) and active donor/category (BR-02). Returns list of errors. */
function validate_donation(array $in): array {
    $err = [];
    if ($in['donor_id'] <= 0)    $err[] = 'Choose a donor.';
    if ($in['category_id'] <= 0) $err[] = 'Choose a category.';
    if ($in['quantity'] === '' || !is_numeric($in['quantity']) || (float)$in['quantity'] <= 0) {
        $err[] = 'Quantity must be greater than 0';
    } elseif (!valid_qty($in['quantity'])) {
        $err[] = 'Quantity must be a number with at most 2 decimals.';
    }
    if (!valid_date($in['donation_date'])) $err[] = 'Enter a valid donation date.';
    elseif ($in['donation_date'] > today())  $err[] = 'Donation date cannot be in the future.';        // BR-05
    if (mb_strlen($in['notes']) > 500) $err[] = 'Notes must be 500 characters or fewer.';
    if (!$err) {
        $d = q_one('SELECT is_active FROM donors WHERE donor_id = ?', 'i', [$in['donor_id']]);
        $c = q_one('SELECT is_active FROM donation_categories WHERE category_id = ?', 'i', [$in['category_id']]);
        if (!$d || !$c) $err[] = 'The selected donor or category does not exist.';
        elseif (!(int)$d['is_active'] || !(int)$c['is_active']) $err[] = 'Donor/Category is inactive';    // BR-02
    }
    return $err;
}

function with_transaction(callable $fn) {
    $db = db();
    $db->begin_transaction();
    try {
        $res = $fn($db);
        $db->commit();
        return $res;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function record_donation(array $in, int $userId): int {
    return with_transaction(function () use ($in, $userId) {
        $notes = $in['notes'] === '' ? null : $in['notes'];
        [, $id] = q_exec(
            "INSERT INTO donations (donor_id, category_id, recorded_by, donation_date, quantity, status, notes) VALUES (?,?,?,?,?, 'RECORDED', ?)",
            'iiisds', [$in['donor_id'], $in['category_id'], $userId, $in['donation_date'], $in['quantity'], $notes]);
        return $id;
    });
}

function lock_donation(int $id): array {
    $d = q_one('SELECT donation_id, status, donation_date, quantity, recorded_by FROM donations WHERE donation_id = ? FOR UPDATE', 'i', [$id]);
    if (!$d) throw new BusinessRuleError('Donation not found.');
    return $d;
}

function change_status(int $id, string $to, ?string $reason = null): void {
    with_transaction(function () use ($id, $to, $reason) {
        $d = lock_donation($id);
        if (!transition_allowed($d['status'], $to)) {                                              // BR-03
            throw new BusinessRuleError("Invalid status change: {$d['status']} cannot become $to.");
        }
        q_exec('UPDATE donations SET status = ?, cancel_reason = ? WHERE donation_id = ?', 'ssi', [$to, $reason, $id]);
    });
}

function remaining_of(int $donationId): string {
    $r = q_one('SELECT d.quantity - COALESCE(SUM(x.quantity_released), 0) AS remaining
                FROM donations d LEFT JOIN distributions x ON x.donation_id = d.donation_id
                WHERE d.donation_id = ? GROUP BY d.donation_id, d.quantity', 'i', [$donationId]);
    return $r['remaining'] ?? '0';
}

function distribute(int $id, array $in, int $userId): string {
    return with_transaction(function () use ($id, $in, $userId) {
        $d = lock_donation($id);                                    // SELECT ... FOR UPDATE: blocks concurrent releases
        if (!in_array($d['status'], ['VERIFIED', 'PARTIAL'], true)) {                              // BR-04
            throw new BusinessRuleError('Only VERIFIED or PARTIAL donations can be distributed.');
        }
        $errs = [];
        if ($in['recipient_name'] === '' || mb_strlen($in['recipient_name']) > 120) $errs[] = 'Recipient name is required (max 120 characters).';
        if ($in['recipient_area'] === '' || mb_strlen($in['recipient_area']) > 120) $errs[] = 'Recipient area is required (max 120 characters).';
        if ($in['quantity'] === '' || !is_numeric($in['quantity']) || (float)$in['quantity'] <= 0) $errs[] = 'Quantity must be greater than 0';
        elseif (!valid_qty($in['quantity'])) $errs[] = 'Quantity must be a number with at most 2 decimals.';
        if (!valid_date($in['distribution_date'])) $errs[] = 'Enter a valid distribution date.';
        elseif ($in['distribution_date'] < $d['donation_date']) $errs[] = 'Distribution date cannot be earlier than the donation date (' . $d['donation_date'] . ').'; // BR-05
        if (mb_strlen($in['remarks']) > 255) $errs[] = 'Remarks must be 255 characters or fewer.';
        if ($errs) throw new BusinessRuleError(implode(' ', $errs));

        $remainingCents = (int)round((float)remaining_of($id) * 100);
        $qtyCents       = (int)round((float)$in['quantity'] * 100);
        if ($qtyCents > $remainingCents) {                                                         // BR-04
            throw new BusinessRuleError('Quantity exceeds the remaining amount (' . fmt_qty($remainingCents / 100) . ' left). Nothing was saved.');
        }
        $remarks = $in['remarks'] === '' ? null : $in['remarks'];
        q_exec('INSERT INTO distributions (donation_id, released_by, recipient_name, recipient_area, quantity_released, distribution_date, remarks)
                VALUES (?,?,?,?,?,?,?)', 'iissdss',
            [$id, $userId, $in['recipient_name'], $in['recipient_area'], $in['quantity'], $in['distribution_date'], $remarks]);

        $newStatus = ($remainingCents - $qtyCents) === 0 ? 'DISTRIBUTED' : 'PARTIAL';
        if ($newStatus !== $d['status']) {
            if (!transition_allowed($d['status'], $newStatus)) throw new BusinessRuleError('Invalid status change.');
            q_exec('UPDATE donations SET status = ? WHERE donation_id = ?', 'si', [$newStatus, $id]);
        }
        return $newStatus;
    });
}
