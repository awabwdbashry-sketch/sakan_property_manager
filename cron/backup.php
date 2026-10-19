<?php
/**
 * نسخة احتياطية لقاعدة البيانات إلى مجلد خاص (storage/backups أو backup_path في config/local.php).
 * التشغيل: php cron/backup.php     (يمكن جدولته عبر Windows Task Scheduler — انظر README)
 * يحذف النسخ الأقدم من 30 نسخة للحفاظ على المساحة.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../includes/bootstrap.php';
try {
    $f = backup_to_file();
    $all = glob(backup_dir() . '/sakan_backup_*.sql'); sort($all);
    while (count($all) > 30) @unlink(array_shift($all));
    echo date('Y-m-d H:i:s') . ' — تم إنشاء النسخة: ' . $f . ' (' . round(filesize($f) / 1024) . ' ك.ب)' . PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR, 'فشل النسخ الاحتياطي: ' . $e->getMessage() . PHP_EOL); exit(1); }
