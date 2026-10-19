<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('users.manage');
if (!is_post()) redirect('users/');
csrf_check();
$id = post_int('id'); $act = post('act');
$u = q_one("SELECT * FROM users WHERE id = ?", [$id]);
if (!$u) { flash('error', 'المستخدم غير موجود.'); redirect('users/'); }
if ($act === 'disable') {
    if ($id === user_id()) flash('error', 'لا يمكنك تعطيل حسابك الحالي.');
    elseif (active_admin_count($id) === 0 && q_val("SELECT 1 FROM role_permissions WHERE role_id = ? AND perm_code = 'users.manage'", [$u['role_id']])) flash('error', 'لا يمكن تعطيل آخر مدير فعّال.');
    else { q("UPDATE users SET is_active = 0 WHERE id = ?", [$id]); audit('user.status', 'user', $id, ['active' => false]); flash('success', 'تم تعطيل المستخدم، وتبقى سجلاته محفوظة.'); }
} elseif ($act === 'enable') {
    q("UPDATE users SET is_active = 1 WHERE id = ?", [$id]); audit('user.status', 'user', $id, ['active' => true]); flash('success', 'تم تفعيل المستخدم.');
} elseif ($act === 'password') {
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if ($p1 !== $p2) flash('error', 'تأكيد كلمة المرور غير مطابق.');
    elseif ($e = validate_password_strength($p1)) flash('error', $e);
    else { q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($p1, PASSWORD_DEFAULT), $id]); audit('user.password', 'user', $id, []); flash('success', 'تم تغيير كلمة المرور.'); }
    redirect('users/form.php?id=' . $id);
}
redirect('users/');
