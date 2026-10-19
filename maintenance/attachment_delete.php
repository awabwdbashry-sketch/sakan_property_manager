<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) redirect('maintenance/');
$mid = post_int('request_id');
handle_attachment_delete('maintenance', $mid, 'maintenance/view.php?id=' . $mid);
