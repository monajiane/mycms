<?php
/**
 * admin/reviews.php — مدیریت نظرات (تأیید/رد)
 */
$pageTitle = 'مدیریت نظرات';
require __DIR__ . '/_header.php';

// تغییر وضعیت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    if (csrf_verify()) {
        $rid = (int)($_POST['review_id'] ?? 0);
        set_review_status($rid, $_POST['status'] ?? 'approved');
        flash('success', ($_POST['status'] ?? '') === 'approved' ? 'نظر تأیید و منتشر شد.' : 'نظر به حالت «در انتظار» برگشت.');
    }
    header('Location: reviews.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

// حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_verify()) {
        db()->prepare("DELETE FROM reviews WHERE id = ?")->execute([(int)($_POST['review_id'] ?? 0)]);
        flash('success', 'نظر حذف شد.');
    }
    header('Location: reviews.php');
    exit;
}

$filter = $_GET['status'] ?? null;
$reviews = all_reviews_admin($filter);
$pendingCount = pending_reviews_count();
?>

<h1 class="page-title">مدیریت نظرات</h1>

<div class="quick-actions" style="margin-bottom:1.2rem;">
    <a href="reviews.php" class="qa-btn<?= $filter === null ? ' qa-active' : '' ?>">همه</a>
    <a href="reviews.php?status=pending" class="qa-btn<?= $filter === 'pending' ? ' qa-active' : '' ?>">⏳ در انتظار تأیید (<?= $pendingCount ?>)</a>
    <a href="reviews.php?status=approved" class="qa-btn<?= $filter === 'approved' ? ' qa-active' : '' ?>">✅ تأییدشده</a>
</div>

<?php if (!$reviews): ?>
    <div class="dash-panel"><p class="muted">نظری یافت نشد.</p></div>
<?php else: ?>
<table class="data-table">
    <thead><tr><th>#</th><th>محصول</th><th>کاربر</th><th>امتیاز</th><th>متن نظر</th><th>وضعیت</th><th>عملیات</th></tr></thead>
    <tbody>
    <?php foreach ($reviews as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['product_name'] ?? '—') ?></td>
            <td><?= e($r['full_name'] ?: $r['username']) ?></td>
            <td><?= fa_digits((int)$r['rating']) ?> ★</td>
            <td style="max-width:280px;"><?= e(mb_substr((string)$r['comment'], 0, 100)) ?><?= mb_strlen((string)$r['comment']) > 100 ? '…' : '' ?></td>
            <td>
                <?php if (($r['status'] ?? 'approved') === 'pending'): ?>
                    <span class="status status-pending">⏳ در انتظار تأیید</span>
                <?php else: ?>
                    <span class="status status-paid">✅ منتشرشده</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" action="reviews.php<?= isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '' ?>" style="display:inline;" class="review-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                    <?php if (($r['status'] ?? 'approved') === 'pending'): ?>
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="btn btn-accent btn-sm">✓ تأیید</button>
                    <?php else: ?>
                        <input type="hidden" name="status" value="pending">
                        <button type="submit" class="btn btn-ghost btn-sm">↩ لغو انتشار</button>
                    <?php endif; ?>
                </form>
                <form method="post" action="reviews.php" style="display:inline;" class="review-actions" onsubmit="return confirm('نظر حذف شود؟')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<style>
.qa-active { background: var(--accent, #0f228c); color: #fff !important; border-color: var(--accent, #0f228c); }
.review-actions { display: inline-flex; gap: .3rem; }
.status-pending { background: #fef3c7; color: #92400e; padding: .2rem .6rem; border-radius: 999px; font-size: .78rem; }
</style>

<?php require __DIR__ . '/_footer.php'; ?>
