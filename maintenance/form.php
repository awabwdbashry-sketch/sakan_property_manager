<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('maintenance.manage');
$id = get_int('id') ?: post_int('id');
$m = $id ? q_one("SELECT * FROM maintenance_requests WHERE id = ?", [$id]) : null;
if ($id && !$m) render_error_page('غير موجود', 'البلاغ المطلوب غير موجود.', 404);
$errors = [];
if (is_post()) {
    csrf_check();
    $d = ['property_id' => post_int('property_id'), 'unit_id' => post_int('unit_id'), 'tenant_id' => post_int('tenant_id'), 'title' => post('title'), 'description' => post('description'),
          'priority' => post('priority', 'medium'), 'reported_on' => post('reported_on') ?: today(), 'assigned_to' => post_int('assigned_to'), 'estimated_cost' => post('estimated_cost'),
          'vendor' => post('vendor'), 'notes' => post('notes'), 'hold_unit' => post('hold_unit') === '1'];
    try {
        if ($m) { maintenance_update($id, $d); $mid = $id; } else { $mid = maintenance_create($d); }
        [$ok, $uerr] = save_uploads('files', 'maintenance', $mid, 'document');
        if ($uerr) flash('warning', 'بعض المرفقات لم تُرفع: ' . implode(' | ', $uerr));
        flash('success', $m ? 'تم تحديث البلاغ.' : 'تم تسجيل البلاغ بنجاح.');
        redirect('maintenance/view.php?id=' . $mid);
    } catch (DomainException $ex) { $errors[] = $ex->getMessage(); }
    $m = array_merge($m ?? [], $d);
}
$props = q_all("SELECT id, name FROM properties WHERE status = 'active' OR id = ? ORDER BY name", [(int)($m['property_id'] ?? 0)]);
$units = q_all("SELECT id, unit_no, property_id FROM units WHERE is_archived = 0 ORDER BY unit_no");
$tenants = q_all("SELECT id, full_name FROM tenants ORDER BY full_name");
$users = q_all("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
$selUnit = (int)($m['unit_id'] ?? get_int('unit_id'));
$selProp = (int)($m['property_id'] ?? 0); if (!$selProp && $selUnit) $selProp = (int)q_val("SELECT property_id FROM units WHERE id = ?", [$selUnit]);
$selTenant = (int)($m['tenant_id'] ?? 0);
if (!$m && $selUnit && !$selTenant) $selTenant = (int)q_val("SELECT tenant_id FROM contracts WHERE unit_id = ? AND status = 'active' LIMIT 1", [$selUnit]);
$v = fn($k, $d = '') => e($m[$k] ?? $d);
layout_start($id ? 'تعديل بلاغ ' . $m['request_no'] : 'بلاغ صيانة جديد', 'maintenance');
echo field_error_box($errors);
?>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
<div class="form-grid">
  <div class="field"><label>العقار <span class="req">*</span></label><select name="property_id" id="mProp" required><?= opts($props, 'id', 'name', $selProp, 'اختر العقار') ?></select></div>
  <div class="field"><label>الوحدة</label><select name="unit_id" data-units-of="#mProp"><option value="">— مرفق عام / مبنى —</option>
    <?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>" data-property="<?= (int)$u['property_id'] ?>" <?= $selUnit === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['unit_no']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>المستأجر المرتبط</label><select name="tenant_id"><?= opts($tenants, 'id', 'full_name', $selTenant, '— بدون —') ?></select></div>
  <div class="field"><label>الأولوية</label><select name="priority"><?= opts_assoc(labels('priority'), $m['priority'] ?? 'medium') ?></select></div>
  <div class="field full"><label>عنوان المشكلة <span class="req">*</span></label><input type="text" name="title" required maxlength="180" value="<?= $v('title') ?>"></div>
  <div class="field full"><label>الوصف التفصيلي</label><textarea name="description"><?= $v('description') ?></textarea></div>
  <div class="field"><label>تاريخ البلاغ</label><input type="date" name="reported_on" value="<?= $v('reported_on', today()) ?>" max="<?= today() ?>"></div>
  <div class="field"><label>المسؤول عن المتابعة</label><select name="assigned_to"><?= opts($users, 'id', 'full_name', $m['assigned_to'] ?? '', '— غير محدد —') ?></select></div>
  <div class="field"><label>التكلفة التقديرية</label><input type="number" step="0.01" min="0" name="estimated_cost" value="<?= $v('estimated_cost', '0') ?>"><span class="hint">تقدير أولي فقط؛ التكلفة الفعلية تُسجَّل كمصروف مرتبط.</span></div>
  <div class="field"><label>الفني / المورد</label><input type="text" name="vendor" maxlength="150" value="<?= $v('vendor') ?>"></div>
  <div class="field full"><label class="check"><input type="checkbox" name="hold_unit" value="1" <?= !empty($m['hold_unit']) ? 'checked' : '' ?>> حجز الوحدة (تحويلها إلى «تحت الصيانة» حتى إغلاق البلاغ)</label><span class="hint">لا يؤثر على الوحدات المؤجرة بعقد نشط.</span></div>
  <div class="field full"><label>ملاحظات</label><textarea name="notes"><?= $v('notes') ?></textarea></div>
  <div class="field full"><label>صور أو مرفقات</label><input type="file" name="files[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"></div>
</div>
<div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button><a class="btn" href="<?= e(url($id ? 'maintenance/view.php?id=' . $id : 'maintenance/')) ?>">إلغاء</a></div></form>
<?php layout_end();
