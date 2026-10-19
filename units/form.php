<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('units.manage');

$id = get_int('id') ?: post_int('id');
$u = $id ? q_one("SELECT * FROM units WHERE id = ?", [$id]) : null;
if ($id && !$u) render_error_page('غير موجود', 'الوحدة المطلوبة غير موجودة.', 404);
$hasActive = $u ? (int)q_val("SELECT COUNT(*) FROM contracts WHERE unit_id = ? AND status = 'active'", [$id]) > 0 : false;
$errors = [];
$presetProp = get_int('property_id');

if (is_post()) {
    csrf_check();
    $d = ['property_id' => post_int('property_id'), 'unit_no' => post('unit_no'), 'floor' => post('floor'), 'unit_type' => post('unit_type'),
          'area' => post('area'), 'rooms' => post('rooms'), 'base_rent' => post('base_rent') ?: '0', 'deposit' => post('deposit') ?: '0',
          'description' => post('description'), 'notes' => post('notes'), 'status' => post('status', 'vacant')];
    $prop = q_one("SELECT id, status FROM properties WHERE id = ?", [$d['property_id']]);
    if (!$prop) $errors[] = 'اختر العقار.';
    elseif ($prop['status'] !== 'active' && (!$u || (int)$u['property_id'] !== (int)$prop['id'])) $errors[] = 'لا يمكن إضافة وحدات إلى عقار مؤرشف.';
    if ($u && $prop && (int)$u['property_id'] !== (int)$d['property_id'] && q_val("SELECT COUNT(*) FROM contracts WHERE unit_id = ?", [$id]) > 0) $errors[] = 'لا يمكن نقل وحدة لها عقود إلى عقار آخر.';
    if ($d['unit_no'] === '' || mb_strlen($d['unit_no']) > 30) $errors[] = 'رقم الوحدة مطلوب (حتى 30 حرفاً).';
    elseif ($prop && q_val("SELECT id FROM units WHERE property_id = ? AND unit_no = ? AND id <> ?", [$d['property_id'], $d['unit_no'], $id])) $errors[] = 'رقم الوحدة مستخدم مسبقاً داخل هذا العقار.';
    if (!valid_money($d['base_rent'])) $errors[] = 'قيمة الإيجار الأساسي غير صحيحة.';
    if (!valid_money($d['deposit'])) $errors[] = 'قيمة التأمين غير صحيحة.';
    if ($d['area'] !== '' && !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $d['area'])) $errors[] = 'المساحة غير صحيحة.';
    if ($d['rooms'] !== '' && (!ctype_digit($d['rooms']) || (int)$d['rooms'] > 100)) $errors[] = 'عدد الغرف غير صحيح.';
    if (!isset(labels('unit_status')[$d['status']]) || $d['status'] === 'rented') $d['status'] = $u && $u['status'] === 'rented' ? 'rented' : 'vacant';
    if ($hasActive && $d['status'] !== 'rented') { $d['status'] = 'rented'; flash('warning', 'الوحدة لديها عقد نشط، لذلك بقيت حالتها «مؤجرة».'); }
    if (!$errors) {
        $vals = [$d['property_id'], $d['unit_no'], $d['floor'] ?: null, $d['unit_type'] ?: null, $d['area'] === '' ? null : $d['area'], $d['rooms'] === '' ? null : (int)$d['rooms'],
                 cents_to_dec(to_cents($d['base_rent'])), cents_to_dec(to_cents($d['deposit'])), $d['description'] ?: null, $d['notes'] ?: null, $d['status']];
        if ($u) {
            q("UPDATE units SET property_id=?, unit_no=?, floor=?, unit_type=?, area=?, rooms=?, base_rent=?, deposit=?, description=?, notes=?, status=? WHERE id=?", [...$vals, $id]);
            audit('unit.update', 'unit', $id, ['old' => array_intersect_key($u, $d), 'new' => $d]);
            $uid = $id;
            sync_unit_status($uid);
        } else {
            q("INSERT INTO units (property_id, unit_no, floor, unit_type, area, rooms, base_rent, deposit, description, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", [...$vals, user_id()]);
            $uid = insert_id();
            audit('unit.create', 'unit', $uid, $d);
        }
        [$ok, $uerr] = save_uploads('images', 'unit', $uid, 'image');
        if ($uerr) flash('warning', 'تم الحفظ، لكن بعض الصور لم تُرفع: ' . implode(' | ', $uerr));
        flash('success', $u ? 'تم تحديث الوحدة.' : 'تمت إضافة الوحدة بنجاح.');
        redirect('units/view.php?id=' . $uid);
    }
    $u = array_merge($u ?? [], $d);
}
$props = q_all("SELECT id, name FROM properties WHERE status = 'active' OR id = ? ORDER BY name", [(int)($u['property_id'] ?? 0)]);
$v = fn($k) => e($u[$k] ?? '');
$selProp = $u['property_id'] ?? $presetProp;
$statuses = labels('unit_status'); if (!$hasActive && (!$u || $u['status'] !== 'rented')) unset($statuses['rented']);
layout_start($id ? 'تعديل وحدة' : 'إضافة وحدة', 'units');
echo field_error_box($errors);
if ($hasActive) echo '<div class="alert alert-info">هذه الوحدة لديها عقد نشط، وتُدار حالتها تلقائياً من العقود.</div>';
?>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="form-grid">
    <div class="field"><label>العقار <span class="req">*</span></label><select name="property_id" required><?= opts($props, 'id', 'name', $selProp, 'اختر العقار') ?></select></div>
    <div class="field"><label>رقم الوحدة <span class="req">*</span></label><input type="text" name="unit_no" required maxlength="30" value="<?= $v('unit_no') ?>"></div>
    <div class="field"><label>الطابق</label><input type="text" name="floor" maxlength="20" value="<?= $v('floor') ?>"></div>
    <div class="field"><label>نوع الوحدة</label><select name="unit_type"><?= opts_assoc(labels('unit_types'), $u['unit_type'] ?? '', 'اختر النوع') ?></select></div>
    <div class="field"><label>المساحة (م²)</label><input type="number" name="area" step="0.01" min="0" value="<?= $v('area') ?>"></div>
    <div class="field"><label>عدد الغرف</label><input type="number" name="rooms" min="0" max="100" value="<?= $v('rooms') ?>"></div>
    <div class="field"><label>قيمة الإيجار الأساسي (شهرياً) <span class="req">*</span></label><input type="number" name="base_rent" step="0.01" min="0" required value="<?= $v('base_rent') !== '' ? $v('base_rent') : '0' ?>"></div>
    <div class="field"><label>قيمة التأمين</label><input type="number" name="deposit" step="0.01" min="0" value="<?= $v('deposit') !== '' ? $v('deposit') : '0' ?>"></div>
    <div class="field"><label>الحالة التشغيلية</label><select name="status" <?= $hasActive ? 'disabled' : '' ?>><?= opts_assoc($statuses, $u['status'] ?? 'vacant') ?></select>
      <?php if ($hasActive): ?><input type="hidden" name="status" value="rented"><?php endif; ?></div>
    <div class="field full"><label>الوصف</label><textarea name="description"><?= $v('description') ?></textarea></div>
    <div class="field full"><label>ملاحظات داخلية</label><textarea name="notes"><?= $v('notes') ?></textarea></div>
    <div class="field full"><label>صور الوحدة</label><input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button><a class="btn" href="<?= e(url($id ? 'units/view.php?id=' . $id : 'units/')) ?>">إلغاء</a></div>
</form>
<?php if ($id && ($imgs = attachments_for('unit', $id, 'image'))): ?>
<div class="card"><h3>الصور الحالية</h3><?= image_gallery($imgs, true, 'units/image_delete.php', ['unit_id' => $id]) ?></div>
<?php endif;
layout_end();
