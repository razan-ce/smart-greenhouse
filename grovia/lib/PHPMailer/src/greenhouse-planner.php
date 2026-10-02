<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_worker_access();
if ($user['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
require_once __DIR__ . '/partials/current-user.php';

// There's one shared greenhouse now, owned by the Admin — every granted
// worker sees the same one, not a per-user row. Never fabricate a "live"
// reading — only report real sensor data, only when it's actually fresh.
$db = get_db();
$myGreenhouse = $db->query('SELECT greenhouse_id, greenhouse_name, land_size, crop_type FROM greenhouses ORDER BY created_at ASC LIMIT 1')->fetch() ?: null;

// Same 3-second freshness rule api/sensor-latest.php uses — a reading
// older than that is treated as the device being offline, not "live."
$latestSensor = $db->query(
    'SELECT temperature_value, humidity_value, soil_value, recorded_at, '
    . 'TIMESTAMPDIFF(SECOND, recorded_at, NOW()) AS age_seconds '
    . 'FROM sensor_readings ORDER BY id DESC LIMIT 1'
)->fetch();
$isLive = $latestSensor && (int) $latestSensor['age_seconds'] <= 3;

$ghRealData = [
    'greenhouseName' => $myGreenhouse['greenhouse_name'] ?? null,
    'landSize' => isset($myGreenhouse['land_size']) ? (float) $myGreenhouse['land_size'] : null,
    'cropType' => $myGreenhouse['crop_type'] ?? null,
    'deviceStatus' => $isLive ? 'Online' : 'Offline',
    'sensor' => $isLive ? [
        'temperature' => $latestSensor['temperature_value'] !== null ? (float) $latestSensor['temperature_value'] : null,
        'humidity' => $latestSensor['humidity_value'] !== null ? (float) $latestSensor['humidity_value'] : null,
        'soilMoisture' => $latestSensor['soil_value'] !== null ? (float) $latestSensor['soil_value'] : null,
        'recordedAt' => $latestSensor['recorded_at'] ?? null,
    ] : null,
];

// Same split the JS uses (dimsFromLandSize) — computed here too so the
// server-rendered inputs already show the right numbers before JS boots.
$defaultLengthM = 8;
$defaultWidthM = 5;
if ($ghRealData['landSize']) {
    $defaultWidthM = max(2, round(sqrt($ghRealData['landSize'] / 1.6)));
    $defaultLengthM = max(2, round($ghRealData['landSize'] / $defaultWidthM));
}

$gh_page_title = 'Greenhouse Planner | Grovia';
$gh_extra_head = '<link rel="stylesheet" href="css/greenhouse-planner.css?v=1">
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1800px] px-5 py-6 sm:px-8 lg:px-8 lg:py-7">
    <div class="flex flex-col gap-5">
  <div class="flex flex-wrap items-center justify-between gap-3">      
<div>
    <nav class="gp-crumbs">
      <a href="dashboard.php">Home</a>
      <i data-lucide="chevron-right"></i>
      <span>Greenhouse Planner</span>
    </nav>
    <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Greenhouse Planner</h1>
    <p class="mt-1 max-w-2xl text-[15px] text-text-secondary"
    >Drag vegetables into the grid — GreenMind AI validates spacing, airflow, and yield in real time.</p>
  </div>
<div class="flex flex-wrap items-center gap-2">
  <span class="gp-status-pill">
    <i data-lucide="wifi-off"></i>
    No Device Connected
  </span>
  <span class="gp-status-pill">
    <i data-lucide="check-circle-2"></i>
    AI Analysis Complete
  </span>
</div>


</div>  
<div class="gp-stat-bar">
    <div class="gp-stat">
        <div class="gp-stat-head"><i data-lucide="sprout"></i>
        <span>Plants placed</span></div>
        <div class="gp-stat-value" id="gpStatPlaced">0 / 0</div>
    </div>
    <div class="gp-stat gp-stat-wide">
        <div class="gp-stat-head"><i data-lucide="gauge"></i>
        <span>Utilization</span></div>
        <div class="gp-stat-value" id="gpStatUtilPct">0%</div>
        <div class="gp-bar"><div class="gp-bar-fill" id="gpStatUtilBar" style="width:0%"></div></div>
    </div>
    <div class="gp-stat">
        <div class="gp-stat-head"><i data-lucide="wheat"></i><span>Est. Harvest</span></div>
        <div class="gp-stat-value" id="gpStatHarvest">0 kg</div>
    </div>
</div>
<div class="gp-workspace">
 <aside class="gp-panel gp-veg-panel">
    <h2 class="gp-panel-title">Select &amp; Plant Vegetables</h2>
 <p class="gp-panel-sub">Click a vegetable, then click a grid cell to plant it</p>
  <p class="gp-field-group-title">Season</p>
<div class="gp-seg" id="gpSeasonSeg">
  <button type="button" class="gp-seg-btn" data-value="spring">Spring</button>
  <button type="button" class="gp-seg-btn is-active" data-value="summer">Summer</button>
  <button type="button" class="gp-seg-btn" data-value="autumn">Autumn</button>
  <button type="button" class="gp-seg-btn" data-value="winter">Winter</button>
</div>

<div class="gp-sensor-row" id="gpSensorRow">
  <div class="gp-sensor-chip"><i data-lucide="thermometer"></i><span id="gpSensorTemp">—°C</span></div>
  <div class="gp-sensor-chip"><i data-lucide="droplets"></i><span id="gpSensorHumidity">—%</span></div>
  <div class="gp-sensor-chip"><i data-lucide="waves"></i><span id="gpSensorSoil">—%</span></div>
</div>
<p class="gp-sensor-caption" id="gpSensorCaption"></p>

<div class="gp-search">
  <i data-lucide="search"></i>
  <input type="text" id="gpVegSearch" placeholder="Search vegetables…" autocomplete="off">
</div>

<div class="gp-veg-list" id="gpVegList"></div>
</aside> 
<div class="gp-stage-col">
    <div class="gp-panel gp-stage-panel">
     <div class="gp-stage-3d" id="gpStage3D">
  <canvas id="gpCanvas3D"></canvas>
</div>
    </div>
    <div class="gp-panel gp-toolbar-panel">
  <label class="gp-dim-field-inline"><span>Length (m)</span><input type="number" id="gpLength" min="2" max="20" value="<?= (int) $defaultLengthM ?>"></label>
  <label class="gp-dim-field-inline"><span>Width (m)</span><input type="number" id="gpWidth" min="2" max="20" value="<?= (int) $defaultWidthM ?>"></label>
  <div class="gp-toolbar-spacer"></div>
  <button type="button" class="gp-ctrl-btn" id="gpResetBtn"><i data-lucide="rotate-ccw"></i><span>Reset</span></button>
  <button type="button" class="gp-ctrl-btn gp-ctrl-btn-danger" id="gpClearBtn"><i data-lucide="trash-2"></i><span>Clear All</span></button>
</div>
  </div>

</div>
<div class="gp-review-row">
    <div class="gp-panel gp-review-panel">
        <h2 class="gp-panel-title">AI Review</h2>
        <div class="gp-review-grid" id="gpReviewGrid"></div>
    </div>

    <div class="gp-cta-card">
        <div class="gp-cta-icon">
            <i data-lucide="sparkles"></i>
        </div>
        <div class="gp-cta-body">
            <h3>Generate Final Plan</h3>
            <p>Get a printable planting schedule and AI recommendations for this layout.</p>
        </div>
        <button type="button" class="gp-cta-btn" id="gpGenerateBtn" aria-label="Generate final plan">
            <i data-lucide="arrow-right"></i>
        </button>
    </div>
</div>


</div>
  </main>
</div>
<div id="gpPlanModal" class="gp-modal-backdrop">
 <div class="gp-modal">
    <div class="gp-modal-head">
      <h2>Your Greenhouse Plan</h2>
<button type="button" id="gpPlanModalClose" aria-label="Close">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="gp-modal-body" id="gpPlanModalBody"></div>
<div class="gp-modal-actions">
      <button type="button" class="gp-modal-print-btn" id="gpPlanPrintBtn">
        <i data-lucide="printer"></i> Print / Save as PDF
      </button>
    </div>
  </div>
</div>
</body>
<script>window.GH_REAL_DATA = <?= json_encode($ghRealData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script type="module" src="js/greenhouse-planner-scene.js?v=4"></script>
<script type="module" src="js/greenhouse-planner.js?v=4"></script>
</html>
