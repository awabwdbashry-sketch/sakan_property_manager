<?php
declare(strict_types=1);

define('SAKAN_ROOT', dirname(__DIR__));

// ---------- الإعدادات ----------
$__cfg = require SAKAN_ROOT . '/config/config.php';
if (is_file(SAKAN_ROOT . '/config/local.php')) {
    $__local = require SAKAN_ROOT . '/config/local.php';
    $__cfg = array_replace_recursive($__cfg, $__local);
}
if (($__dbn = getenv('SAKAN_DB_NAME')) !== false && $__dbn !== '') $__cfg['db']['name'] = $__dbn; // للاختبارات الآلية فقط
$GLOBALS['SAKAN_CFG'] = $__cfg;
unset($__cfg, $__local);

function cfg(string $key, $default = null) {
    $v = $GLOBALS['SAKAN_CFG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) return $default;
        $v = $v[$part];
    }
    return $v;
}

// ---------- الأخطاء ----------
error_reporting(E_ALL);
ini_set('display_errors', cfg('debug') ? '1' : '0');
ini_set('log_errors', '1');
@ini_set('error_log', rtrim((string)cfg('storage_path'), '/\\') . '/logs/php-error.log');
date_default_timezone_set((string)cfg('timezone', 'Africa/Khartoum'));
mb_internal_encoding('UTF-8');

set_exception_handler(function (Throwable $e) {
    error_log('[SAKAN] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    $detail = cfg('debug') ? '<pre dir="ltr" style="text-align:left">' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>' : '';
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>خطأ</title>'
       . '<body style="font-family:Tahoma,sans-serif;background:#F6F0E6;color:#39352F;display:grid;place-items:center;min-height:100vh;margin:0">'
       . '<div style="background:#fff;padding:32px 40px;border-radius:16px;max-width:520px;text-align:center">'
       . '<h2 style="color:#C96F50">حدث خطأ غير متوقع</h2><p>تعذّر إكمال العملية. تم تسجيل التفاصيل التقنية داخلياً في <span dir="ltr">storage/logs/php-error.log</span>.</p>'
       . '<p><a href="javascript:history.back()">رجوع</a></p>' . $detail . '</div></body></html>';
    exit;
});

// ---------- الجلسة ----------
function sakan_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}
if (PHP_SAPI === 'cli') {
    if (!isset($_SESSION)) $_SESSION = []; // سطر الأوامر (الاختبارات / المهام المجدولة) بلا جلسة حقيقية
} elseif (session_status() === PHP_SESSION_NONE) {
    session_name((string)cfg('session_name', 'SAKANSESSID'));
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => sakan_is_https(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// ---------- الملفات المشتركة ----------
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/finance.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/ops.php';
require_once __DIR__ . '/charts.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/migrate.php';

// الإعدادات المخزنة في قاعدة البيانات تتجاوز المنطقة الزمنية الافتراضية
try {
    $tz = setting('timezone');
    if ($tz && in_array($tz, timezone_identifiers_list(), true)) date_default_timezone_set($tz);
} catch (Throwable $e) { /* قاعدة البيانات غير جاهزة بعد (صفحة الإعداد) */ }

// ترقية المخطط الآمنة تلقائياً (قواعد الإصدار 1 بلا ترحيل 002)؛ عند الفشل تُعرض صفحة واضحة بدل «خطأ غير متوقع»
try { schema_upgrade_if_needed(); }
catch (Throwable $e) {
    error_log('[SAKAN] فشلت ترقية المخطط: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, 'فشلت ترقية المخطط: ' . $e->getMessage() . PHP_EOL); exit(1); }
    http_response_code(503);
    auth_page_start('قاعدة البيانات تحتاج ترقية', 'تعذّرت الترقية التلقائية، ولم يتغيّر شيء في بياناتك.');
    echo '<div class="alert alert-warning"><div>افتح phpMyAdmin ← اختر قاعدة <b class="ltr">' . e((string)cfg('db')['name']) . '</b> ← تبويب «استيراد» ← نفّذ الملف <b class="ltr">database/migrations/002_reminders_whatsapp.sql</b> ثم أعد تحميل الصفحة.'
       . '<br><br>سبب الفشل: <span class="ltr">' . e(mb_substr($e->getMessage(), 0, 300)) . '</span></div></div>';
    auth_page_end(); exit;
}
