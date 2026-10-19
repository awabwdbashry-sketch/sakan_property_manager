<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('units.manage');
if (!is_post()) redirect('units/');
csrf_check();
$id = post_int('id'); $act = post('act');
$u = q_one("SELECT * FROM units WHERE id = ?", [$id]);
if (!$u) { flash('error', 'الوحدة غير موجودة.'); redirect('units/'); }
if ($act === 'archive') {
    if (q_val("SELECT COUNT(*) FROM contracts WHERE unit_id = ? AND status = 'active'", [$id]) > 0) {
        flash('error', 'لا يمكن أرشفة وحدة لديها عقد نشط.'); redirect('units/view.php?id=' . $id);
    }
    q("UPDATE units SET is_archived = 1 WHERE id = ?", [$id]);
    audit('unit.archive', 'unit', $id, ['unit_no' => $u['unit_no']]);
    flash('success', 'تمت أرشفة الوحدة. سجلاتها التاريخية محفوظة.');
    redirect('properties/view.php?id=' . $u['property_id']);
}
if ($act === 'restore') { q("UPDATE units SET is_archived = 0 WHERE id = ?", [$id]); flash('success', 'تمت استعادة الوحدة.'); }
redirect('units/view.php?id=' . $id);
