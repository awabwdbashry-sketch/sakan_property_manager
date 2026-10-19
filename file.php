<?php
// تنزيل/عرض المرفقات الخاصة بعد التحقق من الصلاحية. لا يوجد وصول مباشر لمجلد storage.
require __DIR__ . '/includes/bootstrap.php';
require_login();
$id = get_int('id');
$a = $id > 0 ? q_one("SELECT * FROM attachments WHERE id = ?", [$id]) : null;
if (!$a) { render_error_page('غير موجود', 'المرفق المطلوب غير موجود.', 404); }
$perms = attachment_perms($a['entity_type']);
if (!$perms || !can($perms[0])) { render_error_page('غير مصرّح', 'ليست لديك صلاحية لعرض هذا المرفق.', 403); }
// مستندات المستأجر (هوية وغيرها) تتطلب صلاحية البيانات الحساسة
if ($a['entity_type'] === 'tenant' && $a['kind'] === 'document' && !can('tenants.sensitive')) {
    render_error_page('غير مصرّح', 'ليست لديك صلاحية لعرض مستندات المستأجر.', 403);
}
$path = storage_dir() . '/' . $a['stored_name'];
$real = realpath($path);
$base = realpath(storage_dir());
if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
    render_error_page('الملف مفقود', 'الملف غير موجود على الخادم. قد يكون قد نُقل أو حُذف.', 404);
}
$dl = get('dl') === '1';
$inlineOk = in_array($a['mime'], ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
header('Content-Type: ' . $a['mime']);
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, max-age=3600');
$fname = rawurlencode($a['original_name']);
header('Content-Disposition: ' . (($dl || !$inlineOk) ? 'attachment' : 'inline') . "; filename*=UTF-8''" . $fname);
readfile($real);
