<?php
// Turns one sensor reading into an alert if it's outside a safe range —
// returns null when everything's fine, so callers only insert a
// notification when there's actually something to say.
function gh_evaluate_sensor(string $sensorType, float $value): ?array {
    switch ($sensorType) {
        case 'temperature':
            if ($value >= 38) return ['title' => 'Temperature Danger', 'message' => "Temperature is dangerously high ({$value}°C)."];
            if ($value >= 30) return ['title' => 'Temperature Warning', 'message' => "Temperature is too hot ({$value}°C)."];
            if ($value < 15) return ['title' => 'Temperature Warning', 'message' => "Temperature is too cold ({$value}°C)."];
            return null;

        case 'gas':
            if ($value >= 1000) return ['title' => 'Gas Level Danger', 'message' => "Gas level is dangerously high ({$value})."];
            return null;

        case 'water_level':
            if ($value < 10) return ['title' => 'Water Level Danger', 'message' => "Water tank is almost empty ({$value}%)."];
            if ($value < 20) return ['title' => 'Water Level Warning', 'message' => "Water tank is low ({$value}%)."];
            return null;

        case 'soil':
            if ($value == 0) return null;
            if ($value < 1200) return ['title' => 'Soil Warning', 'message' => "Soil is overwatered ({$value})."];
            if ($value > 2800) return ['title' => 'Soil Warning', 'message' => "Soil is too dry ({$value})."];
            return null;

        case 'humidity':
            if ($value < 30) return ['title' => 'Humidity Warning', 'message' => "Air is too dry ({$value}%)."];
            if ($value > 70) return ['title' => 'Humidity Warning', 'message' => "Air is too humid ({$value}%)."];
            return null;

        default:
            return null;
    }
}

//delte if older tha one day 
function gh_sweep_old_notifications(PDO $db): void {
    $db->exec('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
}



function gh_check_and_notify(PDO $db, int $userId, array $readings): void {
    gh_sweep_old_notifications($db);
    foreach ($readings as $sensorType => $value) {
        if ($value === null) continue;

        $alert = gh_evaluate_sensor($sensorType, (float) $value);
        if ($alert === null) continue;

        $check = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = ? AND is_read = 0");
        $check->execute([$userId, $alert['title']]);
        if ((int) $check->fetchColumn() > 0) continue;

        $insert = $db->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)');
        $insert->execute([$userId, $alert['title'], $alert['message'], 'Sensor']);
    }
}





function gh_check_connection_change(PDO $db, int $userId, bool $reallyConnected): void {
    $wasConnected = (bool) $db->query('SELECT esp32_connected FROM device_state WHERE id = 1')->fetchColumn();
    if ($reallyConnected === $wasConnected) {
        return;
    }

    $db->prepare('UPDATE device_state SET esp32_connected = ? WHERE id = 1')->execute([$reallyConnected ? 1 : 0]);
    gh_sweep_old_notifications($db);

    $title = $reallyConnected ? 'ESP32 Connected' : 'ESP32 Disconnected';
    $message = $reallyConnected
        ? 'Your ESP32 device just came back online.'
        : 'Your ESP32 device has gone offline — no readings received in the last few seconds.';

    $db->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)')
        ->execute([$userId, $title, $message, 'System']);
}

function gh_check_watering_reminder(PDO $db, int $userId): void {
    $schedule = $db->query('SELECT enabled, scheduled_time FROM irrigation_schedule WHERE id = 1')->fetch();
    if (!$schedule || !$schedule['enabled']) {
        return;
    }

    $now = new DateTime();
    $scheduledToday = new DateTime($now->format('Y-m-d') . ' ' . $schedule['scheduled_time']);
    $secondsSinceScheduled = $now->getTimestamp() - $scheduledToday->getTimestamp();
    if ($secondsSinceScheduled < 0 || $secondsSinceScheduled > 120) {
        return; // not within the 2-minute window right after the scheduled time
    }

    $alreadySentToday = $db->prepare(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'Scheduled Watering' AND DATE(created_at) = CURDATE()"
    );
    $alreadySentToday->execute([$userId]);
    if ((int) $alreadySentToday->fetchColumn() > 0) {
        return;
    }

    $db->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)')
        ->execute([$userId, 'Scheduled Watering', 'The daily watering schedule is running now.', 'System']);
}

// A schedule task's own time has arrived — notify once, then mark it so it
// never reminds twice for the same task.
function gh_check_schedule_reminders(PDO $db, int $userId): void {
    $stmt = $db->prepare(
        "SELECT task_id, title FROM schedule_tasks "
        . "WHERE user_id = ? AND status = 'Pending' AND reminder_sent = 0 "
        . "AND CONCAT(scheduled_date, ' ', scheduled_time) <= NOW()"
    );
    $stmt->execute([$userId]);
    $dueTasks = $stmt->fetchAll();

    foreach ($dueTasks as $task) {
        $db->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)')
            ->execute([$userId, 'Task Reminder', "It's time for: {$task['title']}", 'System']);
        $db->prepare('UPDATE schedule_tasks SET reminder_sent = 1 WHERE task_id = ?')->execute([$task['task_id']]);
    }
}
