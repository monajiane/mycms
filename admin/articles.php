<?php
/**
 * admin/articles.php — مدیریت مقالات دانشنامه (فهرست + حذف)
 */
$pageTitle = 'مدیریت مقالات';
require __DIR__ . '/_header.php';

// حذف مقاله
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $aid = (int)$_GET['id'];
    $art = get_article_admin($aid);
    if ($art && !empty($art['image']) && strpos($art['image'], 'uploads/') === 0) {
        $f = __DIR__ . '/../' . $art['image'];
        if (is_file($f)) { @unlink($f); }
    }
    if ($art && !empty($art['video']) && strpos($art['video'], 'uploads/') === 0) {
        $f = __DIR__ . '/../' . $art['video'];
        if (is_file($f)) { @unlink($f); }
    }
    db()->prepare("DELETE FROM articles WHERE id = ?")->execute([$aid]);
    flash('success', 'مقاله حذف شد.');
    header('Location: articles.php');
    exit;
}

$articles = db()->query("SELECT * FROM articles ORDER BY id DESC")->fetchAll();
?>

<h1 class="page-title">مدیریت مقالات</h1>
<a href="article_edit.php" class="btn btn-accent">+ افزودن مقاله جدید</a>

<?php if (!$articles): ?>
    <div class="dash-panel"><p class="muted">هنوز مقاله‌ای ثبت نشده است.</p></div>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr><th>#</th><th>عنوان</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr>
    </thead>
    <tbody>
    <?php foreach ($articles as $a): ?>
        <tr>
            <td><?= (int)$a['id'] ?></td>
            <td><?= e($a['title']) ?></td>
            <td><?= e(persian_date($a['created_at'])) ?></td>
            <td><?= $a['published'] ? 'منتشرشده' : 'پیش‌نویس' ?></td>
            <td class="actions">
                <a href="article_edit.php?id=<?= (int)$a['id'] ?>" class="btn btn-ghost btn-sm">ویرایش</a>
                <a href="../article.php?id=<?= (int)$a['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">مشاهده</a>
                <a href="articles.php?action=delete&id=<?= (int)$a['id'] ?>" class="btn btn-danger btn-sm"
                   onclick="return confirm('این مقاله حذف شود؟')">حذف</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
