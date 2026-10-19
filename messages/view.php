<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('messages.send');
$id = get_int('id');
$r = q_one("SELECT r.*, a.full_name appr, o.full_name outn, t.full_name tname FROM reminders r LEFT JOIN users a ON a.id = r.approved_by LEFT JOIN users o ON o.id = r.outcome_by LEFT JOIN tenants t ON t.id = r.tenant_id WHERE r.id = ?", [$id]);
if (!$r) render_error_page('غير موجود', 'السجل المطلوب غير موجود.', 404);
if (!in_array($r['kind'], MSG_KINDS, true) || !can(msg_kind_perm($r['kind']))) render_error_page('غير مصرّح', 'ليست لديك صلاحية على هذا السجل.', 403);
$back = $r['installment_id'] && $r['kind'] === 'rent' ? 'payments/view.php?id=' . $r['installment_id'] : ($r['payment_id'] ? 'payments/receipt.php?id=' . $r['payment_id'] : ($r['contract_id'] ? 'contracts/view.php?id=' . $r['contract_id'] : ($r['maintenance_id'] ? 'maintenance/view.php?id=' . $r['maintenance_id'] : 'messages/')));
$isMsg = $r['message'] !== null;
layout_start('تذكير #' . $r['id'], 'messages');
page_actions('<a class="btn" href="' . e(url('messages/')) . '">السجل</a> <a class="btn" href="' . e(url($back)) . '">السجل المرتبط</a>');
?>
<div class="grid g-main"><div>
  <div class="card"><div class="card-head"><h2>الرسالة</h2><span class="badge <?= e(reminder_status_tone($r['status'])) ?>"><?= e(reminder_status_label($r['status'])) ?></span></div>
    <?php if ($isMsg): ?><pre class="msg-pre"><?= e($r['message']) ?></pre>
    <dl class="dl"><div><dt>إلى</dt><dd><?= e($r['tname'] ?: '—') ?> — <span class="ltr"><?= e($r['phone']) ?></span></dd></div>
      <div><dt>وافق المالك/المستخدم</dt><dd><?= e($r['appr'] ?: '—') ?> — <?= e(fmt_datetime($r['approved_at'])) ?></dd></div>
      <?php if ($r['outcome_at']): ?><div><dt>سُجّلت النتيجة بواسطة</dt><dd><?= e($r['outn'] ?: '—') ?> — <?= e(fmt_datetime($r['outcome_at'])) ?></dd></div><?php endif; ?>
      <?php if ($r['outcome_note']): ?><div><dt>ملاحظة</dt><dd><?= e($r['outcome_note']) ?></dd></div><?php endif; ?></dl>
    <?php else: ?><p class="muted">إجراء بلا رسالة (<?= e(reminder_status_label($r['status'])) ?>)<?= $r['snooze_until'] ? ' حتى ' . e(fmt_date($r['snooze_until'])) : '' ?>.</p><?php endif; ?>
  </div>
</div><div>
  <?php if ($isMsg && $r['status'] === 'approved'): ?>
  <div class="card"><div class="card-head"><h2>الخطوة التالية</h2></div>
    <p>1) افتح واتساب وراجع الرسالة ثم اضغط إرسال بنفسك.<br>2) عُد إلى هنا وسجّل ما حدث فعلاً.</p>
    <a class="btn btn-primary" target="_blank" rel="noopener" href="<?= e(wa_url((string)$r['phone'], (string)$r['message'])) ?>"><?= icon('chat') ?> فتح واتساب بالرسالة</a>
    <p class="muted" style="font-size:12.5px;margin-top:10px">فتح واتساب لا يعني أن الرسالة أُرسلت؛ النظام لا يسجّل الإرسال تلقائياً.</p></div>
  <div class="card"><div class="card-head"><h2>تسجيل النتيجة</h2></div>
    <form method="post" action="<?= e(url('messages/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="reminder_id" value="<?= (int)$r['id'] ?>">
      <div class="field"><label>ملاحظة (اختيارية)</label><input type="text" name="note" maxlength="300" placeholder="مثال: ردّ المستأجر ووعد بالسداد غداً"></div>
      <div class="btn-row"><button class="btn btn-primary" name="act" value="sent" data-confirm="تأكيد: أنت أرسلت الرسالة فعلاً من واتساب؟">أرسلتُ الرسالة فعلاً</button>
      <button class="btn" name="act" value="not_sent">لم أُرسل</button></div></form></div>
  <?php elseif ($isMsg && $r['status'] === 'not_sent'): ?>
  <div class="card"><p class="muted">سُجّلت كغير مُرسلة. لإعادة المحاولة جهّز رسالة جديدة من السجل المرتبط.</p></div>
  <?php endif; ?>
</div></div>
<?php layout_end();
