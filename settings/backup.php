<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('settings.manage');
if (!is_post()) redirect('settings/');
csrf_check();
audit('backup.run', 'system', null, ['mode' => 'download']);
$fname = 'sakan_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
@set_time_limit(300);
foreach (dump_database() as $chunk) { echo $chunk; }
