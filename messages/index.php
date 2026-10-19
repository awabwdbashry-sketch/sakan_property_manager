<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('messages.send');
$st = get('status');
$where = ['1=1']; $par = [];
if (in_array($st, ['approved', 'sent', 'not_sent', 'snoozed', 'dismissed'], true)) { $where[] = 'r.status = ?'; $par[] = $st; } else $st = '';
$W = implode(' AND ', $where);
$pg = paginate("SELECT COUNT(*) FROM reminders r WHERE $W", "SELECT r.*, t.full_name tname, a.full_name appr FROM reminders r LEFT JOIN tenants t ON t.id = r.tenant_id LEFT JOIN users a ON a.id = r.approved_by WHERE $W ORDER BY r.id DESC", $par, max(1, get_int('page')), 25);
$pendingN = (int)q_val("SELECT COUNT(*) FROM reminders WHERE status = 'approved'");
layout_start('سجل الرسائل والتذكيرات', 'messages');
?>
<?php if ($pendingN): ?><div class="alert alert-warning">لديك <?= $pendingN ?> رسالة فُتح لها واتساب ولم تُسجَّل نتيجتها بعد. <a href="?status=approved">عرضها</a></div><?php endif; ?>
<form class="filters" method="get"><div class="field"><label>الحالة</label><select name="status" data-auto><?= opts_assoc(['approved' => 'بانتظار تسجيل النتيجة', 'sent' => 'أُرسلت', 'not_sent' => 'لم تُرسل', 'snoozed' => 'مؤجَّلة', 'dismissed' => 'ملغاة'], $st, 'كل الحالات') ?></select></div></form>
<?php if (!$pg['rows']): ?><?= empty_state('لا توجد رسائل بعد', 'تظهر هنا كل التذكيرات التي راجعتها ووافقت عليها، مع نتيجتها. ابدأ من إشعار متأخرات أو من صفحة استحقاق/عقد/بلاغ/إيصال.') ?>
<?php else: ?><div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th>التاريخ</th><th>النوع</th><th>المستأجر</th><th>الحالة</th><th>المستخدم</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e(fmt_datetime($r['created_at'])) ?></td><td><?= e(['rent' => 'تذكير استحقاق', 'contract' => 'عقد', 'maintenance' => 'صيانة', 'receipt' => 'إيصال'][$r['kind']] ?? $r['kind']) ?></td>
  <td><?= e($r['tname'] ?: '—') ?></td><td><span class="badge <?= e(reminder_status_tone($r['status'])) ?>"><?= e(reminder_status_label($r['status'])) ?></span></td><td><?= e($r['appr'] ?: '—') ?></td>
  <td class="acts"><a class="btn btn-sm" href="<?= e(url('messages/view.php?id=' . (int)$r['id'])) ?>">فتح</a></td></tr><?php endforeach; ?></tbody></table></div></div><?= pager($pg) ?><?php endif;
layout_end();
