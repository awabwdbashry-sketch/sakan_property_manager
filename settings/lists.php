<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('settings.manage');
if (!is_post()) redirect('settings/');
csrf_check();
$act = post('act'); $name = post('name'); $id = post_int('id');
try {
    if ($act === 'pm_add' || $act === 'cat_add') {
        $tbl = $act === 'pm_add' ? 'payment_methods' : 'expense_categories';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) throw new DomainException('الاسم مطلوب (2–80 حرفاً).');
        if (q_val("SELECT id FROM $tbl WHERE name = ?", [$name])) throw new DomainException('الاسم موجود مسبقاً.');
        q("INSERT INTO $tbl (name, sort_order) VALUES (?, 50)", [$name]);
        audit('settings.update', 'settings', null, [$tbl => 'add:' . $name]); flash('success', 'تمت الإضافة.');
    } elseif ($act === 'pm_toggle' || $act === 'cat_toggle') {
        $tbl = $act === 'pm_toggle' ? 'payment_methods' : 'expense_categories';
        $row = q_one("SELECT * FROM $tbl WHERE id = ?", [$id]);
        if (!$row) throw new DomainException('العنصر غير موجود.');
        if ($row['is_active'] && (int)q_val("SELECT COUNT(*) FROM $tbl WHERE is_active = 1") <= 1) throw new DomainException('يجب أن يبقى عنصر فعّال واحد على الأقل.');
        q("UPDATE $tbl SET is_active = 1 - is_active WHERE id = ?", [$id]);
        audit('settings.update', 'settings', null, [$tbl => 'toggle:' . $row['name']]); flash('success', 'تم التحديث.');
    }
} catch (DomainException $e) { flash('error', $e->getMessage()); }
redirect('settings/#lists');
