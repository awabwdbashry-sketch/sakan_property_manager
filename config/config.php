<?php
/**
 * الإعدادات الافتراضية للتطبيق.
 * لا تعدّل هذا الملف؛ عدّل config/local.php بدلاً منه.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'sakan_property_manager',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    // اتركه فارغاً ليتم اكتشاف المسار تلقائياً (مثال يدوي: '/sakan_property_manager')
    'base_url'        => '',
    'debug'           => false,
    'timezone'        => 'Africa/Khartoum',
    // مجلد الملفات المرفوعة. يفضّل وضعه خارج www (مثال: D:/sakan_storage)
    'storage_path'    => dirname(__DIR__) . '/storage',
    'max_upload_mb'   => 5,
    'session_name'    => 'SAKANSESSID',
    'session_idle_minutes' => 120,
    // إنشاء المدير الأول مسموح فقط من الجهاز نفسه ما لم تُفعّل هذا الخيار
    'allow_remote_setup' => false,
    // تسجيل الدخول: عدد المحاولات الفاشلة المسموحة خلال النافذة (بالدقائق)
    'login_max_attempts' => 5,
    'login_window_minutes' => 15,
    'per_page'        => 20,
];
