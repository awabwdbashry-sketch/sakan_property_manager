<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) redirect('contracts/');
$cid = post_int('contract_id');
handle_attachment_delete('contract', $cid, 'contracts/view.php?id=' . $cid);
