<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
$errors = [];
if (is_post()) {
    csrf_check();
    $cur = (string)($_POST['current'] ?? ''); $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    $hash = q_val("SELECT password_hash FROM users WHERE id = ?", [$me['id']]);
    if (!password_verify($cur, $hash)) $errors[] = 'كلمة المرور الحالية غير صحيحة.';
    if ($p1 !== $p2) $errors[] = 'تأكيد كلمة المرور غير مطابق.';
    if ($e = validate_password_strength($p1)) $errors[] = $e;
    if (!$errors) {
        q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($p1, PASSWORD_DEFAULT), $me['id']]);
        audit('user.password', 'user', (int)$me['id'], ['self' => true]);
        session_regenerate_id(true);
        flash('success', 'تم تغيير كلمة المرور بنجاح.'); redirect('users/profile.php');
    }
}
layout_start('حسابي', '');
echo field_error_box($errors);
?>
<div class="card" style="max-width:640px"><h2><?= e($me['full_name']) ?></h2><div class="kv"><span>اسم المستخدم</span><b class="ltr"><?= e($me['username']) ?></b></div><div class="kv"><span>الدور</span><b><?= e($me['role_name']) ?></b></div><div class="kv"><span>آخر تسجيل دخول</span><b><?= fmt_datetime($me['last_login_at']) ?></b></div></div>
<form method="post" class="card" style="max-width:640px"><?= csrf_field() ?><h3>تغيير كلمة المرور</h3>
  <div class="form-grid"><div class="field full"><label>كلمة المرور الحالية</label><input type="password" name="current" required autocomplete="current-password"></div>
  <div class="field"><label>الجديدة</label><input type="password" name="password" required autocomplete="new-password"></div><div class="field"><label>تأكيد الجديدة</label><input type="password" name="password2" required autocomplete="new-password"></div></div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">تغيير</button></div></form>
<?php layout_end();
