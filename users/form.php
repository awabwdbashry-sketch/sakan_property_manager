<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('users.manage');
$id = get_int('id') ?: post_int('id');
$u = $id ? q_one("SELECT * FROM users WHERE id = ?", [$id]) : null;
if ($id && !$u) render_error_page('غير موجود', 'المستخدم المطلوب غير موجود.', 404);
$errors = [];
if (is_post()) {
    csrf_check();
    $d = ['full_name' => post('full_name'), 'username' => post('username'), 'email' => post('email'), 'role_id' => post_int('role_id')];
    $pw = (string)($_POST['password'] ?? '');
    if (mb_strlen($d['full_name']) < 3) $errors[] = 'الاسم الكامل مطلوب.';
    if (!$u) {
        if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $d['username'])) $errors[] = 'اسم المستخدم: 3–40 حرفاً إنجليزياً أو أرقاماً (يسمح بـ _ . -).';
        elseif (q_val("SELECT id FROM users WHERE username = ?", [$d['username']])) $errors[] = 'اسم المستخدم مستخدم مسبقاً.';
        if ($e = validate_password_strength($pw)) $errors[] = $e;
    }
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صحيح.';
    if (!q_val("SELECT id FROM roles WHERE id = ?", [$d['role_id']])) $errors[] = 'اختر الدور.';
    if ($u && !$errors) { // حماية من فقدان آخر مدير
        $hasManage = (bool)q_val("SELECT 1 FROM role_permissions WHERE role_id = ? AND perm_code = 'users.manage'", [$d['role_id']]);
        if (!$hasManage && $u['is_active'] && active_admin_count((int)$id) === 0) $errors[] = 'لا يمكن تغيير دور آخر مدير فعّال؛ يجب أن يبقى مدير واحد على الأقل.';
    }
    if (!$errors) {
        if ($u) {
            q("UPDATE users SET full_name = ?, email = ?, role_id = ? WHERE id = ?", [$d['full_name'], $d['email'] ?: null, $d['role_id'], $id]);
            audit('user.update', 'user', $id, ['role_id' => ['from' => $u['role_id'], 'to' => $d['role_id']]]);
            if ((int)$id === user_id()) unset($GLOBALS['SAKAN_USER']);
            flash('success', 'تم تحديث بيانات المستخدم.');
        } else {
            q("INSERT INTO users (full_name, username, email, password_hash, role_id) VALUES (?,?,?,?,?)", [$d['full_name'], $d['username'], $d['email'] ?: null, password_hash($pw, PASSWORD_DEFAULT), $d['role_id']]);
            audit('user.create', 'user', insert_id(), ['username' => $d['username'], 'role_id' => $d['role_id']]);
            flash('success', 'تمت إضافة المستخدم.');
        }
        redirect('users/');
    }
    $u = array_merge($u ?? [], $d);
}
$roles = q_all("SELECT id, name FROM roles ORDER BY id");
$v = fn($k) => e($u[$k] ?? '');
layout_start($id ? 'تعديل مستخدم' : 'إضافة مستخدم', 'users');
echo field_error_box($errors);
?>
<form method="post" class="card" style="max-width:760px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
<div class="form-grid">
  <div class="field"><label>الاسم الكامل <span class="req">*</span></label><input type="text" name="full_name" required maxlength="150" value="<?= $v('full_name') ?>"></div>
  <div class="field"><label>اسم المستخدم <span class="req">*</span></label><input type="text" name="username" class="ltr-in" maxlength="40" <?= $id ? 'readonly' : 'required' ?> value="<?= $v('username') ?>" autocomplete="off"></div>
  <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" class="ltr-in" maxlength="150" value="<?= $v('email') ?>"></div>
  <div class="field"><label>الدور <span class="req">*</span></label><select name="role_id" required><?= opts($roles, 'id', 'name', $u['role_id'] ?? '', 'اختر الدور') ?></select></div>
  <?php if (!$id): ?><div class="field full"><label>كلمة المرور <span class="req">*</span></label><input type="password" name="password" required autocomplete="new-password"><span class="hint">8 أحرف على الأقل وتحتوي على أحرف وأرقام.</span></div><?php endif; ?>
</div>
<?php if ($id): ?><p class="muted" style="margin-top:14px">لإعادة تعيين كلمة المرور استخدم النموذج أدناه.</p><?php endif; ?>
<div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button><a class="btn" href="<?= e(url('users/')) ?>">إلغاء</a></div></form>
<?php if ($id): ?><form method="post" action="<?= e(url('users/action.php')) ?>" class="card" style="max-width:760px" data-confirm="سيتم تغيير كلمة مرور هذا المستخدم فوراً. هل تريد المتابعة؟"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="password">
  <h3>إعادة تعيين كلمة المرور</h3><div class="form-grid"><div class="field"><label>كلمة المرور الجديدة</label><input type="password" name="password" required autocomplete="new-password"></div><div class="field"><label>تأكيد كلمة المرور</label><input type="password" name="password2" required autocomplete="new-password"></div></div>
  <div class="form-actions"><button class="btn btn-danger" type="submit">تغيير كلمة المرور</button></div></form><?php endif;
layout_end();
