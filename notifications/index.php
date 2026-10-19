<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('notifications.view');
$show = get('show', 'open');
$type = get('type');
$types = ['rent_due' => 'قرب استحقاق إيجار', 'rent_today' => 'حلّ الموعد اليوم', 'rent_partial' => 'دفعة جزئية', 'rent_overdue' => 'دفعة متأخرة', 'contract_end' => 'قرب انتهاء عقد', 'contract_expired' => 'عقد انتهى',
          'maint_new' => 'بلاغ صيانة جديد', 'maint_upd' => 'تحديث صيانة', 'maint_overdue' => 'صيانة متأخرة', 'payment' => 'تحصيل', 'payment_void' => 'إلغاء دفعة'];
$tone = ['rent_due' => 'b-sand', 'rent_today' => 'b-amber', 'rent_partial' => 'b-amber', 'rent_overdue' => 'b-red', 'contract_end' => 'b-amber', 'contract_expired' => 'b-red', 'maint_new' => 'b-clay', 'maint_upd' => 'b-sage', 'maint_overdue' => 'b-red', 'payment' => 'b-sage', 'payment_void' => 'b-gray'];
$where = ['1=1']; $par = [];
if ($show === 'unread') { $where[] = 'is_read = 0'; $where[] = '(snoozed_until IS NULL OR snoozed_until <= NOW())'; }
elseif ($show === 'handled') $where[] = 'handled_at IS NOT NULL';
elseif ($show === 'open') { $where[] = 'handled_at IS NULL'; $where[] = '(snoozed_until IS NULL OR snoozed_until <= NOW())'; }
elseif ($show === 'snoozed') $where[] = 'snoozed_until > NOW()';
else $show = 'all';
if (isset($types[$type])) { $where[] = 'type = ?'; $par[] = $type; } else $type = '';
$W = implode(' AND ', $where);
$pg = paginate("SELECT COUNT(*) FROM notifications WHERE $W", "SELECT * FROM notifications WHERE $W ORDER BY (handled_at IS NOT NULL), is_read, id DESC", $par, max(1, get_int('page')), 25);
$canMsg = can('messages.send');
function notif_compose_link(array $n): array { // [kind, id] أو []
    if ($iid = notification_installment_id($n)) return ['rent', $iid, 'مراجعة وإرسال تذكير'];
    if (preg_match('#^contracts/view\.php\?id=(\d+)$#', (string)$n['link'], $m) && in_array($n['type'], ['contract_end', 'contract_expired'], true)) return ['contract', (int)$m[1], 'رسالة بخصوص العقد'];
    if (preg_match('#^maintenance/view\.php\?id=(\d+)$#', (string)$n['link'], $m)) return ['maintenance', (int)$m[1], 'رسالة بخصوص البلاغ'];
    if ($n['type'] === 'payment' && preg_match('#^payments/receipt\.php\?id=(\d+)$#', (string)$n['link'], $m)) return ['receipt', (int)$m[1], 'إشعار المستأجر بالإيصال'];
    return [];
}
layout_start('الإشعارات والتنبيهات', 'notifications');
$act = fn(string $a, int $id, string $label, string $cls = 'btn btn-sm', string $extra = '') => '<form method="post" action="' . e(url('notifications/action.php')) . '" class="inline-form">' . csrf_field() . '<input type="hidden" name="act" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '">' . $extra . '<button class="' . $cls . '">' . $label . '</button></form>';
?>
<div class="page-actions">
  <form method="post" action="<?= e(url('notifications/action.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="act" value="read_all"><button class="btn btn-sm">تعليم الكل كمقروء</button></form>
  <?php if (can('settings.manage')): ?><form method="post" action="<?= e(url('notifications/action.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="act" value="refresh"><button class="btn btn-sm btn-sage">فحص التنبيهات الآن</button></form><?php endif; ?>
</div>
<form class="filters" method="get">
  <div class="field"><label>عرض</label><select name="show" data-auto><?= opts_assoc(['open' => 'بحاجة لإجراء (غير معالجة)', 'unread' => 'غير المقروءة', 'snoozed' => 'المؤجَّلة', 'handled' => 'المعالجة', 'all' => 'الكل'], $show) ?></select></div>
  <div class="field"><label>النوع</label><select name="type" data-auto><?= opts_assoc($types, $type, 'كل الأنواع') ?></select></div>
</form>
<?php if (!$pg['rows']): ?><?= empty_state('لا توجد إشعارات', 'ستظهر هنا تنبيهات الاستحقاق والتأخر والدفعات الجزئية وانتهاء العقود وبلاغات الصيانة المتأخرة، وتُفحص تلقائياً عبر المهمة المجدولة.') ?>
<?php else: ?><div class="card notif-list">
<?php foreach ($pg['rows'] as $n): $cl = notif_compose_link($n); $snoozed = $n['snoozed_until'] && strtotime($n['snoozed_until']) > time(); ?>
  <div class="notif <?= $n['handled_at'] ? 'is-handled' : ($n['is_read'] ? 'is-read' : 'is-new') ?>">
    <div class="notif-main">
      <div><span class="badge <?= e($tone[$n['type']] ?? 'b-gray') ?>"><?= e($types[$n['type']] ?? $n['type']) ?></span>
        <?php if ($n['handled_at']): ?><span class="badge b-sage">معالج</span><?php elseif (!$n['is_read']): ?><span class="badge b-clay">جديد</span><?php endif; ?>
        <?php if ($snoozed): ?><span class="badge b-sand">مؤجَّل حتى <?= e(fmt_date($n['snoozed_until'])) ?></span><?php endif; ?>
        <b><?= e($n['title']) ?></b></div>
      <small class="muted"><?= e($n['body']) ?> · <?= e(fmt_datetime($n['created_at'])) ?></small></div>
    <div class="notif-acts">
      <?php if ($n['link']): ?><?= $act('open', (int)$n['id'], 'فتح السجل', 'btn btn-sm btn-primary') ?><?php endif; ?>
      <?php if ($cl && $canMsg && can(msg_kind_perm($cl[0]))): ?><a class="btn btn-sm btn-sage" href="<?= e(url('messages/compose.php?kind=' . $cl[0] . '&id=' . $cl[1])) ?>"><?= e($cl[2]) ?></a><?php endif; ?>
      <?php if (!$n['is_read']): ?><?= $act('read', (int)$n['id'], 'مقروء') ?><?php endif; ?>
      <?php if (!$n['handled_at']): ?>
        <?= $act('snooze', (int)$n['id'], 'تأجيل 3 أيام', 'btn btn-sm', '<input type="hidden" name="days" value="3">') ?>
        <?= $act('handle', (int)$n['id'], 'تمت المعالجة') ?>
      <?php endif; ?>
    </div></div>
<?php endforeach; ?></div><?= pager($pg) ?><?php endif;
layout_end();
