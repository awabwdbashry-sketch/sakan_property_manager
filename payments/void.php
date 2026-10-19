<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('payments.void');
if (!is_post()) redirect('payments/');
csrf_check();
$iid = post_int('installment_id');
try { void_payment(post_int('payment_id'), post('reason')); notify_payment_event(post_int('payment_id'), true); flash('success', 'تم إلغاء الدفعة وإعادة احتساب الاستحقاق.'); }
catch (DomainException $e) { flash('error', $e->getMessage()); }
redirect('payments/view.php?id=' . $iid);
