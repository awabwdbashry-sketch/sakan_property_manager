<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('notifications.view');
if (!is_post()) redirect('notifications/');
csrf_check();
$act = post('act'); $id = post_int('id');
if ($act === 'read_all') { q("UPDATE notifications SET is_read = 1, read_by = ?, read_at = NOW() WHERE is_read = 0", [user_id()]); flash('success', 'تم تعليم كل الإشعارات كمقروءة.'); }
elseif ($act === 'read' || $act === 'open') {
    q("UPDATE notifications SET is_read = 1, read_by = ?, read_at = NOW() WHERE id = ? AND is_read = 0", [user_id(), $id]);
    if ($act === 'open') {
        $link = q_val("SELECT link FROM notifications WHERE id = ?", [$id]);
        if ($link && preg_match('#^[a-z_]+/[a-z_]*\.?[a-z]*\??[a-z0-9=&_]*$#i', $link)) redirect($link);
    }
} elseif ($act === 'handle') {
    q("UPDATE notifications SET handled_at = NOW(), handled_by = ?, is_read = 1, read_by = COALESCE(read_by, ?), read_at = COALESCE(read_at, NOW()) WHERE id = ? AND handled_at IS NULL", [user_id(), user_id(), $id]);
    audit('notification.handle', 'notification', $id);
    flash('success', 'تم تعليم الإشعار كمعالج.');
} elseif ($act === 'snooze') {
    $days = max(1, min(30, (int)post('days', '3')));
    q("UPDATE notifications SET snoozed_until = ?, is_read = 1 WHERE id = ? AND handled_at IS NULL", [date('Y-m-d', strtotime("+$days day")) . ' 00:00:00', $id]);
    flash('success', 'تم تأجيل الإشعار ' . $days . ' أيام.');
} elseif ($act === 'refresh') {
    require_perm('settings.manage');
    $s = generate_notifications();
    flash('success', 'تم فحص التنبيهات. جديد: استحقاقات قريبة ' . $s['due'] . '، متأخرة ' . $s['overdue'] . '، عقود قريبة الانتهاء ' . $s['contracts'] . '، عقود انتهت ' . $s['expired'] . '.');
}
redirect('notifications/' . ($act === 'read' ? '?' . http_build_query(array_filter(['show' => get('show')])) : ''));
