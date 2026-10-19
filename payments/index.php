<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('payments.view');
expire_contracts(); sync_overdue();

$status = get('status'); $pid = get_int('property_id'); $qtext = get('q'); $from = get('from'); $to = get('to'); $cid = get_int('contract_id'); $tid = get_int('tenant_id');
$where = ["i.status <> 'cancelled'"]; $par = [];
if ($status === 'overdue') $where[] = SQL_OVERDUE;
elseif (in_array($status, ['due', 'partial', 'paid'], true)) { $where[] = 'i.status = ?'; $par[] = $status; }
elseif ($status === 'open') $where[] = 'i.remaining > 0';
else $status = '';
if ($pid) { $where[] = 'i.property_id = ?'; $par[] = $pid; }
if ($cid) { $where[] = 'i.contract_id = ?'; $par[] = $cid; }
if ($tid) { $where[] = 'i.tenant_id = ?'; $par[] = $tid; }
if ($qtext !== '') { $where[] = '(t.full_name LIKE ? OR i.ref_no LIKE ? OR u.unit_no LIKE ?)'; array_push($par, "%$qtext%", "%$qtext%", "%$qtext%"); }
if (valid_date($from)) { $where[] = 'i.due_date >= ?'; $par[] = $from; } else $from = '';
if (valid_date($to)) { $where[] = 'i.due_date <= ?'; $par[] = $to; } else $to = '';
$W = implode(' AND ', $where);
$J = "FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id";
$pg = paginate("SELECT COUNT(*) $J WHERE $W",
  "SELECT i.*, t.full_name, u.unit_no, p.name pname $J WHERE $W ORDER BY i.due_date, i.id", $par, max(1, get_int('page')));
$sum = q_one("SELECT COALESCE(SUM(i.amount),0) a, COALESCE(SUM(i.paid_amount),0) p, COALESCE(SUM(i.remaining),0) r $J WHERE $W", $par);
$props = q_all("SELECT id, name FROM properties ORDER BY name");

layout_start('الاستحقاقات والتحصيل', 'payments');
page_actions('<a class="btn" href="' . e(url('payments/history.php')) . '">سجل الدفعات والإيصالات</a>');
?>
<div class="stats">
  <?= stat_card('إجمالي المستحق (حسب التصفية)', money($sum['a']), '', 'amber') ?>
  <?= stat_card('المدفوع', money($sum['p']), '', 'sage') ?>
  <?= stat_card('المتبقي', money($sum['r']), '', 'red') ?>
</div>
<form class="filters" method="get">
  <div class="field grow"><label>بحث</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="المستأجر أو المرجع أو الوحدة"></div>
  <div class="field"><label>العقار</label><select name="property_id" data-auto><?= opts($props, 'id', 'name', $pid, 'كل العقارات') ?></select></div>
  <div class="field"><label>الحالة</label><select name="status" data-auto><?= opts_assoc(['open' => 'غير مسددة', 'due' => 'مستحق', 'partial' => 'مدفوع جزئياً', 'overdue' => 'متأخر', 'paid' => 'مدفوع'], $status, 'الكل') ?></select></div>
  <div class="field"><label>من تاريخ</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <?php if ($cid): ?><input type="hidden" name="contract_id" value="<?= $cid ?>"><?php endif; ?><?php if ($tid): ?><input type="hidden" name="tenant_id" value="<?= $tid ?>"><?php endif; ?>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('payments/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?>
  <?= empty_state('لا توجد استحقاقات', 'تُولَّد الاستحقاقات تلقائياً عند تفعيل العقود. فعّل عقداً أو عدّل التصفية.', can('contracts.manage') ? 'العقود' : '', 'contracts/') ?>
<?php else: ?>
<div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>المرجع</th><th>المستأجر</th><th>العقار / الوحدة</th><th>الاستحقاق</th><th class="amt">المبلغ</th><th class="amt">المدفوع</th><th class="amt">المتبقي</th><th>الحالة</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $i): ?>
<tr><td class="ltr"><a href="<?= e(url('payments/view.php?id=' . $i['id'])) ?>"><?= e($i['ref_no']) ?></a></td><td><?= e($i['full_name']) ?></td><td><?= e($i['pname']) ?> / <?= e($i['unit_no']) ?></td>
<td><?= fmt_date($i['due_date']) ?></td><td class="amt"><?= money($i['amount']) ?></td><td class="amt"><?= money($i['paid_amount']) ?></td><td class="amt"><b><?= money($i['remaining']) ?></b></td>
<td><?= badge('inst_status', $i['status']) ?><?php if ($i['status'] === 'overdue' && to_cents($i['paid_amount']) > 0) echo '<br><small class="muted">دفع جزئي</small>'; ?></td>
<td class="acts"><?php if (can('payments.collect') && to_cents($i['remaining']) > 0): ?><a class="btn btn-sm btn-primary" href="<?= e(url('payments/view.php?id=' . $i['id'] . '#collect')) ?>">تحصيل</a><?php endif; ?><a class="btn btn-sm" href="<?= e(url('payments/view.php?id=' . $i['id'])) ?>">التفاصيل</a></td></tr>
<?php endforeach; ?></tbody></table></div><?= pager($pg) ?></div>
<?php endif;
layout_end();
