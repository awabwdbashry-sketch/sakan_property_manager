<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
if (!is_post()) redirect('contracts/');
csrf_check();
$id = post_int('id'); $act = post('act');
$c = q_one("SELECT * FROM contracts WHERE id = ?", [$id]);
if (!$c) { flash('error', 'العقد غير موجود.'); redirect('contracts/'); }
$back = 'contracts/view.php?id=' . $id;
try {
    switch ($act) {
        case 'activate':
            require_perm('contracts.manage');
            activate_contract($id);
            flash('success', 'تم تفعيل العقد وتوليد الاستحقاقات.');
            break;
        case 'terminate':
            require_perm('contracts.terminate');
            terminate_contract($id, post('date'), post('reason'));
            flash('success', 'تم إنهاء العقد، وأُلغيت الاستحقاقات غير المدفوعة اللاحقة لتاريخ الإنهاء.');
            break;
        case 'renew':
            require_perm('contracts.manage');
            $newId = renew_contract($id, post('new_end'), post('rent'), post('period'), post('due_day', '1'), post('notes'));
            flash('success', 'تم تجديد العقد بعقد جديد وتفعيله.');
            redirect('contracts/view.php?id=' . $newId);
        case 'deposit':
            require_perm('payments.collect');
            record_deposit($id, post('kind'), post('amount'), post('date') ?: today(), post_int('method_id'), post('notes'));
            flash('success', 'تم تسجيل حركة التأمين.');
            break;
        case 'upload':
            require_perm('contracts.manage');
            [$ok, $uerr] = save_uploads('docs', 'contract', $id, 'document');
            if ($uerr) flash('error', implode(' | ', $uerr)); elseif ($ok) flash('success', 'تم رفع المرفقات.'); else flash('warning', 'لم يتم اختيار ملف.');
            break;
        default:
            flash('error', 'عملية غير معروفة.');
    }
} catch (DomainException $e) {
    flash('error', $e->getMessage());
}
redirect($back);
