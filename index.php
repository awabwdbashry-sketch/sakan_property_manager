<?php
require __DIR__ . '/includes/bootstrap.php';
if (!db_ready()) db_not_ready_page();
if (needs_setup()) redirect('setup/');
if (current_user()) redirect('dashboard/');
redirect('auth/login.php');
