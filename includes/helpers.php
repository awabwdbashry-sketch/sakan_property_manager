<?php
declare(strict_types=1);

// ---------- المخرجات والمسارات ----------
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_url(): string {
    static $b = null;
    if ($b !== null) return $b;
    $cfg = (string)cfg('base_url', '');
    if ($cfg !== '') return $b = '/' . trim($cfg, '/');
    // اكتشاف تلقائي: مسار المشروع بالنسبة إلى جذر الويب
    $root = str_replace('\\', '/', (string)realpath(SAKAN_ROOT));
    $doc  = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($doc !== '' && strpos($root, $doc) === 0) {
        $rel = substr($root, strlen($doc));
        return $b = rtrim($rel, '/');
    }
    return $b = '';
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function asset(string $path): string {
    $f = SAKAN_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($f) ? filemtime($f) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}
function redirect(string $path): never {
    if (!preg_match('#^https?://#', $path)) $path = url($path);
    header('Location: ' . $path);
    exit;
}

// ---------- الطلب ----------
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, $d = ''): string { $v = $_POST[$k] ?? $d; return is_array($v) ? '' : trim((string)$v); }
function get(string $k, $d = ''): string { $v = $_GET[$k] ?? $d; return is_array($v) ? '' : trim((string)$v); }
function get_int(string $k): int { return (int)get($k, '0'); }
function post_int(string $k): int { return (int)post($k, '0'); }
function old(string $k, $default = ''): string {
    return e($_POST[$k] ?? $default);
}

// ---------- رسائل الفلاش ----------
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = ['t' => $type, 'm' => $msg]; }
function flash_get(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $t)) {
        http_response_code(419);
        render_error_page('انتهت صلاحية الجلسة', 'تعذّر التحقق من الطلب. أعد تحميل الصفحة وحاول مرة أخرى.');
    }
}
/** مفتاح منع تكرار الإرسال: يُولَّد عند عرض النموذج ويُستهلك مرة واحدة */
function form_key(): string {
    $k = bin2hex(random_bytes(16));
    return $k;
}
function form_key_field(?string $key = null): string {
    return '<input type="hidden" name="_idem" value="' . e($key ?? form_key()) . '">';
}

function render_error_page(string $title, string $msg, int $code = 0): never {
    if ($code) http_response_code($code);
    $ctx = $GLOBALS['SAKAN_USER'] ?? null;
    if ($ctx && function_exists('layout_start')) {
        layout_start($title, '');
        echo '<div class="empty-state"><div class="empty-ico">!</div><h2>' . e($title) . '</h2><p>' . e($msg) . '</p><a class="btn btn-primary" href="' . e(url('dashboard/')) . '">العودة إلى لوحة التحكم</a></div>';
        layout_end();
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title>'
           . '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '">'
           . '<body class="auth-body"><div class="auth-card"><h2>' . e($title) . '</h2><p>' . e($msg) . '</p><a class="btn btn-primary" href="' . e(url('')) . '">رجوع</a></div></body></html>';
    }
    exit;
}

// ---------- الأموال (بالسنتات لتفادي أخطاء الفاصلة العائمة) ----------
function to_cents($v): int {
    $s = trim((string)$v);
    $s = strtr($s, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.','٬'=>'', ',' => '', ' ' => '']);
    if ($s === '' || !preg_match('/^-?\d+(\.\d{1,2})?$/', $s)) return 0;
    $neg = $s[0] === '-';
    $s = ltrim($s, '-');
    [$i, $d] = array_pad(explode('.', $s), 2, '0');
    $c = (int)$i * 100 + (int)str_pad($d, 2, '0');
    return $neg ? -$c : $c;
}
function cents_to_dec(int $c): string {
    $neg = $c < 0; $c = abs($c);
    return ($neg ? '-' : '') . intdiv($c, 100) . '.' . str_pad((string)($c % 100), 2, '0', STR_PAD_LEFT);
}
function valid_money($v): bool {
    $s = strtr(trim((string)$v), [',' => '', ' ' => '']);
    return $s !== '' && preg_match('/^\d+(\.\d{1,2})?$/', $s) === 1;
}
function money($v, bool $withCur = true): string {
    $n = number_format((float)$v, 2, '.', ',');
    return $withCur ? $n . ' ' . e(setting('currency', 'ج.س')) : $n;
}
/** للاستخدام داخل CSV أو نص خام */
function money_plain($v): string { return number_format((float)$v, 2, '.', ''); }

// ---------- التواريخ ----------
const AR_MONTHS = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
function fmt_date(?string $d): string {
    if (!$d || $d === '0000-00-00') return '—';
    $ts = strtotime($d);
    if (!$ts) return '—';
    return date((string)setting('date_format', 'd/m/Y'), $ts);
}
function fmt_datetime(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date((string)setting('date_format', 'd/m/Y'), $ts) . ' ' . date('H:i', $ts) : '—';
}
function fmt_date_long(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? (int)date('j', $ts) . ' ' . AR_MONTHS[(int)date('n', $ts)] . ' ' . date('Y', $ts) : '—';
}
function month_label(string $ym): string { // 2026-03 => مارس 2026
    [$y, $m] = array_pad(explode('-', $ym), 2, '1');
    return AR_MONTHS[(int)$m] . ' ' . $y;
}
function valid_date(?string $d): bool {
    if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $dd] = array_map('intval', explode('-', $d));
    return checkdate($m, $dd, $y);
}
function today(): string { return date('Y-m-d'); }
function days_between(string $a, string $b): int {
    return (int)round((strtotime($b) - strtotime($a)) / 86400);
}
/** إضافة أشهر مع الحفاظ على نهاية الشهر (31 يناير + شهر = 28/29 فبراير) */
function add_months(string $date, int $n, ?int $anchorDay = null): string {
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    $d = $anchorDay ?? $d;
    $t = $y * 12 + ($m - 1) + $n;
    $ny = intdiv($t, 12); $nm = $t % 12 + 1;
    $last = (int)date('t', mktime(0, 0, 0, $nm, 1, $ny));
    return sprintf('%04d-%02d-%02d', $ny, $nm, min($d, $last));
}

// ---------- التسميات ----------
function labels(string $group): array {
    static $L = [
        'unit_status' => ['vacant' => 'شاغرة', 'rented' => 'مؤجرة', 'reserved' => 'محجوزة', 'maintenance' => 'تحت الصيانة', 'unavailable' => 'غير متاحة'],
        'contract_status' => ['draft' => 'مسودة', 'active' => 'نشط', 'expired' => 'منتهٍ', 'terminated' => 'مُنهى'],
        'period' => ['monthly' => 'شهري', 'quarterly' => 'ربع سنوي', 'semiannual' => 'نصف سنوي', 'annual' => 'سنوي'],
        'inst_status' => ['due' => 'مستحق', 'partial' => 'مدفوع جزئياً', 'paid' => 'مدفوع', 'overdue' => 'متأخر', 'cancelled' => 'ملغى'],
        'priority' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'مرتفعة', 'urgent' => 'عاجلة'],
        'maint_status' => ['new' => 'جديد', 'in_progress' => 'قيد المعالجة', 'waiting' => 'بانتظار قطع غيار/موافقة', 'completed' => 'مكتمل', 'cancelled' => 'ملغي'],
        'prop_status' => ['active' => 'نشط', 'archived' => 'مؤرشف'],
        'tenant_status' => ['active' => 'نشط', 'inactive' => 'غير نشط'],
        'pay_status' => ['valid' => 'صحيحة', 'void' => 'ملغاة'],
        'exp_status' => ['paid' => 'مدفوع', 'cancelled' => 'ملغى'],
        'unit_types' => ['شقة' => 'شقة', 'استوديو' => 'استوديو', 'دور كامل' => 'دور كامل', 'محل تجاري' => 'محل تجاري', 'مكتب' => 'مكتب', 'غرفة' => 'غرفة', 'أخرى' => 'أخرى'],
    ];
    return $L[$group] ?? [];
}
function label(string $group, ?string $key): string { return labels($group)[$key ?? ''] ?? (string)$key; }

function badge(string $group, ?string $key): string {
    $cls = [
        'vacant' => 'b-sage', 'rented' => 'b-clay', 'reserved' => 'b-sand', 'maintenance' => 'b-amber', 'unavailable' => 'b-gray',
        'draft' => 'b-gray', 'active' => 'b-sage', 'expired' => 'b-gray', 'terminated' => 'b-red',
        'due' => 'b-sand', 'partial' => 'b-amber', 'paid' => 'b-sage', 'overdue' => 'b-red', 'cancelled' => 'b-gray',
        'low' => 'b-gray', 'medium' => 'b-sand', 'high' => 'b-amber', 'urgent' => 'b-red',
        'new' => 'b-clay', 'in_progress' => 'b-amber', 'waiting' => 'b-sand', 'completed' => 'b-sage',
        'archived' => 'b-gray', 'inactive' => 'b-gray', 'valid' => 'b-sage', 'void' => 'b-red',
    ][$key ?? ''] ?? 'b-gray';
    return '<span class="badge ' . $cls . '">' . e(label($group, $key)) . '</span>';
}

// ---------- التقسيم إلى صفحات ----------
function paginate(string $countSql, string $dataSql, array $params, int $page, ?int $per = null): array {
    $per = $per ?? (int)cfg('per_page', 20);
    $total = (int)q_val($countSql, $params);
    $pages = max(1, (int)ceil($total / $per));
    $page = max(1, min($page, $pages));
    $rows = q_all($dataSql . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per];
}
function pager(array $p): string {
    if ($p['pages'] <= 1) return '<div class="pager-info">إجمالي السجلات: ' . (int)$p['total'] . '</div>';
    $qs = $_GET; unset($qs['page']);
    $mk = function (int $n, string $label, bool $active = false, bool $disabled = false) use ($qs) {
        if ($disabled) return '<span class="pg disabled">' . $label . '</span>';
        $qs['page'] = $n;
        return '<a class="pg' . ($active ? ' active' : '') . '" href="?' . e(http_build_query($qs)) . '">' . $label . '</a>';
    };
    $h = '<nav class="pager"><div class="pager-info">إجمالي السجلات: ' . (int)$p['total'] . '</div><div class="pager-links">';
    $h .= $mk($p['page'] - 1, 'السابق', false, $p['page'] <= 1);
    $start = max(1, $p['page'] - 2); $end = min($p['pages'], $p['page'] + 2);
    if ($start > 1) { $h .= $mk(1, '1'); if ($start > 2) $h .= '<span class="pg disabled">…</span>'; }
    for ($i = $start; $i <= $end; $i++) $h .= $mk($i, (string)$i, $i === $p['page']);
    if ($end < $p['pages']) { if ($end < $p['pages'] - 1) $h .= '<span class="pg disabled">…</span>'; $h .= $mk($p['pages'], (string)$p['pages']); }
    $h .= $mk($p['page'] + 1, 'التالي', false, $p['page'] >= $p['pages']);
    return $h . '</div></nav>';
}

// ---------- عناصر مساعدة ----------
function opts(array $rows, string $valKey, string $labelKey, $selected = null, string $placeholder = ''): string {
    $h = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($rows as $r) {
        $v = (string)$r[$valKey];
        $h .= '<option value="' . e($v) . '"' . ((string)$selected === $v ? ' selected' : '') . '>' . e($r[$labelKey]) . '</option>';
    }
    return $h;
}
function opts_assoc(array $map, $selected = null, string $placeholder = ''): string {
    $h = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($map as $k => $v) $h .= '<option value="' . e((string)$k) . '"' . ((string)$selected === (string)$k ? ' selected' : '') . '>' . e($v) . '</option>';
    return $h;
}
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }

function csv_download(string $filename, array $header, iterable $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    echo "\xEF\xBB\xBF"; // BOM ليفتح Excel العربية بشكل صحيح
    $out = fopen('php://output', 'w');
    $safe = function ($v) { // منع حقن الصيغ في Excel
        $v = (string)$v;
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) ? "'" . $v : $v;
    };
    fputcsv($out, array_map($safe, $header));
    foreach ($rows as $r) fputcsv($out, array_map($safe, array_values($r)));
    fclose($out);
    exit;
}
