<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('tenants.manage');
$id = get_int('id') ?: post_int('id');
$t = $id ? q_one("SELECT * FROM tenants WHERE id = ?", [$id]) : null;
if ($id && !$t) render_error_page('غير موجود', 'المستأجر المطلوب غير موجود.', 404);
$sens = can('tenants.sensitive');
$errors = [];
if (is_post()) {
    csrf_check();
    $d = ['full_name' => post('full_name'), 'phone' => post('phone'), 'email' => post('email'), 'national_id' => $sens ? post('national_id') : ($t['national_id'] ?? ''),
          'address' => post('address'), 'alt_contact_name' => post('alt_contact_name'), 'alt_contact_phone' => post('alt_contact_phone'),
          'status' => post('status', 'active'), 'notes' => post('notes'), 'registered_on' => post('registered_on') ?: today()];
    if (mb_strlen($d['full_name']) < 3) $errors[] = 'الاسم الكامل مطلوب.';
    if (!preg_match('/^[0-9+\-\s()]{5,40}$/', $d['phone'])) $errors[] = 'رقم الهاتف مطلوب وبصيغة صحيحة.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صحيح.';
    if ($d['alt_contact_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,40}$/', $d['alt_contact_phone'])) $errors[] = 'هاتف جهة الاتصال البديلة غير صحيح.';
    if (!in_array($d['status'], ['active', 'inactive'], true)) $d['status'] = 'active';
    if (!valid_date($d['registered_on'])) $errors[] = 'تاريخ التسجيل غير صحيح.';
    if ($t && $d['status'] === 'inactive' && q_val("SELECT COUNT(*) FROM contracts WHERE tenant_id = ? AND status = 'active'", [$id]) > 0) $errors[] = 'لا يمكن جعل مستأجر لديه عقد نشط «غير نشط».';
    if (!$errors) {
        $vals = [$d['full_name'], $d['phone'], $d['email'] ?: null, $d['national_id'] ?: null, $d['address'] ?: null, $d['alt_contact_name'] ?: null, $d['alt_contact_phone'] ?: null, $d['status'], $d['notes'] ?: null, $d['registered_on']];
        if ($t) {
            q("UPDATE tenants SET full_name=?, phone=?, email=?, national_id=?, address=?, alt_contact_name=?, alt_contact_phone=?, status=?, notes=?, registered_on=? WHERE id=?", [...$vals, $id]);
            audit('tenant.update', 'tenant', $id, ['fields' => array_keys(array_diff_assoc(array_map('strval', array_intersect_key($t, $d)), array_map('strval', $d)))]);
            $tid = $id;
        } else {
            q("INSERT INTO tenants (full_name, phone, email, national_id, address, alt_contact_name, alt_contact_phone, status, notes, registered_on, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)", [...$vals, user_id()]);
            $tid = insert_id();
            audit('tenant.create', 'tenant', $tid, ['name' => $d['full_name']]);
        }
        if ($sens) { [$ok, $uerr] = save_uploads('docs', 'tenant', $tid, 'document'); if ($uerr) flash('warning', 'بعض المرفقات لم تُرفع: ' . implode(' | ', $uerr)); }
        flash('success', $t ? 'تم تحديث بيانات المستأجر.' : 'تمت إضافة المستأجر بنجاح.');
        redirect('tenants/view.php?id=' . $tid);
    }
    $t = array_merge($t ?? [], $d);
}
$v = fn($k) => e($t[$k] ?? '');
layout_start($id ? 'تعديل مستأجر' : 'إضافة مستأجر', 'tenants');
echo field_error_box($errors);
?>
<div class="alert alert-info">المستأجر هنا سجل إداري داخلي فقط؛ لا يُنشأ له حساب أو كلمة مرور.</div>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="form-grid">
    <div class="field"><label>الاسم الكامل <span class="req">*</span></label><input type="text" name="full_name" required maxlength="150" value="<?= $v('full_name') ?>"></div>
    <div class="field"><label>رقم الهاتف <span class="req">*</span></label><input type="tel" name="phone" class="ltr-in" required maxlength="40" value="<?= $v('phone') ?>"></div>
    <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" class="ltr-in" maxlength="150" value="<?= $v('email') ?>"></div>
    <?php if ($sens): ?><div class="field"><label>رقم الهوية</label><input type="text" name="national_id" class="ltr-in" maxlength="50" value="<?= $v('national_id') ?>"></div><?php endif; ?>
    <div class="field"><label>تاريخ التسجيل</label><input type="date" name="registered_on" value="<?= e($t['registered_on'] ?? today()) ?>"></div>
    <div class="field"><label>الحالة</label><select name="status"><?= opts_assoc(labels('tenant_status'), $t['status'] ?? 'active') ?></select></div>
    <div class="field full"><label>العنوان</label><input type="text" name="address" maxlength="255" value="<?= $v('address') ?>"></div>
    <div class="field"><label>جهة اتصال بديلة (الاسم)</label><input type="text" name="alt_contact_name" maxlength="120" value="<?= $v('alt_contact_name') ?>"></div>
    <div class="field"><label>هاتف جهة الاتصال البديلة</label><input type="tel" name="alt_contact_phone" class="ltr-in" maxlength="40" value="<?= $v('alt_contact_phone') ?>"></div>
    <div class="field full"><label>ملاحظات داخلية</label><textarea name="notes"><?= $v('notes') ?></textarea></div>
    <?php if ($sens): ?><div class="field full"><label>مرفقات المستأجر (هوية، مستندات)</label><input type="file" name="docs[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"><span class="hint">PDF أو صور — لا تُعرض إلا لمن يملك صلاحية البيانات الحساسة.</span></div><?php endif; ?>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button><a class="btn" href="<?= e(url($id ? 'tenants/view.php?id=' . $id : 'tenants/')) ?>">إلغاء</a></div>
</form>
<?php if ($id && $sens && ($docs = attachments_for('tenant', $id))): ?>
<div class="card"><h3>المرفقات الحالية</h3><?= doc_list($docs, true, 'tenants/attachment_delete.php', ['tenant_id' => $id]) ?></div>
<?php endif;
layout_end();
