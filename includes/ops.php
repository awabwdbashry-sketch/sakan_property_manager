<?php
declare(strict_types=1);

/* منطق المصروفات وبلاغات الصيانة */

const MAINT_OPEN = ['new', 'in_progress', 'waiting'];

function maint_log(int $id, string $event, ?string $note = null): void {
    q("INSERT INTO maintenance_logs (request_id, user_id, event, note) VALUES (?,?,?,?)", [$id, user_id(), $event, $note !== null ? mb_substr($note, 0, 500) : null]);
}

function maintenance_validate(array $d, int $id = 0): array {
    $err = [];
    $prop = q_one("SELECT id FROM properties WHERE id = ?", [$d['property_id']]);
    if (!$prop) $err[] = 'اختر العقار.';
    if ($d['unit_id'] && !q_val("SELECT id FROM units WHERE id = ? AND property_id = ?", [$d['unit_id'], $d['property_id']])) $err[] = 'الوحدة لا تتبع العقار المختار.';
    if ($d['tenant_id'] && !q_val("SELECT id FROM tenants WHERE id = ?", [$d['tenant_id']])) $err[] = 'المستأجر غير موجود.';
    if (mb_strlen($d['title']) < 3) $err[] = 'عنوان المشكلة مطلوب.';
    if (!isset(labels('priority')[$d['priority']])) $err[] = 'الأولوية غير صحيحة.';
    if (!valid_date($d['reported_on'])) $err[] = 'تاريخ البلاغ غير صحيح.';
    if ($d['estimated_cost'] !== '' && !valid_money($d['estimated_cost'])) $err[] = 'التكلفة التقديرية غير صحيحة.';
    if ($d['assigned_to'] && !q_val("SELECT id FROM users WHERE id = ? AND is_active = 1", [$d['assigned_to']])) $err[] = 'المسؤول عن المتابعة غير صالح.';
    if ($d['hold_unit'] && !$d['unit_id']) $err[] = 'لحجز الوحدة يجب اختيار وحدة.';
    return $err;
}

function maintenance_create(array $d): int {
    return tx(function () use ($d) {
        $err = maintenance_validate($d);
        if ($err) throw new DomainException(implode(' ', $err));
        q("INSERT INTO maintenance_requests (request_no, property_id, unit_id, tenant_id, title, description, priority, reported_on, assigned_to, status, estimated_cost, vendor, notes, hold_unit, created_by)
           VALUES (?,?,?,?,?,?,?,?,?, 'new', ?,?,?,?,?)",
          [make_maintenance_no(), $d['property_id'], $d['unit_id'] ?: null, $d['tenant_id'] ?: null, $d['title'], $d['description'] ?: null, $d['priority'], $d['reported_on'],
           $d['assigned_to'] ?: null, cents_to_dec(to_cents($d['estimated_cost'] ?: '0')), $d['vendor'] ?: null, $d['notes'] ?: null, $d['hold_unit'] ? 1 : 0, user_id()]);
        $id = insert_id();
        maint_log($id, 'created', 'تسجيل البلاغ');
        audit('maintenance.create', 'maintenance', $id, ['title' => $d['title'], 'priority' => $d['priority']]);
        if ($d['unit_id'] && $d['hold_unit']) sync_unit_status((int)$d['unit_id']);
        $no = q_val("SELECT request_no FROM maintenance_requests WHERE id = ?", [$id]);
        notify('maint_new', 'بلاغ صيانة جديد: ' . $d['title'], $no . ' — الأولوية: ' . label('priority', $d['priority']), 'maintenance/view.php?id=' . $id, 'maint_new:' . $id);
        return $id;
    });
}

function maintenance_update(int $id, array $d): void {
    tx(function () use ($id, $d) {
        $m = q_one("SELECT * FROM maintenance_requests WHERE id = ? FOR UPDATE", [$id]);
        if (!$m) throw new DomainException('البلاغ غير موجود.');
        $err = maintenance_validate($d, $id);
        if ($err) throw new DomainException(implode(' ', $err));
        q("UPDATE maintenance_requests SET property_id=?, unit_id=?, tenant_id=?, title=?, description=?, priority=?, reported_on=?, assigned_to=?, estimated_cost=?, vendor=?, notes=?, hold_unit=? WHERE id=?",
          [$d['property_id'], $d['unit_id'] ?: null, $d['tenant_id'] ?: null, $d['title'], $d['description'] ?: null, $d['priority'], $d['reported_on'], $d['assigned_to'] ?: null,
           cents_to_dec(to_cents($d['estimated_cost'] ?: '0')), $d['vendor'] ?: null, $d['notes'] ?: null, $d['hold_unit'] ? 1 : 0, $id]);
        maint_log($id, 'updated', 'تعديل بيانات البلاغ');
        audit('maintenance.update', 'maintenance', $id, ['title' => $d['title']]);
        foreach (array_unique(array_filter([(int)$m['unit_id'], (int)$d['unit_id']])) as $uid) {
            if (in_array($m['status'], MAINT_OPEN, true) && $d['hold_unit'] && (int)$d['unit_id'] === $uid) sync_unit_status($uid); else release_unit_hold($uid);
        }
    });
}

function maintenance_set_status(int $id, string $status, string $note, string $completedOn = '', string $vendor = ''): void {
    tx(function () use ($id, $status, $note, $completedOn, $vendor) {
        $m = q_one("SELECT * FROM maintenance_requests WHERE id = ? FOR UPDATE", [$id]);
        if (!$m) throw new DomainException('البلاغ غير موجود.');
        if (!isset(labels('maint_status')[$status])) throw new DomainException('الحالة غير صحيحة.');
        if ($status === 'completed') {
            if ($completedOn === '') $completedOn = today();
            if (!valid_date($completedOn)) throw new DomainException('تاريخ الإكمال غير صحيح.');
            if ($completedOn < $m['reported_on']) throw new DomainException('تاريخ الإكمال لا يمكن أن يسبق تاريخ البلاغ.');
        } else $completedOn = '';
        if ($status === 'cancelled' && mb_strlen($note) < 3) throw new DomainException('اذكر سبب الإلغاء.');
        q("UPDATE maintenance_requests SET status = ?, completed_on = ?, vendor = COALESCE(NULLIF(?, ''), vendor) WHERE id = ?", [$status, $completedOn ?: null, $vendor, $id]);
        maint_log($id, 'status:' . $status, ($m['status'] !== $status ? label('maint_status', $m['status']) . ' ← ' : '') . label('maint_status', $status) . ($note !== '' ? ' — ' . $note : ''));
        audit('maintenance.update', 'maintenance', $id, ['status' => ['from' => $m['status'], 'to' => $status]]);
        if ($m['unit_id']) { if (in_array($status, MAINT_OPEN, true) && $m['hold_unit']) sync_unit_status((int)$m['unit_id']); else release_unit_hold((int)$m['unit_id']); }
        if ($m['status'] !== $status) notify('maint_upd', 'تحديث بلاغ صيانة: ' . $m['title'], $m['request_no'] . ' ← ' . label('maint_status', $status), 'maintenance/view.php?id=' . $id, 'maint_upd:' . $id . ':' . $status);
    });
}

function maintenance_add_note(int $id, string $note): void {
    if (mb_strlen($note) < 2) throw new DomainException('اكتب الملاحظة.');
    if (!q_val("SELECT id FROM maintenance_requests WHERE id = ?", [$id])) throw new DomainException('البلاغ غير موجود.');
    maint_log($id, 'note', $note);
    audit('maintenance.update', 'maintenance', $id, ['note' => true]);
}

// ---------- المصروفات ----------
function expense_validate(array $d, int $id = 0): array {
    $err = [];
    if (!q_val("SELECT id FROM properties WHERE id = ?", [$d['property_id']])) $err[] = 'اختر العقار.';
    if ($d['unit_id'] && !q_val("SELECT id FROM units WHERE id = ? AND property_id = ?", [$d['unit_id'], $d['property_id']])) $err[] = 'الوحدة لا تتبع العقار المختار.';
    if (!q_val("SELECT id FROM expense_categories WHERE id = ?", [$d['category_id']])) $err[] = 'اختر تصنيف المصروف.';
    if (!valid_money($d['amount']) || to_cents($d['amount']) <= 0) $err[] = 'قيمة المصروف يجب أن تكون أكبر من صفر.';
    if (!valid_date($d['expense_date'])) $err[] = 'تاريخ المصروف غير صحيح.';
    elseif ($d['expense_date'] > today()) $err[] = 'لا يمكن تسجيل مصروف بتاريخ مستقبلي.';
    if (mb_strlen($d['description']) < 3) $err[] = 'وصف المصروف مطلوب.';
    if ($d['method_id'] && !q_val("SELECT id FROM payment_methods WHERE id = ?", [$d['method_id']])) $err[] = 'وسيلة الدفع غير صحيحة.';
    if ($d['maintenance_id']) {
        $m = q_one("SELECT property_id FROM maintenance_requests WHERE id = ?", [$d['maintenance_id']]);
        if (!$m) $err[] = 'بلاغ الصيانة غير موجود.';
        elseif ((int)$m['property_id'] !== (int)$d['property_id']) $err[] = 'بلاغ الصيانة يتبع عقاراً آخر.';
        elseif (q_val("SELECT id FROM expenses WHERE maintenance_id = ? AND id <> ? AND status = 'paid'", [$d['maintenance_id'], $id])) $err[] = 'يوجد مصروف مسجّل مسبقاً لهذا البلاغ؛ لا يمكن احتساب تكلفته مرتين.';
    }
    return $err;
}

function expense_create(array $d): int {
    return tx(function () use ($d) {
        $err = expense_validate($d);
        if ($err) throw new DomainException(implode(' ', $err));
        // مصروف ملغى سابق مرتبط بالبلاغ يُفك ارتباطه ليظل الفهرس الفريد صحيحاً
        if ($d['maintenance_id']) q("UPDATE expenses SET maintenance_id = NULL WHERE maintenance_id = ? AND status = 'cancelled'", [$d['maintenance_id']]);
        q("INSERT INTO expenses (expense_no, property_id, unit_id, category_id, amount, expense_date, method_id, payee, description, notes, maintenance_id, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
          [make_expense_no(), $d['property_id'], $d['unit_id'] ?: null, $d['category_id'], cents_to_dec(to_cents($d['amount'])), $d['expense_date'], $d['method_id'] ?: null,
           $d['payee'] ?: null, $d['description'], $d['notes'] ?: null, $d['maintenance_id'] ?: null, user_id()]);
        $id = insert_id();
        audit('expense.create', 'expense', $id, ['amount' => cents_to_dec(to_cents($d['amount'])), 'maintenance_id' => $d['maintenance_id'] ?: null]);
        if ($d['maintenance_id']) maint_log((int)$d['maintenance_id'], 'cost', 'تسجيل التكلفة الفعلية كمصروف بقيمة ' . cents_to_dec(to_cents($d['amount'])));
        return $id;
    });
}

function expense_update(int $id, array $d): void {
    tx(function () use ($id, $d) {
        $e = q_one("SELECT * FROM expenses WHERE id = ? FOR UPDATE", [$id]);
        if (!$e) throw new DomainException('المصروف غير موجود.');
        if ($e['status'] !== 'paid') throw new DomainException('لا يمكن تعديل مصروف ملغى.');
        $err = expense_validate($d, $id);
        if ($err) throw new DomainException(implode(' ', $err));
        if ($d['maintenance_id']) q("UPDATE expenses SET maintenance_id = NULL WHERE maintenance_id = ? AND status = 'cancelled' AND id <> ?", [$d['maintenance_id'], $id]);
        q("UPDATE expenses SET property_id=?, unit_id=?, category_id=?, amount=?, expense_date=?, method_id=?, payee=?, description=?, notes=?, maintenance_id=? WHERE id=?",
          [$d['property_id'], $d['unit_id'] ?: null, $d['category_id'], cents_to_dec(to_cents($d['amount'])), $d['expense_date'], $d['method_id'] ?: null, $d['payee'] ?: null, $d['description'], $d['notes'] ?: null, $d['maintenance_id'] ?: null, $id]);
        audit('expense.update', 'expense', $id, ['old_amount' => $e['amount'], 'new_amount' => cents_to_dec(to_cents($d['amount']))]);
    });
}

function expense_cancel(int $id, string $reason): void {
    tx(function () use ($id, $reason) {
        $e = q_one("SELECT * FROM expenses WHERE id = ? FOR UPDATE", [$id]);
        if (!$e) throw new DomainException('المصروف غير موجود.');
        if ($e['status'] !== 'paid') throw new DomainException('المصروف ملغى مسبقاً.');
        if (mb_strlen(trim($reason)) < 3) throw new DomainException('سبب الإلغاء مطلوب.');
        q("UPDATE expenses SET status = 'cancelled', cancel_reason = ? WHERE id = ?", [mb_substr($reason, 0, 250), $id]);
        audit('expense.cancel', 'expense', $id, ['amount' => $e['amount'], 'reason' => $reason]);
    });
}
