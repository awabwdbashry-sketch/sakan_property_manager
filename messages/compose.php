<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('messages.send');
$kind = is_post() ? post('kind') : get('kind');
$id = is_post() ? post_int('id') : get_int('id');
if (!in_array($kind, MSG_KINDS, true)) render_error_page('غير موجود', 'نوع الرسالة غير معروف.', 404);
require_perm(msg_kind_perm($kind));
if ($kind === 'rent') sync_overdue();
$ctx = msg_context($kind, $id);
if (!$ctx) render_error_page('غير موجود', 'السجل المطلوب غير موجود.', 404);
$col = ['rent' => 'installment_id', 'contract' => 'contract_id', 'maintenance' => 'maintenance_id', 'receipt' => 'payment_id'][$kind];

if (is_post()) {
    csrf_check();
    $msg = trim(str_replace("\r\n", "\n", post('message')));
    $phone = wa_phone(post('phone'));
    $err = null;
    if ($ctx['blocked']) $err = $ctx['blocked'];
    elseif ($kind === 'rent' && (int)post('expected_cents') !== $ctx['remaining_cents']) {
        $err = 'تغيّر المبلغ المتبقي منذ تجهيز الرسالة (المتبقي الآن ' . msg_money(cents_to_dec($ctx['remaining_cents'])) . '). أعدنا تجهيز الرسالة بالمبلغ الصحيح، راجعها ووافق من جديد.';
        $msg = '';
    }
    elseif ($msg === '' || mb_strlen($msg) > 1500) $err = 'نص الرسالة مطلوب (حتى 1500 حرف).';
    elseif (!$phone) $err = 'رقم الهاتف غير صالح لواتساب. أدخله بصيغة صحيحة (مثل 0912345678 أو 249912345678).';
    if ($err) { flash('error', $err); redirect('messages/compose.php?kind=' . $kind . '&id=' . $id); }
    q("INSERT INTO reminders (kind, installment_id, payment_id, contract_id, maintenance_id, tenant_id, phone, message, expected_remaining, status, approved_by, approved_at)
       VALUES (?,?,?,?,?,?,?,?,?, 'approved', ?, NOW())",
      [$kind, $ctx['installment_id'] ?? null, $ctx['payment_id'] ?? null, $ctx['contract_id'] ?? null, $ctx['maintenance_id'] ?? null, $ctx['tenant_id'] ?? null, $phone, $msg,
       $kind === 'rent' ? cents_to_dec($ctx['remaining_cents']) : null, user_id()]);
    $rid = insert_id();
    audit('reminder.approve', 'reminder', $rid, ['kind' => $kind, 'ref' => $id]);
    flash('success', 'سُجّلت موافقتك. اضغط «فتح واتساب»، وبعد العودة سجّل نتيجة الإرسال الفعلية.');
    redirect('messages/view.php?id=' . $rid);
}

$prev = reminder_log($col, $id, 5);
$pending = array_filter($prev, fn($r) => $r['status'] === 'approved');
layout_start($ctx['title'], 'messages');
page_actions('<a class="btn" href="' . e(url($ctx['back'])) . '">رجوع للسجل</a>');
?>
<div class="grid g-main"><div>
  <?php if ($ctx['blocked']): ?><div class="alert alert-error"><?= e($ctx['blocked']) ?></div><?php endif; ?>
  <?php if (!empty($ctx['notice'])): ?><div class="alert alert-info"><?= e($ctx['notice']) ?></div><?php endif; ?>
  <?php if ($pending): ?><div class="alert alert-warning">يوجد تذكير سبق فتح واتساب له ولم تُسجَّل نتيجته بعد. راجعه قبل إنشاء رسالة جديدة لتجنب التكرار: <a href="<?= e(url('messages/view.php?id=' . (int)reset($pending)['id'])) ?>">فتح التذكير</a></div><?php endif; ?>
  <div class="card"><div class="card-head"><h2>مراجعة الرسالة قبل الإرسال</h2></div>
    <form method="post" action="<?= e(url('messages/compose.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">
      <?php if ($kind === 'rent'): ?><input type="hidden" name="expected_cents" value="<?= (int)$ctx['remaining_cents'] ?>"><?php endif; ?>
      <div class="field"><label>رقم واتساب للمستلم <span class="req">*</span></label><input type="tel" name="phone" class="ltr-in" required maxlength="30" value="<?= e($ctx['phone']) ?>"><span class="hint">يُحوَّل تلقائياً إلى الصيغة الدولية برمز الدولة المحدد في الإعدادات (<?= e(setting('whatsapp_country_code', '249')) ?>).</span></div>
      <div class="field"><label>نص الرسالة (يمكنك تعديله) <span class="req">*</span></label><textarea name="message" rows="12" maxlength="1500" required <?= $ctx['blocked'] ? 'disabled' : '' ?>><?= $ctx['blocked'] ? '' : e($ctx['text']) ?></textarea></div>
      <p class="muted" style="font-size:13px">بالضغط على الزر أدناه تُسجَّل موافقتك باسمك وتاريخك، ثم تظهر لك صفحة فيها رابط فتح واتساب. لن تُرسَل أي رسالة تلقائياً؛ الإرسال الفعلي يتم منك داخل واتساب.</p>
      <button class="btn btn-primary" type="submit" <?= $ctx['blocked'] ? 'disabled' : '' ?>><?= icon('chat') ?> أوافق وأجهّز فتح واتساب</button>
    </form>
  </div>
</div><div>
  <div class="card"><div class="card-head"><h2>بيانات السجل</h2></div><dl class="dl">
    <?php foreach ($ctx['details'] as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?></dl></div>
  <?php if ($kind === 'rent' && !$ctx['blocked']): ?>
  <div class="card"><div class="card-head"><h2>تأجيل أو إلغاء التذكير</h2></div>
    <form method="post" action="<?= e(url('messages/action.php')) ?>" style="display:grid;gap:8px"><?= csrf_field() ?><input type="hidden" name="kind" value="rent"><input type="hidden" name="id" value="<?= (int)$id ?>">
      <select name="days"><?= opts_assoc(['1' => 'غداً', '3' => 'بعد 3 أيام', '7' => 'بعد أسبوع', '14' => 'بعد أسبوعين'], '3') ?></select>
      <button class="btn" name="act" value="snooze">تأجيل التذكير</button>
      <button class="btn btn-danger" name="act" value="dismiss" data-confirm="سيُسجَّل إلغاء التذكير لهذا الاستحقاق. هل أنت متأكد؟">إلغاء التذكير</button></form></div>
  <?php endif; ?>
  <?= reminder_log_card($col, $id) ?>
</div></div>
<?php layout_end();
