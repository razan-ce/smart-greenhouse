<?php

function gh_log_admin_action(PDO $db, int $adminId, string $action, string $targetTable, ?int $targetId, string $description): void {
    $stmt = $db->prepare('INSERT INTO admin_activity_log (admin_id, action, target_table, target_id, description) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$adminId, $action, $targetTable, $targetId, $description]);
}

function gh_toggle_greenhouse_access(PDO $db, array $target, int $actingAdminId): array {
    if ($target['role'] === 'Admin') {
        return ['ok' => false, 'text' => 'Admins always have access — nothing to grant or revoke.'];
    }

    $stmt = $db->prepare('SELECT 1 FROM greenhouse_access WHERE user_id = ? LIMIT 1');
    $stmt->execute([$target['user_id']]);
    $hasAccess = (bool) $stmt->fetchColumn();

    if ($hasAccess) {
        $db->prepare('DELETE FROM greenhouse_access WHERE user_id = ?')->execute([$target['user_id']]);
        gh_log_admin_action($db, $actingAdminId, 'Revoke Greenhouse Access', 'users', (int) $target['user_id'], "Revoked greenhouse access for {$target['email']}");
        return ['ok' => true, 'text' => "Access revoked for {$target['email']}."];
    }

    $db->prepare('INSERT INTO greenhouse_access (user_id, granted_by) VALUES (?, ?)')->execute([$target['user_id'], $actingAdminId]);
    gh_log_admin_action($db, $actingAdminId, 'Grant Greenhouse Access', 'users', (int) $target['user_id'], "Granted greenhouse access to {$target['email']}");
    $db->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)')->execute([
        $target['user_id'],
        'Access Granted',
        "You've been granted access to the greenhouse — you can now use the dashboard, sensors, and controls.",
        'System',
    ]);
    return ['ok' => true, 'text' => "Access granted for {$target['email']}."];
}
