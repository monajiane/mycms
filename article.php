<?php
/**
 * article.php — صفحهٔ تکی مقالهٔ دانشنامه
 */
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = get_article($id);
if (!$article) {
    header('Location: knowledge.php');
    exit;
}

$pageTitle = ($article['seo_title'] ?? '') !== '' ? $article['seo_title'] : $article['title'];
$excerpt = article_excerpt($article, 160);
$autoDesc = ($article['seo_description'] ?? '') !== '' ? $article['seo_description'] : $excerpt;
if ($autoDesc !== '') {
    $pageDescription = $autoDesc;
}
if (($article['og_image'] ?? '') !== '') {
    $pageImage = product_image_url($article['og_image']);
} elseif (!empty($article['image'])) {
    $pageImage = product_image_url($article['image']);
}
if (!empty($article['noindex'])) {
    $pageRobots = 'noindex, nofollow';
}
$pageOgType = 'article';
$pageHeadExtra = article_jsonld($article);
if (($article['seo_keywords'] ?? '') !== '') {
    $pageHeadExtra .= '<meta name="keywords" content="' . e($article['seo_keywords']) . '">' . "\n";
}
require __DIR__ . '/includes/header.php';
?>

<section class="container article-single">
    <nav class="breadcrumb">
        <a href="knowledge.php">دانشنامه</a> ›
        <span><?= e($article['title']) ?></span>
    </nav>

    <article class="article-content">
        <header class="article-head">
            <p class="hero-eyebrow">دانشنامه</p>
            <h1><?= e($article['title']) ?></h1>
            <time class="article-date"><?= e(persian_date($article['created_at'], true)) ?></time>
        </header>

        <?php if (!empty($article['image'])): ?>
            <figure class="article-cover">
                <img src="<?= e(product_image_url($article['image'])) ?>" alt="<?= e($article['title']) ?>">
            </figure>
        <?php endif; ?>

        <?php if (!empty($article['video'])): ?>
            <?= article_video_embed($article['video']) ?>
        <?php endif; ?>

        <div class="article-body-text">
            <?= render_description($article['content']) ?>
        </div>
    </article>

    <div class="article-back">
        <a href="knowledge.php" class="btn btn-ghost">→ بازگشت به دانشنامه</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
