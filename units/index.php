<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('units.view');

$pid = get_int('property_id'); $status = get('status'); $qtext = get('q'); $type = get('type');
$where = ['u.is_archived = 0']; $par = [];
if ($pid) { $where[] = 'u.property_id = ?'; $par[] = $pid; }
if (isset(labels('unit_status')[$status])) { $where[] = 'u.status = ?'; $par[] = $status; } else { $status = ''; }
if ($qtext !== '') { $where[] = 'u.unit_no LIKE ?'; $par[] = '%' . $qtext . '%'; }
if ($type !== '') { $where[] = 'u.unit_type = ?'; $par[] = $type; }
$W = implode(' AND ', $where);
$pg = paginate("SELECT COUNT(*) FROM units u WHERE $W",
  "SELECT u.*, p.name pname, p.code pcode,
     (SELECT t.full_name FROM contracts c JOIN tenants t ON t.id = c.tenant_id WHERE c.unit_id = u.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) tenant_name,
     (SELECT COUNT(*) FROM installments i WHERE i.unit_id = u.id AND " . SQL_OVERDUE . ") overdue_n
   FROM units u JOIN properties p ON p.id = u.property_id WHERE $W ORDER BY p.name, u.floor, u.unit_no", $par, max(1, get_int('page')));
sync_overdue();
$props = q_all("SELECT id, name FROM properties WHERE status = 'active' ORDER BY name");

layout_start('الوحدات السكنية', 'units');
if (can('units.manage')) page_actions('<a class="btn btn-primary" href="' . e(url('units/form.php' . ($pid ? '?property_id=' . $pid : ''))) . '">' . icon('plus') . ' إضافة وحدة</a>');
?>
<form class="filters" method="get">
  <div class="field"><label>العقار</label><select name="property_id" data-auto><?= opts($props, 'id', 'name', $pid, 'كل العقارات') ?></select></div>
  <div class="field"><label>الحالة</label><select name="status" data-auto><?= opts_assoc(labels('unit_status'), $status, 'كل الحالات') ?></select></div>
  <div class="field"><label>النوع</label><select name="type"><?= opts_assoc(labels('unit_types'), $type, 'كل الأنواع') ?></select></div>
  <div class="field"><label>رقم الوحدة</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="بحث برقم الوحدة"></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('units/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?>
  <?= empty_state('لا توجد وحدات', 'لم يتم العثور على وحدات مطابقة. أضف وحدة جديدة أو عدّل عوامل التصفية.', can('units.manage') ? 'إضافة وحدة' : '', 'units/form.php') ?>
<?php else: ?>
<div class="card"><div class="table-wrap"><table class="tbl">
<thead><tr><th>العقار</th><th>الوحدة</th><th>الطابق</th><th>النوع</th><th>الغرف</th><th class="amt">الإيجار الأساسي</th><th>الحالة</th><th>المستأجر الحالي</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $u): ?>
<tr>
  <td><?= e($u['pname']) ?></td>
  <td><a href="<?= e(url('units/view.php?id=' . $u['id'])) ?>"><b><?= e($u['unit_no']) ?></b></a></td>
  <td><?= e($u['floor']) ?: '—' ?></td><td><?= e($u['unit_type']) ?: '—' ?></td><td><?= $u['rooms'] !== null ? (int)$u['rooms'] : '—' ?></td>
  <td class="amt"><?= money($u['base_rent']) ?></td>
  <td><?= badge('unit_status', $u['status']) ?><?php if ($u['overdue_n'] > 0): ?> <span class="badge b-red">متأخرة</span><?php endif; ?></td>
  <td><?= e($u['tenant_name']) ?: '—' ?></td>
  <td class="acts"><a class="btn btn-sm" href="<?= e(url('units/view.php?id=' . $u['id'])) ?>">عرض</a><?php if (can('units.manage')): ?><a class="btn btn-sm" href="<?= e(url('units/form.php?id=' . $u['id'])) ?>">تعديل</a><?php endif; ?></td>
</tr>
<?php endforeach; ?></tbody></table></div><?= pager($pg) ?></div>
<?php endif;
layout_end();
