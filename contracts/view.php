<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('contracts.view');
expire_contracts(); sync_overdue();
$id = get_int('id');
$c = q_one("SELECT c.*, t.full_name, t.phone, u.unit_no, u.status ustatus, p.name pname, p.address paddr, rf.contract_no renewed_no, rt.id renewal_id, rt.contract_no renewal_no
            FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id
            LEFT JOIN contracts rf ON rf.id = c.renewed_from_id
            LEFT JOIN contracts rt ON rt.renewed_from_id = c.id AND rt.status IN ('draft','active')
            WHERE c.id = ?", [$id]);
if (!$c) render_error_page('غير موجود', 'العقد المطلوب غير موجود.', 404);
$insts = q_all("SELECT * FROM installments WHERE contract_id = ? ORDER BY seq_no", [$id]);
$events = q_all("SELECT e.*, u.full_name uname FROM contract_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.contract_id = ? ORDER BY e.id DESC", [$id]);
$deposits = q_all("SELECT d.*, m.name mname FROM deposits d LEFT JOIN payment_methods m ON m.id = d.method_id WHERE d.contract_id = ? ORDER BY d.txn_date, d.id", [$id]);
$depBal = deposit_balance_cents($id);
$docs = attachments_for('contract', $id);
$methods = q_all("SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order");
$totals = ['amount' => 0, 'paid' => 0, 'rem' => 0];
foreach ($insts as $i) if ($i['status'] !== 'cancelled') { $totals['amount'] += to_cents($i['amount']); $totals['paid'] += to_cents($i['paid_amount']); $totals['rem'] += to_cents($i['remaining']); }
$preview = $c['status'] === 'draft' ? build_schedule($c['start_date'], $c['end_date'], $c['period'], to_cents($c['rent_amount']), (int)$c['due_day']) : [];
$daysLeft = days_between(today(), $c['end_date']);

layout_start('العقد ' . $c['contract_no'], 'contracts');
$act = '<a class="btn" href="' . e(url('contracts/')) . '">رجوع للقائمة</a> <a class="btn" target="_blank" href="' . e(url('contracts/print.php?id=' . $id)) . '">' . icon('print') . ' معاينة وطباعة / PDF</a> ' . wa_button('contract', $id, 'تذكير بالتجديد / الانتهاء');
if (can('contracts.manage') && in_array($c['status'], ['draft', 'active'], true)) $act .= ' <a class="btn" href="' . e(url('contracts/form.php?id=' . $id)) . '">' . icon('edit') . ' تعديل</a>';
if (can('contracts.manage') && $c['status'] === 'draft') $act .= ' ' . confirm_form('contracts/action.php', ['id' => $id, 'act' => 'activate'], 'تفعيل العقد', 'سيتم تفعيل العقد وتوليد جميع استحقاقاته وتحويل الوحدة إلى «مؤجرة». هل تريد المتابعة؟', 'btn btn-sage');
page_actions($act);
if ($c['status'] === 'active' && $daysLeft >= 0 && $daysLeft <= (int)setting('alert_contract_days', 30) && !$c['renewal_id']) echo '<div class="alert alert-warning">ينتهي هذا العقد خلال ' . $daysLeft . ' يوم (' . fmt_date($c['end_date']) . '). يمكنك تجديده من الأسفل.</div>';
?>
<div class="stats">
  <?= stat_card('الحالة', badge('contract_status', $c['status']), $c['renewal_id'] ? 'جُدِّد بعقد ' . e($c['renewal_no']) : '') ?>
  <?= stat_card('إجمالي الاستحقاقات', money(cents_to_dec($totals['amount'])), '', 'amber') ?>
  <?= stat_card('المدفوع', money(cents_to_dec($totals['paid'])), '', 'sage') ?>
  <?= stat_card('المتبقي', money(cents_to_dec($totals['rem'])), '', 'red') ?>
  <?= stat_card('رصيد التأمين المحتجز', money(cents_to_dec($depBal)), 'المطلوب: ' . money($c['deposit_amount']), 'clay') ?>
</div>
<div class="grid g-main"><div>
  <div class="card"><div class="card-head"><h2>بيانات العقد</h2></div>
    <dl class="dl">
      <div><dt>رقم العقد</dt><dd class="ltr"><?= e($c['contract_no']) ?></dd></div>
      <div><dt>المستأجر</dt><dd><a href="<?= e(url('tenants/view.php?id=' . $c['tenant_id'])) ?>"><?= e($c['full_name']) ?></a></dd></div>
      <div><dt>العقار</dt><dd><a href="<?= e(url('properties/view.php?id=' . $c['property_id'])) ?>"><?= e($c['pname']) ?></a></dd></div>
      <div><dt>الوحدة</dt><dd><a href="<?= e(url('units/view.php?id=' . $c['unit_id'])) ?>"><?= e($c['unit_no']) ?></a></dd></div>
      <div><dt>المدة</dt><dd><?= fmt_date($c['start_date']) ?> ← <?= fmt_date($c['end_date']) ?></dd></div>
      <div><dt>الإيجار الشهري</dt><dd><?= money($c['rent_amount']) ?></dd></div>
      <div><dt>دورية السداد</dt><dd><?= label('period', $c['period']) ?></dd></div>
      <div><dt>يوم الاستحقاق</dt><dd><?= (int)$c['due_day'] ?></dd></div>
      <?php if ($c['renewed_no']): ?><div><dt>تجديد للعقد</dt><dd><a class="ltr" href="<?= e(url('contracts/view.php?id=' . $c['renewed_from_id'])) ?>"><?= e($c['renewed_no']) ?></a></dd></div><?php endif; ?>
      <?php if ($c['terminated_on']): ?><div><dt>تاريخ الإنهاء</dt><dd><?= fmt_date($c['terminated_on']) ?></dd></div><div><dt>سبب الإنهاء</dt><dd><?= e($c['termination_reason']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($c['terms']): ?><hr><h4>الشروط</h4><p style="white-space:pre-line;margin:0"><?= e($c['terms']) ?></p><?php endif; ?>
    <?php if ($c['notes']): ?><hr><p class="muted" style="white-space:pre-line;margin:0"><b>ملاحظات:</b> <?= e($c['notes']) ?></p><?php endif; ?>
  </div>

  <div class="card"><div class="card-head"><h2><?= $c['status'] === 'draft' ? 'الاستحقاقات المتوقعة (معاينة — لم تُنشأ بعد)' : 'الاستحقاقات' ?></h2></div>
  <?php if ($preview): ?>
    <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>#</th><th>الفترة</th><th>تاريخ الاستحقاق</th><th class="amt">المبلغ</th></tr></thead><tbody>
    <?php foreach ($preview as $s): ?><tr><td><?= $s['seq'] ?></td><td><?= fmt_date($s['period_start']) ?> ← <?= fmt_date($s['period_end']) ?></td><td><?= fmt_date($s['due_date']) ?></td><td class="amt"><?= money(cents_to_dec($s['amount_cents'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php elseif (!$insts): ?><div class="empty-inline">لا توجد استحقاقات لهذا العقد.</div>
  <?php else: ?>
    <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>المرجع</th><th>الفترة</th><th>الاستحقاق</th><th class="amt">المبلغ</th><th class="amt">المدفوع</th><th class="amt">المتبقي</th><th>الحالة</th><th></th></tr></thead><tbody>
    <?php foreach ($insts as $i): ?><tr><td class="ltr"><?= e($i['ref_no']) ?></td><td><?= fmt_date($i['period_start']) ?> ← <?= fmt_date($i['period_end']) ?></td><td><?= fmt_date($i['due_date']) ?></td>
      <td class="amt"><?= money($i['amount']) ?></td><td class="amt"><?= money($i['paid_amount']) ?></td><td class="amt"><?= money($i['remaining']) ?></td>
      <td><?= badge('inst_status', $i['status']) ?><?php if ($i['status'] === 'overdue' && to_cents($i['paid_amount']) > 0) echo ' <small class="muted">(دفع جزئي)</small>'; ?></td>
      <td class="acts"><?php if (can('payments.view')): ?><a class="btn btn-sm" href="<?= e(url('payments/view.php?id=' . $i['id'])) ?>">التفاصيل</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?></div>

  <?php if (can('payments.view')): ?><div class="card"><div class="card-head"><h2>التأمين</h2></div>
    <?php if ($deposits): ?><div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>التاريخ</th><th>الحركة</th><th class="amt">المبلغ</th><th>الوسيلة</th><th>ملاحظات</th></tr></thead><tbody>
    <?php foreach ($deposits as $d): ?><tr><td><?= fmt_date($d['txn_date']) ?></td><td><?= ['received' => 'استلام', 'refunded' => 'رد للمستأجر', 'deducted' => 'خصم'][$d['kind']] ?></td><td class="amt"><?= money($d['amount']) ?></td><td><?= e($d['mname']) ?: '—' ?></td><td><?= e($d['notes']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><div class="empty-inline">لا توجد حركات تأمين.</div><?php endif; ?>
    <?php if (can('payments.collect') && $c['status'] !== 'draft'): ?>
    <details style="margin-top:14px"><summary class="btn btn-sm">تسجيل حركة تأمين</summary>
      <form method="post" action="<?= e(url('contracts/action.php')) ?>" class="form-grid" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="deposit">
        <div class="field"><label>الحركة</label><select name="kind"><?= opts_assoc(['received' => 'استلام تأمين', 'refunded' => 'رد تأمين', 'deducted' => 'خصم من التأمين']) ?></select></div>
        <div class="field"><label>المبلغ</label><input type="number" step="0.01" min="0.01" name="amount" required></div>
        <div class="field"><label>التاريخ</label><input type="date" name="date" value="<?= today() ?>" max="<?= today() ?>"></div>
        <div class="field"><label>الوسيلة</label><select name="method_id"><?= opts($methods, 'id', 'name', null, '—') ?></select></div>
        <div class="field full"><label>ملاحظات</label><input type="text" name="notes" maxlength="250"></div>
        <div class="field"><button class="btn btn-primary" type="submit">حفظ</button></div></form></details>
    <?php endif; ?></div><?php endif; ?>

  <?= can('messages.send') ? reminder_log_card('contract_id', $id) : '' ?>
  <div class="card"><div class="card-head"><h2>سجل تغييرات العقد</h2></div>
    <?php if (!$events): ?><div class="empty-inline">لا توجد أحداث.</div><?php else: ?><ul class="timeline">
    <?php foreach ($events as $ev): ?><li><b><?= e($ev['description']) ?></b><small><?= fmt_datetime($ev['created_at']) ?><?= $ev['uname'] ? ' — ' . e($ev['uname']) : '' ?></small></li><?php endforeach; ?></ul><?php endif; ?></div>
</div><div>
  <?php if ($c['status'] === 'active' && (can('contracts.manage') || can('contracts.terminate'))): ?>
  <?php if (can('contracts.manage') && !$c['renewal_id']): ?>
  <div class="card"><h2>تجديد العقد</h2><p class="muted">ينشئ عقداً جديداً يبدأ بعد <?= fmt_date($c['end_date']) ?> ويربطه بهذا العقد.</p>
    <form method="post" action="<?= e(url('contracts/action.php')) ?>" data-confirm="سيتم إنشاء عقد تجديد وتفعيله مع استحقاقاته. هل تريد المتابعة؟"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="renew">
      <div class="field"><label>تاريخ النهاية الجديد</label><input type="date" name="new_end" required value="<?= e(day_before(add_months(day_after($c['end_date']), 12))) ?>"></div>
      <div class="field" style="margin-top:10px"><label>الإيجار الشهري</label><input type="number" step="0.01" min="0.01" name="rent" required value="<?= e($c['rent_amount']) ?>"></div>
      <div class="field" style="margin-top:10px"><label>دورية السداد</label><select name="period"><?= opts_assoc(labels('period'), $c['period']) ?></select></div>
      <div class="field" style="margin-top:10px"><label>يوم الاستحقاق</label><input type="number" min="1" max="31" name="due_day" value="<?= (int)$c['due_day'] ?>"></div>
      <div class="field" style="margin-top:10px"><label>ملاحظات</label><input type="text" name="notes" maxlength="250"></div>
      <div class="form-actions" style="border:0;padding:0"><button class="btn btn-primary btn-sm" type="submit">تجديد</button></div></form></div>
  <?php endif; ?>
  <?php if (can('contracts.terminate')): ?>
  <div class="card"><h2>إنهاء العقد</h2><p class="muted">تُلغى الاستحقاقات غير المدفوعة التي تبدأ بعد تاريخ الإنهاء، وتبقى المبالغ المسجلة كما هي.</p>
    <form method="post" action="<?= e(url('contracts/action.php')) ?>" data-confirm="سيتم إنهاء العقد نهائياً وتحرير الوحدة. لا يمكن التراجع عن هذه العملية. هل أنت متأكد؟"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="terminate">
      <div class="field"><label>تاريخ الإنهاء</label><input type="date" name="date" required value="<?= today() ?>"></div>
      <div class="field" style="margin-top:10px"><label>السبب <span class="req">*</span></label><input type="text" name="reason" required minlength="3" maxlength="250"></div>
      <div class="form-actions" style="border:0;padding:0"><button class="btn btn-danger btn-sm" type="submit">إنهاء العقد</button></div></form></div>
  <?php endif; endif; ?>
  <div class="card"><h2>مرفقات العقد</h2><?= doc_list($docs, can('contracts.manage'), 'contracts/attachment_delete.php', ['contract_id' => $id]) ?>
    <?php if (can('contracts.manage')): ?><form method="post" action="<?= e(url('contracts/action.php')) ?>" enctype="multipart/form-data" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="upload">
      <div class="field"><input type="file" name="docs[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"></div><div class="form-actions" style="border:0;padding:0;margin-top:10px"><button class="btn btn-sm" type="submit">رفع</button></div></form><?php endif; ?></div>
</div></div>
<?php layout_end();
