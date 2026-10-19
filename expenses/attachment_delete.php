<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) redirect('expenses/');
$eid = post_int('expense_id');
handle_attachment_delete('expense', $eid, 'expenses/view.php?id=' . $eid);
