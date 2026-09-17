<?php
/**
 * includes/page_builder_render.php — رندر بلوک‌ها در front-end
 * استفاده:
 *   $page = get_page_by_slug('landing');
 *   page_builder_render($page['blocks_json']);
 */
function to_slug($text) {
    $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    $text = strtr($text, $map);
    $text = trim($text);
    if ($text === '') return '';
    // اگر فقط ASCII باشد، استاندارد slug می‌سازد
    if (preg_match('/^[\x20-\x7e]+$/', $text)) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }
    // متن فارسی/عربی: فقط URL-encode می‌کنیم
    return rawurlencode($text);
}

function get_page_by_slug($slug) {
    $st = db()->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published' LIMIT 1");
    $st->execute([$slug]);
    return $st->fetch();
}

/**
 * طرحِ ساخته‌شده با ویرایشگر بصری (GrapesJS) را برمی‌گرداند.
 * اگر جدول نباشد یا طرحی ذخیره نشده باشد، null برمی‌گردد (بدون خطا).
 */
function get_page_design($pageId) {
    try {
        $st = db()->prepare("SELECT * FROM page_designs WHERE page_id = ? LIMIT 1");
        $st->execute([(int)$pageId]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * رندر طرحِ بصری؛ اگر طرحی نباشد به بلوک‌های قدیمی برمی‌گردد.
 */
function page_design_render($page) {
    $design = get_page_design($page['id'] ?? 0);
    if ($design && trim((string)($design['html'] ?? '')) !== '') {
        echo '<div class="pd-render">';
        if (trim((string)($design['css'] ?? '')) !== '') {
            echo '<style>' . $design['css'] . '</style>';
        }
        echo $design['html'];
        echo '</div>';
        return true;
    }
    page_builder_render($page['blocks_json'] ?? '[]');
    return false;
}

function page_builder_render($json) {
    $blocks = json_decode($json, true);
    if (!is_array($blocks) || !$blocks) return;
    echo '<div class="pb-render">';
    foreach ($blocks as $b) {
        $type = $b['type'] ?? '';
        $fn = 'pb_render_' . $type;
        if (function_exists($fn)) {
            echo '<section class="pb-section pb-section--' . e($type) . '">';
            $fn($b);
            echo '</section>';
        }
    }
    echo '</div>';
}

function pb_render_hero($b) {
    $img = !empty($b['image']) ? product_image_url($b['image']) : '';
    ?>
    <div class="pb-hero" style="<?= $img ? 'background-image:linear-gradient(rgba(0,0,0,.5),rgba(0,0,0,.5)),url(' . e($img) . ')' : '' ?>">
        <div class="pb-hero__inner">
            <?php if (!empty($b['eyebrow'])): ?><span class="pb-hero__eyebrow"><?= e($b['eyebrow']) ?></span><?php endif; ?>
            <h1 class="pb-hero__title"><?= e($b['title'] ?? '') ?></h1>
            <?php if (!empty($b['subtitle'])): ?><p class="pb-hero__subtitle"><?= e($b['subtitle']) ?></p><?php endif; ?>
            <?php if (!empty($b['cta_label'])): ?>
                <a href="<?= e($b['cta_url'] ?? '#') ?>" class="pb-hero__cta"><?= e($b['cta_label']) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function pb_render_features($b) {
    $items = [];
    foreach (explode("\n", $b['items'] ?? '') as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) >= 2) $items[] = ['icon' => $parts[2] ?? '✓', 'title' => $parts[0], 'desc' => $parts[1]];
    }
    ?>
    <div class="pb-features">
        <?php if (!empty($b['title'])): ?><h2 class="pb-section__title"><?= e($b['title']) ?></h2><?php endif; ?>
        <div class="pb-features__grid">
            <?php foreach ($items as $it): ?>
                <div class="pb-feature">
                    <div class="pb-feature__icon"><?= e($it['icon']) ?></div>
                    <h3 class="pb-feature__title"><?= e($it['title']) ?></h3>
                    <p class="pb-feature__desc"><?= e($it['desc']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function pb_render_products($b) {
    $cid = (int)($b['category_id'] ?? 0);
    $limit = max(1, min(20, (int)($b['limit'] ?? 8)));
    $sql = "SELECT * FROM products WHERE status = 'active'";
    $params = [];
    if ($cid) { $sql .= " AND category_id = ?"; $params[] = $cid; }
    $sql .= " ORDER BY id DESC LIMIT $limit";
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    if (!$rows) return;
    ?>
    <div class="pb-products">
        <?php if (!empty($b['title'])): ?><h2 class="pb-section__title"><?= e($b['title']) ?></h2><?php endif; ?>
        <div class="pb-products__grid">
            <?php foreach ($rows as $p): ?>
                <a href="<?= e(BASE_URL) ?>/product.php?id=<?= (int)$p['id'] ?>" class="pb-product">
                    <img src="<?= e(product_image_url($p['image'] ?? '')) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                    <h3><?= e($p['name']) ?></h3>
                    <div class="pb-product__price"><?= fa_digits(number_format((float)$p['price'])) ?> تومان</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function pb_render_text($b) {
    echo '<div class="pb-text">' . ($b['content'] ?? '') . '</div>';
}

function pb_render_image($b) {
    if (empty($b['src'])) return;
    ?>
    <figure class="pb-image">
        <img src="<?= e(product_image_url($b['src'])) ?>" alt="<?= e($b['alt'] ?? '') ?>" loading="lazy">
        <?php if (!empty($b['caption'])): ?><figcaption><?= e($b['caption']) ?></figcaption><?php endif; ?>
    </figure>
    <?php
}

function pb_render_cta($b) {
    $color = $b['color'] ?? 'var(--accent, #0d9488)';
    ?>
    <div class="pb-cta" style="background:<?= e($color) ?>">
        <h2 class="pb-cta__title"><?= e($b['title'] ?? '') ?></h2>
        <?php if (!empty($b['subtitle'])): ?><p class="pb-cta__subtitle"><?= e($b['subtitle']) ?></p><?php endif; ?>
        <?php if (!empty($b['button_label'])): ?>
            <a href="<?= e($b['button_url'] ?? '#') ?>" class="pb-cta__btn"><?= e($b['button_label']) ?></a>
        <?php endif; ?>
    </div>
    <?php
}

function pb_render_form($b) {
    $fields = [];
    foreach (explode("\n", $b['fields'] ?? '') as $line) {
        $parts = array_map('trim', explode('|', $line));
        if ($parts[0]) $fields[] = ['name' => $parts[0], 'placeholder' => $parts[1] ?? '', 'required' => !empty($parts[2])];
    }
    ?>
    <form class="pb-form-block" method="post" action="<?= e(BASE_URL) ?>/custom_request.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="source" value="page-builder">
        <?php if (!empty($b['title'])): ?><h2 class="pb-section__title"><?= e($b['title']) ?></h2><?php endif; ?>
        <?php foreach ($fields as $f): ?>
            <label><?= e($f['placeholder']) ?>
                <input type="text" name="<?= e($f['name']) ?>" placeholder="<?= e($f['placeholder']) ?>" <?= $f['required'] ? 'required' : '' ?>>
            </label>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-accent"><?= e($b['submit_label'] ?? 'ارسال') ?></button>
    </form>
    <?php
}

function pb_render_testimonials($b) {
    $items = [];
    foreach (explode("\n", $b['items'] ?? '') as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) >= 2) $items[] = ['name' => $parts[0], 'text' => $parts[1], 'stars' => max(0, min(5, (int)($parts[2] ?? 5)))];
    }
    ?>
    <div class="pb-testimonials">
        <?php if (!empty($b['title'])): ?><h2 class="pb-section__title"><?= e($b['title']) ?></h2><?php endif; ?>
        <div class="pb-testimonials__grid">
            <?php foreach ($items as $it): ?>
                <div class="pb-testimonial">
                    <div class="pb-testimonial__stars"><?= str_repeat('★', $it['stars']) . str_repeat('☆', 5 - $it['stars']) ?></div>
                    <p class="pb-testimonial__text"><?= e($it['text']) ?></p>
                    <div class="pb-testimonial__name">— <?= e($it['name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function pb_render_faq($b) {
    $items = [];
    $lines = explode("\n", $b['items'] ?? '');
    $cur = null;
    foreach ($lines as $l) {
        if (trim($l) === '') { if ($cur) $items[] = $cur; $cur = null; }
        elseif ($cur === null) $cur = ['q' => trim($l), 'a' => ''];
        else $cur['a'] .= ($cur['a'] ? "\n" : '') . $l;
    }
    if ($cur) $items[] = $cur;
    ?>
    <div class="pb-faq">
        <?php if (!empty($b['title'])): ?><h2 class="pb-section__title"><?= e($b['title']) ?></h2><?php endif; ?>
        <div class="pb-faq__list">
            <?php foreach ($items as $i => $it): ?>
                <details class="pb-faq__item" <?= $i === 0 ? 'open' : '' ?>>
                    <summary><?= e($it['q']) ?></summary>
                    <div class="pb-faq__answer"><?= nl2br(e($it['a'])) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function pb_render_video($b) {
    if (empty($b['url'])) return;
    $url = $b['url'];
    // YouTube embed
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)~', $url, $m)) {
        $url = 'https://www.youtube.com/embed/' . $m[1];
    } elseif (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
        $url = 'https://player.vimeo.com/video/' . $m[1];
    }
    ?>
    <div class="pb-video">
        <iframe src="<?= e($url) ?>" frameborder="0" allowfullscreen loading="lazy"></iframe>
        <?php if (!empty($b['caption'])): ?><p class="pb-video__caption"><?= e($b['caption']) ?></p><?php endif; ?>
    </div>
    <?php
}

function pb_render_pricing($b) {
    $plans = [];
    foreach (explode("\n", $b['plans'] ?? '') as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) >= 2) $plans[] = ['name' => $parts[0], 'price' => $parts[1], 'features' => explode(',', $parts[2] ?? ''), 'featured' => !empty($parts[3]) && $parts[3] === 'بله'];
    }
    ?>
    <div class="pb-pricing">
        <?php if (!empty($b['title'])): ?><h2 class="pb-section__title"><?= e($b['title']) ?></h2><?php endif; ?>
        <div class="pb-pricing__grid">
            <?php foreach ($plans as $p): ?>
                <div class="pb-plan <?= $p['featured'] ? 'is-featured' : '' ?>">
                    <?php if ($p['featured']): ?><span class="pb-plan__badge">پیشنهادی</span><?php endif; ?>
                    <h3 class="pb-plan__name"><?= e($p['name']) ?></h3>
                    <div class="pb-plan__price"><?= e($p['price']) ?></div>
                    <ul class="pb-plan__features">
                        <?php foreach ($p['features'] as $f): if (trim($f)): ?><li><?= e(trim($f)) ?></li><?php endif; endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
