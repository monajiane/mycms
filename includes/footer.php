<?php
/** includes/footer.php — قالب مشترک پایین صفحات فروشگاه */
$settings = get_settings();
?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <div class="brand footer-logo"><span class="brand-mark"></span><span class="brand-text"><?= e($settings['store_name']) ?></span></div>
            <p class="footer-about"><?= e(setting('company_about', 'تولید، تأمین و بازسازی ابزارآلات دقیق صنعتی با بیش از سه دهه تجربه.')) ?></p>
        </div>
        <nav class="footer-col" aria-label="دسترسی سریع">
            <h3>دسترسی سریع</h3>
            <a href="<?= e(BASE_URL) ?>/index.php">خانه</a>
            <a href="<?= e(BASE_URL) ?>/shop.php">فروشگاه</a>
            <a href="<?= e(BASE_URL) ?>/knowledge.php">دانشنامه</a>
            <a href="<?= e(BASE_URL) ?>/about.php">درباره ما</a>
        </nav>
        <nav class="footer-col" aria-label="خدمات مشتریان">
            <h3>خدمات مشتریان</h3>
            <a href="<?= e(BASE_URL) ?>/account.php">باشگاه مشتریان</a>
            <a href="<?= e(BASE_URL) ?>/custom_request.php">سفارش ابزار اختصاصی</a>
            <a href="<?= e(BASE_URL) ?>/cart.php">سبد خرید</a>
            <?php if (tracking_enabled()): ?>
            <a href="<?= e(BASE_URL) ?>/track.php">رهگیری سفارش</a>
            <?php endif; ?>
            <a href="<?= e(BASE_URL) ?>/terms.php">قوانین و مقررات</a>
            <a href="<?= e(BASE_URL) ?>/privacy.php">حریم خصوصی</a>
        </nav>
        <div class="footer-col" aria-label="اطلاعات تماس">
            <h3>تماس با ما</h3>
            <span class="footer-line"><?= e(setting('company_address', 'تهران، ایران')) ?></span>
            <span class="footer-line" dir="ltr"><?= e(fa_digits(setting('company_phone', '021-00000000'))) ?></span>
            <span class="footer-line" dir="ltr"><?= e(setting('company_email', 'info@abzarsazi-shargh.ir')) ?></span>
            <span class="footer-line"><?= e(setting('company_hours', 'شنبه تا چهارشنبه ۰۸:۰۰ تا ۱۷:۰۰')) ?></span>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>© <?= date('Y') ?> <?= e($settings['store_name']) ?> - کلیهٔ حقوق محفوظ است.</p>
        <?php $enamadCode = trim((string)setting('enamad_code', '')); $enamadImg = trim((string)setting('enamad_image', '')); $enamadLink = trim((string)setting('enamad_link', '')); ?>
        <?php if ($enamadCode !== ''): ?>
        <p class="footer-trust"><?= $enamadCode /* کد رسمی اینماد — عیناً رندر می‌شود */ ?></p>
        <?php elseif ($enamadImg !== ''): ?>
        <p class="footer-trust">
            <?php if ($enamadLink !== ''): ?><a href="<?= e($enamadLink) ?>" target="_blank" rel="noopener noreferrer"><?php endif; ?>
                <img src="<?= e(product_image_url($enamadImg)) ?>" alt="نماد اعتماد الکترونیکی" loading="lazy" style="height: 88px; width: auto;">
            <?php if ($enamadLink !== ''): ?></a><?php endif; ?>
        </p>
        <?php endif; ?>
        <p><?= e($settings['store_tagline']) ?></p>
    </div>
</footer>
<script src="<?= e(BASE_URL) ?>/assets/js/main.js?v=1.3" defer></script>
<script>
(function () {
    var t = document.getElementById('navToggle');
    var nav = document.getElementById('mainNav');
    if (t && nav) {
        t.addEventListener('click', function () {
            var open = document.body.classList.toggle('nav-open');
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
})();
</script>
<?php if (is_customer_logged_in()): ?>
<?php
$chatUser = current_customer();
$chatUserId = (int)$chatUser['id'];
?>
<!-- ویجت چت زنده (مشتری ↔ پشتیبانی) -->
<style>
.chat-fab { position: fixed; bottom: 24px; inset-inline-start: 24px; z-index: 999; width: 56px; height: 56px; border-radius: 50%; background: var(--accent, #c9a04a); color: #fff; border: none; cursor: pointer; box-shadow: 0 6px 20px rgba(0,0,0,.25); font-size: 26px; display: flex; align-items: center; justify-content: center; }
.chat-fab .chat-dot { position: absolute; top: 2px; inset-inline-end: 2px; background: #e5484d; color: #fff; font-size: 11px; min-width: 18px; height: 18px; border-radius: 999px; display: none; align-items: center; justify-content: center; padding: 0 4px; }
.chat-panel { position: fixed; bottom: 90px; inset-inline-start: 24px; z-index: 999; width: 320px; max-width: calc(100vw - 32px); background: #fff; border: 1px solid var(--line, #e5e2da); border-radius: 14px; box-shadow: 0 12px 40px rgba(0,0,0,.18); display: none; flex-direction: column; overflow: hidden; }
.chat-panel.open { display: flex; }
.chat-head { background: var(--accent, #c9a04a); color: #fff; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; font-weight: 700; }
.chat-head button { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; line-height: 1; }
.chat-body { height: 300px; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; background: #faf9f6; }
.chat-msg { max-width: 85%; padding: 8px 12px; border-radius: 12px; line-height: 1.6; font-size: .9rem; white-space: pre-wrap; word-wrap: break-word; }
.chat-msg.me { align-self: flex-end; background: var(--accent, #c9a04a); color: #fff; border-bottom-inline-end-radius: 4px; }
.chat-msg.them { align-self: flex-start; background: #fff; color: #1f2430; border: 1px solid var(--line, #e5e2da); border-bottom-inline-start-radius: 4px; }
.chat-msg .chat-time { display: block; font-size: .68rem; opacity: .7; margin-top: 4px; }
.chat-empty { color: var(--ink-soft, #7a7a7a); text-align: center; font-size: .85rem; margin: auto; }
.chat-foot { display: flex; gap: 6px; padding: 10px; border-top: 1px solid var(--line, #e5e2da); background: #fff; }
.chat-foot input { flex: 1; border: 1px solid var(--line, #e5e2da); border-radius: 8px; padding: 9px 12px; font-size: .9rem; }
.chat-foot button { border: none; background: var(--accent, #c9a04a); color: #fff; border-radius: 8px; padding: 0 14px; cursor: pointer; font-weight: 700; }
</style>
<button class="chat-fab" id="chatFab" aria-label="گفتگو با پشتیبانی"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span class="chat-dot" id="chatDot"></span></button>
<div class="chat-panel" id="chatPanel">
    <div class="chat-head">گفتگو با پشتیبانی <button id="chatClose" aria-label="بستن">×</button></div>
    <div class="chat-body" id="chatBody"><div class="chat-empty">در حال بارگذاری…</div></div>
    <div class="chat-foot">
        <input type="text" id="chatInput" placeholder="پیام خود را بنویسید…">
        <button id="chatSend">ارسال</button>
    </div>
</div>
<script>
(function () {
    var CSRF = '<?= e(csrf_token()) ?>';
    var API = '<?= e(BASE_URL) ?>/chat_api.php';
    var userId = <?= $chatUserId ?>;
    var threadId = 0;
    var afterId = 0;
    var pollTimer = null;
    var firstPoll = true;
    var fab = document.getElementById('chatFab');
    var panel = document.getElementById('chatPanel');
    var body = document.getElementById('chatBody');
    var dot = document.getElementById('chatDot');
    var input = document.getElementById('chatInput');

    function playSound() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { return; }
            var ctx = new Ctx();
            var now = ctx.currentTime;
            [0, 0.18].forEach(function (offset, i) {
                var o = ctx.createOscillator();
                var g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'sine';
                o.frequency.value = i === 0 ? 740 : 988;
                g.gain.setValueAtTime(0.0001, now + offset);
                g.gain.exponentialRampToValueAtTime(0.12, now + offset + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.22);
                o.start(now + offset);
                o.stop(now + offset + 0.24);
            });
        } catch (e) {}
    }

    function render(messages) {
        if (!messages || !messages.length) {
            if (!body.querySelector('.chat-msg')) {
                body.innerHTML = '<div class="chat-empty">هنوز پیامی ندارید؛ اولین پیام را بفرستید.</div>';
            }            return;
        }
        body.querySelectorAll('.chat-empty').forEach(function (e) { e.remove(); });
        messages.forEach(function (m) {
            if (parseInt(m.id, 10) <= afterId) { return; }
            var el = document.createElement('div');
            el.className = 'chat-msg ' + (parseInt(m.from_admin, 10) ? 'them' : 'me');
            el.innerHTML = m.body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                + '<span class="chat-time">' + (parseInt(m.from_admin,10) ? 'پشتیبانی' : 'شما') + '</span>';
            body.appendChild(el);
            afterId = Math.max(afterId, parseInt(m.id, 10));
        });
        body.scrollTop = body.scrollHeight;
    }

    function poll() {
        fetch(API + '?action=poll&thread_id=' + threadId + '&after_id=' + afterId, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { return; }
                threadId = d.thread_id;
                // صدای اعلان فقط برای پیام جدید پشتیبانی
                var hasNewThem = (d.messages || []).some(function (m) {
                    return parseInt(m.from_admin, 10) === 1 && parseInt(m.id, 10) > afterId;
                });
                if (hasNewThem && !firstPoll) { playSound(); }
                firstPoll = false;
                render(d.messages);
                if (d.unread > 0) { dot.style.display = 'flex'; dot.textContent = d.unread; }
                else { dot.style.display = 'none'; }
            })
            .catch(function () {});
    }

    function send() {
        var text = input.value.trim();
        if (!text) { return; }
        var fd = new FormData();
        fd.append('action', 'send');
        fd.append('csrf', CSRF);
        fd.append('thread_id', threadId);
        fd.append('body', text);
        input.value = '';
        fetch(API + '?action=send', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { threadId = d.thread_id; render([d.message]); }
            })
            .catch(function () {});
    }

    fab.addEventListener('click', function () { panel.classList.toggle('open'); if (panel.classList.contains('open')) { poll(); } });
    document.getElementById('chatClose').addEventListener('click', function () { panel.classList.remove('open'); });
    document.getElementById('chatSend').addEventListener('click', send);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); send(); } });

    poll();
    pollTimer = setInterval(poll, 4000);
})();
</script>
<?php endif; ?>
</body>
</html>
