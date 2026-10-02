<?php
// Shared relative-time formatting, used across the admin pages.
function gh_format_last_seen(?string $lastSeen): string {
    if (!$lastSeen) {
        return 'Never';
    }
    $diff = time() - strtotime($lastSeen);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return date('M j, g:ia', strtotime($lastSeen));
}
