<?php
declare(strict_types=1);

/*
 * منطق الأعمال المالي: العقود والاستحقاقات والدفعات والإيصالات.
 * جميع المبالغ تُحسب بالسنتات (أعداد صحيحة) داخل PHP وتُخزَّن DECIMAL(14,2).
 *
 * سياسات موثّقة:
 *  - فترات السداد تبدأ من تاريخ بداية العقد وتتقدم بأشهر كاملة (مع الحفاظ على نهاية الشهر).
 *  - مبلغ الفترة = الإيجار الشهري × عدد أشهر الفترة. الفترة الأخيرة غير الكاملة تُحسب بالتناسب على الأيام.
 *  - تاريخ الاستحقاق = يوم الاستحقاق في شهر بداية الفترة (يُقصّ إلى آخر يوم في الشهر القصير)،
 *    وإن سبق بداية الفترة فيكون تاريخ بداية الفترة نفسه.
 *  - التأمين لا يدخل في الإيجار المحصّل ويُسجَّل منفصلاً.
 */

const PERIOD_MONTHS = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'annual' => 12];

function day_before(string $d): string { return date('Y-m-d', strtotime($d . ' -1 day')); }
function day_after(string $d): string { return date('Y-m-d', strtotime($d . ' +1 day')); }

/** عدد الأشهر الكاملة + الأيام المتبقية بين تاريخين (النهاية شاملة) */
function months_span(string $start, string $endInclusive): array {
    $endEx = day_after($endInclusive);
    $m = 0;
    while (add_months($start, $m + 1) <= $endEx) $m++;
    $from = add_months($start, $m);
    $rem = days_between($from, $endEx);
    $mlen = max(1, days_between($from, add_months($start, $m + 1)));
    return [$m, $rem, $mlen];
}

function due_date_for(string $periodStart, int $dueDay): string {
    [$y, $m] = array_map('intval', explode('-', $periodStart));
    $last = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
    $due = sprintf('%04d-%02d-%02d', $y, $m, min(max(1, $dueDay), $last));
    return max($periodStart, $due);
}

/** جدول الاستحقاقات المتوقع للعقد (دون إدخال شيء في القاعدة) */
function build_schedule(string $start, string $end, string $period, int $rentCents, int $dueDay): array {
    $pm = PERIOD_MONTHS[$period] ?? 1;
    $out = [];
    for ($i = 0; $i < 600; $i++) {
        $ps = add_months($start, $i * $pm);
        if ($ps > $end) break;
        $peFull = day_before(add_months($start, ($i + 1) * $pm));
        $pe = min($peFull, $end);
        if ($pe === $peFull) {
            $amt = $rentCents * $pm;
        } else {
            [$m, $rem, $mlen] = months_span($ps, $pe);
            $amt = (int)round($rentCents * ($m + $rem / $mlen));
        }
        $out[] = ['seq' => $i + 1, 'period_start' => $ps, 'period_end' => $pe, 'due_date' => due_date_for($ps, $dueDay), 'amount_cents' => $amt];
    }
    return $out;
}

// ---------- أرقام مرجعية ----------
function make_contract_no(): string {
    return (string)setting('contract_prefix', 'CN') . '-' . date('Y') . '-' . str_pad((string)next_counter('contract'), 5, '0', STR_PAD_LEFT);
}
function make_txn_no(): string { return 'TX-' . date('Y') . '-' . str_pad((string)next_counter('payment'), 6, '0', STR_PAD_LEFT); }
function make_receipt_no(): string {
    return (string)setting('receipt_prefix', 'RC') . '-' . date('Y') . '-' . str_pad((string)next_counter('receipt'), 6, '0', STR_PAD_LEFT);
}
function make_expense_no(): string { return 'EX-' . date('Y') . '-' . str_pad((string)next_counter('expense'), 6, '0', STR_PAD_LEFT); }
function make_maintenance_no(): string { return 'MR-' . date('Y') . '-' . str_pad((string)next_counter('maintenance'), 5, '0', STR_PAD_LEFT); }

// ---------- حالة الوحدة ----------
function sync_unit_status(int $unitId): void {
    $u = q_one("SELECT id, status FROM units WHERE id = ? FOR UPDATE", [$unitId]);
    if (!$u) return;
    $hasActive = (int)q_val("SELECT COUNT(*) FROM contracts WHERE unit_id = ? AND status = 'active'", [$unitId]) > 0;
    $new = $u['status'];
    if ($hasActive) {
        $new = 'rented';
    } else {
        $hold = (int)q_val("SELECT COUNT(*) FROM maintenance_requests WHERE unit_id = ? AND hold_unit = 1 AND status IN ('new','in_progress','waiting')", [$unitId]) > 0;
        if ($hold) $new = 'maintenance';
        elseif ($u['status'] === 'rented') $new = 'vacant';
        // حالة «تحت الصيانة» اليدوية تبقى كما هي؛ تُحرَّر عبر release_unit_hold عند إغلاق بلاغ الصيانة
    }
    if ($new !== $u['status']) {
        q("UPDATE units SET status = ? WHERE id = ?", [$new, $unitId]);
        audit('unit.status', 'unit', $unitId, ['from' => $u['status'], 'to' => $new, 'auto' => true]);
    }
}

/** عند إغلاق آخر بلاغ صيانة يحجز الوحدة: تعود الوحدة «شاغرة» إن لم يكن لها عقد نشط */
function release_unit_hold(int $unitId): void {
    $u = q_one("SELECT status FROM units WHERE id = ? FOR UPDATE", [$unitId]);
    if (!$u || $u['status'] !== 'maintenance') return;
    $hold = (int)q_val("SELECT COUNT(*) FROM maintenance_requests WHERE unit_id = ? AND hold_unit = 1 AND status IN ('new','in_progress','waiting')", [$unitId]);
    $active = (int)q_val("SELECT COUNT(*) FROM contracts WHERE unit_id = ? AND status = 'active'", [$unitId]);
    if ($hold === 0 && $active === 0) {
        q("UPDATE units SET status = 'vacant' WHERE id = ?", [$unitId]);
        audit('unit.status', 'unit', $unitId, ['from' => 'maintenance', 'to' => 'vacant', 'auto' => true]);
    }
}

// ---------- العقود ----------
function contract_overlap(int $unitId, string $start, string $end, int $excludeId = 0): ?array {
    return q_one("SELECT id, contract_no, start_date, end_date FROM contracts
                  WHERE unit_id = ? AND status = 'active' AND id <> ? AND start_date <= ? AND end_date >= ? LIMIT 1",
                 [$unitId, $excludeId, $end, $start]);
}

function contract_log(int $contractId, string $type, string $desc, $old = null, $new = null): void {
    q("INSERT INTO contract_events (contract_id, event_type, description, old_values, new_values, user_id) VALUES (?,?,?,?,?,?)",
      [$contractId, $type, $desc,
       $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
       $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE), user_id()]);
}

/** التحقق من صلاحية بيانات العقد. يرجع مصفوفة أخطاء. */
function validate_contract_input(array $d, int $excludeId = 0): array {
    $err = [];
    if ($d['tenant_id'] <= 0 || !q_val("SELECT id FROM tenants WHERE id = ?", [$d['tenant_id']])) $err[] = 'اختر المستأجر.';
    $unit = $d['unit_id'] > 0 ? q_one("SELECT u.*, p.status pstatus FROM units u JOIN properties p ON p.id = u.property_id WHERE u.id = ?", [$d['unit_id']]) : null;
    if (!$unit) $err[] = 'اختر الوحدة.';
    elseif ($unit['pstatus'] !== 'active' || (int)$unit['is_archived'] === 1) $err[] = 'لا يمكن إنشاء عقد على عقار أو وحدة مؤرشفة.';
    if (!valid_date($d['start_date']) || !valid_date($d['end_date'])) $err[] = 'أدخل تاريخي البداية والنهاية بشكل صحيح.';
    elseif ($d['end_date'] < $d['start_date']) $err[] = 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.';
    if (!valid_money($d['rent_amount']) || to_cents($d['rent_amount']) <= 0) $err[] = 'قيمة الإيجار الشهري يجب أن تكون أكبر من صفر.';
    if (!isset(PERIOD_MONTHS[$d['period']])) $err[] = 'دورية السداد غير صحيحة.';
    if ($d['deposit_amount'] !== '' && !valid_money($d['deposit_amount'])) $err[] = 'مبلغ التأمين غير صحيح.';
    if ($d['due_day'] < 1 || $d['due_day'] > 31) $err[] = 'يوم الاستحقاق يجب أن يكون بين 1 و31.';
    if ($unit && !$err) {
        if (in_array($unit['status'], ['maintenance', 'unavailable'], true)) {
            $err[] = 'الوحدة حالتها «' . label('unit_status', $unit['status']) . '». عالج حالة الوحدة أولاً قبل التأجير.';
        }
        if ($ov = contract_overlap((int)$d['unit_id'], $d['start_date'], $d['end_date'], $excludeId)) {
            $err[] = 'يوجد عقد نشط متداخل على الوحدة نفسها (' . $ov['contract_no'] . ' من ' . fmt_date($ov['start_date']) . ' إلى ' . fmt_date($ov['end_date']) . ').';
        }
    }
    return $err;
}

function create_contract(array $d, ?int $renewedFrom = null): int {
    return tx(function () use ($d, $renewedFrom) {
        q("SELECT id FROM units WHERE id = ? FOR UPDATE", [$d['unit_id']]); // قفل لمنع التسابق
        $err = validate_contract_input($d);
        if ($err) throw new DomainException(implode(' ', $err));
        $unit = q_one("SELECT property_id FROM units WHERE id = ?", [$d['unit_id']]);
        q("INSERT INTO contracts (contract_no, tenant_id, property_id, unit_id, start_date, end_date, rent_amount, period, deposit_amount, due_day, terms, notes, status, renewed_from_id, created_by)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'draft', ?, ?)",
          [make_contract_no(), $d['tenant_id'], $unit['property_id'], $d['unit_id'], $d['start_date'], $d['end_date'],
           cents_to_dec(to_cents($d['rent_amount'])), $d['period'], cents_to_dec(to_cents($d['deposit_amount'] ?: '0')),
           $d['due_day'], $d['terms'] ?: null, $d['notes'] ?: null, $renewedFrom, user_id()]);
        $id = insert_id();
        contract_log($id, 'created', $renewedFrom ? 'إنشاء عقد تجديد' : 'إنشاء العقد كمسودة');
        audit('contract.create', 'contract', $id, ['unit_id' => $d['unit_id'], 'tenant_id' => $d['tenant_id'], 'renewal' => (bool)$renewedFrom]);
        return $id;
    });
}

function activate_contract(int $id): void {
    tx(function () use ($id) {
        $c = q_one("SELECT * FROM contracts WHERE id = ? FOR UPDATE", [$id]);
        if (!$c) throw new DomainException('العقد غير موجود.');
        if ($c['status'] !== 'draft') throw new DomainException('يمكن تفعيل العقود في حالة «مسودة» فقط.');
        q("SELECT id FROM units WHERE id = ? FOR UPDATE", [$c['unit_id']]);
        $err = validate_contract_input([
            'tenant_id' => (int)$c['tenant_id'], 'unit_id' => (int)$c['unit_id'], 'start_date' => $c['start_date'], 'end_date' => $c['end_date'],
            'rent_amount' => $c['rent_amount'], 'period' => $c['period'], 'deposit_amount' => $c['deposit_amount'], 'due_day' => (int)$c['due_day'],
        ], $id);
        if ($err) throw new DomainException(implode(' ', $err));
        $t = q_one("SELECT status FROM tenants WHERE id = ?", [$c['tenant_id']]);
        if ($t['status'] !== 'active') throw new DomainException('المستأجر غير نشط.');
        q("UPDATE contracts SET status = 'active', activated_at = NOW() WHERE id = ?", [$id]);
        $n = generate_installments($id);
        sync_unit_status((int)$c['unit_id']);
        contract_log($id, 'activated', 'تفعيل العقد وإنشاء ' . $n . ' استحقاق');
        audit('contract.activate', 'contract', $id, ['installments' => $n]);
    });
}

/** يولّد الاستحقاقات للعقد النشط. آمن عند التكرار: لا ينشئ المكرر أبداً. */
function generate_installments(int $contractId): int {
    return tx(function () use ($contractId) {
        $c = q_one("SELECT * FROM contracts WHERE id = ? FOR UPDATE", [$contractId]);
        if (!$c || $c['status'] !== 'active') return 0;
        $sched = build_schedule($c['start_date'], $c['end_date'], $c['period'], to_cents($c['rent_amount']), (int)$c['due_day']);
        $created = 0;
        foreach ($sched as $s) {
            $ref = $c['contract_no'] . '/' . str_pad((string)$s['seq'], 3, '0', STR_PAD_LEFT);
            $st = q("INSERT IGNORE INTO installments (ref_no, contract_id, seq_no, tenant_id, property_id, unit_id, period_start, period_end, due_date, amount, paid_amount, remaining, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,0.00,?, ?)",
                    [$ref, $contractId, $s['seq'], $c['tenant_id'], $c['property_id'], $c['unit_id'], $s['period_start'], $s['period_end'], $s['due_date'],
                     cents_to_dec($s['amount_cents']), cents_to_dec($s['amount_cents']), $s['due_date'] < today() ? 'overdue' : 'due']);
            $created += $st->rowCount();
        }
        if ($created) audit('contract.generate', 'contract', $contractId, ['created' => $created]);
        return $created;
    });
}

function terminate_contract(int $id, string $date, string $reason): void {
    tx(function () use ($id, $date, $reason) {
        $c = q_one("SELECT * FROM contracts WHERE id = ? FOR UPDATE", [$id]);
        if (!$c) throw new DomainException('العقد غير موجود.');
        if ($c['status'] !== 'active') throw new DomainException('يمكن إنهاء العقود النشطة فقط.');
        if (!valid_date($date)) throw new DomainException('تاريخ الإنهاء غير صحيح.');
        if ($date < $c['start_date']) throw new DomainException('تاريخ الإنهاء لا يمكن أن يسبق تاريخ بداية العقد.');
        if (mb_strlen($reason) < 3) throw new DomainException('اذكر سبب الإنهاء.');
        q("UPDATE contracts SET status = 'terminated', terminated_on = ?, termination_reason = ? WHERE id = ?", [$date, mb_substr($reason, 0, 250), $id]);
        // إلغاء الاستحقاقات غير المدفوعة التي تبدأ فتراتها بعد تاريخ الإنهاء (لا نمسّ ما دُفع)
        $cancelled = q("UPDATE installments SET status = 'cancelled', remaining = 0.00 WHERE contract_id = ? AND period_start > ? AND paid_amount = 0 AND status <> 'cancelled'", [$id, $date])->rowCount();
        sync_unit_status((int)$c['unit_id']);
        contract_log($id, 'terminated', 'إنهاء العقد بتاريخ ' . $date . ' — ' . $reason, ['status' => 'active'], ['status' => 'terminated', 'cancelled_installments' => $cancelled]);
        audit('contract.terminate', 'contract', $id, ['date' => $date, 'reason' => $reason, 'cancelled_installments' => $cancelled]);
    });
}

/** تجديد: ينشئ عقداً جديداً يبدأ بعد نهاية القديم ويفعّله، ويربط السجلين. */
function renew_contract(int $oldId, string $newEnd, string $rent, string $period, string $dueDayStr, string $notes): int {
    return tx(function () use ($oldId, $newEnd, $rent, $period, $dueDayStr, $notes) {
        $o = q_one("SELECT * FROM contracts WHERE id = ? FOR UPDATE", [$oldId]);
        if (!$o) throw new DomainException('العقد غير موجود.');
        if (!in_array($o['status'], ['active', 'expired'], true)) throw new DomainException('يمكن تجديد العقود النشطة أو المنتهية فقط.');
        if (q_val("SELECT COUNT(*) FROM contracts WHERE renewed_from_id = ? AND status IN ('draft','active')", [$oldId]) > 0) {
            throw new DomainException('تم تجديد هذا العقد مسبقاً.');
        }
        $start = day_after($o['end_date']);
        $newId = create_contract([
            'tenant_id' => (int)$o['tenant_id'], 'unit_id' => (int)$o['unit_id'], 'start_date' => $start, 'end_date' => $newEnd,
            'rent_amount' => $rent, 'period' => $period, 'deposit_amount' => $o['deposit_amount'], 'due_day' => (int)$dueDayStr,
            'terms' => $o['terms'], 'notes' => $notes,
        ], $oldId);
        activate_contract($newId);
        contract_log($oldId, 'renewed', 'تجديد العقد بعقد جديد', ['end_date' => $o['end_date'], 'rent' => $o['rent_amount']], ['new_contract_id' => $newId, 'new_end' => $newEnd, 'new_rent' => cents_to_dec(to_cents($rent))]);
        audit('contract.renew', 'contract', $oldId, ['new_contract_id' => $newId]);
        return $newId;
    });
}

/** تعديل العقد: المسودة كاملة، والنشط الشروط والملاحظات فقط (حفاظاً على السجل المالي) */
function update_contract(int $id, array $d): void {
    tx(function () use ($id, $d) {
        $c = q_one("SELECT * FROM contracts WHERE id = ? FOR UPDATE", [$id]);
        if (!$c) throw new DomainException('العقد غير موجود.');
        if ($c['status'] === 'draft') {
            q("SELECT id FROM units WHERE id = ? FOR UPDATE", [$d['unit_id']]);
            $err = validate_contract_input($d, $id);
            if ($err) throw new DomainException(implode(' ', $err));
            $unit = q_one("SELECT property_id FROM units WHERE id = ?", [$d['unit_id']]);
            q("UPDATE contracts SET tenant_id=?, property_id=?, unit_id=?, start_date=?, end_date=?, rent_amount=?, period=?, deposit_amount=?, due_day=?, terms=?, notes=? WHERE id=?",
              [$d['tenant_id'], $unit['property_id'], $d['unit_id'], $d['start_date'], $d['end_date'], cents_to_dec(to_cents($d['rent_amount'])), $d['period'],
               cents_to_dec(to_cents($d['deposit_amount'] ?: '0')), $d['due_day'], $d['terms'] ?: null, $d['notes'] ?: null, $id]);
        } elseif ($c['status'] === 'active') {
            q("UPDATE contracts SET terms = ?, notes = ? WHERE id = ?", [$d['terms'] ?: null, $d['notes'] ?: null, $id]);
        } else {
            throw new DomainException('لا يمكن تعديل عقد ' . label('contract_status', $c['status']) . '.');
        }
        contract_log($id, 'updated', 'تعديل بيانات العقد', ['terms' => $c['terms'], 'notes' => $c['notes'], 'rent' => $c['rent_amount'], 'end' => $c['end_date']], ['terms' => $d['terms'], 'notes' => $d['notes'], 'rent' => $d['rent_amount'] ?? null, 'end' => $d['end_date'] ?? null]);
        audit('contract.update', 'contract', $id, []);
    });
}

/** انتهاء العقود التي تجاوز تاريخ نهايتها + تحديث حالة الوحدات */
function expire_contracts(): int {
    $rows = q_all("SELECT id, unit_id FROM contracts WHERE status = 'active' AND end_date < CURDATE()");
    foreach ($rows as $r) {
        tx(function () use ($r) {
            q("UPDATE contracts SET status = 'expired' WHERE id = ? AND status = 'active'", [$r['id']]);
            contract_log((int)$r['id'], 'expired', 'انتهاء مدة العقد تلقائياً');
            audit('contract.expire', 'contract', (int)$r['id'], []);
            sync_unit_status((int)$r['unit_id']);
        });
    }
    return count($rows);
}

// ---------- الاستحقاقات والدفعات ----------
/** يعيد حساب المدفوع والمتبقي والحالة من الدفعات الصحيحة */
function refresh_installment(int $id): void {
    $i = q_one("SELECT * FROM installments WHERE id = ? FOR UPDATE", [$id]);
    if (!$i || $i['status'] === 'cancelled') return;
    $paid = to_cents(q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE installment_id = ? AND status = 'valid'", [$id]));
    $amount = to_cents($i['amount']);
    $rem = max(0, $amount - $paid);
    if ($rem === 0) $status = 'paid';
    elseif ($i['due_date'] < today()) $status = 'overdue';
    elseif ($paid > 0) $status = 'partial';
    else $status = 'due';
    q("UPDATE installments SET paid_amount = ?, remaining = ?, status = ? WHERE id = ?", [cents_to_dec($paid), cents_to_dec($rem), $status, $id]);
}

/** تحويل المستحق إلى متأخر عند فوات تاريخه (يُستدعى قبل عرض الصفحات المالية وفي المهمة المجدولة) */
function sync_overdue(): void {
    q("UPDATE installments SET status = 'overdue' WHERE status IN ('due','partial') AND remaining > 0 AND due_date < CURDATE()");
}

/**
 * تسجيل دفعة. تُنفَّذ كلها في معاملة واحدة: الدفعة + تحديث الاستحقاق + الإيصال.
 * $idemKey يمنع التكرار عند إعادة إرسال النموذج: يُرجع الدفعة نفسها دون إنشاء أخرى.
 * @return array [paymentId, receiptId, duplicate(bool)]
 */
function record_payment(int $installmentId, string $amountStr, string $paidOn, int $methodId, string $reference, string $notes, string $idemKey): array {
    if (!preg_match('/^[a-f0-9]{16,64}$/', $idemKey)) throw new DomainException('طلب غير صالح، أعد تحميل الصفحة.');
    try {
        return tx(function () use ($installmentId, $amountStr, $paidOn, $methodId, $reference, $notes, $idemKey) {
            if ($ex = q_one("SELECT p.id, r.id rid FROM payments p LEFT JOIN receipts r ON r.payment_id = p.id WHERE p.idempotency_key = ?", [$idemKey])) {
                return [(int)$ex['id'], (int)$ex['rid'], true];
            }
            $i = q_one("SELECT * FROM installments WHERE id = ? FOR UPDATE", [$installmentId]);
            if (!$i) throw new DomainException('الاستحقاق غير موجود.');
            if ($i['status'] === 'cancelled') throw new DomainException('هذا الاستحقاق ملغى ولا يقبل دفعات.');
            if (!valid_money($amountStr)) throw new DomainException('أدخل مبلغاً صحيحاً (حتى منزلتين عشريتين).');
            $amt = to_cents($amountStr);
            if ($amt <= 0) throw new DomainException('المبلغ يجب أن يكون أكبر من صفر.');
            $rem = to_cents($i['remaining']);
            if ($rem <= 0) throw new DomainException('هذا الاستحقاق مسدد بالكامل.');
            if ($amt > $rem) throw new DomainException('المبلغ (' . money(cents_to_dec($amt), false) . ') أكبر من المتبقي (' . money(cents_to_dec($rem), false) . ').');
            if (!valid_date($paidOn)) throw new DomainException('تاريخ الدفع غير صحيح.');
            if ($paidOn > today()) throw new DomainException('لا يمكن تسجيل دفعة بتاريخ مستقبلي.');
            if (!q_val("SELECT id FROM payment_methods WHERE id = ? AND is_active = 1", [$methodId])) throw new DomainException('اختر وسيلة دفع صحيحة.');

            q("INSERT INTO payments (txn_no, installment_id, amount, paid_on, method_id, reference_no, notes, idempotency_key, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)",
              [make_txn_no(), $installmentId, cents_to_dec($amt), $paidOn, $methodId, mb_substr($reference, 0, 80) ?: null, mb_substr($notes, 0, 250) ?: null, $idemKey, user_id()]);
            $pid = insert_id();
            q("INSERT INTO receipts (receipt_no, payment_id, issued_by) VALUES (?,?,?)", [make_receipt_no(), $pid, user_id()]);
            $rid = insert_id();
            refresh_installment($installmentId);
            audit('payment.create', 'payment', $pid, ['installment' => $i['ref_no'], 'amount' => cents_to_dec($amt)]);
            return [$pid, $rid, false];
        });
    } catch (PDOException $e) {
        // سباق نادر: نفس المفتاح أُدخل من طلب متزامن
        if ((int)($e->errorInfo[1] ?? 0) === 1062 && ($ex = q_one("SELECT p.id, r.id rid FROM payments p LEFT JOIN receipts r ON r.payment_id = p.id WHERE p.idempotency_key = ?", [$idemKey]))) {
            return [(int)$ex['id'], (int)$ex['rid'], true];
        }
        throw $e;
    }
}

/** إلغاء دفعة بتصحيح موثّق (لا حذف). */
function void_payment(int $paymentId, string $reason): void {
    tx(function () use ($paymentId, $reason) {
        $p = q_one("SELECT * FROM payments WHERE id = ? FOR UPDATE", [$paymentId]);
        if (!$p) throw new DomainException('الدفعة غير موجودة.');
        if ($p['status'] === 'void') throw new DomainException('الدفعة ملغاة مسبقاً.');
        if (mb_strlen(trim($reason)) < 3) throw new DomainException('سبب الإلغاء مطلوب.');
        q("UPDATE payments SET status = 'void', void_reason = ?, voided_by = ?, voided_at = NOW() WHERE id = ?", [mb_substr($reason, 0, 250), user_id(), $paymentId]);
        refresh_installment((int)$p['installment_id']);
        audit('payment.void', 'payment', $paymentId, ['amount' => $p['amount'], 'reason' => $reason, 'txn' => $p['txn_no']]);
    });
}

// ---------- التأمين ----------
function record_deposit(int $contractId, string $kind, string $amountStr, string $date, int $methodId, string $notes): void {
    tx(function () use ($contractId, $kind, $amountStr, $date, $methodId, $notes) {
        $c = q_one("SELECT id FROM contracts WHERE id = ? FOR UPDATE", [$contractId]);
        if (!$c) throw new DomainException('العقد غير موجود.');
        if (!in_array($kind, ['received', 'refunded', 'deducted'], true)) throw new DomainException('نوع الحركة غير صحيح.');
        if (!valid_money($amountStr) || to_cents($amountStr) <= 0) throw new DomainException('مبلغ غير صحيح.');
        if (!valid_date($date) || $date > today()) throw new DomainException('تاريخ غير صحيح.');
        $held = deposit_balance_cents($contractId);
        $amt = to_cents($amountStr);
        if ($kind !== 'received' && $amt > $held) throw new DomainException('المبلغ أكبر من رصيد التأمين المحتجز (' . money(cents_to_dec($held), false) . ').');
        q("INSERT INTO deposits (contract_id, kind, amount, txn_date, method_id, notes, recorded_by) VALUES (?,?,?,?,?,?,?)",
          [$contractId, $kind, cents_to_dec($amt), $date, $methodId ?: null, mb_substr($notes, 0, 250) ?: null, user_id()]);
        audit('deposit.create', 'contract', $contractId, ['kind' => $kind, 'amount' => cents_to_dec($amt)]);
    });
}
function deposit_balance_cents(int $contractId): int {
    $r = q_one("SELECT COALESCE(SUM(CASE WHEN kind='received' THEN amount ELSE -amount END),0) b FROM deposits WHERE contract_id = ?", [$contractId]);
    return to_cents($r['b']);
}

// ---------- تجميع مالي مشترك ----------
/** شرط SQL للمتأخرات (يُحسب لحظياً بغض النظر عن الحالة المخزنة) */
const SQL_OVERDUE = "(i.status <> 'cancelled' AND i.remaining > 0 AND i.due_date < CURDATE())";
