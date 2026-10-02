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
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

if (!csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your session expired. Reload the page and try again.']);
    exit;
}

$messageId = (int) ($_POST['message_id'] ?? 0);
$db = get_db();

$stmt = $db->prepare('SELECT message_id, user_id, sender_role FROM chat_messages WHERE message_id = ?');
$stmt->execute([$messageId]);
$msg = $stmt->fetch();
if (!$msg) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Message not found.']);
    exit;
}

$isAdmin = ($user['role'] ?? '') === 'Admin';
$canDelete = $isAdmin
    ? $msg['sender_role'] === 'Admin'
    : ($msg['sender_role'] === 'User' && (int) $msg['user_id'] === (int) $user['user_id']);

if (!$canDelete) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You can only delete your own messages.']);
    exit;
}

$db->prepare('DELETE FROM chat_messages WHERE message_id = ?')->execute([$messageId]);

echo json_encode(['ok' => true]);
