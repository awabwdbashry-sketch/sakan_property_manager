<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('payments.view');
$from = get('from'); $to = get('to'); $pid = get_int('property_id'); $qtext = get('q'); $st = get('status', 'valid'); $mid = get_int('method_id');
$where = ['1=1']; $par = [];
if (valid_date($from)) { $where[] = 'pay.paid_on >= ?'; $par[] = $from; } else $from = '';
if (valid_date($to)) { $where[] = 'pay.paid_on <= ?'; $par[] = $to; } else $to = '';
if ($pid) { $where[] = 'i.property_id = ?'; $par[] = $pid; }
if ($mid) { $where[] = 'pay.method_id = ?'; $par[] = $mid; }
if (in_array($st, ['valid', 'void'], true)) { $where[] = 'pay.status = ?'; $par[] = $st; } else $st = '';
if ($qtext !== '') { $where[] = '(t.full_name LIKE ? OR pay.txn_no LIKE ? OR rc.receipt_no LIKE ? OR pay.reference_no LIKE ?)'; array_push($par, "%$qtext%", "%$qtext%", "%$qtext%", "%$qtext%"); }
$W = implode(' AND ', $where);
$J = "FROM payments pay JOIN installments i ON i.id = pay.installment_id JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id JOIN payment_methods m ON m.id = pay.method_id LEFT JOIN receipts rc ON rc.payment_id = pay.id";
$pg = paginate("SELECT COUNT(*) $J WHERE $W", "SELECT pay.*, t.full_name, u.unit_no, p.name pname, m.name mname, rc.receipt_no $J WHERE $W ORDER BY pay.paid_on DESC, pay.id DESC", $par, max(1, get_int('page')));
$total = q_val("SELECT COALESCE(SUM(CASE WHEN pay.status='valid' THEN pay.amount END),0) $J WHERE $W", $par);
$props = q_all("SELECT id, name FROM properties ORDER BY name"); $methods = q_all("SELECT id, name FROM payment_methods ORDER BY sort_order");
layout_start('سجل الدفعات والإيصالات', 'payments');
page_actions('<a class="btn" href="' . e(url('payments/')) . '">رجوع للاستحقاقات</a>');
?>
<form class="filters" method="get">
  <div class="field grow"><label>بحث</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="المستأجر أو رقم العملية أو الإيصال"></div>
  <div class="field"><label>العقار</label><select name="property_id"><?= opts($props, 'id', 'name', $pid, 'الكل') ?></select></div>
  <div class="field"><label>الوسيلة</label><select name="method_id"><?= opts($methods, 'id', 'name', $mid, 'الكل') ?></select></div>
  <div class="field"><label>الحالة</label><select name="status"><?= opts_assoc(labels('pay_status'), $st, 'الكل') ?></select></div>
  <div class="field"><label>من</label><input type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label>إلى</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('payments/history.php')) ?>">مسح</a>
</form>
<div class="stats"><?= stat_card('إجمالي المحصّل (الدفعات الصحيحة ضمن التصفية)', money($total), '', 'sage') ?></div>
<?php if (!$pg['rows']): ?><?= empty_state('لا توجد دفعات', 'لم تُسجَّل دفعات مطابقة بعد.') ?><?php else: ?>
<div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>التاريخ</th><th>رقم العملية</th><th>الإيصال</th><th>المستأجر</th><th>العقار / الوحدة</th><th class="amt">المبلغ</th><th>الوسيلة</th><th>الحالة</th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $p): ?><tr style="<?= $p['status'] === 'void' ? 'opacity:.6' : '' ?>"><td><?= fmt_date($p['paid_on']) ?></td><td class="ltr"><a href="<?= e(url('payments/view.php?id=' . $p['installment_id'])) ?>"><?= e($p['txn_no']) ?></a></td>
<td class="ltr"><a target="_blank" href="<?= e(url('payments/receipt.php?id=' . $p['id'])) ?>"><?= e($p['receipt_no']) ?></a></td><td><?= e($p['full_name']) ?></td><td><?= e($p['pname']) ?> / <?= e($p['unit_no']) ?></td><td class="amt"><?= money($p['amount']) ?></td><td><?= e($p['mname']) ?></td><td><?= badge('pay_status', $p['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?= pager($pg) ?></div><?php endif;
layout_end();
