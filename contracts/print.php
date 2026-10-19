<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('contracts.view');
$id = get_int('id');
$c = q_one("SELECT c.*, t.full_name, t.phone, t.address taddr, u.unit_no, u.floor, u.unit_type, p.name pname, p.address paddr, p.city
            FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id WHERE c.id = ?", [$id]);
if (!$c) render_error_page('غير موجود', 'العقد المطلوب غير موجود.', 404);
$insts = q_all("SELECT * FROM installments WHERE contract_id = ? AND status <> 'cancelled' ORDER BY seq_no", [$id]);
layout_start('ملخص العقد ' . $c['contract_no'], 'contracts', ['print' => true]);
doc_actions('contracts/view.php?id=' . $id, 'رجوع للعقد', wa_button('contract', $id, 'رسالة واتساب بخصوص العقد'));
?>
<div class="doc-sheet">
  <div class="doc-head"><div><h1>ملخص عقد إيجار</h1><div class="muted"><?= e(setting('org_name')) ?></div></div>
    <div class="doc-meta">رقم العقد: <b class="ltr"><?= e($c['contract_no']) ?></b><br>الحالة: <?= e(label('contract_status', $c['status'])) ?><br>تاريخ الطباعة: <?= fmt_date(today()) ?></div></div>
  <h3>أطراف العقد</h3>
  <dl class="dl"><div><dt>المؤجر</dt><dd><?= e(setting('owner_name') ?: setting('org_name')) ?></dd></div><div><dt>المستأجر</dt><dd><?= e($c['full_name']) ?></dd></div><div><dt>هاتف المستأجر</dt><dd class="ltr"><?= e($c['phone']) ?></dd></div></dl>
  <h3 style="margin-top:20px">العين المؤجرة</h3>
  <dl class="dl"><div><dt>العقار</dt><dd><?= e($c['pname']) ?></dd></div><div><dt>العنوان</dt><dd><?= e(trim($c['city'] . ' ' . $c['paddr'])) ?: '—' ?></dd></div><div><dt>الوحدة</dt><dd><?= e($c['unit_no']) ?> <?= $c['floor'] ? '— الطابق ' . e($c['floor']) : '' ?></dd></div><div><dt>النوع</dt><dd><?= e($c['unit_type']) ?: '—' ?></dd></div></dl>
  <h3 style="margin-top:20px">المدة والقيمة</h3>
  <dl class="dl"><div><dt>تاريخ البداية</dt><dd><?= fmt_date_long($c['start_date']) ?></dd></div><div><dt>تاريخ النهاية</dt><dd><?= fmt_date_long($c['end_date']) ?></dd></div>
    <div><dt>الإيجار الشهري</dt><dd><?= money($c['rent_amount']) ?></dd></div><div><dt>دورية السداد</dt><dd><?= label('period', $c['period']) ?></dd></div>
    <div><dt>مبلغ التأمين</dt><dd><?= money($c['deposit_amount']) ?></dd></div><div><dt>يوم الاستحقاق</dt><dd><?= (int)$c['due_day'] ?> من كل فترة</dd></div></dl>
  <?php if ($c['terms']): ?><h3 style="margin-top:20px">الشروط</h3><p style="white-space:pre-line"><?= e($c['terms']) ?></p><?php endif; ?>
  <?php if ($insts): ?><h3 style="margin-top:20px">جدول الاستحقاقات</h3>
  <table class="tbl tbl-sm"><thead><tr><th>#</th><th>الفترة</th><th>الاستحقاق</th><th class="amt">المبلغ</th></tr></thead><tbody>
  <?php foreach ($insts as $i): ?><tr><td><?= (int)$i['seq_no'] ?></td><td><?= fmt_date($i['period_start']) ?> ← <?= fmt_date($i['period_end']) ?></td><td><?= fmt_date($i['due_date']) ?></td><td class="amt"><?= money($i['amount']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  <div class="doc-sign"><div>توقيع المؤجر</div><div>توقيع المستأجر</div></div>
</div>
<?php layout_end();
