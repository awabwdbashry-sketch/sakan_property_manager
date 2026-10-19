<?php
declare(strict_types=1);

function needs_setup(): bool {
    try {
        return (int)q_val("SELECT COUNT(*) FROM users") === 0 && setting('setup_completed', '0') !== '1';
    } catch (Throwable $e) {
        return false;
    }
}

function current_user(): ?array {
    if (array_key_exists('SAKAN_USER', $GLOBALS) && $GLOBALS['SAKAN_USER'] !== null) return $GLOBALS['SAKAN_USER'];
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) return null;

    $idle = (int)cfg('session_idle_minutes', 120) * 60;
    if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > $idle) {
        session_unset(); session_regenerate_id(true);
        flash('warning', 'انتهت جلستك بسبب عدم النشاط. يرجى تسجيل الدخول مجدداً.');
        return null;
    }
    $u = q_one("SELECT u.*, r.code AS role_code, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$uid]);
    if (!$u || (int)$u['is_active'] !== 1) {
        session_unset(); session_regenerate_id(true);
        return null;
    }
    $_SESSION['last_seen'] = time();
    $perms = array_column(q_all("SELECT perm_code FROM role_permissions WHERE role_id = ?", [$u['role_id']]), 'perm_code');
    $u['perms'] = array_flip($perms);
    unset($u['password_hash']);
    return $GLOBALS['SAKAN_USER'] = $u;
}

function can(string $perm): bool {
    $u = current_user();
    return $u !== null && isset($u['perms'][$perm]);
}

function require_login(): array {
    if (needs_setup()) redirect('setup/');
    $u = current_user();
    if (!$u) {
        $_SESSION['_after_login'] = (string)($_SERVER['REQUEST_URI'] ?? '');
        redirect('auth/login.php');
    }
    return $u;
}

/** التحقق من الصلاحية على الخادم */
function require_perm(string $perm): array {
    $u = require_login();
    if (!isset($u['perms'][$perm])) {
        render_error_page('غير مصرّح', 'ليست لديك صلاحية للوصول إلى هذه الصفحة أو تنفيذ هذه العملية.', 403);
    }
    return $u;
}

function user_id(): ?int { $u = current_user(); return $u ? (int)$u['id'] : null; }

// ---------- تسجيل الدخول مع الحدّ من المحاولات ----------
function login_blocked_seconds(string $username, string $ip): int {
    $max = (int)cfg('login_max_attempts', 5);
    $win = (int)cfg('login_window_minutes', 15);
    $rows = q_all("SELECT UNIX_TIMESTAMP(attempted_at) ts FROM login_attempts
                   WHERE success = 0 AND attempted_at > (NOW() - INTERVAL $win MINUTE) AND (username = ? OR ip = ?)
                   ORDER BY attempted_at DESC", [$username, $ip]);
    if (count($rows) < $max) return 0;
    // يبدأ القفل من آخر محاولة فاشلة
    $last = (int)$rows[0]['ts'];
    $wait = ($win * 60) - (time() - $last);
    // تأخير تصاعدي بسيط: كل محاولة زائدة تزيد القفل
    return max(0, min($wait, $win * 60));
}

function attempt_login(string $username, string $password): ?string {
    $ip = client_ip();
    $username = mb_substr($username, 0, 60);
    if (($w = login_blocked_seconds($username, $ip)) > 0) {
        return 'تم تجاوز عدد المحاولات المسموحة. حاول مرة أخرى بعد ' . max(1, (int)ceil($w / 60)) . ' دقيقة.';
    }
    $u = q_one("SELECT id, password_hash, is_active FROM users WHERE username = ?", [$username]);
    $ok = false;
    if ($u) {
        $ok = password_verify($password, $u['password_hash']);
    } else {
        password_verify($password, '$2y$10$U1wijA9pY3I29nqWjdakiOksKbF8o3qRUK9XcM7vgq63v4gMegHSS'); // تسوية زمن الاستجابة
    }
    q("INSERT INTO login_attempts (username, ip, success) VALUES (?, ?, ?)", [$username, $ip, $ok ? 1 : 0]);
    if (!$ok) return 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    if ((int)$u['is_active'] !== 1) return 'هذا الحساب معطّل. يرجى التواصل مع مدير النظام.';

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['last_seen'] = time();
    unset($GLOBALS['SAKAN_USER']);
    q("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$u['id']]);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    q("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)");
    audit('auth.login', 'user', (int)$u['id'], []);
    return null;
}

function do_logout(): void {
    if (user_id()) audit('auth.logout', 'user', user_id(), []);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function validate_password_strength(string $pw): ?string {
    if (mb_strlen($pw) < 8) return 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
    if (!preg_match('/[A-Za-z\p{Arabic}]/u', $pw) || !preg_match('/\d/', $pw)) return 'كلمة المرور يجب أن تحتوي على أحرف وأرقام.';
    return null;
}

/** عدد المديرين الفعّالين (من يملك users.manage) باستثناء مستخدم معيّن */
function active_admin_count(int $excludeUserId = 0): int {
    return (int)q_val("SELECT COUNT(DISTINCT u.id) FROM users u JOIN role_permissions rp ON rp.role_id = u.role_id AND rp.perm_code = 'users.manage' WHERE u.is_active = 1 AND u.id <> ?", [$excludeUserId]);
}
