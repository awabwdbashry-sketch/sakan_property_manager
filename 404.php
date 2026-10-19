<?php
require __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
if (db_ready() && current_user()) { render_error_page('الصفحة غير موجودة', 'الرابط الذي طلبته غير موجود أو تم نقله.', 404); }
render_error_page('الصفحة غير موجودة', 'الرابط الذي طلبته غير موجود أو تم نقله.', 404);
