<?php
/**
 * admin/ai_assistant.php — دستیار هوش مصنوعی حرفه‌ای
 * ۱) پریست‌های هوشمند: کلیک → فرم با نمونهٔ آماده پر می‌شود
 * ۲) توضیح سریع محصولات: انتخاب محصول واقعی → تولید توضیح بدون تایپ → ذخیرهٔ مستقیم
 * ۳) حالت آزاد: هر درخواستی با پریست‌های محتوایی
 */
$pageTitle = 'دستیار هوش مصنوعی';
require __DIR__ . '/_header.php';

$presets = ai_presets();
$result = '';
$error = '';
$mode = 'product';
$prompt = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    if (!csrf_verify()) {
        $error = 'نشست شما منقضی شده است؛ دوباره تلاش کنید.';
    } else {
        $mode = isset($presets[$_POST['mode'] ?? '']) ? $_POST['mode'] : 'product';
        $prompt = trim($_POST['prompt'] ?? '');
        if ($prompt === '') {
            $error = 'درخواست خود را بنویسید.';
        } else {
            $p = $presets[$mode];
            $systemPrompt = $p['system'];
            if (!empty($p['with_stats'])) {
                $systemPrompt .= "\n\nداده‌های واقعی فروشگاه:\n" . ai_db_stats();
            }
            $res = ai_complete($systemPrompt, $prompt);
            if ($res['ok']) {
                $result = $res['text'];
            } else {
                $error = $res['error'];
            }
        }
    }
}

// ---- توضیح سریع محصول (تک محصول یا دسته‌ای) ----
$quickDone = [];
$quickFail = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_desc') {
    if (!csrf_verify()) {
        $error = 'نشست شما منقضی شده است؛ دوباره تلاش کنید.';
    } else {
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        if (!$ids) {
            $error = 'حداقل یک محصول را انتخاب کنید.';
        } elseif (setting('ai_api_key', '') === '') {
            $error = 'کلید API هوش مصنوعی تنظیم نشده است (تنظیمات ← هوش مصنوعی).';
        } else {
            foreach ($ids as $pid) {
                $st = db()->prepare("SELECT p.id, p.name, p.price, p.stock, COALESCE(c.name,'عمومی') AS cat
                                     FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?");
                $st->execute([$pid]);
                $p = $st->fetch();
                if (!$p) { $quickFail[] = ['name' => '#' . $pid, 'why' => 'یافت نشد']; continue; }
                $r = ai_generate_product_description($p);
                if ($r['ok'] && mb_strlen($r['description']) > 20) {
                    $upd = db()->prepare("UPDATE products SET description=? WHERE id=?");
                    $upd->execute([$r['description'], $pid]);
                    $quickDone[] = $p['name'];
                } else {
                    $quickFail[] = ['name' => $p['name'], 'why' => $r['error'] ?: 'پاسخ کوتاه بود'];
                }
            }
            if ($quickDone) {
                flash('success', 'توضیح برای ' . fa_digits((string)count($quickDone)) . ' محصول ذخیره شد.');
            }
        }
    }
}

$needDesc = ai_products_needing_description();
$allProducts = db()->query("SELECT p.id, p.name, COALESCE(c.name,'عمومی') AS cat, LENGTH(p.description) AS dlen
                            FROM products p LEFT JOIN categories c ON c.id=p.category_id
                            WHERE p.active=1 ORDER BY p.name")->fetchAll();
$noApiKey = setting('ai_api_key', '') === '';
?>
<style>
.ai-wrap { max-width: 1000px; }
.ai-header { display: flex; align-items: center; gap: .7rem; margin-bottom: .3rem; }
.ai-header .ai-logo {
    width: 44px; height: 44px; border-radius: 12px; flex: 0 0 44px;
    display: grid; place-items: center;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark, #0a1a6b));
    color: #fff; box-shadow: 0 4px 14px -4px rgba(15,34,140,.4);
}
.ai-header .ai-logo svg { width: 24px; height: 24px; }
.ai-header h1 { margin: 0; }
.ai-header p { margin: .15rem 0 0; font-size: .85rem; color: var(--ink-soft); }
.ai-section { margin-top: 1.4rem; }
.ai-section > h2 { font-size: 1.02rem; margin: 0 0 .7rem; display:flex; align-items:center; gap:.4rem; }
.ai-modes { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .7rem; }
.ai-mode-btn {
    display: flex; flex-direction: column; align-items: flex-start; gap: .25rem;
    padding: .75rem 1rem; border-radius: var(--radius); border: 1px solid var(--line);
    background: var(--surface); cursor: pointer; font-size: .9rem; text-align: right;
    transition: all .15s;
}
.ai-mode-btn .ai-mode-name { font-weight: 700; color: var(--ink); }
.ai-mode-btn.active { border-color: var(--accent); background: var(--accent); }
.ai-mode-btn.active .ai-mode-name { color: #fff; }
.ai-mode-btn:hover:not(.active) { border-color: var(--accent); transform: translateY(-1px); box-shadow: var(--shadow-soft); }
.ai-card {
    background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius);
    padding: 1.3rem 1.4rem; box-shadow: var(--shadow-soft);
}
.ai-card textarea { margin-bottom: .8rem; width: 100%; }
.ai-submit-row { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; }
.ai-loading { display: none; align-items: center; gap: .5rem; color: var(--ink-soft); font-size: .88rem; }
.ai-loading.show { display: inline-flex; }
.ai-spinner {
    width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--line);
    border-top-color: var(--accent); animation: aiSpin .7s linear infinite;
}
@keyframes aiSpin { to { transform: rotate(360deg); } }
.ai-result {
    margin-top: 1.2rem; background: var(--surface); border: 1px solid var(--line);
    border-left: 4px solid var(--accent); border-radius: var(--radius); padding: 1.4rem;
    line-height: 2; max-height: 520px; overflow-y: auto; font-size: .93rem;
    box-shadow: var(--shadow-soft);
}
.ai-result-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; }
.ai-result-head .ai-result-label { font-weight: 700; color: var(--accent); font-size: .88rem; }
.ai-result-body h1, .ai-result-body h2, .ai-result-body h3 { margin: .8em 0 .4em; }
.ai-result-body ul, .ai-result-body ol { padding-right: 1.4rem; }
.ai-result-body p { margin: .5em 0; }
.ai-note { background: var(--accent-soft); border: 1px solid var(--line); border-radius: var(--radius-sm); padding: .8rem 1rem; font-size: .85rem; margin-bottom: 1rem; }
.ai-quick-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: .55rem; max-height: 330px; overflow-y: auto; padding: .2rem; }
.ai-quick-item {
    display: flex; align-items: center; gap: .6rem; padding: .6rem .8rem;
    border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--bg);
    cursor: pointer; transition: all .12s; font-size: .88rem;
}
.ai-quick-item:hover { border-color: var(--accent); }
.ai-quick-item input { accent-color: var(--accent); }
.ai-quick-item .qi-meta { color: var(--ink-soft); font-size: .78rem; }
.ai-quick-actions { display: flex; gap: .7rem; align-items: center; margin-top: .9rem; flex-wrap: wrap; }
.ai-quick-done { margin-top: .8rem; font-size: .86rem; }
.ai-quick-done .ok { color: #0a7d33; }
.ai-quick-done .fail { color: #b4232a; }
.ai-all-btn { font-size: .8rem; }
.ai-tabs { display:flex; gap:.4rem; margin-bottom: .9rem; flex-wrap: wrap; }
.ai-tab {
    padding: .45rem 1rem; border-radius: 999px; border: 1px solid var(--line);
    background: var(--bg); cursor: pointer; font-size: .86rem;
}
.ai-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.ai-panel { display: none; }
.ai-panel.active { display: block; }
.ai-md-table { border-collapse: collapse; width: 100%; margin: .6em 0; font-size: .88rem; }
.ai-md-table th, .ai-md-table td { border: 1px solid var(--line); padding: .4rem .7rem; }
.ai-md-table th { background: var(--bg); }
.ai-md-code { background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: .1rem .4rem; direction: ltr; display: inline-block; font-size: .85em; }
.ai-md-pre { background: var(--bg); border: 1px solid var(--line); border-radius: 8px; padding: .8rem 1rem; overflow-x: auto; direction: ltr; text-align: left; }
</style>

<div class="ai-wrap">
    <div class="ai-header">
        <span class="ai-logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z"/></svg></span>
        <div>
            <h1 class="page-title">دستیار هوش مصنوعی</h1>
            <p>تولید محتوا، توضیح محصول و گزارش فروش — با چند کلیک، بدون تایپ اضافه</p>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($noApiKey): ?>
    <div class="ai-note">ابتدا در <a href="settings.php?tab=ai">تنظیمات ← هوش مصنوعی</a> کلید API را وارد کنید (OpenAI یا سرویس‌های سازگار).</div>
    <?php endif; ?>

    <div class="ai-tabs">
        <button type="button" class="ai-tab active" data-tab="content">تولید محتوا</button>
        <button type="button" class="ai-tab" data-tab="quick">توضیح سریع محصولات</button>
    </div>

    <!-- ================= پنل تولید محتوا ================= -->
    <div class="ai-panel active" id="panel-content">
        <form method="post" action="ai_assistant.php" class="admin-form" id="aiForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate">
            <div class="ai-modes" id="aiModes">
                <?php foreach ($presets as $key => $p): ?>
                    <button type="button" class="ai-mode-btn<?= $mode === $key ? ' active' : '' ?>" data-mode="<?= e($key) ?>">
                        <span class="ai-mode-name"><?= e($p['name']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="mode" id="aiMode" value="<?= e($mode) ?>">

            <div class="ai-card" style="margin-top:1rem;">
                <label>درخواست شما
                    <textarea name="prompt" rows="5" required placeholder="<?= e($presets[$mode]['hint']) ?>" id="aiPrompt"><?= e($prompt) ?></textarea>
                </label>
                <div class="ai-submit-row">
                    <button type="submit" class="btn btn-accent" id="aiSubmit">تولید کن</button>
                    <button type="button" class="btn btn-ghost" id="aiStarter">پرکردن با نمونهٔ آماده</button>
                    <span class="ai-loading" id="aiLoading"><span class="ai-spinner"></span> در حال تولید…</span>
                </div>
            </div>
        </form>

        <?php if ($result !== ''): ?>
            <div class="ai-result" id="aiResult">
                <div class="ai-result-head">
                    <span class="ai-result-label">خروجی تولید شد</span>
                    <button type="button" class="btn btn-ghost btn-sm ai-copy" id="aiCopy">کپی متن</button>
                </div>
                <div class="ai-result-body" id="aiResultBody"><?= ai_render_markdown($result) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================= پنل توضیح سریع محصولات ================= -->
    <div class="ai-panel" id="panel-quick">
        <?php if ($quickDone || $quickFail): ?>
        <div class="ai-quick-done">
            <?php foreach ($quickDone as $n): ?><div class="ok">«<?= e($n) ?>» توضیح جدید ذخیره شد</div><?php endforeach; ?>
            <?php foreach ($quickFail as $f): ?><div class="fail">«<?= e($f['name']) ?>» — <?= e($f['why']) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($needDesc): ?>
        <div class="ai-card">
            <h3 style="margin-top:0;">محصولات با توضیح کوتاه/خالی</h3>
            <p class="muted" style="font-size:.85rem;">محصولات را انتخاب کنید → «تولید و ذخیره» → توضیح حرفه‌ای از روی نام/دسته/قیمت واقعی ساخته و مستقیم ذخیره می‌شود. <b>بدون هیچ تایپی.</b></p>
            <form method="post" action="ai_assistant.php" id="quickForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="quick_desc">
                <div class="ai-quick-list">
                    <?php foreach ($needDesc as $p): ?>
                    <label class="ai-quick-item">
                        <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>">
                        <span>
                            <b><?= e($p['name']) ?></b><br>
                            <span class="qi-meta"><?= e($p['cat']) ?> · <?= fa_digits(number_format((int)$p['price'])) ?> تومان · توضیح فعلی: <?= fa_digits((string)$p['dlen']) ?> بایت</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="ai-quick-actions">
                    <button type="button" class="btn btn-ghost ai-all-btn" id="selAll">انتخاب همه</button>
                    <button type="button" class="btn btn-ghost ai-all-btn" id="selNone">لغو انتخاب</button>
                    <button type="submit" class="btn btn-accent" id="quickSubmit">تولید و ذخیره</button>
                    <span class="ai-loading" id="quickLoading"><span class="ai-spinner"></span> در حال تولید… (هر محصول تا ۱ دقیقه)</span>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="ai-note">همهٔ محصولات فعال توضیح مناسب دارند.</div>
        <?php endif; ?>

        <?php if ($allProducts): ?>
        <div class="ai-card" style="margin-top:1rem;">
            <h3 style="margin-top:0;">بازنویسی توضیح یک محصول خاص</h3>
            <form method="post" action="ai_assistant.php" id="singleForm" style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="quick_desc">
                <select name="ids[]" style="flex:1; min-width:260px;">
                    <?php foreach ($allProducts as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['cat']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline">تولید توضیح جدید</button>
            </form>
            <p class="muted" style="font-size:.82rem; margin:.5rem 0 0;">توضیح فعلی محصول با نسخهٔ حرفه‌ای جدید جایگزین می‌شود.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    // تب‌ها
    var tabs = document.querySelectorAll('.ai-tab');
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.classList.remove('active'); });
            t.classList.add('active');
            document.querySelectorAll('.ai-panel').forEach(function (p) { p.classList.remove('active'); });
            document.getElementById('panel-' + t.getAttribute('data-tab')).classList.add('active');
        });
    });

    // پریست‌ها + starter
    var modeBtns = document.querySelectorAll('.ai-mode-btn');
    var modeInput = document.getElementById('aiMode');
    var prompt = document.getElementById('aiPrompt');
    var hints = <?= json_encode(array_map(function ($p) { return $p['hint']; }, $presets)) ?>;
    var starters = <?= json_encode(array_map(function ($p) { return $p['starter'] ?? ''; }, $presets)) ?>;

    modeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            modeBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var m = btn.getAttribute('data-mode');
            modeInput.value = m;
            if (hints[m]) { prompt.placeholder = hints[m]; }
            // اگر starter دارد، فوراً فرم را پر کن (رفتار «کلیک = آماده‌سازی»)
            if (starters[m]) { prompt.value = starters[m]; }
        });
    });

    var starterBtn = document.getElementById('aiStarter');
    if (starterBtn) {
        starterBtn.addEventListener('click', function () {
            var s = starters[modeInput.value] || '';
            if (s) { prompt.value = s; prompt.focus(); }
        });
    }

    // کپی
    var copy = document.getElementById('aiCopy');
    if (copy) {
        copy.addEventListener('click', function () {
            var txt = document.getElementById('aiResultBody').innerText;
            navigator.clipboard.writeText(txt).then(function () {
                copy.textContent = 'کپی شد';
                setTimeout(function () { copy.textContent = 'کپی متن'; }, 1500);
            });
        });
    }

    // لودینگ فرم تولید
    var form = document.getElementById('aiForm');
    var loading = document.getElementById('aiLoading');
    var submit = document.getElementById('aiSubmit');
    if (form && loading && submit) {
        form.addEventListener('submit', function () {
            submit.disabled = true;
            submit.textContent = 'در حال تولید…';
            loading.classList.add('show');
        });
    }

    // انتخاب همه/هیچ
    var selAll = document.getElementById('selAll');
    var selNone = document.getElementById('selNone');
    if (selAll) selAll.addEventListener('click', function () {
        document.querySelectorAll('#quickForm input[name="ids[]"]').forEach(function (c) { c.checked = true; });
    });
    if (selNone) selNone.addEventListener('click', function () {
        document.querySelectorAll('#quickForm input[name="ids[]"]').forEach(function (c) { c.checked = false; });
    });

    // لودینگ فرم سریع
    var quickForm = document.getElementById('quickForm');
    var quickLoading = document.getElementById('quickLoading');
    var quickSubmit = document.getElementById('quickSubmit');
    if (quickForm && quickLoading && quickSubmit) {
        quickForm.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('#quickForm input[name="ids[]"]:checked').length;
            if (!checked) {
                e.preventDefault();
                alert('حداقل یک محصول را انتخاب کنید.');
                return;
            }
            if (!confirm('توضیح فعلی ' + checked + ' محصول انتخاب‌شده با متن جدید AI جایگزین می‌شود. ادامه؟')) {
                e.preventDefault();
                return;
            }
            quickSubmit.disabled = true;
            quickLoading.classList.add('show');
        });
    }
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
