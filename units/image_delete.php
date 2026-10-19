<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) redirect('units/');
$uid = post_int('unit_id');
handle_attachment_delete('unit', $uid, 'units/form.php?id=' . $uid);
