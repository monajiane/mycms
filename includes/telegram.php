<?php
/**
 * includes/telegram.php — Telegram bot helpers
 * Persists: tg_bot_token, tg_chat_admin_ids (comma-separated chat ids)
 * Public:
 *   tg_setting($k, $d='')
 *   tg_set_setting($k, $v)
 *   tg_enabled(): bool
 *   tg_admin_chat_ids(): array
 *   tg_send($chatId, $text, $opts=[]): array  // raw API response
 *   tg_notify_admins($text, $category = 'general'): int  // number of admins notified
 *   tg_admin_keyboard(): string  // HTML inline keyboard builder helper
 *
 * No external HTTP library required — uses stream wrapper. No persistent file lock.
 */
if (!defined('ABZAR_TG')) define('ABZAR_TG', 1);

function tg_setting($key, $default = '') {
    if (function_exists('setting')) return setting($key, $default);
    return $default;
}
function tg_set_setting($key, $value) {
    if (function_exists('set_setting')) { set_setting($key, (string)$value); return true; }
    return false;
}
function tg_enabled() {
    $token = trim((string)tg_setting('tg_bot_token', ''));
    if ($token === '') return false;
    $ids = tg_admin_chat_ids();
    return !empty($ids);
}
function tg_admin_chat_ids() {
    $raw = (string)tg_setting('tg_chat_admin_ids', '');
    $out = [];
    foreach (explode(',', $raw) as $p) {
        $p = trim($p);
        if ($p !== '' && ctype_digit(ltrim($p, '-'))) $out[] = $p;
    }
    return $out;
}
function tg_bot_token() { return trim((string)tg_setting('tg_bot_token', '')); }

function tg_api_url($method) {
    $t = tg_bot_token();
    return $t === '' ? '' : 'https://api.telegram.org/bot' . $t . '/' . $method;
}

/** Low-level POST. Returns decoded JSON array; on error returns ['ok'=>false,'error'=>...]. */
function tg_send($chatId, $text, $opts = []) {
    $url = tg_api_url('sendMessage');
    if ($url === '') return ['ok' => false, 'error' => 'no_token'];
    $payload = array_merge([
        'chat_id'                  => (string)$chatId,
        'text'                     => (string)$text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ], $opts);
    $body = http_build_query($payload, '', '&');
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: AbzarSharghTGBot/1.0\r\n",
            'content'       => $body,
            'timeout'       => 8,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return ['ok' => false, 'error' => 'http_fail'];
    $j = json_decode($raw, true);
    if (!is_array($j)) return ['ok' => false, 'error' => 'bad_json', 'raw' => $raw];
    return $j;
}

/** Send to all configured admin chats. Returns count of successful deliveries. */
function tg_notify_admins($text, $category = 'general') {
    if (!tg_enabled()) return 0;
    $ok = 0;
    foreach (tg_admin_chat_ids() as $cid) {
        $r = tg_send($cid, $text);
        if (!empty($r['ok'])) $ok++;
        if (function_exists('log_event')) {
            log_event('tg notify cat=' . $category . ' chat=' . $cid . ' ok=' . (!empty($r['ok']) ? '1' : '0') . (empty($r['ok']) ? ' err=' . substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 200) : ''), 'info');
        }
    }
    return $ok;
}

/** Build inline keyboard JSON. $rows = [ [ ['text'=>..,'url'=>..] ], ... ] */
function tg_keyboard_json($rows) {
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}
