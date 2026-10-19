<?php
declare(strict_types=1);

/** أيقونات SVG خفيفة (stroke) */
function icon(string $n): string {
    static $p = [
        'home'    => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>',
        'building'=> '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',
        'door'    => '<path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17"/><path d="M4 21h16"/><circle cx="14.5" cy="12.5" r=".8" fill="currentColor"/>',
        'users'   => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18 14.8c1.8.7 3 2.4 3 5.2"/>',
        'file'    => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'wallet'  => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3"/><rect x="3" y="8" width="18" height="12" rx="2"/><circle cx="16.5" cy="14" r="1.2" fill="currentColor"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'tool'    => '<path d="M14.5 6.5a4 4 0 0 0-5 5L4 17l3 3 5.5-5.5a4 4 0 0 0 5-5l-2.5 2.5-2.5-.5-.5-2.5z"/>',
        'chart'   => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-8"/>',
        'chat'    => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
        'bell'    =>'<path d="M6 17V11a6 6 0 1 1 12 0v6l1.5 2h-15z"/><path d="M10 21h4"/>',
        'cog'     => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>',
        'shield'  => '<path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'log'     => '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'search'  => '<circle cx="11" cy="11" r="6"/><path d="m20 20-4-4"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'logout'  => '<path d="M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'print'   => '<path d="M7 9V3h10v6"/><rect x="4" y="9" width="16" height="8" rx="1.5"/><path d="M7 14h10v7H7z"/>',
        'download'=> '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>',
        'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'eye'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'chev'    => '<path d="m9 6 6 6-6 6"/>',
        'check'   => '<path d="m5 12 5 5 9-10"/>',
        'x'       => '<path d="M6 6l12 12M18 6 6 18"/>',
        'alert'   => '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4M12 17.5v.01"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

function nav_items(): array {
    return [
        ['dashboard/', 'لوحة التحكم', 'home', 'dashboard.view', 'dashboard'],
        ['properties/', 'العقارات', 'building', 'properties.view', 'properties'],
        ['units/', 'الوحدات', 'door', 'units.view', 'units'],
        ['tenants/', 'المستأجرون', 'users', 'tenants.view', 'tenants'],
        ['contracts/', 'العقود', 'file', 'contracts.view', 'contracts'],
        ['payments/', 'الاستحقاقات والتحصيل', 'wallet', 'payments.view', 'payments'],
        ['expenses/', 'المصروفات', 'receipt', 'expenses.view', 'expenses'],
        ['maintenance/', 'الصيانة والبلاغات', 'tool', 'maintenance.view', 'maintenance'],
        ['reports/', 'التقارير', 'chart', 'reports.view', 'reports'],
        ['notifications/', 'الإشعارات', 'bell', 'notifications.view', 'notifications'],
        ['messages/', 'سجل الرسائل والتذكيرات', 'chat', 'messages.send', 'messages'],
        null,
        ['users/', 'المستخدمون والصلاحيات', 'shield', 'users.manage', 'users'],
        ['settings/', 'الإعدادات', 'cog', 'settings.manage', 'settings'],
        ['audit/', 'سجل التدقيق', 'log', 'audit.view', 'audit'],
    ];
}

/** يبدأ صفحة داخلية. $active = مفتاح القسم في القائمة. */
function layout_start(string $title, string $active = '', array $opts = []): void {
    $u = current_user();
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
    }
    $org = (string)setting('org_name', 'سَكَن');
    $unread = $u ? unread_notifications_count() : 0;
    ?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> | <?= e($org) ?></title>
<link rel="preload" href="<?= e(asset('fonts/tajawal-arabic-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if (!empty($opts['print'])): ?><link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>" media="print"><?php endif; ?>
</head>
<body class="app" data-base="<?= e(base_url()) ?>">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url('dashboard/')) ?>">
    <?php $logoId = (int)setting('logo_attachment_id', 0); ?>
    <span class="brand-mark"<?= $logoId ? ' style="background:#fff;border:1px solid var(--line);overflow:hidden"' : '' ?>><?php if ($logoId): ?><img src="<?= e(attachment_url($logoId)) ?>" alt="" style="width:100%;height:100%;object-fit:contain"><?php else: echo icon('home'); endif; ?></span>
    <span class="brand-text"><b>سَكَن</b><small>SAKAN Property Manager</small></span>
  </a>
  <nav class="nav">
<?php foreach (nav_items() as $it):
        if ($it === null) { echo '<div class="nav-sep"></div>'; continue; }
        if (!can($it[3])) continue; ?>
    <a class="nav-link<?= $active === $it[4] ? ' active' : '' ?>" href="<?= e(url($it[0])) ?>">
      <?= icon($it[2]) ?><span><?= e($it[1]) ?></span>
      <?php if ($it[4] === 'notifications' && $unread > 0): ?><em class="nav-count"><?= $unread > 99 ? '99+' : $unread ?></em><?php endif; ?>
    </a>
<?php endforeach; ?>
  </nav>
  <div class="sidebar-foot"><?= e($org) ?></div>
</aside>
<div class="scrim" id="scrim"></div>
<div class="main">
  <header class="topbar">
    <button class="icon-btn" id="sbToggle" type="button" aria-label="القائمة"><?= icon('menu') ?></button>
    <h1 class="page-title"><?= e($title) ?></h1>
    <div class="topbar-tools">
<?php if ($u && can('notifications.view')): ?>
      <div class="bell-wrap">
        <a class="icon-btn bell" id="bellBtn" href="<?= e(url('notifications/')) ?>" aria-label="الإشعارات" aria-haspopup="true"><?= icon('bell') ?><?php if ($unread > 0): ?><span class="dot"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?></a>
        <div class="bell-pop" id="bellPop" hidden>
          <div class="bell-head"><b>آخر الإشعارات</b><a href="<?= e(url('notifications/')) ?>">عرض الكل</a></div>
<?php $bn = q_all("SELECT id, type, title, body, link, is_read, created_at FROM notifications WHERE handled_at IS NULL AND (snoozed_until IS NULL OR snoozed_until <= NOW()) ORDER BY is_read, id DESC LIMIT 6");
      if (!$bn): ?><div class="bell-empty">لا توجد إشعارات تحتاج إجراءً.</div><?php endif;
      foreach ($bn as $n): ?>
          <a class="bell-item <?= $n['is_read'] ? '' : 'is-new' ?>" href="<?= e(url($n['link'] ?: 'notifications/')) ?>"><b><?= e($n['title']) ?></b><small><?= e($n['body']) ?></small></a>
<?php endforeach; ?>
        </div>
      </div>
<?php endif; ?>
<?php if ($u): ?>
      <div class="user-menu">
        <button class="user-btn" type="button" id="userBtn"><span class="avatar"><?= e(mb_substr($u['full_name'], 0, 1)) ?></span><span class="user-meta"><b><?= e($u['full_name']) ?></b><small><?= e($u['role_name']) ?></small></span></button>
        <div class="user-pop" id="userPop" hidden>
          <a href="<?= e(url('users/profile.php')) ?>">حسابي وكلمة المرور</a>
          <form method="post" action="<?= e(url('auth/logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout') ?> تسجيل الخروج</button></form>
        </div>
      </div>
<?php endif; ?>
    </div>
  </header>
  <main class="content">
<?php foreach (flash_get() as $f): ?>
    <div class="alert alert-<?= e($f['t']) ?>" role="alert"><?= e($f['m']) ?><button type="button" class="alert-x" aria-label="إغلاق">×</button></div>
<?php endforeach;
}

function layout_end(): void {
    ?>
  </main>
  <footer class="foot">سَكَن | SAKAN Property Manager — نظام إدارة عقارات للمالك</footer>
</div>
<div class="modal" id="confirmModal" hidden>
  <div class="modal-card" role="dialog" aria-modal="true">
    <h3 id="cmTitle">تأكيد العملية</h3>
    <p id="cmText"></p>
    <div class="modal-actions"><button type="button" class="btn btn-ghost" id="cmNo">تراجع</button><button type="button" class="btn btn-danger" id="cmYes">تأكيد</button></div>
  </div>
</div>
<div class="lightbox" id="lightbox" hidden><button type="button" class="lb-close" aria-label="إغلاق">×</button><img alt=""></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
<?php
}

// ---------- مكوّنات واجهة مشتركة ----------
function page_actions(string $html): void { echo '<div class="page-actions">' . $html . '</div>'; }

function empty_state(string $title, string $text, string $btnLabel = '', string $btnHref = ''): string {
    $b = $btnLabel !== '' ? '<a class="btn btn-primary" href="' . e(url($btnHref)) . '">' . icon('plus') . ' ' . e($btnLabel) . '</a>' : '';
    return '<div class="empty-state"><div class="empty-ico">' . icon('building') . '</div><h3>' . e($title) . '</h3><p>' . e($text) . '</p>' . $b . '</div>';
}

function stat_card(string $label, string $value, string $sub = '', string $tone = ''): string {
    return '<div class="stat ' . e($tone) . '"><div class="stat-label">' . e($label) . '</div><div class="stat-value">' . $value . '</div>' . ($sub !== '' ? '<div class="stat-sub">' . $sub . '</div>' : '') . '</div>';
}

/** زر/نموذج لعملية حساسة مع نافذة تأكيد */
function confirm_form(string $action, array $fields, string $label, string $confirmText, string $class = 'btn btn-sm btn-danger'): string {
    $h = '<form method="post" action="' . e(url($action)) . '" class="inline-form" data-confirm="' . e($confirmText) . '">' . csrf_field();
    foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
    return $h . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
}

function field_error_box(array $errors): string {
    if (!$errors) return '';
    $h = '<div class="alert alert-error" role="alert"><ul>';
    foreach ($errors as $er) $h .= '<li>' . e($er) . '</li>';
    return $h . '</ul></div>';
}

/** معرض صور مصغّرة: لا يُفتح Lightbox إلا عند الضغط */
function image_gallery(array $images, bool $canDelete = false, string $deleteAction = '', array $extra = []): string {
    if (!$images) return '<p class="muted">لا توجد صور مرفوعة.</p>';
    $h = '<div class="gallery">';
    foreach ($images as $im) {
        $h .= '<figure class="thumb"><img src="' . e(attachment_url((int)$im['id'])) . '" alt="' . e($im['original_name']) . '" loading="lazy" data-lightbox>';
        if ($canDelete) {
            $h .= '<form method="post" action="' . e(url($deleteAction)) . '" data-confirm="هل تريد حذف هذه الصورة نهائياً؟">' . csrf_field() . '<input type="hidden" name="attachment_id" value="' . (int)$im['id'] . '">';
            foreach ($extra as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
            $h .= '<button class="thumb-del" aria-label="حذف">×</button></form>';
        }
        $h .= '</figure>';
    }
    return $h . '</div>';
}

function doc_list(array $docs, bool $canDelete = false, string $deleteAction = '', array $extra = []): string {
    if (!$docs) return '<p class="muted">لا توجد مستندات مرفقة.</p>';
    $h = '<ul class="doc-list">';
    foreach ($docs as $d) {
        $h .= '<li><a href="' . e(attachment_url((int)$d['id'], true)) . '">' . icon('file') . ' ' . e($d['original_name']) . '</a><small>' . round($d['size'] / 1024) . ' ك.ب</small>';
        if ($canDelete) {
            $h .= '<form method="post" action="' . e(url($deleteAction)) . '" data-confirm="هل تريد حذف هذا المرفق؟">' . csrf_field() . '<input type="hidden" name="attachment_id" value="' . (int)$d['id'] . '">';
            foreach ($extra as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
            $h .= '<button class="link-danger">حذف</button></form>';
        }
        $h .= '</li>';
    }
    return $h . '</ul>';
}

/** معالجة حذف مرفق بشكل موحّد بعد التحقق من الصلاحية. */
function handle_attachment_delete(string $entity, int $entityId, string $redirectTo): void {
    $perms = attachment_perms($entity);
    require_perm($perms[1]);
    csrf_check();
    $aid = post_int('attachment_id');
    $a = q_one("SELECT id FROM attachments WHERE id = ? AND entity_type = ? AND entity_id = ?", [$aid, $entity, $entityId]);
    if ($a && delete_attachment($aid)) flash('success', 'تم حذف المرفق.'); else flash('error', 'المرفق غير موجود.');
    redirect($redirectTo);
}

// ---------- صفحات الدخول والإعداد (بدون قائمة جانبية) ----------
function db_ready(): bool {
    try { db(); return (bool)q_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"); }
    catch (Throwable $e) { return false; }
}
function auth_page_start(string $title, string $sub = ''): void {
    if (!headers_sent()) { header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); }
    ?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | سَكَن</title>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body class="auth-body"><div class="auth-card">
  <svg class="auth-skyline" width="220" height="64" viewBox="0 0 220 64" fill="none" aria-hidden="true"><path d="M0 62h220" stroke="#E4DACB" stroke-width="2"/><rect x="18" y="26" width="34" height="36" rx="3" fill="#E5C99A"/><rect x="60" y="8" width="42" height="54" rx="3" fill="#C96F50"/><rect x="110" y="20" width="36" height="42" rx="3" fill="#78947A"/><rect x="154" y="32" width="46" height="30" rx="3" fill="#EFE8DD" stroke="#E4DACB"/><g fill="#F6F0E6"><rect x="68" y="16" width="8" height="8" rx="1.5"/><rect x="86" y="16" width="8" height="8" rx="1.5"/><rect x="68" y="32" width="8" height="8" rx="1.5"/><rect x="86" y="32" width="8" height="8" rx="1.5"/><rect x="118" y="28" width="7" height="7" rx="1.5"/><rect x="132" y="28" width="7" height="7" rx="1.5"/><rect x="118" y="42" width="7" height="7" rx="1.5"/><rect x="132" y="42" width="7" height="7" rx="1.5"/></g></svg>
  <div class="brand"><span class="brand-mark"><?= icon('home') ?></span><span class="brand-text"><b>سَكَن</b><small>SAKAN Property Manager</small></span></div>
  <h2><?= e($title) ?></h2>
  <?php if ($sub !== ''): ?><p class="sub"><?= e($sub) ?></p><?php endif;
    foreach (flash_get() as $f): ?><div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endforeach;
}
function auth_page_end(): void { echo '</div><script src="' . e(asset('js/app.js')) . '"></script></body></html>'; }
function db_not_ready_page(): never {
    auth_page_start('قاعدة البيانات غير جاهزة', 'لم يتم الاتصال بقاعدة البيانات أو لم تُستورد الجداول بعد.');
    echo '<div class="alert alert-warning"><div>١) افتح phpMyAdmin واستورد الملف <b class="ltr">database/schema.sql</b>.<br>٢) تأكد من بيانات الاتصال في <b class="ltr">config/local.php</b>.<br>٣) أعد تحميل هذه الصفحة.</div></div>';
    auth_page_end(); exit;
}

/**
 * أزرار موحّدة لصفحات المستندات (إيصال/عقد/كشف استحقاق/بلاغ صيانة).
 * ملاحظة صريحة: «حفظ PDF» يتم من نافذة الطباعة في المتصفح (صفحة A4 منسّقة)، وليس عبر مولّد PDF على الخادم.
 */
function doc_actions(string $backUrl, string $backLabel, string $extraHtml = ''): void {
    page_actions('<button class="btn btn-primary" type="button" onclick="window.print()">' . icon('print') . ' طباعة / حفظ كـ PDF</button> '
        . $extraHtml . ' <a class="btn" href="' . e(url($backUrl)) . '">' . e($backLabel) . '</a>'
        . '<span class="doc-hint">لحفظ PDF: اضغط الزر ثم اختر «حفظ كـ PDF» كوجهة الطباعة (حجم A4).</span>');
}
