<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('settings.manage');
$errors = [];
$tzs = ['Africa/Khartoum' => 'الخرطوم', 'Africa/Cairo' => 'القاهرة', 'Asia/Riyadh' => 'الرياض', 'Asia/Dubai' => 'دبي', 'Asia/Kuwait' => 'الكويت', 'Asia/Qatar' => 'الدوحة', 'Asia/Baghdad' => 'بغداد',
        'Asia/Amman' => 'عمّان', 'Asia/Beirut' => 'بيروت', 'Africa/Tripoli' => 'طرابلس', 'Africa/Tunis' => 'تونس', 'Africa/Algiers' => 'الجزائر', 'Africa/Casablanca' => 'الدار البيضاء', 'Europe/Istanbul' => 'إسطنبول', 'UTC' => 'UTC'];
$dfs = ['d/m/Y' => 'يوم/شهر/سنة (31/12/2026)', 'Y-m-d' => 'سنة-شهر-يوم (2026-12-31)', 'd-m-Y' => 'يوم-شهر-سنة (31-12-2026)', 'j/n/Y' => 'يوم/شهر/سنة بدون أصفار (1/3/2026)'];
if (is_post()) {
    csrf_check();
    $d = ['org_name' => post('org_name'), 'owner_name' => post('owner_name'), 'currency' => post('currency'), 'timezone' => post('timezone'), 'date_format' => post('date_format'),
          'contact_phone' => post('contact_phone'), 'contact_email' => post('contact_email'), 'contact_address' => post('contact_address'),
          'alert_rent_days' => post('alert_rent_days'), 'alert_contract_days' => post('alert_contract_days'), 'receipt_prefix' => strtoupper(post('receipt_prefix')), 'contract_prefix' => strtoupper(post('contract_prefix')), 'receipt_footer' => post('receipt_footer'),
          'whatsapp_country_code' => isset($_POST['whatsapp_country_code']) ? post('whatsapp_country_code') : (string)setting('whatsapp_country_code', '249'),
          'maint_overdue_days' => isset($_POST['maint_overdue_days']) ? post('maint_overdue_days') : (string)setting('maint_overdue_days', '7')];
    if (mb_strlen($d['org_name']) < 2) $errors[] = 'اسم المالك أو المؤسسة مطلوب.';
    if ($d['currency'] === '' || mb_strlen($d['currency']) > 12) $errors[] = 'رمز العملة مطلوب (حتى 12 حرفاً).';
    if (!in_array($d['timezone'], timezone_identifiers_list(), true)) $errors[] = 'المنطقة الزمنية غير صحيحة.';
    if (!isset($dfs[$d['date_format']])) $errors[] = 'تنسيق التاريخ غير صحيح.';
    foreach (['alert_rent_days' => 'أيام تنبيه الإيجار', 'alert_contract_days' => 'أيام تنبيه العقود'] as $k => $lab) if (!ctype_digit($d[$k]) || (int)$d[$k] > 365) $errors[] = $lab . ' يجب أن يكون رقماً بين 0 و365.';
    if (!preg_match('/^[1-9][0-9]{0,3}$/', $d['whatsapp_country_code'])) $errors[] = 'رمز دولة واتساب يجب أن يكون أرقاماً فقط (مثل 249).';
    if (!ctype_digit($d['maint_overdue_days']) || (int)$d['maint_overdue_days'] < 1 || (int)$d['maint_overdue_days'] > 365) $errors[] = 'أيام اعتبار بلاغ الصيانة متأخراً: رقم بين 1 و365.';
    if ($d['contact_email'] !== '' && !filter_var($d['contact_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صحيح.';
    foreach (['receipt_prefix', 'contract_prefix'] as $k) if (!preg_match('/^[A-Z0-9]{1,8}$/', $d[$k])) $errors[] = 'بادئة الأرقام: حتى 8 أحرف إنجليزية كبيرة أو أرقام.';
    // الشعار
    $logoFile = $_FILES['logo'] ?? null; $newLogo = null;
    if (!$errors && $logoFile && ($logoFile['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
        [$aid, $uerr] = save_upload($logoFile, 'setting', 1, 'image');
        if ($uerr) $errors[] = 'الشعار: ' . $uerr; else $newLogo = $aid;
    }
    if (!$errors) {
        $old = [];
        foreach ($d as $k => $v) { $old[$k] = (string)setting($k, ''); setting_set($k, $v); }
        if ($newLogo) { if ($prev = (int)setting('logo_attachment_id', 0)) delete_attachment($prev); setting_set('logo_attachment_id', (string)$newLogo); }
        audit('settings.update', 'settings', null, ['changed' => array_keys(array_diff_assoc($d, $old))]);
        flash('success', 'تم حفظ الإعدادات.'); redirect('settings/');
    }
}
$S = fn($k) => e($_POST[$k] ?? setting($k, ''));
$pms = q_all("SELECT * FROM payment_methods ORDER BY sort_order, id"); $cats = q_all("SELECT * FROM expense_categories ORDER BY sort_order, id");
layout_start('إعدادات النظام', 'settings');
echo field_error_box($errors);
?>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?>
  <h2>البيانات العامة</h2>
  <div class="form-grid">
    <div class="field"><label>اسم المؤسسة / المالك <span class="req">*</span></label><input type="text" name="org_name" required maxlength="150" value="<?= $S('org_name') ?>"></div>
    <div class="field"><label>اسم المالك (يظهر في العقود)</label><input type="text" name="owner_name" maxlength="150" value="<?= $S('owner_name') ?>"></div>
    <div class="field"><label>هاتف التواصل</label><input type="tel" name="contact_phone" class="ltr-in" maxlength="40" value="<?= $S('contact_phone') ?>"></div>
    <div class="field"><label>البريد الإلكتروني</label><input type="email" name="contact_email" class="ltr-in" maxlength="150" value="<?= $S('contact_email') ?>"></div>
    <div class="field full"><label>العنوان</label><input type="text" name="contact_address" maxlength="255" value="<?= $S('contact_address') ?>"></div>
    <div class="field"><label>الشعار</label><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><?php if ($lid = (int)setting('logo_attachment_id', 0)): ?><span class="hint">الشعار الحالي: <img src="<?= e(attachment_url($lid)) ?>" alt="" style="height:30px;vertical-align:middle;border-radius:6px"> — ارفع صورة جديدة لاستبداله.</span><?php endif; ?></div>
  </div>
  <h2 style="margin-top:26px">العملة والتواريخ</h2>
  <div class="form-grid">
    <div class="field"><label>رمز العملة <span class="req">*</span></label><input type="text" name="currency" required maxlength="12" value="<?= $S('currency') ?>"><span class="hint">مثال: ج.س، ر.س، د.إ، $</span></div>
    <div class="field"><label>المنطقة الزمنية</label><select name="timezone"><?= opts_assoc($tzs, $_POST['timezone'] ?? setting('timezone')) ?></select></div>
    <div class="field"><label>تنسيق التاريخ</label><select name="date_format"><?= opts_assoc($dfs, $_POST['date_format'] ?? setting('date_format')) ?></select></div>
  </div>
  <h2 style="margin-top:26px">التنبيهات والترقيم</h2>
  <div class="form-grid">
    <div class="field"><label>التنبيه قبل استحقاق الإيجار (أيام)</label><input type="number" name="alert_rent_days" min="0" max="365" value="<?= $S('alert_rent_days') ?>"></div>
    <div class="field"><label>التنبيه قبل انتهاء العقد (أيام)</label><input type="number" name="alert_contract_days" min="0" max="365" value="<?= $S('alert_contract_days') ?>"></div>
    <div class="field"><label>اعتبار بلاغ الصيانة متأخراً بعد (أيام)</label><input type="number" name="maint_overdue_days" min="1" max="365" value="<?= $S('maint_overdue_days') ?>"></div>
    <div class="field"><label>رمز الدولة لواتساب</label><input type="text" name="whatsapp_country_code" class="ltr-in" maxlength="4" inputmode="numeric" value="<?= $S('whatsapp_country_code') ?>"><span class="hint">يُضاف للأرقام التي تبدأ بصفر (السودان 249، السعودية 966، مصر 20).</span></div>
    <div class="field"><label>بادئة رقم الإيصال</label><input type="text" name="receipt_prefix" class="ltr-in" maxlength="8" value="<?= $S('receipt_prefix') ?>"></div>
    <div class="field"><label>بادئة رقم العقد</label><input type="text" name="contract_prefix" class="ltr-in" maxlength="8" value="<?= $S('contract_prefix') ?>"></div>
    <div class="field full"><label>نص ذيل الإيصال</label><textarea name="receipt_footer" maxlength="400"><?= $S('receipt_footer') ?></textarea></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ الإعدادات</button></div>
</form>

<div class="grid g2" id="lists">
  <?php foreach ([['pm', 'وسائل الدفع', $pms, 'payment_methods'], ['cat', 'تصنيفات المصروفات', $cats, 'expense_categories']] as [$k, $title, $rows, $tbl]): ?>
  <div class="card"><h2><?= $title ?></h2>
    <?php foreach ($rows as $r): ?><div class="kv"><span><?= e($r['name']) ?> <?= $r['is_active'] ? '' : '<span class="badge b-gray">معطّل</span>' ?></span>
      <form method="post" action="<?= e(url('settings/lists.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="act" value="<?= $k ?>_toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm"><?= $r['is_active'] ? 'تعطيل' : 'تفعيل' ?></button></form></div><?php endforeach; ?>
    <form method="post" action="<?= e(url('settings/lists.php')) ?>" class="filters" style="border:0;padding:14px 0 0;background:none"><?= csrf_field() ?><input type="hidden" name="act" value="<?= $k ?>_add"><div class="field grow"><input type="text" name="name" required minlength="2" maxlength="80" placeholder="إضافة جديد…"></div><button class="btn btn-sm btn-primary">إضافة</button></form></div>
  <?php endforeach; ?>
</div>

<div class="card"><h2>النسخ الاحتياطي</h2>
  <p>يحفظ النسخ الاحتياطي كل جداول قاعدة البيانات. <b>لا يشمل الملفات المرفوعة</b> (مجلد <span class="ltr">storage/uploads</span>) فانسخه يدوياً مع النسخة.</p>
  <form method="post" action="<?= e(url('settings/backup.php')) ?>" data-no-lock="1" class="inline-form"><?= csrf_field() ?><button class="btn btn-sage" type="submit"><?= icon('download') ?> تنزيل نسخة احتياطية الآن (SQL)</button></form>
  <hr>
  <h3>الاستعادة والجدولة</h3>
  <ol style="line-height:2"><li><b>من phpMyAdmin:</b> أنشئ/اختر قاعدة <span class="ltr">sakan_property_manager</span> ← تبويب «استيراد» ← اختر ملف النسخة ← «تنفيذ».</li>
  <li><b>نسخ آلي:</b> شغّل <span class="ltr">php cron/backup.php</span> يدوياً أو جدوله عبر Windows Task Scheduler (الخطوات في ملف README.md)، وتُحفظ النسخ في مجلد خاص غير قابل للوصول من المتصفح.</li>
  <li>لا تعتمد على النسخ الآلي قبل تجربة استعادته على قاعدة اختبارية.</li></ol>
  <?php if ($last = setting('last_cron_run')): ?><p class="muted">آخر تشغيل للتنبيهات الدورية: <?= fmt_datetime($last) ?></p><?php endif; ?>
</div>
<?php layout_end();
