<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) redirect('properties/');
$pid = post_int('property_id');
handle_attachment_delete('property', $pid, 'properties/form.php?id=' . $pid);
