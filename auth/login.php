<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!db_ready()) db_not_ready_page();
if (needs_setup()) redirect('setup/');
if (current_user()) redirect('dashboard/');

$error = null;
if (is_post()) {
    csrf_check();
    $error = attempt_login(post('username'), (string)($_POST['password'] ?? ''));
    if (!$error) {
        $to = $_SESSION['_after_login'] ?? '';
        unset($_SESSION['_after_login']);
        // السماح بالتحويل لمسارات داخل التطبيق فقط
        if ($to !== '' && strpos($to, base_url() . '/') === 0 && strpos($to, '//') !== 0 && strpos($to, '/auth/') === false) redirect($to);
        redirect('dashboard/');
    }
}
auth_page_start('تسجيل الدخول', 'مرحباً بك، سجّل الدخول لإدارة عقاراتك');
if ($error) echo '<div class="alert alert-error" role="alert">' . e($error) . '</div>';
?>
<form method="post" autocomplete="on">
  <?= csrf_field() ?>
  <div class="field"><label for="u">اسم المستخدم</label><input id="u" name="username" type="text" required autofocus autocomplete="username" value="<?= old('username') ?>"></div>
  <div class="field"><label for="p">كلمة المرور</label><input id="p" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary" type="submit">دخول</button>
</form>
<?php auth_page_end();
