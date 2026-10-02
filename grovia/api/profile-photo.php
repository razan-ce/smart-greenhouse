<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No photo was uploaded, or the upload failed.']);
    exit;
}

$file = $_FILES['photo'];

$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be 5MB or smaller.']);
    exit;
}

// Trust the actual file bytes, not the client-supplied MIME type/extension.
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$detectedType = mime_content_type($file['tmp_name']);
if (!isset($allowed[$detectedType])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be a JPEG, PNG, or WebP image.']);
    exit;
}
$ext = $allowed[$detectedType];

$db = get_db();

$uploadDir = __DIR__ . '/../uploads/profile-photos/';
$publicPathPrefix = 'uploads/profile-photos/';
$filename = 'user' . $user['user_id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save the uploaded photo.']);
    exit;
}

// Clean up the old photo file so uploads/ doesn't grow forever, but only if
// it's one of ours (a local uploads/profile-photos/ path) — never touch
// anything that might be an external URL or a shared/default asset.
$stmt = $db->prepare('SELECT profile_image FROM users WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$oldPath = $stmt->fetchColumn();
if ($oldPath && str_starts_with($oldPath, $publicPathPrefix)) {
    $oldFile = __DIR__ . '/../' . $oldPath;
    if (is_file($oldFile)) {
        @unlink($oldFile);
    }
}

$newPublicPath = $publicPathPrefix . $filename;
$db->prepare('UPDATE users SET profile_image = ? WHERE user_id = ?')->execute([$newPublicPath, $user['user_id']]);

$_SESSION['user']['profile_image'] = $newPublicPath;

echo json_encode(['ok' => true, 'profile_image' => $newPublicPath]);
