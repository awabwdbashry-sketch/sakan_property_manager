<?php
declare(strict_types=1);

function notify(string $type, string $title, ?string $body, ?string $link, string $dedupeKey): bool {
    return q("INSERT IGNORE INTO notifications (type, title, body, link, dedupe_key) VALUES (?,?,?,?,?)",
             [$type, mb_substr($title, 0, 200), $body !== null ? mb_substr($body, 0, 500) : null, $link, mb_substr($dedupeKey, 0, 120)])->rowCount() > 0;
}

function unread_notifications_count(): int {
    static $n = null;
    if ($n === null) $n = (int)q_val("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (snoozed_until IS NULL OR snoozed_until <= NOW())");
    return $n;
}

/** إشعارات الأحداث المالية الفورية (تُستدعى بعد نجاح العملية؛ فشل الإشعار لا يُفشل العملية). */
function notify_payment_event(int $paymentId, bool $void = false): void {
    try {
        $r = q_one("SELECT pay.id, pay.txn_no, pay.amount, pay.void_reason, rc.receipt_no, i.id iid, t.full_name FROM payments pay
                    JOIN installments i ON i.id = pay.installment_id JOIN tenants t ON t.id = i.tenant_id LEFT JOIN receipts rc ON rc.payment_id = pay.id WHERE pay.id = ?", [$paymentId]);
        if (!$r) return;
        if ($void) notify('payment_void', 'إلغاء دفعة ' . $r['txn_no'], $r['full_name'] . ' — ' . money($r['amount']) . ' — السبب: ' . $r['void_reason'], 'payments/view.php?id=' . $r['iid'], 'payment_void:' . $paymentId);
        else notify('payment', 'تحصيل دفعة — إيصال ' . $r['receipt_no'], $r['full_name'] . ' — ' . money($r['amount']), 'payments/receipt.php?id=' . $paymentId, 'payment:' . $paymentId);
    } catch (Throwable $e) { error_log('[SAKAN] notify_payment_event: ' . $e->getMessage()); }
}

/** رقم الاستحقاق المرتبط بإشعار إيجار (من رابطه) أو 0 */
function notification_installment_id(array $n): int {
    return (in_array($n['type'], ['rent_due', 'rent_today', 'rent_partial', 'rent_overdue'], true) && preg_match('#^payments/view\.php\?id=(\d+)$#', (string)$n['link'], $m)) ? (int)$m[1] : 0;
}

/**
 * ينشئ التنبيهات الدورية (دون تكرار بفضل dedupe_key). تُستدعى من cron/run.php،
 * وأيضاً كاحتياط عند فتح النظام مرة كل عدة ساعات.
 */
function generate_notifications(): array {
    $stats = ['expired' => expire_contracts(), 'due' => 0, 'overdue' => 0, 'contracts' => 0];
    sync_overdue();
    $rentDays = max(0, (int)setting('alert_rent_days', 7));
    $ctDays = max(0, (int)setting('alert_contract_days', 30));

    $due = q_all("SELECT i.id, i.ref_no, i.due_date, i.remaining, t.full_name, u.unit_no, p.name pname
                  FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id
                  WHERE i.status IN ('due','partial') AND i.remaining > 0 AND i.due_date >= CURDATE() AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL $rentDays DAY)");
    foreach ($due as $r) {
        $stats['due'] += (int)notify('rent_due', 'اقتراب موعد استحقاق إيجار',
            $r['full_name'] . ' — ' . $r['pname'] . ' / وحدة ' . $r['unit_no'] . ' — ' . money($r['remaining']) . ' بتاريخ ' . fmt_date($r['due_date']),
            'payments/view.php?id=' . $r['id'], 'rent_due:' . $r['id']);
    }
    $over = q_all("SELECT i.id, i.due_date, i.remaining, t.full_name, u.unit_no, p.name pname
                   FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id
                   WHERE i.status = 'overdue' AND i.remaining > 0");
    foreach ($over as $r) {
        $stats['overdue'] += (int)notify('rent_overdue', 'دفعة متأخرة',
            $r['full_name'] . ' — ' . $r['pname'] . ' / وحدة ' . $r['unit_no'] . ' — المتبقي ' . money($r['remaining']) . ' منذ ' . fmt_date($r['due_date']),
            'payments/view.php?id=' . $r['id'], 'rent_overdue:' . $r['id']);
    }
    $cts = q_all("SELECT c.id, c.contract_no, c.end_date, t.full_name, u.unit_no, p.name pname
                  FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id
                  WHERE c.status = 'active' AND c.end_date >= CURDATE() AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL $ctDays DAY)
                    AND NOT EXISTS (SELECT 1 FROM contracts n WHERE n.renewed_from_id = c.id AND n.status IN ('draft','active'))");
    foreach ($cts as $r) {
        $stats['contracts'] += (int)notify('contract_end', 'عقد يقارب على الانتهاء',
            $r['contract_no'] . ' — ' . $r['full_name'] . ' (' . $r['pname'] . ' / ' . $r['unit_no'] . ') ينتهي في ' . fmt_date($r['end_date']),
            'contracts/view.php?id=' . $r['id'], 'contract_end:' . $r['id'] . ':' . $r['end_date']);
    }
    // حلول موعد الاستحقاق اليوم دون سداد كامل
    $today = q_all("SELECT i.id, i.remaining, t.full_name, u.unit_no, p.name pname
                    FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id
                    WHERE i.status IN ('due','partial') AND i.remaining > 0 AND i.due_date = CURDATE()");
    foreach ($today as $r) {
        notify('rent_today', 'حل موعد الاستحقاق اليوم', $r['full_name'] . ' — ' . $r['pname'] . ' / وحدة ' . $r['unit_no'] . ' — المتبقي ' . money($r['remaining']),
               'payments/view.php?id=' . $r['id'], 'rent_today:' . $r['id']);
    }
    // مبلغ متبقٍ بعد دفعة جزئية (مفتاح جديد لكل مستوى سداد، فلا يتكرر لنفس الرصيد)
    $part = q_all("SELECT i.id, i.paid_amount, i.remaining, i.due_date, t.full_name, u.unit_no, p.name pname
                   FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id
                   WHERE i.status = 'partial' AND i.paid_amount > 0 AND i.remaining > 0");
    foreach ($part as $r) {
        notify('rent_partial', 'دفعة جزئية — متبقٍ على الاستحقاق', $r['full_name'] . ' — ' . $r['pname'] . ' / وحدة ' . $r['unit_no'] . ' — المدفوع ' . money($r['paid_amount']) . '، المتبقي ' . money($r['remaining']) . '، الاستحقاق ' . fmt_date($r['due_date']),
               'payments/view.php?id=' . $r['id'], 'rent_partial:' . $r['id'] . ':' . to_cents($r['paid_amount']));
    }
    // عقود انتهت مؤخراً وتحتاج مراجعة
    $exp = q_all("SELECT c.id, c.contract_no, c.end_date, t.full_name, u.unit_no, p.name pname
                  FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id
                  WHERE c.status = 'expired' AND c.end_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    AND NOT EXISTS (SELECT 1 FROM contracts n WHERE n.renewed_from_id = c.id AND n.status IN ('draft','active'))");
    foreach ($exp as $r) {
        notify('contract_expired', 'عقد انتهى ويحتاج مراجعة', $r['contract_no'] . ' — ' . $r['full_name'] . ' (' . $r['pname'] . ' / ' . $r['unit_no'] . ') انتهى في ' . fmt_date($r['end_date']),
               'contracts/view.php?id=' . $r['id'], 'contract_expired:' . $r['id']);
    }
    // بلاغات صيانة متأخرة (مفتوحة منذ أكثر من N يوم)
    $md = max(1, (int)setting('maint_overdue_days', 7));
    $mt = q_all("SELECT m.id, m.request_no, m.title, m.reported_on, p.name pname FROM maintenance_requests m JOIN properties p ON p.id = m.property_id
                 WHERE m.status IN ('new','in_progress','waiting') AND m.reported_on < DATE_SUB(CURDATE(), INTERVAL $md DAY)");
    foreach ($mt as $r) {
        notify('maint_overdue', 'بلاغ صيانة متأخر: ' . $r['title'], $r['request_no'] . ' — ' . $r['pname'] . ' — مفتوح منذ ' . fmt_date($r['reported_on']),
               'maintenance/view.php?id=' . $r['id'], 'maint_overdue:' . $r['id']);
    }
    // معالجة تلقائية: إشعارات الإيجار التي سُدّد استحقاقها أو أُلغي لم تعد تحتاج إجراءً
    q("UPDATE notifications n JOIN installments i ON n.link = CONCAT('payments/view.php?id=', i.id)
       SET n.handled_at = NOW(), n.is_read = 1
       WHERE n.type IN ('rent_due','rent_today','rent_partial','rent_overdue') AND n.handled_at IS NULL AND (i.remaining <= 0 OR i.status = 'cancelled')");
    q("UPDATE notifications n JOIN maintenance_requests m ON n.link = CONCAT('maintenance/view.php?id=', m.id)
       SET n.handled_at = NOW() WHERE n.type = 'maint_overdue' AND n.handled_at IS NULL AND m.status IN ('completed','cancelled')");
    setting_set('last_cron_run', date('Y-m-d H:i:s'));
    return $stats;
}

/** احتياط: تشغيل التنبيهات تلقائياً إن لم تعمل المهمة المجدولة منذ 6 ساعات */
function maybe_run_notifications(): void {
    $last = (string)setting('last_cron_run', '');
    if ($last === '' || strtotime($last) < time() - 6 * 3600) {
        try { generate_notifications(); } catch (Throwable $e) { error_log('[SAKAN] notifications: ' . $e->getMessage()); }
    }
}
