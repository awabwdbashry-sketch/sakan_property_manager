<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('reports.view');
sync_overdue();
$type = get('type'); $id = get_int('id');
$from = get('from'); $to = get('to'); if (!valid_date($from)) $from = ''; if (!valid_date($to)) $to = '';
$cfg = ['tenant' => ['i.tenant_id', 'tenants', 'full_name', 'كشف حساب مستأجر'], 'unit' => ['i.unit_id', 'units', 'unit_no', 'كشف حساب وحدة'], 'property' => ['i.property_id', 'properties', 'name', 'كشف حساب عقار']];
if (!isset($cfg[$type]) || $id <= 0) render_error_page('طلب غير صالح', 'اختر نوع الكشف والجهة المطلوبة.', 400);
[$col, $tbl, $nameCol, $ttl] = $cfg[$type];
$ent = q_one("SELECT * FROM $tbl WHERE id = ?", [$id]);
if (!$ent) render_error_page('غير موجود', 'الجهة المطلوبة غير موجودة.', 404);
$subtitle = $ent[$nameCol];
if ($type === 'unit') $subtitle = q_val("SELECT CONCAT(p.name, ' / وحدة ', u.unit_no) FROM units u JOIN properties p ON p.id = u.property_id WHERE u.id = ?", [$id]);

$dw = ''; $dp = [];
$iw = ''; if ($from) { $iw .= ' AND i.due_date >= ?'; $dp[] = $from; } if ($to) { $iw .= ' AND i.due_date <= ?'; $dp[] = $to; }
$insts = q_all("SELECT i.id, i.ref_no, i.due_date, i.amount, t.full_name, u.unit_no FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id WHERE $col = ? AND i.status <> 'cancelled'$iw", array_merge([$id], $dp));
$pp = []; $pw = ''; if ($from) { $pw .= ' AND p.paid_on >= ?'; $pp[] = $from; } if ($to) { $pw .= ' AND p.paid_on <= ?'; $pp[] = $to; }
$pays = q_all("SELECT p.id, p.paid_on, p.amount, p.txn_no, rc.receipt_no, i.ref_no, m.name mname, t.full_name FROM payments p JOIN installments i ON i.id = p.installment_id JOIN tenants t ON t.id = i.tenant_id
               JOIN payment_methods m ON m.id = p.method_id LEFT JOIN receipts rc ON rc.payment_id = p.id WHERE $col = ? AND p.status = 'valid'$pw", array_merge([$id], $pp));
$lines = [];
foreach ($insts as $i) $lines[] = ['d' => $i['due_date'], 'o' => 0, 'desc' => 'استحقاق إيجار ' . $i['ref_no'] . ($type !== 'tenant' ? ' — ' . $i['full_name'] : ''), 'debit' => to_cents($i['amount']), 'credit' => 0];
foreach ($pays as $p) $lines[] = ['d' => $p['paid_on'], 'o' => 1, 'desc' => 'دفعة ' . $p['txn_no'] . ' (' . $p['mname'] . ') — إيصال ' . $p['receipt_no'] . ' عن ' . $p['ref_no'], 'debit' => 0, 'credit' => to_cents($p['amount'])];
usort($lines, fn($a, $b) => [$a['d'], $a['o']] <=> [$b['d'], $b['o']]);
$bal = 0; $td = $tc = 0;
foreach ($lines as &$l) { $bal += $l['debit'] - $l['credit']; $l['bal'] = $bal; $td += $l['debit']; $tc += $l['credit']; } unset($l);

$exps = [];
if ($type !== 'tenant') {
    $ecol = $type === 'unit' ? 'e.unit_id' : 'e.property_id'; $ew = ''; $ep = [$id];
    if ($from) { $ew .= ' AND e.expense_date >= ?'; $ep[] = $from; } if ($to) { $ew .= ' AND e.expense_date <= ?'; $ep[] = $to; }
    $exps = q_all("SELECT e.expense_date, e.expense_no, e.description, c.name cname, e.amount FROM expenses e JOIN expense_categories c ON c.id = e.category_id WHERE $ecol = ? AND e.status = 'paid'$ew ORDER BY e.expense_date", $ep);
}
$te = 0; foreach ($exps as $x) $te += to_cents($x['amount']);

if (get('export') === 'csv') {
    $rows = []; foreach ($lines as $l) $rows[] = [fmt_date($l['d']), $l['desc'], money_plain(cents_to_dec($l['debit'])), money_plain(cents_to_dec($l['credit'])), money_plain(cents_to_dec($l['bal']))];
    $rows[] = ['', 'الإجمالي', money_plain(cents_to_dec($td)), money_plain(cents_to_dec($tc)), money_plain(cents_to_dec($td - $tc))];
    csv_download('sakan_statement_' . $type . '_' . $id . '.csv', ['التاريخ', 'البيان', 'مدين (مستحق)', 'دائن (مدفوع)', 'الرصيد'], $rows);
}
layout_start($ttl, 'reports', ['print' => true]);
$qs = $_GET; $qs['export'] = 'csv';
page_actions('<a class="btn" href="' . e(url('reports/')) . '">التقارير</a> <button class="btn" onclick="window.print()">' . icon('print') . ' طباعة</button> <a class="btn btn-sage" href="?' . e(http_build_query($qs)) . '">' . icon('download') . ' CSV</a>');
?>
<form class="filters" method="get"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= $id ?>">
  <div class="field"><label>من تاريخ</label><input type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary" type="submit">تطبيق</button></form>
<div class="doc-sheet" style="max-width:none">
  <div class="doc-head"><div><h1><?= e($ttl) ?></h1><div><b><?= e($subtitle) ?></b></div><div class="muted"><?= e(setting('org_name')) ?></div></div>
    <div class="doc-meta">تاريخ الإصدار: <?= fmt_date(today()) ?><br>الفترة: <?= $from ? fmt_date($from) : 'من البداية' ?> — <?= $to ? fmt_date($to) : 'حتى اليوم' ?><br>العملة: <?= e(setting('currency')) ?></div></div>
  <?php if (!$lines): ?><div class="empty-inline">لا توجد حركات في هذه الفترة.</div><?php else: ?>
  <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>التاريخ</th><th>البيان</th><th class="amt">مدين (مستحق)</th><th class="amt">دائن (مدفوع)</th><th class="amt">الرصيد</th></tr></thead><tbody>
  <?php foreach ($lines as $l): ?><tr><td><?= fmt_date($l['d']) ?></td><td><?= e($l['desc']) ?></td><td class="amt"><?= $l['debit'] ? money(cents_to_dec($l['debit']), false) : '' ?></td><td class="amt"><?= $l['credit'] ? money(cents_to_dec($l['credit']), false) : '' ?></td><td class="amt"><?= money(cents_to_dec($l['bal']), false) ?></td></tr><?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="2">الإجمالي</td><td class="amt"><?= money(cents_to_dec($td), false) ?></td><td class="amt"><?= money(cents_to_dec($tc), false) ?></td><td class="amt"><?= money(cents_to_dec($td - $tc), false) ?></td></tr></tfoot></table></div>
  <p class="muted">الرصيد الموجب = مبلغ مستحق على المستأجر لم يُسدَّد بعد (بما فيه أقساط لم يحن موعدها). الأقساط الملغاة والدفعات الملغاة مستبعدة.</p><?php endif; ?>
  <?php if ($type !== 'tenant'): ?><h3 style="margin-top:24px">المصروفات المدفوعة</h3>
  <?php if (!$exps): ?><div class="empty-inline">لا توجد مصروفات.</div><?php else: ?><div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>التاريخ</th><th>الرقم</th><th>التصنيف</th><th>الوصف</th><th class="amt">المبلغ</th></tr></thead><tbody>
  <?php foreach ($exps as $x): ?><tr><td><?= fmt_date($x['expense_date']) ?></td><td class="ltr"><?= e($x['expense_no']) ?></td><td><?= e($x['cname']) ?></td><td><?= e($x['description']) ?></td><td class="amt"><?= money($x['amount'], false) ?></td></tr><?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="4">إجمالي المصروفات</td><td class="amt"><?= money(cents_to_dec($te), false) ?></td></tr></tfoot></table></div><?php endif; ?>
  <div class="kv" style="margin-top:14px"><span>صافي التدفق النقدي للفترة (المحصّل − المصروفات)</span><b><?= money(cents_to_dec($tc - $te)) ?></b></div><?php endif; ?>
</div>
<?php layout_end();
