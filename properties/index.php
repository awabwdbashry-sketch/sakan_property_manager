<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('properties.view');

$status = get('status', 'active');
$qtext = get('q');
$city = get('city');
$where = ['1=1']; $par = [];
if (in_array($status, ['active', 'archived'], true)) { $where[] = 'p.status = ?'; $par[] = $status; } else { $status = ''; }
if ($qtext !== '') { $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.address LIKE ?)'; $l = '%' . $qtext . '%'; array_push($par, $l, $l, $l); }
if ($city !== '') { $where[] = 'p.city = ?'; $par[] = $city; }
$W = implode(' AND ', $where);

$pg = paginate("SELECT COUNT(*) FROM properties p WHERE $W",
  "SELECT p.*,
     (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id AND u.is_archived = 0) units_n,
     (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id AND u.is_archived = 0 AND u.status = 'rented') rented_n,
     (SELECT a.id FROM attachments a WHERE a.entity_type = 'property' AND a.entity_id = p.id AND a.kind = 'image' ORDER BY a.id LIMIT 1) cover_id
   FROM properties p WHERE $W ORDER BY p.name", $par, max(1, get_int('page')), 12);
$cities = q_all("SELECT DISTINCT city FROM properties WHERE city IS NOT NULL AND city <> '' ORDER BY city");

layout_start('العقارات', 'properties');
if (can('properties.manage')) page_actions('<a class="btn btn-primary" href="' . e(url('properties/form.php')) . '">' . icon('plus') . ' إضافة عقار</a>');
?>
<form class="filters" method="get">
  <div class="field grow"><label>بحث</label><input type="search" name="q" value="<?= e($qtext) ?>" placeholder="اسم العقار أو الرمز أو العنوان"></div>
  <div class="field"><label>المدينة</label><select name="city"><?= opts($cities, 'city', 'city', $city, 'كل المدن') ?></select></div>
  <div class="field"><label>الحالة</label><select name="status"><?= opts_assoc(['active' => 'نشط', 'archived' => 'مؤرشف'], $status, 'الكل') ?></select></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button>
  <a class="btn btn-ghost" href="<?= e(url('properties/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?>
  <?= empty_state('لا توجد عقارات', $qtext !== '' || $city !== '' ? 'لا توجد نتائج مطابقة لعوامل التصفية الحالية.' : 'ابدأ بإضافة أول عقار أو مبنى لديك، ثم أضف وحداته السكنية.', can('properties.manage') && $qtext === '' ? 'إضافة عقار' : '', 'properties/form.php') ?>
<?php else: ?>
<div class="prop-cards">
<?php foreach ($pg['rows'] as $p): $occ = $p['units_n'] ? round($p['rented_n'] / $p['units_n'] * 100) : 0; ?>
  <article class="prop-card">
    <div class="card-cover"><?php if ($p['cover_id']): ?><img src="<?= e(attachment_url((int)$p['cover_id'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><?php else: echo icon('building'); endif; ?></div>
    <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
      <h3><a href="<?= e(url('properties/view.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a></h3><?= badge('prop_status', $p['status']) ?>
    </div>
    <div class="muted"><span class="ltr"><?= e($p['code']) ?></span> · <?= e(trim($p['city'] . ' ' . ($p['district'] ? '— ' . $p['district'] : ''))) ?: 'العنوان غير محدد' ?></div>
    <div class="meta"><span>الوحدات: <b><?= (int)$p['units_n'] ?></b></span><span>المؤجرة: <b><?= (int)$p['rented_n'] ?></b></span><span>الإشغال: <b><?= $occ ?>%</b></span></div>
    <div class="progress" style="margin-top:10px"><i style="width:<?= $occ ?>%"></i></div>
    <div class="acts">
      <a class="btn btn-sm" href="<?= e(url('properties/view.php?id=' . $p['id'])) ?>"><?= icon('eye') ?> التفاصيل</a>
      <?php if (can('properties.manage')): ?><a class="btn btn-sm" href="<?= e(url('properties/form.php?id=' . $p['id'])) ?>"><?= icon('edit') ?> تعديل</a><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?= pager($pg) ?>
<?php endif;
layout_end();
