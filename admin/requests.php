<?php
/**
 * admin/requests.php — مدیریت درخواست‌های ابزار سفارشی
 * فهرست، جزئیات، تأیید (ساخت محصول خصوصی) و رد (با یادداشت)
 */
$pageTitle = 'درخواست‌های ابزار سفارشی';
require __DIR__ . '/_header.php';

// ---- تأیید یا رد درخواست ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['decision'])) {
    if (!csrf_verify()) {
        flash('error', 'نشست نامعتبر است؛ لطفاً دوباره تلاش کنید.');
        header('Location: requests.php');
        exit;
    }
    $reqId = (int)$_POST['request_id'];
    $req = get_request($reqId);
    $decision = $_POST['decision'];

    if ($req && $req['status'] === 'pending') {
        if ($decision === 'approve') {
            $price = (int)($_POST['price'] ?? 0);
            $note  = trim($_POST['admin_note'] ?? '');
            if ($price <= 0) {
                flash('error', 'برای تأیید، قیمت نهایی (تومان) باید بزرگ‌تر از صفر باشد.');
                header('Location: requests.php?id=' . $reqId);
                exit;
            }
            // ساخت محصول خصوصی (فقط برای همان کاربر)
            $desc = trim((string)$req['description']);
            if ($note !== '') {
                $desc .= "\n\nیادداشت مدیر: " . $note;
            }
            db()->prepare("INSERT INTO products (name, slug, description, price, category_id, stock, image, featured, active, owner_user_id)
                           VALUES (?, ?, ?, ?, NULL, ?, '', 0, 1, ?)")
                ->execute([$req['title'], make_slug($req['title']), $desc, $price, max(1, (int)$req['quantity']), (int)$req['user_id']]);
            $productId = (int)db()->lastInsertId();
            db()->prepare("UPDATE custom_requests SET status = 'approved', price = ?, admin_note = ?, product_id = ? WHERE id = ?")
                ->execute([$price, $note, $productId, $reqId]);
            flash('success', 'درخواست تأیید شد و محصول خصوصی برای کاربر ساخته شد.');
        } elseif ($decision === 'reject') {
            $note = trim($_POST['admin_note'] ?? '');
            db()->prepare("UPDATE custom_requests SET status = 'rejected', admin_note = ? WHERE id = ?")
                ->execute([$note, $reqId]);
            flash('success', 'درخواست رد شد.');
        }
        header('Location: requests.php');
        exit;
    }
    header('Location: requests.php');
    exit;
}

// ---- جزئیات یک درخواست ----
$detail = null;
if (isset($_GET['id'])) {
    $detail = get_request((int)$_GET['id']);
}

if ($detail) {
    $u = db()->prepare("SELECT * FROM users WHERE id = ?");
    $u->execute([(int)$detail['user_id']]);
    $reqUser = $u->fetch();
    ?>
    <h1 class="page-title">درخواست #<?= (int)$detail['id'] ?></h1>
    <a href="requests.php" class="btn btn-ghost btn-sm">→ بازگشت به فهرست</a>

    <div class="dash-panel">
        <dl class="detail-meta">
            <dt>وضعیت</dt><dd><span class="status req-<?= e($detail['status']) ?>"><?= e(request_status_label($detail['status'])) ?></span></dd>
            <dt>کاربر</dt><dd><?= e($reqUser['full_name'] ?: $reqUser['username'] ?? '—') ?> (<?= e($reqUser['username'] ?? '—') ?>)</dd>
            <dt>نام ابزار</dt><dd><?= e($detail['title']) ?></dd>
            <dt>تعداد</dt><dd><?= (int)$detail['quantity'] ?></dd>
            <dt>تاریخ</dt><dd><?= e(persian_date($detail['created_at'] ?? null)) ?></dd>
            <?php if ($detail['file'] && is_file(__DIR__ . '/../' . $detail['file'])): ?>
                <dt>فایل فنی</dt><dd><a href="<?= e(BASE_URL) ?>/<?= e($detail['file']) ?>" target="_blank">مشاهده / دانلود فایل</a></dd>
            <?php elseif ($detail['file']): ?>
                <dt>فایل فنی</dt><dd>فایل موجود نیست</dd>
            <?php else: ?>
                <dt>فایل فنی</dt><dd>— (پیوست ندارد)</dd>
            <?php endif; ?>
            <?php if ($detail['status'] === 'approved'): ?>
                <dt>قیمت نهایی</dt><dd><?= e(fmt_price((int)$detail['price'])) ?></dd>
                <?php if ($detail['product_id']): ?><dt>محصول ساخته‌شده</dt><dd><a href="../product.php?id=<?= (int)$detail['product_id'] ?>" target="_blank">مشاهده (ID <?= (int)$detail['product_id'] ?>)</a></dd><?php endif; ?>
            <?php endif; ?>
        </dl>
    </div>

    <div class="dash-panel">
        <h2>توضیحات مشتری</h2>
        <p class="req-desc"><?= nl2br(e($detail['description'])) ?></p>
    </div>

    <?php if ($detail['status'] === 'pending'): ?>
        <div class="dash-cols">
            <section class="dash-panel">
                <h2>تأیید و ساخت محصول</h2>
                <form method="post" action="requests.php" class="admin-form">
                    <input type="hidden" name="request_id" value="<?= (int)$detail['id'] ?>">
                    <input type="hidden" name="decision" value="approve">
                    <?= csrf_field() ?>
                    <label>قیمت نهایی (تومان) *
                        <input type="number" name="price" min="1" step="1" required>
                    </label>
                    <label>یادداشت برای مشتری (اختیاری)
                        <textarea name="admin_note" rows="3"></textarea>
                    </label>
                    <button type="submit" class="btn btn-accent">تأیید و ساخت محصول خصوصی</button>
                </form>
            </section>
            <section class="dash-panel">
                <h2>رد درخواست</h2>
                <form method="post" action="requests.php" class="admin-form">
                    <input type="hidden" name="request_id" value="<?= (int)$detail['id'] ?>">
                    <input type="hidden" name="decision" value="reject">
                    <?= csrf_field() ?>
                    <label>دلیل رد (اختیاری)
                        <textarea name="admin_note" rows="3"></textarea>
                    </label>
                    <button type="submit" class="btn btn-danger">رد درخواست</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
    <?php
} else {
    $list = db()->query("SELECT r.*, u.username, u.full_name FROM custom_requests r
                         LEFT JOIN users u ON u.id = r.user_id
                         ORDER BY r.id DESC")->fetchAll();
    $pending = db()->query("SELECT COUNT(*) FROM custom_requests WHERE status = 'pending'")->fetchColumn();
    ?>
    <h1 class="page-title">درخواست‌های ابزار سفارشی</h1>
    <?php if ($pending > 0): ?>
        <div class="alert alert-warning"><?= (int)$pending ?> درخواست در انتظار بررسی دارید.</div>
    <?php endif; ?>

    <table class="data-table">
        <thead><tr><th>#</th><th>کاربر</th><th>نام ابزار</th><th>تعداد</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
        <tbody>
        <?php if (!$list): ?>
            <tr><td colspan="7" class="muted">هنوز درخواستی ثبت نشده است.</td></tr>
        <?php endif; ?>
        <?php foreach ($list as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['full_name'] ?: $r['username'] ?? '—') ?></td>
                <td><?= e($r['title']) ?></td>
                <td><?= (int)$r['quantity'] ?></td>
                <td><span class="status req-<?= e($r['status']) ?>"><?= e(request_status_label($r['status'])) ?></span></td>
                <td><?= e(persian_date($r['created_at'] ?? null)) ?></td>
                <td><a href="requests.php?id=<?= (int)$r['id'] ?>" class="btn btn-ghost btn-sm">بررسی</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

require __DIR__ . '/_footer.php';
