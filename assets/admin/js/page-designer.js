/**
 * assets/admin/js/page-designer.js
 * ویرایشگر بصری صفحه (GrapesJS) برای پنل مدیریت ابزارسازی شرق
 * ------------------------------------------------------------------
 * - بلوک‌های آمادهٔ فارسی (راست‌به‌چپ)
 * - ذخیرهٔ طرح در page_designs از طریق page_design_api.php
 * - پیش‌نمایش با CSS پایه که همراه طرح ذخیره می‌شود
 */
(function () {
    'use strict';

    var cfg = window.PD_CONFIG || {};
    var container = document.getElementById('gjs');
    if (!container) return;

    if (typeof window.grapesjs === 'undefined') {
        setMsg('فایل GrapesJS بارگذاری نشد (assets/vendor/grapesjs/grapes.min.js).', 'error');
        return;
    }

    /* ------------------------------------------------------------------
     * CSS پایه: هم داخل بوم (frameStyle) و هم همراه طرح ذخیره می‌شود تا
     * صفحهٔ نهایی دقیقاً مثل بوم دیده شود.
     * ------------------------------------------------------------------ */
    var PD_BASE_CSS_LIST = [
        'html{direction:rtl}',
        'body{direction:rtl;text-align:right;font-family:var(--font,"Vazirmatn","Segoe UI",Tahoma,sans-serif);color:var(--ink,#0e1729);background:#fff;margin:0;line-height:1.8}',
        '.pd-sec{box-sizing:border-box;padding:56px 0}',
        '.pd-sec *{box-sizing:border-box}',
        '.pd-sec--tint{background:var(--bg,#f4f6fa)}',
        '.pd-sec--navy{background:var(--navy,#06163a);color:#fff}',
        '.pd-sec--navy .pd-h2,.pd-sec--navy .pd-lead,.pd-sec--navy .pd-text{color:#fff}',
        '.pd-container{max-width:1140px;margin:0 auto;padding:0 20px}',
        '.pd-h1{font-size:2.6rem;line-height:1.35;font-weight:800;margin:0 0 .6rem}',
        '.pd-h2{font-size:1.9rem;line-height:1.4;font-weight:700;margin:0 0 1.4rem;color:var(--ink,#0e1729)}',
        '.pd-lead{font-size:1.15rem;color:var(--ink-soft,#5a6472);margin:0 0 1.6rem}',
        '.pd-text{color:var(--ink-soft,#5a6472);margin:0 0 1rem}',
        '.pd-center{text-align:center}',
        '.pd-btn{display:inline-block;padding:.8rem 1.6rem;border-radius:var(--radius,12px);font-weight:700;text-decoration:none;border:2px solid transparent;cursor:pointer;font-family:inherit;font-size:1rem}',
        '.pd-btn--primary{background:var(--accent,#0f228c);color:#fff}',
        '.pd-btn--ghost{background:transparent;color:#fff;border-color:rgba(255,255,255,.6)}',
        '.pd-btn--light{background:#fff;color:var(--accent,#0f228c)}',
        '.pd-hero{background:linear-gradient(135deg,var(--navy,#06163a) 0%,var(--accent,#0f228c) 100%);color:#fff;padding:96px 0}',
        '.pd-hero .pd-h1{color:#fff}',
        '.pd-hero .pd-lead{color:rgba(255,255,255,.85);max-width:640px}',
        '.pd-hero__actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}',
        '.pd-eyebrow{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.35);color:#fff;padding:4px 14px;border-radius:999px;font-size:.85rem;margin-bottom:14px}',
        '.pd-grid{display:grid;gap:22px}',
        '.pd-grid--2{grid-template-columns:repeat(2,1fr)}',
        '.pd-grid--3{grid-template-columns:repeat(3,1fr)}',
        '.pd-grid--4{grid-template-columns:repeat(4,1fr)}',
        '.pd-card{background:var(--surface,#fff);border:1px solid var(--line,#e6eaf1);border-radius:var(--radius-lg,16px);padding:26px;box-shadow:var(--shadow-soft,0 2px 10px -4px rgba(6,22,58,.08))}',
        '.pd-card__ico{font-size:2rem;line-height:1;margin-bottom:12px}',
        '.pd-card h3{margin:0 0 .5rem;font-size:1.15rem;color:var(--ink,#0e1729)}',
        '.pd-card p{margin:0;color:var(--ink-soft,#5a6472);font-size:.98rem}',
        '.pd-band{background:var(--accent,#0f228c);color:#fff;padding:56px 0;text-align:center}',
        '.pd-band .pd-h2{color:#fff}',
        '.pd-band .pd-text{color:rgba(255,255,255,.9)}',
        '.pd-img{max-width:100%;height:auto;border-radius:var(--radius,12px);display:block}',
        '.pd-fig{margin:0}',
        '.pd-fig figcaption{font-size:.88rem;color:var(--ink-soft,#5a6472);margin-top:8px;text-align:center}',
        '.pd-quote{background:var(--surface,#fff);border-right:4px solid var(--accent,#0f228c);border-radius:var(--radius,12px);padding:24px;box-shadow:var(--shadow-soft,0 2px 10px -4px rgba(6,22,58,.08))}',
        '.pd-quote p{margin:0 0 10px;font-size:1.05rem;color:var(--ink,#0e1729)}',
        '.pd-quote footer{color:var(--ink-soft,#5a6472);font-size:.9rem}',
        '.pd-faq details{background:#fff;border:1px solid var(--line,#e6eaf1);border-radius:var(--radius,12px);padding:14px 18px;margin-bottom:12px}',
        '.pd-faq summary{cursor:pointer;font-weight:700;color:var(--ink,#0e1729)}',
        '.pd-faq p{margin:10px 0 0;color:var(--ink-soft,#5a6472)}',
        '.pd-form{display:grid;gap:14px;max-width:560px}',
        '.pd-form input,.pd-form textarea{width:100%;padding:.85rem 1rem;border:1px solid var(--line,#e6eaf1);border-radius:var(--radius-sm,8px);font-family:inherit;font-size:1rem;background:#fff}',
        '.pd-form textarea{min-height:120px;resize:vertical}',
        '.pd-products{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}',
        '.pd-product{background:#fff;border:1px solid var(--line,#e6eaf1);border-radius:var(--radius-lg,16px);overflow:hidden;text-decoration:none;box-shadow:var(--shadow-soft,0 2px 10px -4px rgba(6,22,58,.08))}',
        '.pd-product img{width:100%;height:180px;object-fit:cover;display:block}',
        '.pd-product__body{padding:16px}',
        '.pd-product__name{font-weight:700;color:var(--ink,#0e1729);margin:0 0 6px;font-size:1rem}',
        '.pd-product__price{color:var(--accent,#0f228c);font-weight:800}',
        '.pd-contact{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;text-align:center}',
        '.pd-contact .pd-card__ico{margin-inline:auto}',
        '.pd-video{position:relative;padding-top:56.25%;background:#06163a;border-radius:var(--radius-lg,16px);overflow:hidden}',
        '.pd-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0}',
        '.pd-video__ph{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.85);font-size:1rem}',
        '.pd-divider{border:0;border-top:1px solid var(--line,#e6eaf1);margin:0}',
        '.pd-spacer{height:48px}',
        '@media(max-width:900px){.pd-grid--3,.pd-grid--4,.pd-products,.pd-contact{grid-template-columns:1fr 1fr}}',
        '@media(max-width:640px){.pd-h1{font-size:1.9rem}.pd-h2{font-size:1.45rem}.pd-grid--2,.pd-grid--3,.pd-grid--4,.pd-products,.pd-contact{grid-template-columns:1fr}.pd-hero{padding:64px 0}.pd-sec{padding:40px 0}}'
    ];

    // CSS بوم (iframe ویرایشگر): شامل قواعد html/body — فقط داخل بوم استفاده می‌شود.
    var PD_BASE_CSS = PD_BASE_CSS_LIST.join('\n');

    // CSS خروجی که همراه طرح ذخیره و روی صفحهٔ عمومی /p/ چاپ می‌شود.
    // دو قاعدهٔ سراسری html/body به .pd-render محدود شده‌اند تا استایل طرح به
    // هدر/فوتر/بدنهٔ کل سایت نشت نکند.
    var PD_EXPORT_CSS = ([
        '.pd-render{direction:rtl;text-align:right;font-family:var(--font,"Vazirmatn","Segoe UI",Tahoma,sans-serif);color:var(--ink,#0e1729);background:#fff;line-height:1.8}',
        '.pd-render *{box-sizing:border-box}'
    ].concat(PD_BASE_CSS_LIST.slice(2))).join('\n');

    var STARTER_HTML = '' +
        '<section class="pd-sec pd-hero" dir="rtl"><div class="pd-container">' +
        '<span class="pd-eyebrow">ابزارسازی شرق</span>' +
        '<h1 class="pd-h1">عنوان اصلی صفحهٔ شما</h1>' +
        '<p class="pd-lead">اینجا توضیح کوتاهی بنویسید که ارزش پیشنهاد شما را برای مشتری روشن می‌کند.</p>' +
        '<div class="pd-hero__actions">' +
        '<a class="pd-btn pd-btn--light" href="/shop.php">همین حالا خرید کنید</a>' +
        '<a class="pd-btn pd-btn--ghost" href="/about.php">دربارهٔ ما</a>' +
        '</div></div></section>';

    /* ------------------------------------------------------------------
     * بلوک‌های آماده (فارسی)
     * ------------------------------------------------------------------ */
    function block(id, label, category, content) {
        return { id: id, label: label, category: category, content: content, attributes: { title: label, dir: 'rtl' } };
    }

    function buildBlocks() {
        var B = [];
        var catBase = 'پایه', catSec = 'بخش‌ها', catLay = 'چیدمان';

        B.push(block('pd-h1', '🎯 عنوان اصلی', catBase,
            '<h1 class="pd-h1" dir="rtl">عنوان اصلی صفحه</h1>'));
        B.push(block('pd-h2', '🏷 عنوان بخش', catBase,
            '<h2 class="pd-h2" dir="rtl">عنوان بخش</h2>'));
        B.push(block('pd-text', '📝 متن', catBase,
            '<p class="pd-text" dir="rtl">متن توضیحی خود را اینجا بنویسید. با دوبار کلیک روی متن، آن را ویرایش کنید.</p>'));
        B.push(block('pd-btn', '🔘 دکمه', catBase,
            '<p dir="rtl" style="margin:0"><a class="pd-btn pd-btn--primary" href="#">متن دکمه</a></p>'));
        B.push(block('pd-img', '🖼 تصویر', catBase,
            '<figure class="pd-fig" dir="rtl"><img class="pd-img" src="' + (cfg.assets && cfg.assets[0] ? cfg.assets[0] : 'https://via.placeholder.com/960x420?text=image') + '" alt=""><figcaption>توضیح تصویر</figcaption></figure>'));
        B.push(block('pd-divider', '➖ جداکننده', catBase, '<hr class="pd-divider">'));
        B.push(block('pd-spacer', '⬜ فاصله', catBase, '<div class="pd-spacer"></div>'));

        B.push(block('pd-hero', '🌟 سربرگ (Hero)', catSec, STARTER_HTML));
        B.push(block('pd-hero-img', '🖼 سربرگ تصویری', catSec,
            '<section class="pd-sec" dir="rtl" style="background:var(--navy,#06163a);color:#fff"><div class="pd-container">' +
            '<div class="pd-grid pd-grid--2" style="align-items:center">' +
            '<div><h1 class="pd-h1" style="color:#fff">ابزار حرفه‌ای، انتخاب حرفه‌ای‌ها</h1>' +
            '<p class="pd-lead" style="color:rgba(255,255,255,.85)">توضیح کوتاه دربارهٔ محصول یا خدمت شما.</p>' +
            '<a class="pd-btn pd-btn--light" href="/shop.php">مشاهده محصولات</a></div>' +
            '<img class="pd-img" src="' + (cfg.assets && cfg.assets[0] ? cfg.assets[0] : 'https://via.placeholder.com/640x420?text=image') + '" alt=""></div>' +
            '</div></section>'));
        B.push(block('pd-features3', '✨ سه ویژگی', catSec,
            '<section class="pd-sec pd-sec--tint" dir="rtl"><div class="pd-container">' +
            '<h2 class="pd-h2 pd-center">چرا ما؟</h2><div class="pd-grid pd-grid--3">' +
            '<div class="pd-card"><div class="pd-card__ico">🚚</div><h3>ارسال سریع</h3><p>تحویل سریع در سراسر کشور.</p></div>' +
            '<div class="pd-card"><div class="pd-card__ico">🛡</div><h3>ضمانت اصالت</h3><p>تضمین اصل بودن همهٔ کالاها.</p></div>' +
            '<div class="pd-card"><div class="pd-card__ico">📞</div><h3>پشتیبانی</h3><p>پاسخگویی در روزهای کاری.</p></div>' +
            '</div></div></section>'));
        B.push(block('pd-features4', '✨ چهار ویژگی', catSec,
            '<section class="pd-sec" dir="rtl"><div class="pd-container"><div class="pd-grid pd-grid--4">' +
            '<div class="pd-card"><div class="pd-card__ico">✅</div><h3>ویژگی یک</h3><p>توضیح کوتاه.</p></div>' +
            '<div class="pd-card"><div class="pd-card__ico">⚡</div><h3>ویژگی دو</h3><p>توضیح کوتاه.</p></div>' +
            '<div class="pd-card"><div class="pd-card__ico">💎</div><h3>ویژگی سه</h3><p>توضیح کوتاه.</p></div>' +
            '<div class="pd-card"><div class="pd-card__ico">🔧</div><h3>ویژگی چهار</h3><p>توضیح کوتاه.</p></div>' +
            '</div></div></section>'));
        B.push(block('pd-cta', '📣 دعوت به اقدام', catSec,
            '<section class="pd-sec pd-band" dir="rtl"><div class="pd-container">' +
            '<h2 class="pd-h2">همین امروز سفارش دهید</h2>' +
            '<p class="pd-text">متن کوتاه تشویقی برای اقدام مشتری.</p>' +
            '<a class="pd-btn pd-btn--light" href="/shop.php">شروع خرید</a></div></section>'));
        B.push(block('pd-testimonial', '💬 نظر مشتری', catSec,
            '<section class="pd-sec" dir="rtl"><div class="pd-container"><div class="pd-grid pd-grid--3">' +
            '<blockquote class="pd-quote"><p>کیفیت محصولات عالی بود.</p><footer>— علی محمدی</footer></blockquote>' +
            '<blockquote class="pd-quote"><p>ارسال سریع و بسته‌بندی مناسب.</p><footer>— مریم احمدی</footer></blockquote>' +
            '<blockquote class="pd-quote"><p>قیمت منصفانه و پشتیبانی خوب.</p><footer>— حسین رضایی</footer></blockquote>' +
            '</div></div></section>'));
        B.push(block('pd-faq', '❓ پرسش‌های متداول', catSec,
            '<section class="pd-sec pd-sec--tint" dir="rtl"><div class="pd-container pd-faq">' +
            '<h2 class="pd-h2">پرسش‌های متداول</h2>' +
            '<details open><summary>هزینهٔ ارسال چقدر است؟</summary><p>هزینهٔ ارسال پس از ثبت سفارش محاسبه و اعلام می‌شود.</p></details>' +
            '<details><summary>گارانتی محصولات چگونه است؟</summary><p>همهٔ کالاها با ضمانت اصالت عرضه می‌شوند.</p></details>' +
            '</div></section>'));
        B.push(block('pd-products', '🛍 محصولات', catSec, buildProductsBlock()));
        B.push(block('pd-contact', '📬 اطلاعات تماس', catSec,
            '<section class="pd-sec" dir="rtl"><div class="pd-container"><div class="pd-contact">' +
            '<div class="pd-card"><div class="pd-card__ico">📞</div><h3>تلفن</h3><p dir="ltr">021-00000000</p></div>' +
            '<div class="pd-card"><div class="pd-card__ico">📍</div><h3>نشانی</h3><p>تهران، ...</p></div>' +
            '<div class="pd-card"><div class="pd-card__ico">🕘</div><h3>ساعات کاری</h3><p>شنبه تا چهارشنبه ۹ تا ۱۷</p></div>' +
            '</div></div></section>'));
        B.push(block('pd-form', '📮 فرم تماس', catSec,
            '<section class="pd-sec pd-sec--tint" dir="rtl"><div class="pd-container">' +
            '<h2 class="pd-h2">فرم تماس</h2>' +
            '<form class="pd-form" method="post" action="/custom_request.php">' +
            '<div><label>نام و نام خانوادگی</label><input type="text" name="name" placeholder="نام شما"></div>' +
            '<div><label>شمارهٔ تماس</label><input type="text" name="phone" placeholder="۰۹۱۲..."></div>' +
            '<div><label>پیام</label><textarea name="description" placeholder="متن پیام"></textarea></div>' +
            '<div><button type="submit" class="pd-btn pd-btn--primary">ارسال</button></div>' +
            '</form></div></section>'));
        B.push(block('pd-video', '🎬 ویدیو', catSec,
            '<section class="pd-sec" dir="rtl"><div class="pd-container">' +
            '<div class="pd-video"><div class="pd-video__ph">🎬 جای ویدیو — کد embed را اینجا بگذارید</div></div>' +
            '</div></section>'));

        B.push(block('pd-2col', '▥ دو ستون', catLay,
            '<section class="pd-sec" dir="rtl"><div class="pd-container"><div class="pd-grid pd-grid--2">' +
            '<div><h3 class="pd-h2">ستون اول</h3><p class="pd-text">متن ستون اول.</p></div>' +
            '<div><h3 class="pd-h2">ستون دوم</h3><p class="pd-text">متن ستون دوم.</p></div>' +
            '</div></div></section>'));
        B.push(block('pd-3col', '▤ سه ستون', catLay,
            '<section class="pd-sec" dir="rtl"><div class="pd-container"><div class="pd-grid pd-grid--3">' +
            '<div class="pd-card"><h3>ستون یک</h3><p>متن.</p></div>' +
            '<div class="pd-card"><h3>ستون دو</h3><p>متن.</p></div>' +
            '<div class="pd-card"><h3>ستون سه</h3><p>متن.</p></div>' +
            '</div></div></section>'));

        return B;
    }

    function buildProductsBlock() {
        var items = (cfg.products && cfg.products.length) ? cfg.products.slice(0, 3) : [
            { name: 'محصول نمونه', price: '۱٬۰۰۰٬۰۰۰', image: '' },
            { name: 'محصول نمونه', price: '۲٬۰۰۰٬۰۰۰', image: '' },
            { name: 'محصول نمونه', price: '۳٬۰۰۰٬۰۰۰', image: '' }
        ];
        var cards = '';
        for (var i = 0; i < items.length; i++) {
            var p = items[i];
            var img = p.image || 'https://via.placeholder.com/480x360?text=product';
            cards += '<a class="pd-product" href="/shop.php"><img src="' + img + '" alt="">' +
                '<div class="pd-product__body"><p class="pd-product__name">' + escapeHtml(p.name) + '</p>' +
                '<div class="pd-product__price">' + escapeHtml(String(p.price)) + ' تومان</div></div></a>';
        }
        return '<section class="pd-sec" dir="rtl"><div class="pd-container">' +
            '<h2 class="pd-h2 pd-center">محصولات ویژه</h2>' +
            '<div class="pd-products">' + cards + '</div></div></section>';
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ------------------------------------------------------------------
     * راه‌اندازی GrapesJS
     * ------------------------------------------------------------------ */
    var editor = window.grapesjs.init({
        container: '#gjs',
        height: '100%',
        width: 'auto',
        fromElement: false,
        storageManager: false,
        noticeOnUnload: false,
        showOffsets: true,
        avoidInlineStyle: false,
        assetManager: {
            assets: cfg.assets || [],
            upload: false,
            embedAsBase64: false,
            autoAdd: true
        },
        canvas: {
            styles: cfg.canvasStyles || [],
            frameStyle: PD_BASE_CSS
        },
        deviceManager: {
            devices: [
                { id: 'desktop', name: 'دسکتاپ', width: '' },
                { id: 'tablet', name: 'تبلت', width: '820px', widthMedia: '992px' },
                { id: 'mobile', name: 'موبایل', width: '390px', widthMedia: '640px' }
            ]
        },
        blockManager: { appendTo: '#pd-blocks', blocks: buildBlocks() },
        styleManager: { appendTo: '#pd-styles' },
        layerManager: { appendTo: '#pd-layers' },
        selectorManager: { appendTo: '#pd-styles' },
        panels: { defaults: [] }
    });

    window.PD_EDITOR = editor;

    function setRTL() {
        try {
            var doc = editor.Canvas.getDocument();
            if (doc) {
                doc.documentElement.setAttribute('dir', 'rtl');
                doc.documentElement.setAttribute('lang', 'fa');
                if (doc.body) { doc.body.setAttribute('dir', 'rtl'); doc.body.dir = 'rtl'; }
            }
        } catch (e) { /* بی‌اهمیت */ }
    }

    var dirty = false;
    editor.on('update', function () { dirty = true; });

    editor.on('load', function () {
        setRTL();
        var d = cfg.design;
        var loaded = false;
        if (d && d.project && d.project.length > 2) {
            try { editor.loadProjectData(JSON.parse(d.project)); loaded = true; }
            catch (e) { loaded = false; }
        }
        if (!loaded && d && d.html && d.html.trim() !== '') {
            try {
                editor.setComponents(d.html);
                // CSS ذخیره‌شده با پیشوند CSS پایه شروع می‌شود؛ برای جلوگیری از تکرار
                // تدریجی آن روی هر بار ذخیره، پیشوند را حذف و فقط استایل کاربر را بارگذاری می‌کنیم.
                var userCss = String(d.css || '');
                if (userCss.indexOf(PD_EXPORT_CSS) === 0) userCss = userCss.slice(PD_EXPORT_CSS.length);
                editor.setStyle(userCss);
                loaded = true;
            } catch (e2) { loaded = false; }
        }
        if (!loaded) {
            editor.setComponents(STARTER_HTML);
        }
        dirty = false;
        setMsg('آماده — بلوک‌ها را از ستون کنار بکشید و روی بوم رها کنید.', 'info');
    });

    editor.on('canvas:frame:load', setRTL);

    /* ------------------------------------------------------------------
     * ارتباط با سرور
     * ------------------------------------------------------------------ */
    function api(action, fields) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf', cfg.csrf);
        if (fields) {
            for (var k in fields) {
                if (Object.prototype.hasOwnProperty.call(fields, k)) fd.append(k, fields[k]);
            }
        }
        return fetch(cfg.api, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().catch(function () { return { error: 'پاسخ نامعتبر از سرور (HTTP ' + r.status + ')' }; });
        });
    }

    function setMsg(text, kind) {
        var el = document.getElementById('pdMsg');
        if (!el) return;
        el.textContent = text || '';
        el.className = 'pd-msg' + (kind ? ' pd-msg--' + kind : '');
    }

    function busy(btn, on, label) {
        if (!btn) return;
        if (on) {
            btn.dataset.label = btn.textContent;
            btn.disabled = true;
            btn.textContent = label || '… در حال ذخیره';
        } else {
            btn.disabled = false;
            if (btn.dataset.label) btn.textContent = btn.dataset.label;
        }
    }

    /* ---------- ذخیرهٔ طرح ---------- */
    var saveBtn = document.getElementById('pdSave');
    function saveDesign() {
        busy(saveBtn, true);
        setMsg('در حال ذخیره…', 'info');
        var payload = {
            page_id: String(cfg.pageId),
            project: JSON.stringify(editor.getProjectData()),
            html: editor.getHtml(),
            css: PD_EXPORT_CSS + '\n' + editor.getCss()
        };
        return api('save', payload).then(function (res) {
            busy(saveBtn, false);
            if (res && res.ok) {
                dirty = false;
                var t = new Date();
                setMsg('طرح ذخیره شد — ' + pad(t.getHours()) + ':' + pad(t.getMinutes()) + ':' + pad(t.getSeconds()), 'ok');
            } else {
                setMsg('خطا در ذخیره: ' + ((res && res.error) || 'نامشخص'), 'error');
            }
        }).catch(function (e) {
            busy(saveBtn, false);
            setMsg('خطای شبکه: ' + e.message, 'error');
        });
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    if (saveBtn) saveBtn.addEventListener('click', saveDesign);

    /* ---------- مشخصات صفحه ---------- */
    var metaForm = document.getElementById('pdMetaForm');
    if (metaForm) metaForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var title = document.getElementById('pdTitle').value.trim();
        var slug = document.getElementById('pdSlug').value.trim();
        var status = document.getElementById('pdStatus').value;
        if (title === '') { setMsg('عنوان صفحه نمی‌تواند خالی باشد.', 'error'); return; }
        setMsg('در حال ثبت مشخصات…', 'info');
        api('meta', { page_id: String(cfg.pageId), title: title, slug: slug, status: status }).then(function (res) {
            if (res && res.ok) {
                document.getElementById('pdSlug').value = res.slug;
                var prev = document.getElementById('pdPreview');
                if (prev) prev.setAttribute('href', cfg.base + '/p/' + res.slug + '?preview=1');
                setMsg('مشخصات ثبت شد. آدرس صفحه: /p/' + res.slug, 'ok');
            } else {
                setMsg('خطا: ' + ((res && res.error) || 'نامشخص'), 'error');
            }
        }).catch(function (e) { setMsg('خطای شبکه: ' + e.message, 'error'); });
    });

    /* ---------- Undo / Redo / Devices / Clear ---------- */
    var undo = document.getElementById('pdUndo');
    var redo = document.getElementById('pdRedo');
    if (undo) undo.addEventListener('click', function () { editor.UndoManager.undo(); });
    if (redo) redo.addEventListener('click', function () { editor.UndoManager.redo(); });

    var devBtns = document.querySelectorAll('[data-device]');
    Array.prototype.forEach.call(devBtns, function (b) {
        b.addEventListener('click', function () {
            var dev = b.getAttribute('data-device');
            editor.setDevice(dev);
            Array.prototype.forEach.call(devBtns, function (x) { x.classList.remove('is-active'); });
            b.classList.add('is-active');
        });
    });

    var clearBtn = document.getElementById('pdClear');
    if (clearBtn) clearBtn.addEventListener('click', function () {
        if (!window.confirm('کل بوم پاک شود؟ (تا زمان ذخیره، قابل بازگشت با واگرد است)')) return;
        try { editor.DomComponents.clear(); } catch (e) { editor.setComponents(''); }
        try { editor.Css.clear(); } catch (e2) { }
        dirty = true;
        setMsg('بوم پاک شد. با «واگرد» قابل بازگردانی است.', 'info');
    });

    /* ---------- تب‌های پنل ---------- */
    var tabs = document.querySelectorAll('.pd-tab');
    Array.prototype.forEach.call(tabs, function (t) {
        t.addEventListener('click', function () {
            var pane = t.getAttribute('data-pane');
            Array.prototype.forEach.call(tabs, function (x) { x.classList.remove('is-active'); });
            t.classList.add('is-active');
            Array.prototype.forEach.call(document.querySelectorAll('.pd-pane'), function (p) {
                var on = p.getAttribute('data-pane') === pane;
                p.hidden = !on;
                p.classList.toggle('is-active', on);
            });
            if (pane === 'styles') { try { editor.StyleManager.select(editor.getSelected()); } catch (e) {} }
        });
    });

    /* ---------- تنظیم ارتفاع دقیق بوم ---------- */
    function sizeApp() {
        var app = document.querySelector('.pd-app');
        if (!app) return;
        var top = app.getBoundingClientRect().top;
        if (window.innerWidth > 900) {
            app.style.height = Math.max(420, window.innerHeight - top) + 'px';
        } else {
            app.style.height = '';
        }
    }
    window.addEventListener('resize', sizeApp);
    sizeApp();
    editor.on('load', sizeApp);

    /* ---------- میان‌بر Ctrl+S ---------- */
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            saveDesign();
        }
    });

    /* ---------- هشدار خروج با تغییرات ذخیره‌نشده ---------- */
    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    /* ------------------------------------------------------------------
     * ساخت صفحهٔ جدید (از نوار کناری)
     * ------------------------------------------------------------------ */
    var newToggle = document.getElementById('pdNewToggle');
    var newForm = document.getElementById('pdNewForm');
    var newCancel = document.getElementById('pdNewCancel');
    if (newToggle && newForm) {
        newToggle.addEventListener('click', function () {
            newForm.hidden = !newForm.hidden;
            if (!newForm.hidden) { var t = document.getElementById('pdNewTitle'); if (t) t.focus(); }
        });
    }
    if (newCancel && newForm) {
        newCancel.addEventListener('click', function () { newForm.hidden = true; });
    }
    if (newForm) {
        newForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var msg = document.getElementById('pdNewMsg');
            var title = document.getElementById('pdNewTitle').value.trim();
            var slug = document.getElementById('pdNewSlug').value.trim();
            var status = document.getElementById('pdNewStatus').value;
            if (title === '') { if (msg) msg.textContent = 'عنوان را وارد کنید.'; return; }
            if (msg) msg.textContent = 'در حال ساخت…';
            api('create', { title: title, slug: slug, status: status }).then(function (res) {
                if (res && res.ok) {
                    dirty = false;
                    window.location.href = 'page_designer.php?page=' + res.id;
                } else if (msg) {
                    msg.textContent = 'خطا: ' + ((res && res.error) || 'نامشخص');
                }
            }).catch(function (e) { if (msg) msg.textContent = 'خطای شبکه: ' + e.message; });
        });
    }
})();
