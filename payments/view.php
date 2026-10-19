<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('payments.view');
sync_overdue();
$id = get_int('id');
$i = q_one("SELECT i.*, t.full_name, t.phone, u.unit_no, p.name pname, c.contract_no, c.status cstatus
            FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id JOIN contracts c ON c.id = i.contract_id WHERE i.id = ?", [$id]);
if (!$i) render_error_page('غير موجود', 'الاستحقاق المطلوب غير موجود.', 404);
$pays = q_all("SELECT pay.*, m.name mname, r.receipt_no, r.id rid, us.full_name uname FROM payments pay JOIN payment_methods m ON m.id = pay.method_id LEFT JOIN receipts r ON r.payment_id = pay.id LEFT JOIN users us ON us.id = pay.recorded_by WHERE pay.installment_id = ? ORDER BY pay.id", [$id]);
$methods = q_all("SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id");
$remaining = to_cents($i['remaining']);
$canCollect = can('payments.collect') && $remaining > 0 && $i['status'] !== 'cancelled';

layout_start('الاستحقاق ' . $i['ref_no'], 'payments');
page_actions('<a class="btn" href="' . e(url('payments/')) . '">رجوع للقائمة</a> <a class="btn" href="' . e(url('contracts/view.php?id=' . $i['contract_id'])) . '">العقد ' . e($i['contract_no']) . '</a> '
    . '<a class="btn" target="_blank" href="' . e(url('payments/statement.php?id=' . $id)) . '">' . icon('print') . ' كشف الاستحقاق (طباعة / PDF)</a> '
    . ($remaining > 0 && $i['status'] !== 'cancelled' ? wa_button('rent', $id, 'تذكير عبر واتساب') : ''));
?>
<div class="stats">
  <?= stat_card('المبلغ المستحق', money($i['amount']), 'الاستحقاق: ' . fmt_date($i['due_date']), 'amber') ?>
  <?= stat_card('المدفوع', money($i['paid_amount']), '', 'sage') ?>
  <?= stat_card('المتبقي', money($i['remaining']), '', 'red') ?>
  <?= stat_card('الحالة', badge('inst_status', $i['status'])) ?>
</div>
<div class="grid g-main"><div>
  <div class="card"><div class="card-head"><h2>بيانات الاستحقاق</h2></div><dl class="dl">
    <div><dt>المرجع</dt><dd class="ltr"><?= e($i['ref_no']) ?></dd></div>
    <div><dt>المستأجر</dt><dd><a href="<?= e(url('tenants/view.php?id=' . $i['tenant_id'])) ?>"><?= e($i['full_name']) ?></a></dd></div>
    <div><dt>العقار / الوحدة</dt><dd><?= e($i['pname']) ?> / <?= e($i['unit_no']) ?></dd></div>
    <div><dt>الفترة</dt><dd><?= fmt_date($i['period_start']) ?> ← <?= fmt_date($i['period_end']) ?></dd></div>
  </dl></div>
  <?= can('messages.send') ? reminder_log_card('installment_id', $id) : '' ?>
  <div class="card"><div class="card-head"><h2>الدفعات المسجلة</h2></div>
  <?php if (!$pays): ?><div class="empty-inline">لم تُسجَّل أي دفعة على هذا الاستحقاق بعد.</div><?php else: ?>
  <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>رقم العملية</th><th>التاريخ</th><th class="amt">المبلغ</th><th>الوسيلة</th><th>المرجع</th><th>الإيصال</th><th>الحالة</th><th></th></tr></thead><tbody>
  <?php foreach ($pays as $p): ?><tr style="<?= $p['status'] === 'void' ? 'opacity:.6' : '' ?>"><td class="ltr"><?= e($p['txn_no']) ?></td><td><?= fmt_date($p['paid_on']) ?></td><td class="amt"><?= money($p['amount']) ?></td><td><?= e($p['mname']) ?></td><td class="ltr"><?= e($p['reference_no']) ?: '—' ?></td>
    <td><?php if ($p['rid']): ?><a class="ltr" target="_blank" href="<?= e(url('payments/receipt.php?id=' . $p['id'])) ?>"><?= e($p['receipt_no']) ?></a><?php endif; ?></td>
    <td><?= badge('pay_status', $p['status']) ?><?php if ($p['status'] === 'void'): ?><br><small class="muted"><?= e($p['void_reason']) ?></small><?php endif; ?></td>
    <td class="acts"><?php if ($p['status'] === 'valid' && can('payments.void')): ?>
      <details><summary class="btn btn-sm btn-danger">إلغاء</summary>
        <form method="post" action="<?= e(url('payments/void.php')) ?>" data-confirm="سيتم إلغاء هذه الدفعة بتصحيح موثّق وإعادة احتساب المتبقي. لا يمكن التراجع. هل أنت متأكد؟" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="installment_id" value="<?= $id ?>">
        <input type="text" name="reason" required minlength="3" maxlength="250" placeholder="سبب الإلغاء (مطلوب)"><button class="btn btn-sm btn-danger" style="margin-top:6px" type="submit">تأكيد الإلغاء</button></form></details><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</div><div>
<?php if ($canCollect): ?>
  <div class="card" id="collect"><h2>تسجيل دفعة</h2>
    <form method="post" action="<?= e(url('payments/collect.php')) ?>"><?= csrf_field() ?><?= form_key_field() ?><input type="hidden" name="installment_id" value="<?= $id ?>">
      <div class="field"><label>المبلغ <span class="req">*</span></label><input type="number" id="payAmt" name="amount" step="0.01" min="0.01" max="<?= e(cents_to_dec($remaining)) ?>" required value="<?= e(cents_to_dec($remaining)) ?>">
        <span class="hint">المتبقي: <b><?= money($i['remaining']) ?></b> — <a href="#" data-fill="#payAmt" data-value="<?= e(cents_to_dec($remaining)) ?>" onclick="event.preventDefault();document.getElementById('payAmt').value='<?= e(cents_to_dec($remaining)) ?>'">سداد كامل المتبقي</a></span></div>
      <div class="field" style="margin-top:10px"><label>تاريخ الدفع</label><input type="date" name="paid_on" value="<?= today() ?>" max="<?= today() ?>" required></div>
      <div class="field" style="margin-top:10px"><label>وسيلة الدفع</label><select name="method_id" required><?= opts($methods, 'id', 'name') ?></select></div>
      <div class="field" style="margin-top:10px"><label>رقم مرجع التحويل</label><input type="text" name="reference_no" class="ltr-in" maxlength="80"></div>
      <div class="field" style="margin-top:10px"><label>ملاحظات</label><input type="text" name="notes" maxlength="250"></div>
      <div class="form-actions" style="border:0;padding:0"><button class="btn btn-primary" type="submit">تسجيل الدفعة وإصدار الإيصال</button></div>
    </form></div>
<?php elseif ($remaining <= 0 && $i['status'] === 'paid'): ?><div class="alert alert-success">هذا الاستحقاق مسدد بالكامل.</div>
<?php elseif ($i['status'] === 'cancelled'): ?><div class="alert alert-warning">هذا الاستحقاق ملغى (انتهى العقد قبل موعده).</div><?php endif; ?>
</div></div>
<?php layout_end();
