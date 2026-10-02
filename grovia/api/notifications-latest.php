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

$db = get_db();

$countStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$countStmt->execute([$user['user_id']]);
$unreadCount = (int) $countStmt->fetchColumn();

$listStmt = $db->prepare(
    'SELECT notification_id, title, message, type, is_read, created_at '
    . 'FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15'
);
$listStmt->execute([$user['user_id']]);
$notifications = $listStmt->fetchAll();

echo json_encode([
    'ok' => true,
    'unread_count' => $unreadCount,
    'notifications' => $notifications,
]);
