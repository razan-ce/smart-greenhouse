<?php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admins only.']);
    exit;
}

$db = get_db();


$db->prepare('UPDATE users SET last_seen = NOW() WHERE user_id = ?')->execute([$user['user_id']]);

$rows = $db->query(
    "SELECT u.user_id, u.full_name, u.email, u.profile_image,
            lm.message AS last_message, lm.created_at AS last_message_at, lm.sender_role AS last_sender_role,
            (SELECT COUNT(*) FROM chat_messages cm WHERE cm.user_id = u.user_id AND cm.sender_role = 'User' AND cm.is_read_by_admin = 0) AS unread_count
     FROM users u
     LEFT JOIN chat_messages lm ON lm.message_id = (
         SELECT cm2.message_id FROM chat_messages cm2 WHERE cm2.user_id = u.user_id ORDER BY cm2.created_at DESC LIMIT 1
     )
     WHERE u.role = 'User' AND lm.message_id IS NOT NULL
     ORDER BY (unread_count > 0) DESC, lm.created_at DESC"
)->fetchAll();

echo json_encode(['ok' => true, 'threads' => $rows]);
