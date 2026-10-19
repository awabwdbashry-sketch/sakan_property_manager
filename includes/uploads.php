<?php
declare(strict_types=1);

const UPLOAD_IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const UPLOAD_DOC_MIMES   = ['application/pdf' => 'pdf'];

/** كيان => [صلاحية العرض، صلاحية الإدارة] */
function attachment_perms(string $entity): ?array {
    return [
        'property'    => ['properties.view', 'properties.manage'],
        'unit'        => ['units.view', 'units.manage'],
        'tenant'      => ['tenants.view', 'tenants.manage'],
        'contract'    => ['contracts.view', 'contracts.manage'],
        'expense'     => ['expenses.view', 'expenses.manage'],
        'maintenance' => ['maintenance.view', 'maintenance.manage'],
        'setting'     => ['dashboard.view', 'settings.manage'],
    ][$entity] ?? null;
}

function storage_dir(): string {
    $p = rtrim(str_replace('\\', '/', (string)cfg('storage_path')), '/');
    return $p . '/uploads';
}

/**
 * يعالج ملفاً مرفوعاً واحداً من $_FILES[$field][$i]. يرجع [id|null, خطأ|null]
 */
function save_upload(array $file, string $entity, int $entityId, string $kind): array {
    if (!attachment_perms($entity)) return [null, 'نوع كيان غير صالح.'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return [null, 'حجم الملف يتجاوز الحد المسموح به.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'فشل رفع الملف (رمز ' . (int)$file['error'] . ').'];
    $max = (int)cfg('max_upload_mb', 5) * 1024 * 1024;
    if ($file['size'] > $max) return [null, 'حجم الملف يتجاوز الحد الأقصى (' . cfg('max_upload_mb', 5) . ' ميجابايت).'];
    if (!is_uploaded_file($file['tmp_name']) && !defined('SAKAN_TESTING')) return [null, 'ملف غير صالح.'];

    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$fi->file($file['tmp_name']);
    $allowed = $kind === 'image' ? UPLOAD_IMAGE_MIMES : (UPLOAD_IMAGE_MIMES + UPLOAD_DOC_MIMES);
    if (!isset($allowed[$mime])) return [null, $kind === 'image' ? 'الصور المسموحة: JPG وPNG وWEBP فقط.' : 'الأنواع المسموحة: PDF وJPG وPNG وWEBP.'];
    if (isset(UPLOAD_IMAGE_MIMES[$mime])) {
        $info = @getimagesize($file['tmp_name']);
        if (!$info || $info[0] < 1 || $info[1] < 1) return [null, 'ملف الصورة تالف أو غير صالح.'];
    } elseif (strncmp((string)file_get_contents($file['tmp_name'], false, null, 0, 5), '%PDF-', 5) !== 0) {
        return [null, 'ملف PDF غير صالح.'];
    }
    $ext = $allowed[$mime];
    $stored = bin2hex(random_bytes(20)) . '.' . $ext;
    $sub = date('Y-m');
    $dir = storage_dir() . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return [null, 'تعذّر إنشاء مجلد التخزين. راجع صلاحيات المجلد.'];
    $dest = $dir . '/' . $stored;
    $moved = defined('SAKAN_TESTING') ? copy($file['tmp_name'], $dest) : move_uploaded_file($file['tmp_name'], $dest);
    if (!$moved) return [null, 'تعذّر حفظ الملف على الخادم.'];
    @chmod($dest, 0644);

    $orig = preg_replace('/[\x00-\x1F\x7F\\\\\/]/u', '', (string)$file['name']);
    q("INSERT INTO attachments (entity_type, entity_id, kind, original_name, stored_name, mime, size, uploaded_by) VALUES (?,?,?,?,?,?,?,?)",
      [$entity, $entityId, isset(UPLOAD_IMAGE_MIMES[$mime]) ? 'image' : 'document', mb_substr($orig, 0, 250), $sub . '/' . $stored, $mime, (int)$file['size'], user_id()]);
    $id = insert_id();
    audit('attachment.add', $entity, $entityId, ['attachment_id' => $id, 'name' => $orig]);
    return [$id, null];
}

/** رفع عدة ملفات من حقل واحد (name="field[]") */
function save_uploads(string $field, string $entity, int $entityId, string $kind): array {
    $errors = []; $ok = 0;
    if (empty($_FILES[$field])) return [0, []];
    $f = $_FILES[$field];
    if (!is_array($f['name'])) { $f = array_map(fn($v) => [$v], $f); }
    foreach (array_keys($f['name']) as $i) {
        $one = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        [$id, $err] = save_upload($one, $entity, $entityId, $kind);
        if ($err) $errors[] = $one['name'] . ': ' . $err; elseif ($id) $ok++;
    }
    return [$ok, $errors];
}

function attachments_for(string $entity, int $entityId, ?string $kind = null): array {
    $sql = "SELECT id, kind, original_name, mime, size, created_at FROM attachments WHERE entity_type = ? AND entity_id = ?";
    $p = [$entity, $entityId];
    if ($kind) { $sql .= " AND kind = ?"; $p[] = $kind; }
    return q_all($sql . " ORDER BY id", $p);
}
function attachment_url(int $id, bool $download = false): string { return url('file.php?id=' . $id . ($download ? '&dl=1' : '')); }

function delete_attachment(int $id): bool {
    $a = q_one("SELECT * FROM attachments WHERE id = ?", [$id]);
    if (!$a) return false;
    $path = storage_dir() . '/' . $a['stored_name'];
    q("DELETE FROM attachments WHERE id = ?", [$id]);
    if (is_file($path)) @unlink($path);
    audit('attachment.delete', $a['entity_type'], (int)$a['entity_id'], ['attachment_id' => $id, 'name' => $a['original_name']]);
    return true;
}
