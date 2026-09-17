<?php
/**
 * admin/upload_image.php — endpoint آپلود تصویر برای TinyMCE
 * خروجی: { location: "https://..." } یا { error: "..." }
 */
require_once __DIR__ . '/../config.php';
require_admin();

if (!csrf_verify()) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }

if (empty($_FILES['file'])) { http_response_code(400); echo json_encode(['error' => 'فایلی ارسال نشد']); exit; }

$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(['error' => 'خطای آپلود: ' . $f['error']]); exit; }

$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) { http_response_code(400); echo json_encode(['error' => 'فرمت مجاز نیست']); exit; }

if ($f['size'] > 8 * 1024 * 1024) { http_response_code(400); echo json_encode(['error' => 'حجم بالای ۸ مگابایت']); exit; }

$dir = __DIR__ . '/../uploads/editor';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$name = 'ed-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$path = $dir . '/' . $name;

if (!move_uploaded_file($f['tmp_name'], $path)) { http_response_code(500); echo json_encode(['error' => 'ذخیره نشد']); exit; }

// ثبت در جدول media
try {
    db()->prepare("INSERT INTO media (path, name, mime, size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
       ->execute(['uploads/editor/' . $name, $name, $f['type'], $f['size'], $_SESSION['admin_id'] ?? null]);
} catch (Throwable $t) { /* جدول ممکن است هنوز نباشد؛ بی‌خیال */ }

echo json_encode(['location' => BASE_URL . '/uploads/editor/' . $name]);
