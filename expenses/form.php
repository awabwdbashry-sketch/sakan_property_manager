<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('expenses.manage');
$id = get_int('id') ?: post_int('id');
$x = $id ? q_one("SELECT * FROM expenses WHERE id = ?", [$id]) : null;
if ($id && !$x) render_error_page('غير موجود', 'المصروف المطلوب غير موجود.', 404);
if ($x && $x['status'] !== 'paid') render_error_page('غير قابل للتعديل', 'لا يمكن تعديل مصروف ملغى.', 403);
$errors = [];
if (is_post()) {
    csrf_check();
    $d = ['property_id' => post_int('property_id'), 'unit_id' => post_int('unit_id'), 'category_id' => post_int('category_id'), 'amount' => post('amount'),
          'expense_date' => post('expense_date'), 'method_id' => post_int('method_id'), 'payee' => post('payee'), 'description' => post('description'), 'notes' => post('notes'),
          'maintenance_id' => post_int('maintenance_id')];
    try {
        if ($x) { expense_update($id, $d); $eid = $id; } else { $eid = expense_create($d); }
        [$ok, $uerr] = save_uploads('docs', 'expense', $eid, 'document');
        if ($uerr) flash('warning', 'بعض المرفقات لم تُرفع: ' . implode(' | ', $uerr));
        flash('success', $x ? 'تم تحديث المصروف.' : 'تم تسجيل المصروف.');
        redirect('expenses/view.php?id=' . $eid);
    } catch (DomainException $ex) { $errors[] = $ex->getMessage(); }
    $x = array_merge($x ?? [], $d);
}
$props = q_all("SELECT id, name FROM properties WHERE status = 'active' OR id = ? ORDER BY name", [(int)($x['property_id'] ?? 0)]);
$units = q_all("SELECT id, unit_no, property_id FROM units WHERE is_archived = 0 ORDER BY unit_no");
$cats = q_all("SELECT id, name FROM expense_categories WHERE is_active = 1 OR id = ? ORDER BY sort_order, id", [(int)($x['category_id'] ?? 0)]);
$methods = q_all("SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order");
$maintOpts = q_all("SELECT m.id, CONCAT(m.request_no, ' — ', m.title) label, m.property_id FROM maintenance_requests m
                    WHERE m.status <> 'cancelled' AND (NOT EXISTS (SELECT 1 FROM expenses ex WHERE ex.maintenance_id = m.id AND ex.status = 'paid') OR m.id = ?) ORDER BY m.id DESC LIMIT 200", [(int)($x['maintenance_id'] ?? 0)]);
$preMaint = get_int('maintenance_id');
if ($preMaint && !$x && ($pm = q_one("SELECT * FROM maintenance_requests WHERE id = ?", [$preMaint]))) {
    $x = ['property_id' => $pm['property_id'], 'unit_id' => $pm['unit_id'], 'maintenance_id' => $pm['id'], 'description' => 'تكلفة صيانة: ' . $pm['title'], 'payee' => $pm['vendor'], 'amount' => $pm['estimated_cost'] > 0 ? $pm['estimated_cost'] : '', 'expense_date' => today(),
          'category_id' => (int)q_val("SELECT id FROM expense_categories WHERE name = 'صيانة' LIMIT 1")];
}
$v = fn($k, $d = '') => e($x[$k] ?? $d);
layout_start($id ? 'تعديل مصروف' : 'إضافة مصروف', 'expenses');
echo field_error_box($errors);
?>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
<div class="form-grid">
  <div class="field"><label>العقار <span class="req">*</span></label><select name="property_id" id="expProp" required><?= opts($props, 'id', 'name', $x['property_id'] ?? '', 'اختر العقار') ?></select></div>
  <div class="field"><label>الوحدة (اختياري)</label><select name="unit_id" data-units-of="#expProp"><option value="">— على مستوى العقار —</option>
    <?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>" data-property="<?= (int)$u['property_id'] ?>" <?= (int)($x['unit_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['unit_no']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>التصنيف <span class="req">*</span></label><select name="category_id" required><?= opts($cats, 'id', 'name', $x['category_id'] ?? '', 'اختر التصنيف') ?></select></div>
  <div class="field"><label>القيمة <span class="req">*</span></label><input type="number" step="0.01" min="0.01" name="amount" required value="<?= $v('amount') ?>"></div>
  <div class="field"><label>تاريخ المصروف <span class="req">*</span></label><input type="date" name="expense_date" required max="<?= today() ?>" value="<?= $v('expense_date', today()) ?>"></div>
  <div class="field"><label>طريقة الدفع</label><select name="method_id"><?= opts($methods, 'id', 'name', $x['method_id'] ?? '', '—') ?></select></div>
  <div class="field"><label>المستفيد / المورد</label><input type="text" name="payee" maxlength="150" value="<?= $v('payee') ?>"></div>
  <div class="field"><label>ربط ببلاغ صيانة</label><select name="maintenance_id"><option value="">— بدون ربط —</option><?php foreach ($maintOpts as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($x['maintenance_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['label']) ?></option><?php endforeach; ?></select>
    <span class="hint">يربط بلاغاً واحداً بمصروف واحد فقط لمنع احتساب التكلفة مرتين.</span></div>
  <div class="field full"><label>الوصف <span class="req">*</span></label><input type="text" name="description" required maxlength="255" value="<?= $v('description') ?>"></div>
  <div class="field full"><label>ملاحظات</label><textarea name="notes"><?= $v('notes') ?></textarea></div>
  <div class="field full"><label>مرفقات (فواتير، إيصالات)</label><input type="file" name="docs[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"></div>
</div>
<div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button><a class="btn" href="<?= e(url($id ? 'expenses/view.php?id=' . $id : 'expenses/')) ?>">إلغاء</a></div></form>
<?php layout_end();
