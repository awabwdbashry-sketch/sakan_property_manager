<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('maintenance.view');
$pid = get_int('property_id'); $st = get('status'); $pr = get('priority'); $qtext = get('q'); $open = get('open') === '1';
$where = ['1=1']; $par = [];
if ($pid) { $where[] = 'm.property_id = ?'; $par[] = $pid; }
if (isset(labels('maint_status')[$st])) { $where[] = 'm.status = ?'; $par[] = $st; } else $st = '';
if (isset(labels('priority')[$pr])) { $where[] = 'm.priority = ?'; $par[] = $pr; } else $pr = '';
if ($open) $where[] = "m.status IN ('new','in_progress','waiting')";
if ($qtext !== '') { $where[] = '(m.request_no LIKE ? OR m.title LIKE ? OR u.unit_no LIKE ?)'; array_push($par, "%$qtext%", "%$qtext%", "%$qtext%"); }
$W = implode(' AND ', $where);
$J = "FROM maintenance_requests m JOIN properties p ON p.id = m.property_id LEFT JOIN units u ON u.id = m.unit_id LEFT JOIN users us ON us.id = m.assigned_to LEFT JOIN expenses ex ON ex.maintenance_id = m.id AND ex.status = 'paid'";
$pg = paginate("SELECT COUNT(*) $J WHERE $W",
  "SELECT m.*, p.name pname, u.unit_no, us.full_name assignee, ex.amount actual_cost $J WHERE $W ORDER BY FIELD(m.status,'new','in_progress','waiting','completed','cancelled'), FIELD(m.priority,'urgent','high','medium','low'), m.id DESC", $par, max(1, get_int('page')));
$props = q_all("SELECT id, name FROM properties ORDER BY name");
layout_start('الصيانة والبلاغات', 'maintenance');
if (can('maintenance.manage')) page_actions('<a class="btn btn-primary" href="' . e(url('maintenance/form.php')) . '">' . icon('plus') . ' بلاغ جديد</a>');
?>
<form class="filters" method="get">
  <div class="field grow"><label>بحث</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="رقم البلاغ أو العنوان أو الوحدة"></div>
  <div class="field"><label>العقار</label><select name="property_id" data-auto><?= opts($props, 'id', 'name', $pid, 'الكل') ?></select></div>
  <div class="field"><label>الحالة</label><select name="status" data-auto><?= opts_assoc(labels('maint_status'), $st, 'الكل') ?></select></div>
  <div class="field"><label>الأولوية</label><select name="priority" data-auto><?= opts_assoc(labels('priority'), $pr, 'الكل') ?></select></div>
  <div class="field"><label>عرض</label><select name="open" data-auto><?= opts_assoc(['1' => 'المفتوحة فقط'], $open ? '1' : '', 'الكل') ?></select></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('maintenance/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?><?= empty_state('لا توجد بلاغات صيانة', 'سجّل بلاغاً عند ورود مشكلة في وحدة أو مرفق مشترك لتتابع معالجتها وتكلفتها.', can('maintenance.manage') ? 'بلاغ جديد' : '', 'maintenance/form.php') ?>
<?php else: ?><div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>الرقم</th><th>المشكلة</th><th>العقار / الوحدة</th><th>التاريخ</th><th>الأولوية</th><th>الحالة</th><th>المسؤول</th><th class="amt">التقدير / الفعلي</th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $m): ?><tr><td class="ltr"><a href="<?= e(url('maintenance/view.php?id=' . $m['id'])) ?>"><?= e($m['request_no']) ?></a></td><td><?= e($m['title']) ?></td><td><?= e($m['pname']) ?> / <?= e($m['unit_no']) ?: 'عام' ?></td><td><?= fmt_date($m['reported_on']) ?></td>
<td><?= badge('priority', $m['priority']) ?></td><td><?= badge('maint_status', $m['status']) ?></td><td><?= e($m['assignee']) ?: '—' ?></td>
<td class="amt"><?= money($m['estimated_cost'], false) ?> / <b><?= $m['actual_cost'] !== null ? money($m['actual_cost'], false) : '—' ?></b></td></tr><?php endforeach; ?>
</tbody></table></div><?= pager($pg) ?></div><?php endif;
layout_end();
