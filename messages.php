<?php
/**
 * messages.php — پیام‌ها و پشتیبانی (باشگاه مشتریان)
 * رابط پیام‌رسان: لیست گفتگوها + پنجرهٔ چت شبیه پیام‌رسان موبایلی.
 */
require_once __DIR__ . '/config.php';

if (!is_customer_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php?next=' . urlencode('/messages.php'));
    exit;
}

$user   = current_customer();
$userId = (int)$user['id'];
$errors = [];

// ---- بستن / باز کردن گفتگو (توسط مشتری) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_thread') {
    if (!csrf_verify()) {
        $errors[] = 'نشست نامعتبر است.';
    } else {
        $ct = (int)($_POST['thread_id'] ?? 0);
        $st = db()->prepare("SELECT id FROM messages WHERE id = ? AND user_id = ? AND parent_id IS NULL");
        $st->execute([$ct, $userId]);
        if ($st->fetch()) {
            $close = (($_POST['close'] ?? '1') === '1');
            set_thread_closed($ct, $close, 'customer');
            flash('success', $close ? 'گفتگو بسته شد. دیگر پیامی به آن اضافه نمی‌شود.' : 'گفتگو دوباره باز شد.');
        }
        header('Location: ' . BASE_URL . '/messages.php' . ($ct ? '?id=' . $ct : ''));
        exit;
    }
}

// ---- ارسال پیام جدید / پاسخ در گفتگو ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ip_rate_limit('contact', 3, 600)) {
        $errors[] = 'تعداد پیام‌های ارسالی شما بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.';
    } elseif (!csrf_verify()) {
        $errors[] = 'نشست نامعتبر است؛ لطفاً صفحه را بازنشانی و دوباره تلاش کنید.';
    } else {
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $subject  = trim($_POST['subject'] ?? '');
        $body     = trim($_POST['body'] ?? '');
        $parentId = null;

        if ($threadId > 0) {
            // پاسخ در یک گفتگوی موجود → موضوع ثابت می‌ماند
            $st = db()->prepare("SELECT * FROM messages WHERE id = ? AND user_id = ? AND parent_id IS NULL");
            $st->execute([$threadId, $userId]);
            $root = $st->fetch();
            if (!$root) {
                $errors[] = 'گفتگوی موردنظر یافت نشد.';
            } elseif (!empty($root['is_closed'])) {
                $errors[] = 'این گفتگو بسته شده است. ابتدا آن را باز کنید.';
            } else {
                $subject  = $root['subject'];
                $parentId = (int)$root['id'];
            }
        }

        if ($body === '') {
            $errors[] = 'متن پیام الزامی است.';
        }
        if ($threadId === 0 && $subject === '') {
            $errors[] = 'موضوع پیام الزامی است.';
        } elseif ($subject !== '' && mb_strlen($subject, 'UTF-8') > 200) {
            $errors[] = 'موضوع نباید بیش از ۲۰۰ کاراکتر باشد.';
        }

        if (!$errors) {
            send_message($userId, $subject, $body, 0, $parentId);
            flash('success', 'پیام شما با موفقیت ارسال شد. پاسخ پشتیبانی در همین صفحه نمایش داده می‌شود.');
            header('Location: ' . BASE_URL . '/messages.php' . ($parentId ? '?id=' . $parentId : ''));
            exit;
        }
    }
}

// ---- مشاهدهٔ یک گفتگو ----
$detail    = null;
$thread    = null;
$threadId  = (int)($_GET['id'] ?? 0);
if ($threadId > 0) {
    $st = db()->prepare("SELECT * FROM messages WHERE id = ? AND user_id = ? AND parent_id IS NULL");
    $st->execute([$threadId, $userId]);
    $thread = $st->fetch();
    if ($thread) {
        mark_message_read($threadId, false, $userId);
        $st = db()->prepare("SELECT * FROM messages WHERE user_id = ? AND (id = ? OR parent_id = ?) ORDER BY id ASC");
        $st->execute([$userId, $threadId, $threadId]);
        $detail = $st->fetchAll();
    }
}

$threads     = user_messages($userId);
$unreadTotal = unread_messages_count($userId);

$pageTitle = 'پیام‌ها و پشتیبانی | باشگاه مشتریان';
require __DIR__ . '/includes/header.php';
?>
<style>
/* ---------- رابط پیام‌رسان ---------- */
.msgr {
    display: flex; flex-direction: column;
    background: var(--surface); border: 1px solid var(--line);
    border-radius: var(--radius-lg); overflow: hidden;
    box-shadow: var(--shadow-soft);
}
/* سربرگ چت */
.msgr-head {
    display: flex; align-items: center; gap: 0.8rem;
    padding: 0.9rem 1.1rem; background: var(--navy); color: #fff;
}
.msgr-back {
    display: grid; place-items: center; width: 36px; height: 36px; flex: 0 0 36px;
    border-radius: 50%; color: #fff; background: rgba(255,255,255,.12);
    transition: background .15s;
}
.msgr-back:hover { background: rgba(255,255,255,.25); }
.msgr-head-title { font-weight: 700; font-size: 1rem; }
.msgr-head-sub { font-size: .78rem; color: rgba(255,255,255,.7); margin-top: 0.1rem; }

/* بدنهٔ چت (اسکرول‌شونده) */
.msgr-body {
    flex: 1; min-height: 320px; max-height: 56vh; overflow-y: auto;
    padding: 1.1rem 1rem; display: flex; flex-direction: column; gap: 0.7rem;
    background: #eef1f6;
}
.chat-day { align-self: center; font-size: .72rem; color: var(--ink-soft); background: rgba(255,255,255,.8); padding: .2rem .8rem; border-radius: 999px; }
.bubble { max-width: 78%; padding: 0.6rem 0.95rem; border-radius: 14px; line-height: 1.85; font-size: 0.93rem; word-break: break-word; position: relative; }
.bubble-customer { align-self: flex-end; background: var(--accent); color: #fff; border-end-end-radius: 4px; }
.bubble-admin { align-self: flex-start; background: #fff; color: var(--navy); border: 1px solid var(--line); border-end-start-radius: 4px; }
.bubble-meta { display: block; font-size: 0.68rem; margin-top: 0.3rem; opacity: 0.75; text-align: left; }
.bubble-admin .bubble-meta { color: var(--ink-soft); }
.bubble-customer .bubble-meta { color: rgba(255,255,255,.85); }

/* نوار ورودی (ثابت پایین) */
.msgr-input {
    display: flex; align-items: flex-end; gap: 0.5rem;
    padding: 0.75rem 0.9rem; background: var(--surface); border-top: 1px solid var(--line);
}
.msgr-input textarea {
    flex: 1; resize: none; border: 1px solid var(--line); border-radius: 22px;
    padding: 0.6rem 1rem; font: inherit; font-size: .93rem; max-height: 120px; min-height: 42px;
    background: var(--bg, #f4f6fa);
}
.msgr-input textarea:focus { outline: none; border-color: var(--accent); background: #fff; }
.msgr-send {
    flex: 0 0 44px; height: 42px; border: none; border-radius: 50%; cursor: pointer;
    background: var(--accent); color: #fff; display: grid; place-items: center;
    transition: background .15s;
}
.msgr-send:hover { background: var(--accent-dark, #0a1a6b); }
.msgr-close-btn {
    padding: .4rem .9rem; border-radius: 999px; border: 1px solid rgba(255,255,255,.35);
    background: transparent; color: #fff; cursor: pointer; font-size: .78rem;
    transition: background .15s;
}
.msgr-close-btn:hover { background: rgba(255,255,255,.18); }
.msgr-closed-note {
    padding: .9rem 1.1rem; text-align: center; font-size: .85rem;
    background: #fdf2f2; color: #9b1c1c; border-top: 1px solid var(--line);
}

/* لیست گفتگوها */
.msg-list { display: flex; flex-direction: column; gap: 0.6rem; }
.msg-card { display: flex; gap: 0.8rem; align-items: center; border: 1px solid var(--line); border-radius: var(--radius); padding: 0.9rem 1rem; background: var(--surface); color: inherit; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
.msg-card:hover { border-color: var(--accent); box-shadow: var(--shadow-soft); }
.msg-card.unread { border-inline-start: 3px solid var(--accent); }
.msg-avatar { flex: 0 0 44px; width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: 1rem; }
.msg-card.unread .msg-avatar { background: var(--accent); color: #fff; }
.msg-main { flex: 1; min-width: 0; }
.msg-top { display: flex; justify-content: space-between; align-items: center; gap: 0.6rem; }
.msg-subject { font-weight: 700; color: var(--navy); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.msg-date { font-size: 0.75rem; color: var(--ink-soft); white-space: nowrap; }
.msg-preview { color: var(--ink-soft); font-size: 0.86rem; margin-top: 0.25rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.msg-unread-badge { flex: 0 0 auto; background: var(--accent); color: #fff; font-size: 0.7rem; border-radius: 999px; padding: 0.15rem 0.55rem; font-weight: 600; }

/* فرم پیام جدید (جمع‌شونده) */
.compose-toggle { display: inline-flex; align-items: center; gap: .4rem; }
.compose-box { border: 1px dashed var(--line); border-radius: var(--radius); padding: 1rem 1.2rem; margin-bottom: 1.4rem; background: var(--bg, #f4f6fa); }

@media (max-width: 640px) {
    .msgr-body { max-height: 62vh; }
    .bubble { max-width: 86%; }
}
</style>

<section class="container account">
    <div class="account-head">
        <div>
            <p class="auth-eyebrow">باشگاه مشتریان</p>
            <h1 class="page-title">پیام‌ها و پشتیبانی</h1>
        </div>
        <div class="account-head-actions">
            <a href="account.php" class="btn btn-ghost">بازگشت به حساب</a>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($detail !== null && $thread): ?>

        <!-- ===== پنجرهٔ چت ===== -->
        <div class="msgr">
            <div class="msgr-head">
                <a href="messages.php" class="msgr-back" aria-label="بازگشت به فهرست گفتگوها">→</a>
                <div>
                    <div class="msgr-head-title"><?= e($thread['subject']) ?></div>
                    <div class="msgr-head-sub">پشتیبانی فروشگاه</div>
                </div>
                <?php $threadClosed = !empty($thread['is_closed']); ?>
                <form method="post" action="messages.php?id=<?= (int)$threadId ?>" style="margin-inline-start:auto;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="close_thread">
                    <input type="hidden" name="thread_id" value="<?= (int)$threadId ?>">
                    <input type="hidden" name="close" value="<?= $threadClosed ? '0' : '1' ?>">
                    <button type="submit" class="msgr-close-btn" title="<?= $threadClosed ? 'باز کردن گفتگو' : 'بستن گفتگو' ?>"><?= $threadClosed ? '🔓 بازکردن' : '🔒 بستن گفتگو' ?></button>
                </form>
            </div>

            <div class="msgr-body" id="msgrBody">
                <?php foreach ($detail as $m): $fromAdmin = (int)$m['from_admin']; ?>
                    <div class="bubble <?= $fromAdmin ? 'bubble-admin' : 'bubble-customer' ?>">
                        <div><?= nl2br(e($m['body'])) ?></div>
                        <span class="bubble-meta"><?= e(persian_date($m['created_at'], true)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($threadClosed): ?>
            <div class="msgr-closed-note">این گفتگو بسته شده است. برای ادامهٔ گفتگو آن را باز کنید یا یک گفتگوی جدید بسازید.</div>
            <?php else: ?>
            <form method="post" action="messages.php?id=<?= (int)$threadId ?>" class="msgr-input" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="thread_id" value="<?= (int)$threadId ?>">
                <textarea name="body" rows="1" required placeholder="پیام خود را بنویسید…"></textarea>
                <button type="submit" class="msgr-send" aria-label="ارسال">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4l17.4-7.5c.8-.35.8-1.45 0-1.8L3.4 3.6c-.66-.28-1.37.2-1.3.9l.7 7.2 11.4 1.3-11.4 1.3-.7 7.2c-.07.7.64 1.18 1.3.9z"/></svg>
                </button>
            </form>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <!-- ===== فرم پیام جدید (جمع‌شونده) ===== -->
        <div class="compose-box">
            <button type="button" class="btn btn-accent compose-toggle" id="composeToggle">＋ پیام جدید به پشتیبانی</button>
            <form method="post" action="messages.php" class="auth-form" id="composeForm" style="display:none; margin-top: 1rem;" novalidate>
                <?= csrf_field() ?>
                <label>موضوع *
                    <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" placeholder="مثلاً: پیگیری سفارش، سؤال فنی، درخواست مشاوره…" required>
                </label>
                <label>متن پیام *
                    <textarea name="body" rows="4" required placeholder="جزئیات درخواست یا سؤال خود را بنویسید…"><?= e($_POST['body'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="btn btn-accent">ارسال پیام</button>
            </form>
        </div>

        <?php if ($unreadTotal > 0): ?>
            <div class="alert alert-info">شما <?= (int)$unreadTotal ?> پاسخ خوانده‌نشده از پشتیبانی دارید.</div>
        <?php endif; ?>

        <h2 class="section-title" style="font-size: 1.4rem;">گفتگوهای شما</h2>
        <?php if (!$threads): ?>
            <div class="empty-state">
                <p>هنوز گفتگویی ندارید. اولین پیام خود را به پشتیبانی ارسال کنید.</p>
            </div>
        <?php else: ?>
            <div class="msg-list">
                <?php foreach ($threads as $t): ?>
                    <a href="messages.php?id=<?= (int)$t['id'] ?>" class="msg-card <?= $t['unread'] > 0 ? 'unread' : '' ?>">
                        <span class="msg-avatar"><?= e(mb_substr($t['subject'], 0, 1, 'UTF-8')) ?></span>
                        <span class="msg-main">
                            <span class="msg-top">
                                <span class="msg-subject"><?= e($t['subject']) ?></span>
                                <span class="msg-date"><?= e(persian_date($t['last_at'], true)) ?></span>
                            </span>
                            <span class="msg-preview"><?= e(mb_strlen($t['last_body']) > 90 ? mb_substr($t['last_body'], 0, 90, 'UTF-8') . '…' : $t['last_body']) ?></span>
                        </span>
                        <?php if ($t['unread'] > 0): ?><span class="msg-unread-badge"><?= (int)$t['unread'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</section>

<script>
(function () {
    var toggle = document.getElementById('composeToggle');
    var form = document.getElementById('composeForm');
    if (toggle && form) {
        toggle.addEventListener('click', function () {
            var show = form.style.display === 'none';
            form.style.display = show ? 'block' : 'none';
            toggle.textContent = show ? '− بستن فرم' : '＋ پیام جدید به پشتیبانی';
        });
    }
    // اسکرول خودکار به آخرین پیام در چت
    var body = document.getElementById('msgrBody');
    if (body) { body.scrollTop = body.scrollHeight; }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
