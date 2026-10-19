<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('properties.view');
sync_overdue();

$id = get_int('id');
$p = q_one("SELECT * FROM properties WHERE id = ?", [$id]);
if (!$p) render_error_page('غير موجود', 'العقار المطلوب غير موجود.', 404);

$units = q_all("SELECT u.*, (SELECT t.full_name FROM contracts c JOIN tenants t ON t.id = c.tenant_id WHERE c.unit_id = u.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) tenant_name
                FROM units u WHERE u.property_id = ? AND u.is_archived = 0 ORDER BY u.floor, u.unit_no", [$id]);
$byStatus = array_fill_keys(array_keys(labels('unit_status')), 0);
foreach ($units as $u) $byStatus[$u['status']]++;
$total = count($units);
$occ = $total ? round($byStatus['rented'] / $total * 100) : 0;

$fin = q_one("SELECT
   COALESCE(SUM(CASE WHEN i.status <> 'cancelled' AND i.due_date <= CURDATE() THEN i.amount END),0) due_to_date,
   COALESCE(SUM(CASE WHEN i.status <> 'cancelled' THEN i.remaining END),0) remaining_all,
   COALESCE(SUM(CASE WHEN " . SQL_OVERDUE . " THEN i.remaining END),0) overdue
   FROM installments i WHERE i.property_id = ?", [$id]);
$collected = (float)q_val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN installments i ON i.id = p.installment_id WHERE i.property_id = ? AND p.status = 'valid'", [$id]);
$expenses = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE property_id = ? AND status = 'paid'", [$id]);
$maint = q_all("SELECT m.*, u.unit_no FROM maintenance_requests m LEFT JOIN units u ON u.id = m.unit_id WHERE m.property_id = ? ORDER BY m.id DESC LIMIT 8", [$id]);
$images = attachments_for('property', $id, 'image');
$canManage = can('properties.manage');

layout_start('العقار: ' . $p['name'], 'properties');
$act = '<a class="btn" href="' . e(url('properties/')) . '">رجوع للقائمة</a>';
if ($canManage) {
    $act .= ' <a class="btn" href="' . e(url('properties/form.php?id=' . $id)) . '">' . icon('edit') . ' تعديل</a>';
    if (can('units.manage') && $p['status'] === 'active') $act .= ' <a class="btn btn-primary" href="' . e(url('units/form.php?property_id=' . $id)) . '">' . icon('plus') . ' إضافة وحدة</a>';
    $act .= $p['status'] === 'active'
        ? ' ' . confirm_form('properties/archive.php', ['id' => $id, 'act' => 'archive'], 'أرشفة العقار', 'سيتم أرشفة العقار وإخفاؤه من القوائم النشطة مع الاحتفاظ بكل سجلاته المالية. هل تريد المتابعة؟', 'btn btn-danger')
        : ' ' . confirm_form('properties/archive.php', ['id' => $id, 'act' => 'restore'], 'استعادة العقار', 'هل تريد استعادة هذا العقار إلى القائمة النشطة؟', 'btn btn-sage');
}
if (can('reports.view')) $act .= ' <a class="btn" href="' . e(url('reports/statement.php?type=property&id=' . $id)) . '">' . icon('print') . ' كشف الحساب</a>';
page_actions($act);
?>
<div class="stats">
  <?= stat_card('إجمالي الوحدات', (string)$total, 'مؤجرة: ' . $byStatus['rented'] . ' · شاغرة: ' . $byStatus['vacant']) ?>
  <?= stat_card('نسبة الإشغال', $occ . '%', '<div class="progress"><i style="width:' . $occ . '%"></i></div>', 'sage') ?>
  <?= stat_card('الإيجارات المستحقة حتى اليوم', money($fin['due_to_date']), '', 'amber') ?>
  <?= stat_card('المحصّل فعلياً', money($collected), '', 'sage') ?>
  <?= stat_card('المتأخرات', money($fin['overdue']), '', 'red') ?>
  <?= stat_card('المصروفات المدفوعة', money($expenses), '', 'clay') ?>
</div>
<div class="grid g-main">
  <div>
    <div class="card">
      <div class="card-head"><h2>بيانات العقار</h2><?= badge('prop_status', $p['status']) ?></div>
      <dl class="dl">
        <div><dt>الرمز الداخلي</dt><dd class="ltr"><?= e($p['code']) ?></dd></div>
        <div><dt>المدينة / المنطقة</dt><dd><?= e(trim($p['city'] . ' ' . ($p['district'] ? '— ' . $p['district'] : ''))) ?: '—' ?></dd></div>
        <div><dt>العنوان</dt><dd><?= e($p['address']) ?: '—' ?></dd></div>
        <div><dt>عدد الطوابق</dt><dd><?= $p['floors'] !== null ? (int)$p['floors'] : '—' ?></dd></div>
        <div><dt>المسؤول عن العقار</dt><dd><?= e($p['manager_name']) ?: '—' ?></dd></div>
        <div><dt>رقم التواصل</dt><dd class="ltr"><?= e($p['contact_phone']) ?: '—' ?></dd></div>
        <div><dt>تاريخ الإضافة</dt><dd><?= fmt_date($p['created_at']) ?></dd></div>
      </dl>
      <?php if ($p['description']): ?><hr><p style="white-space:pre-line;margin:0"><?= e($p['description']) ?></p><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2>الوحدات (<?= $total ?>)</h2><?php if (can('units.view')): ?><a class="btn btn-sm" href="<?= e(url('units/?property_id=' . $id)) ?>">عرض الكل مع الفلاتر</a><?php endif; ?></div>
      <?php if (!$units): ?>
        <?= empty_state('لا توجد وحدات بعد', 'أضف الوحدات السكنية التابعة لهذا العقار لتبدأ بتأجيرها.', can('units.manage') && $p['status'] === 'active' ? 'إضافة وحدة' : '', 'units/form.php?property_id=' . $id) ?>
      <?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>الوحدة</th><th>الطابق</th><th>النوع</th><th class="amt">الإيجار</th><th>الحالة</th><th>المستأجر الحالي</th></tr></thead><tbody>
      <?php foreach ($units as $u): ?>
        <tr><td><a href="<?= e(url('units/view.php?id=' . $u['id'])) ?>"><b><?= e($u['unit_no']) ?></b></a></td><td><?= e($u['floor']) ?: '—' ?></td><td><?= e($u['unit_type']) ?: '—' ?></td>
            <td class="amt"><?= money($u['base_rent']) ?></td><td><?= badge('unit_status', $u['status']) ?></td><td><?= e($u['tenant_name']) ?: '—' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>
    <?php if (can('maintenance.view')): ?>
    <div class="card"><div class="card-head"><h2>سجل الصيانة</h2><a class="btn btn-sm" href="<?= e(url('maintenance/?property_id=' . $id)) ?>">عرض الكل</a></div>
      <?php if (!$maint): ?><div class="empty-inline">لا توجد بلاغات صيانة لهذا العقار.</div><?php else: ?>
      <div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>الرقم</th><th>المشكلة</th><th>الوحدة</th><th>الأولوية</th><th>الحالة</th></tr></thead><tbody>
      <?php foreach ($maint as $m): ?><tr><td><a class="ltr" href="<?= e(url('maintenance/view.php?id=' . $m['id'])) ?>"><?= e($m['request_no']) ?></a></td><td><?= e($m['title']) ?></td><td><?= e($m['unit_no']) ?: 'عام' ?></td><td><?= badge('priority', $m['priority']) ?></td><td><?= badge('maint_status', $m['status']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?></div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card"><h2>الصور</h2><?= image_gallery($images) ?></div>
    <div class="card"><h2>حالة الوحدات</h2>
      <?php foreach (labels('unit_status') as $k => $lab): ?><div class="kv"><span><?= e($lab) ?></span><b><?= $byStatus[$k] ?></b></div><?php endforeach; ?>
    </div>
  </div>
</div>
<?php layout_end();
