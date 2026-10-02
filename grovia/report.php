<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_worker_access();
if ($user['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
require_once __DIR__ . '/partials/current-user.php';

$db = get_db();
$userId = (int) $user['user_id'];


$stmt = $db->prepare('SELECT * FROM ai_insights WHERE user_id = ? ORDER BY generated_at DESC LIMIT 1');
$stmt->execute([$userId]);
$insight = $stmt->fetch();


$sensorRows = $db->query(
    "SELECT DATE(recorded_at) AS day, ROUND(AVG(temperature_value), 1) AS avg_temp, "
    . "ROUND(AVG(humidity_value), 1) AS avg_humidity, ROUND(AVG(soil_value), 0) AS avg_soil "
    . "FROM sensor_readings WHERE recorded_at >= NOW() - INTERVAL 7 DAY "
    . "GROUP BY DATE(recorded_at) ORDER BY day ASC"
)->fetchAll();

$chartLabels = [];
$chartTemp = [];
$chartHumidity = [];
$chartSoil = [];
foreach ($sensorRows as $r) {
    $chartLabels[] = (new DateTime($r['day']))->format('D j');
    $chartTemp[] = $r['avg_temp'] !== null ? (float) $r['avg_temp'] : null;
    $chartHumidity[] = $r['avg_humidity'] !== null ? (float) $r['avg_humidity'] : null;
    $chartSoil[] = $r['avg_soil'] !== null ? (float) $r['avg_soil'] : null;
}


$latestWater = $db->query('SELECT water_level_value FROM sensor_readings WHERE water_level_value IS NOT NULL ORDER BY recorded_at DESC LIMIT 1')->fetchColumn();
$waterStatus = null;
if ($latestWater !== false) {
    $waterPct = max(0, min(100, round(((float) $latestWater / 900) * 100)));
    $waterStatus = $waterPct >= 50 ? 'Full' : 'Empty';
}

$weekStart = (new DateTime('monday this week'))->format('Y-m-d');
$weekEnd = (new DateTime('sunday this week'))->format('Y-m-d');
$taskStmt = $db->prepare(
    "SELECT status, COUNT(*) AS n FROM schedule_tasks WHERE user_id = ? AND scheduled_date BETWEEN ? AND ? GROUP BY status"
);
$taskStmt->execute([$userId, $weekStart, $weekEnd]);
$taskCounts = ['Pending' => 0, 'Completed' => 0, 'Skipped' => 0];
foreach ($taskStmt->fetchAll() as $r) {
    $taskCounts[$r['status']] = (int) $r['n'];
}
$totalTasks = array_sum($taskCounts);
$completionPct = $totalTasks > 0 ? round(($taskCounts['Completed'] / $totalTasks) * 100) : null;


$alertStmt = $db->prepare('SELECT title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
$alertStmt->execute([$userId]);
$recentAlerts = $alertStmt->fetchAll();

$gh_page_title = 'Weekly Report | Grovia';
$gh_extra_head = '<link rel="stylesheet" href="css/report.css?v=1">';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[900px] px-5 py-6 sm:px-8 lg:px-8 lg:py-7">
    <div class="flex flex-col gap-6">

      <div class="noprint flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Weekly Report</h1>
          <p class="mt-1 text-[15px] text-text-secondary">A summary of your greenhouse, ready to print or save as PDF.</p>
        </div>
        <button type="button" id="printReportBtn" class="flex items-center gap-2 rounded-xl bg-green-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-glow transition-all active:scale-95">
          <i data-lucide="printer" class="h-4 w-4"></i> Print / Save as PDF
        </button>
      </div>

      <div id="reportCard" class="report-card rounded-2xl border border-border/60 bg-white p-8 shadow-soft">

        <div class="report-header">
          <div>
            <p class="report-eyebrow">Grovia Smart Greenhouse</p>
            <h2 class="report-title">Weekly Report</h2>
          </div>
          <div class="report-meta">
            <p><?= htmlspecialchars($user['full_name'] ?? $user['email'], ENT_QUOTES) ?></p>
            <p><?= (new DateTime($weekStart))->format('M j') ?> – <?= (new DateTime($weekEnd))->format('M j, Y') ?></p>
            <p>Generated <?= date('M j, Y g:ia') ?></p>
          </div>
        </div>

        <section class="report-section">
          <h3>AI Summary</h3>
          <?php if ($insight): ?>
            <p class="report-insight-headline"><?= htmlspecialchars($insight['headline'], ENT_QUOTES) ?></p>
            <p class="report-body-text"><?= nl2br(htmlspecialchars($insight['summary'], ENT_QUOTES)) ?></p>
            <div class="report-recommendation">
              <strong>Recommendation:</strong> <?= htmlspecialchars($insight['recommendation'], ENT_QUOTES) ?>
            </div>
            <p class="report-caption">Generated <?= (new DateTime($insight['generated_at']))->format('M j, Y g:ia') ?></p>
          <?php else: ?>
            <p class="report-body-text">No AI Insight has been generated yet — visit the Dashboard and refresh the AI Insights card first, then reload this report.</p>
          <?php endif; ?>
        </section>

        <section class="report-section">
          <h3>Sensor Trends — Last 7 Days</h3>
          <?php if ($sensorRows): ?>
            <div class="report-chart-wrap"><canvas id="reportTrendChart"></canvas></div>
          <?php else: ?>
            <p class="report-body-text">No sensor readings recorded in the last 7 days.</p>
          <?php endif; ?>
          <?php if ($waterStatus !== null): ?>
            <p class="report-caption">Water reservoir, current status: <strong><?= $waterStatus ?></strong></p>
          <?php endif; ?>
        </section>

        <section class="report-section">
          <h3>This Week's Schedule</h3>
          <?php if ($totalTasks > 0): ?>
            <div class="report-task-stats">
              <div><span class="report-task-num"><?= $taskCounts['Completed'] ?></span><span>Completed</span></div>
              <div><span class="report-task-num"><?= $taskCounts['Pending'] ?></span><span>Pending</span></div>
              <div><span class="report-task-num"><?= $taskCounts['Skipped'] ?></span><span>Skipped</span></div>
              <div><span class="report-task-num"><?= $completionPct ?>%</span><span>Completion Rate</span></div>
            </div>
          <?php else: ?>
            <p class="report-body-text">No tasks scheduled this week.</p>
          <?php endif; ?>
        </section>

        <section class="report-section">
          <h3>Recent Alerts</h3>
          <?php if ($recentAlerts): ?>
            <ul class="report-alert-list">
              <?php foreach ($recentAlerts as $a): ?>
                <li>
                  <strong><?= htmlspecialchars($a['title'], ENT_QUOTES) ?></strong>
                  — <?= htmlspecialchars($a['message'], ENT_QUOTES) ?>
                  <span class="report-caption"><?= (new DateTime($a['created_at']))->format('M j, g:ia') ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="report-body-text">No alerts recorded recently.</p>
          <?php endif; ?>
        </section>

      </div>

    </div>
  </main>
</div>

<script>
  const printBtn = document.getElementById('printReportBtn');
  printBtn.addEventListener('click', () => window.print());

  <?php if ($sensorRows): ?>
  const ctx = document.getElementById('reportTrendChart');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [
        { label: 'Temperature (°C)', data: <?= json_encode($chartTemp) ?>, borderColor: '#EF4444', backgroundColor: '#EF4444', tension: 0.3, spanGaps: true },
        { label: 'Humidity (%)', data: <?= json_encode($chartHumidity) ?>, borderColor: '#0EA5E9', backgroundColor: '#0EA5E9', tension: 0.3, spanGaps: true },
        { label: 'Soil (raw, lower = wetter)', data: <?= json_encode($chartSoil) ?>, borderColor: '#16A34A', backgroundColor: '#16A34A', tension: 0.3, spanGaps: true },
      ],
    },
    options: {
      responsive: true,
      animation: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
      scales: { y: { ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 10 } } } },
    },
  });
  <?php endif; ?>
</script>

</body>
</html>
