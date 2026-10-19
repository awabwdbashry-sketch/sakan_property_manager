<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('maintenance.manage');
if (!is_post()) redirect('maintenance/');
csrf_check();
$id = post_int('id'); $act = post('act');
try {
    if ($act === 'status') { maintenance_set_status($id, post('status'), post('note'), post('completed_on'), post('vendor')); flash('success', 'تم تحديث حالة البلاغ.'); }
    elseif ($act === 'note') { maintenance_add_note($id, post('note')); flash('success', 'تمت إضافة الملاحظة.'); }
    elseif ($act === 'upload') {
        if (!q_val("SELECT id FROM maintenance_requests WHERE id = ?", [$id])) throw new DomainException('البلاغ غير موجود.');
        [$ok, $uerr] = save_uploads('files', 'maintenance', $id, 'document');
        if ($uerr) flash('error', implode(' | ', $uerr)); elseif ($ok) flash('success', 'تم رفع المرفقات.'); else flash('warning', 'لم يتم اختيار ملف.');
    } else flash('error', 'عملية غير معروفة.');
} catch (DomainException $e) { flash('error', $e->getMessage()); }
redirect('maintenance/view.php?id=' . $id);
