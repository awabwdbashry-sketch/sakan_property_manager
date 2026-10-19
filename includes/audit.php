<?php
declare(strict_types=1);

/** تسجيل عملية في سجل التدقيق. لا تُخزَّن كلمات المرور أو الأسرار. */
function audit(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void {
    $strip = function ($v) use (&$strip) {
        if (!is_array($v)) return $v;
        $o = [];
        foreach ($v as $k => $x) {
            if (is_string($k) && preg_match('/pass|secret|token|_csrf|hash/i', $k)) continue;
            $o[$k] = $strip($x);
        }
        return $o;
    };
    $u = $GLOBALS['SAKAN_USER'] ?? null;
    $uid = $u['id'] ?? ($_SESSION['uid'] ?? null);
    $uname = $u['username'] ?? null;
    if ($uid && !$uname) $uname = q_val("SELECT username FROM users WHERE id = ?", [$uid]);
    $json = $details ? json_encode($strip($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    q("INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, ip) VALUES (?,?,?,?,?,?,?)",
      [$uid, $uname, $action, $entityType, $entityId, $json, client_ip()]);
}

function audit_action_label(string $a): string {
    static $m = [
        'auth.login' => 'تسجيل دخول', 'auth.logout' => 'تسجيل خروج', 'setup.admin' => 'إنشاء المدير الأول',
        'property.create' => 'إضافة عقار', 'property.update' => 'تعديل عقار', 'property.archive' => 'أرشفة عقار', 'property.restore' => 'استعادة عقار',
        'unit.create' => 'إضافة وحدة', 'unit.update' => 'تعديل وحدة', 'unit.archive' => 'أرشفة وحدة', 'unit.status' => 'تغيير حالة وحدة',
        'tenant.create' => 'إضافة مستأجر', 'tenant.update' => 'تعديل مستأجر', 'tenant.status' => 'تغيير حالة مستأجر',
        'contract.create' => 'إنشاء عقد', 'contract.update' => 'تعديل عقد', 'contract.activate' => 'تفعيل عقد', 'contract.renew' => 'تجديد عقد',
        'contract.terminate' => 'إنهاء عقد', 'contract.expire' => 'انتهاء عقد', 'contract.generate' => 'توليد استحقاقات',
        'payment.create' => 'تسجيل دفعة', 'payment.void' => 'إلغاء دفعة',
        'expense.create' => 'إضافة مصروف', 'expense.update' => 'تعديل مصروف', 'expense.cancel' => 'إلغاء مصروف',
        'maintenance.create' => 'تسجيل بلاغ صيانة', 'maintenance.update' => 'تحديث بلاغ صيانة',
        'user.create' => 'إضافة مستخدم', 'user.update' => 'تعديل مستخدم', 'user.password' => 'إعادة تعيين كلمة مرور', 'user.status' => 'تغيير حالة مستخدم',
        'role.perms' => 'تغيير صلاحيات دور', 'settings.update' => 'تعديل الإعدادات', 'attachment.add' => 'إرفاق ملف', 'attachment.delete' => 'حذف مرفق',
        'deposit.create' => 'حركة تأمين', 'report.export' => 'تصدير تقرير', 'role.create' => 'إنشاء دور', 'backup.run' => 'نسخ احتياطي',
        'reminder.approve' => 'موافقة على إرسال رسالة (فتح واتساب)', 'reminder.sent' => 'تسجيل: أُرسلت الرسالة', 'reminder.not_sent' => 'تسجيل: لم تُرسل الرسالة',
        'reminder.snooze' => 'تأجيل تذكير', 'reminder.dismiss' => 'إلغاء تذكير', 'notification.handle' => 'تعليم إشعار كمعالج',
    ];
    return $m[$a] ?? $a;
}
