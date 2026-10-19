<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('tenants.view');
sync_overdue();
$id = get_int('id');
$t = q_one("SELECT * FROM tenants WHERE id = ?", [$id]);
if (!$t) render_error_page('غير موجود', 'المستأجر المطلوب غير موجود.', 404);
$contracts = q_all("SELECT c.*, u.unit_no, p.name pname FROM contracts c JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id WHERE c.tenant_id = ? ORDER BY c.start_date DESC", [$id]);
$insts = q_all("SELECT i.*, u.unit_no FROM installments i JOIN units u ON u.id = i.unit_id WHERE i.tenant_id = ? AND i.status <> 'cancelled' AND (i.remaining > 0 OR i.due_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) ORDER BY i.due_date DESC LIMIT 40", [$id]);
$tot = q_one("SELECT COALESCE(SUM(CASE WHEN i.status <> 'cancelled' AND i.due_date <= CURDATE() THEN i.amount END),0) due_to_date,
                     COALESCE(SUM(CASE WHEN " . SQL_OVERDUE . " THEN i.remaining END),0) overdue FROM installments i WHERE i.tenant_id = ?", [$id]);
$paid = (float)q_val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN installments i ON i.id = p.installment_id WHERE i.tenant_id = ? AND p.status = 'valid'", [$id]);
$maint = can('maintenance.view') ? q_all("SELECT m.*, u.unit_no FROM maintenance_requests m LEFT JOIN units u ON u.id = m.unit_id WHERE m.tenant_id = ? ORDER BY m.id DESC LIMIT 10", [$id]) : [];
$sens = can('tenants.sensitive');

layout_start('ملف المستأجر: ' . $t['full_name'], 'tenants');
$act = '<a class="btn" href="' . e(url('tenants/')) . '">رجوع للقائمة</a>';
if (can('tenants.manage')) $act .= ' <a class="btn" href="' . e(url('tenants/form.php?id=' . $id)) . '">' . icon('edit') . ' تعديل</a>';
if (can('contracts.manage') && $t['status'] === 'active') $act .= ' <a class="btn btn-primary" href="' . e(url('contracts/form.php?tenant_id=' . $id)) . '">' . icon('plus') . ' عقد جديد</a>';
if (can('reports.view')) $act .= ' <a class="btn" href="' . e(url('reports/statement.php?type=tenant&id=' . $id)) . '">' . icon('print') . ' كشف الحساب</a>';
page_actions($act);
?>
<div class="stats">
  <?= stat_card('المستحق حتى اليوم', money($tot['due_to_date']), '', 'amber') ?>
  <?= stat_card('المدفوع', money($paid), '', 'sage') ?>
  <?= stat_card('المتأخرات', money($tot['overdue']), '', 'red') ?>
</div>
<div class="grid g-main"><div>
  <div class="card"><div class="card-head"><h2>العقود</h2></div>
  <?php if (!$contracts): ?><div class="empty-inline">لا توجد عقود لهذا المستأجر بعد.</div><?php else: ?>
  <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>العقد</th><th>العقار / الوحدة</th><th>الفترة</th><th class="amt">الإيجار</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($contracts as $c): ?><tr><td><a class="ltr" href="<?= e(url('contracts/view.php?id=' . $c['id'])) ?>"><?= e($c['contract_no']) ?></a></td><td><?= e($c['pname']) ?> / <?= e($c['unit_no']) ?></td><td><?= fmt_date($c['start_date']) ?> ← <?= fmt_date($c['end_date']) ?></td><td class="amt"><?= money($c['rent_amount']) ?></td><td><?= badge('contract_status', $c['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></div>
  <div class="card"><div class="card-head"><h2>الاستحقاقات الأخيرة والمتأخرة</h2></div>
  <?php if (!$insts): ?><div class="empty-inline">لا توجد استحقاقات.</div><?php else: ?>
  <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>المرجع</th><th>الوحدة</th><th>الاستحقاق</th><th class="amt">المبلغ</th><th class="amt">المتبقي</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($insts as $i): ?><tr><td><a class="ltr" href="<?= e(url('payments/view.php?id=' . $i['id'])) ?>"><?= e($i['ref_no']) ?></a></td><td><?= e($i['unit_no']) ?></td><td><?= fmt_date($i['due_date']) ?></td><td class="amt"><?= money($i['amount']) ?></td><td class="amt"><?= money($i['remaining']) ?></td><td><?= badge('inst_status', $i['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></div>
  <?php if ($maint): ?><div class="card"><div class="card-head"><h2>بلاغات الصيانة المرتبطة</h2></div>
  <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>الرقم</th><th>المشكلة</th><th>الوحدة</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($maint as $m): ?><tr><td><a class="ltr" href="<?= e(url('maintenance/view.php?id=' . $m['id'])) ?>"><?= e($m['request_no']) ?></a></td><td><?= e($m['title']) ?></td><td><?= e($m['unit_no']) ?: '—' ?></td><td><?= badge('maint_status', $m['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
</div><div>
  <div class="card"><div class="card-head"><h2>البيانات الشخصية</h2><?= badge('tenant_status', $t['status']) ?></div>
    <div class="kv"><span>الهاتف</span><b class="ltr"><?= e($t['phone']) ?></b></div>
    <div class="kv"><span>البريد</span><b class="ltr"><?= e($t['email']) ?: '—' ?></b></div>
    <div class="kv"><span>رقم الهوية</span><b class="ltr"><?= $sens ? (e($t['national_id']) ?: '—') : '••••••' ?></b></div>
    <div class="kv"><span>العنوان</span><b><?= e($t['address']) ?: '—' ?></b></div>
    <div class="kv"><span>جهة بديلة</span><b><?= e($t['alt_contact_name']) ?: '—' ?> <span class="ltr"><?= e($t['alt_contact_phone']) ?></span></b></div>
    <div class="kv"><span>تاريخ التسجيل</span><b><?= fmt_date($t['registered_on']) ?></b></div>
    <?php if ($t['notes']): ?><hr><p class="muted" style="white-space:pre-line;margin:0"><?= e($t['notes']) ?></p><?php endif; ?>
  </div>
  <?php if ($sens): ?><div class="card"><h2>المرفقات</h2><?= doc_list(attachments_for('tenant', $id)) ?></div><?php endif; ?>
</div></div>
<?php layout_end();
