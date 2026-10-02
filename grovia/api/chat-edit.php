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
$newMessage = trim($_POST['message'] ?? '');
if ($newMessage === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Message cannot be empty.']);
    exit;
}
if (mb_strlen($newMessage) > 2000) {
    $newMessage = mb_substr($newMessage, 0, 2000);
}

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
$canEdit = $isAdmin
    ? $msg['sender_role'] === 'Admin'
    : ($msg['sender_role'] === 'User' && (int) $msg['user_id'] === (int) $user['user_id']);

if (!$canEdit) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You can only edit your own messages.']);
    exit;
}

$db->prepare('UPDATE chat_messages SET message = ? WHERE message_id = ?')->execute([$newMessage, $messageId]);

echo json_encode(['ok' => true, 'message' => $newMessage]);
