<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int)$c['port'], $c['name'], $c['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function q_one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function q_val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function insert_id(): int { return (int)db()->lastInsertId(); }

/** تنفيذ دالة داخل معاملة. يدعم الاستدعاء المتداخل. */
function tx(callable $fn) {
    $pdo = db();
    if ($pdo->inTransaction()) return $fn();
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** رقم تسلسلي آمن (داخل المعاملة) */
function next_counter(string $name): int {
    q("INSERT INTO counters (name, value) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)", [$name]);
    return insert_id();
}

function setting(string $key, $default = null) {
    if (!isset($GLOBALS['SAKAN_SETTINGS'])) {
        $GLOBALS['SAKAN_SETTINGS'] = [];
        foreach (q_all("SELECT skey, svalue FROM settings") as $r) $GLOBALS['SAKAN_SETTINGS'][$r['skey']] = $r['svalue'];
    }
    $c = $GLOBALS['SAKAN_SETTINGS'];
    return array_key_exists($key, $c) && $c[$key] !== null ? $c[$key] : $default;
}
function setting_set(string $key, ?string $value): void {
    q("INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$key, $value]);
    unset($GLOBALS['SAKAN_SETTINGS']);
}
