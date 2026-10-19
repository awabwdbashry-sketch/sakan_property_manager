<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('dashboard.view');
expire_contracts(); sync_overdue(); maybe_run_notifications();

// ----- الفلاتر -----
$pid = get_int('property_id');
$preset = get('range', 'month');
$from = get('from'); $to = get('to');
if ($preset === 'custom' && valid_date($from) && valid_date($to) && $from <= $to) { /* مخصص */ }
else {
    switch ($preset) {
        case 'last': $from = date('Y-m-01', strtotime('first day of last month')); $to = date('Y-m-t', strtotime('last day of last month')); break;
        case 'year': $from = date('Y-01-01'); $to = date('Y-12-31'); break;
        case 'all':  $from = '2000-01-01'; $to = '2099-12-31'; break;
        default: $preset = 'month'; $from = date('Y-m-01'); $to = date('Y-m-t');
    }
}
$pf = $pid ? ' AND i.property_id = ' . (int)$pid : '';
$pfU = $pid ? ' AND u.property_id = ' . (int)$pid : '';

// ----- العقارات والوحدات -----
$totProps = (int)q_val("SELECT COUNT(*) FROM properties WHERE status = 'active'" . ($pid ? " AND id = $pid" : ''));
$uc = q_one("SELECT COUNT(*) total, COALESCE(SUM(u.status='rented'),0) rented, COALESCE(SUM(u.status='vacant'),0) vacant, COALESCE(SUM(u.status='maintenance'),0) maint
             FROM units u JOIN properties p ON p.id = u.property_id WHERE u.is_archived = 0 AND p.status = 'active'$pfU");
$occ = $uc['total'] ? round($uc['rented'] / $uc['total'] * 100, 1) : 0;

// ----- المالية للفترة -----
$due = q_one("SELECT COALESCE(SUM(i.amount),0) due, COALESCE(SUM(i.remaining),0) rem FROM installments i WHERE i.status <> 'cancelled' AND i.due_date BETWEEN ? AND ?$pf", [$from, $to]);
$collected = q_val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN installments i ON i.id = p.installment_id WHERE p.status = 'valid' AND p.paid_on BETWEEN ? AND ?$pf", [$from, $to]);
$arrears = q_one("SELECT COALESCE(SUM(i.remaining),0) amt, COUNT(*) n FROM installments i WHERE " . SQL_OVERDUE . $pf);
$expPaid = q_val("SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.status = 'paid' AND e.expense_date BETWEEN ? AND ?" . ($pid ? " AND e.property_id = $pid" : ''), [$from, $to]);

// ----- قوائم -----
$ctDays = max(1, (int)setting('alert_contract_days', 30)); $rentDays = max(1, (int)setting('alert_rent_days', 7));
$expiring = q_all("SELECT c.id, c.contract_no, c.end_date, t.full_name, u.unit_no, p.name pname FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id
                   WHERE c.status = 'active' AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $ctDays DAY)" . ($pid ? " AND c.property_id = $pid" : '') . " ORDER BY c.end_date LIMIT 8");
$upcoming = q_all("SELECT i.id, i.ref_no, i.due_date, i.remaining, t.full_name, u.unit_no FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id
                   WHERE i.status IN ('due','partial') AND i.remaining > 0 AND i.due_date >= CURDATE()$pf ORDER BY i.due_date LIMIT 8");
$lastPays = q_all("SELECT p.id, p.amount, p.paid_on, p.installment_id, rc.receipt_no, t.full_name FROM payments p JOIN installments i ON i.id = p.installment_id JOIN tenants t ON t.id = i.tenant_id LEFT JOIN receipts rc ON rc.payment_id = p.id
                   WHERE p.status = 'valid'$pf ORDER BY p.id DESC LIMIT 6");
$lastExp = q_all("SELECT e.id, e.expense_no, e.amount, e.expense_date, e.description, c.name cname FROM expenses e JOIN expense_categories c ON c.id = e.category_id WHERE e.status = 'paid'" . ($pid ? " AND e.property_id = $pid" : '') . " ORDER BY e.id DESC LIMIT 6");
$lastMaint = q_all("SELECT m.id, m.request_no, m.title, m.priority, m.status, u.unit_no FROM maintenance_requests m LEFT JOIN units u ON u.id = m.unit_id" . ($pid ? " WHERE m.property_id = $pid" : '') . " ORDER BY m.id DESC LIMIT 6");
$occRows = q_all("SELECT p.id, p.name, COUNT(u.id) total, COALESCE(SUM(u.status='rented'),0) rented FROM properties p LEFT JOIN units u ON u.property_id = p.id AND u.is_archived = 0
                  WHERE p.status = 'active'" . ($pid ? " AND p.id = $pid" : '') . " GROUP BY p.id, p.name ORDER BY p.name");

// ----- مخطط آخر 12 شهراً -----
$cFrom = date('Y-m-01', strtotime('-11 months')); $cTo = date('Y-m-t');
$months = months_between($cFrom, $cTo);
$dueM = array_column(q_all("SELECT DATE_FORMAT(i.due_date,'%Y-%m') ym, SUM(i.amount) s FROM installments i WHERE i.status <> 'cancelled' AND i.due_date BETWEEN ? AND ?$pf GROUP BY ym", [$cFrom, $cTo]), 's', 'ym');
$colM = array_column(q_all("SELECT DATE_FORMAT(p.paid_on,'%Y-%m') ym, SUM(p.amount) s FROM payments p JOIN installments i ON i.id = p.installment_id WHERE p.status = 'valid' AND p.paid_on BETWEEN ? AND ?$pf GROUP BY ym", [$cFrom, $cTo]), 's', 'ym');
$series = [['الإيجارات المستحقة', '#D9B77C', array_map(fn($m) => (float)($dueM[$m] ?? 0), $months)], ['المحصّل فعلياً', '#5F7B62', array_map(fn($m) => (float)($colM[$m] ?? 0), $months)]];
$props = q_all("SELECT id, name FROM properties WHERE status = 'active' ORDER BY name");
$hasData = $totProps > 0;

layout_start('لوحة التحكم', 'dashboard');
?>
<?php
$todo = [];
if (can('payments.view')) { $n = (int)q_val("SELECT COUNT(*) FROM installments i WHERE " . SQL_OVERDUE . ($pid ? ' AND i.property_id = ' . (int)$pid : '')); $todo[] = [$n, 'استحقاقات متأخرة', 'payments/?status=overdue']; }
if (can('messages.send')) { $n = (int)q_val("SELECT COUNT(*) FROM reminders WHERE status = 'approved'"); $todo[] = [$n, 'رسائل فُتح لها واتساب بانتظار تسجيل النتيجة', 'messages/?status=approved']; }
if (can('contracts.view')) { $n = (int)q_val("SELECT COUNT(*) FROM contracts c WHERE c.status = 'active' AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $ctDays DAY)" . ($pid ? ' AND c.property_id = ' . (int)$pid : '')); $todo[] = [$n, 'عقود تنتهي خلال ' . $ctDays . ' يوماً', 'contracts/?soon=1']; }
if (can('maintenance.view')) { $n = (int)q_val("SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('new','in_progress','waiting')" . ($pid ? ' AND property_id = ' . (int)$pid : '')); $todo[] = [$n, 'بلاغات صيانة مفتوحة', 'maintenance/']; }
if (can('notifications.view')) { $n = (int)q_val("SELECT COUNT(*) FROM notifications WHERE handled_at IS NULL AND (snoozed_until IS NULL OR snoozed_until <= NOW())"); $todo[] = [$n, 'إشعارات تحتاج إجراءً', 'notifications/']; }
if ($todo): ?>
<div class="todo-strip" aria-label="إجراءات مطلوبة">
<?php foreach ($todo as [$n, $lab, $href]): ?><a class="todo <?= $n ? '' : 'is-zero' ?>" href="<?= e(url($href)) ?>"><b><?= $n ?></b><span><?= e($lab) ?></span></a><?php endforeach; ?>
</div>
<?php endif; ?>
<form class="filters" method="get">
  <div class="field"><label>الفترة</label><select name="range" id="rangeSel" data-auto><?= opts_assoc(['month' => 'هذا الشهر', 'last' => 'الشهر الماضي', 'year' => 'هذه السنة', 'all' => 'كل الفترات', 'custom' => 'فترة مخصصة'], $preset) ?></select></div>
  <div class="field"><label>من</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label>إلى</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="field"><label>العقار</label><select name="property_id" data-auto><?= opts($props, 'id', 'name', $pid, 'كل العقارات') ?></select></div>
  <button class="btn btn-primary" type="submit" onclick="var r=document.getElementById('rangeSel');if(this.form.from.value&&this.form.to.value)r.value='custom';"><?= icon('search') ?> تطبيق</button>
</form>
<?php if (!$hasData): ?>
  <?= empty_state('ابدأ بإعداد نظامك', 'لا توجد عقارات بعد. أضف أول عقار ثم وحداته ومستأجريه وعقودك لتظهر الأرقام الحقيقية هنا.', can('properties.manage') ? 'إضافة عقار' : '', 'properties/form.php') ?>
<?php else: ?>
<div class="stats">
  <?= stat_card('إجمالي العقارات', (string)$totProps, '', 'clay') ?>
  <?= stat_card('إجمالي الوحدات', (string)(int)$uc['total'], 'مؤجرة ' . (int)$uc['rented'] . ' · شاغرة ' . (int)$uc['vacant'] . ' · صيانة ' . (int)$uc['maint']) ?>
  <?= stat_card('نسبة الإشغال', $occ . '%', '<div class="progress"><i style="width:' . $occ . '%"></i></div>', 'sage') ?>
  <?= stat_card('الإيجارات المستحقة للفترة', money($due['due']), 'حسب تاريخ الاستحقاق', 'amber') ?>
  <?= stat_card('المحصّل فعلياً في الفترة', money($collected), 'حسب تاريخ الدفع', 'sage') ?>
  <?= stat_card('المتبقي من مستحقات الفترة', money($due['rem']), '', 'amber') ?>
  <?= stat_card('إجمالي المتأخرات', money($arrears['amt']), (int)$arrears['n'] . ' استحقاق متأخر (حتى اليوم)', 'red') ?>
  <?= stat_card('المصروفات المدفوعة', money($expPaid), 'خلال الفترة', 'clay') ?>
</div>
<div class="grid g-main">
<div>
  <div class="card"><div class="card-head"><h2>الإيرادات المستحقة والتحصيل — آخر 12 شهراً</h2></div><?= chart_legend($series) ?><?= svg_bar_chart(array_map('short_month_label', $months), $series, 'مقارنة الإيجارات المستحقة بالمحصّل شهرياً') ?></div>
  <div class="grid g2">
    <div class="card"><div class="card-head"><h3>الدفعات القادمة</h3></div>
    <?php if (!$upcoming): ?><div class="empty-inline">لا توجد دفعات قادمة.</div><?php else: foreach ($upcoming as $r): ?>
      <div class="kv"><span><a href="<?= e(url('payments/view.php?id=' . $r['id'])) ?>"><?= e($r['full_name']) ?></a> <small class="muted">وحدة <?= e($r['unit_no']) ?> · <?= fmt_date($r['due_date']) ?></small></span><b><?= money($r['remaining']) ?></b></div><?php endforeach; endif; ?></div>
    <div class="card"><div class="card-head"><h3>عقود تقارب الانتهاء (<?= $ctDays ?> يوماً)</h3></div>
    <?php if (!$expiring): ?><div class="empty-inline">لا توجد عقود قريبة الانتهاء.</div><?php else: foreach ($expiring as $r): ?>
      <div class="kv"><span><a href="<?= e(url('contracts/view.php?id=' . $r['id'])) ?>"><?= e($r['full_name']) ?></a> <small class="muted"><?= e($r['pname']) ?> / <?= e($r['unit_no']) ?></small></span><b><?= fmt_date($r['end_date']) ?></b></div><?php endforeach; endif; ?></div>
  </div>
  <div class="grid g2">
    <div class="card"><div class="card-head"><h3>آخر عمليات التحصيل</h3></div>
    <?php if (!$lastPays): ?><div class="empty-inline">لا توجد عمليات تحصيل.</div><?php else: foreach ($lastPays as $r): ?>
      <div class="kv"><span><a href="<?= e(url('payments/receipt.php?id=' . $r['id'])) ?>"><?= e($r['full_name']) ?></a> <small class="muted ltr"><?= e($r['receipt_no']) ?> · <?= fmt_date($r['paid_on']) ?></small></span><b><?= money($r['amount']) ?></b></div><?php endforeach; endif; ?></div>
    <div class="card"><div class="card-head"><h3>آخر المصروفات</h3></div>
    <?php if (!$lastExp): ?><div class="empty-inline">لا توجد مصروفات.</div><?php else: foreach ($lastExp as $r): ?>
      <div class="kv"><span><a href="<?= e(url('expenses/view.php?id=' . $r['id'])) ?>"><?= e($r['cname']) ?></a> <small class="muted"><?= e(mb_strimwidth($r['description'], 0, 34, '…')) ?> · <?= fmt_date($r['expense_date']) ?></small></span><b><?= money($r['amount']) ?></b></div><?php endforeach; endif; ?></div>
  </div>
</div>
<div>
  <div class="card"><div class="card-head"><h3>الإشغال حسب العقار</h3></div>
  <?php foreach ($occRows as $r): $o = $r['total'] ? round($r['rented'] / $r['total'] * 100) : 0; ?>
    <div class="occ-row"><a href="<?= e(url('properties/view.php?id=' . $r['id'])) ?>"><?= e($r['name']) ?></a><b><?= $o ?>% <small class="muted">(<?= (int)$r['rented'] ?>/<?= (int)$r['total'] ?>)</small></b><div class="progress"><i style="width:<?= $o ?>%"></i></div></div>
  <?php endforeach; ?></div>
  <div class="card"><div class="card-head"><h3>آخر بلاغات الصيانة</h3></div>
  <?php if (!$lastMaint): ?><div class="empty-inline">لا توجد بلاغات.</div><?php else: foreach ($lastMaint as $r): ?>
    <div class="kv"><span><a href="<?= e(url('maintenance/view.php?id=' . $r['id'])) ?>"><?= e($r['title']) ?></a> <small class="muted">وحدة <?= e($r['unit_no'] ?: 'عام') ?></small></span><?= badge('maint_status', $r['status']) ?></div><?php endforeach; endif; ?></div>
  <div class="def-box"><b>تعريفات:</b> «المستحق» حسب تاريخ استحقاق الأقساط، و«المحصّل» حسب تاريخ الدفع الفعلي (الدفعات الصحيحة فقط)، و«المتأخرات» مجموع المتبقي من أقساط فات موعدها حتى اليوم. التأمين لا يدخل في الإيراد.</div>
</div>
</div>
<?php endif;
layout_end();
