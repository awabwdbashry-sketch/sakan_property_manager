<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('payments.view');
$id = get_int('id');
$r = q_one("SELECT pay.*, m.name mname, rc.receipt_no, rc.issued_at, i.ref_no, i.period_start, i.period_end, i.due_date, i.amount inst_amount, i.paid_amount inst_paid, i.remaining inst_rem,
                   t.full_name, t.phone, u.unit_no, p.name pname, c.contract_no, us.full_name issuer
            FROM payments pay JOIN receipts rc ON rc.payment_id = pay.id JOIN payment_methods m ON m.id = pay.method_id
            JOIN installments i ON i.id = pay.installment_id JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id JOIN contracts c ON c.id = i.contract_id
            LEFT JOIN users us ON us.id = rc.issued_by WHERE pay.id = ?", [$id]);
if (!$r) render_error_page('غير موجود', 'الإيصال المطلوب غير موجود.', 404);
layout_start('إيصال ' . $r['receipt_no'], 'payments', ['print' => true]);
doc_actions('payments/view.php?id=' . $r['installment_id'], 'رجوع للاستحقاق', wa_button('receipt', $id, 'إشعار المستأجر عبر واتساب'));
?>
<div class="doc-sheet">
  <div class="doc-head"><div><h1>إيصال استلام</h1><div class="muted"><?= e(setting('org_name')) ?></div>
      <?php if (setting('contact_phone')): ?><div class="muted ltr"><?= e(setting('contact_phone')) ?></div><?php endif; ?></div>
    <div class="doc-meta">رقم الإيصال: <b class="ltr"><?= e($r['receipt_no']) ?></b><br>رقم العملية: <span class="ltr"><?= e($r['txn_no']) ?></span><br>تاريخ الدفع: <?= fmt_date_long($r['paid_on']) ?></div></div>
  <?php if ($r['status'] === 'void'): ?><div class="center" style="margin-bottom:14px"><span class="void-stamp">ملغى</span><div class="muted"><?= e($r['void_reason']) ?></div></div><?php endif; ?>
  <dl class="dl">
    <div><dt>استلمنا من السيد/ة</dt><dd><?= e($r['full_name']) ?></dd></div>
    <div><dt>العقار / الوحدة</dt><dd><?= e($r['pname']) ?> / <?= e($r['unit_no']) ?></dd></div>
    <div><dt>رقم العقد</dt><dd class="ltr"><?= e($r['contract_no']) ?></dd></div>
    <div><dt>عن الفترة</dt><dd><?= fmt_date($r['period_start']) ?> ← <?= fmt_date($r['period_end']) ?></dd></div>
    <div><dt>وسيلة الدفع</dt><dd><?= e($r['mname']) ?><?= $r['reference_no'] ? ' — ' . e($r['reference_no']) : '' ?></dd></div>
    <div><dt>مرجع الاستحقاق</dt><dd class="ltr"><?= e($r['ref_no']) ?></dd></div>
  </dl>
  <div class="doc-amount"><span>المبلغ المستلم</span><span><?= money($r['amount']) ?></span></div>
  <div class="grid g3" style="margin-bottom:10px">
    <div class="kv"><span>قيمة الاستحقاق</span><b><?= money($r['inst_amount']) ?></b></div>
    <div class="kv"><span>إجمالي المدفوع حتى الآن</span><b><?= money($r['inst_paid']) ?></b></div>
    <div class="kv"><span>المتبقي على الاستحقاق حالياً</span><b><?= money($r['inst_rem']) ?></b></div></div>
  <?php if ($r['notes']): ?><p class="muted">ملاحظات: <?= e($r['notes']) ?></p><?php endif; ?>
  <div class="doc-sign"><div>المستلم: <?= e($r['issuer'] ?: '') ?></div><div>توقيع المستأجر</div></div>
  <p class="muted center" style="margin-top:26px;font-size:12.5px"><?= e(setting('receipt_footer')) ?></p>
</div>
<?php layout_end();
