<?php
declare(strict_types=1);

/** نسخة احتياطية SQL بلغة PHP الصرفة (لا تعتمد على mysqldump). تُعاد كنص عبر مولّد لتفادي استهلاك الذاكرة. */
function dump_database(): Generator {
    $pdo = db();
    $name = (string)cfg('db.name');
    yield "-- SAKAN Property Manager backup\n-- Database: $name\n-- Date: " . date('Y-m-d H:i:s') . "\n";
    yield "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        yield "DROP TABLE IF EXISTS `$t`;\n$create;\n\n";
        $st = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_NUM);
        $batch = [];
        $cols = null;
        while ($row = $st->fetch()) {
            if ($cols === null) { $cols = []; for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = '`' . $st->getColumnMeta($i)['name'] . '`'; }
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row);
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) { yield "INSERT INTO `$t` (" . implode(',', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";\n"; $batch = []; }
        }
        if ($batch) yield "INSERT INTO `$t` (" . implode(',', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";\n";
        yield "\n";
    }
    yield "SET FOREIGN_KEY_CHECKS = 1;\n";
}

function backup_dir(): string {
    $p = (string)cfg('backup_path', '');
    if ($p === '') $p = rtrim(str_replace('\\', '/', (string)cfg('storage_path')), '/') . '/backups';
    return rtrim(str_replace('\\', '/', $p), '/');
}

/** يكتب نسخة إلى مجلد النسخ الاحتياطية ويعيد مسار الملف */
function backup_to_file(): string {
    $dir = backup_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('تعذّر إنشاء مجلد النسخ الاحتياطي: ' . $dir);
    $file = $dir . '/sakan_backup_' . date('Ymd_His') . '.sql';
    $fh = fopen($file, 'wb');
    if (!$fh) throw new RuntimeException('تعذّر إنشاء ملف النسخة.');
    foreach (dump_database() as $chunk) fwrite($fh, $chunk);
    fclose($fh);
    return $file;
}
