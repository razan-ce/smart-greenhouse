<?php
date_default_timezone_set('Asia/Beirut');


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


require_once __DIR__ . '/weather-lib.php';
$weather_today_detail = null;
$weather_is_live = false;
$weatherCtx = stream_context_create(['http' => ['timeout' => 4]]);
$weatherJson = @file_get_contents(
    'https://api.open-meteo.com/v1/forecast?latitude=33.8938&longitude=35.5018'
    . '&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m'
    . '&timezone=Asia%2FBeirut',
    false,
    $weatherCtx
);
if ($weatherJson !== false) {
    $weatherData = json_decode($weatherJson, true);
    if (isset($weatherData['current']['temperature_2m'])) {
        $current = $weatherData['current'];
        $weather_today_detail = [
            'temp'      => round((float) $current['temperature_2m'], 1),
            'feelsLike' => round((float) $current['apparent_temperature'], 1),
            'humidity'  => (int) round($current['relative_humidity_2m']),
            'windSpeed' => round((float) $current['wind_speed_10m'], 1),
            'label'     => gh_weather_code_label((int) $current['weather_code']),
            'icon'      => gh_weather_code_icon((int) $current['weather_code']),
        ];
        $weather_is_live = true;
    }
}

// Sensors with a real background photo in assets/ — anything not listed here
// falls back to the custom icon illustration from gh_sensor_hero() instead.
$gh_hero_photos = [
    'temperature' => 'assets/temp-hero.png',
    'soil' => 'assets/soil-hero.png',
    'gas' => 'assets/gas-hero.png',
    'humidity' => 'assets/humidity-hero.png',
    'waterLevel' => 'assets/water-hero.png',
];

$gh_page_title = 'Sensors | Grovia';
$gh_extra_head = <<<'HTML'
<style>
  /* Water-flow fill across the whole actuator card — only shown for the
     pump. The card itself becomes the tank: water rises from the bottom
     of the entire panel (not just the icon), the header sits directly
     over open water, and the control section below floats on a frosted
     glass panel so the buttons/text stay fully legible over it. */
  #actuatorCard { position: relative; }
  #actuatorCardWave {
    position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0;
  }
  #actuatorCardWave .wave-level {
    position: absolute; left: 0; right: 0; bottom: 0; height: 0%;
    transition: height 1.1s cubic-bezier(.34,1.25,.4,1);
  }
  #actuatorCardWave.is-on .wave-level { height: 72%; }
  #actuatorCardWave .wave-fill {
    position: absolute; inset: 14px 0 0 0;
    background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 38%, #075985 72%, #0c4a6e 100%);
  }
  #actuatorCardWave .wave-rim {
    position: absolute; left: 0; right: 0; top: 12px; height: 2px;
    background: linear-gradient(90deg, rgba(224,242,254,0.1), rgba(224,242,254,0.85) 50%, rgba(224,242,254,0.1));
    filter: blur(0.3px);
  }
  #actuatorCardWave .wave-sheen {
    position: absolute; inset: 14px 0 0 0; opacity: 0.22;
    background: linear-gradient(125deg, rgba(255,255,255,0.55) 0%, transparent 32%);
  }
  #actuatorCardWave .wave-depth {
    position: absolute; inset: 14px 0 0 0;
    background: linear-gradient(180deg, rgba(2,132,199,0) 0%, rgba(3,50,79,0.35) 100%);
  }

  #actuatorCardWave .wave-strip {
    position: absolute; left: 0; width: 200%; height: 34px;
    animation-play-state: paused;
  }
  #actuatorCardWave .wave-strip-back {
    top: -10px; opacity: 0.5;
    animation: waveScroll 4.2s linear infinite paused;
  }
  #actuatorCardWave .wave-strip-front {
    top: -15px;
    animation: waveScroll 2.4s linear infinite reverse paused;
  }
  #actuatorCardWave.is-on .wave-strip { animation-play-state: running; }
  @keyframes waveScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }

  /* Rising bubbles — subtle, only while the pump is actually on. */
  #actuatorCardWave .bubble {
    position: absolute; bottom: 10px; width: 4px; height: 4px; border-radius: 50%;
    background: rgba(255,255,255,0.85); opacity: 0;
  }
  #actuatorCardWave.is-on .bubble { animation: bubbleRise 3.2s ease-in infinite; }
  #actuatorCardWave .bubble-1 { left: 18%; animation-delay: 0.1s; }
  #actuatorCardWave .bubble-2 { left: 38%; width: 3px; height: 3px; animation-delay: 1.1s; }
  #actuatorCardWave .bubble-3 { left: 58%; animation-delay: 0.6s; }
  #actuatorCardWave .bubble-4 { left: 76%; width: 3px; height: 3px; animation-delay: 1.9s; }
  #actuatorCardWave .bubble-5 { left: 88%; animation-delay: 2.4s; }
  @keyframes bubbleRise {
    0% { opacity: 0; transform: translateY(0) scale(0.8); }
    12% { opacity: 0.9; }
    88% { opacity: 0.5; }
    100% { opacity: 0; transform: translateY(-90px) scale(1); }
  }


  #actuatorHeader, #actuatorBody { position: relative; z-index: 1; }
  #actuatorBody.is-glass {
    background: rgba(255,255,255,0.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
  }

  .vent-photo-wrap {
    position: relative; width: 100%; max-width: 320px; aspect-ratio: 4 / 3;
    border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.1));
  }
  .vent-photo {
    position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
    opacity: 0; transition: opacity 0.9s ease;
  }
  .vent-photo-closed { opacity: 1; }
  #ventCard.is-open .vent-photo-closed { opacity: 0; }
  #ventCard.is-open .vent-photo-open { opacity: 1; }

  #actuatorFanPhoto { box-shadow: 0 1px 4px rgba(0,0,0,0.18); }
  #actuatorFanPhoto.is-spinning { animation: fanSpin 0.7s linear infinite; }
  @keyframes fanSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
HTML;
require __DIR__ . '/partials/head.php';


$gh_sensors = [
    ['key' => 'gas', 'label' => 'Gas', 'icon' => 'wind', 'actuator' => 'fan',
        'title' => 'Air Quality Monitoring',
        'description' => 'Detects smoke, NH3, alcohol, and other airborne compounds in real time to keep the air around your plants safe.',
        'secondary' => ['temperature', 'humidity']],
    ['key' => 'temperature', 'label' => 'Temperature', 'icon' => 'thermometer', 'actuator' => 'fan',
        'title' => 'Temperature Monitoring',
        'description' => 'Real-time temperature monitoring to keep your greenhouse in the perfect condition for growth.',
        'secondary' => ['humidity', 'gas']],
    ['key' => 'soil', 'label' => 'Soil', 'icon' => 'sprout', 'actuator' => 'pump',
        'title' => 'Soil Moisture Monitoring',
        'description' => "Tracks soil moisture so irrigation happens precisely when it's needed — not a drop more, not a drop less.",
        'secondary' => ['waterLevel', 'humidity']],
    ['key' => 'waterLevel', 'label' => 'Water Level', 'icon' => 'waves', 'actuator' => 'pump',
        'title' => 'Water Reservoir Monitoring',
        'description' => 'Monitors the tank level that supplies your irrigation pump, so it never runs dry.',
        'secondary' => ['soil', 'temperature']],
    ['key' => 'humidity', 'label' => 'Humidity', 'icon' => 'droplets', 'actuator' => null,
        'title' => 'Humidity Monitoring',
        'description' => 'Monitors the air humidity in real time to ensure the perfect environment for your plants.',
        'secondary' => ['temperature', 'gas']],
    ['key' => 'light', 'label' => 'Light', 'icon' => 'sun', 'actuator' => 'lamp',
        'title' => 'Light Monitoring',
        'description' => "Measures ambient light level to confirm your plants are getting enough — or too much — light through the day.",
        'secondary' => ['temperature', 'humidity']],
];

// Custom icon illustration for sensors without a real photo in $gh_hero_photos.
function gh_sensor_hero(string $key): string {
    switch ($key) {
        case 'light':
        default:
            return '
              <div class="relative flex h-28 w-32 shrink-0 items-center justify-center">
                <div class="absolute h-24 w-24 rounded-full bg-amber-50"></div>
                <div class="absolute h-16 w-16 rounded-full bg-amber-100"></div>
                <div class="relative flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-amber-300 to-amber-500 shadow-lg">
                  <i data-lucide="sun" class="h-8 w-8 text-white"></i>
                </div>
              </div>';
    }
}
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1000px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-6">

      <div>
        <h1 class="text-[28px] font-semibold tracking-tight text-text-primary sm:text-[32px]">Sensors</h1>
        <p class="mt-1 text-sm text-text-secondary">Select a sensor to see its live reading, trend, and, where available, control the device it's tied to.</p>
      </div>


      <div class="flex gap-3 overflow-x-auto pb-1 custom-scroll">
        <?php foreach ($gh_sensors as $i => $s): $k = $s['key']; $active = $i === 0; ?>
        <button type="button" data-tab-btn="<?= $k ?>" data-actuator="<?= $s['actuator'] ?? '' ?>"
          class="sensor-tab-btn flex w-[150px] shrink-0 flex-col items-start gap-3 rounded-2xl border p-4 text-left transition-all duration-200 <?= $active ? 'is-active border-transparent bg-green-gradient shadow-glow' : 'border-border/60 bg-white shadow-soft hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-soft-lg' ?>">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl <?= $active ? 'bg-white/20' : 'bg-light' ?>" data-icon-wrap>
            <i data-lucide="<?= $s['icon'] ?>" class="h-5 w-5 <?= $active ? 'text-white' : 'text-secondary' ?>"></i>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold <?= $active ? 'text-white' : 'text-text-primary' ?>" data-label-text><?= htmlspecialchars($s['label']) ?></p>
            <p class="mt-0.5 truncate text-xs <?= $active ? 'text-white/80' : 'text-text-secondary' ?>" id="<?= $k ?>MiniValue" data-mini-value>--</p>
          </div>
        </button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($gh_sensors as $i => $s): $k = $s['key']; ?>
      <div id="panel-<?= $k ?>" data-tab-panel="<?= $k ?>" class="sensor-panel <?= $i === 0 ? '' : 'hidden' ?> flex flex-col gap-6">


        <?php if (isset($gh_hero_photos[$k])): ?>
        <div class="relative flex items-center overflow-hidden rounded-2xl border border-border/60 p-8 shadow-soft sm:p-10"
          style="background-image: linear-gradient(to right, #ffffff 0%, #ffffff 32%, rgba(255,255,255,0.82) 50%, rgba(255,255,255,0.25) 72%, rgba(255,255,255,0) 100%), url('<?= htmlspecialchars($gh_hero_photos[$k]) ?>'); background-size: cover; background-position: center right; min-height: 220px;">
          <div class="relative min-w-0 max-w-[36ch]">
            <h2 class="text-2xl font-semibold tracking-tight text-text-primary sm:text-3xl"><?= htmlspecialchars($s['title']) ?></h2>
            <p class="mt-2 text-base text-text-secondary"><?= htmlspecialchars($s['description']) ?></p>
          </div>
        </div>
        <?php else: ?>
        <div class="flex items-center justify-between gap-6 overflow-hidden rounded-2xl border border-border/60 bg-mesh-green p-8 shadow-soft sm:p-10">
          <div class="min-w-0">
            <h2 class="text-2xl font-semibold tracking-tight text-text-primary sm:text-3xl"><?= htmlspecialchars($s['title']) ?></h2>
            <p class="mt-2 max-w-[34ch] text-base text-text-secondary"><?= htmlspecialchars($s['description']) ?></p>
          </div>
          <div class="shrink-0 origin-right scale-110 sm:scale-125"><?= gh_sensor_hero($k) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($k === 'temperature'): ?>
        <!-- Outdoor weather conditions — real live data (Open-Meteo), for comparison against the greenhouse's own temperature reading -->
        <div class="rounded-2xl border border-border/60 bg-white p-8 shadow-soft sm:p-10">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-text-primary">Outdoor Conditions</h3>
            <?php if ($weather_is_live): ?>
            <span class="flex items-center gap-1.5 rounded-full bg-light px-2.5 py-1 text-[10px] font-semibold text-secondary">
              <span class="h-1.5 w-1.5 rounded-full bg-primary"></span> Live · Beirut
            </span>
            <?php else: ?>
            <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[10px] font-semibold text-neutral-500">Unavailable</span>
            <?php endif; ?>
          </div>

          <?php if ($weather_is_live): ?>
          <div class="mt-5 flex items-center gap-5">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-light">
              <i data-lucide="<?= htmlspecialchars($weather_today_detail['icon']) ?>" class="h-8 w-8 text-secondary" stroke-width="1.75"></i>
            </div>
            <div>
              <span class="block text-4xl font-semibold leading-none tracking-tight text-text-primary"><?= $weather_today_detail['temp'] ?>°C</span>
              <p class="mt-1.5 text-sm text-text-secondary"><?= htmlspecialchars($weather_today_detail['label']) ?> · Feels like <?= $weather_today_detail['feelsLike'] ?>°C</p>
            </div>
          </div>
          <div class="mt-6 grid grid-cols-2 gap-4">
            <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
              <div class="flex items-center gap-2 text-xs font-medium text-text-secondary"><i data-lucide="droplets" class="h-3.5 w-3.5"></i> Outdoor Humidity</div>
              <span class="mt-1.5 block text-xl font-semibold text-text-primary"><?= $weather_today_detail['humidity'] ?>%</span>
            </div>
            <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
              <div class="flex items-center gap-2 text-xs font-medium text-text-secondary"><i data-lucide="wind" class="h-3.5 w-3.5"></i> Wind Speed</div>
              <span class="mt-1.5 block text-xl font-semibold text-text-primary"><?= $weather_today_detail['windSpeed'] ?> km/h</span>
            </div>
          </div>
          <p class="mt-5 text-xs text-text-secondary">Real-time weather for Beirut, Lebanon — useful context for how much your greenhouse structure is insulating against the outside temperature.</p>
          <?php else: ?>
          <p class="mt-4 text-sm text-text-secondary">Couldn't reach the weather service just now — this will populate automatically on the next page load.</p>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($k === 'waterLevel'): ?>
        <!-- 3D water tank — fill height/surface driven live by the actual water_level_value reading -->
        <div class="rounded-2xl border border-border/60 bg-white p-8 shadow-soft sm:p-10">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-text-primary">Water Tank</h3>
            <span class="text-2xl font-bold text-secondary" id="waterLevelTankPct">--%</span>
          </div>
          <div class="mt-6 flex items-center justify-center" style="perspective: 700px;">
            <svg width="160" height="220" viewBox="0 0 160 220" style="transform: rotateX(10deg); transform-style: preserve-3d;">
              <defs>
                <linearGradient id="tankWaterGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#38bdf8" />
                  <stop offset="100%" stop-color="#0369a1" />
                </linearGradient>
                <linearGradient id="tankGlassGrad" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stop-color="#ffffff" stop-opacity="0.85" />
                  <stop offset="18%" stop-color="#e5e7eb" stop-opacity="0.15" />
                  <stop offset="82%" stop-color="#e5e7eb" stop-opacity="0.15" />
                  <stop offset="100%" stop-color="#ffffff" stop-opacity="0.85" />
                </linearGradient>
                <clipPath id="tankClipPath">
                  <path d="M14,20 a66,10 0 0 0 132,0 v180 a66,10 0 0 1 -132,0 Z" />
                </clipPath>
              </defs>

              <path d="M14,20 a66,10 0 0 0 132,0 v180 a66,10 0 0 1 -132,0 Z" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2" />

              <g clip-path="url(#tankClipPath)">
                <rect id="waterLevelTankFill" x="14" y="200" width="132" height="0" fill="url(#tankWaterGrad)" style="transition: y 0.8s ease, height 0.8s ease;" />
                <ellipse id="waterLevelTankSurface" cx="80" cy="200" rx="66" ry="8" fill="#7dd3fc" opacity="0.75" style="transition: cy 0.8s ease;" />
              </g>

              <path d="M14,20 a66,10 0 0 0 132,0 v180 a66,10 0 0 1 -132,0 Z" fill="url(#tankGlassGrad)" opacity="0.5" />
              <ellipse cx="80" cy="20" rx="66" ry="10" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2" />
            </svg>
          </div>
          <p class="mt-4 text-center text-xs text-text-secondary" id="waterLevelTankCaption">Waiting for the ESP32's first reading.</p>
        </div>
        <?php endif; ?>

        <!-- Current value + gauge ring -->
        <div class="rounded-2xl border border-border/60 bg-white p-8 shadow-soft sm:p-10">
          <div class="flex items-center justify-between gap-8">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Current <?= htmlspecialchars($s['label']) ?></p>
                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-500" id="<?= $k ?>Badge">No Data</span>
              </div>
              <span class="mt-3 block text-[58px] font-semibold leading-none tracking-tight text-neutral-300" id="<?= $k ?>Value">--</span>
              <p class="mt-3 text-base font-semibold" id="<?= $k ?>Status"></p>
            </div>
            <svg width="150" height="150" viewBox="0 0 110 110" class="-rotate-90 shrink-0">
              <circle cx="55" cy="55" r="46" fill="none" stroke="#DCFCE7" stroke-width="10" />
              <circle id="<?= $k ?>Ring" class="ring-progress" cx="55" cy="55" r="46" fill="none" stroke="#22C55E" stroke-width="10"
                stroke-linecap="round" stroke-dasharray="289" stroke-dashoffset="289" data-pct="0"
                style="transition: stroke-dashoffset 0.6s ease, stroke 0.4s ease" />
            </svg>
          </div>
          <p class="mt-5 text-xs text-text-secondary" id="<?= $k ?>Time">Waiting for the ESP32's first reading.</p>
        </div>

        <!-- Cross-reference cards -->
        <div class="grid grid-cols-2 gap-5">
          <?php foreach ([1, 2] as $n): ?>
          <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-soft">
            <div class="flex items-center gap-2 text-xs font-medium text-text-secondary">
              <i data-lucide="circle" class="h-4 w-4" id="<?= $k ?>Sec<?= $n ?>Icon"></i>
              <span id="<?= $k ?>Sec<?= $n ?>Label">--</span>
            </div>
            <span class="mt-2 block text-2xl font-semibold text-text-primary" id="<?= $k ?>Sec<?= $n ?>Value">--</span>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Trend chart -->
        <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-soft sm:p-6">
          <h3 class="text-sm font-semibold text-text-primary">Today's Trend</h3>
          <div class="mt-3 h-[130px]">
            <canvas id="<?= $k ?>TrendChart"></canvas>
          </div>
        </div>

        <!-- Status banner -->
        <div class="flex items-start gap-4 rounded-2xl border border-border/60 bg-neutral-50 p-5" id="<?= $k ?>Banner">
          <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-100" id="<?= $k ?>BannerIconWrap">
            <i data-lucide="info" class="h-5 w-5 text-neutral-400" id="<?= $k ?>BannerIcon"></i>
          </div>
          <div>
            <p class="text-base font-semibold text-text-primary" id="<?= $k ?>BannerTitle">Waiting for data</p>
            <p class="mt-0.5 text-sm text-text-secondary" id="<?= $k ?>BannerText">Once the ESP32 sends a reading, this will show whether it's within a safe range.</p>
          </div>
        </div>

      </div>
      <?php endforeach; ?>

      <div id="actuatorCard" class="hidden overflow-hidden rounded-2xl border border-border/60 bg-white shadow-soft">

        <div id="actuatorCardWave" class="hidden">
          <div class="wave-level">
            <div class="wave-fill"></div>
            <svg class="wave-strip wave-strip-back" viewBox="0 0 200 34" preserveAspectRatio="none">
              <path d="M0 17 Q 25 3 50 17 T 100 17 T 150 17 T 200 17 V34 H0 Z" fill="#38bdf8" opacity="0.65"/>
            </svg>
            <svg class="wave-strip wave-strip-front" viewBox="0 0 200 34" preserveAspectRatio="none">
              <path d="M0 19 Q 25 7 50 19 T 100 19 T 150 19 T 200 19 V34 H0 Z" fill="#0ea5e9" stroke="#e0f2fe" stroke-width="1" stroke-opacity="0.6"/>
            </svg>
            <div class="wave-rim"></div>
            <div class="wave-depth"></div>
            <div class="wave-sheen"></div>
            <span class="bubble bubble-1"></span>
            <span class="bubble bubble-2"></span>
            <span class="bubble bubble-3"></span>
            <span class="bubble bubble-4"></span>
            <span class="bubble bubble-5"></span>
          </div>
        </div>
//fan
        <div class="flex flex-col items-center gap-5 border-b border-border/60 p-8 text-center sm:p-10" id="actuatorHeader">
          <div class="relative flex h-32 w-32 items-center justify-center rounded-full transition-colors duration-500" id="actuatorRing">
            <div class="absolute inset-0 rounded-full opacity-20" id="actuatorPulse"></div>
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-white shadow-soft">
              <i data-lucide="fan" id="actuatorIcon" class="h-10 w-10 text-neutral-300 transition-colors duration-500"></i>
              <img src="assets/part-fan.jpg" alt="Fan" id="actuatorFanPhoto" class="hidden h-[88px] w-[88px] rounded-full object-cover">
            </div>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-text-primary" id="actuatorTitle">Fan Control</h3>
            <p class="mt-1 text-sm text-text-secondary" id="actuatorSubtitle">Reacts to gas and temperature readings.</p>
          </div>
          <span class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold" id="actuatorStateBadge">
            <span class="h-1.5 w-1.5 rounded-full" id="actuatorStateDot"></span>
            <span id="actuatorStateText">Off</span>
          </span>
        </div>

        <div class="flex flex-col gap-5 p-6 sm:p-8" id="actuatorBody">
          <div class="hidden items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4" id="actuatorOfflineNote">
            <i data-lucide="wifi-off" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"></i>
            <div>
              <p class="text-xs font-semibold text-amber-800">Greenhouse device isn't connected</p>
              <p class="mt-0.5 text-xs text-amber-700">Connect your ESP32 — changes here will be saved but won't take effect until it's back online.</p>
            </div>
          </div>

          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex rounded-xl border border-border/70 bg-neutral-50/70 p-1">
              <button type="button" data-mode-btn="auto" class="actuator-mode-btn flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold transition-colors">
                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i> Automatic
              </button>
              <button type="button" data-mode-btn="manual" class="actuator-mode-btn flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold transition-colors">
                <i data-lucide="hand" class="h-3.5 w-3.5"></i> Manual
              </button>
            </div>

            <button type="button" id="actuatorToggleBtn" class="flex items-center justify-center gap-2 rounded-xl border border-border/70 bg-white px-5 py-2.5 text-xs font-semibold text-text-secondary transition-all hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40">
              <i data-lucide="power" class="h-3.5 w-3.5"></i>
              <span id="actuatorToggleLabel">Turn On</span>
            </button>
          </div>

          <p class="text-xs text-text-secondary" id="actuatorModeNote">Automatic mode: the system decides based on live sensor readings.</p>

          <div class="flex items-start gap-3 rounded-2xl bg-neutral-50 p-4">
            <i data-lucide="zap" class="mt-0.5 h-4 w-4 shrink-0 text-neutral-400"></i>
            <div>
              <p class="text-xs font-semibold text-text-primary">Automatic trigger threshold</p>
              <p class="mt-0.5 text-xs text-text-secondary" id="actuatorThreshold">—</p>
            </div>
          </div>

          <p class="flex items-center gap-1.5 text-[11px] text-text-secondary" id="actuatorUpdated">
            <i data-lucide="clock" class="h-3 w-3"></i> <span></span>
          </p>

          <div id="irrigationSection" class="hidden flex-col gap-5 border-t border-border/60 pt-5">
            <div class="grid grid-cols-2 gap-4">
              <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
                <div class="flex items-center gap-2 text-xs font-medium text-text-secondary">
                  <i data-lucide="droplets" class="h-3.5 w-3.5"></i> Last Watered
                </div>
                <span class="mt-1.5 block text-sm font-semibold text-text-primary" id="lastWateredText">—</span>
              </div>
              <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
                <div class="flex items-center gap-2 text-xs font-medium text-text-secondary">
                  <i data-lucide="calendar-clock" class="h-3.5 w-3.5"></i> Next Watering
                </div>
                <span class="mt-1.5 block text-sm font-semibold text-text-primary" id="nextWateringText">No schedule set</span>
              </div>
            </div>

            <div class="rounded-2xl border border-border/60 p-4">
              <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-text-primary">Daily watering schedule</p>
                <label class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center">
                  <input type="checkbox" id="scheduleEnabledInput" class="peer sr-only">
                  <span class="absolute inset-0 rounded-full bg-neutral-200 transition-colors peer-checked:bg-primary"></span>
                  <span class="absolute left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
                </label>
              </div>
              <p class="mt-1 text-[11px] text-text-secondary">When enabled, the pump also runs at this time every day, regardless of soil moisture (reservoir safety check still applies).</p>
              <div class="mt-3 flex items-end gap-3">
                <div class="flex-1">
                  <label class="text-[10px] font-medium text-text-secondary">Time</label>
                  <input type="time" id="scheduleTimeInput" class="mt-1 w-full rounded-lg border border-border/70 px-2 py-1.5 text-sm">
                </div>
                <div class="flex-1">
                  <label class="text-[10px] font-medium text-text-secondary">Duration (min)</label>
                  <input type="number" id="scheduleDurationInput" min="1" max="180" class="mt-1 w-full rounded-lg border border-border/70 px-2 py-1.5 text-sm">
                </div>
                <button type="button" id="scheduleSaveBtn" class="shrink-0 rounded-lg bg-green-gradient px-3 py-2 text-xs font-semibold text-white transition-transform active:scale-95">Save</button>
              </div>
              <p class="mt-2 hidden text-[11px] font-medium text-primary" id="scheduleSavedNote">Saved.</p>
            </div>

            <div class="rounded-2xl border border-border/60 p-4">
              <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-text-primary">Watering History — Last 7 Days</p>
                <span class="text-[11px] font-medium text-text-secondary" id="historyWeekTotal"></span>
              </div>
              <div class="mt-3 flex max-h-64 flex-col gap-2 overflow-y-auto" id="historyList">
                <p class="py-4 text-center text-xs text-text-secondary" id="historyEmpty">Loading…</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Ceiling Vent card — separate from the actuator card above (it's a
           servo, not a relay), shown alongside Fan Control since it's also
           temperature-driven. -->
      <div id="ventCard" class="hidden overflow-hidden rounded-2xl border border-border/60 bg-white shadow-soft">
        <div class="flex flex-col items-center gap-3 border-b border-border/60 p-6 text-center sm:p-8">
          <!-- Real photos of the greenhouse unit — crossfades between the
               closed and open shots based on actual vent state. -->
          <div class="vent-photo-wrap">
            <img src="assets/hardware.jpg" alt="Ceiling vent closed" class="vent-photo vent-photo-closed">
            <img src="assets/opeNcelling.jpeg" alt="Ceiling vent open" class="vent-photo vent-photo-open">
          </div>
          <div>
            <h3 class="text-sm font-semibold text-text-primary">Ceiling Vent</h3>
            <p class="text-xs text-text-secondary">Opens for ventilation when it's too hot.</p>
          </div>
          <span class="flex items-center gap-1.5 rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500" id="ventStateBadge">
            <span class="h-1.5 w-1.5 rounded-full bg-neutral-400" id="ventStateDot"></span>
            <span id="ventStateText">Closed</span>
          </span>
        </div>
        <div class="flex flex-col gap-4 p-6">
          <div class="hidden items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4" id="ventOfflineNote">
            <i data-lucide="wifi-off" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"></i>
            <div>
              <p class="text-xs font-semibold text-amber-800">Greenhouse device isn't connected</p>
              <p class="mt-0.5 text-xs text-amber-700">Connect your ESP32 — changes here will be saved but won't take effect until it's back online.</p>
            </div>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex rounded-xl border border-border/70 bg-neutral-50/70 p-1">
              <button type="button" data-vent-mode-btn="auto" class="vent-mode-btn flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold transition-colors">
                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i> Automatic
              </button>
              <button type="button" data-vent-mode-btn="manual" class="vent-mode-btn flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold transition-colors">
                <i data-lucide="hand" class="h-3.5 w-3.5"></i> Manual
              </button>
            </div>
            <button type="button" id="ventToggleBtn" class="flex items-center justify-center gap-2 rounded-xl border border-border/70 bg-white px-5 py-2.5 text-xs font-semibold text-text-secondary transition-all hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40">
              <i data-lucide="power" class="h-3.5 w-3.5"></i>
              <span id="ventToggleLabel">Open</span>
            </button>
          </div>
          <p class="text-xs text-text-secondary" id="ventModeNote">Automatic mode: opens when temperature ≥ 30°C, closes once it cools back down.</p>
        </div>
      </div>

    </div>


  </main>
</div>

<script>
(function () {
  const SENSOR_META = {
    gas: { label: 'Air Quality', icon: 'wind' },
    temperature: { label: 'Temperature', icon: 'thermometer' },
    soil: { label: 'Soil Moisture', icon: 'sprout' },
    waterLevel: { label: 'Water Level', icon: 'waves' },
    humidity: { label: 'Humidity', icon: 'droplets' },
    light: { label: 'Light', icon: 'sun' },
  };

  const SECONDARY_FOR = {
    gas: ['temperature', 'humidity'],
    temperature: ['humidity', 'gas'],
    soil: ['waterLevel', 'humidity'],
    waterLevel: ['soil', 'temperature'],
    humidity: ['temperature', 'gas'],
    light: ['temperature', 'humidity'],
  };

  const cards = {};
  Object.keys(SENSOR_META).forEach((key) => {
    cards[key] = {
      value: document.getElementById(key + 'Value'),
      badge: document.getElementById(key + 'Badge'),
      time: document.getElementById(key + 'Time'),
      status: document.getElementById(key + 'Status'),
      ring: document.getElementById(key + 'Ring'),
      mini: document.getElementById(key + 'MiniValue'),
    };
  });

  const latestDisplay = {}; 

  function formatTime(mysqlDatetime) {
    const d = new Date(mysqlDatetime.replace(' ', 'T'));
    return 'Recorded ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
  }

  function gasStatus(v) {
    if (v >= 1000) return { text: 'Poor — gas detected', cls: 'text-red-500' };
    if (v >= 700) return { text: 'Moderate', cls: 'text-amber-500' };
    return { text: 'Good air quality', cls: 'text-primary' };
  }
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

  function waterLevelStatus(pct) {
    if (pct < 50) return { text: 'Empty Tank', cls: 'text-red-500' };
    return { text: 'Full', cls: 'text-sky-500' };
  }

  const STATUS_COLOR_CLASSES = ['text-red-500', 'text-amber-500', 'text-primary', 'text-sky-500', 'text-slate-500', 'text-neutral-400'];
  const CLS_TO_HEX = { 'text-red-500': '#ef4444', 'text-amber-500': '#f59e0b', 'text-primary': '#22C55E', 'text-sky-500': '#0ea5e9', 'text-slate-500': '#64748b', 'text-neutral-400': '#a3a3a3' };
  const CLS_TO_BANNER = {
    'text-red-500': { bg: 'bg-red-50', iconWrap: 'bg-red-100', icon: 'alert-triangle', iconColor: 'text-red-600', title: 'Needs attention' },
    'text-amber-500': { bg: 'bg-amber-50', iconWrap: 'bg-amber-100', icon: 'alert-circle', iconColor: 'text-amber-600', title: 'Slightly outside ideal range' },
    'text-primary': { bg: 'bg-light', iconWrap: 'bg-white', icon: 'shield-check', iconColor: 'text-secondary', title: 'All good' },
    'text-sky-500': { bg: 'bg-sky-50', iconWrap: 'bg-sky-100', icon: 'info', iconColor: 'text-sky-600', title: 'Within range' },
    'text-slate-500': { bg: 'bg-neutral-50', iconWrap: 'bg-neutral-100', icon: 'moon', iconColor: 'text-neutral-500', title: 'Normal' },
    'text-neutral-400': { bg: 'bg-neutral-50', iconWrap: 'bg-neutral-100', icon: 'info', iconColor: 'text-neutral-400', title: 'No reading yet' },
  };

  function gaugePctFor(key, v) {
    switch (key) {
      case 'gas': return Math.max(0, Math.min(100, Math.round(100 - (v / 4095) * 100)));
      case 'light': return Math.max(0, Math.min(100, Math.round(((v - 3800) / (4095 - 3800)) * 100)));
      case 'soil': return v === 0 ? 0 : Math.max(0, Math.min(100, Math.round(100 - (v / 4095) * 100)));
      case 'temperature': return Math.max(0, Math.min(100, Math.round((v / 45) * 100)));
      case 'humidity': return Math.max(0, Math.min(100, Math.round(v)));
      case 'waterLevel': return Math.max(0, Math.min(100, Math.round((v / 900) * 100)));
      default: return 0;
    }
  }

  function setRing(ringEl, pct, colorHex) {
    if (!ringEl) return;
    const circumference = parseFloat(ringEl.getAttribute('stroke-dasharray'));
    const clamped = Math.max(0, Math.min(100, pct));
    ringEl.style.strokeDashoffset = circumference * (1 - clamped / 100);
    ringEl.setAttribute('data-pct', clamped);
    ringEl.setAttribute('stroke', colorHex);
  }

  function setBanner(key, status) {
    const map = CLS_TO_BANNER[status.cls] || CLS_TO_BANNER['text-neutral-400'];
    const wrap = document.getElementById(key + 'Banner');
    const iconWrap = document.getElementById(key + 'BannerIconWrap');
    const icon = document.getElementById(key + 'BannerIcon');
    if (!wrap) return;
    wrap.className = 'flex items-start gap-3 rounded-2xl border border-border/60 p-4 ' + map.bg;
    iconWrap.className = 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ' + map.iconWrap;
    icon.setAttribute('data-lucide', map.icon);
    icon.className = 'h-4 w-4 ' + map.iconColor;
    document.getElementById(key + 'BannerTitle').textContent = map.title;
    document.getElementById(key + 'BannerText').textContent = SENSOR_META[key].label + ' reading: ' + status.text + '.';
  }

  function setLive(key, value, status) {
    const card = cards[key];
    card.value.textContent = value;
    card.value.classList.remove('text-neutral-300');
    card.value.classList.add('text-text-primary');
    card.badge.textContent = 'Live';
    card.badge.classList.remove('bg-neutral-100', 'text-neutral-500');
    card.badge.classList.add('bg-light', 'text-secondary');
    card.status.textContent = status.text;
    card.status.classList.remove(...STATUS_COLOR_CLASSES);
    card.status.classList.add(status.cls);
    setBanner(key, status);
    if (card.mini) card.mini.textContent = value;
    latestDisplay[key] = { value: value, icon: SENSOR_META[key].icon, label: SENSOR_META[key].label };
  }

  function setDisconnected(key, lastSeenText) {
    const card = cards[key];
    card.value.textContent = '--';
    card.value.classList.remove('text-text-primary');
    card.value.classList.add('text-neutral-300');
    card.badge.textContent = 'Disconnected';
    card.badge.classList.remove('bg-light', 'text-secondary');
    card.badge.classList.add('bg-red-50', 'text-red-500');
    card.status.textContent = '';
    card.status.classList.remove(...STATUS_COLOR_CLASSES);
    card.time.textContent = lastSeenText;
    setRing(card.ring, 0, CLS_TO_HEX['text-neutral-400']);
    setBanner(key, { text: 'Device offline', cls: 'text-neutral-400' });
    if (card.mini) card.mini.textContent = 'Offline';
    latestDisplay[key] = { value: '--', icon: SENSOR_META[key].icon, label: SENSOR_META[key].label };
    if (key === 'waterLevel') setTankFill(0, 'Waiting for a reading — device offline.');
  }

  function setTankFill(pct, captionText) {
    const fillRect = document.getElementById('waterLevelTankFill');
    const surface = document.getElementById('waterLevelTankSurface');
    const pctLabel = document.getElementById('waterLevelTankPct');
    const caption = document.getElementById('waterLevelTankCaption');
    if (!fillRect) return;

    const clamped = Math.max(0, Math.min(100, pct));
    const innerTop = 20, innerBottom = 200;
    const fillHeight = (innerBottom - innerTop) * (clamped / 100);
    const fillY = innerBottom - fillHeight;

    fillRect.setAttribute('y', fillY);
    fillRect.setAttribute('height', fillHeight);
    if (surface) surface.setAttribute('cy', fillY);
    if (pctLabel) pctLabel.textContent = clamped + '%';
    if (caption && captionText) caption.textContent = captionText;
  }

  function renderAllSecondaries() {
    Object.keys(SECONDARY_FOR).forEach((key) => {
      SECONDARY_FOR[key].forEach((otherKey, idx) => {
        const n = idx + 1;
        const disp = latestDisplay[otherKey];
        if (!disp) return;
        document.getElementById(key + 'Sec' + n + 'Label').textContent = disp.label;
        document.getElementById(key + 'Sec' + n + 'Value').textContent = disp.value;
        document.getElementById(key + 'Sec' + n + 'Icon').setAttribute('data-lucide', disp.icon);
      });
    });
    if (window.lucide) window.lucide.createIcons();
  }

  function pollSensors() {
    fetch('api/sensor-latest.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok || !data.latest) return;
        const r = data.latest;

        if (!data.connected) {
          const lastSeen = 'Last seen ' + formatTime(r.recorded_at).replace('Recorded ', '');
          Object.keys(SENSOR_META).forEach((key) => setDisconnected(key, lastSeen));
          renderAllSecondaries();
          return;
        }

        if (r.gas_value !== null) {
          const v = Math.round(r.gas_value);
          setLive('gas', gaugePctFor('gas', v) + '%', gasStatus(v));
          setRing(cards.gas.ring, gaugePctFor('gas', v), CLS_TO_HEX[gasStatus(v).cls]);
          cards.gas.time.textContent = formatTime(r.recorded_at);
        }
        if (r.light_value !== null) {
          const v = Math.round(r.light_value);
          setLive('light', gaugePctFor('light', v) + '%', lightStatus(v));
          setRing(cards.light.ring, gaugePctFor('light', v), CLS_TO_HEX[lightStatus(v).cls]);
          cards.light.time.textContent = formatTime(r.recorded_at);
        }
        if (r.soil_value !== null) {
          const v = Math.round(r.soil_value);
          const display = v === 0 ? '--' : gaugePctFor('soil', v) + '%';
          setLive('soil', display, soilStatus(v));
          setRing(cards.soil.ring, gaugePctFor('soil', v), CLS_TO_HEX[soilStatus(v).cls]);
          cards.soil.time.textContent = formatTime(r.recorded_at);
        }
        if (r.temperature_value !== null) {
          const v = Math.round(r.temperature_value * 10) / 10;
          setLive('temperature', v + '°C', temperatureStatus(v));
          setRing(cards.temperature.ring, gaugePctFor('temperature', v), CLS_TO_HEX[temperatureStatus(v).cls]);
          cards.temperature.time.textContent = formatTime(r.recorded_at);
        }
        if (r.humidity_value !== null) {
          const v = Math.round(r.humidity_value * 10) / 10;
          setLive('humidity', v + '%', humidityStatus(v));
          setRing(cards.humidity.ring, gaugePctFor('humidity', v), CLS_TO_HEX[humidityStatus(v).cls]);
          cards.humidity.time.textContent = formatTime(r.recorded_at);
        }
        if (r.water_level_value !== null) {
          const v = Math.round(r.water_level_value);
          const pct = gaugePctFor('waterLevel', v);
          setLive('waterLevel', pct + '%', waterLevelStatus(pct));
          setRing(cards.waterLevel.ring, pct, CLS_TO_HEX[waterLevelStatus(pct).cls]);
          cards.waterLevel.time.textContent = formatTime(r.recorded_at);
          setTankFill(pct, 'Updated ' + formatTime(r.recorded_at).replace('Recorded ', ''));
        }

        renderAllSecondaries();
      })
      .catch(() => {});
  }

  pollSensors();
  setInterval(pollSensors, 500);

  // ---- Today's trend charts (real historical averages, one per sensor) ----
  const trendCharts = {};
  function initTrendCharts() {
    if (!window.Chart) return;
    Object.keys(SENSOR_META).forEach((key) => {
      const canvas = document.getElementById(key + 'TrendChart');
      if (!canvas) return;
      trendCharts[key] = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
          labels: [],
          datasets: [{
            data: [],
            borderColor: '#22C55E',
            borderWidth: 2,
            tension: 0.4,
            spanGaps: true,
            pointRadius: 0,
            fill: true,
            backgroundColor: (context) => {
              const { chart } = context;
              const { ctx, chartArea } = chart;
              if (!chartArea) return null;
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, 'rgba(34,197,94,0.30)');
              gradient.addColorStop(1, 'rgba(34,197,94,0)');
              return gradient;
            },
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { color: '#6B7280', font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 6 } },
            y: { grid: { color: '#F3F4F6' }, ticks: { color: '#6B7280', font: { size: 10 } } },
          },
        },
      });
    });
  }

  function loadTrend() {
    fetch('api/sensor-trend.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        const TREND_KEY = { gas: 'gas', temperature: 'temperature', soil: 'soil', waterLevel: 'water_level', humidity: 'humidity', light: 'light' };
        Object.keys(SENSOR_META).forEach((key) => {
          const chart = trendCharts[key];
          if (!chart) return;
          let values = data[TREND_KEY[key]] || [];
          if (key === 'waterLevel') {
            values = values.map((v) => (v === null ? null : Math.max(0, Math.min(100, Math.round((v / 900) * 100)))));
          }
          chart.data.labels = data.labels;
          chart.data.datasets[0].data = values;
          chart.update();
        });
      })
      .catch(() => {});
  }

  initTrendCharts();
  loadTrend();
  setInterval(loadTrend, 60000);

  // ---- Tab switching ----
  const tabBtns = document.querySelectorAll('[data-tab-btn]');
  const panels = document.querySelectorAll('[data-tab-panel]');
  const actuatorCard = document.getElementById('actuatorCard');
  const ventCard = document.getElementById('ventCard');
  let activeActuator = null;

  function selectTab(key, actuator) {
    tabBtns.forEach((btn) => {
      const isActive = btn.dataset.tabBtn === key;
      const iconWrap = btn.querySelector('[data-icon-wrap]');
      const icon = iconWrap.querySelector('[data-lucide]'); // matches both the pre-render <i> tag and the <svg> Lucide replaces it with
      const labelText = btn.querySelector('[data-label-text]');
      const miniValue = btn.querySelector('[data-mini-value]');

      btn.classList.toggle('is-active', isActive);
      btn.classList.toggle('border-transparent', isActive);
      btn.classList.toggle('bg-green-gradient', isActive);
      btn.classList.toggle('shadow-glow', isActive);
      btn.classList.toggle('border-border/60', !isActive);
      btn.classList.toggle('bg-white', !isActive);
      btn.classList.toggle('shadow-soft', !isActive);

      iconWrap.classList.toggle('bg-white/20', isActive);
      iconWrap.classList.toggle('bg-light', !isActive);
      icon.classList.toggle('text-white', isActive);
      icon.classList.toggle('text-secondary', !isActive);
      labelText.classList.toggle('text-white', isActive);
      labelText.classList.toggle('text-text-primary', !isActive);
      miniValue.classList.toggle('text-white/80', isActive);
      miniValue.classList.toggle('text-text-secondary', !isActive);
    });
    panels.forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== key));

    activeActuator = actuator || null;
    if (activeActuator) {
      actuatorCard.classList.remove('hidden');
      const ACTUATOR_META = {
        fan: {
          icon: 'fan', title: 'Fan Control',
          subtitle: 'Reacts to gas and temperature readings.',
          threshold: 'Turns on automatically when gas ≥ 1000 or temperature ≥ 30°C.',
        },
        pump: {
          icon: 'droplet', title: 'Water Pump Control',
          subtitle: 'Reacts to soil moisture and reservoir level.',
          threshold: 'Turns on automatically when soil is too dry, as long as the reservoir has at least 10% water.',
        },
        lamp: {
          icon: 'lightbulb', title: 'Grow Lamp Control',
          subtitle: 'Reacts to the ambient light level.',
          threshold: "Turns on automatically when the plant isn't getting enough light.",
        },
      };
      const meta = ACTUATOR_META[activeActuator];
      document.getElementById('actuatorIcon').setAttribute('data-lucide', meta.icon);
      document.getElementById('actuatorTitle').textContent = meta.title;
      document.getElementById('actuatorSubtitle').textContent = meta.subtitle;
      document.getElementById('actuatorThreshold').textContent = meta.threshold;
      document.getElementById('irrigationSection').classList.toggle('hidden', activeActuator !== 'pump');
      document.getElementById('irrigationSection').classList.toggle('flex', activeActuator === 'pump');
      if (window.lucide) window.lucide.createIcons();
      refreshActuatorState();
      if (activeActuator === 'pump') loadIrrigationStatus();
    } else {
      actuatorCard.classList.add('hidden');
    }

    // Ceiling vent card rides alongside Fan Control (Gas/Temperature tabs) —
    // it's a separate card since it's a servo, not a relay device.
    ventCard.classList.toggle('hidden', activeActuator !== 'fan');
    if (activeActuator === 'fan') refreshVentState();
  }

  tabBtns.forEach((btn) => {
    btn.addEventListener('click', () => selectTab(btn.dataset.tabBtn, btn.dataset.actuator || null));
  });

  selectTab(tabBtns[0].dataset.tabBtn, tabBtns[0].dataset.actuator || null);

  // ---- Actuator control ----
  const modeBtns = document.querySelectorAll('[data-mode-btn]');
  const toggleBtn = document.getElementById('actuatorToggleBtn');
  const toggleLabel = document.getElementById('actuatorToggleLabel');
  const stateDot = document.getElementById('actuatorStateDot');
  const stateText = document.getElementById('actuatorStateText');
  const stateBadge = document.getElementById('actuatorStateBadge');
  const modeNote = document.getElementById('actuatorModeNote');

  function formatAgo(mysqlDatetime) {
    if (!mysqlDatetime) return '';
    const then = new Date(mysqlDatetime.replace(' ', 'T'));
    const diffSec = Math.max(0, Math.round((Date.now() - then.getTime()) / 1000));
    if (diffSec < 60) return 'just now';
    const diffMin = Math.round(diffSec / 60);
    if (diffMin < 60) return diffMin + 'm ago';
    const diffHr = Math.round(diffMin / 60);
    if (diffHr < 24) return diffHr + 'h ago';
    return Math.round(diffHr / 24) + 'd ago';
  }

  function renderActuatorState(on, mode, updatedAt) {
    stateText.textContent = on ? 'On' : 'Off';
    stateBadge.classList.toggle('bg-light', on);
    stateBadge.classList.toggle('text-secondary', on);
    stateBadge.classList.toggle('bg-neutral-100', !on);
    stateBadge.classList.toggle('text-neutral-500', !on);
    stateDot.classList.toggle('bg-primary', on);
    stateDot.classList.toggle('bg-neutral-400', !on);

    // Big status ring: soft green glow + pulsing halo when on, flat gray when off.
    // The pump gets an animated water-fill across the WHOLE card instead of
    // the flat ring glow — a rising, flowing wave reads as "water moving"
    // far better than a tint, and filling the whole panel (not just the
    // icon) makes it read as an actual tank rather than a decoration.
    const ring = document.getElementById('actuatorRing');
    const pulse = document.getElementById('actuatorPulse');
    const icon = document.getElementById('actuatorIcon');
    const fanPhoto = document.getElementById('actuatorFanPhoto');
    const cardWave = document.getElementById('actuatorCardWave');
    const body = document.getElementById('actuatorBody');
    const isPump = activeActuator === 'pump';
    const isFan = activeActuator === 'fan';


    icon.classList.toggle('hidden', isFan);
    fanPhoto.classList.toggle('hidden', !isFan);
    fanPhoto.classList.toggle('is-spinning', isFan && on);

    cardWave.classList.toggle('hidden', !isPump);
    cardWave.classList.toggle('is-on', isPump && on);
    body.classList.toggle('is-glass', isPump && on);

    ring.style.backgroundColor = isPump ? 'transparent' : (on ? 'rgba(34,197,94,0.14)' : '#F3F4F6');
    pulse.style.backgroundColor = (!isPump && on) ? '#22C55E' : 'transparent';
    pulse.classList.toggle('animate-ping', !isPump && on);
    icon.classList.toggle('text-primary', on && !isPump);
    icon.classList.toggle('text-sky-500', on && isPump);
    icon.classList.toggle('text-neutral-300', !on);

    modeBtns.forEach((btn) => {
      const isActive = btn.dataset.modeBtn === mode;
      btn.classList.toggle('bg-white', isActive);
      btn.classList.toggle('shadow-sm', isActive);
      btn.classList.toggle('text-text-primary', isActive);
      btn.classList.toggle('text-text-secondary', !isActive);
    });

    const isManual = mode === 'manual';
    toggleBtn.disabled = !isManual;
    toggleLabel.textContent = on ? 'Turn Off' : 'Turn On';
    modeNote.textContent = isManual
      ? 'Manual mode: use the button above to switch it on or off yourself.'
      : 'Automatic mode: the system decides based on live sensor readings.';

    const updatedEl = document.getElementById('actuatorUpdated');
    if (updatedEl) updatedEl.querySelector('span').textContent = updatedAt ? 'Last changed ' + formatAgo(updatedAt) : '';
  }

  function refreshActuatorState() {
    fetch('api/device-control.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok || !activeActuator) return;
        const on = !!data.state[activeActuator + '_on'];
        const mode = data.state[activeActuator + '_mode'];
        const actuatorOffline = document.getElementById('actuatorOfflineNote');
        actuatorOffline.classList.toggle('hidden', !!data.state.esp32_connected);
        actuatorOffline.classList.toggle('flex', !data.state.esp32_connected);
        renderActuatorState(on, mode, data.state.updated_at);
      })
      .catch(() => {});
  }

  modeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!activeActuator) return;
      fetch('api/device-control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ device: activeActuator, mode: btn.dataset.modeBtn }),
      }).then(refreshActuatorState).catch(() => {});
    });
  });

  toggleBtn.addEventListener('click', () => {
    if (!activeActuator) return;
    const turningOn = toggleLabel.textContent === 'Turn On';
    fetch('api/device-control.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ device: activeActuator, on: turningOn }),
    }).then(refreshActuatorState).catch(() => {});
  });

  setInterval(() => {
    if (!activeActuator) return;
    refreshActuatorState();
    if (activeActuator === 'pump') loadIrrigationStatus();
    if (activeActuator === 'fan') refreshVentState();
  }, 2000);

  // ---- Ceiling vent control — separate device from the fan/pump/lamp
  // actuator card above, but same manual/automatic pattern. ----
  const ventModeBtns = document.querySelectorAll('[data-vent-mode-btn]');
  const ventToggleBtn = document.getElementById('ventToggleBtn');
  const ventToggleLabel = document.getElementById('ventToggleLabel');
  const ventStateDot = document.getElementById('ventStateDot');
  const ventStateText = document.getElementById('ventStateText');
  const ventStateBadge = document.getElementById('ventStateBadge');
  const ventModeNote = document.getElementById('ventModeNote');

  function renderVentState(open, mode) {
    document.getElementById('ventCard').classList.toggle('is-open', open);
    ventStateText.textContent = open ? 'Open' : 'Closed';
    ventStateBadge.classList.toggle('bg-light', open);
    ventStateBadge.classList.toggle('text-secondary', open);
    ventStateBadge.classList.toggle('bg-neutral-100', !open);
    ventStateBadge.classList.toggle('text-neutral-500', !open);
    ventStateDot.classList.toggle('bg-primary', open);
    ventStateDot.classList.toggle('bg-neutral-400', !open);

    ventModeBtns.forEach((btn) => {
      const isActive = btn.dataset.ventModeBtn === mode;
      btn.classList.toggle('bg-white', isActive);
      btn.classList.toggle('shadow-sm', isActive);
      btn.classList.toggle('text-text-primary', isActive);
      btn.classList.toggle('text-text-secondary', !isActive);
    });

    const isManual = mode === 'manual';
    ventToggleBtn.disabled = !isManual;
    ventToggleLabel.textContent = open ? 'Close' : 'Open';
    ventModeNote.textContent = isManual
      ? 'Manual mode: use the button above to open or close it yourself.'
      : 'Automatic mode: opens when temperature ≥ 30°C, closes once it cools back down.';
  }

  function refreshVentState() {
    fetch('api/device-control.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        const ventOffline = document.getElementById('ventOfflineNote');
        ventOffline.classList.toggle('hidden', !!data.state.esp32_connected);
        ventOffline.classList.toggle('flex', !data.state.esp32_connected);
        renderVentState(!!data.state.vent_on, data.state.vent_mode);
      })
      .catch(() => {});
  }

  ventModeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      fetch('api/device-control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ device: 'vent', mode: btn.dataset.ventModeBtn }),
      }).then(refreshVentState).catch(() => {});
    });
  });

  ventToggleBtn.addEventListener('click', () => {
    const turningOn = ventToggleLabel.textContent === 'Open';
    fetch('api/device-control.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ device: 'vent', on: turningOn }),
    }).then(refreshVentState).catch(() => {});
  });

  // ---- Irrigation: last watered / next watering / schedule ----
  function formatDateTime(mysqlDatetime) {
    const d = new Date(mysqlDatetime.replace(' ', 'T'));
    const today = new Date();
    const isToday = d.toDateString() === today.toDateString();
    const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    return (isToday ? 'Today, ' : d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ') + time;
  }

  let scheduleInputsDirty = false;
  const scheduleEnabledInput = document.getElementById('scheduleEnabledInput');
  const scheduleTimeInput = document.getElementById('scheduleTimeInput');
  const scheduleDurationInput = document.getElementById('scheduleDurationInput');
  [scheduleEnabledInput, scheduleTimeInput, scheduleDurationInput].forEach((el) => {
    el.addEventListener('input', () => { scheduleInputsDirty = true; });
  });

  function loadIrrigationStatus() {
    fetch('api/irrigation-status.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;

        const lastEl = document.getElementById('lastWateredText');
        if (data.last_watered) {
          const w = data.last_watered;
          if (w.ended_at) {
            const minutes = Math.round(w.duration_seconds / 60);
            lastEl.textContent = formatDateTime(w.started_at) + ' (' + (minutes < 1 ? '<1' : minutes) + ' min)';
          } else {
            lastEl.textContent = 'Watering now — started ' + formatDateTime(w.started_at);
          }
        } else {
          lastEl.textContent = 'No watering recorded yet';
        }

        document.getElementById('nextWateringText').textContent = data.next_watering
          ? formatDateTime(data.next_watering)
          : 'No schedule set';

        // Don't clobber what the user is actively typing/toggling.
        if (!scheduleInputsDirty && data.schedule) {
          scheduleEnabledInput.checked = !!data.schedule.enabled;
          scheduleTimeInput.value = data.schedule.scheduled_time.slice(0, 5);
          scheduleDurationInput.value = data.schedule.duration_minutes;
        }
      })
      .catch(() => {});
  }

  document.getElementById('scheduleSaveBtn').addEventListener('click', () => {
    fetch('api/irrigation-schedule.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        enabled: scheduleEnabledInput.checked,
        scheduled_time: scheduleTimeInput.value,
        duration_minutes: parseInt(scheduleDurationInput.value, 10) || 15,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        scheduleInputsDirty = false;
        const note = document.getElementById('scheduleSavedNote');
        note.classList.remove('hidden');
        setTimeout(() => note.classList.add('hidden'), 2000);
        loadIrrigationStatus();
      })
      .catch(() => {});
  });

})();
</script>
<script src="js/dashboard-app.js?v=1"></script>
</body>
</html>
