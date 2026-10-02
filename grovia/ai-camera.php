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
$stmt = $db->prepare('SELECT id, image_path, crop_name, status, disease_name, confidence, severity, affected_area_pct, recommendation, detected_at FROM disease_detections WHERE user_id = ? ORDER BY detected_at DESC LIMIT 12');
$stmt->execute([$user['user_id']]);
$history = $stmt->fetchAll();

$gh_page_title = 'AI Camera | Grovia';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1100px] px-5 py-6 sm:px-8 lg:px-8 lg:py-7">
    <div class="flex flex-col gap-6">

      <div>
        <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
          <i data-lucide="camera" class="h-3.5 w-3.5"></i>
          AI Plant Vision
        </div>
        <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">AI Camera</h1>
        <p class="mt-1 max-w-2xl text-[15px] text-text-secondary">Detects plant disease in real time and provides accurate analysis and treatment suggestions. Using your laptop's camera until a physical ESP32-CAM is connected.</p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- Capture panel -->
        <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
          <h3 class="text-sm font-semibold text-text-primary">Capture</h3>
          <p class="mt-1 text-xs text-text-secondary">Point your camera at a leaf or plant, or upload a photo instead.</p>

          <div class="relative mt-4 aspect-square w-full overflow-hidden rounded-2xl bg-neutral-900">
            <video id="cameraVideo" autoplay playsinline muted class="h-full w-full object-cover"></video>
            <canvas id="cameraCanvas" class="hidden h-full w-full object-cover"></canvas>
            <div id="cameraPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-neutral-400">
              <i data-lucide="camera-off" class="h-8 w-8"></i>
              <p class="text-xs">Camera not started</p>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="button" id="cameraStartBtn" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-white px-4 py-2.5 text-sm font-medium text-text-secondary transition-all hover:bg-neutral-50">
              <i data-lucide="video" class="h-4 w-4"></i> Start Camera
            </button>
            <button type="button" id="cameraCaptureBtn" class="hidden inline-flex items-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-medium text-white shadow-sm">
              <i data-lucide="aperture" class="h-4 w-4"></i> Capture Photo
            </button>
            <button type="button" id="cameraRetakeBtn" class="hidden inline-flex items-center gap-2 rounded-xl border border-border/70 bg-white px-4 py-2.5 text-sm font-medium text-text-secondary transition-all hover:bg-neutral-50">
              <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Retake
            </button>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-border/70 bg-white px-4 py-2.5 text-sm font-medium text-text-secondary transition-all hover:bg-neutral-50">
              <i data-lucide="upload" class="h-4 w-4"></i> Upload Instead
              <input type="file" id="cameraFileInput" accept="image/jpeg,image/png,image/webp" class="hidden">
            </label>
          </div>

          <button type="button" id="cameraAnalyzeBtn" disabled class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-green-gradient px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all disabled:cursor-not-allowed disabled:opacity-40">
            <i data-lucide="sparkles" class="h-4 w-4"></i>
            <span id="cameraAnalyzeLabel">Capture or upload a photo first</span>
          </button>
        </div>

        <!-- Result panel -->
        <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
          <h3 class="text-sm font-semibold text-text-primary">Detection Result</h3>
          <div id="resultEmpty" class="mt-10 flex flex-col items-center gap-2 text-center text-text-secondary">
            <i data-lucide="scan-search" class="h-8 w-8 opacity-40"></i>
            <p class="text-xs">Analyze a photo to see the AI's diagnosis here.</p>
          </div>

          <div id="resultContent" class="hidden mt-4 flex flex-col gap-4">
            <div class="flex items-center gap-3">
              <span class="rounded-full px-3 py-1.5 text-xs font-semibold" id="resultStatusBadge">—</span>
              <span class="text-xs text-text-secondary" id="resultCrop"></span>
            </div>
            <div>
              <p class="text-lg font-semibold text-text-primary" id="resultDisease">—</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
                <p class="text-[11px] font-medium text-text-secondary">Confidence</p>
                <p class="mt-1 text-xl font-semibold text-text-primary" id="resultConfidence">—</p>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-200">
                  <div class="h-full rounded-full bg-green-gradient transition-all duration-700" id="resultConfidenceBar" style="width:0%"></div>
                </div>
              </div>
              <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
                <p class="text-[11px] font-medium text-text-secondary">Affected Area</p>
                <p class="mt-1 text-xl font-semibold text-text-primary" id="resultArea">—</p>
              </div>
            </div>
            <div class="flex items-center gap-2 text-xs">
              <span class="font-medium text-text-secondary">Severity:</span>
              <span class="rounded-full px-2.5 py-1 font-semibold" id="resultSeverityBadge">—</span>
            </div>
            <div class="rounded-2xl bg-light p-4">
              <p class="flex items-center gap-1.5 text-xs font-semibold text-secondary"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Recommendation</p>
              <p class="mt-1.5 text-sm text-text-primary" id="resultRecommendation"></p>
            </div>
          </div>
        </div>
      </div>

      <!-- History -->
      <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
        <h3 class="text-sm font-semibold text-text-primary">Recent Detections</h3>
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4" id="historyGrid">
          <?php if (empty($history)): ?>
          <p class="col-span-full py-6 text-center text-xs text-text-secondary" id="historyEmpty">No detections yet — capture or upload a photo above to get started.</p>
          <?php endif; ?>
          <?php foreach ($history as $h):
            $statusColor = $h['status'] === 'Healthy' ? 'bg-light text-secondary' : (in_array($h['status'], ['Disease Detected', 'Pest Damage'], true) ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600');
            $cardData = json_encode([
                'id' => (int) $h['id'],
                'image_path' => $h['image_path'],
                'crop_name' => $h['crop_name'],
                'status' => $h['status'],
                'disease_name' => $h['disease_name'],
                'confidence' => $h['confidence'] !== null ? (float) $h['confidence'] : null,
                'severity' => $h['severity'],
                'affected_area_pct' => $h['affected_area_pct'] !== null ? (float) $h['affected_area_pct'] : null,
                'recommendation' => $h['recommendation'],
                'detected_at_label' => date('M j, g:ia', strtotime($h['detected_at'])),
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
          ?>
          <div class="group relative cursor-pointer overflow-hidden rounded-2xl border border-border/60 transition-shadow hover:shadow-soft" data-history-card data-id="<?= (int) $h['id'] ?>" data-detection='<?= $cardData ?>'>
            <button type="button" data-history-delete aria-label="Delete detection" class="absolute right-1.5 top-1.5 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-black/55 text-white opacity-80 transition-opacity hover:bg-red-600 hover:opacity-100">
              <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
            </button>
            <img src="<?= htmlspecialchars($h['image_path'], ENT_QUOTES) ?>" alt="" class="h-28 w-full object-cover">
            <div class="p-3">
              <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $statusColor ?>"><?= htmlspecialchars($h['status'], ENT_QUOTES) ?></span>
              <p class="mt-1.5 truncate text-xs font-medium text-text-primary"><?= htmlspecialchars($h['disease_name'] ?: $h['crop_name'], ENT_QUOTES) ?></p>
              <p class="text-[10px] text-text-secondary"><?= htmlspecialchars(date('M j, g:ia', strtotime($h['detected_at'])), ENT_QUOTES) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Detection detail modal -->
<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
    <div class="flex items-start justify-between gap-3">
      <h3 class="text-sm font-semibold text-text-primary">Detection Details</h3>
      <button type="button" id="detailCloseBtn" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-text-secondary transition-colors hover:bg-neutral-100">
        <i data-lucide="x" class="h-4 w-4"></i>
      </button>
    </div>
    <img id="detailImage" src="" alt="" class="mt-4 h-48 w-full rounded-2xl bg-neutral-100 object-cover">
    <div class="mt-4 flex items-center gap-3">
      <span class="rounded-full px-3 py-1.5 text-xs font-semibold" id="detailStatusBadge">—</span>
      <span class="text-xs text-text-secondary" id="detailCrop"></span>
    </div>
    <p class="mt-3 text-lg font-semibold text-text-primary" id="detailDisease">—</p>
    <div class="mt-3 grid grid-cols-2 gap-4">
      <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
        <p class="text-[11px] font-medium text-text-secondary">Confidence</p>
        <p class="mt-1 text-xl font-semibold text-text-primary" id="detailConfidence">—</p>
        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-200">
          <div class="h-full rounded-full bg-green-gradient transition-all duration-700" id="detailConfidenceBar" style="width:0%"></div>
        </div>
      </div>
      <div class="rounded-2xl border border-border/60 bg-neutral-50 p-4">
        <p class="text-[11px] font-medium text-text-secondary">Affected Area</p>
        <p class="mt-1 text-xl font-semibold text-text-primary" id="detailArea">—</p>
      </div>
    </div>
    <div class="mt-3 flex items-center gap-2 text-xs">
      <span class="font-medium text-text-secondary">Severity:</span>
      <span class="rounded-full px-2.5 py-1 font-semibold" id="detailSeverityBadge">—</span>
    </div>
    <div class="mt-3 rounded-2xl bg-light p-4">
      <p class="flex items-center gap-1.5 text-xs font-semibold text-secondary"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Recommendation</p>
      <p class="mt-1.5 text-sm text-text-primary" id="detailRecommendation"></p>
    </div>
    <p class="mt-3 text-[11px] text-text-secondary" id="detailDate"></p>
    <button type="button" id="detailDeleteBtn" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-100">
      <i data-lucide="trash-2" class="h-4 w-4"></i> Delete This Detection
    </button>
  </div>
</div>

<script>
(function () {
  const video = document.getElementById('cameraVideo');
  const canvas = document.getElementById('cameraCanvas');
  const placeholder = document.getElementById('cameraPlaceholder');
  const startBtn = document.getElementById('cameraStartBtn');
  const captureBtn = document.getElementById('cameraCaptureBtn');
  const retakeBtn = document.getElementById('cameraRetakeBtn');
  const fileInput = document.getElementById('cameraFileInput');
  const analyzeBtn = document.getElementById('cameraAnalyzeBtn');
  const analyzeLabel = document.getElementById('cameraAnalyzeLabel');

  let stream = null;
  let capturedBlob = null;

  function setAnalyzeReady(ready, label) {
    analyzeBtn.disabled = !ready;
    analyzeLabel.textContent = label;
  }

  startBtn.addEventListener('click', async () => {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      video.srcObject = stream;
      placeholder.classList.add('hidden');
      video.classList.remove('hidden');
      canvas.classList.add('hidden');
      startBtn.classList.add('hidden');
      captureBtn.classList.remove('hidden');
    } catch (err) {
      placeholder.querySelector('p').textContent = 'Could not access camera — check permissions, or use "Upload Instead".';
    }
  });

  captureBtn.addEventListener('click', () => {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob((blob) => {
      capturedBlob = blob;
      setAnalyzeReady(true, 'Analyze This Photo');
    }, 'image/jpeg', 0.92);

    video.classList.add('hidden');
    canvas.classList.remove('hidden');
    captureBtn.classList.add('hidden');
    retakeBtn.classList.remove('hidden');
    if (stream) stream.getTracks().forEach((t) => t.stop());
  });

  retakeBtn.addEventListener('click', () => {
    capturedBlob = null;
    setAnalyzeReady(false, 'Capture or upload a photo first');
    canvas.classList.add('hidden');
    retakeBtn.classList.add('hidden');
    startBtn.classList.remove('hidden');
    placeholder.classList.remove('hidden');
    video.classList.add('hidden');
  });

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    capturedBlob = file;

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        canvas.width = img.width;
        canvas.height = img.height;
        canvas.getContext('2d').drawImage(img, 0, 0);
        canvas.classList.remove('hidden');
        video.classList.add('hidden');
        placeholder.classList.add('hidden');
        startBtn.classList.add('hidden');
        captureBtn.classList.add('hidden');
        retakeBtn.classList.remove('hidden');
        if (stream) stream.getTracks().forEach((t) => t.stop());
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    setAnalyzeReady(true, 'Analyze This Photo');
  });

  const resultEmpty = document.getElementById('resultEmpty');
  const resultContent = document.getElementById('resultContent');

  const STATUS_COLOR = {
    'Healthy': 'bg-light text-secondary',
    'Disease Detected': 'bg-red-50 text-red-600',
    'Pest Damage': 'bg-red-50 text-red-600',
    'Nutrient Deficiency': 'bg-amber-50 text-amber-600',
    'No Plant Visible': 'bg-neutral-100 text-neutral-600',
  };
  const SEVERITY_COLOR = {
    'None': 'bg-light text-secondary',
    'Mild': 'bg-amber-50 text-amber-600',
    'Moderate': 'bg-amber-100 text-amber-700',
    'Severe': 'bg-red-50 text-red-600',
  };

  function prependHistoryCard(data) {
    const grid = document.getElementById('historyGrid');
    const empty = document.getElementById('historyEmpty');
    if (empty) empty.remove();

    const card = document.createElement('div');
    card.className = 'group relative cursor-pointer overflow-hidden rounded-2xl border border-border/60 transition-shadow hover:shadow-soft';
    card.dataset.historyCard = '';
    card.dataset.id = data.id;
    card.dataset.detection = JSON.stringify({
      id: data.id,
      image_path: data.image_path,
      crop_name: data.crop_name,
      status: data.status,
      disease_name: data.disease_name,
      confidence: data.confidence,
      severity: data.severity,
      affected_area_pct: data.affected_area_pct,
      recommendation: data.recommendation,
      detected_at_label: 'Just now',
    });
    card.innerHTML =
      '<button type="button" data-history-delete aria-label="Delete detection" class="absolute right-1.5 top-1.5 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-black/55 text-white opacity-80 transition-opacity hover:bg-red-600 hover:opacity-100">' +
        '<i data-lucide="trash-2" class="h-3.5 w-3.5"></i>' +
      '</button>' +
      '<img src="' + data.image_path + '" alt="" class="h-28 w-full object-cover">' +
      '<div class="p-3">' +
        '<span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ' + (STATUS_COLOR[data.status] || 'bg-neutral-100 text-neutral-600') + '">' + data.status + '</span>' +
        '<p class="mt-1.5 truncate text-xs font-medium text-text-primary" data-history-title></p>' +
        '<p class="text-[10px] text-text-secondary">Just now</p>' +
      '</div>';
    card.querySelector('[data-history-title]').textContent = data.disease_name || data.crop_name || '—';
    grid.prepend(card);
    if (window.lucide) window.lucide.createIcons();
  }

  const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
  const detailModal = document.getElementById('detailModal');
  const detailCloseBtn = document.getElementById('detailCloseBtn');
  const detailDeleteBtn = document.getElementById('detailDeleteBtn');
  let detailCurrentId = null;

  function openDetail(d) {
    detailCurrentId = d.id;
    document.getElementById('detailImage').src = d.image_path;
    document.getElementById('detailStatusBadge').textContent = d.status;
    document.getElementById('detailStatusBadge').className = 'rounded-full px-3 py-1.5 text-xs font-semibold ' + (STATUS_COLOR[d.status] || 'bg-neutral-100 text-neutral-600');
    document.getElementById('detailCrop').textContent = d.crop_name ? 'Crop: ' + d.crop_name : '';
    document.getElementById('detailDisease').textContent = d.disease_name || (d.status === 'Healthy' ? 'No issues detected' : '—');
    document.getElementById('detailConfidence').textContent = (d.confidence !== null && d.confidence !== undefined ? Math.round(d.confidence) : '—') + '%';
    document.getElementById('detailConfidenceBar').style.width = (d.confidence || 0) + '%';
    document.getElementById('detailArea').textContent = (d.affected_area_pct !== null && d.affected_area_pct !== undefined ? Math.round(d.affected_area_pct) : 0) + '%';
    document.getElementById('detailSeverityBadge').textContent = d.severity || 'None';
    document.getElementById('detailSeverityBadge').className = 'rounded-full px-2.5 py-1 font-semibold ' + (SEVERITY_COLOR[d.severity] || 'bg-neutral-100 text-neutral-600');
    document.getElementById('detailRecommendation').textContent = d.recommendation || '';
    document.getElementById('detailDate').textContent = d.detected_at_label || '';
    detailModal.classList.remove('hidden');
    detailModal.classList.add('flex');
    if (window.lucide) window.lucide.createIcons();
  }

  function closeDetail() {
    detailModal.classList.add('hidden');
    detailModal.classList.remove('flex');
    detailCurrentId = null;
  }

  detailCloseBtn.addEventListener('click', closeDetail);
  detailModal.addEventListener('click', (e) => { if (e.target === detailModal) closeDetail(); });

  function deleteDetection(id, card) {
    if (!confirm('Delete this detection? This cannot be undone.')) return Promise.resolve(false);
    return fetch('api/disease-delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, csrf_token: CSRF_TOKEN }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) {
          alert(data.error || 'Could not delete this detection.');
          return false;
        }
        if (card) card.remove();
        const grid = document.getElementById('historyGrid');
        if (!grid.querySelector('[data-history-card]')) {
          const p = document.createElement('p');
          p.id = 'historyEmpty';
          p.className = 'col-span-full py-6 text-center text-xs text-text-secondary';
          p.textContent = 'No detections yet — capture or upload a photo above to get started.';
          grid.appendChild(p);
        }
        return true;
      })
      .catch(() => { alert('Network error — try again.'); return false; });
  }

  detailDeleteBtn.addEventListener('click', () => {
    if (detailCurrentId == null) return;
    const card = document.querySelector('[data-history-card][data-id="' + detailCurrentId + '"]');
    deleteDetection(detailCurrentId, card).then((ok) => { if (ok) closeDetail(); });
  });

  document.getElementById('historyGrid').addEventListener('click', (e) => {
    const card = e.target.closest('[data-history-card]');
    if (!card) return;
    const delBtn = e.target.closest('[data-history-delete]');
    if (delBtn) {
      e.stopPropagation();
      deleteDetection(Number(card.dataset.id), card);
      return;
    }
    const raw = card.dataset.detection;
    if (raw) openDetail(JSON.parse(raw));
  });

  analyzeBtn.addEventListener('click', () => {
    if (!capturedBlob) return;
    setAnalyzeReady(false, 'Analyzing…');

    const form = new FormData();
    form.append('photo', capturedBlob, 'capture.jpg');

    fetch('api/disease-detect.php', { method: 'POST', body: form })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) {
          setAnalyzeReady(true, data.error || 'Analysis failed, try again');
          return;
        }
        resultEmpty.classList.add('hidden');
        resultContent.classList.remove('hidden');

        document.getElementById('resultStatusBadge').textContent = data.status;
        document.getElementById('resultStatusBadge').className = 'rounded-full px-3 py-1.5 text-xs font-semibold ' + (STATUS_COLOR[data.status] || 'bg-neutral-100 text-neutral-600');
        document.getElementById('resultCrop').textContent = data.crop_name ? 'Crop: ' + data.crop_name : '';
        document.getElementById('resultDisease').textContent = data.disease_name || (data.status === 'Healthy' ? 'No issues detected' : '—');
        document.getElementById('resultConfidence').textContent = (data.confidence !== null ? Math.round(data.confidence) : '—') + '%';
        document.getElementById('resultConfidenceBar').style.width = (data.confidence || 0) + '%';
        document.getElementById('resultArea').textContent = (data.affected_area_pct !== null ? Math.round(data.affected_area_pct) : 0) + '%';
        document.getElementById('resultSeverityBadge').textContent = data.severity || 'None';
        document.getElementById('resultSeverityBadge').className = 'rounded-full px-2.5 py-1 font-semibold ' + (SEVERITY_COLOR[data.severity] || 'bg-neutral-100 text-neutral-600');
        document.getElementById('resultRecommendation').textContent = data.recommendation || '';

        setAnalyzeReady(true, 'Analyze Another Photo');
        if (window.lucide) window.lucide.createIcons();
        prependHistoryCard(data);
      })
      .catch(() => setAnalyzeReady(true, 'Network error — try again'));
  });
})();
</script>
<script src="js/dashboard-app.js?v=1"></script>
</body>
</html>
