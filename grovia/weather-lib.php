<?php
// Shared weather-display helpers for Beirut, Lebanon — mirrors the ones in
// the greenhouse project so both dashboards render the same design.

function gh_weather_code_icon(int $code): string {
    if ($code === 0 || $code === 1) return 'sun';
    if ($code === 2) return 'cloud-sun';
    if ($code === 3 || $code === 45 || $code === 48) return 'cloud';
    if (in_array($code, [51, 53, 55, 56, 57], true)) return 'cloud-drizzle';
    if (in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true)) return 'cloud-rain';
    if (in_array($code, [71, 73, 75, 77, 85, 86], true)) return 'snowflake';
    if (in_array($code, [95, 96, 99], true)) return 'cloud-lightning';
    return 'cloud';
}

function gh_weather_code_label(int $code): string {
    $map = [
        0 => 'Clear sky', 1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
        45 => 'Fog', 48 => 'Depositing rime fog',
        51 => 'Light drizzle', 53 => 'Drizzle', 55 => 'Dense drizzle',
        56 => 'Freezing drizzle', 57 => 'Freezing drizzle',
        61 => 'Light rain', 63 => 'Rain', 65 => 'Heavy rain',
        66 => 'Freezing rain', 67 => 'Freezing rain',
        71 => 'Light snow', 73 => 'Snow', 75 => 'Heavy snow', 77 => 'Snow grains',
        80 => 'Rain showers', 81 => 'Rain showers', 82 => 'Violent rain showers',
        85 => 'Snow showers', 86 => 'Snow showers',
        95 => 'Thunderstorm', 96 => 'Thunderstorm with hail', 99 => 'Thunderstorm with hail',
    ];
    return $map[$code] ?? 'Mixed conditions';
}

/**
 * Maps a temperature to a "how it feels" band for the Today panel: a label,
 * a text color, a soft background glow, a marker color, and where it sits
 * (0-100%) on a 5°C-42°C gauge.
 */
function gh_temp_feel(float $temp): array {
    $min = 5.0; $max = 42.0;
    $pct = max(0, min(100, (($temp - $min) / ($max - $min)) * 100));

    if ($temp >= 34) {
        return ['label' => 'Scorching', 'text' => 'text-red-500', 'glow' => 'rgba(239,68,68,0.14)', 'dot' => '#EF4444', 'pct' => round($pct, 1)];
    }
    if ($temp >= 28) {
        return ['label' => 'Hot', 'text' => 'text-amber-500', 'glow' => 'rgba(245,158,11,0.14)', 'dot' => '#F59E0B', 'pct' => round($pct, 1)];
    }
    if ($temp >= 20) {
        return ['label' => 'Pleasant', 'text' => 'text-primary', 'glow' => 'rgba(34,197,94,0.13)', 'dot' => '#22C55E', 'pct' => round($pct, 1)];
    }
    if ($temp >= 10) {
        return ['label' => 'Cool', 'text' => 'text-sky-500', 'glow' => 'rgba(14,165,233,0.13)', 'dot' => '#0EA5E9', 'pct' => round($pct, 1)];
    }
    return ['label' => 'Cold', 'text' => 'text-blue-600', 'glow' => 'rgba(37,99,235,0.14)', 'dot' => '#2563EB', 'pct' => round($pct, 1)];
}
