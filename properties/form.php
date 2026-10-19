<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('properties.manage');

$id = get_int('id') ?: post_int('id');
$p = $id ? q_one("SELECT * FROM properties WHERE id = ?", [$id]) : null;
if ($id && !$p) render_error_page('غير موجود', 'العقار المطلوب غير موجود.', 404);
$errors = [];

if (is_post()) {
    csrf_check();
    $d = [
        'code' => strtoupper(post('code')), 'name' => post('name'), 'address' => post('address'), 'city' => post('city'),
        'district' => post('district'), 'description' => post('description'), 'floors' => post('floors'),
        'manager_name' => post('manager_name'), 'contact_phone' => post('contact_phone'),
    ];
    if (mb_strlen($d['name']) < 2) $errors[] = 'اسم العقار مطلوب.';
    if ($d['code'] === '') { $d['code'] = 'P-' . str_pad((string)(next_counter('property')), 3, '0', STR_PAD_LEFT); }
    if (!preg_match('/^[A-Z0-9\x{0600}-\x{06FF}_.-]{2,30}$/u', $d['code'])) $errors[] = 'الرمز الداخلي: من 2 إلى 30 حرفاً (حروف وأرقام و - _ .).';
    elseif (q_val("SELECT id FROM properties WHERE code = ? AND id <> ?", [$d['code'], $id])) $errors[] = 'الرمز الداخلي مستخدم لعقار آخر.';
    if ($d['floors'] !== '' && (!ctype_digit($d['floors']) || (int)$d['floors'] > 300)) $errors[] = 'عدد الطوابق غير صحيح.';
    if ($d['contact_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,40}$/', $d['contact_phone'])) $errors[] = 'رقم التواصل غير صحيح.';
    if (!$errors) {
        $vals = [$d['code'], $d['name'], $d['address'] ?: null, $d['city'] ?: null, $d['district'] ?: null, $d['description'] ?: null,
                 $d['floors'] === '' ? null : (int)$d['floors'], $d['manager_name'] ?: null, $d['contact_phone'] ?: null];
        if ($p) {
            q("UPDATE properties SET code=?, name=?, address=?, city=?, district=?, description=?, floors=?, manager_name=?, contact_phone=? WHERE id=?", [...$vals, $id]);
            audit('property.update', 'property', $id, ['old' => array_intersect_key($p, $d), 'new' => $d]);
            $pid = $id;
        } else {
            q("INSERT INTO properties (code, name, address, city, district, description, floors, manager_name, contact_phone, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)", [...$vals, user_id()]);
            $pid = insert_id();
            audit('property.create', 'property', $pid, $d);
        }
        [$ok, $uerr] = save_uploads('images', 'property', $pid, 'image');
        if ($uerr) flash('warning', 'تم الحفظ، لكن بعض الصور لم تُرفع: ' . implode(' | ', $uerr));
        flash('success', $p ? 'تم تحديث بيانات العقار.' : 'تمت إضافة العقار بنجاح. يمكنك الآن إضافة وحداته.');
        redirect('properties/view.php?id=' . $pid);
    }
    $p = array_merge($p ?? [], $d);
}
$v = fn($k) => e($p[$k] ?? '');
layout_start($id ? 'تعديل عقار' : 'إضافة عقار', 'properties');
echo field_error_box($errors);
?>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="form-grid">
    <div class="field"><label>اسم العقار <span class="req">*</span></label><input type="text" name="name" required maxlength="150" value="<?= $v('name') ?>"></div>
    <div class="field"><label>الرمز الداخلي</label><input type="text" name="code" class="ltr-in" maxlength="30" value="<?= $v('code') ?>" placeholder="يُولَّد تلقائياً إن تُرك فارغاً"><span class="hint">رمز فريد مثل P-001</span></div>
    <div class="field"><label>المدينة</label><input type="text" name="city" maxlength="80" value="<?= $v('city') ?>"></div>
    <div class="field"><label>المنطقة / الحي</label><input type="text" name="district" maxlength="80" value="<?= $v('district') ?>"></div>
    <div class="field full"><label>العنوان</label><input type="text" name="address" maxlength="255" value="<?= $v('address') ?>"></div>
    <div class="field"><label>عدد الطوابق</label><input type="number" name="floors" min="0" max="300" value="<?= $v('floors') ?>"></div>
    <div class="field"><label>المسؤول عن العقار</label><input type="text" name="manager_name" maxlength="120" value="<?= $v('manager_name') ?>"></div>
    <div class="field"><label>رقم التواصل</label><input type="tel" name="contact_phone" class="ltr-in" maxlength="40" value="<?= $v('contact_phone') ?>"></div>
    <div class="field full"><label>وصف العقار</label><textarea name="description"><?= $v('description') ?></textarea></div>
    <div class="field full"><label>صور العقار</label><input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"><span class="hint">JPG أو PNG أو WEBP — حتى <?= (int)cfg('max_upload_mb', 5) ?> ميجابايت للصورة. الصورة الأولى تُستخدم كغلاف.</span></div>
  </div>
<?php if ($p && $id): $imgs = attachments_for('property', $id, 'image'); if ($imgs): ?>
  <h3 style="margin-top:22px">الصور الحالية</h3>
  <?php /* حذف الصور عبر نموذج منفصل لتفادي تداخل النماذج */ ?>
<?php endif; endif; ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button><a class="btn" href="<?= e(url($id ? 'properties/view.php?id=' . $id : 'properties/')) ?>">إلغاء</a></div>
</form>
<?php if ($id && !empty($imgs)): ?>
<div class="card"><h3>إدارة الصور الحالية</h3><?= image_gallery($imgs, true, 'properties/image_delete.php', ['property_id' => $id]) ?></div>
<?php endif;
layout_end();
