<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('contracts.view');
expire_contracts();
$status = get('status'); $pid = get_int('property_id'); $qtext = get('q'); $soon = get('soon') === '1';
$where = ['1=1']; $par = [];
if (isset(labels('contract_status')[$status])) { $where[] = 'c.status = ?'; $par[] = $status; } else $status = '';
if ($pid) { $where[] = 'c.property_id = ?'; $par[] = $pid; }
if ($qtext !== '') { $where[] = '(c.contract_no LIKE ? OR t.full_name LIKE ? OR u.unit_no LIKE ?)'; array_push($par, "%$qtext%", "%$qtext%", "%$qtext%"); }
if ($soon) { $d = (int)setting('alert_contract_days', 30); $where[] = "c.status = 'active' AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $d DAY)"; }
$W = implode(' AND ', $where);
$pg = paginate("SELECT COUNT(*) FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id WHERE $W",
  "SELECT c.*, t.full_name, u.unit_no, p.name pname FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id WHERE $W ORDER BY c.id DESC", $par, max(1, get_int('page')));
$props = q_all("SELECT id, name FROM properties ORDER BY name");
layout_start('العقود الإيجارية', 'contracts');
if (can('contracts.manage')) page_actions('<a class="btn btn-primary" href="' . e(url('contracts/form.php')) . '">' . icon('plus') . ' عقد جديد</a>');
?>
<form class="filters" method="get">
  <div class="field grow"><label>بحث</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="رقم العقد أو المستأجر أو الوحدة"></div>
  <div class="field"><label>العقار</label><select name="property_id" data-auto><?= opts($props, 'id', 'name', $pid, 'كل العقارات') ?></select></div>
  <div class="field"><label>الحالة</label><select name="status" data-auto><?= opts_assoc(labels('contract_status'), $status, 'كل الحالات') ?></select></div>
  <div class="field"><label>قرب الانتهاء</label><select name="soon" data-auto><?= opts_assoc(['1' => 'تنتهي قريباً'], $soon ? '1' : '', 'الكل') ?></select></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('contracts/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?>
  <?= empty_state('لا توجد عقود', 'أنشئ عقداً بين مستأجر ووحدة شاغرة، ثم فعّله لتُولَّد الاستحقاقات تلقائياً.', can('contracts.manage') ? 'عقد جديد' : '', 'contracts/form.php') ?>
<?php else: ?>
<div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>رقم العقد</th><th>المستأجر</th><th>العقار / الوحدة</th><th>الفترة</th><th class="amt">الإيجار الشهري</th><th>الدورية</th><th>الحالة</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $c): $left = days_between(today(), $c['end_date']); ?>
<tr><td><a class="ltr" href="<?= e(url('contracts/view.php?id=' . $c['id'])) ?>"><b><?= e($c['contract_no']) ?></b></a></td><td><?= e($c['full_name']) ?></td><td><?= e($c['pname']) ?> / <?= e($c['unit_no']) ?></td>
<td><?= fmt_date($c['start_date']) ?> ← <?= fmt_date($c['end_date']) ?><?php if ($c['status'] === 'active' && $left >= 0 && $left <= (int)setting('alert_contract_days', 30)): ?> <span class="badge b-amber">باقي <?= $left ?> يوم</span><?php endif; ?></td>
<td class="amt"><?= money($c['rent_amount']) ?></td><td><?= label('period', $c['period']) ?></td><td><?= badge('contract_status', $c['status']) ?></td>
<td class="acts"><a class="btn btn-sm" href="<?= e(url('contracts/view.php?id=' . $c['id'])) ?>">عرض</a></td></tr>
<?php endforeach; ?></tbody></table></div><?= pager($pg) ?></div>
<?php endif;
layout_end();
