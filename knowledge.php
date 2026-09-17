<?php
/**
 * knowledge.php — دانشنامه (مقالات فنی و آموزشی)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'دانشنامه | ' . setting('store_name');
$articles = get_articles();

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="hero-eyebrow"><?= e(pc_get('knowledge', 'hero_eyebrow', 'دانشنامه')) ?></p>
        <h1 class="page-title page-title-lg"><?= e(pc_get('knowledge', 'hero_title', 'مطالب فنی و آموزشی')) ?></h1>
        <p class="hero-tagline"><?= e(pc_get('knowledge', 'hero_tagline', 'راهنما، نکات تخصصی و آموزش ابزار صنعتی')) ?></p>
    </div>
</section>

<section class="container articles-section">
    <?php if (!$articles): ?>
        <div class="empty-state">
            <p>هنوز مقاله‌ای منتشر نشده است.</p>
        </div>
    <?php else: ?>
        <div class="articles-grid">
            <?php foreach ($articles as $a): ?>
                <article class="article-card reveal-on-scroll">
                    <a href="article.php?id=<?= (int)$a['id'] ?>" class="article-thumb">
                        <?php if (!empty($a['image'])): ?>
                            <img src="<?= e(product_image_url($a['image'])) ?>" alt="<?= e($a['title']) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="thumb-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" style="width:34%;max-width:96px;height:auto;opacity:.85"><path d="M5 4h9a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3V4z" stroke-width="1.6"/><path d="M17 20V7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2M8 8h5M8 12h5" stroke-width="1.6" stroke-linecap="round"/></svg></div>
                        <?php endif; ?>
                    </a>
                    <div class="article-body">
                        <time class="article-date"><?= e(persian_date($a['created_at'])) ?></time>
                        <a href="article.php?id=<?= (int)$a['id'] ?>" class="article-title"><?= e($a['title']) ?></a>
                        <p class="article-excerpt"><?= e(article_excerpt($a)) ?></p>
                        <a href="article.php?id=<?= (int)$a['id'] ?>" class="article-more">ادامهٔ مطلب →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
