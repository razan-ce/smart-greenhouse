<?php
date_default_timezone_set('Asia/Beirut');

// No caching — this page's inline JS keeps changing during development,
// and a stale cached copy makes it look like fixes "aren't working."
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_worker_access();
if ($user['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
require_once __DIR__ . '/partials/current-user.php';

// Real count from the database now that this project is connected to
// smart_greenhouse — everything else on this page is still just design.
$stmt = get_db()->prepare('SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$user['user_id']]);
$activeAlertsCount = (int) ($stmt->fetch()['n'] ?? 0);

// Beirut local time, so the greeting matches the real time of day.
$hour = (int) date('G');
if ($hour >= 5 && $hour < 12) { $greeting = 'Good morning'; }
elseif ($hour >= 12 && $hour < 18) { $greeting = 'Good afternoon'; }
else { $greeting = 'Good evening'; }

// Real live weather for Beirut (Open-Meteo, no API key needed). Short
// timeout + graceful fallback — if the request fails, fall back to a
// climatologically plausible static forecast rather than making up numbers.
require_once __DIR__ . '/weather-lib.php';

$weather_forecast = [
    ['day' => 'Today', 'date' => date('M j'),                  'icon' => 'sun',       'high' => 32, 'low' => 25, 'precip' => 0],
    ['day' => date('D', strtotime('+1 day')), 'date' => date('M j', strtotime('+1 day')), 'icon' => 'sun',       'high' => 33, 'low' => 25, 'precip' => 0],
    ['day' => date('D', strtotime('+2 day')), 'date' => date('M j', strtotime('+2 day')), 'icon' => 'cloud-sun', 'high' => 32, 'low' => 26, 'precip' => 0],
    ['day' => date('D', strtotime('+3 day')), 'date' => date('M j', strtotime('+3 day')), 'icon' => 'sun',       'high' => 33, 'low' => 26, 'precip' => 0],
    ['day' => date('D', strtotime('+4 day')), 'date' => date('M j', strtotime('+4 day')), 'icon' => 'sun',       'high' => 32, 'low' => 25, 'precip' => 0],
];
$weather_today_detail = [
    'temp' => 31.5, 'feelsLike' => 34.0, 'humidity' => 62, 'windSpeed' => 11.0,
    'label' => 'Clear sky', 'icon' => 'sun', 'fullDate' => date('l, F j'), 'precip' => 0,
];
$weather_is_live = false;
$beirutTemp = null; // still used by the small Live Sensors temperature card

$weatherCtx = stream_context_create(['http' => ['timeout' => 4]]);
$weatherJson = @file_get_contents(
    'https://api.open-meteo.com/v1/forecast?latitude=33.8938&longitude=35.5018'
    . '&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m'
    . '&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max'
    . '&timezone=Asia%2FBeirut&forecast_days=5',
    false,
    $weatherCtx
);
if ($weatherJson !== false) {
    $weatherData = json_decode($weatherJson, true);
    if (isset($weatherData['current']['temperature_2m']) && isset($weatherData['daily']['time'])) {
        $current = $weatherData['current'];
        $daily = $weatherData['daily'];

        $forecast = [];
        for ($i = 0; $i < count($daily['time']) && $i < 5; $i++) {
            $ts = strtotime($daily['time'][$i]);
            $forecast[] = [
                'day'    => $i === 0 ? 'Today' : date('D', $ts),
                'date'   => date('M j', $ts),
                'icon'   => gh_weather_code_icon((int) $daily['weather_code'][$i]),
                'high'   => (int) round($daily['temperature_2m_max'][$i]),
                'low'    => (int) round($daily['temperature_2m_min'][$i]),
                'precip' => (int) round($daily['precipitation_probability_max'][$i] ?? 0),
            ];
        }

        $weather_forecast = $forecast;
        $weather_today_detail = [
            'temp'      => round((float) $current['temperature_2m'], 1),
            'feelsLike' => round((float) $current['apparent_temperature'], 1),
            'humidity'  => (int) round($current['relative_humidity_2m']),
            'windSpeed' => round((float) $current['wind_speed_10m'], 1),
            'label'     => gh_weather_code_label((int) $current['weather_code']),
            'icon'      => gh_weather_code_icon((int) $current['weather_code']),
            'fullDate'  => date('l, F j'),
            'precip'    => $forecast[0]['precip'] ?? 0,
        ];
        $weather_is_live = true;
        $beirutTemp = $weather_today_detail['temp'];
    }
}

$gh_page_title = 'Dashboard | Grovia';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1600px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-8">

      <!-- Hero -->
      <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-[28px] font-semibold tracking-tight text-text-primary sm:text-[32px]"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($display_name) ?></h1>
          <p class="mt-1.5 text-[15px] text-text-secondary">Here's what's happening in your greenhouse today.</p>
        </div>

        <div class="flex gap-3 sm:gap-4">
          <div class="flex min-w-[104px] items-center gap-3 rounded-2xl border border-border/60 bg-white px-4 py-3 shadow-soft transition-all duration-200 hover:-translate-y-0.5 hover:shadow-soft-lg">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-light">
              <i data-lucide="alert-triangle" class="h-4 w-4 text-secondary" stroke-width="2.25"></i>
            </div>
            <div class="flex flex-col leading-tight">
              <span class="text-sm font-semibold text-text-primary"><?= $activeAlertsCount ?></span>
              <span class="whitespace-nowrap text-[11px] text-text-secondary">Active Alerts</span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-12 gap-5">

        <!-- Live Greenhouse Overview -->
        <div class="col-span-12">
          <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-white shadow-soft-lg">
            <div class="grid grid-cols-1 lg:grid-cols-5">
              <div class="relative z-10 flex flex-col justify-between gap-8 p-7 sm:p-9 lg:col-span-2">
                <div>
                  <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-primary/15 bg-light/70 px-3 py-1.5">
                    <span class="relative flex h-2 w-2 text-primary">
                      <span class="pulse-dot absolute inline-flex h-full w-full rounded-full opacity-60"></span>
                      <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                    </span>
                    <span class="text-xs font-semibold text-secondary">All Systems Operational</span>
                  </div>
                  <h2 class="text-2xl font-semibold tracking-tight text-text-primary">Live Greenhouse Overview</h2>
                  <p class="mt-2 text-sm leading-relaxed text-text-secondary">Real-time telemetry from your greenhouse's connected sensors.</p>
                </div>

                <button type="button" class="group inline-flex w-fit items-center gap-2 rounded-xl border border-border bg-white px-4 py-2 text-sm font-medium text-text-primary transition-all hover:bg-neutral-50 hover:border-neutral-300 active:scale-[0.98]">
                  View Full Diagnostics
                  <i data-lucide="arrow-up-right" class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"></i>
                </button>
              </div>

              <div class="relative min-h-[320px] overflow-hidden bg-gradient-to-br from-[#F7FEF9] to-[#EFFDF4] lg:col-span-3 lg:min-h-[420px]">
                <img src="assets/hardware.jpg" alt="Smart greenhouse hardware: sensors, irrigation pump, water tank, and climate controls" class="absolute inset-0 h-full w-full object-cover" />
                <div class="absolute right-6 top-6 z-10 hidden items-center gap-1.5 rounded-full border border-border/60 bg-white/80 px-3 py-1.5 backdrop-blur sm:flex">
                  <i data-lucide="check-circle-2" class="h-3.5 w-3.5 text-primary"></i>
                  <span class="text-[11px] font-medium text-text-secondary">Updated just now</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- AI Insights — proactive Gemini-generated summary of the past week's
             sensor trends, watering activity, and AI Camera checks. Cached in
             ai_insights for 24h per user (or on manual refresh) so it doesn't
             call the API on every dashboard load. -->
        <div class="col-span-12">
          <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-7" id="insightCard">
            <div class="flex items-center justify-between gap-3">
              <div class="inline-flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-light">
                  <i data-lucide="sparkles" class="h-4 w-4 text-secondary"></i>
                </div>
                <h3 class="text-sm font-semibold text-text-primary">AI Insights</h3>
              </div>
              <div class="flex items-center gap-3">
                <span class="hidden text-[11px] text-text-secondary sm:inline" id="insightUpdated"></span>
                <button type="button" id="insightRefreshBtn" class="flex h-8 w-8 items-center justify-center rounded-xl border border-border/70 text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary" aria-label="Refresh insight">
                  <i data-lucide="refresh-cw" class="h-3.5 w-3.5" id="insightRefreshIcon"></i>
                </button>
              </div>
            </div>

            <div id="insightLoading" class="mt-4 flex items-center gap-2 text-xs text-text-secondary">
              <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i> Analyzing your greenhouse's recent activity…
            </div>

            <div id="insightContent" class="mt-4 hidden">
              <div class="flex items-start gap-2.5">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full" id="insightTrendIconWrap">
                  <i data-lucide="check-circle-2" class="h-3.5 w-3.5" id="insightTrendIcon"></i>
                </span>
                <p class="text-[15px] font-semibold text-text-primary" id="insightHeadline">—</p>
              </div>
              <p class="mt-2 text-sm leading-relaxed text-text-secondary" id="insightSummary"></p>
              <div class="mt-3 rounded-2xl bg-light p-4">
                <p class="flex items-center gap-1.5 text-xs font-semibold text-secondary"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Recommendation</p>
                <p class="mt-1.5 text-sm text-text-primary" id="insightRecommendation"></p>
              </div>
            </div>

            <div id="insightError" class="mt-4 hidden text-xs text-text-secondary"></div>
          </div>
        </div>

        <!-- Live Sensors — polled from sensor_readings, filled in by
             your ESP32 hitting api/sensor-ingest.php. Shows "No Data" until
             the very first real reading arrives, never a guessed number. -->
        <div class="col-span-12">
          <h3 class="mb-3 text-base font-semibold text-text-primary">Live Sensors</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            <div class="relative overflow-hidden rounded-2xl border border-border/60 bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:shadow-soft-lg">
              <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100" id="gasIconWrap">
                  <i data-lucide="wind" class="h-[18px] w-[18px] text-neutral-400" stroke-width="2.25"></i>
                </div>
                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500" id="gasBadge">No Data</span>
              </div>
              <div class="mt-4 flex items-baseline gap-1">
                <span class="text-[28px] font-semibold leading-none tracking-tight text-neutral-300" id="gasValue">--</span>
              </div>
              <p class="mt-1 text-sm text-text-secondary">Gas Sensor (MQ-135)</p>
              <p class="mt-1 text-xs font-semibold" id="gasStatus"></p>
              <p class="mt-2 text-xs text-text-secondary" id="gasTime">Waiting for the ESP32's first reading.</p>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-border/60 bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:shadow-soft-lg">
              <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100" id="lightIconWrap">
                  <i data-lucide="sun" class="h-[18px] w-[18px] text-neutral-400" stroke-width="2.25"></i>
                </div>
                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500" id="lightBadge">No Data</span>
              </div>
              <div class="mt-4 flex items-baseline gap-1">
                <span class="text-[28px] font-semibold leading-none tracking-tight text-neutral-300" id="lightValue">--</span>
              </div>
              <p class="mt-1 text-sm text-text-secondary">Light Sensor (HW-028)</p>
              <p class="mt-1 text-xs font-semibold" id="lightStatus"></p>
              <p class="mt-2 text-xs text-text-secondary" id="lightTime">Waiting for the ESP32's first reading.</p>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-border/60 bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:shadow-soft-lg">
              <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100" id="soilIconWrap">
                  <i data-lucide="sprout" class="h-[18px] w-[18px] text-neutral-400" stroke-width="2.25"></i>
                </div>
                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500" id="soilBadge">No Data</span>
              </div>
              <div class="mt-4 flex items-baseline gap-1">
                <span class="text-[28px] font-semibold leading-none tracking-tight text-neutral-300" id="soilValue">--</span>
              </div>
              <p class="mt-1 text-sm text-text-secondary">Soil Sensor (HW-080)</p>
              <p class="mt-1 text-xs font-semibold" id="soilStatus"></p>
              <p class="mt-2 text-xs text-text-secondary" id="soilTime">Waiting for the ESP32's first reading.</p>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-border/60 bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:shadow-soft-lg">
              <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100" id="temperatureIconWrap">
                  <i data-lucide="thermometer" class="h-[18px] w-[18px] text-neutral-400" stroke-width="2.25"></i>
                </div>
                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500" id="temperatureBadge">No Data</span>
              </div>
              <div class="mt-4 flex items-baseline gap-1">
                <span class="text-[28px] font-semibold leading-none tracking-tight text-neutral-300" id="temperatureValue">--</span>
              </div>
              <p class="mt-1 text-sm text-text-secondary">Temperature (DHT11)</p>
              <p class="mt-1 text-xs font-semibold" id="temperatureStatus"></p>
              <p class="mt-2 text-xs text-text-secondary" id="temperatureTime">Waiting for the ESP32's first reading.</p>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-border/60 bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:shadow-soft-lg">
              <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100" id="humidityIconWrap">
                  <i data-lucide="droplets" class="h-[18px] w-[18px] text-neutral-400" stroke-width="2.25"></i>
                </div>
                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500" id="humidityBadge">No Data</span>
              </div>
              <div class="mt-4 flex items-baseline gap-1">
                <span class="text-[28px] font-semibold leading-none tracking-tight text-neutral-300" id="humidityValue">--</span>
              </div>
              <p class="mt-1 text-sm text-text-secondary">Humidity (DHT11)</p>
              <p class="mt-1 text-xs font-semibold" id="humidityStatus"></p>
              <p class="mt-2 text-xs text-text-secondary" id="humidityTime">Waiting for the ESP32's first reading.</p>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-border/60 bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:shadow-soft-lg">
              <div class="flex items-start justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100" id="waterLevelIconWrap">
                  <i data-lucide="waves" class="h-[18px] w-[18px] text-neutral-400" stroke-width="2.25"></i>
                </div>
                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500" id="waterLevelBadge">No Data</span>
              </div>
              <div class="mt-4 flex items-baseline gap-1">
                <span class="text-[28px] font-semibold leading-none tracking-tight text-neutral-300" id="waterLevelValue">--</span>
              </div>
              <p class="mt-1 text-sm text-text-secondary">Water Level</p>
              <p class="mt-1 text-xs font-semibold" id="waterLevelStatus"></p>
              <p class="mt-2 text-xs text-text-secondary" id="waterLevelTime">Waiting for the ESP32's first reading.</p>
            </div>

          </div>
        </div>

      </div>

      <!-- Weather Forecast -->
      <?php
      $temp_feel = gh_temp_feel((float) $weather_today_detail['temp']);
      $today_icon_color = $weather_today_detail['icon'] === 'sun' || $weather_today_detail['icon'] === 'cloud-sun'
          ? 'text-amber-400'
          : ($weather_today_detail['icon'] === 'cloud' ? 'text-slate-400' : 'text-sky-500');
      ?>
      <div class="mt-6 rounded-2xl border border-border/60 bg-white shadow-soft">
        <div class="flex flex-row items-center justify-between p-6 pb-2">
          <h3 class="text-base font-semibold text-text-primary">Weather Forecast</h3>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-light px-2.5 py-1 text-[11px] font-medium text-secondary">
            <i data-lucide="map-pin" class="h-3 w-3"></i>
            Beirut, Lebanon
            <?php if ($weather_is_live): ?>
            <span class="ml-0.5 h-1.5 w-1.5 rounded-full bg-primary"></span> Live
            <?php else: ?>
            <span class="ml-0.5 text-text-secondary">(offline)</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="flex flex-col gap-4 p-6 pt-2 lg:flex-row">
          <!-- Today: big detail panel -->
          <div class="relative flex shrink-0 flex-col justify-between overflow-hidden rounded-2xl border border-border/60 bg-white p-6 shadow-soft lg:w-[340px]">
            <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full blur-3xl" style="background: <?= $temp_feel['glow'] ?>;"></div>

            <div class="relative">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-text-primary">Today</p>
                  <p class="text-xs text-text-secondary"><?= htmlspecialchars($weather_today_detail['fullDate']) ?></p>
                </div>
                <i data-lucide="<?= $weather_today_detail['icon'] ?>" class="h-12 w-12 <?= $today_icon_color ?>" stroke-width="1.5"></i>
              </div>

              <div class="mt-4 flex items-baseline gap-1">
                <span class="text-[52px] font-semibold leading-none tracking-tight text-text-primary"><?= $weather_today_detail['temp'] ?>°</span>
                <span class="text-lg font-medium text-text-secondary">C</span>
              </div>
              <p class="mt-1.5 flex items-center gap-1.5 text-sm">
                <span class="font-semibold <?= $temp_feel['text'] ?>">Feels <?= $temp_feel['label'] ?></span>
                <span class="text-text-secondary">· <?= htmlspecialchars($weather_today_detail['label']) ?></span>
              </p>
              <p class="text-xs text-text-secondary">High <?= $weather_forecast[0]['high'] ?>° · Low <?= $weather_forecast[0]['low'] ?>°</p>

              <!-- Cold → Hot gauge, marker shows where today sits -->
              <div class="relative mt-4">
                <div class="h-2 w-full rounded-full" style="background: linear-gradient(90deg, #2563EB 0%, #0EA5E9 25%, #22C55E 50%, #F59E0B 75%, #EF4444 100%);"></div>
                <div class="absolute top-1/2 h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-soft" style="left: <?= $temp_feel['pct'] ?>%; background-color: <?= $temp_feel['dot'] ?>;"></div>
              </div>
              <div class="mt-1.5 flex justify-between text-[10px] text-text-secondary">
                <span>Cold</span>
                <span>Hot</span>
              </div>
            </div>

            <div class="relative mt-6 grid grid-cols-3 gap-2 border-t border-border/60 pt-4">
              <div class="flex flex-col items-center gap-1">
                <i data-lucide="thermometer" class="h-3.5 w-3.5 text-text-secondary"></i>
                <span class="text-xs font-semibold text-text-primary"><?= $weather_today_detail['feelsLike'] ?>°</span>
                <span class="text-[10px] text-text-secondary">Feels like</span>
              </div>
              <div class="flex flex-col items-center gap-1">
                <i data-lucide="droplets" class="h-3.5 w-3.5 text-text-secondary"></i>
                <span class="text-xs font-semibold text-text-primary"><?= $weather_today_detail['humidity'] ?>%</span>
                <span class="text-[10px] text-text-secondary">Humidity</span>
              </div>
              <div class="flex flex-col items-center gap-1">
                <i data-lucide="wind" class="h-3.5 w-3.5 text-text-secondary"></i>
                <span class="text-xs font-semibold text-text-primary"><?= $weather_today_detail['windSpeed'] ?></span>
                <span class="text-[10px] text-text-secondary">km/h wind</span>
              </div>
            </div>
          </div>

          <!-- Next 4 days: compact cards -->
          <div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-4">
            <?php foreach (array_slice($weather_forecast, 1) as $day):
              $iconColor = $day['icon'] === 'sun' || $day['icon'] === 'cloud-sun' ? 'text-amber-400' : ($day['icon'] === 'cloud' ? 'text-slate-400' : 'text-sky-500');
            ?>
            <div class="flex flex-col items-center gap-2 rounded-2xl bg-neutral-50/70 px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-light/50">
              <span class="text-xs font-semibold text-text-primary"><?= htmlspecialchars($day['day']) ?></span>
              <span class="text-[10px] text-text-secondary"><?= htmlspecialchars($day['date']) ?></span>
              <i data-lucide="<?= $day['icon'] ?>" class="my-1 h-8 w-8 <?= $iconColor ?>" stroke-width="1.75"></i>
              <div class="flex items-baseline gap-1">
                <span class="text-sm font-semibold text-text-primary"><?= $day['high'] ?>°</span>
                <span class="text-xs text-text-secondary"><?= $day['low'] ?>°</span>
              </div>
              <div class="flex items-center gap-0.5 text-[10px] text-sky-500">
                <i data-lucide="droplets" class="h-2.5 w-2.5"></i><?= $day['precip'] ?>%
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
// Loads the AI Insights card once on page load. A cached insight (< 24h old)
// comes back instantly; otherwise the endpoint calls Gemini and caches the
// result server-side, so repeat dashboard visits stay fast and cheap.
(function () {
  const loading = document.getElementById('insightLoading');
  const content = document.getElementById('insightContent');
  const errorBox = document.getElementById('insightError');
  const updated = document.getElementById('insightUpdated');
  const refreshBtn = document.getElementById('insightRefreshBtn');
  const refreshIcon = document.getElementById('insightRefreshIcon');

  const TREND_STYLE = {
    positive: { wrap: 'bg-light text-secondary', icon: 'check-circle-2' },
    neutral: { wrap: 'bg-neutral-100 text-neutral-600', icon: 'info' },
    warning: { wrap: 'bg-amber-50 text-amber-600', icon: 'alert-triangle' },
  };

  function timeAgo(mysqlDatetime) {
    const d = new Date(mysqlDatetime.replace(' ', 'T'));
    const mins = Math.max(0, Math.round((Date.now() - d.getTime()) / 60000));
    if (mins < 1) return 'Updated just now';
    if (mins < 60) return 'Updated ' + mins + 'm ago';
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return 'Updated ' + hrs + 'h ago';
    return 'Updated ' + Math.round(hrs / 24) + 'd ago';
  }

  function render(data) {
    document.getElementById('insightHeadline').textContent = data.headline;
    document.getElementById('insightSummary').textContent = data.summary;
    document.getElementById('insightRecommendation').textContent = data.recommendation;
    const style = TREND_STYLE[data.trend] || TREND_STYLE.neutral;
    const wrap = document.getElementById('insightTrendIconWrap');
    wrap.className = 'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full ' + style.wrap;
    document.getElementById('insightTrendIcon').setAttribute('data-lucide', style.icon);
    updated.textContent = timeAgo(data.generated_at);
    loading.classList.add('hidden');
    errorBox.classList.add('hidden');
    content.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
  }

  function showError(message) {
    loading.classList.add('hidden');
    content.classList.add('hidden');
    errorBox.textContent = message;
    errorBox.classList.remove('hidden');
  }

  function load(refresh) {
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    errorBox.classList.add('hidden');
    refreshBtn.disabled = true;
    refreshIcon.classList.add('animate-spin');

    fetch('api/ai-insight.php' + (refresh ? '?refresh=1' : ''))
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) {
          showError(data.error || "Couldn't load an insight right now.");
          return;
        }
        render(data);
      })
      .catch(() => showError('Network error — try again later.'))
      .finally(() => {
        refreshBtn.disabled = false;
        refreshIcon.classList.remove('animate-spin');
      });
  }

  refreshBtn.addEventListener('click', () => load(true));
  load(false);
})();

// Polls api/sensor-latest.php every 3s and fills in the three Live Sensors
// cards above once your ESP32 has sent at least one real reading. Cards
// start as "No Data" (grey, "--") and switch to "Live" (green, real number)
// the moment a non-null value comes back — never fakes a number in between.
(function () {
  const cards = {
    gas: { value: document.getElementById('gasValue'), badge: document.getElementById('gasBadge'), time: document.getElementById('gasTime'), iconWrap: document.getElementById('gasIconWrap'), status: document.getElementById('gasStatus') },
    light: { value: document.getElementById('lightValue'), badge: document.getElementById('lightBadge'), time: document.getElementById('lightTime'), iconWrap: document.getElementById('lightIconWrap'), status: document.getElementById('lightStatus') },
    soil: { value: document.getElementById('soilValue'), badge: document.getElementById('soilBadge'), time: document.getElementById('soilTime'), iconWrap: document.getElementById('soilIconWrap'), status: document.getElementById('soilStatus') },
    temperature: { value: document.getElementById('temperatureValue'), badge: document.getElementById('temperatureBadge'), time: document.getElementById('temperatureTime'), iconWrap: document.getElementById('temperatureIconWrap'), status: document.getElementById('temperatureStatus') },
    humidity: { value: document.getElementById('humidityValue'), badge: document.getElementById('humidityBadge'), time: document.getElementById('humidityTime'), iconWrap: document.getElementById('humidityIconWrap'), status: document.getElementById('humidityStatus') },
    waterLevel: { value: document.getElementById('waterLevelValue'), badge: document.getElementById('waterLevelBadge'), time: document.getElementById('waterLevelTime'), iconWrap: document.getElementById('waterLevelIconWrap'), status: document.getElementById('waterLevelStatus') },
  };

  function formatTime(mysqlDatetime) {
    const d = new Date(mysqlDatetime.replace(' ', 'T'));
    return 'Recorded ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
  }

  // Thresholds below are starting estimates based on the readings seen
  // during setup — not a factory calibration. Tune the numbers once you've
  // watched each sensor in real wet/dry/gas conditions for a while.
  function gasStatus(v) {
    if (v >= 1000) return { text: 'Poor — gas detected', cls: 'text-red-500' };
    if (v >= 700) return { text: 'Moderate', cls: 'text-amber-500' };
    return { text: 'Good air quality', cls: 'text-primary' };
  }

  // Recalibrated: this sensor's real range sits compressed near the top
  // (roughly 3800-4095), not spread across the full 0-4095 scale.
  function lightStatus(v) {
    if (v < 3900) return { text: 'No light detected', cls: 'text-slate-500' };
    if (v < 4020) return { text: 'Normal light', cls: 'text-primary' };
    return { text: 'Bright', cls: 'text-amber-500' };
  }

  function soilStatus(v) {
    if (v === 0) return { text: 'No soil detected', cls: 'text-neutral-400' };
    if (v < 1200) return { text: 'Too much water', cls: 'text-sky-500' };
    if (v < 2800) return { text: 'Good moisture', cls: 'text-primary' };
    return { text: 'Needs water', cls: 'text-amber-500' };
  }

  function temperatureStatus(v) {
    if (v < 15) return { text: 'Cold', cls: 'text-sky-500' };
    if (v < 30) return { text: 'Comfortable', cls: 'text-primary' };
    return { text: 'Hot', cls: 'text-red-500' };
  }

  function humidityStatus(v) {
    if (v < 30) return { text: 'Dry', cls: 'text-amber-500' };
    if (v < 70) return { text: 'Comfortable', cls: 'text-primary' };
    return { text: 'Humid', cls: 'text-sky-500' };
  }

  // This reservoir sensor is a detect/no-detect float switch, not a
  // graduated level sensor — it only ever reports 0% or 100%, so there's
  // no real "Half" state to show.
  function waterLevelStatus(pct) {
    if (pct < 50) return { text: 'Empty Tank', cls: 'text-red-500' };
    return { text: 'Full', cls: 'text-sky-500' };
  }

  const STATUS_COLOR_CLASSES = ['text-red-500', 'text-amber-500', 'text-primary', 'text-sky-500', 'text-slate-500', 'text-neutral-400'];

  function setLive(card, value, status) {
    card.value.textContent = value;
    card.value.classList.remove('text-neutral-300');
    card.value.classList.add('text-text-primary');
    card.badge.textContent = 'Live';
    card.badge.classList.remove('bg-neutral-100', 'text-neutral-500');
    card.badge.classList.add('bg-light', 'text-secondary');
    card.iconWrap.classList.remove('bg-neutral-100');
    card.iconWrap.classList.add('bg-light');
    card.iconWrap.querySelector('i')?.classList.remove('text-neutral-400');
    card.iconWrap.querySelector('i')?.classList.add('text-secondary');
    card.status.textContent = status.text;
    card.status.classList.remove(...STATUS_COLOR_CLASSES);
    card.status.classList.add(status.cls);
  }

  // Wipes the number back to "--" instead of leaving a stale reading on
  // screen looking live — used once api/sensor-latest.php says the ESP32
  // hasn't sent anything in the last few seconds.
  function setDisconnected(card, lastSeenText) {
    card.value.textContent = '--';
    card.value.classList.remove('text-text-primary');
    card.value.classList.add('text-neutral-300');
    card.badge.textContent = 'Disconnected';
    card.badge.classList.remove('bg-light', 'text-secondary');
    card.badge.classList.add('bg-red-50', 'text-red-500');
    card.iconWrap.classList.remove('bg-light');
    card.iconWrap.classList.add('bg-neutral-100');
    card.iconWrap.querySelector('i')?.classList.remove('text-secondary');
    card.iconWrap.querySelector('i')?.classList.add('text-neutral-400');
    card.status.textContent = '';
    card.status.classList.remove(...STATUS_COLOR_CLASSES);
    card.time.textContent = lastSeenText;
  }

  function poll() {
    fetch('api/sensor-latest.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        if (!data.latest) return;
        const r = data.latest;

        if (!data.connected) {
          const lastSeen = 'Last seen ' + formatTime(r.recorded_at).replace('Recorded ', '');
          setDisconnected(cards.gas, lastSeen);
          setDisconnected(cards.light, lastSeen);
          setDisconnected(cards.soil, lastSeen);
          setDisconnected(cards.temperature, lastSeen);
          setDisconnected(cards.humidity, lastSeen);
          setDisconnected(cards.waterLevel, lastSeen);
          return;
        }

        // Raw ADC counts (0-4095) aren't meaningful to look at, so the big
        // number shown is a percentage — the status label underneath still
        // uses the raw value, since that's the scale the thresholds were
        // tuned against.
        if (r.gas_value !== null) {
          const v = Math.round(r.gas_value);
          // Shown as an air-quality score, not a "how much gas" reading —
          // higher % = cleaner air, so it reads naturally next to the label.
          const pct = Math.max(0, Math.min(100, Math.round(100 - (v / 4095) * 100)));
          setLive(cards.gas, pct + '%', gasStatus(v));
          cards.gas.time.textContent = formatTime(r.recorded_at);
        }
        if (r.light_value !== null) {
          const v = Math.round(r.light_value);
          // Scaled against this sensor's real calibrated range (3800 = fully
          // dark, 4095 = fully bright), not the full 0-4095 ADC scale — using
          // the full scale made even a genuinely dark reading show ~94%.
          const pct = Math.max(0, Math.min(100, Math.round(((v - 3800) / (4095 - 3800)) * 100)));
          setLive(cards.light, pct + '%', lightStatus(v));
          cards.light.time.textContent = formatTime(r.recorded_at);
        }
        if (r.soil_value !== null) {
          const v = Math.round(r.soil_value);
          // v === 0 is the "no soil in contact" sentinel (see main.cpp) —
          // show "--" rather than a misleading "0%" moisture reading.
          if (v === 0) {
            setLive(cards.soil, '--', soilStatus(v));
          } else {
            const moisturePct = Math.max(0, Math.min(100, Math.round(100 - (v / 4095) * 100)));
            setLive(cards.soil, moisturePct + '%', soilStatus(v));
          }
          cards.soil.time.textContent = formatTime(r.recorded_at);
        }
        if (r.temperature_value !== null) {
          const v = Math.round(r.temperature_value * 10) / 10;
          setLive(cards.temperature, v + '°C', temperatureStatus(v));
          cards.temperature.time.textContent = formatTime(r.recorded_at);
        }
        if (r.humidity_value !== null) {
          const v = Math.round(r.humidity_value * 10) / 10;
          setLive(cards.humidity, v + '%', humidityStatus(v));
          cards.humidity.time.textContent = formatTime(r.recorded_at);
        }
        if (r.water_level_value !== null) {
          const v = Math.round(r.water_level_value);
          // Empty (dry) reads 0, but "fully submerged" only reaches ~867 on
          // this sensor, not anywhere near 4095 — scaled against its real
          // observed range instead of the full ADC scale.
          const pct = Math.max(0, Math.min(100, Math.round((v / 900) * 100)));
          setLive(cards.waterLevel, pct + '%', waterLevelStatus(pct));
          cards.waterLevel.time.textContent = formatTime(r.recorded_at);
        }
      })
      .catch(() => {});
  }

  poll();
  setInterval(poll, 500);
})();
</script>
<script src="js/dashboard-app.js?v=1"></script>
</body>
</html>
