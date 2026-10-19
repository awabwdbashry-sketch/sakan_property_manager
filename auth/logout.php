<?php
require __DIR__ . '/../includes/bootstrap.php';
if (is_post()) { csrf_check(); do_logout(); session_start(); flash('success', 'تم تسجيل الخروج بنجاح.'); }
redirect('auth/login.php');
