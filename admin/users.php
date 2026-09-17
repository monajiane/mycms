<?php
/**
 * admin/users.php — مدیریت کاربران + تغییر رمز
 */
$pageTitle = 'مدیریت کاربران';
require __DIR__ . '/_header.php';

$errors = [];
$success = get_flash('success') ?: '';
$error_msg = get_flash('error') ?: '';

/** شناسهٔ ادمین اصلی (نخستین حساب مدیر) — قابل حذف/تنزل نیست */
define('PRIMARY_ADMIN_ID', 1);

// افزودن کاربر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = ($_POST['role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';

    if ($username === '' || strlen($password) < 6) {
        $errors[] = 'نام کاربری و رمز عبور (حداقل ۶ کاراکتر) الزامی است.';
    }
    $exists = db()->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $exists->execute([$username]);
    if ((int)$exists->fetchColumn() > 0) {
        $errors[] = 'این نام کاربری قبلاً ثبت شده است.';
    }

    if (!$errors) {
        $st = db()->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $phone, $role]);
        // اطلاع به ادمین‌ها دربارهٔ کاربر افزوده‌شده (برای همکاران ادمین)
        try {
            $displayName = ($fullName !== '') ? $fullName : $username;
            sms_notify_admins(
                sms_template('admin_new_user', [
                    '{name}'     => $displayName,
                    '{username}' => $username,
                    '{phone}'    => ($phone !== '') ? $phone : 'ثبت نشده',
                ]),
                'admin_new_user',
                [$displayName, $username, ($phone !== '') ? $phone : '-']
            );
        } catch (Throwable $tnu) {
            // نوتیف نباید افزودن کاربر را خراب کند
        }
        flash('success', 'کاربر «' . $username . '» افزوده شد.');
        header('Location: users.php');
        exit;
    }
}

// تغییر نقش
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'role' && isset($_GET['role'])) {
    $id = (int)$_GET['id'];
    if ($id === PRIMARY_ADMIN_ID) {
        flash('error', 'نقش ادمین اصلی قابل تغییر نیست.');
    } else {
        $role = ($_GET['role'] === 'admin') ? 'admin' : 'customer';
        db()->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);
        flash('success', 'نقش کاربر تغییر کرد.');
    }
    header('Location: users.php');
    exit;
}

// تغییر رمز توسط مدیر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    $new_pass  = $_POST['new_password'] ?? '';
    if ($target_id === PRIMARY_ADMIN_ID && (int)$_SESSION['admin_id'] !== PRIMARY_ADMIN_ID) {
        flash('error', 'فقط ادمین اصلی می‌تواند رمز خودش را تغییر دهد.');
    } elseif (strlen($new_pass) < 6) {
        flash('error', 'رمز جدید باید حداقل ۶ کاراکتر باشد.');
    } elseif ($target_id <= 0) {
        flash('error', 'کاربر نامعتبر.');
    } else {
        db()->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([password_hash($new_pass, PASSWORD_DEFAULT), $target_id]);
        flash('success', 'رمز کاربر تغییر کرد.');
    }
    header('Location: users.php');
    exit;
}

// حذف کاربر
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id'];
    if ($id === PRIMARY_ADMIN_ID) {
        flash('error', 'ادمین اصلی قابل حذف نیست.');
    } elseif ($id === (int)$_SESSION['admin_id']) {
        flash('error', 'نمی‌توانید حساب خودتان را حذف کنید.');
    } else {
        db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        flash('success', 'کاربر حذف شد.');
    }
    header('Location: users.php');
    exit;
}

$users = db()->query("SELECT * FROM users ORDER BY id")->fetchAll();
?>

<div class="page-head">
    <h1 class="page-title">مدیریت کاربران</h1>
    <a href="profile.php" class="btn btn-ghost">👤 پروفایل من (تغییر رمز)</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-error"><?= e($error_msg) ?></div><?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="users.php" class="admin-form inline-form">
    <input type="hidden" name="action" value="add">
    <input type="text" name="username" placeholder="نام کاربری" required>
    <input type="password" name="password" placeholder="رمز عبور (۶+ کاراکتر)" required>
    <input type="text" name="full_name" placeholder="نام کامل">
    <input type="email" name="email" placeholder="ایمیل">
    <input type="tel" name="phone" placeholder="شماره تماس (مثلاً 09124045217)" pattern="^09[0-9]{9}$" maxlength="11">
    <select name="role">
        <option value="customer">مشتری</option>
        <option value="admin">مدیر</option>
    </select>
    <button type="submit" class="btn btn-accent">افزودن کاربر</button>
</form>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>نام کاربری</th>
            <th>نام کامل</th>
            <th>ایمیل</th>
            <th>شماره تماس</th>
            <th>نقش</th>
            <th>امتیاز</th>
            <th>عملیات</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u):
        $phone = $u['phone'] ?? '';
        $phone_link = $phone ? '<a href="tel:' . e($phone) . '" class="phone-link" dir="ltr">' . e($phone) . '</a>' : '—';
    ?>
        <tr class="<?= (int)$u['id'] === (int)$_SESSION['admin_id'] ? 'row-self' : '' ?>">
            <td><?= (int)$u['id'] ?></td>
            <td>
                <?= e($u['username']) ?>
                <?php if ((int)$u['id'] === (int)$_SESSION['admin_id']): ?>
                    <span class="badge badge-accent">شما</span>
                <?php endif; ?>
            </td>
            <td><?= e($u['full_name'] ?: '—') ?></td>
            <td>
                <?php if (!empty($u['email'])): ?>
                    <a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= $phone_link ?></td>
            <td>
                <span class="status <?= $u['role'] === 'admin' ? 'status-paid' : 'status-pending' ?>">
                    <?= $u['role'] === 'admin' ? 'مدیر' : 'مشتری' ?>
                </span>
            </td>
            <td><?= (int)($u['points'] ?? 0) ?></td>
            <td class="actions">
                <?php if ((int)$u['id'] === PRIMARY_ADMIN_ID && (int)$_SESSION['admin_id'] !== PRIMARY_ADMIN_ID): ?>
                    <span class="badge badge-gold">ادمین اصلی</span>
                <?php elseif ((int)$u['id'] === PRIMARY_ADMIN_ID && (int)$_SESSION['admin_id'] === PRIMARY_ADMIN_ID): ?>
                    <a href="profile.php" class="btn btn-ghost btn-sm">✏️ ویرایش پروفایل و تلفن</a>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="openResetPwd(<?= (int)$u['id'] ?>, '<?= e(addslashes($u['username'])) ?>')">تغییر رمز</button>
                <?php else: ?>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="openResetPwd(<?= (int)$u['id'] ?>, '<?= e(addslashes($u['username'])) ?>')">تغییر رمز</button>
                    <?php if ((int)$u['id'] !== (int)$_SESSION['admin_id']): ?>
                        <a href="users.php?action=role&id=<?= (int)$u['id'] ?>&role=<?= $u['role'] === 'admin' ? 'customer' : 'admin' ?>" class="btn btn-ghost btn-sm">تغییر نقش</a>
                        <a href="users.php?action=delete&id=<?= (int)$u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('حذف شود؟')">حذف</a>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- مودال تغییر رمز -->
<div class="modal-overlay" id="resetPwdModal" style="display:none">
    <div class="modal-card">
        <h3>تغییر رمز عبور</h3>
        <p>کاربر: <strong id="resetPwdUser"></strong></p>
        <form method="post" action="users.php">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetPwdUserId" value="0">
            <label>رمز جدید (حداقل ۶ کاراکتر):</label>
            <input type="password" name="new_password" minlength="6" required autofocus>
            <div class="modal-actions">
                <button type="submit" class="btn btn-accent">تغییر رمز</button>
                <button type="button" class="btn btn-ghost" onclick="closeResetPwd()">انصراف</button>
            </div>
        </form>
    </div>
</div>

<style>
.page-head { display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; }
.row-self { background: #f0f7ff; }
.phone-link { color: #1d4ed8; text-decoration: none; font-weight: 500; }
.phone-link:hover { text-decoration: underline; }
.badge { display:inline-block; padding:1px 6px; border-radius:8px; font-size:11px; margin-right:4px; vertical-align: middle; }
.badge-accent { background: #dbeafe; color: #1e40af; }
.badge-gold { background: #fef3c7; color: #92400e; }
.status { padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.status-paid { background: #d1fae5; color: #065f46; }
.status-pending { background: #f3f4f6; color: #4b5563; }
.alert { padding: 10px 14px; border-radius: 8px; margin: 10px 0; }
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; display: flex; align-items: center; justify-content: center; }
.modal-card { background: #fff; padding: 24px; border-radius: 12px; width: 360px; max-width: 90%; }
.modal-card h3 { margin: 0 0 12px; }
.modal-card label { display: block; margin: 12px 0 6px; font-weight: 600; }
.modal-card input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
.modal-actions { display: flex; gap: 8px; margin-top: 16px; }
.inline-form { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 24px; }
.inline-form input, .inline-form select { padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 6px; }
.actions { white-space: nowrap; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
</style>

<script>
function openResetPwd(id, username) {
    document.getElementById('resetPwdUserId').value = id;
    document.getElementById('resetPwdUser').textContent = username;
    document.getElementById('resetPwdModal').style.display = 'flex';
}
function closeResetPwd() {
    document.getElementById('resetPwdModal').style.display = 'none';
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeResetPwd();
});
document.getElementById('resetPwdModal').addEventListener('click', (e) => {
    if (e.target.id === 'resetPwdModal') closeResetPwd();
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>
