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

$isAdmin = ($user['role'] ?? '') === 'Admin';
$db = get_db();

if ($isAdmin) {
    $threadUserId = (int) ($_GET['user_id'] ?? 0);
    $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'User'");
    $stmt->execute([$threadUserId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'That user was not found.']);
        exit;
    }
    $db->prepare("UPDATE chat_messages SET is_read_by_admin = 1 WHERE user_id = ? AND sender_role = 'User' AND is_read_by_admin = 0")
        ->execute([$threadUserId]);
    // Presence heartbeat — see api/chat-threads.php for why.
    $db->prepare('UPDATE users SET last_seen = NOW() WHERE user_id = ?')->execute([$user['user_id']]);
} else {
    $threadUserId = (int) $user['user_id'];
    $db->prepare("UPDATE chat_messages SET is_read_by_user = 1 WHERE user_id = ? AND sender_role = 'Admin' AND is_read_by_user = 0")
        ->execute([$threadUserId]);
}

$stmt = $db->prepare('SELECT message_id, sender_role, message, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC LIMIT 200');
$stmt->execute([$threadUserId]);

echo json_encode(['ok' => true, 'messages' => $stmt->fetchAll()]);
