<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('users.manage');
$users = q_all("SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.is_active DESC, u.full_name");
layout_start('المستخدمون والصلاحيات', 'users');
page_actions('<a class="btn btn-primary" href="' . e(url('users/form.php')) . '">' . icon('plus') . ' إضافة مستخدم</a> <a class="btn" href="' . e(url('users/roles.php')) . '">' . icon('shield') . ' الأدوار والصلاحيات</a>');
?>
<div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>الدور</th><th>آخر دخول</th><th>الحالة</th><th></th></tr></thead><tbody>
<?php foreach ($users as $u): ?><tr><td><b><?= e($u['full_name']) ?></b><?= (int)$u['id'] === user_id() ? ' <span class="badge b-sage">أنت</span>' : '' ?></td><td class="ltr"><?= e($u['username']) ?></td><td><?= e($u['role_name']) ?></td><td><?= fmt_datetime($u['last_login_at']) ?></td>
<td><?= $u['is_active'] ? '<span class="badge b-sage">فعّال</span>' : '<span class="badge b-gray">معطّل</span>' ?></td>
<td class="acts"><a class="btn btn-sm" href="<?= e(url('users/form.php?id=' . $u['id'])) ?>">تعديل</a>
<?php if ((int)$u['id'] !== user_id()): ?><?= confirm_form('users/action.php', ['id' => $u['id'], 'act' => $u['is_active'] ? 'disable' : 'enable'], $u['is_active'] ? 'تعطيل' : 'تفعيل', $u['is_active'] ? 'سيُمنع هذا المستخدم من تسجيل الدخول، وتبقى سجلاته محفوظة. هل تريد المتابعة؟' : 'تفعيل هذا المستخدم؟', $u['is_active'] ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-sage') ?><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php layout_end();
