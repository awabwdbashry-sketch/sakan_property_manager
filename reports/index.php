<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('reports.view');
layout_start('التقارير', 'reports');
?>
<div class="def-box" style="margin-bottom:18px"><b>تعريفات المؤشرات:</b> <b>المستحق</b> = قيمة الأقساط حسب تاريخ استحقاقها · <b>المحصّل</b> = الدفعات الصحيحة حسب تاريخ الدفع · <b>المتبقي</b> = المستحق − المدفوع · <b>المتأخرات</b> = المتبقي من أقساط فات موعدها · <b>المصروفات</b> = المصروفات المدفوعة غير الملغاة · <b>صافي التدفق النقدي</b> = المحصّل − المصروفات (وليس أرباحاً محققة).</div>
<div class="prop-cards">
<?php foreach (report_catalog() as $k => $r): ?>
  <a class="prop-card" style="color:inherit;text-decoration:none" href="<?= e(url('reports/report.php?type=' . $k)) ?>"><div style="display:flex;gap:12px;align-items:center"><span class="brand-mark" style="flex:none"><?= icon('chart') ?></span><div><h3 style="margin:0"><?= e($r[0]) ?></h3><div class="muted"><?= e($r[1]) ?></div></div></div></a>
<?php endforeach; ?>
  <div class="prop-card"><h3>كشوف الحساب</h3><div class="muted">استحقاقات ودفعات ورصيد جارٍ، قابلة للطباعة.</div>
  <?php foreach ([['tenant', 'مستأجر', q_all("SELECT id, full_name label FROM tenants ORDER BY full_name")],
                  ['unit', 'وحدة', q_all("SELECT u.id, CONCAT(p.name, ' / ', u.unit_no) label FROM units u JOIN properties p ON p.id = u.property_id ORDER BY p.name, u.unit_no")],
                  ['property', 'عقار', q_all("SELECT id, name label FROM properties ORDER BY name")]] as [$ty, $lab, $list]): ?>
    <form method="get" action="<?= e(url('reports/statement.php')) ?>" style="display:flex;gap:8px;margin-top:10px"><input type="hidden" name="type" value="<?= $ty ?>">
      <select name="id" required style="flex:1"><option value="">كشف حساب <?= $lab ?>…</option><?= opts($list, 'id', 'label') ?></select><button class="btn btn-sm btn-primary">عرض</button></form>
  <?php endforeach; ?></div>
</div>
<?php layout_end();
