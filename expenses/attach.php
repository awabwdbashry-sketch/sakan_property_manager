<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('expenses.manage');
if (!is_post()) redirect('expenses/');
csrf_check();
$id = post_int('id');
if (!q_val("SELECT id FROM expenses WHERE id = ?", [$id])) { flash('error', 'المصروف غير موجود.'); redirect('expenses/'); }
[$ok, $uerr] = save_uploads('docs', 'expense', $id, 'document');
if ($uerr) flash('error', implode(' | ', $uerr)); elseif ($ok) flash('success', 'تم رفع المرفقات.'); else flash('warning', 'لم يتم اختيار ملف.');
redirect('expenses/view.php?id=' . $id);
