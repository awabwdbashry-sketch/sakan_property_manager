<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('properties.manage');
if (!is_post()) redirect('properties/');
csrf_check();
$id = post_int('id'); $act = post('act');
$p = q_one("SELECT * FROM properties WHERE id = ?", [$id]);
if (!$p) { flash('error', 'العقار غير موجود.'); redirect('properties/'); }
if ($act === 'archive') {
    $n = (int)q_val("SELECT COUNT(*) FROM contracts WHERE property_id = ? AND status = 'active'", [$id]);
    if ($n > 0) { flash('error', 'لا يمكن أرشفة عقار لديه ' . $n . ' عقد نشط. أنهِ العقود أو انتظر انتهاءها أولاً.'); redirect('properties/view.php?id=' . $id); }
    q("UPDATE properties SET status = 'archived' WHERE id = ?", [$id]);
    audit('property.archive', 'property', $id, ['name' => $p['name']]);
    flash('success', 'تمت أرشفة العقار. سجلاته المالية والتاريخية محفوظة.');
} elseif ($act === 'restore') {
    q("UPDATE properties SET status = 'active' WHERE id = ?", [$id]);
    audit('property.restore', 'property', $id, ['name' => $p['name']]);
    flash('success', 'تمت استعادة العقار.');
}
redirect('properties/view.php?id=' . $id);
