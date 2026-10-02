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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_check($input['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request, refresh the page and try again.']);
    exit;
}

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing detection id.']);
    exit;
}

$db = get_db();

$stmt = $db->prepare('SELECT image_path FROM disease_detections WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['user_id']]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Detection not found.']);
    exit;
}

$del = $db->prepare('DELETE FROM disease_detections WHERE id = ? AND user_id = ?');
$del->execute([$id, $user['user_id']]);

$imagePath = __DIR__ . '/../' . $row['image_path'];
if (is_file($imagePath)) {
    @unlink($imagePath);
}

echo json_encode(['ok' => true]);
