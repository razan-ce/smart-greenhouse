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

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Message is empty.']);
    exit;
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

$isAdmin = ($user['role'] ?? '') === 'Admin';
$db = get_db();

if ($isAdmin) {
    $threadUserId = (int) ($_POST['user_id'] ?? 0);
    $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'User'");
    $stmt->execute([$threadUserId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'That user was not found.']);
        exit;
    }
    $senderRole = 'Admin';
} else {
    $threadUserId = (int) $user['user_id'];
    $senderRole = 'User';
}

// Whoever wrote it has obviously "read" it; the other side hasn't yet.
$readByUser = $senderRole === 'User' ? 1 : 0;
$readByAdmin = $senderRole === 'Admin' ? 1 : 0;

$stmt = $db->prepare('INSERT INTO chat_messages (user_id, sender_role, message, is_read_by_user, is_read_by_admin) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$threadUserId, $senderRole, $message, $readByUser, $readByAdmin]);

$stmt = $db->prepare('SELECT message_id, sender_role, message, created_at FROM chat_messages WHERE message_id = ?');
$stmt->execute([$db->lastInsertId()]);

echo json_encode(['ok' => true, 'message' => $stmt->fetch()]);
