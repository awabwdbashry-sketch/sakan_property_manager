<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('maintenance.view');
$id = get_int('id');
$m = q_one("SELECT m.*, p.name pname, u.unit_no, t.full_name tname, us.full_name assignee FROM maintenance_requests m JOIN properties p ON p.id = m.property_id
            LEFT JOIN units u ON u.id = m.unit_id LEFT JOIN tenants t ON t.id = m.tenant_id LEFT JOIN users us ON us.id = m.assigned_to WHERE m.id = ?", [$id]);
if (!$m) render_error_page('غير موجود', 'البلاغ المطلوب غير موجود.', 404);
$exp = q_one("SELECT * FROM expenses WHERE maintenance_id = ? AND status = 'paid'", [$id]);
$logs = q_all("SELECT l.*, u.full_name FROM maintenance_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.request_id = ? ORDER BY l.id", [$id]);
layout_start('تقرير بلاغ ' . $m['request_no'], 'maintenance', ['print' => true]);
doc_actions('maintenance/view.php?id=' . $id, 'رجوع للبلاغ', wa_button('maintenance', $id, 'رسالة واتساب بخصوص البلاغ'));
?>
<div class="doc-sheet">
  <div class="doc-head"><div><h1>تقرير بلاغ صيانة</h1><div class="muted"><?= e(setting('org_name')) ?></div></div>
    <div class="doc-meta">رقم البلاغ: <b class="ltr"><?= e($m['request_no']) ?></b><br>تاريخ الإصدار: <?= fmt_date(today()) ?><br>الحالة: <b><?= e(label('maint_status', $m['status'])) ?></b></div></div>
  <h3><?= e($m['title']) ?></h3>
  <dl class="dl">
    <div><dt>العقار</dt><dd><?= e($m['pname']) ?></dd></div><div><dt>الوحدة</dt><dd><?= e($m['unit_no'] ?: 'مرفق عام') ?></dd></div>
    <div><dt>المستأجر</dt><dd><?= e($m['tname'] ?: '—') ?></dd></div><div><dt>الأولوية</dt><dd><?= e(label('priority', $m['priority'])) ?></dd></div>
    <div><dt>تاريخ البلاغ</dt><dd><?= fmt_date($m['reported_on']) ?></dd></div><div><dt>تاريخ الإكمال</dt><dd><?= fmt_date($m['completed_on']) ?></dd></div>
    <div><dt>المسؤول</dt><dd><?= e($m['assignee'] ?: '—') ?></dd></div><div><dt>الفني / المورد</dt><dd><?= e($m['vendor'] ?: '—') ?></dd></div>
    <div><dt>التكلفة التقديرية</dt><dd><?= money($m['estimated_cost']) ?></dd></div>
    <div><dt>التكلفة الفعلية (المصروف المرتبط)</dt><dd><?= $exp ? money($exp['amount']) . ' — ' . e($exp['expense_no']) : 'لم تُسجَّل' ?></dd></div></dl>
  <?php if ($m['description']): ?><h3 style="margin-top:18px">الوصف</h3><p style="white-space:pre-line"><?= e($m['description']) ?></p><?php endif; ?>
  <?php if ($logs): ?><h3 style="margin-top:18px">سجل المتابعة</h3><table class="tbl tbl-sm"><thead><tr><th>التاريخ</th><th>الإجراء</th><th>بواسطة</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?><tr><td><?= e(fmt_datetime($l['created_at'])) ?></td><td><?= e($l['note'] ?: $l['event']) ?></td><td><?= e($l['full_name'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  <div class="doc-sign"><div>المسؤول عن المتابعة</div><div>الاعتماد</div></div>
</div>
<?php layout_end();
