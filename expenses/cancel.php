<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('expenses.manage');
if (!is_post()) redirect('expenses/');
csrf_check();
$id = post_int('id');
try { expense_cancel($id, post('reason')); flash('success', 'تم إلغاء المصروف.'); } catch (DomainException $e) { flash('error', $e->getMessage()); }
redirect('expenses/view.php?id=' . $id);
