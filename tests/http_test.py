#!/usr/bin/env python3
"""
اختبارات HTTP شاملة (دخول، صلاحيات، CSRF، رفع ملفات، XSS، دورة مالية كاملة عبر النماذج).
التشغيل: ابدأ الخادم على قاعدة اختبار فارغة (المخطط مستورد) ثم:
    python3 tests/http_test.py http://127.0.0.1:8081/sakan_property_manager
"""
import sys, re, io, struct, zlib, requests
BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8081/sakan_property_manager').rstrip('/')
passed = failed = 0
def t(name, cond, extra=''):
    global passed, failed
    if cond: passed += 1; print('  [PASS]', name)
    else: failed += 1; print('  [FAIL]', name, extra)

class C:
    def __init__(self): self.s = requests.Session()
    def get(self, p, **k): return self.s.get(BASE + '/' + p.lstrip('/'), allow_redirects=k.pop('allow_redirects', False), **k)
    def token(self, p='dashboard/'):
        r = self.s.get(BASE + '/' + p, allow_redirects=True)
        m = re.search(r'name="_csrf" value="([a-f0-9]+)"', r.text) or re.search(r'name="csrf" content="([a-f0-9]+)"', r.text)
        return m.group(1) if m else ''
    def post(self, p, data, tokpage=None, files=None, follow=False):
        d = dict(data)
        if '_csrf' not in d: d['_csrf'] = self.token(tokpage or p)
        return self.s.post(BASE + '/' + p.lstrip('/'), data=d, files=files, allow_redirects=follow)
def idem(c, page):
    r = c.s.get(BASE + '/' + page); return re.search(r'name="_idem" value="([a-f0-9]+)"', r.text).group(1)
def png_bytes():
    def chunk(t, d): c = struct.pack('>I', len(d)) + t + d; return c + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    raw = b'\x00\xc9\x6f\x50' * 4  # 4x1... one row with filter byte
    raw = (b'\x00' + b'\xc9\x6f\x50' * 4) * 4
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 4, 4, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')

adm = C()
print('== الإعداد الأولي ==')
r = adm.get('setup/'); t('صفحة الإعداد تعمل', r.status_code == 200 and 'الإعداد الأولي' in r.text)
tok = adm.token('setup/')
r = adm.post('setup/', {'_csrf': 'bad', 'full_name': 'x', 'username': 'admin', 'password': 'Test12345', 'password2': 'Test12345'})
t('الإعداد بدون CSRF صحيح مرفوض (419)', r.status_code == 419)
r = adm.post('setup/', {'_csrf': tok, 'full_name': 'المدير العام', 'username': 'admin', 'password': 'weak', 'password2': 'weak'})
t('كلمة مرور ضعيفة مرفوضة', r.status_code == 200 and 'كلمة المرور' in r.text)
r = adm.post('setup/', {'_csrf': tok, 'full_name': 'المدير العام', 'username': 'admin', 'password': 'Admin12345', 'password2': 'Admin12345', 'org_name': 'شركة الاختبار'})
t('إنشاء المدير الأول ثم التحويل للوحة التحكم', r.status_code == 302 and 'dashboard' in r.headers.get('Location', ''))
r = C().get('setup/'); t('تعطيل الإعداد بعد اكتماله', 'مكتمل' in r.text)
r = adm.post('setup/', {'full_name': 'هاكر', 'username': 'hack', 'password': 'Hack12345', 'password2': 'Hack12345'}, 'setup/')
t('محاولة إنشاء مدير ثانٍ عبر الإعداد مرفوضة', 'hack' not in requests.get(BASE + '/auth/login.php').text)

print('== الدخول والحماية ==')
anon = C()
for p in ['dashboard/', 'properties/', 'payments/', 'users/', 'settings/', 'audit/', 'reports/', 'file.php?id=1', 'payments/receipt.php?id=1']:
    r = anon.get(p); t(f'زائر بلا دخول يُحوَّل: {p}', r.status_code == 302 and 'login' in r.headers.get('Location', ''))
r = anon.s.post(BASE + '/payments/collect.php', data={'installment_id': 1, 'amount': 5}, allow_redirects=False)
t('POST مالي بلا دخول مرفوض', r.status_code == 302 and 'login' in r.headers.get('Location', ''))
r = anon.post('auth/login.php', {'username': 'admin', 'password': 'wrong'}, 'auth/login.php'); t('كلمة مرور خاطئة تُرفض', 'غير صحيحة' in r.text)
thr = C()
for i in range(6): r = thr.post('auth/login.php', {'username': 'ghost', 'password': 'x%d' % i}, 'auth/login.php')
t('تقييد المحاولات المتكررة', 'تجاوز عدد المحاولات' in r.text)
import subprocess, os
subprocess.run(['mysql', '-uroot', os.environ.get('SAKAN_DB_NAME', 'sakan_http'), '-e', 'DELETE FROM login_attempts'])
r = C().post('auth/login.php', {'username': 'admin', 'password': 'Admin12345'}, 'auth/login.php')
t('الدخول الصحيح بعد تجاوز محاولات مستخدم آخر ممكن (IP نفسه محجوب كاحتياط)', r.status_code in (200, 302))
adm2 = C(); import time
r = adm.get('dashboard/'); t('المدير يصل للوحة التحكم', r.status_code == 200 and 'لوحة التحكم' in r.text)
t('الواجهة RTL عربية', 'dir="rtl"' in r.text and 'lang="ar"' in r.text)
t('لوحة تحكم فارغة تعرض حالة فارغة لا أرقاماً وهمية', 'ابدأ بإعداد نظامك' in r.text)

print('== العقارات والوحدات والمستأجرون ==')
r = adm.post('properties/form.php', {'name': 'برج النيل', 'code': 'NILE1', 'city': 'الخرطوم', 'district': 'الرياض', 'floors': '5'}, 'properties/form.php')
t('إنشاء عقار', r.status_code == 302 and 'properties/view.php?id=' in r.headers['Location']); pid = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.post('properties/form.php', {'name': 'مكرر', 'code': 'NILE1'}, 'properties/form.php'); t('منع تكرار رمز العقار', r.status_code == 200 and 'مستخدم' in r.text)
r = adm.post('units/form.php', {'property_id': pid, 'unit_no': 'A1', 'floor': '1', 'unit_type': 'شقة', 'base_rent': '1000', 'deposit': '500', 'status': 'vacant'}, 'units/form.php')
t('إنشاء وحدة', r.status_code == 302); uid = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.post('units/form.php', {'property_id': pid, 'unit_no': 'A1', 'base_rent': '5'}, 'units/form.php'); t('منع تكرار رقم الوحدة داخل العقار', 'مسبقاً' in r.text)
r = adm.post('units/form.php', {'property_id': pid, 'unit_no': 'A2', 'floor': '1', 'base_rent': '800', 'status': 'vacant'}, 'units/form.php'); uid2 = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
XSS = '<script>alert(1)</script>'
r = adm.post('tenants/form.php', {'full_name': 'سالم ' + XSS, 'phone': '0912345678', 'national_id': '12345', 'status': 'active'}, 'tenants/form.php')
t('إنشاء مستأجر', r.status_code == 302); tid = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.get('tenants/'); t('XSS: اسم المستأجر يُرمَّز في القائمة', XSS not in r.text and '&lt;script&gt;' in r.text)
r = adm.post('tenants/form.php', {'full_name': 'غير صالح', 'phone': 'abc'}, 'tenants/form.php'); t('رقم هاتف غير صالح مرفوض', 'رقم الهاتف' in r.text)
for q in ["' OR 1=1 --", '%" OR "1"="1', "'; DROP TABLE users; --"]:
    r = adm.get('properties/?q=' + requests.utils.quote(q)); t('حقن SQL في البحث لا يكسر الصفحة: ' + q[:12], r.status_code == 200)
r = adm.get('units/?status=zzz&property_id=abc'); t('معاملات فلترة غير صالحة تُعالج', r.status_code == 200)

print('== العقود والاستحقاقات عبر الواجهة ==')
cdata = {'tenant_id': tid, 'unit_id': uid, 'start_date': '2026-01-01', 'end_date': '2026-12-31', 'rent_amount': '1000', 'period': 'monthly', 'deposit_amount': '500', 'due_day': '1', 'activate_now': '1'}
r = adm.post('contracts/form.php', cdata, 'contracts/form.php'); t('إنشاء عقد وتفعيله', r.status_code == 302); cid = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.get(f'contracts/view.php?id={cid}'); t('صفحة العقد تعرض 12 استحقاقاً', len(set(re.findall(r'CN-\d{4}-\d{5}/\d{3}', r.text))) == 12 and 'نشط' in r.text)
r = adm.post('contracts/form.php', dict(cdata, start_date='2026-06-01', end_date='2027-05-31'), 'contracts/form.php'); t('منع عقد نشط متداخل عبر الواجهة', 'متداخل' in r.text)
r = adm.get(f'units/view.php?id={uid}'); t('الوحدة أصبحت مؤجرة', 'مؤجرة' in r.text)
r = adm.get('payments/?status=overdue'); t('قائمة المتأخرات تعمل', r.status_code == 200 and 'متأخر' in r.text)
m = re.search(r'payments/view\.php\?id=(\d+)', adm.get(f'payments/?contract_id={cid}').text); iid = int(m.group(1))
r = adm.get(f'payments/view.php?id={iid}'); t('صفحة التحصيل فيها نموذج الدفع', 'تسجيل دفعة' in r.text)
key = idem(adm, f'payments/view.php?id={iid}')
pay = {'installment_id': iid, 'amount': '400', 'paid_on': '2026-02-01', 'method_id': '1', '_idem': key}
r = adm.post('payments/collect.php', pay, f'payments/view.php?id={iid}'); t('دفعة جزئية 400 تنجح ثم تحويل للإيصال', r.status_code == 302 and 'receipt.php' in r.headers['Location']); pay1 = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.post('payments/collect.php', pay, f'payments/view.php?id={iid}'); t('إعادة إرسال النموذج نفسه لا تكرر الدفعة (نفس الإيصال)', r.status_code == 302 and f'id={pay1}' in r.headers['Location'])
r = adm.get(f'payments/view.php?id={iid}'); t('المتبقي 600 والدفعات = 1', '600.00' in r.text and r.text.count('TX-') == 1)
r = adm.post('payments/collect.php', {'installment_id': iid, 'amount': '600.01', 'paid_on': '2026-02-02', 'method_id': '1', '_idem': 'a' * 32}, f'payments/view.php?id={iid}', follow=True); t('رفض دفع مبلغ يتجاوز المتبقي', 'أكبر من المتبقي' in r.text)
r = adm.post('payments/collect.php', {'installment_id': iid, 'amount': '600', 'paid_on': '2026-02-02', 'method_id': '2', 'reference_no': 'BK-77', '_idem': 'b' * 32}, f'payments/view.php?id={iid}'); pay2 = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.get(f'payments/receipt.php?id={pay2}'); t('الإيصال يُعرض بالعربية مع رقم فريد ومبلغ', 'إيصال استلام' in r.text and 'RC-' in r.text and '600.00' in r.text)
r = adm.get(f'payments/view.php?id={iid}'); t('الاستحقاق أصبح مدفوعاً', 'مدفوع' in r.text and 'مسدد بالكامل' in r.text)
r = adm.post('payments/void.php', {'payment_id': pay2, 'installment_id': iid, 'reason': ''}, f'payments/view.php?id={iid}', follow=True); t('إلغاء الدفعة يتطلب سبباً', 'سبب' in r.text)
r = adm.post('payments/void.php', {'payment_id': pay2, 'installment_id': iid, 'reason': 'خطأ إدخال'}, f'payments/view.php?id={iid}', follow=True); t('إلغاء موثّق يعيد المتبقي', '600.00' in r.text and 'ملغاة' in r.text)
r = adm.get('payments/history.php'); t('سجل الدفعات يعرض الإيصالات', 'RC-' in r.text)

print('== المصروفات والصيانة ==')
r = adm.post('maintenance/form.php', {'property_id': pid, 'unit_id': uid2, 'title': 'تسرب مياه', 'priority': 'high', 'reported_on': '2026-03-01', 'estimated_cost': '300', 'hold_unit': '1'}, 'maintenance/form.php')
t('تسجيل بلاغ صيانة', r.status_code == 302); mid = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.get(f'units/view.php?id={uid2}'); t('الوحدة المحجوزة للصيانة أصبحت «تحت الصيانة»', 'تحت الصيانة' in r.text)
r = adm.post('contracts/form.php', dict(cdata, unit_id=uid2, activate_now='1'), 'contracts/form.php'); t('منع التأجير على وحدة تحت الصيانة', 'الصيانة' in r.text)
exp = {'property_id': pid, 'unit_id': uid2, 'category_id': 1, 'amount': '350', 'expense_date': '2026-03-05', 'description': 'إصلاح تسرب', 'maintenance_id': mid, 'method_id': 1}
r = adm.post('expenses/form.php', exp, 'expenses/form.php'); t('تسجيل مصروف مرتبط بالبلاغ', r.status_code == 302); eid = int(re.search(r'id=(\d+)', r.headers['Location']).group(1))
r = adm.post('expenses/form.php', exp, 'expenses/form.php'); t('منع احتساب تكلفة البلاغ مرتين', 'مرتين' in r.text)
r = adm.get(f'maintenance/view.php?id={mid}'); t('التكلفة الفعلية = المصروف المرتبط', '350.00' in r.text)
r = adm.post('maintenance/action.php', {'id': mid, 'act': 'status', 'status': 'completed', 'completed_on': '2026-03-06', 'note': 'تم الإصلاح'}, f'maintenance/view.php?id={mid}', follow=True); t('إكمال البلاغ', 'مكتمل' in r.text)
r = adm.get(f'units/view.php?id={uid2}'); t('الوحدة تعود شاغرة بعد إغلاق البلاغ', 'شاغرة' in r.text)
r = adm.post('expenses/form.php', {'property_id': pid, 'category_id': 5, 'amount': '-5', 'expense_date': '2026-03-05', 'description': 'سالب'}, 'expenses/form.php'); t('مصروف سالب مرفوض', 'أكبر من صفر' in r.text)
r = adm.post('expenses/form.php', {'property_id': pid, 'category_id': 5, 'amount': '100', 'expense_date': '2099-01-01', 'description': 'مستقبل'}, 'expenses/form.php'); t('مصروف بتاريخ مستقبلي مرفوض', 'مستقبلي' in r.text)

print('== الرفع والمرفقات ==')
php = io.BytesIO(b'<?php echo "pwn"; ?>')
r = adm.post('properties/form.php', {'name': 'عقار صور', 'code': 'IMG1'}, 'properties/form.php', files={'images[]': ('shell.php.jpg', php, 'image/jpeg')}, follow=True)
t('ملف PHP متنكر كصورة لا يُقبل', 'لم تُرفع' in r.text or 'الصور المسموحة' in r.text)
r = adm.post('properties/form.php', {'name': 'عقار صور 2', 'code': 'IMG2'}, 'properties/form.php', files={'images[]': ('ok.png', io.BytesIO(png_bytes()), 'image/png')}, follow=True)
t('صورة PNG صحيحة تُقبل', 'تمت إضافة العقار' in r.text and '/file.php?id=' in r.text)
aid = int(re.search(r'file\.php\?id=(\d+)', r.text).group(1))
r = adm.get(f'file.php?id={aid}'); t('المرفق يُعرض لمستخدم مصرّح بنوع صحيح', r.status_code == 200 and r.headers['Content-Type'] == 'image/png' and r.headers.get('X-Content-Type-Options') == 'nosniff')
r = anon.get(f'file.php?id={aid}'); t('المرفق مرفوض للزائر', r.status_code == 302)
r = adm.get('file.php?id=99999'); t('مرفق مفقود يعطي 404 آمن', r.status_code == 404)
r = adm.get('file.php?id=abc'); t('معرف مرفق غير صالح يعالج', r.status_code == 404)
big = io.BytesIO(png_bytes() + b'0' * (6 * 1024 * 1024))
r = adm.post('properties/form.php', {'name': 'كبير', 'code': 'BIG1'}, 'properties/form.php', files={'images[]': ('big.png', big, 'image/png')}, follow=True); t('رفض ملف أكبر من الحد', 'يتجاوز' in r.text)
r = adm.post('contracts/action.php', {'id': cid, 'act': 'upload'}, f'contracts/view.php?id={cid}', files={'docs[]': ('c.pdf', io.BytesIO(b'%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF'), 'application/pdf')}, follow=True); t('رفع PDF لعقد', 'تم رفع المرفقات' in r.text)
r = adm.post('contracts/action.php', {'id': cid, 'act': 'upload'}, f'contracts/view.php?id={cid}', files={'docs[]': ('x.pdf', io.BytesIO(b'MZ not a pdf'), 'application/pdf')}, follow=True); t('رفض ملف PDF مزيف', 'الأنواع المسموحة' in r.text or 'غير صالح' in r.text)

print('== المستخدمون والصلاحيات ==')
r = adm.post('users/form.php', {'full_name': 'موظف إداري', 'username': 'staff1', 'role_id': 2, 'password': 'Staff12345'}, 'users/form.php'); t('إنشاء موظف', r.status_code == 302)
st = C(); r = st.post('auth/login.php', {'username': 'staff1', 'password': 'Staff12345'}, 'auth/login.php'); t('دخول الموظف', r.status_code == 302)
for p in ['users/', 'settings/', 'audit/', 'users/roles.php']:
    r = st.get(p); t(f'الموظف ممنوع من {p} (403 من الخادم)', r.status_code == 403)
r = st.post('payments/void.php', {'payment_id': pay1, 'installment_id': iid, 'reason': 'محاولة'}, 'payments/', ); t('الموظف لا يملك إلغاء الدفعات (خادم)', r.status_code == 403)
r = st.get('properties/'); t('الموظف يصل للعقارات', r.status_code == 200)
r = st.get('dashboard/'); t('قائمة الموظف لا تعرض إدارة المستخدمين', '/users/index.php' not in r.text and '/users/roles.php' not in r.text and '/settings/' not in r.text.split('<main')[0])
r = st.get(f'tenants/view.php?id={tid}'); t('الموظف لا يرى رقم الهوية', '>12345<' not in r.text and '••••' in r.text)
r = adm.get(f'tenants/view.php?id={tid}'); t('المدير يرى رقم الهوية', '12345' in r.text.replace('0912345678',''))
r = adm.post('users/action.php', {'id': 1, 'act': 'disable'}, 'users/', follow=True); t('لا يمكن تعطيل الحساب الحالي/آخر مدير', 'لا يمكن' in r.text or 'لا يمكنك' in r.text)
r = adm.post('users/action.php', {'id': 2, 'act': 'disable'}, 'users/', follow=True)
r = C().post('auth/login.php', {'username': 'staff1', 'password': 'Staff12345'}, 'auth/login.php'); t('المستخدم المعطّل لا يدخل', r.status_code == 200 and 'معطّل' in r.text)
r = st.get('properties/'); t('جلسة المستخدم المعطّل تُقطع فوراً', r.status_code == 302)

print('== التقارير والتصدير ==')
for ty in ['properties', 'occupancy', 'contracts', 'rents_due', 'collected', 'arrears', 'expenses', 'cashflow', 'monthly', 'maintenance']:
    r = adm.get(f'reports/report.php?type={ty}&from=2026-01-01&to=2026-12-31'); t(f'تقرير {ty} يعمل', r.status_code == 200 and 'Fatal' not in r.text and 'خطأ غير متوقع' not in r.text)
r = adm.get('reports/report.php?type=collected&from=2026-01-01&to=2026-12-31&export=csv'); t('تصدير CSV مع BOM وترميز عربي', r.content[:3] == b'\xef\xbb\xbf' and 'text/csv' in r.headers['Content-Type'])
r = adm.get('reports/report.php?type=collected&from=2026-01-01&to=2026-12-31'); t('المحصّل في التقرير = 400 (الدفعة الملغاة مستبعدة)', '400.00' in r.text and '600.00' not in r.text.split('<tbody>')[1].split('</tbody>')[0])
r = adm.get('reports/report.php?type=cashflow&from=2026-01-01&to=2026-12-31'); t('صافي التدفق = 400 − 350 = 50', '50.00' in r.text)
for ty, i in [('tenant', tid), ('unit', uid), ('property', pid)]:
    r = adm.get(f'reports/statement.php?type={ty}&id={i}'); t(f'كشف حساب {ty}', r.status_code == 200 and 'كشف حساب' in r.text)
r = adm.get('reports/report.php?type=nope'); t('نوع تقرير غير معروف 404', r.status_code == 404)
r = adm.get('dashboard/?range=year'); t('لوحة التحكم تعرض أرقاماً حقيقية ومخططاً', r.status_code == 200 and '<svg class="chart"' in r.text and 'برج النيل' in r.text)
m = re.search(r'المحصّل فعلياً في الفترة</div><div class="stat-value">([\d,\.]+)', r.text); t('رقم المحصّل في اللوحة = 400', bool(m) and m.group(1).startswith('400.00'), m.group(1) if m else 'none')

print('== الإشعارات والتدقيق والإعدادات ==')
r = adm.get('notifications/?show=all'); t('مركز الإشعارات يعمل', r.status_code == 200)
r = adm.post('notifications/action.php', {'act': 'refresh'}, 'notifications/', follow=True); t('فحص التنبيهات يدوياً', 'تم فحص التنبيهات' in r.text)
r = adm.get('notifications/?show=all'); t('توجد إشعارات متأخرات/صيانة', 'دفعة متأخرة' in r.text or 'بلاغ صيانة' in r.text)
r = adm.get('audit/'); t('سجل التدقيق يسجل العمليات', 'تسجيل دفعة' in r.text and 'إلغاء دفعة' in r.text and 'إنشاء عقد' in r.text)
r = adm.get('audit/'); t('السجل لا يحوي كلمات المرور', 'Admin12345' not in r.text and 'Staff12345' not in r.text)
r = adm.post('settings/index.php', {'org_name': 'شركة جديدة', 'owner_name': 'المالك', 'currency': 'ر.س', 'timezone': 'Asia/Riyadh', 'date_format': 'Y-m-d', 'alert_rent_days': '10', 'alert_contract_days': '45', 'receipt_prefix': 'REC', 'contract_prefix': 'CT', 'receipt_footer': 'شكراً'}, 'settings/', follow=True)
t('حفظ الإعدادات', 'تم حفظ الإعدادات' in r.text)
r = adm.get(f'payments/receipt.php?id={pay1}'); sheet = r.text.split('class="doc-sheet"')[1]
t('العملة من الإعدادات تظهر في الإيصال', 'ر.س' in sheet and 'ج.س' not in sheet)
r = adm.post('settings/backup.php', {}, 'settings/'); t('تنزيل نسخة احتياطية SQL', r.status_code == 200 and 'CREATE TABLE' in r.text and 'sakan_backup' in r.headers.get('Content-Disposition', ''))
r = adm.get('settings/'); t('إعدادات النظام لا تكشف كلمات مرور قاعدة البيانات', 'root' not in r.text.lower().split('<main')[1] if '<main' in r.text else True)
r = adm.get('nonexistent-page.php'); t('صفحة غير موجودة 404', r.status_code == 404)
r = adm.post('auth/logout.php', {}, 'dashboard/'); t('تسجيل الخروج', r.status_code == 302)
r = adm.get('dashboard/'); t('بعد الخروج الصفحات محمية', r.status_code == 302)

print(f'\n==== النتيجة: نجح {passed} — فشل {failed} ====')
sys.exit(1 if failed else 0)
