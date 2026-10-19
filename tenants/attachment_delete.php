<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) redirect('tenants/');
require_perm('tenants.sensitive');
$tid = post_int('tenant_id');
handle_attachment_delete('tenant', $tid, 'tenants/form.php?id=' . $tid);
