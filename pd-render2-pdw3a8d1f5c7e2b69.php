<?php
// pd-render2 — موقت. همه چیز داخل تراکنش و rollback می‌شود؛ هیچ داده‌ای ذخیره نمی‌شود.
if (($_GET['k'] ?? '') !== 'pdw3a8d1f5c7e2b69') { http_response_code(404); exit('404'); }
header('Content-Type: text/plain; charset=utf-8');
$root = __DIR__;
require_once $root . '/config.php';
if (is_file($root . '/includes/functions.php')) require_once $root . '/includes/functions.php';
ini_set('display_errors', '0');
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
$_SESSION['admin_id'] = 1; $_SESSION['admin_role'] = 'admin';

$db = db();
$rollback = false;
$err = null; $html = '';
try {
    $db->beginTransaction();
    $rollback = true;
    $now = date('Y-m-d H:i:s');
    $db->prepare("INSERT INTO pages (title, slug, status, blocks_json, created_at, updated_at) VALUES (?,?,?,?,?,?)")
       ->execute(['__diag_temp__', '__diag_temp__', 'draft', '[]', $now, $now]);
    $id = (int)$db->lastInsertId();
    $_GET['page'] = $id;

    ob_start();
    include $root . '/admin/page_designer.php';
    $html = ob_get_clean();
} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $err = get_class($e) . ': ' . $e->getMessage();
}
try { if ($rollback && $db->inTransaction()) { $db->rollBack(); } } catch (Throwable $e2) {}

echo "render_bytes=" . strlen($html) . "\n";
echo "render_error=" . ($err ?: 'none') . "\n";
echo "has_fatal=" . (stripos($html, 'Fatal error') !== false ? 'YES' : 'no') . "\n";
echo "has_gjs=" . (strpos($html, 'id="gjs"') !== false ? 'YES' : 'no') . "\n";
echo "has_blocks_pane=" . (strpos($html, 'id="pd-blocks"') !== false ? 'YES' : 'no') . "\n";
echo "has_pd_config=" . (strpos($html, 'window.PD_CONFIG') !== false ? 'YES' : 'no') . "\n";
echo "has_saveBtn=" . (strpos($html, 'id="pdSave"') !== false ? 'YES' : 'no') . "\n";
preg_match_all('~<script[^>]*>~', $html, $m);
foreach ($m[0] as $x) echo "script: " . trim($x) . "\n";
echo "pages_count_after_rollback=" . $db->query("SELECT COUNT(*) FROM pages")->fetchColumn() . "\n";
echo "DONE\n";
