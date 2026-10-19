<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('expenses.view');
$id = get_int('id');
$x = q_one("SELECT e.*, p.name pname, c.name cname, u.unit_no, m.name mname, us.full_name uname, mr.request_no, mr.title mtitle
            FROM expenses e JOIN properties p ON p.id = e.property_id JOIN expense_categories c ON c.id = e.category_id LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN payment_methods m ON m.id = e.method_id LEFT JOIN users us ON us.id = e.recorded_by LEFT JOIN maintenance_requests mr ON mr.id = e.maintenance_id WHERE e.id = ?", [$id]);
if (!$x) render_error_page('غير موجود', 'المصروف المطلوب غير موجود.', 404);
$docs = attachments_for('expense', $id);
layout_start('المصروف ' . $x['expense_no'], 'expenses', ['print' => true]);
$act = '<a class="btn" href="' . e(url('expenses/')) . '">رجوع للقائمة</a> <button class="btn" onclick="window.print()">' . icon('print') . ' طباعة</button>';
if (can('expenses.manage') && $x['status'] === 'paid') {
    $act .= ' <a class="btn" href="' . e(url('expenses/form.php?id=' . $id)) . '">' . icon('edit') . ' تعديل</a>';
}
page_actions($act);
?>
<div class="grid g-main"><div class="card">
  <div class="card-head"><h2>تفاصيل المصروف</h2><?= badge('exp_status', $x['status']) ?></div>
  <dl class="dl">
    <div><dt>الرقم</dt><dd class="ltr"><?= e($x['expense_no']) ?></dd></div><div><dt>التاريخ</dt><dd><?= fmt_date($x['expense_date']) ?></dd></div>
    <div><dt>القيمة</dt><dd><?= money($x['amount']) ?></dd></div><div><dt>التصنيف</dt><dd><?= e($x['cname']) ?></dd></div>
    <div><dt>العقار</dt><dd><a href="<?= e(url('properties/view.php?id=' . $x['property_id'])) ?>"><?= e($x['pname']) ?></a></dd></div><div><dt>الوحدة</dt><dd><?= e($x['unit_no']) ?: '—' ?></dd></div>
    <div><dt>المستفيد</dt><dd><?= e($x['payee']) ?: '—' ?></dd></div><div><dt>طريقة الدفع</dt><dd><?= e($x['mname']) ?: '—' ?></dd></div>
    <div><dt>سجّله</dt><dd><?= e($x['uname']) ?: '—' ?></dd></div>
    <?php if ($x['request_no']): ?><div><dt>بلاغ الصيانة المرتبط</dt><dd><a class="ltr" href="<?= e(url('maintenance/view.php?id=' . $x['maintenance_id'])) ?>"><?= e($x['request_no']) ?></a> — <?= e($x['mtitle']) ?></dd></div><?php endif; ?>
  </dl><hr><p style="margin:0"><?= e($x['description']) ?></p>
  <?php if ($x['notes']): ?><p class="muted" style="white-space:pre-line"><?= e($x['notes']) ?></p><?php endif; ?>
  <?php if ($x['status'] === 'cancelled'): ?><div class="alert alert-warning" style="margin-top:14px">مصروف ملغى — السبب: <?= e($x['cancel_reason']) ?></div><?php endif; ?>
</div><div>
  <div class="card"><h2>المرفقات</h2><?= doc_list($docs, can('expenses.manage'), 'expenses/attachment_delete.php', ['expense_id' => $id]) ?>
  <?php if (can('expenses.manage') && $x['status'] === 'paid'): ?><form method="post" action="<?= e(url('expenses/attach.php')) ?>" enctype="multipart/form-data" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><div class="field"><input type="file" name="docs[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"></div><div class="form-actions" style="border:0;padding:0;margin-top:10px"><button class="btn btn-sm" type="submit">رفع</button></div></form><?php endif; ?></div>
  <?php if (can('expenses.manage') && $x['status'] === 'paid'): ?><div class="card"><h2>إلغاء المصروف</h2><p class="muted">لا تُحذف المصروفات؛ يُلغى المصروف بسبب موثّق ويُستبعد من التقارير.</p>
    <form method="post" action="<?= e(url('expenses/cancel.php')) ?>" data-confirm="سيتم إلغاء هذا المصروف واستبعاده من التقارير. هل أنت متأكد؟"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <div class="field"><input type="text" name="reason" required minlength="3" maxlength="250" placeholder="سبب الإلغاء (مطلوب)"></div><div class="form-actions" style="border:0;padding:0;margin-top:10px"><button class="btn btn-danger btn-sm" type="submit">إلغاء المصروف</button></div></form></div><?php endif; ?>
</div></div>
<?php layout_end();
