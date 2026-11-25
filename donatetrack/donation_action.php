<?php
// Handles Verify, Cancel, Distribute and Delete for one donation. POST only.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/donation_logic.php';
$u = require_role(['COORDINATOR', 'VOLUNTEER']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('donations.php'); }
csrf_check();

$id = (int)post('donation_id');
$action = post('action');
$back = 'donation_view.php?id=' . $id;

try {
    switch ($action) {
        case 'verify':
            if (!is_coord()) deny();                                   // BR-08
            change_status($id, 'VERIFIED');
            flash('success', "Donation #$id verified.");
            break;

        case 'cancel':
            if (!is_coord()) deny();
            $reason = post('cancel_reason');
            if ($reason === '') throw new BusinessRuleError('Enter a reason for cancelling.');
            if (mb_strlen($reason) > 255) throw new BusinessRuleError('Reason must be 255 characters or fewer.');
            change_status($id, 'CANCELLED', $reason);
            flash('success', "Donation #$id cancelled.");
            break;

        case 'distribute':
            if (!is_coord()) deny();
            $new = distribute($id, [
                'recipient_name' => post('recipient_name'), 'recipient_area' => post('recipient_area'),
                'quantity' => str_replace(',', '', post('quantity')), 'distribution_date' => post('distribution_date'),
                'remarks' => post('remarks')], $u['user_id']);
            flash('success', 'Distribution saved. Donation status is now ' . $new . '.');
            break;

        case 'delete':
            $d = q_one('SELECT status, recorded_by FROM donations WHERE donation_id = ?', 'i', [$id]);
            if (!$d) throw new BusinessRuleError('Donation not found.');
            if (!is_coord() && (int)$d['recorded_by'] !== $u['user_id']) deny();
            if ($d['status'] !== 'RECORDED') throw new BusinessRuleError('Only RECORDED donations can be deleted. Cancel this donation instead.');
            with_transaction(function () use ($id) { q_exec('DELETE FROM donations WHERE donation_id = ?', 'i', [$id]); });
            flash('success', "Donation #$id deleted.");
            redirect('donations.php');

        default:
            throw new BusinessRuleError('Unknown action.');
    }
} catch (BusinessRuleError $e) {
    flash('danger', $e->getMessage());
} catch (mysqli_sql_exception $e) {
    flash('danger', db_message($e));
}
redirect($back);
