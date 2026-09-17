<?php
/**
 * admin/media_api.php — Media Library API (list / upload / delete / folder)
 */
require_once __DIR__ . '/../config.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list': {
            $q = trim($_GET['q'] ?? '');
            $folder = trim($_GET['folder'] ?? '');
            $where = []; $params = [];
            if ($q !== '') { $where[] = '(name LIKE ? OR alt LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
            if ($folder !== '') { $where[] = 'folder = ?'; $params[] = $folder; }
            $sql = "SELECT id, path, name, alt, folder, size, mime, created_at FROM media";
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY id DESC LIMIT 200';
            $st = db()->prepare($sql);
            $st->execute($params);
            $items = [];
            foreach ($st->fetchAll() as $r) {
                $items[] = [
                    'id' => (int)$r['id'],
                    'url' => BASE_URL . '/' . $r['path'],
                    'name' => $r['name'],
                    'alt' => $r['alt'] ?? '',
                    'folder' => $r['folder'] ?? '',
                    'size' => (int)$r['size'],
                    'mime' => $r['mime'],
                    'created' => $r['created_at'],
                ];
            }
            echo json_encode($items);
            break;
        }
        case 'folders': {
            $st = db()->query("SELECT folder, COUNT(*) c FROM media WHERE folder IS NOT NULL AND folder <> '' GROUP BY folder ORDER BY folder");
            echo json_encode(array_map(fn($r) => ['name' => $r['folder'], 'count' => (int)$r['c']], $st->fetchAll()));
            break;
        }
        case 'upload': {
            if (!csrf_verify()) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }
            $folder = trim($_POST['folder'] ?? 'editor');
            $dir = __DIR__ . '/../uploads/' . preg_replace('/[^a-z0-9_-]/i', '', $folder);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $uploaded = []; $errors = [];
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif', 'pdf', 'mp4', 'webm'];
            foreach ($_FILES['files']['name'] ?? [] as $i => $name) {
                $err = $_FILES['files']['error'][$i];
                if ($err !== UPLOAD_ERR_OK) { $errors[] = "$name: $err"; continue; }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) { $errors[] = "$name: فرمت نامجاز"; continue; }
                if ($_FILES['files']['size'][$i] > 8 * 1024 * 1024) { $errors[] = "$name: بالای ۸MB"; continue; }
                $newName = pathinfo($name, PATHINFO_FILENAME) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $newName = preg_replace('/[^\w\.\-]/u', '_', $newName);
                $path = $dir . '/' . $newName;
                if (!move_uploaded_file($_FILES['files']['tmp_name'][$i], $path)) { $errors[] = "$name: ذخیره نشد"; continue; }
                $relPath = 'uploads/' . basename($dir) . '/' . $newName;
                try {
                    db()->prepare("INSERT INTO media (path, name, mime, size, folder, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
                       ->execute([$relPath, $name, $_FILES['files']['type'][$i], $_FILES['files']['size'][$i], basename($dir), $_SESSION['admin_id'] ?? null]);
                } catch (Throwable $t) { /* ignore */ }
                $uploaded[] = ['url' => BASE_URL . '/' . $relPath, 'name' => $name];
            }
            echo json_encode(['ok' => true, 'uploaded' => $uploaded, 'errors' => $errors]);
            break;
        }
        case 'delete': {
            if (!csrf_verify()) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }
            $id = (int)($_POST['id'] ?? 0);
            $st = db()->prepare("SELECT path FROM media WHERE id = ?");
            $st->execute([$id]); $r = $st->fetch();
            if ($r) {
                @unlink(__DIR__ . '/../' . $r['path']);
                db()->prepare("DELETE FROM media WHERE id = ?")->execute([$id]);
            }
            echo json_encode(['ok' => true]);
            break;
        }
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(['error' => $t->getMessage()]);
}
