<?php
/**
 * chat_api.php — نقطهٔ پایانی JSON برای چت زندهٔ مشتری↔پشتیبانی
 * اکشن‌ها:
 *   GET  ?action=poll&thread_id=N&after_id=N  → بازگشت پیام‌های جدید
 *   POST ?action=send                          → ارسال پیام (نیازمند CSRF)
 * فقط مشتری واردشده مجاز است.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chat_json($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'poll');

/* ---------- دریافت پیام‌ها (ادمین) ---------- */
if ($action === 'admin_poll') {
    if (!is_logged_in()) {
        chat_json(['ok' => false, 'error' => 'not_logged_in']);
    }
    if (!ip_rate_limit('chatpoll:admin', 60, 60)) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        chat_json(['ok' => false, 'error' => 'rate_limited']);
    }
    $threadId = (int)($_GET['thread_id'] ?? 0);
    $afterId  = (int)($_GET['after_id'] ?? 0);
    $messages = [];
    if ($threadId > 0) {
        mark_message_read($threadId, true);
        $st = db()->prepare(
            "SELECT id, body, from_admin, created_at
             FROM messages
             WHERE (id = ? OR parent_id = ?) AND id > ?
             ORDER BY id ASC"
        );
        $st->execute([$threadId, $threadId, $afterId]);
        $messages = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    chat_json([
        'ok'       => true,
        'messages' => $messages,
        'unread'   => admin_unread_count(),
    ]);
}

/* ---------- فقط مشتری واردشده برای بقیهٔ اکشن‌ها ---------- */
if (!is_customer_logged_in()) {
    chat_json(['ok' => false, 'error' => 'not_logged_in']);
}

$userId = (int)current_customer()['id'];

// محدودساز ضد شلیک: poll حداکثر ۳۰ در دقیقه، send حداکثر ۱۰ در دقیقه (هر کاربر)
if (($action === 'poll' && !ip_rate_limit('chatpoll:' . $userId, 30, 60))
    || ($action === 'send' && !ip_rate_limit('chatsend:' . $userId, 10, 60))) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    chat_json(['ok' => false, 'error' => 'rate_limited']);
}

/* ---------- ارسال پیام ---------- */
if ($action === 'send') {
    if (!csrf_verify()) {
        chat_json(['ok' => false, 'error' => 'csrf']);
    }
    $body     = trim((string)($_POST['body'] ?? ''));
    $threadId = (int)($_POST['thread_id'] ?? 0);
    if ($body === '') {
        chat_json(['ok' => false, 'error' => 'empty']);
    }

    $subject  = 'گفتگوی زنده';
    $parentId = null;
    if ($threadId > 0) {
        $st = db()->prepare("SELECT * FROM messages WHERE id = ? AND user_id = ? AND parent_id IS NULL");
        $st->execute([$threadId, $userId]);
        $root = $st->fetch();
        if ($root) {
            $subject  = $root['subject'];
            $parentId = (int)$root['id'];
        } else {
            chat_json(['ok' => false, 'error' => 'not_found']);
        }
    }

    send_message($userId, $subject, $body, 0, $parentId);
    $newId = (int)db()->lastInsertId();
    $newThreadId = $parentId ?: $newId;

    // آخرین پیام را برای نمایش فوری برمی‌گردانیم
    $st = db()->prepare("SELECT id, body, from_admin, created_at FROM messages WHERE id = ?");
    $st->execute([$newId]);
    $msg = $st->fetch(PDO::FETCH_ASSOC);

    chat_json([
        'ok'        => true,
        'thread_id' => $newThreadId,
        'message'   => $msg,
    ]);
}

/* ---------- دریافت پیام‌ها (poll) ---------- */
$threadId = (int)($_GET['thread_id'] ?? 0);
$afterId  = (int)($_GET['after_id'] ?? 0);

if ($threadId === 0) {
    // آخرین گفتگوی کاربر
    $st = db()->prepare("SELECT id FROM messages WHERE user_id = ? AND parent_id IS NULL ORDER BY id DESC LIMIT 1");
    $st->execute([$userId]);
    $threadId = (int)$st->fetchColumn();
}

$messages = [];
if ($threadId > 0) {
    mark_message_read($threadId, false, $userId); // پاسخ‌های پشتیبانی خوانده شدند
    $st = db()->prepare(
        "SELECT id, body, from_admin, created_at
         FROM messages
         WHERE user_id = ? AND (id = ? OR parent_id = ?) AND id > ?
         ORDER BY id ASC"
    );
    $st->execute([$userId, $threadId, $threadId, $afterId]);
    $messages = $st->fetchAll(PDO::FETCH_ASSOC);
}

chat_json([
    'ok'        => true,
    'thread_id' => $threadId,
    'unread'    => unread_messages_count($userId),
    'messages'  => $messages,
]);
