<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('expenses.view');
$pid = get_int('property_id'); $cat = get_int('category_id'); $from = get('from'); $to = get('to'); $qtext = get('q'); $st = get('status', 'paid');
$where = ['1=1']; $par = [];
if ($pid) { $where[] = 'e.property_id = ?'; $par[] = $pid; }
if ($cat) { $where[] = 'e.category_id = ?'; $par[] = $cat; }
if (valid_date($from)) { $where[] = 'e.expense_date >= ?'; $par[] = $from; } else $from = '';
if (valid_date($to)) { $where[] = 'e.expense_date <= ?'; $par[] = $to; } else $to = '';
if (in_array($st, ['paid', 'cancelled'], true)) { $where[] = 'e.status = ?'; $par[] = $st; } else $st = '';
if ($qtext !== '') { $where[] = '(e.expense_no LIKE ? OR e.payee LIKE ? OR e.description LIKE ?)'; array_push($par, "%$qtext%", "%$qtext%", "%$qtext%"); }
$W = implode(' AND ', $where);
$J = "FROM expenses e JOIN properties p ON p.id = e.property_id JOIN expense_categories c ON c.id = e.category_id LEFT JOIN units u ON u.id = e.unit_id";
$pg = paginate("SELECT COUNT(*) $J WHERE $W", "SELECT e.*, p.name pname, c.name cname, u.unit_no $J WHERE $W ORDER BY e.expense_date DESC, e.id DESC", $par, max(1, get_int('page')));
$total = q_val("SELECT COALESCE(SUM(CASE WHEN e.status='paid' THEN e.amount END),0) $J WHERE $W", $par);
$props = q_all("SELECT id, name FROM properties ORDER BY name"); $cats = q_all("SELECT id, name FROM expense_categories ORDER BY sort_order, id");
layout_start('المصروفات', 'expenses');
if (can('expenses.manage')) page_actions('<a class="btn btn-primary" href="' . e(url('expenses/form.php')) . '">' . icon('plus') . ' إضافة مصروف</a>');
?>
<div class="stats"><?= stat_card('إجمالي المصروفات المدفوعة (حسب التصفية)', money($total), '', 'clay') ?></div>
<form class="filters" method="get">
  <div class="field grow"><label>بحث</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="الرقم أو المستفيد أو الوصف"></div>
  <div class="field"><label>العقار</label><select name="property_id" data-auto><?= opts($props, 'id', 'name', $pid, 'الكل') ?></select></div>
  <div class="field"><label>التصنيف</label><select name="category_id" data-auto><?= opts($cats, 'id', 'name', $cat, 'الكل') ?></select></div>
  <div class="field"><label>الحالة</label><select name="status" data-auto><?= opts_assoc(labels('exp_status'), $st, 'الكل') ?></select></div>
  <div class="field"><label>من</label><input type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label>إلى</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('expenses/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?><?= empty_state('لا توجد مصروفات', 'سجّل مصروفات العقارات (صيانة، نظافة، خدمات...) لتظهر في التقارير وصافي التدفق.', can('expenses.manage') ? 'إضافة مصروف' : '', 'expenses/form.php') ?>
<?php else: ?><div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>الرقم</th><th>التاريخ</th><th>العقار / الوحدة</th><th>التصنيف</th><th>المستفيد</th><th>الوصف</th><th class="amt">المبلغ</th><th>الحالة</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $x): ?><tr style="<?= $x['status'] === 'cancelled' ? 'opacity:.6' : '' ?>"><td class="ltr"><a href="<?= e(url('expenses/view.php?id=' . $x['id'])) ?>"><?= e($x['expense_no']) ?></a></td><td><?= fmt_date($x['expense_date']) ?></td>
<td><?= e($x['pname']) ?><?= $x['unit_no'] ? ' / ' . e($x['unit_no']) : '' ?></td><td><?= e($x['cname']) ?></td><td><?= e($x['payee']) ?: '—' ?></td><td><?= e(mb_strimwidth($x['description'], 0, 60, '…')) ?></td>
<td class="amt"><?= money($x['amount']) ?></td><td><?= badge('exp_status', $x['status']) ?></td><td class="acts"><a class="btn btn-sm" href="<?= e(url('expenses/view.php?id=' . $x['id'])) ?>">عرض</a></td></tr><?php endforeach; ?>
</tbody></table></div><?= pager($pg) ?></div><?php endif;
layout_end();
