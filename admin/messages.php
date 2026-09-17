<?php
/**
 * admin/messages.php — پیام‌رسان داخلی (پنل ادمین)
 * نمایش گفتگوها، پاسخ به کاربران، علامت‌گذاری خوانده‌شده و حذف.
 */
$pageTitle = 'پیام‌ها';
require __DIR__ . '/_header.php';

// حذف گفتگو
if (isset($_GET['delete'])) {
    db()->prepare("DELETE FROM messages WHERE id = ? OR parent_id = ?")
        ->execute([(int)$_GET['delete'], (int)$_GET['delete']]);
    flash('success', 'گفتگو حذف شد.');
    header('Location: messages.php');
    exit;
}

// پاسخ ادمین
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    $threadId = (int)($_POST['thread_id'] ?? 0);
    $userId   = (int)($_POST['user_id'] ?? 0);
    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    if ($threadId > 0 && $userId > 0 && $body !== '') {
        if (is_thread_closed($threadId)) {
            flash('error', 'این گفتگو بسته شده است؛ برای پاسخ ابتدا آن را باز کنید.');
            header('Location: messages.php?thread=' . $threadId);
            exit;
        }
        send_message($userId, $subject, $body, 1, $threadId);
        flash('success', 'پاسخ ارسال شد.');
    } else {
        flash('error', 'متن پاسخ الزامی است.');
    }
    header('Location: messages.php?thread=' . $threadId);
    exit;
}

// بستن/باز کردن گفتگو توسط ادمین
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_thread') {
    if (csrf_verify()) {
        $ct = (int)($_POST['thread_id'] ?? 0);
        if ($ct > 0) {
            $close = (($_POST['close'] ?? '1') === '1');
            set_thread_closed($ct, $close, 'admin');
            flash('success', $close ? 'گفتگو بسته شد.' : 'گفتگو باز شد.');
        }
    }
    header('Location: messages.php' . ($ct ? '?thread=' . $ct : ''));
    exit;
}

// مشاهدهٔ یک گفتگو
$threadId = (int)($_GET['thread'] ?? 0);
$thread = null;
$convo  = [];
$cUser  = null;
if ($threadId > 0) {
    $st = db()->prepare("SELECT * FROM messages WHERE id = ? AND parent_id IS NULL");
    $st->execute([$threadId]);
    $thread = $st->fetch();
    if ($thread) {
        mark_message_read($threadId, true);
        $st = db()->prepare("SELECT * FROM messages WHERE id = ? OR parent_id = ? ORDER BY id ASC");
        $st->execute([$threadId, $threadId]);
        $convo = $st->fetchAll();
        $st = db()->prepare("SELECT * FROM users WHERE id = ?");
        $st->execute([(int)$thread['user_id']]);
        $cUser = $st->fetch();
    }
}

$threads = admin_messages();
?>
<style>
.nav-badge { background: var(--accent, #c9a04a); color: #fff; font-size: 0.7rem; border-radius: 999px; padding: 0.1rem 0.5rem; margin-inline-start: 0.3rem; vertical-align: middle; }
.thread { display: flex; flex-direction: column; gap: 0.8rem; margin: 1rem 0 1.4rem; }
.bubble { max-width: 75%; padding: 0.6rem 0.95rem; border-radius: 12px; line-height: 1.8; font-size: 0.92rem; }
.bubble-user  { align-self: flex-start; background: #f1efe9; color: #1f2430; border: 1px solid var(--line, #e5e2da); }
.bubble-admin { align-self: flex-end; background: var(--accent, #c9a04a); color: #fff; }
.bubble-meta { font-size: 0.72rem; margin-top: 0.3rem; opacity: 0.8; }
</style>

<h1 class="page-title">پیام‌های کاربران</h1>

<?php if ($thread && $cUser): ?>

    <div class="dash-panel">
        <a href="messages.php" class="btn btn-ghost btn-sm">→ بازگشت به فهرست</a>
        <?php $threadClosed = !empty($thread['is_closed']); ?>
        <form method="post" action="messages.php" style="display:inline; margin-inline-start:.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="close_thread">
            <input type="hidden" name="thread_id" value="<?= (int)$threadId ?>">
            <input type="hidden" name="close" value="<?= $threadClosed ? '0' : '1' ?>">
            <button type="submit" class="btn btn-sm <?= $threadClosed ? 'btn-accent' : 'btn-danger' ?>" onclick="return confirm('<?= $threadClosed ? 'گفتگو باز شود؟' : 'گفتگو بسته شود؟ دیگر پاسخی به آن اضافه نمیشود.' ?>')"><?= $threadClosed ? '🔓 بازکردن گفتگو' : '🔒 بستن گفتگو' ?></button>
        </form>
        <h2 style="margin-top: 0.6rem;">گفتگو با <?= e($cUser['full_name'] ?: $cUser['username']) ?>
            <span class="muted" style="font-size: 0.85rem;">(<?= e($cUser['username']) ?>)</span>
            <?php if ($threadClosed): ?><span class="status status-failed">بسته‌شده</span><?php endif; ?>
        </h2>

        <div class="thread">
            <?php foreach ($convo as $m): ?>
                <div class="bubble <?= (int)$m['from_admin'] ? 'bubble-admin' : 'bubble-user' ?>">
                    <div><?= nl2br(e($m['body'])) ?></div>
                    <div class="bubble-meta"><?= (int)$m['from_admin'] ? 'شما' : e($cUser['full_name'] ?: $cUser['username']) ?> — <?= e(persian_date($m['created_at'], true)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($threadClosed): ?>
        <div class="alert alert-info">این گفتگو بسته شده است؛ برای پاسخ‌دادن ابتدا آن را باز کنید.</div>
        <?php else: ?>
        <form method="post" action="messages.php" class="admin-form" style="margin-top: 0.5rem;">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="thread_id" value="<?= (int)$threadId ?>">
            <input type="hidden" name="user_id" value="<?= (int)$thread['user_id'] ?>">
            <input type="hidden" name="subject" value="<?= e($thread['subject'] ?? '') ?>">
            <label>پاسخ شما *</label>
            <textarea name="body" rows="4" required placeholder="پاسخ خود را بنویسید…"></textarea>
            <button type="submit" class="btn btn-accent">ارسال پاسخ</button>
        </form>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        var API = '<?= e(BASE_URL) ?>/chat_api.php';
        var threadId = <?= (int)$threadId ?>;
        var afterId = <?= $convo ? (int)$convo[count($convo) - 1]['id'] : 0 ?>;
        var container = document.querySelector('.thread');
        var firstPoll = true;
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
        function poll() {
            fetch(API + '?action=admin_poll&thread_id=' + threadId + '&after_id=' + afterId, { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { return; }
                    var hasNewUser = (d.messages || []).some(function (m) {
                        return parseInt(m.from_admin, 10) === 0 && parseInt(m.id, 10) > afterId;
                    });
                    if (hasNewUser && !firstPoll) { playSound(); }
                    firstPoll = false;
                    (d.messages || []).forEach(function (m) {
                        if (parseInt(m.id, 10) <= afterId) { return; }
                        var el = document.createElement('div');
                        el.className = 'bubble ' + (parseInt(m.from_admin, 10) ? 'bubble-admin' : 'bubble-user');
                        el.innerHTML = m.body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                            + '<div class="bubble-meta">' + (parseInt(m.from_admin,10) ? 'شما' : 'کاربر') + '</div>';
                        container.appendChild(el);
                        afterId = Math.max(afterId, parseInt(m.id, 10));
                    });
                })
                .catch(function () {});
        }
        setInterval(poll, 5000);
    })();
    </script>

<?php else: ?>

    <div class="dash-panel">
        <?php if (!$threads): ?>
            <p class="muted">هنوز پیامی دریافت نشده است.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>کاربر</th><th>موضوع</th><th>آخرین پیام</th><th>وضعیت</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($threads as $t): ?>
                    <?php $isNew = ((int)$t['from_admin'] === 0 && (int)$t['is_read'] === 0); ?>
                    <tr>
                        <td><?= e($t['full_name'] ?: $t['username']) ?></td>
                        <td><?= e($t['subject'] ?: '—') ?></td>
                        <td><?= e(mb_substr((string)$t['body'], 0, 45, 'UTF-8')) ?><?= mb_strlen((string)$t['body'], 'UTF-8') > 45 ? '…' : '' ?></td>
                        <td>
                            <?php if ($isNew): ?><span class="status status-pending">جدید</span>
                            <?php else: ?><span class="muted">خوانده‌شده</span><?php endif; ?>
                        </td>
                        <td>
                            <a href="messages.php?thread=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm">مشاهده</a>
                            <a href="messages.php?delete=<?= (int)$t['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('این گفتگو حذف شود؟')">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
