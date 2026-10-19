<?php
/** إعداد أول مدير. يعمل مرة واحدة فقط ثم يُعطَّل تلقائياً. */
require __DIR__ . '/../includes/bootstrap.php';
if (!db_ready()) db_not_ready_page();
if (!needs_setup()) {
    auth_page_start('الإعداد الأولي مكتمل', 'تم إنشاء المدير الأول مسبقاً، ولا يمكن تكرار هذه العملية.');
    echo '<a class="btn btn-primary" href="' . e(url('auth/login.php')) . '">الانتقال إلى تسجيل الدخول</a>';
    auth_page_end(); exit;
}
$local = in_array(client_ip(), ['127.0.0.1', '::1'], true);
if (!$local && !cfg('allow_remote_setup')) {
    http_response_code(403);
    auth_page_start('الإعداد متاح من الجهاز المحلي فقط', 'لأسباب أمنية افتح هذه الصفحة من الجهاز الذي يعمل عليه الخادم (localhost)، أو فعّل allow_remote_setup مؤقتاً في config/local.php.');
    auth_page_end(); exit;
}

$errors = [];
if (is_post()) {
    csrf_check();
    $name = post('full_name'); $user = post('username'); $org = post('org_name');
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
    if (mb_strlen($name) < 3) $errors[] = 'أدخل الاسم الكامل.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $user)) $errors[] = 'اسم المستخدم: من 3 إلى 40 حرفاً إنجليزياً أو أرقاماً (يسمح بـ _ . -).';
    if ($pw !== $pw2) $errors[] = 'تأكيد كلمة المرور غير مطابق.';
    if ($e = validate_password_strength($pw)) $errors[] = $e;
    if (!$errors) {
        try {
            tx(function () use ($name, $user, $pw, $org) {
                if ((int)q_val("SELECT COUNT(*) FROM users FOR UPDATE") > 0) throw new DomainException('تم إنشاء المدير الأول مسبقاً.');
                q("INSERT INTO users (full_name, username, password_hash, role_id, is_active) VALUES (?,?,?,1,1)", [$name, $user, password_hash($pw, PASSWORD_DEFAULT)]);
                $id = insert_id();
                if ($org !== '') setting_set('org_name', mb_substr($org, 0, 150));
                setting_set('owner_name', $name);
                setting_set('setup_completed', '1');
                $_SESSION['uid'] = $id;
                audit('setup.admin', 'user', $id, ['username' => $user]);
            });
            session_regenerate_id(true);
            $_SESSION['last_seen'] = time();
            unset($GLOBALS['SAKAN_USER']);
            flash('success', 'تم إنشاء حساب المدير بنجاح. مرحباً بك في سَكَن.');
            redirect('dashboard/');
        } catch (DomainException $ex) { $errors[] = $ex->getMessage(); }
    }
}
auth_page_start('الإعداد الأولي', 'أنشئ حساب مدير النظام. لن تظهر هذه الصفحة بعد إنشائه.');
echo field_error_box($errors);
?>
<form method="post">
  <?= csrf_field() ?>
  <div class="field"><label>اسم المالك أو المؤسسة</label><input name="org_name" type="text" value="<?= old('org_name') ?>" placeholder="مثال: شركة الندى العقارية"></div>
  <div class="field"><label>الاسم الكامل <span class="req">*</span></label><input name="full_name" type="text" required value="<?= old('full_name') ?>"></div>
  <div class="field"><label>اسم المستخدم <span class="req">*</span></label><input name="username" class="ltr-in" type="text" required value="<?= old('username') ?>" autocomplete="username"></div>
  <div class="field"><label>كلمة المرور <span class="req">*</span></label><input name="password" type="password" required autocomplete="new-password"><span class="hint">8 أحرف على الأقل، وتحتوي على أحرف وأرقام.</span></div>
  <div class="field"><label>تأكيد كلمة المرور <span class="req">*</span></label><input name="password2" type="password" required autocomplete="new-password"></div>
  <button class="btn btn-primary" type="submit">إنشاء المدير والبدء</button>
</form>
<?php auth_page_end();
