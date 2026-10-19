<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('units.manage');
if (!is_post()) redirect('units/');
csrf_check();
$id = post_int('id'); $new = post('status');
$u = q_one("SELECT * FROM units WHERE id = ?", [$id]);
if (!$u) { flash('error', 'الوحدة غير موجودة.'); redirect('units/'); }
if (!in_array($new, ['vacant', 'reserved', 'maintenance', 'unavailable'], true)) { flash('error', 'حالة غير صالحة.'); redirect('units/view.php?id=' . $id); }
tx(function () use ($id, $new, $u) {
    if (q_val("SELECT COUNT(*) FROM contracts WHERE unit_id = ? AND status = 'active'", [$id]) > 0) {
        flash('error', 'الوحدة لديها عقد نشط؛ تُدار حالتها من العقد. أنهِ العقد أولاً.'); return;
    }
    q("UPDATE units SET status = ? WHERE id = ?", [$new, $id]);
    audit('unit.status', 'unit', $id, ['from' => $u['status'], 'to' => $new]);
    flash('success', 'تم تحديث حالة الوحدة إلى «' . label('unit_status', $new) . '».');
});
redirect('units/view.php?id=' . $id);
