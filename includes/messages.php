<?php
declare(strict_types=1);
/**
 * رسائل واتساب اليدوية: تجهيز النص من بيانات السجل الفعلية فقط.
 * لا يُرسل شيء تلقائياً: يوافق المالك، ثم يفتح رابط wa.me، ثم يسجّل بنفسه نتيجة الإرسال.
 */

const MSG_KINDS = ['rent', 'contract', 'maintenance', 'receipt'];

function msg_kind_perm(string $kind): string {
    return ['rent' => 'payments.view', 'contract' => 'contracts.view', 'maintenance' => 'maintenance.view', 'receipt' => 'payments.view'][$kind] ?? '';
}

/** يحوّل الرقم إلى صيغة دولية بأرقام فقط (بدون +) أو null إن كان غير صالح */
function wa_phone(string $raw): ?string {
    $d = preg_replace('/\D+/', '', strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])) ?? '';
    if ($d === '') return null;
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    elseif (str_starts_with($d, '0')) $d = preg_replace('/\D+/', '', (string)setting('whatsapp_country_code', '249')) . substr($d, 1);
    return (strlen($d) >= 9 && strlen($d) <= 15) ? $d : null;
}

function wa_url(string $phone, string $text): string {
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
}

function msg_money(string|int|float $v): string {
    return number_format((float)$v, 2, '.', ',') . ' ' . (string)setting('currency', 'ج.س');
}

function msg_sender(): string {
    $o = trim((string)setting('owner_name', ''));
    return $o !== '' ? $o : (string)setting('org_name', '');
}

/**
 * سياق الرسالة من قاعدة البيانات الآن. يعيد null إن لم يوجد السجل.
 * المفاتيح: kind, id, tenant_id, tenant, phone, text, title, back, details[label=>value], remaining_cents (للإيجار فقط),
 * installment_id|payment_id|contract_id|maintenance_id, notice (تحذير اختياري)
 */
function msg_context(string $kind, int $id): ?array {
    $greet = static fn(?string $n): string => 'السلام عليكم' . ($n ? ' أستاذ/ة ' . $n : '') . '،';
    $sign = "\n\nشكراً لكم،\n" . msg_sender();

    if ($kind === 'rent') {
        $i = q_one("SELECT i.*, t.full_name, t.phone, u.unit_no, p.name pname, c.contract_no FROM installments i JOIN tenants t ON t.id = i.tenant_id
                    JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id JOIN contracts c ON c.id = i.contract_id WHERE i.id = ?", [$id]);
        if (!$i) return null;
        $rem = to_cents($i['remaining']);
        $due = $i['due_date'] >= date('Y-m-d') ? 'سيحل موعده بتاريخ ' . fmt_date($i['due_date']) : 'قد حل موعده';
        $text = $greet($i['full_name']) . "\n\nنود تذكيركم بأن استحقاق الإيجار الخاص بالوحدة " . $i['unit_no'] . ' عن الفترة ' . fmt_date($i['period_start']) . ' إلى ' . fmt_date($i['period_end']) . ' ' . $due . '.'
              . (to_cents($i['paid_amount']) > 0 ? "\n\nالمدفوع حتى الآن: " . msg_money($i['paid_amount']) . '.' : '')
              . "\n\nالمبلغ المتبقي: " . msg_money($i['remaining']) . "\n\nنرجو التكرم بمراجعة الاستحقاق والتواصل معنا عند الحاجة." . $sign;
        return ['kind' => $kind, 'id' => $id, 'installment_id' => $id, 'contract_id' => (int)$i['contract_id'], 'tenant_id' => (int)$i['tenant_id'], 'tenant' => $i['full_name'], 'phone' => (string)$i['phone'],
                'text' => $text, 'title' => 'تذكير باستحقاق ' . $i['ref_no'], 'back' => 'payments/view.php?id=' . $id, 'remaining_cents' => $rem,
                'blocked' => ($rem <= 0 || $i['status'] === 'cancelled') ? ($i['status'] === 'cancelled' ? 'هذا الاستحقاق ملغى، لا يوجد مبلغ مطلوب.' : 'هذا الاستحقاق مسدد بالكامل؛ لا يوجد مبلغ متبقٍ للمطالبة به.') : null,
                'details' => ['المستأجر' => $i['full_name'], 'الهاتف' => $i['phone'] ?: '—', 'العقار / الوحدة' => $i['pname'] . ' / ' . $i['unit_no'], 'رقم العقد' => $i['contract_no'],
                              'الفترة' => fmt_date($i['period_start']) . ' ← ' . fmt_date($i['period_end']), 'تاريخ الاستحقاق' => fmt_date($i['due_date']),
                              'المبلغ الأصلي' => msg_money($i['amount']), 'المدفوع' => msg_money($i['paid_amount']), 'المتبقي (محقَّق الآن)' => msg_money($i['remaining'])]];
    }
    if ($kind === 'contract') {
        $c = q_one("SELECT c.*, t.full_name, t.phone, u.unit_no, p.name pname FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id WHERE c.id = ?", [$id]);
        if (!$c) return null;
        $ended = $c['end_date'] < date('Y-m-d') || in_array($c['status'], ['expired', 'terminated'], true);
        $text = $greet($c['full_name']) . "\n\nنود إعلامكم بأن عقد الإيجار رقم " . $c['contract_no'] . ' الخاص بالوحدة ' . $c['unit_no'] . ' في ' . $c['pname']
              . ($ended ? ' انتهى بتاريخ ' . fmt_date($c['end_date']) . '.' : ' ينتهي بتاريخ ' . fmt_date($c['end_date']) . '.')
              . "\n\nنرجو التواصل معنا لمناقشة " . ($ended ? 'تجديد العقد أو ترتيبات التسليم.' : 'تجديد العقد أو ترتيبات إنهائه.') . $sign;
        return ['kind' => $kind, 'id' => $id, 'contract_id' => $id, 'tenant_id' => (int)$c['tenant_id'], 'tenant' => $c['full_name'], 'phone' => (string)$c['phone'], 'text' => $text,
                'title' => 'رسالة بخصوص العقد ' . $c['contract_no'], 'back' => 'contracts/view.php?id=' . $id, 'remaining_cents' => null, 'blocked' => null,
                'details' => ['المستأجر' => $c['full_name'], 'الهاتف' => $c['phone'] ?: '—', 'العقار / الوحدة' => $c['pname'] . ' / ' . $c['unit_no'], 'رقم العقد' => $c['contract_no'],
                              'البداية' => fmt_date($c['start_date']), 'النهاية' => fmt_date($c['end_date']), 'حالة العقد' => label('contract_status', $c['status'])]];
    }
    if ($kind === 'maintenance') {
        $m = q_one("SELECT m.*, t.full_name, t.phone, u.unit_no, p.name pname FROM maintenance_requests m JOIN properties p ON p.id = m.property_id LEFT JOIN units u ON u.id = m.unit_id LEFT JOIN tenants t ON t.id = m.tenant_id WHERE m.id = ?", [$id]);
        if (!$m) return null;
        $text = $greet($m['full_name']) . "\n\nبخصوص بلاغ الصيانة رقم " . $m['request_no'] . ' (' . $m['title'] . ')' . ($m['unit_no'] ? ' الخاص بالوحدة ' . $m['unit_no'] : '') . ' في ' . $m['pname']
              . ".\n\nالحالة الحالية: " . label('maint_status', $m['status']) . ($m['completed_on'] ? ' — أُنجز بتاريخ ' . fmt_date($m['completed_on']) : '') . '.' . $sign;
        return ['kind' => $kind, 'id' => $id, 'maintenance_id' => $id, 'tenant_id' => $m['tenant_id'] ? (int)$m['tenant_id'] : null, 'tenant' => (string)$m['full_name'], 'phone' => (string)$m['phone'], 'text' => $text,
                'title' => 'رسالة بخصوص بلاغ الصيانة ' . $m['request_no'], 'back' => 'maintenance/view.php?id=' . $id, 'remaining_cents' => null, 'blocked' => null,
                'details' => ['المستأجر' => $m['full_name'] ?: '— (غير مرتبط بمستأجر؛ أدخل الرقم يدوياً)', 'الهاتف' => $m['phone'] ?: '—', 'العقار / الوحدة' => $m['pname'] . ' / ' . ($m['unit_no'] ?: '—'),
                              'البلاغ' => $m['request_no'] . ' — ' . $m['title'], 'الحالة' => label('maint_status', $m['status']), 'تاريخ البلاغ' => fmt_date($m['reported_on'])]];
    }
    if ($kind === 'receipt') {
        $r = q_one("SELECT pay.*, rc.receipt_no, i.id iid, i.period_start, i.period_end, i.remaining inst_rem, i.contract_id, i.tenant_id, t.full_name, t.phone, u.unit_no, p.name pname
                    FROM payments pay JOIN receipts rc ON rc.payment_id = pay.id JOIN installments i ON i.id = pay.installment_id JOIN tenants t ON t.id = i.tenant_id
                    JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id WHERE pay.id = ?", [$id]);
        if (!$r) return null;
        $text = $greet($r['full_name']) . "\n\nنفيدكم باستلام مبلغ " . msg_money($r['amount']) . ' بتاريخ ' . fmt_date($r['paid_on']) . ' عن إيجار الوحدة ' . $r['unit_no'] . ' للفترة ' . fmt_date($r['period_start']) . ' إلى ' . fmt_date($r['period_end'])
              . '، إيصال رقم ' . $r['receipt_no'] . '.' . (to_cents($r['inst_rem']) > 0 ? "\n\nالمتبقي على هذا الاستحقاق: " . msg_money($r['inst_rem']) . '.' : "\n\nتم سداد هذا الاستحقاق بالكامل.") . $sign;
        return ['kind' => $kind, 'id' => $id, 'payment_id' => $id, 'installment_id' => (int)$r['iid'], 'contract_id' => (int)$r['contract_id'], 'tenant_id' => (int)$r['tenant_id'], 'tenant' => $r['full_name'], 'phone' => (string)$r['phone'], 'text' => $text,
                'title' => 'إشعار بإيصال ' . $r['receipt_no'], 'back' => 'payments/receipt.php?id=' . $id, 'remaining_cents' => null,
                'blocked' => $r['status'] === 'void' ? 'هذه الدفعة ملغاة؛ لا يُرسل إشعار استلام بها.' : null,
                'notice' => 'واتساب لا يرفق ملف الإيصال تلقائياً: الرسالة تحمل رقم الإيصال ومبلغه فقط. إن أردت إرسال الملف فاحفظه PDF من صفحة الإيصال وأرفقه بنفسك داخل واتساب.',
                'details' => ['المستأجر' => $r['full_name'], 'الهاتف' => $r['phone'] ?: '—', 'العقار / الوحدة' => $r['pname'] . ' / ' . $r['unit_no'], 'الإيصال' => $r['receipt_no'], 'المبلغ' => msg_money($r['amount']), 'تاريخ الدفع' => fmt_date($r['paid_on'])]];
    }
    return null;
}

/** سجل الإجراءات السابقة المرتبطة بسجل معيّن */
function reminder_log(string $col, int $id, int $limit = 20): array {
    if (!in_array($col, ['installment_id', 'payment_id', 'contract_id', 'maintenance_id'], true)) return [];
    return q_all("SELECT r.*, a.full_name appr, o.full_name outn FROM reminders r LEFT JOIN users a ON a.id = r.approved_by LEFT JOIN users o ON o.id = r.outcome_by
                  WHERE r.$col = ? ORDER BY r.id DESC LIMIT " . (int)$limit, [$id]);
}

function reminder_status_label(string $s): string {
    return ['approved' => 'فُتح واتساب — بانتظار تسجيل النتيجة', 'sent' => 'أُرسلت (سجّلها المالك)', 'not_sent' => 'لم تُرسل', 'snoozed' => 'مؤجَّل', 'dismissed' => 'أُلغي التذكير'][$s] ?? $s;
}
function reminder_status_tone(string $s): string {
    return ['approved' => 'b-amber', 'sent' => 'b-sage', 'not_sent' => 'b-gray', 'snoozed' => 'b-sand', 'dismissed' => 'b-gray'][$s] ?? 'b-gray';
}

/** بطاقة «الإجراءات السابقة» الموحّدة لصفحات المستندات */
function reminder_log_card(string $col, int $id): string {
    $rows = reminder_log($col, $id);
    $h = '<div class="card"><div class="card-head"><h2>سجل الإجراءات والرسائل</h2></div>';
    if (!$rows) return $h . '<div class="empty-inline">لا توجد إجراءات سابقة على هذا السجل.</div></div>';
    $h .= '<div class="table-wrap"><table class="tbl tbl-sm"><thead><tr><th>التاريخ</th><th>النوع</th><th>الحالة</th><th>المستخدم</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr><td>' . e(fmt_datetime($r['created_at'])) . '</td><td>' . e(['rent' => 'تذكير استحقاق', 'contract' => 'عقد', 'maintenance' => 'صيانة', 'receipt' => 'إيصال'][$r['kind']] ?? $r['kind']) . '</td>'
            . '<td><span class="badge ' . e(reminder_status_tone($r['status'])) . '">' . e(reminder_status_label($r['status'])) . '</span>' . ($r['outcome_note'] ? '<br><small class="muted">' . e($r['outcome_note']) . '</small>' : '')
            . ($r['snooze_until'] ? '<br><small class="muted">حتى ' . e(fmt_date($r['snooze_until'])) . '</small>' : '') . '</td>'
            . '<td>' . e($r['outn'] ?: $r['appr'] ?: '—') . '</td><td>' . (can('messages.send') && $r['message'] !== null ? '<a class="btn btn-sm" href="' . e(url('messages/view.php?id=' . (int)$r['id'])) . '">فتح</a>' : '') . '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/** زر «واتساب» لصفحات المستندات إن كانت للمستخدم الصلاحيتان */
function wa_button(string $kind, int $id, string $text = 'مراجعة وإرسال عبر واتساب'): string {
    if (!can('messages.send') || !can(msg_kind_perm($kind))) return '';
    return '<a class="btn btn-sage" href="' . e(url('messages/compose.php?kind=' . $kind . '&id=' . $id)) . '">' . icon('chat') . ' ' . e($text) . '</a>';
}
