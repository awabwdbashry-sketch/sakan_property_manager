<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('messages.send');
if (!is_post()) redirect('messages/');
csrf_check();
$act = post('act');

// تسجيل نتيجة رسالة سبق فتح واتساب لها
if (post_int('reminder_id') && in_array($act, ['sent', 'not_sent'], true)) {
    $rid = post_int('reminder_id');
    $n = tx(function () use ($rid, $act) {
        $r = q_one("SELECT * FROM reminders WHERE id = ? FOR UPDATE", [$rid]);
        if (!$r || $r['status'] !== 'approved' || !can(msg_kind_perm((string)$r['kind']))) return 0;
        q("UPDATE reminders SET status = ?, outcome_by = ?, outcome_at = NOW(), outcome_note = ? WHERE id = ?", [$act, user_id(), mb_substr(post('note'), 0, 300) ?: null, $rid]);
        if ($act === 'sent' && $r['installment_id']) q("UPDATE notifications SET handled_at = NOW(), handled_by = ?, is_read = 1 WHERE link = ? AND handled_at IS NULL", [user_id(), 'payments/view.php?id=' . $r['installment_id']]);
        return 1;
    });
    if ($n) { audit('reminder.' . $act, 'reminder', $rid); flash('success', $act === 'sent' ? 'سُجّل أن الرسالة أُرسلت.' : 'سُجّل أن الرسالة لم تُرسل.'); }
    else flash('error', 'تعذّر تسجيل النتيجة (قد تكون سُجّلت مسبقاً).');
    redirect('messages/view.php?id=' . $rid);
}

// تأجيل / إلغاء تذكير استحقاق
if (post('kind') === 'rent' && in_array($act, ['snooze', 'dismiss'], true)) {
    require_perm('payments.view');
    $iid = post_int('id'); $days = max(1, min(30, (int)post('days', '3')));
    $i = q_one("SELECT id, tenant_id, contract_id FROM installments WHERE id = ?", [$iid]);
    if (!$i) render_error_page('غير موجود', 'الاستحقاق غير موجود.', 404);
    $until = date('Y-m-d', strtotime("+$days day"));
    q("INSERT INTO reminders (kind, installment_id, contract_id, tenant_id, status, snooze_until, approved_by, approved_at, outcome_by, outcome_at) VALUES ('rent', ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
      [$iid, $i['contract_id'], $i['tenant_id'], $act === 'snooze' ? 'snoozed' : 'dismissed', $act === 'snooze' ? $until : null, user_id(), user_id()]);
    $link = 'payments/view.php?id=' . $iid;
    if ($act === 'snooze') q("UPDATE notifications SET snoozed_until = ? WHERE link = ? AND handled_at IS NULL", [$until . ' 00:00:00', $link]);
    else q("UPDATE notifications SET handled_at = NOW(), handled_by = ?, is_read = 1 WHERE link = ? AND handled_at IS NULL", [user_id(), $link]);
    audit('reminder.' . $act, 'installment', $iid, ['until' => $act === 'snooze' ? $until : null]);
    flash('success', $act === 'snooze' ? 'تم تأجيل التذكير حتى ' . fmt_date($until) . '.' : 'تم إلغاء التذكير لهذا الاستحقاق.');
    redirect('payments/view.php?id=' . $iid);
}
redirect('messages/');
