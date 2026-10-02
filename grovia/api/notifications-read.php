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
$input = $_POST;
if (empty($input)) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
$db = get_db();

if (!empty($input['mark_all'])) {
    
    $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['user_id']]);
} elseif (!empty($input['notification_id'])) {
    $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
    $stmt->execute([(int) $input['notification_id'], $user['user_id']]);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing notification_id or mark_all.']);
    exit;
}

echo json_encode(['ok' => true]);