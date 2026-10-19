<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('audit.view');
$uid = get_int('user_id'); $act = get('action'); $ent = get('entity'); $from = get('from'); $to = get('to'); $qtext = get('q');
$where = ['1=1']; $par = [];
if ($uid) { $where[] = 'a.user_id = ?'; $par[] = $uid; }
if ($act !== '') { $where[] = 'a.action = ?'; $par[] = $act; }
if ($ent !== '') { $where[] = 'a.entity_type = ?'; $par[] = $ent; }
if (valid_date($from)) { $where[] = 'a.created_at >= ?'; $par[] = $from . ' 00:00:00'; } else $from = '';
if (valid_date($to)) { $where[] = 'a.created_at <= ?'; $par[] = $to . ' 23:59:59'; } else $to = '';
if ($qtext !== '') { $where[] = '(a.details LIKE ? OR a.username LIKE ?)'; array_push($par, "%$qtext%", "%$qtext%"); }
$W = implode(' AND ', $where);
$pg = paginate("SELECT COUNT(*) FROM audit_logs a WHERE $W", "SELECT a.* FROM audit_logs a WHERE $W ORDER BY a.id DESC", $par, max(1, get_int('page')), 30);
$users = q_all("SELECT id, full_name FROM users ORDER BY full_name");
$actions = array_column(q_all("SELECT DISTINCT action FROM audit_logs ORDER BY action"), 'action');
$ents = array_column(q_all("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type"), 'entity_type');
layout_start('سجل التدقيق', 'audit');
?>
<div class="alert alert-info">سجل للقراءة فقط: لا يمكن تعديل أو حذف أي سجل من الواجهة. لا تُخزَّن كلمات المرور أو الأسرار فيه.</div>
<form class="filters" method="get">
  <div class="field"><label>المستخدم</label><select name="user_id"><?= opts($users, 'id', 'full_name', $uid, 'الكل') ?></select></div>
  <div class="field"><label>العملية</label><select name="action"><option value="">الكل</option><?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $act === $a ? 'selected' : '' ?>><?= e(audit_action_label($a)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>السجل</label><select name="entity"><option value="">الكل</option><?php foreach ($ents as $a): ?><option value="<?= e($a) ?>" <?= $ent === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>من</label><input type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label>إلى</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="field grow"><label>بحث في التفاصيل</label><input type="search" name="q" value="<?= e($qtext) ?>"></div>
  <button class="btn btn-primary" type="submit"><?= icon('search') ?> تصفية</button><a class="btn btn-ghost" href="<?= e(url('audit/')) ?>">مسح</a>
</form>
<?php if (!$pg['rows']): ?><?= empty_state('لا توجد سجلات', 'لا توجد عمليات مطابقة.') ?><?php else: ?>
<div class="card"><div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>التاريخ</th><th>المستخدم</th><th>العملية</th><th>السجل</th><th>التفاصيل</th><th>IP</th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $a): ?><tr><td class="nowrap"><?= fmt_datetime($a['created_at']) ?></td><td><?= e($a['username']) ?: '—' ?></td><td><?= e(audit_action_label($a['action'])) ?></td>
<td class="ltr"><?= e($a['entity_type']) ?><?= $a['entity_id'] ? ' #' . (int)$a['entity_id'] : '' ?></td><td style="max-width:420px;word-break:break-word"><small class="muted ltr" style="direction:ltr;text-align:left"><?= e(mb_strimwidth((string)$a['details'], 0, 220, '…')) ?></small></td><td class="ltr"><?= e($a['ip']) ?></td></tr><?php endforeach; ?></tbody></table></div><?= pager($pg) ?></div><?php endif;
layout_end();
