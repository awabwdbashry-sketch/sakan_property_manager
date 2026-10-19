<?php
declare(strict_types=1);

/** فهرس التقارير: النوع => [العنوان، الوصف، الفلاتر المتاحة] */
function report_catalog(): array {
    return [
        'properties'  => ['العقارات والوحدات', 'قائمة الوحدات مع حالتها وإيجارها الأساسي.', ['property', 'status_unit']],
        'occupancy'   => ['الإشغال', 'عدد الوحدات المؤجرة والشاغرة ونسبة الإشغال لكل عقار.', ['property']],
        'contracts'   => ['العقود', 'العقود النشطة والمنتهية والقريبة من الانتهاء.', ['property', 'status_contract', 'range_overlap']],
        'rents_due'   => ['الإيجارات المستحقة', 'الاستحقاقات التي يقع تاريخها ضمن الفترة، مع المدفوع والمتبقي.', ['property', 'range', 'status_inst']],
        'collected'   => ['المبالغ المحصّلة فعلياً', 'الدفعات الصحيحة المسجلة ضمن الفترة (حسب تاريخ الدفع).', ['property', 'range', 'method']],
        'arrears'     => ['المتأخرات', 'الأقساط التي فات موعدها وما زال لها متبقٍ، حتى اليوم.', ['property']],
        'expenses'    => ['المصروفات المدفوعة', 'المصروفات المدفوعة ضمن الفترة حسب العقار والتصنيف.', ['property', 'range', 'category']],
        'cashflow'    => ['صافي التدفق النقدي', 'المحصّل فعلياً ناقص المصروفات المدفوعة لكل شهر.', ['property', 'range']],
        'monthly'     => ['التحصيل الشهري', 'المستحق مقابل المحصّل ونسبة التحصيل لكل شهر.', ['property', 'range']],
        'maintenance' => ['الصيانة وتكاليفها', 'بلاغات الصيانة مع التكلفة التقديرية والفعلية (من المصروف المرتبط).', ['property', 'range', 'status_maint']],
    ];
}

/** يعيد [cols=>[key=>label], rows, totals(key=>value), money(keys), notes] */
function report_data(string $type, array $f): array {
    $pid = (int)($f['property_id'] ?? 0); $from = $f['from']; $to = $f['to'];
    $cur = (string)setting('currency', 'ج.س');
    switch ($type) {
        case 'properties':
            $w = 'u.is_archived = 0'; $p = [];
            if ($pid) { $w .= ' AND u.property_id = ?'; $p[] = $pid; }
            if (isset(labels('unit_status')[$f['status_unit'] ?? ''])) { $w .= ' AND u.status = ?'; $p[] = $f['status_unit']; }
            $rows = q_all("SELECT p.name pname, u.unit_no, u.floor, u.unit_type, u.rooms, u.base_rent, u.status,
                    (SELECT t.full_name FROM contracts c JOIN tenants t ON t.id = c.tenant_id WHERE c.unit_id = u.id AND c.status = 'active' LIMIT 1) tenant
                    FROM units u JOIN properties p ON p.id = u.property_id WHERE $w ORDER BY p.name, u.floor, u.unit_no", $p);
            foreach ($rows as &$r) $r['status'] = label('unit_status', $r['status']); unset($r);
            return ['cols' => ['pname' => 'العقار', 'unit_no' => 'الوحدة', 'floor' => 'الطابق', 'unit_type' => 'النوع', 'rooms' => 'الغرف', 'base_rent' => 'الإيجار الأساسي', 'status' => 'الحالة', 'tenant' => 'المستأجر الحالي'],
                    'rows' => $rows, 'totals' => ['base_rent' => array_sum(array_column($rows, 'base_rent'))], 'money' => ['base_rent'],
                    'notes' => 'الإيجار الأساسي هو السعر المرجعي للوحدة وليس بالضرورة قيمة العقد الفعلي.'];
        case 'occupancy':
            $rows = q_all("SELECT p.name pname, COUNT(u.id) total, COALESCE(SUM(u.status='rented'),0) rented, COALESCE(SUM(u.status='vacant'),0) vacant,
                    COALESCE(SUM(u.status='maintenance'),0) maint, COALESCE(SUM(u.status IN ('reserved','unavailable')),0) other
                    FROM properties p LEFT JOIN units u ON u.property_id = p.id AND u.is_archived = 0 WHERE p.status = 'active'" . ($pid ? ' AND p.id = ?' : '') . " GROUP BY p.id, p.name ORDER BY p.name", $pid ? [$pid] : []);
            $T = ['total' => 0, 'rented' => 0, 'vacant' => 0, 'maint' => 0, 'other' => 0];
            foreach ($rows as &$r) { $r['occ'] = $r['total'] ? round($r['rented'] / $r['total'] * 100, 1) . '%' : '0%'; foreach ($T as $k => $_) $T[$k] += (int)$r[$k]; } unset($r);
            $T['occ'] = $T['total'] ? round($T['rented'] / $T['total'] * 100, 1) . '%' : '0%';
            return ['cols' => ['pname' => 'العقار', 'total' => 'الوحدات', 'rented' => 'مؤجرة', 'vacant' => 'شاغرة', 'maint' => 'تحت الصيانة', 'other' => 'محجوزة/غير متاحة', 'occ' => 'نسبة الإشغال'],
                    'rows' => $rows, 'totals' => $T, 'money' => [], 'notes' => 'نسبة الإشغال = الوحدات المؤجرة ÷ إجمالي الوحدات غير المؤرشفة.'];
        case 'contracts':
            $w = '1=1'; $p = [];
            if ($pid) { $w .= ' AND c.property_id = ?'; $p[] = $pid; }
            $st = $f['status_contract'] ?? '';
            if ($st === 'soon') { $d = (int)setting('alert_contract_days', 30); $w .= " AND c.status = 'active' AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $d DAY)"; }
            elseif (isset(labels('contract_status')[$st])) { $w .= ' AND c.status = ?'; $p[] = $st; }
            if ($f['has_range']) { $w .= ' AND c.start_date <= ? AND c.end_date >= ?'; array_push($p, $to, $from); }
            $rows = q_all("SELECT c.contract_no, t.full_name, p.name pname, u.unit_no, c.start_date, c.end_date, c.rent_amount, c.period, c.status
                    FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN units u ON u.id = c.unit_id JOIN properties p ON p.id = c.property_id WHERE $w ORDER BY c.end_date", $p);
            foreach ($rows as &$r) { $r['period'] = label('period', $r['period']); $r['status'] = label('contract_status', $r['status']); $r['start_date'] = fmt_date($r['start_date']); $r['end_date'] = fmt_date($r['end_date']); } unset($r);
            return ['cols' => ['contract_no' => 'العقد', 'full_name' => 'المستأجر', 'pname' => 'العقار', 'unit_no' => 'الوحدة', 'start_date' => 'البداية', 'end_date' => 'النهاية', 'rent_amount' => 'الإيجار الشهري', 'period' => 'الدورية', 'status' => 'الحالة'],
                    'rows' => $rows, 'totals' => [], 'money' => ['rent_amount'], 'notes' => ''];
        case 'rents_due':
            $w = "i.status <> 'cancelled' AND i.due_date BETWEEN ? AND ?"; $p = [$from, $to];
            if ($pid) { $w .= ' AND i.property_id = ?'; $p[] = $pid; }
            $st = $f['status_inst'] ?? '';
            if ($st === 'overdue') $w .= ' AND ' . SQL_OVERDUE; elseif (in_array($st, ['due', 'partial', 'paid'], true)) { $w .= ' AND i.status = ?'; $p[] = $st; }
            $rows = q_all("SELECT i.ref_no, t.full_name, p.name pname, u.unit_no, i.due_date, i.amount, i.paid_amount, i.remaining, i.status
                    FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id WHERE $w ORDER BY i.due_date, i.id", $p);
            $a = $pd = $rm = 0; foreach ($rows as &$r) { $a += to_cents($r['amount']); $pd += to_cents($r['paid_amount']); $rm += to_cents($r['remaining']); $r['due_date'] = fmt_date($r['due_date']); $r['status'] = label('inst_status', $r['status']); } unset($r);
            return ['cols' => ['ref_no' => 'المرجع', 'full_name' => 'المستأجر', 'pname' => 'العقار', 'unit_no' => 'الوحدة', 'due_date' => 'الاستحقاق', 'amount' => 'المستحق', 'paid_amount' => 'المدفوع', 'remaining' => 'المتبقي', 'status' => 'الحالة'],
                    'rows' => $rows, 'totals' => ['amount' => cents_to_dec($a), 'paid_amount' => cents_to_dec($pd), 'remaining' => cents_to_dec($rm)], 'money' => ['amount', 'paid_amount', 'remaining'],
                    'notes' => 'الإيجارات المستحقة هي قيمة الأقساط حسب تاريخ استحقاقها، وليست مبالغ محصّلة. المدفوع هنا إجمالي ما سُدّد على هذه الأقساط حتى اليوم.'];
        case 'collected':
            $w = "p.status = 'valid' AND p.paid_on BETWEEN ? AND ?"; $pp = [$from, $to];
            if ($pid) { $w .= ' AND i.property_id = ?'; $pp[] = $pid; }
            if (!empty($f['method_id'])) { $w .= ' AND p.method_id = ?'; $pp[] = (int)$f['method_id']; }
            $rows = q_all("SELECT p.paid_on, p.txn_no, rc.receipt_no, t.full_name, pr.name pname, u.unit_no, m.name mname, p.reference_no, p.amount
                    FROM payments p JOIN installments i ON i.id = p.installment_id JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties pr ON pr.id = i.property_id
                    JOIN payment_methods m ON m.id = p.method_id LEFT JOIN receipts rc ON rc.payment_id = p.id WHERE $w ORDER BY p.paid_on, p.id", $pp);
            $s = 0; foreach ($rows as &$r) { $s += to_cents($r['amount']); $r['paid_on'] = fmt_date($r['paid_on']); } unset($r);
            return ['cols' => ['paid_on' => 'التاريخ', 'txn_no' => 'رقم العملية', 'receipt_no' => 'الإيصال', 'full_name' => 'المستأجر', 'pname' => 'العقار', 'unit_no' => 'الوحدة', 'mname' => 'الوسيلة', 'reference_no' => 'المرجع', 'amount' => 'المبلغ'],
                    'rows' => $rows, 'totals' => ['amount' => cents_to_dec($s)], 'money' => ['amount'], 'notes' => 'تشمل الدفعات الصحيحة فقط؛ الدفعات الملغاة مستبعدة. لا يشمل التأمين.'];
        case 'arrears':
            $w = SQL_OVERDUE; $p = [];
            if ($pid) { $w .= ' AND i.property_id = ?'; $p[] = $pid; }
            $rows = q_all("SELECT i.ref_no, t.full_name, t.phone, p.name pname, u.unit_no, i.due_date, DATEDIFF(CURDATE(), i.due_date) days_late, i.amount, i.paid_amount, i.remaining
                    FROM installments i JOIN tenants t ON t.id = i.tenant_id JOIN units u ON u.id = i.unit_id JOIN properties p ON p.id = i.property_id WHERE $w ORDER BY i.due_date", $p);
            $rm = 0; foreach ($rows as &$r) { $rm += to_cents($r['remaining']); $r['due_date'] = fmt_date($r['due_date']); } unset($r);
            return ['cols' => ['ref_no' => 'المرجع', 'full_name' => 'المستأجر', 'phone' => 'الهاتف', 'pname' => 'العقار', 'unit_no' => 'الوحدة', 'due_date' => 'الاستحقاق', 'days_late' => 'أيام التأخير', 'amount' => 'المبلغ', 'paid_amount' => 'المدفوع', 'remaining' => 'المتبقي'],
                    'rows' => $rows, 'totals' => ['remaining' => cents_to_dec($rm)], 'money' => ['amount', 'paid_amount', 'remaining'], 'notes' => 'المتأخرات: مجموع المتبقي من أقساط تجاوز تاريخ استحقاقها اليوم.'];
        case 'expenses':
            $w = "e.status = 'paid' AND e.expense_date BETWEEN ? AND ?"; $p = [$from, $to];
            if ($pid) { $w .= ' AND e.property_id = ?'; $p[] = $pid; }
            if (!empty($f['category_id'])) { $w .= ' AND e.category_id = ?'; $p[] = (int)$f['category_id']; }
            $rows = q_all("SELECT e.expense_date, e.expense_no, p.name pname, u.unit_no, c.name cname, e.payee, e.description, e.amount, mr.request_no
                    FROM expenses e JOIN properties p ON p.id = e.property_id JOIN expense_categories c ON c.id = e.category_id LEFT JOIN units u ON u.id = e.unit_id LEFT JOIN maintenance_requests mr ON mr.id = e.maintenance_id WHERE $w ORDER BY e.expense_date, e.id", $p);
            $s = 0; foreach ($rows as &$r) { $s += to_cents($r['amount']); $r['expense_date'] = fmt_date($r['expense_date']); } unset($r);
            return ['cols' => ['expense_date' => 'التاريخ', 'expense_no' => 'الرقم', 'pname' => 'العقار', 'unit_no' => 'الوحدة', 'cname' => 'التصنيف', 'payee' => 'المستفيد', 'description' => 'الوصف', 'request_no' => 'بلاغ الصيانة', 'amount' => 'المبلغ'],
                    'rows' => $rows, 'totals' => ['amount' => cents_to_dec($s)], 'money' => ['amount'], 'notes' => 'المصروفات الملغاة مستبعدة. تكلفة الصيانة تُحتسب مرة واحدة فقط عبر المصروف المرتبط بالبلاغ.'];
        case 'cashflow':
        case 'monthly':
            $months = months_between($from, $to);
            $pf = $pid ? ' AND i.property_id = ' . $pid : '';
            $col = array_column(q_all("SELECT DATE_FORMAT(p.paid_on,'%Y-%m') ym, SUM(p.amount) s FROM payments p JOIN installments i ON i.id = p.installment_id WHERE p.status='valid' AND p.paid_on BETWEEN ? AND ?$pf GROUP BY ym", [$from, $to]), 's', 'ym');
            if ($type === 'cashflow') {
                $exp = array_column(q_all("SELECT DATE_FORMAT(e.expense_date,'%Y-%m') ym, SUM(e.amount) s FROM expenses e WHERE e.status='paid' AND e.expense_date BETWEEN ? AND ?" . ($pid ? " AND e.property_id = $pid" : '') . " GROUP BY ym", [$from, $to]), 's', 'ym');
                $rows = []; $tc = $te = 0;
                foreach ($months as $m) { $c = to_cents($col[$m] ?? 0); $x = to_cents($exp[$m] ?? 0); $tc += $c; $te += $x; $rows[] = ['month' => month_label($m), 'collected' => cents_to_dec($c), 'expenses' => cents_to_dec($x), 'net' => cents_to_dec($c - $x)]; }
                return ['cols' => ['month' => 'الشهر', 'collected' => 'المحصّل فعلياً', 'expenses' => 'المصروفات المدفوعة', 'net' => 'صافي التدفق النقدي'], 'rows' => $rows,
                        'totals' => ['collected' => cents_to_dec($tc), 'expenses' => cents_to_dec($te), 'net' => cents_to_dec($tc - $te)], 'money' => ['collected', 'expenses', 'net'],
                        'notes' => 'صافي التدفق النقدي = المحصّل فعلياً − المصروفات المدفوعة خلال الفترة. وهو ليس «أرباحاً محققة»، ولا يشمل المستحقات غير المحصّلة ولا التأمين.',
                        'chart' => ['labels' => array_map('short_month_label', $months), 'series' => [['المحصّل', '#5F7B62', array_map(fn($r) => (float)$r['collected'], $rows)], ['المصروفات', '#C96F50', array_map(fn($r) => (float)$r['expenses'], $rows)]]]];
            }
            $dueM = array_column(q_all("SELECT DATE_FORMAT(i.due_date,'%Y-%m') ym, SUM(i.amount) s, SUM(i.remaining) r FROM installments i WHERE i.status <> 'cancelled' AND i.due_date BETWEEN ? AND ?$pf GROUP BY ym", [$from, $to]), null, 'ym');
            $rows = []; $td = $tc = $tr = 0;
            foreach ($months as $m) {
                $d = to_cents($dueM[$m]['s'] ?? 0); $c = to_cents($col[$m] ?? 0); $r = to_cents($dueM[$m]['r'] ?? 0); $td += $d; $tc += $c; $tr += $r;
                $rows[] = ['month' => month_label($m), 'due' => cents_to_dec($d), 'collected' => cents_to_dec($c), 'remaining' => cents_to_dec($r), 'rate' => $d > 0 ? round($c / $d * 100, 1) . '%' : '—'];
            }
            return ['cols' => ['month' => 'الشهر', 'due' => 'المستحق (حسب تاريخ الاستحقاق)', 'collected' => 'المحصّل (حسب تاريخ الدفع)', 'remaining' => 'المتبقي من مستحقات الشهر', 'rate' => 'نسبة التحصيل'], 'rows' => $rows,
                    'totals' => ['due' => cents_to_dec($td), 'collected' => cents_to_dec($tc), 'remaining' => cents_to_dec($tr), 'rate' => $td > 0 ? round($tc / $td * 100, 1) . '%' : '—'], 'money' => ['due', 'collected', 'remaining'],
                    'notes' => 'المحصّل في شهر قد يخص استحقاقات شهر سابق، لذا قد تتجاوز النسبة 100%. المتبقي يخص مستحقات الشهر نفسه حتى اليوم.',
                    'chart' => ['labels' => array_map('short_month_label', $months), 'series' => [['المستحق', '#D9B77C', array_map(fn($r) => (float)$r['due'], $rows)], ['المحصّل', '#5F7B62', array_map(fn($r) => (float)$r['collected'], $rows)]]]];
        case 'maintenance':
            $w = 'm.reported_on BETWEEN ? AND ?'; $p = [$from, $to];
            if ($pid) { $w .= ' AND m.property_id = ?'; $p[] = $pid; }
            if (isset(labels('maint_status')[$f['status_maint'] ?? ''])) { $w .= ' AND m.status = ?'; $p[] = $f['status_maint']; }
            $rows = q_all("SELECT m.request_no, m.reported_on, p.name pname, u.unit_no, m.title, m.priority, m.status, m.estimated_cost, ex.amount actual_cost
                    FROM maintenance_requests m JOIN properties p ON p.id = m.property_id LEFT JOIN units u ON u.id = m.unit_id LEFT JOIN expenses ex ON ex.maintenance_id = m.id AND ex.status = 'paid' WHERE $w ORDER BY m.reported_on, m.id", $p);
            $es = $ac = 0; foreach ($rows as &$r) { $es += to_cents($r['estimated_cost']); $ac += to_cents($r['actual_cost'] ?? 0); $r['reported_on'] = fmt_date($r['reported_on']); $r['priority'] = label('priority', $r['priority']); $r['status'] = label('maint_status', $r['status']); $r['actual_cost'] = $r['actual_cost'] ?? '0.00'; } unset($r);
            return ['cols' => ['request_no' => 'الرقم', 'reported_on' => 'التاريخ', 'pname' => 'العقار', 'unit_no' => 'الوحدة', 'title' => 'المشكلة', 'priority' => 'الأولوية', 'status' => 'الحالة', 'estimated_cost' => 'التكلفة التقديرية', 'actual_cost' => 'التكلفة الفعلية'],
                    'rows' => $rows, 'totals' => ['estimated_cost' => cents_to_dec($es), 'actual_cost' => cents_to_dec($ac)], 'money' => ['estimated_cost', 'actual_cost'],
                    'notes' => 'التكلفة الفعلية هي قيمة المصروف المرتبط بالبلاغ (مرة واحدة)، والتقدير أولي فقط ولا يدخل في المصروفات.'];
    }
    return ['cols' => [], 'rows' => [], 'totals' => [], 'money' => [], 'notes' => ''];
}
