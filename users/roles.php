<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('users.manage');
$roles = q_all("SELECT * FROM roles ORDER BY id");
$perms = q_all("SELECT * FROM permissions ORDER BY sort_order");
if (is_post()) {
    csrf_check();
    $act = post('act');
    if ($act === 'create') {
        $name = post('name');
        if (mb_strlen($name) < 3) flash('error', 'اسم الدور مطلوب.');
        else { q("INSERT INTO roles (code, name, is_system) VALUES (?,?,0)", ['custom_' . bin2hex(random_bytes(4)), mb_substr($name, 0, 100)]); audit('role.create', 'role', insert_id(), ['name' => $name]); flash('success', 'تم إنشاء الدور. حدّد صلاحياته الآن.'); }
    } elseif ($act === 'save') {
        $rid = post_int('role_id');
        $role = q_one("SELECT * FROM roles WHERE id = ?", [$rid]);
        if (!$role) { flash('error', 'الدور غير موجود.'); redirect('users/roles.php'); }
        $sel = array_values(array_intersect(array_column($perms, 'code'), array_map('strval', (array)($_POST['perm'] ?? []))));
        if ($role['code'] === 'admin') { flash('warning', 'صلاحيات مدير النظام ثابتة (كل الصلاحيات) لتفادي فقدان الوصول.'); redirect('users/roles.php'); }
        if (in_array('users.manage', $sel, true) === false && q_val("SELECT 1 FROM role_permissions WHERE role_id = ? AND perm_code = 'users.manage'", [$rid]) && active_admin_count(0) - (int)q_val("SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1", [$rid]) < 1) {
            flash('error', 'لا يمكن سحب صلاحية إدارة المستخدمين لأنها ستُفقد آخر مدير فعّال.'); redirect('users/roles.php');
        }
        tx(function () use ($rid, $sel) {
            q("DELETE FROM role_permissions WHERE role_id = ?", [$rid]);
            foreach ($sel as $c) q("INSERT INTO role_permissions (role_id, perm_code) VALUES (?,?)", [$rid, $c]);
            audit('role.perms', 'role', $rid, ['permissions' => $sel]);
        });
        flash('success', 'تم حفظ صلاحيات الدور.');
    }
    redirect('users/roles.php');
}
$has = []; foreach (q_all("SELECT role_id, perm_code FROM role_permissions") as $r) $has[$r['role_id']][$r['perm_code']] = true;
layout_start('الأدوار والصلاحيات', 'users');
page_actions('<a class="btn" href="' . e(url('users/')) . '">رجوع للمستخدمين</a>');
?>
<div class="alert alert-info">تُفرض الصلاحيات على الخادم في كل صفحة وعملية، وليس بإخفاء الأزرار فقط. دور «مدير النظام» ثابت بكل الصلاحيات.</div>
<?php foreach ($roles as $r): $locked = $r['code'] === 'admin'; ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="role_id" value="<?= (int)$r['id'] ?>">
  <div class="card-head"><h2><?= e($r['name']) ?> <?= $locked ? '<span class="badge b-clay">ثابت</span>' : '' ?></h2><?php if (!$locked): ?><button class="btn btn-primary btn-sm" type="submit">حفظ الصلاحيات</button><?php endif; ?></div>
  <?php $grp = ''; foreach ($perms as $p): if ($p['grp'] !== $grp) { if ($grp !== '') echo '</div>'; $grp = $p['grp']; echo '<h4 style="margin:14px 0 6px;color:var(--clay-d)">' . e($grp) . '</h4><div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:6px 16px">'; } ?>
    <label class="check"><input type="checkbox" name="perm[]" value="<?= e($p['code']) ?>" <?= !empty($has[$r['id']][$p['code']]) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>> <?= e($p['name']) ?></label>
  <?php endforeach; echo '</div>'; ?>
</form>
<?php endforeach; ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="act" value="create"><h3>إضافة دور جديد</h3>
  <div class="filters" style="border:0;padding:0;background:none"><div class="field grow"><input type="text" name="name" required minlength="3" maxlength="100" placeholder="مثال: محاسب"></div><button class="btn btn-primary" type="submit">إنشاء</button></div></form>
<?php layout_end();
