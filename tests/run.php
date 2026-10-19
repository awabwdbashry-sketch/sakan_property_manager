<?php
/**
 * اختبارات المنطق المالي والعقود على قاعدة بيانات اختبار مستقلة (sakan_test).
 * التشغيل:  php tests/run.php        (يتطلب MySQL/MariaDB يعمل)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('SAKAN_TESTING', 1);
putenv('SAKAN_DB_NAME=sakan_test');
$root = dirname(__DIR__);
// إعادة بناء قاعدة الاختبار من المخطط
$c = require $root . '/config/config.php'; $l = is_file($root . '/config/local.php') ? require $root . '/config/local.php' : [];
$db = array_replace($c['db'], $l['db'] ?? []);
$pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS sakan_test");
$sql = file_get_contents($root . '/database/schema.sql');
$sql = str_replace('sakan_property_manager', 'sakan_test', $sql);
$pdo->exec($sql);
unset($pdo);

require $root . '/includes/bootstrap.php';

$pass = 0; $fail = 0;
function t(string $name, bool $cond, string $extra = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $name\n"; } else { $fail++; echo "  [FAIL] $name $extra\n"; }
}
function throws(callable $f, string $needle = ''): bool {
    try { $f(); } catch (DomainException $e) { return $needle === '' || mb_strpos($e->getMessage(), $needle) !== false; }
    return false;
}

// بيانات أساسية
q("INSERT INTO users (full_name, username, password_hash, role_id) VALUES ('اختبار','tester','x',1)");
$_SESSION['uid'] = insert_id();
q("INSERT INTO properties (code, name, city) VALUES ('P-T1','برج الاختبار','الخرطوم')"); $prop = insert_id();
q("INSERT INTO units (property_id, unit_no, base_rent, deposit) VALUES (?, 'A1', 1000.00, 500.00)", [$prop]); $u1 = insert_id();
q("INSERT INTO units (property_id, unit_no, base_rent) VALUES (?, 'A2', 800.00)", [$prop]); $u2 = insert_id();
q("INSERT INTO tenants (full_name, phone, registered_on) VALUES ('مستأجر أول','0912345678', CURDATE())"); $ten = insert_id();

echo "== الجدولة ==\n";
$s = build_schedule('2026-01-01', '2026-12-31', 'monthly', 100000, 1);
t('12 استحقاقاً شهرياً لعقد سنة كاملة', count($s) === 12);
t('مجموع المبالغ = 12 × الإيجار', array_sum(array_column($s, 'amount_cents')) === 1200000);
$s = build_schedule('2026-01-01', '2026-12-31', 'quarterly', 100000, 1);
t('4 استحقاقات ربع سنوية بقيمة 3 أشهر', count($s) === 4 && $s[0]['amount_cents'] === 300000);
$s = build_schedule('2026-01-01', '2026-12-31', 'semiannual', 100000, 1);
t('استحقاقان نصف سنويان', count($s) === 2 && $s[1]['amount_cents'] === 600000);
$s = build_schedule('2026-01-01', '2026-12-31', 'annual', 100000, 1);
t('استحقاق سنوي واحد', count($s) === 1 && $s[0]['amount_cents'] === 1200000);
$s = build_schedule('2026-01-31', '2026-05-30', 'monthly', 100000, 31);
t('نهاية الشهر: فبراير يقص يوم الاستحقاق إلى 28', $s[1]['period_start'] === '2026-02-28' && $s[1]['due_date'] === '2026-02-28', json_encode($s[1]));
$s = build_schedule('2028-01-31', '2028-03-30', 'monthly', 100000, 31);
t('السنة الكبيسة: فبراير 29', $s[1]['due_date'] === '2028-02-29', json_encode($s[1]));
$s = build_schedule('2026-01-15', '2026-03-15', 'monthly', 100000, 1);
t('الفترة الأخيرة الجزئية (يوم واحد) بالتناسب', count($s) === 3 && $s[2]['amount_cents'] > 0 && $s[2]['amount_cents'] < 100000, json_encode($s));
$s = build_schedule('2026-01-15', '2026-02-14', 'monthly', 100000, 1);
t('يوم الاستحقاق قبل بداية الفترة => تاريخ البداية', $s[0]['due_date'] === '2026-01-15');

echo "== العقود ==\n";
$base = ['tenant_id' => $ten, 'unit_id' => $u1, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_amount' => '1000', 'period' => 'monthly', 'deposit_amount' => '500', 'due_day' => 1, 'terms' => '', 'notes' => ''];
$cid = create_contract($base);
t('إنشاء عقد كمسودة', q_val("SELECT status FROM contracts WHERE id=?", [$cid]) === 'draft');
t('إنشاء العقد لا يولّد استحقاقات ولا يعني دفعاً', (int)q_val("SELECT COUNT(*) FROM installments WHERE contract_id=?", [$cid]) === 0);
activate_contract($cid);
t('تفعيل العقد', q_val("SELECT status FROM contracts WHERE id=?", [$cid]) === 'active');
t('الوحدة أصبحت مؤجرة', q_val("SELECT status FROM units WHERE id=?", [$u1]) === 'rented');
t('تم توليد 12 استحقاقاً', (int)q_val("SELECT COUNT(*) FROM installments WHERE contract_id=?", [$cid]) === 12);
t('لا يوجد تحصيل تلقائي', (float)q_val("SELECT COALESCE(SUM(paid_amount),0) FROM installments WHERE contract_id=?", [$cid]) == 0 && (int)q_val("SELECT COUNT(*) FROM payments") === 0);
t('إعادة التوليد لا تكرر الاستحقاقات', generate_installments($cid) === 0 && (int)q_val("SELECT COUNT(*) FROM installments WHERE contract_id=?", [$cid]) === 12);
t('منع تداخل عقد نشط على الوحدة نفسها', throws(fn() => create_contract(array_merge($base, ['start_date' => '2026-06-01', 'end_date' => '2027-05-31'])), 'متداخل'));
t('عقد غير متداخل بعد انتهاء الأول مسموح', is_int($cX = create_contract(array_merge($base, ['start_date' => '2027-01-01', 'end_date' => '2027-12-31']))));
t('تفعيل مسودة تتداخل مع عقد نشط مرفوض', throws(function () use ($cX, $cid) {
    q("UPDATE contracts SET start_date='2026-03-01' WHERE id=?", [$cX]); activate_contract($cX); }, 'متداخل'));
q("DELETE FROM contract_events WHERE contract_id=?", [$cX]); q("DELETE FROM contracts WHERE id=?", [$cX]);
t('تاريخ نهاية قبل البداية مرفوض', throws(fn() => create_contract(array_merge($base, ['unit_id' => $u2, 'end_date' => '2025-01-01']))));
t('إيجار صفر مرفوض', throws(fn() => create_contract(array_merge($base, ['unit_id' => $u2, 'rent_amount' => '0']))));
q("UPDATE units SET status='maintenance' WHERE id=?", [$u2]);
t('منع التأجير على وحدة تحت الصيانة', throws(fn() => create_contract(array_merge($base, ['unit_id' => $u2])), 'الصيانة'));
q("UPDATE units SET status='vacant' WHERE id=?", [$u2]);

echo "== الدفعات ==\n";
$inst = q_all("SELECT * FROM installments WHERE contract_id=? ORDER BY seq_no", [$cid]);
$i1 = (int)$inst[0]['id'];
$k1 = bin2hex(random_bytes(16));
[$p1, $r1, $dup] = record_payment($i1, '400', today(), 1, 'REF1', '', $k1);
t('دفعة جزئية 400 من 1000', !$dup && q_val("SELECT status FROM installments WHERE id=?", [$i1]) !== 'paid' && (float)q_val("SELECT paid_amount FROM installments WHERE id=?", [$i1]) == 400.0);
t('المتبقي بعد الدفعة الجزئية 600', (float)q_val("SELECT remaining FROM installments WHERE id=?", [$i1]) == 600.0);
t('الاستحقاق المتأخر مع دفع جزئي يبقى متأخراً', in_array(q_val("SELECT status FROM installments WHERE id=?", [$i1]), ['overdue', 'partial'], true));
[$p1b, $r1b, $dup2] = record_payment($i1, '400', today(), 1, 'REF1', '', $k1);
t('إعادة إرسال النموذج بنفس المفتاح لا تكرر الدفعة', $dup2 && $p1b === $p1 && (int)q_val("SELECT COUNT(*) FROM payments WHERE installment_id=?", [$i1]) === 1);
t('منع دفع مبلغ أكبر من المتبقي', throws(fn() => record_payment($i1, '600.01', today(), 1, '', '', bin2hex(random_bytes(16))), 'أكبر من المتبقي'));
t('منع مبلغ صفر/سالب/غير صالح', throws(fn() => record_payment($i1, '0', today(), 1, '', '', bin2hex(random_bytes(16)))) && throws(fn() => record_payment($i1, '-5', today(), 1, '', '', bin2hex(random_bytes(16)))) && throws(fn() => record_payment($i1, 'abc', today(), 1, '', '', bin2hex(random_bytes(16)))));
t('منع تاريخ دفع مستقبلي', throws(fn() => record_payment($i1, '10', date('Y-m-d', strtotime('+3 days')), 1, '', '', bin2hex(random_bytes(16)))));
[$p2, $r2, ] = record_payment($i1, '600', today(), 2, '', '', bin2hex(random_bytes(16)));
t('سداد كامل المتبقي => مدفوع', q_val("SELECT status FROM installments WHERE id=?", [$i1]) === 'paid' && (float)q_val("SELECT remaining FROM installments WHERE id=?", [$i1]) == 0.0);
t('منع الدفع على استحقاق مسدد', throws(fn() => record_payment($i1, '1', today(), 1, '', '', bin2hex(random_bytes(16))), 'مسدد'));
t('أرقام الإيصالات فريدة', (int)q_val("SELECT COUNT(DISTINCT receipt_no) FROM receipts") === 2 && $r1 !== $r2);
t('رقم عملية فريد لكل دفعة', (int)q_val("SELECT COUNT(DISTINCT txn_no) FROM payments") === 2);
t('كل دفعة لها إيصال واحد', (int)q_val("SELECT COUNT(*) FROM payments p LEFT JOIN receipts r ON r.payment_id=p.id WHERE r.id IS NULL") === 0);
t('تسجيل دفعة بدون وسيلة دفع صحيحة مرفوض', throws(fn() => record_payment((int)$inst[1]['id'], '10', today(), 999, '', '', bin2hex(random_bytes(16)))));

echo "== معاملة واحدة (فشل = تراجع كامل) ==\n";
$before = [(int)q_val("SELECT COUNT(*) FROM payments"), (int)q_val("SELECT COUNT(*) FROM receipts"), (int)q_val("SELECT value FROM counters WHERE name='payment'")];
try { tx(function () use ($inst) { record_payment((int)$inst[1]['id'], '50', today(), 1, '', '', bin2hex(random_bytes(16))); throw new RuntimeException('boom'); }); } catch (RuntimeException $e) {}
$after = [(int)q_val("SELECT COUNT(*) FROM payments"), (int)q_val("SELECT COUNT(*) FROM receipts"), (int)q_val("SELECT value FROM counters WHERE name='payment'")];
t('التراجع يلغي الدفعة والإيصال والعداد معاً', $before === $after && (float)q_val("SELECT paid_amount FROM installments WHERE id=?", [(int)$inst[1]['id']]) == 0.0);

echo "== إلغاء الدفعة ==\n";
t('إلغاء بدون سبب مرفوض', throws(fn() => void_payment($p2, '')));
void_payment($p2, 'خطأ في الإدخال');
t('إلغاء الدفعة يعيد المتبقي 600', (float)q_val("SELECT remaining FROM installments WHERE id=?", [$i1]) == 600.0 && q_val("SELECT status FROM payments WHERE id=?", [$p2]) === 'void');
t('الدفعة الملغاة لا تُحذف', (int)q_val("SELECT COUNT(*) FROM payments WHERE id=?", [$p2]) === 1);
t('إلغاء دفعة ملغاة مسبقاً مرفوض', throws(fn() => void_payment($p2, 'تكرار')));
t('سجل التدقيق سجّل الدفعة والإلغاء', (int)q_val("SELECT COUNT(*) FROM audit_logs WHERE action IN ('payment.create','payment.void')") >= 3);
t('كلمات المرور لا تظهر في التدقيق', (int)q_val("SELECT COUNT(*) FROM audit_logs WHERE details LIKE '%password%'") === 0);

echo "== التأمين منفصل عن الإيجار ==\n";
record_deposit($cid, 'received', '500', today(), 1, '');
t('التأمين المستلم لا يدخل في المحصّل من الإيجار', to_cents(q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='valid'")) === 40000 && deposit_balance_cents($cid) === 50000);
t('رد تأمين أكبر من الرصيد مرفوض', throws(fn() => record_deposit($cid, 'refunded', '600', today(), 1, '')));

echo "== الإنهاء والتجديد ==\n";
t('إنهاء بدون سبب مرفوض', throws(fn() => terminate_contract($cid, '2026-06-30', '')));
$paidBefore = to_cents(q_val("SELECT COALESCE(SUM(paid_amount),0) FROM installments WHERE contract_id=?", [$cid]));
terminate_contract($cid, '2026-06-30', 'اتفاق الطرفين');
t('الإنهاء يلغي الاستحقاقات غير المدفوعة اللاحقة فقط', (int)q_val("SELECT COUNT(*) FROM installments WHERE contract_id=? AND status='cancelled'", [$cid]) === 6
  && to_cents(q_val("SELECT COALESCE(SUM(paid_amount),0) FROM installments WHERE contract_id=?", [$cid])) === $paidBefore);
t('الوحدة تعود شاغرة بعد الإنهاء', q_val("SELECT status FROM units WHERE id=?", [$u1]) === 'vacant');
t('لا دفعات على استحقاق ملغى', throws(fn() => record_payment((int)$inst[11]['id'], '10', today(), 1, '', '', bin2hex(random_bytes(16))), 'ملغى'));
t('لا يمكن إنهاء عقد منتهٍ مرتين', throws(fn() => terminate_contract($cid, '2026-06-30', 'تكرار')));

$cB = create_contract(array_merge($base, ['unit_id' => $u2, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_amount' => '800']));
activate_contract($cB);
$cR = renew_contract($cB, '2027-12-31', '900', 'quarterly', '5', 'تجديد');
t('التجديد ينشئ عقداً نشطاً يبدأ بعد نهاية القديم', q_val("SELECT start_date FROM contracts WHERE id=?", [$cR]) === '2027-01-01' && q_val("SELECT status FROM contracts WHERE id=?", [$cR]) === 'active');
t('التجديد يربط العقدين ولا يمحو القديم', (int)q_val("SELECT renewed_from_id FROM contracts WHERE id=?", [$cR]) === $cB && q_val("SELECT rent_amount FROM contracts WHERE id=?", [$cB]) == '800.00');
t('تجديد العقد نفسه مرتين مرفوض', throws(fn() => renew_contract($cB, '2028-12-31', '900', 'monthly', '1', '')));
t('وحدة بعقدين نشطين متتاليين تبقى مؤجرة', q_val("SELECT status FROM units WHERE id=?", [$u2]) === 'rented');
t('سجل أحداث العقد موثّق', (int)q_val("SELECT COUNT(*) FROM contract_events WHERE contract_id=?", [$cB]) >= 2);
t('تعديل عقد نشط لا يغيّر الإيجار', (function () use ($cB) { update_contract($cB, ['terms' => 'جديد', 'notes' => '', 'rent_amount' => '9999', 'end_date' => '2030-01-01']); return q_val("SELECT rent_amount FROM contracts WHERE id=?", [$cB]) == '800.00' && q_val("SELECT terms FROM contracts WHERE id=?", [$cB]) === 'جديد'; })());

echo "== انتهاء العقود والتنبيهات ==\n";
q("INSERT INTO units (property_id, unit_no, base_rent) VALUES (?, 'A3', 500.00)", [$prop]); $u3 = insert_id();
$cE = create_contract(array_merge($base, ['unit_id' => $u3, 'start_date' => date('Y-m-d', strtotime('-60 days')), 'end_date' => date('Y-m-d', strtotime('+10 days')), 'rent_amount' => '500']));
activate_contract($cE);
$st = generate_notifications();
t('تنبيه قرب انتهاء العقد', (int)q_val("SELECT COUNT(*) FROM notifications WHERE type='contract_end'") >= 1);
t('تنبيه التأخر للدفعات المتأخرة', (int)q_val("SELECT COUNT(*) FROM notifications WHERE type='rent_overdue'") >= 1);
$n1 = (int)q_val("SELECT COUNT(*) FROM notifications"); generate_notifications();
t('تشغيل التنبيهات مرتين لا يكرر الإشعارات', (int)q_val("SELECT COUNT(*) FROM notifications") === $n1);
q("UPDATE contracts SET end_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id=?", [$cE]);
t('انتهاء العقد تلقائياً وتحرير الوحدة', expire_contracts() === 1 && q_val("SELECT status FROM units WHERE id=?", [$u3]) === 'vacant');

echo "== المبالغ ==\n";
t('to_cents/cents_to_dec', to_cents('1,234.5') === 123450 && cents_to_dec(123450) === '1234.50' && to_cents('12.345') === 0 && cents_to_dec(5) === '0.05');
t('add_months نهاية الشهر', add_months('2026-01-31', 1) === '2026-02-28' && add_months('2026-01-31', 2) === '2026-03-31' && add_months('2028-01-31', 1) === '2028-02-29');

echo "\n==== النتيجة: نجح $pass — فشل $fail ====\n";
exit($fail ? 1 : 0);
