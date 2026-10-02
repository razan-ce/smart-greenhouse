<?php

$email = $user['email'];
if (!empty($user['full_name'])) {
    $display_name = $user['full_name'];
} else {
    $name_part = strstr($email, '@', true);
    if ($name_part === false) { $name_part = $email; }
    $display_name = ucwords(str_replace(['.', '_', '-'], ' ', $name_part));
}

function gh_initials(string $name): string {//this the ra if no
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}
$initials = gh_initials($display_name);
$profile_image_url = !empty($user['profile_image']) ? htmlspecialchars($user['profile_image'], ENT_QUOTES) : null;
