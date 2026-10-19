<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('payments.collect');
if (!is_post()) redirect('payments/');
csrf_check();
$iid = post_int('installment_id');
try {
    [$pid, $rid, $dup] = record_payment($iid, post('amount'), post('paid_on'), post_int('method_id'), post('reference_no'), post('notes'), post('_idem'));
    if (!$dup) notify_payment_event($pid);
    flash($dup ? 'info' : 'success', $dup ? 'هذه الدفعة سُجّلت مسبقاً، ولم يتم تكرارها.' : 'تم تسجيل الدفعة وإصدار الإيصال بنجاح.');
    redirect('payments/receipt.php?id=' . $pid);
} catch (DomainException $e) {
    flash('error', $e->getMessage());
    redirect('payments/view.php?id=' . $iid . '#collect');
}
