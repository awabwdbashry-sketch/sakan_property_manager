<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('maintenance.view');
$id = get_int('id');
$m = q_one("SELECT m.*, p.name pname, u.unit_no, t.full_name tname, us.full_name assignee FROM maintenance_requests m JOIN properties p ON p.id = m.property_id
            LEFT JOIN units u ON u.id = m.unit_id LEFT JOIN tenants t ON t.id = m.tenant_id LEFT JOIN users us ON us.id = m.assigned_to WHERE m.id = ?", [$id]);
if (!$m) render_error_page('غير موجود', 'البلاغ المطلوب غير موجود.', 404);
$exp = q_one("SELECT * FROM expenses WHERE maintenance_id = ? AND status = 'paid'", [$id]);
$logs = q_all("SELECT l.*, u.full_name FROM maintenance_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.request_id = ? ORDER BY l.id DESC", [$id]);
$files = attachments_for('maintenance', $id);
$images = array_values(array_filter($files, fn($f) => $f['kind'] === 'image')); $docs = array_values(array_filter($files, fn($f) => $f['kind'] === 'document'));
$manage = can('maintenance.manage');
layout_start('البلاغ ' . $m['request_no'], 'maintenance');
$act = '<a class="btn" href="' . e(url('maintenance/')) . '">رجوع للقائمة</a>';
if ($manage) $act .= ' <a class="btn" href="' . e(url('maintenance/form.php?id=' . $id)) . '">' . icon('edit') . ' تعديل</a>';
if (can('expenses.manage') && !$exp && $m['status'] !== 'cancelled') $act .= ' <a class="btn btn-primary" href="' . e(url('expenses/form.php?maintenance_id=' . $id)) . '">' . icon('receipt') . ' تسجيل التكلفة الفعلية كمصروف</a>';
$act .= ' <a class="btn" target="_blank" href="' . e(url('maintenance/print.php?id=' . $id)) . '">' . icon('print') . ' تقرير البلاغ (طباعة / PDF)</a> ' . wa_button('maintenance', $id, 'رسالة واتساب');
page_actions($act);
?>
<div class="stats">
  <?= stat_card('الحالة', badge('maint_status', $m['status'])) ?>
  <?= stat_card('الأولوية', badge('priority', $m['priority'])) ?>
  <?= stat_card('التكلفة التقديرية', money($m['estimated_cost']), 'تقدير أولي', 'amber') ?>
  <?= stat_card('التكلفة الفعلية', $exp ? money($exp['amount']) : '—', $exp ? '<a href="' . e(url('expenses/view.php?id=' . $exp['id'])) . '">المصروف ' . e($exp['expense_no']) . '</a>' : 'لم تُسجَّل بعد', 'clay') ?>
</div>
<div class="grid g-main"><div>
  <div class="card"><div class="card-head"><h2><?= e($m['title']) ?></h2></div><dl class="dl">
    <div><dt>رقم البلاغ</dt><dd class="ltr"><?= e($m['request_no']) ?></dd></div><div><dt>تاريخ البلاغ</dt><dd><?= fmt_date($m['reported_on']) ?></dd></div>
    <div><dt>العقار</dt><dd><a href="<?= e(url('properties/view.php?id=' . $m['property_id'])) ?>"><?= e($m['pname']) ?></a></dd></div>
    <div><dt>الوحدة</dt><dd><?= $m['unit_id'] ? '<a href="' . e(url('units/view.php?id=' . $m['unit_id'])) . '">' . e($m['unit_no']) . '</a>' : 'مرفق عام' ?></dd></div>
    <div><dt>المستأجر</dt><dd><?= $m['tenant_id'] ? '<a href="' . e(url('tenants/view.php?id=' . $m['tenant_id'])) . '">' . e($m['tname']) . '</a>' : '—' ?></dd></div>
    <div><dt>المسؤول عن المتابعة</dt><dd><?= e($m['assignee']) ?: '—' ?></dd></div><div><dt>الفني / المورد</dt><dd><?= e($m['vendor']) ?: '—' ?></dd></div>
    <div><dt>تاريخ الإكمال</dt><dd><?= fmt_date($m['completed_on']) ?></dd></div><div><dt>حجز الوحدة</dt><dd><?= $m['hold_unit'] ? 'نعم' : 'لا' ?></dd></div></dl>
    <?php if ($m['description']): ?><hr><p style="white-space:pre-line;margin:0"><?= e($m['description']) ?></p><?php endif; ?>
    <?php if ($m['notes']): ?><hr><p class="muted" style="white-space:pre-line;margin:0"><b>ملاحظات:</b> <?= e($m['notes']) ?></p><?php endif; ?></div>
  <div class="card"><div class="card-head"><h2>سجل التغييرات والملاحظات</h2></div>
    <?php if ($manage): ?><form method="post" action="<?= e(url('maintenance/action.php')) ?>" class="filters" style="border:0;padding:0;background:none"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="note">
      <div class="field grow"><input type="text" name="note" required minlength="2" maxlength="500" placeholder="أضف ملاحظة…"></div><button class="btn btn-sm" type="submit">إضافة</button></form><?php endif; ?>
    <ul class="timeline"><?php foreach ($logs as $l): ?><li><b><?= e($l['note'] ?: $l['event']) ?></b><small><?= fmt_datetime($l['created_at']) ?><?= $l['full_name'] ? ' — ' . e($l['full_name']) : '' ?></small></li><?php endforeach; ?></ul></div>
  <?= can('messages.send') ? reminder_log_card('maintenance_id', $id) : '' ?>
</div><div>
<?php if ($manage): ?><div class="card"><h2>تحديث الحالة</h2>
  <form method="post" action="<?= e(url('maintenance/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="status">
    <div class="field"><label>الحالة</label><select name="status"><?= opts_assoc(labels('maint_status'), $m['status']) ?></select></div>
    <div class="field" style="margin-top:10px"><label>تاريخ الإكمال (عند الإكمال)</label><input type="date" name="completed_on" value="<?= e($m['completed_on'] ?? '') ?>" max="<?= today() ?>"></div>
    <div class="field" style="margin-top:10px"><label>الفني / المورد</label><input type="text" name="vendor" maxlength="150" value="<?= e($m['vendor']) ?>"></div>
    <div class="field" style="margin-top:10px"><label>ملاحظة (مطلوبة عند الإلغاء)</label><input type="text" name="note" maxlength="500"></div>
    <div class="form-actions" style="border:0;padding:0"><button class="btn btn-primary btn-sm" type="submit">تحديث</button></div></form></div><?php endif; ?>
  <div class="card"><h2>الصور</h2><?= image_gallery($images, $manage, 'maintenance/attachment_delete.php', ['request_id' => $id]) ?></div>
  <div class="card"><h2>مستندات</h2><?= doc_list($docs, $manage, 'maintenance/attachment_delete.php', ['request_id' => $id]) ?>
    <?php if ($manage): ?><form method="post" action="<?= e(url('maintenance/action.php')) ?>" enctype="multipart/form-data" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="upload"><div class="field"><input type="file" name="files[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"></div><div class="form-actions" style="border:0;padding:0;margin-top:10px"><button class="btn btn-sm" type="submit">رفع</button></div></form><?php endif; ?></div>
</div></div>
<?php layout_end();
