<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('reports.view');
expire_contracts(); sync_overdue();
$cat = report_catalog();
$type = get('type');
if (!isset($cat[$type])) render_error_page('تقرير غير معروف', 'نوع التقرير المطلوب غير موجود.', 404);
[$title, $desc, $filters] = $cat[$type];

$f = ['property_id' => get_int('property_id'), 'from' => get('from'), 'to' => get('to'), 'status_unit' => get('status_unit'), 'status_contract' => get('status_contract'),
      'status_inst' => get('status_inst'), 'status_maint' => get('status_maint'), 'method_id' => get_int('method_id'), 'category_id' => get_int('category_id'), 'has_range' => false];
if (!valid_date($f['from']) || !valid_date($f['to']) || $f['from'] > $f['to']) {
    if (in_array('range_overlap', $filters, true) && ($f['from'] !== '' || $f['to'] !== '')) { $f['from'] = ''; $f['to'] = ''; }
    $defFrom = in_array($type, ['cashflow', 'monthly'], true) ? date('Y-01-01') : date('Y-m-01');
    $defTo = in_array($type, ['cashflow', 'monthly'], true) ? date('Y-12-31') : date('Y-m-t');
    if (!in_array('range_overlap', $filters, true)) { $f['from'] = $defFrom; $f['to'] = $defTo; }
} elseif (in_array('range_overlap', $filters, true)) $f['has_range'] = true;
if (in_array('range_overlap', $filters, true) && $f['from'] !== '' && $f['to'] !== '') $f['has_range'] = true;

$R = report_data($type, $f);

if (get('export') === 'csv') {
    $header = array_values($R['cols']);
    $out = [];
    foreach ($R['rows'] as $r) { $row = []; foreach ($R['cols'] as $k => $_) $row[] = in_array($k, $R['money'], true) ? money_plain($r[$k] ?? 0) : ($r[$k] ?? ''); $out[] = $row; }
    if ($R['totals']) { $row = []; $first = true; foreach ($R['cols'] as $k => $_) { $row[] = $first ? 'الإجمالي' : (isset($R['totals'][$k]) ? (in_array($k, $R['money'], true) ? money_plain($R['totals'][$k]) : $R['totals'][$k]) : ''); $first = false; } $out[] = $row; }
    audit('report.export', 'report', null, ['type' => $type]);
    csv_download('sakan_' . $type . '_' . date('Ymd') . '.csv', $header, $out);
}

$props = q_all("SELECT id, name FROM properties ORDER BY name");
layout_start($title, 'reports', ['print' => true]);
$qs = $_GET; $qs['export'] = 'csv';
page_actions('<a class="btn" href="' . e(url('reports/')) . '">كل التقارير</a> <button class="btn" onclick="window.print()">' . icon('print') . ' طباعة</button> <a class="btn btn-sage" href="?' . e(http_build_query($qs)) . '">' . icon('download') . ' تصدير CSV (Excel)</a>');
?>
<form class="filters" method="get"><input type="hidden" name="type" value="<?= e($type) ?>">
<?php if (in_array('property', $filters, true)): ?><div class="field"><label>العقار</label><select name="property_id"><?= opts($props, 'id', 'name', $f['property_id'], 'كل العقارات') ?></select></div><?php endif; ?>
<?php if (in_array('range', $filters, true) || in_array('range_overlap', $filters, true)): ?>
  <div class="field"><label>من تاريخ</label><input type="date" name="from" value="<?= e($f['from']) ?>"></div><div class="field"><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($f['to']) ?>"></div><?php endif; ?>
<?php if (in_array('status_unit', $filters, true)): ?><div class="field"><label>حالة الوحدة</label><select name="status_unit"><?= opts_assoc(labels('unit_status'), $f['status_unit'], 'الكل') ?></select></div><?php endif; ?>
<?php if (in_array('status_contract', $filters, true)): ?><div class="field"><label>حالة العقد</label><select name="status_contract"><?= opts_assoc(labels('contract_status') + ['soon' => 'قريبة الانتهاء'], $f['status_contract'], 'الكل') ?></select></div><?php endif; ?>
<?php if (in_array('status_inst', $filters, true)): ?><div class="field"><label>حالة الاستحقاق</label><select name="status_inst"><?= opts_assoc(['due' => 'مستحق', 'partial' => 'مدفوع جزئياً', 'paid' => 'مدفوع', 'overdue' => 'متأخر'], $f['status_inst'], 'الكل') ?></select></div><?php endif; ?>
<?php if (in_array('status_maint', $filters, true)): ?><div class="field"><label>حالة البلاغ</label><select name="status_maint"><?= opts_assoc(labels('maint_status'), $f['status_maint'], 'الكل') ?></select></div><?php endif; ?>
<?php if (in_array('method', $filters, true)): ?><div class="field"><label>وسيلة الدفع</label><select name="method_id"><?= opts(q_all("SELECT id, name FROM payment_methods ORDER BY sort_order"), 'id', 'name', $f['method_id'], 'الكل') ?></select></div><?php endif; ?>
<?php if (in_array('category', $filters, true)): ?><div class="field"><label>التصنيف</label><select name="category_id"><?= opts(q_all("SELECT id, name FROM expense_categories ORDER BY sort_order"), 'id', 'name', $f['category_id'], 'الكل') ?></select></div><?php endif; ?>
<button class="btn btn-primary" type="submit"><?= icon('search') ?> عرض</button>
</form>
<div class="card">
  <div class="card-head"><h2><?= e($title) ?></h2><span class="muted"><?= e(setting('org_name')) ?><?= $f['from'] && !in_array($type, ['properties', 'occupancy', 'arrears']) ? ' · من ' . fmt_date($f['from']) . ' إلى ' . fmt_date($f['to']) : '' ?> · العملة: <?= e(setting('currency')) ?></span></div>
  <?php if (!empty($R['chart'])): ?><?= chart_legend($R['chart']['series']) ?><?= svg_bar_chart($R['chart']['labels'], $R['chart']['series'], $title) ?><hr><?php endif; ?>
  <?php if (!$R['rows']): ?><div class="empty-inline">لا توجد بيانات مطابقة للتصفية الحالية.</div><?php else: ?>
  <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><?php foreach ($R['cols'] as $k => $lab): ?><th class="<?= in_array($k, $R['money'], true) ? 'amt' : '' ?>"><?= e($lab) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($R['rows'] as $r): ?><tr><?php foreach ($R['cols'] as $k => $_): ?><td class="<?= in_array($k, $R['money'], true) ? 'amt' : '' ?>"><?= in_array($k, $R['money'], true) ? money($r[$k] ?? 0, false) : e((string)($r[$k] ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
  <?php if ($R['totals']): ?><tfoot><tr><?php $first = true; foreach ($R['cols'] as $k => $_): ?><td class="<?= in_array($k, $R['money'], true) ? 'amt' : '' ?>"><?= $first ? 'الإجمالي' : (isset($R['totals'][$k]) ? (in_array($k, $R['money'], true) ? money($R['totals'][$k], false) : e((string)$R['totals'][$k])) : '') ?></td><?php $first = false; endforeach; ?></tr></tfoot><?php endif; ?></table></div>
  <?php endif; ?>
  <?php if ($R['notes']): ?><div class="def-box" style="margin-top:16px"><b>ملاحظة:</b> <?= e($R['notes']) ?></div><?php endif; ?>
</div>
<?php layout_end();
