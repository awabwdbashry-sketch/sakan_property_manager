<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('payments.view');
sync_overdue();
$id = get_int('id');
$i = q_one("SELECT i.*, t.full_name, t.phone, u.unit_no, p.name pname, c.contract_no
            FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id JOIN contracts c ON c.id = i.contract_id WHERE i.id = ?", [$id]);
if (!$i) render_error_page('غير موجود', 'الاستحقاق المطلوب غير موجود.', 404);
$pays = q_all("SELECT pay.*, m.name mname, rc.receipt_no FROM payments pay JOIN payment_methods m ON m.id = pay.method_id LEFT JOIN receipts rc ON rc.payment_id = pay.id WHERE pay.installment_id = ? ORDER BY pay.paid_on, pay.id", [$id]);
$overdue = $i['status'] === 'overdue' || ($i['status'] !== 'cancelled' && to_cents($i['remaining']) > 0 && $i['due_date'] < today());
layout_start('كشف الاستحقاق ' . $i['ref_no'], 'payments', ['print' => true]);
doc_actions('payments/view.php?id=' . $id, 'رجوع للاستحقاق', (to_cents($i['remaining']) > 0 && $i['status'] !== 'cancelled') ? wa_button('rent', $id, 'تذكير عبر واتساب') : '');
?>
<div class="doc-sheet">
  <div class="doc-head"><div><h1>كشف استحقاق إيجار</h1><div class="muted"><?= e(setting('org_name')) ?></div></div>
    <div class="doc-meta">المرجع: <b class="ltr"><?= e($i['ref_no']) ?></b><br>تاريخ الإصدار: <?= fmt_date(today()) ?><br>الحالة: <b><?= e($overdue ? 'متأخر' : label('inst_status', $i['status'])) ?></b></div></div>
  <dl class="dl">
    <div><dt>المستأجر</dt><dd><?= e($i['full_name']) ?></dd></div>
    <div><dt>العقار / الوحدة</dt><dd><?= e($i['pname']) ?> / <?= e($i['unit_no']) ?></dd></div>
    <div><dt>رقم العقد</dt><dd class="ltr"><?= e($i['contract_no']) ?></dd></div>
    <div><dt>الفترة الإيجارية</dt><dd><?= fmt_date($i['period_start']) ?> ← <?= fmt_date($i['period_end']) ?></dd></div>
    <div><dt>تاريخ الاستحقاق</dt><dd><?= fmt_date($i['due_date']) ?></dd></div>
  </dl>
  <div class="grid g3" style="margin:16px 0">
    <div class="kv"><span>المبلغ الأصلي</span><b><?= money($i['amount']) ?></b></div>
    <div class="kv"><span>إجمالي المدفوع</span><b><?= money($i['paid_amount']) ?></b></div>
    <div class="kv"><span>المتبقي</span><b><?= money($i['remaining']) ?></b></div></div>
  <h3>الدفعات</h3>
  <?php if (!$pays): ?><p class="muted">لا توجد دفعات مسجلة على هذا الاستحقاق.</p><?php else: ?>
  <table class="tbl tbl-sm"><thead><tr><th>رقم العملية</th><th>التاريخ</th><th>الوسيلة</th><th>الإيصال</th><th class="amt">المبلغ</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($pays as $p): ?><tr style="<?= $p['status'] === 'void' ? 'text-decoration:line-through;opacity:.6' : '' ?>"><td class="ltr"><?= e($p['txn_no']) ?></td><td><?= fmt_date($p['paid_on']) ?></td><td><?= e($p['mname']) ?></td><td class="ltr"><?= e($p['receipt_no']) ?></td><td class="amt"><?= money($p['amount']) ?></td><td><?= $p['status'] === 'void' ? 'ملغاة' : 'معتمدة' ?></td></tr><?php endforeach; ?></tbody></table>
  <p class="muted" style="font-size:12.5px">الدفعات الملغاة تظهر للشفافية ولا تدخل في إجمالي المدفوع.</p><?php endif; ?>
  <div class="doc-sign"><div>أعدّه: <?= e(current_user()['full_name'] ?? '') ?></div><div>الاعتماد</div></div>
</div>
<?php layout_end();
