<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('tenants.view');
sync_overdue();
$qtext = get('q'); $status = get('status', 'active'); $only = get('only');
$where = ['1=1']; $par = [];
if ($qtext !== '') { $where[] = '(t.full_name LIKE ? OR t.phone LIKE ?)'; $par[] = "%$qtext%"; $par[] = "%$qtext%"; }
if (in_array($status, ['active', 'inactive'], true)) { $where[] = 't.status = ?'; $par[] = $status; } else $status = '';
if ($only === 'overdue') $where[] = 'EXISTS (SELECT 1 FROM installments i WHERE i.tenant_id = t.id AND ' . SQL_OVERDUE . ')';
if ($only === 'current') $where[] = "EXISTS (SELECT 1 FROM contracts c WHERE c.tenant_id = t.id AND c.status = 'active')";
$W = implode(' AND ', $where);
$pg = paginate("SELECT COUNT(*) FROM tenants t WHERE $W",
  "SELECT t.*, (SELECT COUNT(*) FROM contracts c WHERE c.tenant_id = t.id AND c.status = 'active') active_n,
     (SELECT COALESCE(SUM(i.remaining),0) FROM installments i WHERE i.tenant_id = t.id AND " . SQL_OVERDUE . ") overdue_amt
   FROM tenants t WHERE $W ORDER BY t.full_name", $par, max(1, get_int('page')));
layout_start('المستأجرون', 'tenants');
if (can('tenants.manage')) page_actions('<a class="btn btn-primary" href="' . e(url('tenants/form.php')) . '">' . icon('plus') . ' إضافة مستأجر</a>');
?>
<form class="filters" method="get">
  <div class="field grow"><label>بحث</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="الاسم أو رقم الهاتف"></div>
  <div class="field"><label>الحالة</label><select name="status"><?= opts_assoc(labels('tenant_status'), $status, 'الكل') ?></select></div>
  <div class="field"><label>عرض</label><select name="only"><?= opts_assoc(['current' => 'لديهم عقد نشط', 'overdue' => 'لديهم متأخرات'], $only, 'الكل') ?></select></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('tenants/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?>
  <?= empty_state('لا يوجد مستأجرون', $qtext !== '' ? 'لا توجد نتائج مطابقة للبحث.' : 'سجّل أول مستأجر لتتمكن من إنشاء العقود وتحصيل الإيجارات.', can('tenants.manage') && $qtext === '' ? 'إضافة مستأجر' : '', 'tenants/form.php') ?>
<?php else: ?>
<div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>الاسم</th><th>الهاتف</th><th>البريد</th><th>العقود النشطة</th><th class="amt">المتأخرات</th><th>الحالة</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $t): ?>
<tr><td><a href="<?= e(url('tenants/view.php?id=' . $t['id'])) ?>"><b><?= e($t['full_name']) ?></b></a></td><td class="ltr"><?= e($t['phone']) ?></td><td class="ltr"><?= e($t['email']) ?: '—' ?></td>
<td><?= (int)$t['active_n'] ?></td><td class="amt"><?= $t['overdue_amt'] > 0 ? '<span style="color:var(--red);font-weight:700">' . money($t['overdue_amt']) . '</span>' : '—' ?></td>
<td><?= badge('tenant_status', $t['status']) ?></td>
<td class="acts"><a class="btn btn-sm" href="<?= e(url('tenants/view.php?id=' . $t['id'])) ?>">الملف</a><?php if (can('tenants.manage')): ?><a class="btn btn-sm" href="<?= e(url('tenants/form.php?id=' . $t['id'])) ?>">تعديل</a><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?= pager($pg) ?></div>
<?php endif;
layout_end();
