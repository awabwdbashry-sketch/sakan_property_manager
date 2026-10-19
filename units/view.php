<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('units.view');
sync_overdue();
$id = get_int('id');
$u = q_one("SELECT u.*, p.name pname, p.status pstatus FROM units u JOIN properties p ON p.id = u.property_id WHERE u.id = ?", [$id]);
if (!$u) render_error_page('غير موجود', 'الوحدة المطلوبة غير موجودة.', 404);

$contracts = q_all("SELECT c.*, t.full_name FROM contracts c JOIN tenants t ON t.id = c.tenant_id WHERE c.unit_id = ? ORDER BY c.start_date DESC", [$id]);
$current = null; foreach ($contracts as $c) if ($c['status'] === 'active') { $current = $c; break; }
$fin = q_one("SELECT COALESCE(SUM(CASE WHEN i.status <> 'cancelled' AND i.due_date <= CURDATE() THEN i.amount END),0) due_to_date,
                     COALESCE(SUM(CASE WHEN " . SQL_OVERDUE . " THEN i.remaining END),0) overdue
              FROM installments i WHERE i.unit_id = ?", [$id]);
$collected = (float)q_val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN installments i ON i.id = p.installment_id WHERE i.unit_id = ? AND p.status = 'valid'", [$id]);
$maint = q_all("SELECT * FROM maintenance_requests WHERE unit_id = ? ORDER BY id DESC LIMIT 10", [$id]);
$images = attachments_for('unit', $id, 'image');

layout_start('الوحدة ' . $u['unit_no'] . ' — ' . $u['pname'], 'units');
$act = '<a class="btn" href="' . e(url('units/')) . '">رجوع للقائمة</a>';
if (can('units.manage')) {
    $act .= ' <a class="btn" href="' . e(url('units/form.php?id=' . $id)) . '">' . icon('edit') . ' تعديل</a>';
    if (!$current) $act .= $u['is_archived'] ? ' ' . confirm_form('units/archive.php', ['id' => $id, 'act' => 'restore'], 'استعادة الوحدة', 'استعادة هذه الوحدة؟', 'btn btn-sage') : ' ' . confirm_form('units/archive.php', ['id' => $id, 'act' => 'archive'], 'أرشفة الوحدة', 'سيتم أرشفة الوحدة وإخفاؤها من القوائم. هل تريد المتابعة؟', 'btn btn-danger');
}
if (can('contracts.manage') && in_array($u['status'], ['vacant', 'reserved'], true) && !$u['is_archived'] && $u['pstatus'] === 'active') $act .= ' <a class="btn btn-primary" href="' . e(url('contracts/form.php?unit_id=' . $id)) . '">' . icon('plus') . ' إنشاء عقد</a>';
if (can('maintenance.manage')) $act .= ' <a class="btn" href="' . e(url('maintenance/form.php?unit_id=' . $id)) . '">' . icon('tool') . ' بلاغ صيانة</a>';
if (can('reports.view')) $act .= ' <a class="btn" href="' . e(url('reports/statement.php?type=unit&id=' . $id)) . '">' . icon('print') . ' كشف الحساب</a>';
page_actions($act);
if ($u['is_archived']) echo '<div class="alert alert-warning">هذه الوحدة مؤرشفة.</div>';
?>
<div class="stats">
  <?= stat_card('الحالة', badge('unit_status', $u['status'])) ?>
  <?= stat_card('الإيجار الأساسي', money($u['base_rent']), 'التأمين: ' . money($u['deposit']), 'clay') ?>
  <?= stat_card('المحصّل فعلياً', money($collected), '', 'sage') ?>
  <?= stat_card('المتأخرات', money($fin['overdue']), '', 'red') ?>
</div>
<div class="grid g-main">
<div>
  <div class="card"><div class="card-head"><h2>بيانات الوحدة</h2></div>
    <dl class="dl">
      <div><dt>العقار</dt><dd><a href="<?= e(url('properties/view.php?id=' . $u['property_id'])) ?>"><?= e($u['pname']) ?></a></dd></div>
      <div><dt>رقم الوحدة</dt><dd><?= e($u['unit_no']) ?></dd></div>
      <div><dt>الطابق</dt><dd><?= e($u['floor']) ?: '—' ?></dd></div>
      <div><dt>النوع</dt><dd><?= e($u['unit_type']) ?: '—' ?></dd></div>
      <div><dt>المساحة</dt><dd><?= $u['area'] !== null ? e($u['area']) . ' م²' : '—' ?></dd></div>
      <div><dt>عدد الغرف</dt><dd><?= $u['rooms'] !== null ? (int)$u['rooms'] : '—' ?></dd></div>
    </dl>
    <?php if ($u['description']): ?><hr><p style="white-space:pre-line;margin:0"><?= e($u['description']) ?></p><?php endif; ?>
    <?php if ($u['notes']): ?><hr><p class="muted" style="white-space:pre-line;margin:0"><b>ملاحظات:</b> <?= e($u['notes']) ?></p><?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>العقد الحالي</h2></div>
    <?php if ($current): ?>
      <dl class="dl">
        <div><dt>رقم العقد</dt><dd><a class="ltr" href="<?= e(url('contracts/view.php?id=' . $current['id'])) ?>"><?= e($current['contract_no']) ?></a></dd></div>
        <div><dt>المستأجر</dt><dd><a href="<?= e(url('tenants/view.php?id=' . $current['tenant_id'])) ?>"><?= e($current['full_name']) ?></a></dd></div>
        <div><dt>الفترة</dt><dd><?= fmt_date($current['start_date']) ?> ← <?= fmt_date($current['end_date']) ?></dd></div>
        <div><dt>الإيجار الشهري</dt><dd><?= money($current['rent_amount']) ?></dd></div>
      </dl>
    <?php else: ?><div class="empty-inline">لا يوجد عقد نشط على هذه الوحدة.</div><?php endif; ?></div>
  <div class="card"><div class="card-head"><h2>سجل العقود</h2></div>
    <?php if (!$contracts): ?><div class="empty-inline">لم تُؤجَّر هذه الوحدة بعد.</div><?php else: ?>
    <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>العقد</th><th>المستأجر</th><th>من</th><th>إلى</th><th class="amt">الإيجار</th><th>الحالة</th></tr></thead><tbody>
    <?php foreach ($contracts as $c): ?><tr><td><a class="ltr" href="<?= e(url('contracts/view.php?id=' . $c['id'])) ?>"><?= e($c['contract_no']) ?></a></td><td><?= e($c['full_name']) ?></td><td><?= fmt_date($c['start_date']) ?></td><td><?= fmt_date($c['end_date']) ?></td><td class="amt"><?= money($c['rent_amount']) ?></td><td><?= badge('contract_status', $c['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div>
  <?php if (can('maintenance.view')): ?><div class="card"><div class="card-head"><h2>سجل الصيانة</h2></div>
    <?php if (!$maint): ?><div class="empty-inline">لا توجد بلاغات صيانة لهذه الوحدة.</div><?php else: ?>
    <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>الرقم</th><th>المشكلة</th><th>التاريخ</th><th>الأولوية</th><th>الحالة</th></tr></thead><tbody>
    <?php foreach ($maint as $m): ?><tr><td><a class="ltr" href="<?= e(url('maintenance/view.php?id=' . $m['id'])) ?>"><?= e($m['request_no']) ?></a></td><td><?= e($m['title']) ?></td><td><?= fmt_date($m['reported_on']) ?></td><td><?= badge('priority', $m['priority']) ?></td><td><?= badge('maint_status', $m['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div><?php endif; ?>
</div>
<div>
  <div class="card"><h2>الصور</h2><?= image_gallery($images) ?></div>
  <?php if (can('units.manage') && !$current && !$u['is_archived']): ?>
  <div class="card"><h2>تغيير الحالة التشغيلية</h2>
    <form method="post" action="<?= e(url('units/status.php')) ?>" data-confirm="هل تريد تغيير حالة الوحدة؟">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <div class="field"><select name="status"><?= opts_assoc(array_diff_key(labels('unit_status'), ['rented' => 1]), $u['status']) ?></select></div>
      <div class="form-actions" style="border:0;padding:0;margin-top:12px"><button class="btn btn-primary btn-sm" type="submit">تحديث</button></div>
    </form>
    <p class="hint muted" style="margin-bottom:0">الحالة «مؤجرة» تُضبط تلقائياً بتفعيل العقد.</p>
  </div><?php endif; ?>
</div>
</div>
<?php layout_end();
