<?php
/**
 * المهمة المجدولة: توليد التنبيهات الدورية وإنهاء العقود المنتهية وتحديث المتأخرات.
 * التشغيل من سطر الأوامر فقط:  php cron/run.php
 * (انظر README لإعداد Windows Task Scheduler)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../includes/bootstrap.php';
$s = generate_notifications();
echo date('Y-m-d H:i:s') . ' — تم: عقود منتهية ' . $s['expired'] . ' | تنبيهات استحقاق جديدة ' . $s['due'] . ' | متأخرة جديدة ' . $s['overdue'] . ' | عقود قريبة الانتهاء ' . $s['contracts'] . PHP_EOL;
