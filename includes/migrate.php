<?php
declare(strict_types=1);
/**
 * ترقية المخطط التلقائية الآمنة: إن كانت قاعدة البيانات من الإصدار 1 (بلا ترحيل 002) يُطبَّق ملف
 * database/migrations/002_reminders_whatsapp.sql نفسه (إنشاء جدول وأعمدة وصلاحية وإعدادات فقط، ولا حذف ولا تعديل لبيانات موجودة)
 * ثم يُسجَّل schema_version=2. تُنفَّذ مرة واحدة تحت قفل لمنع التزامن، وتُتجاهل إن لم تكن القاعدة مستوردة بعد.
 */
const SAKAN_SCHEMA_VERSION = 2;

function schema_upgrade_if_needed(): void {
    // قاعدة غير مستوردة بعد: لا شيء نرقّيه (تتولاها صفحة «قاعدة البيانات غير جاهزة»)
    if (!(bool)q_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'settings'")) return;
    if ((int)setting('schema_version', '1') >= SAKAN_SCHEMA_VERSION) return;

    $pdo = db();
    if (!(int)$pdo->query("SELECT GET_LOCK('sakan_schema_upgrade', 15)")->fetchColumn()) throw new RuntimeException('تعذّر الحصول على قفل الترقية.');
    try {
        $GLOBALS['SAKAN_SETTINGS'] = null; // أعد قراءة الإعدادات: ربما رقّاها طلب آخر للتو
        if ((int)(q_val("SELECT svalue FROM settings WHERE skey = 'schema_version'") ?: 1) >= SAKAN_SCHEMA_VERSION) return;
        $file = dirname(__DIR__) . '/database/migrations/002_reminders_whatsapp.sql';
        $sql = @file_get_contents($file);
        if ($sql === false) throw new RuntimeException('ملف الترحيل غير موجود: database/migrations/002_reminders_whatsapp.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);                 // التعليقات
        $sql = preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*$/mi', '', $sql); // نعمل على القاعدة الحالية أياً كان اسمها
        foreach (preg_split('/;\s*\n/', (string)$sql) as $stmt) {
            $stmt = trim($stmt, " \t\r\n;");
            if ($stmt !== '') $pdo->exec($stmt);
        }
        q("INSERT INTO settings (skey, svalue) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [(string)SAKAN_SCHEMA_VERSION]);
        $GLOBALS['SAKAN_SETTINGS'] = null;
        error_log('[SAKAN] تمت ترقية مخطط قاعدة البيانات إلى الإصدار ' . SAKAN_SCHEMA_VERSION . ' تلقائياً.');
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('sakan_schema_upgrade')")->fetchColumn();
    }
}
