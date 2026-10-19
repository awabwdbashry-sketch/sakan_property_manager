<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('contracts.manage');
$id = get_int('id') ?: post_int('id');
$c = $id ? q_one("SELECT * FROM contracts WHERE id = ?", [$id]) : null;
if ($id && !$c) render_error_page('غير موجود', 'العقد المطلوب غير موجود.', 404);
if ($c && $c['status'] !== 'draft' && $c['status'] !== 'active') render_error_page('غير قابل للتعديل', 'لا يمكن تعديل عقد ' . label('contract_status', $c['status']) . '.', 403);
$activeOnly = $c && $c['status'] === 'active';
$errors = [];
if (is_post()) {
    csrf_check();
    $d = ['tenant_id' => post_int('tenant_id'), 'unit_id' => post_int('unit_id'), 'start_date' => post('start_date'), 'end_date' => post('end_date'),
          'rent_amount' => post('rent_amount'), 'period' => post('period', 'monthly'), 'deposit_amount' => post('deposit_amount'), 'due_day' => post_int('due_day') ?: 1,
          'terms' => post('terms'), 'notes' => post('notes')];
    try {
        if ($c) { update_contract($id, $activeOnly ? array_merge($c, ['terms' => $d['terms'], 'notes' => $d['notes']]) : $d); $cid = $id; }
        else { $cid = create_contract($d); }
        [$ok, $uerr] = save_uploads('docs', 'contract', $cid, 'document');
        if ($uerr) flash('warning', 'بعض المرفقات لم تُرفع: ' . implode(' | ', $uerr));
        if (!$c && post('activate_now') === '1') {
            try { activate_contract($cid); flash('success', 'تم إنشاء العقد وتفعيله وتوليد الاستحقاقات.'); }
            catch (DomainException $ex) { flash('error', 'أُنشئ العقد كمسودة لكن تعذّر تفعيله: ' . $ex->getMessage()); }
        } else flash('success', $c ? 'تم تحديث العقد.' : 'تم إنشاء العقد كمسودة. راجع الاستحقاقات المتوقعة ثم فعّله.');
        redirect('contracts/view.php?id=' . $cid);
    } catch (DomainException $ex) { $errors[] = $ex->getMessage(); }
    $c = array_merge($c ?? [], $d, ['rent_amount' => $d['rent_amount'], 'deposit_amount' => $d['deposit_amount']]);
}
$tenants = q_all("SELECT id, full_name FROM tenants WHERE status = 'active' OR id = ? ORDER BY full_name", [(int)($c['tenant_id'] ?? 0)]);
$props = q_all("SELECT id, name FROM properties WHERE status = 'active' ORDER BY name");
$units = q_all("SELECT u.id, u.unit_no, u.property_id, u.status, u.base_rent, u.deposit, p.name pname FROM units u JOIN properties p ON p.id = u.property_id
                WHERE u.is_archived = 0 AND p.status = 'active' ORDER BY p.name, u.unit_no");
$selUnit = (int)($c['unit_id'] ?? get_int('unit_id'));
$selTenant = (int)($c['tenant_id'] ?? get_int('tenant_id'));
$selProp = 0; foreach ($units as $u) if ((int)$u['id'] === $selUnit) $selProp = (int)$u['property_id'];
$v = fn($k, $d = '') => e($c[$k] ?? $d);
layout_start($id ? 'تعديل عقد ' . $c['contract_no'] : 'عقد إيجار جديد', 'contracts');
echo field_error_box($errors);
if ($activeOnly) echo '<div class="alert alert-info">العقد نشط: يمكن تعديل الشروط والملاحظات فقط، حفاظاً على السجل المالي. لتغيير الإيجار أو المدة استخدم «التجديد» أو أنهِ العقد وأنشئ عقداً جديداً.</div>';
?>
<form method="post" enctype="multipart/form-data" class="card" id="contractForm">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <fieldset <?= $activeOnly ? 'disabled' : '' ?> style="border:0;padding:0;margin:0"><div class="form-grid">
    <div class="field"><label>المستأجر <span class="req">*</span></label><select name="tenant_id" required><?= opts($tenants, 'id', 'full_name', $selTenant, 'اختر المستأجر') ?></select>
      <span class="hint"><a href="<?= e(url('tenants/form.php')) ?>">إضافة مستأجر جديد</a></span></div>
    <div class="field"><label>العقار</label><select id="propSel"><?= opts($props, 'id', 'name', $selProp, 'كل العقارات') ?></select></div>
    <div class="field"><label>الوحدة <span class="req">*</span></label>
      <select name="unit_id" id="unitSel" required data-units-of="#propSel">
        <option value="">اختر الوحدة</option>
        <?php foreach ($units as $u): $blocked = in_array($u['status'], ['maintenance', 'unavailable'], true); ?>
          <option value="<?= (int)$u['id'] ?>" data-property="<?= (int)$u['property_id'] ?>" data-rent="<?= e($u['base_rent']) ?>" data-deposit="<?= e($u['deposit']) ?>" <?= $selUnit === (int)$u['id'] ? 'selected' : '' ?> <?= $blocked ? 'disabled' : '' ?>>
            <?= e($u['pname'] . ' — ' . $u['unit_no'] . ' (' . label('unit_status', $u['status']) . ')') ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label>تاريخ البداية <span class="req">*</span></label><input type="date" name="start_date" required value="<?= $v('start_date', today()) ?>"></div>
    <div class="field"><label>تاريخ النهاية <span class="req">*</span></label><input type="date" name="end_date" required value="<?= $v('end_date') ?>"></div>
    <div class="field"><label>الإيجار الشهري <span class="req">*</span></label><input type="number" step="0.01" min="0.01" name="rent_amount" id="rentIn" required value="<?= $v('rent_amount') ?>"></div>
    <div class="field"><label>دورية السداد</label><select name="period"><?= opts_assoc(labels('period'), $c['period'] ?? 'monthly') ?></select></div>
    <div class="field"><label>مبلغ التأمين</label><input type="number" step="0.01" min="0" name="deposit_amount" id="depIn" value="<?= $v('deposit_amount') ?>"><span class="hint">لا يُحتسب ضمن الإيجار المحصّل.</span></div>
    <div class="field"><label>يوم استحقاق الإيجار</label><input type="number" min="1" max="31" name="due_day" value="<?= $v('due_day', '1') ?>"><span class="hint">يُقصّ تلقائياً إلى آخر يوم في الأشهر الأقصر.</span></div>
  </div></fieldset>
  <div class="form-grid" style="margin-top:16px">
    <div class="field full"><label>الشروط</label><textarea name="terms"><?= $v('terms') ?></textarea></div>
    <div class="field full"><label>ملاحظات</label><textarea name="notes"><?= $v('notes') ?></textarea></div>
    <div class="field full"><label>مرفق العقد (PDF أو صورة)</label><input type="file" name="docs[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"></div>
    <?php if (!$c): ?><div class="field full"><label class="check"><input type="checkbox" name="activate_now" value="1"> تفعيل العقد فوراً وتوليد الاستحقاقات</label></div><?php endif; ?>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button><a class="btn" href="<?= e(url($id ? 'contracts/view.php?id=' . $id : 'contracts/')) ?>">إلغاء</a></div>
</form>
<script>
(function(){var us=document.getElementById('unitSel'),r=document.getElementById('rentIn'),d=document.getElementById('depIn');
if(!us)return;us.addEventListener('change',function(){var o=us.options[us.selectedIndex];if(!o||!o.value)return;
if(r&&!r.value&&o.dataset.rent)r.value=o.dataset.rent;if(d&&!d.value&&o.dataset.deposit)d.value=o.dataset.deposit;});})();
</script>
<?php layout_end();
