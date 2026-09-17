<?php
/**
 * admin/page_design_api.php — API ویرایشگر بصری صفحه (GrapesJS)
 * ------------------------------------------------------------------
 * فقط برای مدیر. عملیات خواندن با require_admin و عملیات نوشتن با CSRF.
 * اکشن‌ها:
 *   GET  ?action=list                 → فهرست صفحات + داشتن طرح
 *   GET  ?action=get&id=ID            → صفحه + طرح + دارایی‌ها
 *   POST action=create                → ساخت صفحهٔ جدید (title, slug, status)
 *   POST action=save                  → ذخیرهٔ طرح (page_id, project, html, css)
 *   POST action=meta                  → ویرایش عنوان/آدرس/وضعیت صفحه
 *   POST action=delete_design         → حذف طرح (صفحه می‌ماند)
 *   POST action=delete_page           → حذف کامل صفحه (+ بلوک‌ها + طرح)
 */
require_once __DIR__ . '/../config.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function pd_json($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pd_require_csrf(): void
{
    if (!csrf_verify()) {
        pd_json(['error' => 'CSRF token نامعتبر است. صفحه را دوباره بارگذاری کنید.'], 403);
    }
}

/** slug یکتا بساز (در صورت تکرار، پسوند عددی) */
function pd_unique_slug(string $slug, int $ignoreId = 0): string
{
    $slug = $slug !== '' ? $slug : 'page';
    $base = $slug;
    $i = 2;
    while (true) {
        $st = db()->prepare("SELECT id FROM pages WHERE slug = ? AND id != ? LIMIT 1");
        $st->execute([$slug, $ignoreId]);
        if (!$st->fetchColumn()) return $slug;
        $slug = $base . '-' . $i;
        $i++;
        if ($i > 500) return $base . '-' . time();
    }
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$now = date('Y-m-d H:i:s');

try {
    switch ($action) {

        case 'list': {
            $rows = db()->query("SELECT id, title, slug, status, updated_at FROM pages ORDER BY id DESC")->fetchAll();
            $designIds = [];
            try {
                $designIds = db()->query("SELECT page_id FROM page_designs")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Throwable $e) { /* جدول نیست */ }
            $designIds = array_map('intval', $designIds);
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id' => (int)$r['id'],
                    'title' => (string)$r['title'],
                    'slug' => (string)$r['slug'],
                    'status' => (string)$r['status'],
                    'updated_at' => (string)$r['updated_at'],
                    'has_design' => in_array((int)$r['id'], $designIds, true),
                ];
            }
            pd_json(['ok' => true, 'pages' => $out]);
        }

        case 'get': {
            $id = (int)($_GET['id'] ?? 0);
            $st = db()->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $page = $st->fetch();
            if (!$page) pd_json(['error' => 'صفحه یافت نشد.'], 404);
            $design = function_exists('get_page_design') ? get_page_design($id) : null;
            pd_json(['ok' => true, 'page' => [
                'id' => (int)$page['id'],
                'title' => (string)$page['title'],
                'slug' => (string)$page['slug'],
                'status' => (string)$page['status'],
            ], 'design' => $design ? [
                'project_json' => $design['project_json'],
                'html' => $design['html'],
                'css' => $design['css'],
                'updated_at' => $design['updated_at'],
            ] : null]);
        }

        case 'create': {
            pd_require_csrf();
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') pd_json(['error' => 'عنوان صفحه الزامی است.'], 422);
            $slug = trim((string)($_POST['slug'] ?? ''));
            if ($slug === '') $slug = to_slug($title);
            $slug = pd_unique_slug($slug);
            $status = in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : 'draft';
            db()->prepare("INSERT INTO pages (title, slug, status, blocks_json, created_at, updated_at) VALUES (?,?,?,?,?,?)")
                ->execute([$title, $slug, $status, '[]', $now, $now]);
            $id = (int)db()->lastInsertId();
            pd_json(['ok' => true, 'id' => $id, 'slug' => $slug, 'status' => $status]);
        }

        case 'save': {
            pd_require_csrf();
            $pageId = (int)($_POST['page_id'] ?? 0);
            if ($pageId <= 0) pd_json(['error' => 'شناسه صفحه نامعتبر است.'], 422);
            $chk = db()->prepare("SELECT id FROM pages WHERE id = ? LIMIT 1");
            $chk->execute([$pageId]);
            if (!$chk->fetchColumn()) pd_json(['error' => 'صفحه یافت نشد.'], 404);

            $project = (string)($_POST['project'] ?? '');
            $html = (string)($_POST['html'] ?? '');
            $css = (string)($_POST['css'] ?? '');
            if ($project !== '' && json_decode($project, true) === null) {
                pd_json(['error' => 'ساختار پروژهٔ ارسالی معتبر نیست.'], 422);
            }

            $st = db()->prepare("SELECT id FROM page_designs WHERE page_id = ? LIMIT 1");
            $st->execute([$pageId]);
            $existing = $st->fetchColumn();
            if ($existing) {
                db()->prepare("UPDATE page_designs SET project_json = ?, html = ?, css = ?, updated_at = ? WHERE page_id = ?")
                    ->execute([$project, $html, $css, $now, $pageId]);
            } else {
                db()->prepare("INSERT INTO page_designs (page_id, project_json, html, css, updated_at) VALUES (?,?,?,?,?)")
                    ->execute([$pageId, $project, $html, $css, $now]);
            }
            db()->prepare("UPDATE pages SET updated_at = ? WHERE id = ?")->execute([$now, $pageId]);
            pd_json(['ok' => true, 'saved_at' => $now, 'bytes' => ['project' => strlen($project), 'html' => strlen($html), 'css' => strlen($css)]]);
        }

        case 'meta': {
            pd_require_csrf();
            $pageId = (int)($_POST['page_id'] ?? 0);
            $st = db()->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
            $st->execute([$pageId]);
            $page = $st->fetch();
            if (!$page) pd_json(['error' => 'صفحه یافت نشد.'], 404);

            $title = trim((string)($_POST['title'] ?? $page['title']));
            if ($title === '') $title = $page['title'];
            $slug = trim((string)($_POST['slug'] ?? $page['slug']));
            if ($slug === '') $slug = $page['slug'];
            $slug = pd_unique_slug($slug, $pageId);
            $status = in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : $page['status'];

            db()->prepare("UPDATE pages SET title = ?, slug = ?, status = ?, updated_at = ? WHERE id = ?")
                ->execute([$title, $slug, $status, $now, $pageId]);
            pd_json(['ok' => true, 'title' => $title, 'slug' => $slug, 'status' => $status]);
        }

        case 'delete_design': {
            pd_require_csrf();
            $pageId = (int)($_POST['page_id'] ?? 0);
            try {
                db()->prepare("DELETE FROM page_designs WHERE page_id = ?")->execute([$pageId]);
            } catch (Throwable $e) {
                pd_json(['error' => 'جدول طرح یافت نشد.'], 500);
            }
            pd_json(['ok' => true]);
        }

        case 'delete_page': {
            pd_require_csrf();
            $pageId = (int)($_POST['page_id'] ?? 0);
            if ($pageId <= 0) pd_json(['error' => 'شناسه صفحه نامعتبر است.'], 422);
            db()->prepare("DELETE FROM page_blocks WHERE page_id = ?")->execute([$pageId]);
            try { db()->prepare("DELETE FROM page_designs WHERE page_id = ?")->execute([$pageId]); } catch (Throwable $e) {}
            db()->prepare("DELETE FROM pages WHERE id = ?")->execute([$pageId]);
            pd_json(['ok' => true]);
        }

        default:
            pd_json(['error' => 'اکشن ناشناخته.', 'action' => $action], 400);
    }
} catch (Throwable $e) {
    // جزئیات خطا فقط در لاگ سرور ثبت می‌شود؛ به کاربر پیام کوتاه برمی‌گردد.
    error_log('[page_design_api] ' . $e->getMessage());
    pd_json(['error' => 'خطای داخلی سرور. جزئیات در لاگ ثبت شد.'], 500);
}
