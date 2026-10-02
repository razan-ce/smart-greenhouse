// Greenhouse Planner — standalone page, independent of predict.php/
// predict-ui.js/predict-scene.js (no shared state, no shared DOM ids).
// The visual is a real 3D greenhouse (greenhouse-planner-scene.js, Three.js)
// driven through window.GPScene — this file owns all state/math (grid
// geometry, spacing conflicts, stats) and never touches Three.js objects
// directly, same separation predict-ui.js/predict-scene.js use. Loaded as
// type="module" so it runs after the scene module (document order).

(function () {
  // Real per-user data injected by greenhouse-planner.php (from the
  // greenhouses/devices/sensor_data tables) — { greenhouseName, landSize,
  // cropType, deviceStatus, sensor } with sensor only present when a real
  // device is Online. Never fabricate a "live" reading when this is null.
  const REAL = window.GH_REAL_DATA || {};

  // ---------- Reference data ----------
  // kgPerPlant/suitability/spacingCm are realistic per-plant reference
  // values (not simulation output) — this page doesn't share a crop
  // knowledge base with predict.php since it's a separate tool.
  const VEGGIES = {
    tomato: { label: 'Tomato', emoji: '🍅', spacingCm: 50, spacingLabel: '45 x 60 cm', growthLabel: '70–90 days', kgPerPlant: 0.7, best: ['summer', 'spring'], ok: ['autumn'] },
    cucumber: { label: 'Cucumber', emoji: '🥒', spacingCm: 60, spacingLabel: '60 x 60 cm', growthLabel: '50–70 days', kgPerPlant: 1.0, best: ['summer'], ok: ['spring'] },
    lettuce: { label: 'Lettuce', emoji: '🥬', spacingCm: 30, spacingLabel: '30 x 30 cm', growthLabel: '30–45 days', kgPerPlant: 0.25, best: ['autumn', 'winter', 'spring'], ok: ['summer'] },
    pepper: { label: 'Bell Pepper', emoji: '🌶️', spacingCm: 47, spacingLabel: '45 x 50 cm', growthLabel: '60–80 days', kgPerPlant: 0.4, best: ['summer'], ok: ['spring', 'autumn'] },
    spinach: { label: 'Spinach', emoji: '🌿', spacingCm: 25, spacingLabel: '25 x 25 cm', growthLabel: '25–40 days', kgPerPlant: 0.15, best: ['autumn', 'winter', 'spring'], ok: ['summer'] },
    strawberry: { label: 'Strawberry', emoji: '🍓', spacingCm: 35, spacingLabel: '30 x 40 cm', growthLabel: '60–90 days', kgPerPlant: 0.15, best: ['spring', 'autumn'], ok: ['winter'] },
    eggplant: { label: 'Eggplant', emoji: '🍆', spacingCm: 50, spacingLabel: '45 x 55 cm', growthLabel: '70–85 days', kgPerPlant: 0.6, best: ['summer'], ok: ['spring'] },
    broccoli: { label: 'Broccoli', emoji: '🥦', spacingCm: 45, spacingLabel: '40 x 45 cm', growthLabel: '60–80 days', kgPerPlant: 0.5, best: ['autumn', 'winter'], ok: ['spring'] },
    carrot: { label: 'Carrot', emoji: '🥕', spacingCm: 8, spacingLabel: '5 x 8 cm', growthLabel: '60–75 days', kgPerPlant: 0.08, best: ['spring', 'autumn'], ok: ['winter'] },
    onion: { label: 'Onion', emoji: '🧅', spacingCm: 10, spacingLabel: '8 x 10 cm', growthLabel: '100–120 days', kgPerPlant: 0.1, best: ['spring', 'autumn'], ok: ['winter'] },
    greenbean: { label: 'Green Beans', emoji: '🫘', spacingCm: 15, spacingLabel: '10 x 15 cm', growthLabel: '50–60 days', kgPerPlant: 0.2, best: ['summer', 'spring'], ok: ['autumn'] },
    corn: { label: 'Corn', emoji: '🌽', spacingCm: 30, spacingLabel: '25 x 30 cm', growthLabel: '65–85 days', kgPerPlant: 0.3, best: ['summer'], ok: ['spring'] },
    watermelon: { label: 'Watermelon', emoji: '🍉', spacingCm: 180, spacingLabel: '150 x 180 cm', growthLabel: '80–90 days', kgPerPlant: 4.5, best: ['summer'], ok: ['spring'] },
  };

  // Suitability tiers, same convention as the Prediction page's planning
  // panel — derived from real seasonal fit, not arbitrary per-crop numbers.
  const FIT_SUITABILITY = { best: 96, ok: 82, poor: 58 };
  const FIT_YIELD_MUL = { best: 1, ok: 0.85, poor: 0.62 };
  const FIT_RANK = { best: 0, ok: 1, poor: 2 };
  function fitForSeason(crop, season) {
    const v = VEGGIES[crop];
    if (v.best.includes(season)) return 'best';
    if (v.ok.includes(season)) return 'ok';
    return 'poor';
  }

  // No fabricated sensor numbers, ever. Returns real per-field readings
  // only when a device is actually Online and reporting; any field the
  // device hasn't sent is null, not a guessed value. When nothing is
  // connected this returns isLive:false and the UI shows a plain
  // "connect a device" state instead of inventing a temperature.
  function effectiveClimate() {
    const live = REAL.deviceStatus === 'Online' && REAL.sensor;
    if (!live) return { tempC: null, humidity: null, soilMoisturePct: null, isLive: false };
    const tempC = REAL.sensor.temperature;
    const humidity = REAL.sensor.humidity;
    const soilMoisturePct = REAL.sensor.soilMoisture;
    const isLive = tempC != null || humidity != null || soilMoisturePct != null;
    return { tempC, humidity, soilMoisturePct, isLive };
  }

  const SEASON_LABELS = { spring: 'Spring', summer: 'Summer', autumn: 'Autumn', winter: 'Winter' };
  function seasonLabel(season) { return SEASON_LABELS[season] || season; }

  // Maps the free-text crop_type saved during onboarding (e.g. "Pepper",
  // "Tomatoes") onto a VEGGIES key, so the planner can start pre-armed
  // with what the user actually told us they're growing.
  const CROP_TYPE_MAP = {
    tomato: 'tomato', tomatoes: 'tomato',
    cucumber: 'cucumber', cucumbers: 'cucumber',
    lettuce: 'lettuce',
    pepper: 'pepper', peppers: 'pepper', 'bell pepper': 'pepper',
    spinach: 'spinach',
    strawberry: 'strawberry', strawberries: 'strawberry',
    eggplant: 'eggplant', aubergine: 'eggplant',
    broccoli: 'broccoli',
    carrot: 'carrot', carrots: 'carrot',
    onion: 'onion', onions: 'onion',
    'green bean': 'greenbean', 'green beans': 'greenbean', beans: 'greenbean',
    corn: 'corn', maize: 'corn',
    watermelon: 'watermelon',
  };
  function mapCropType(raw) {
    if (!raw) return null;
    return CROP_TYPE_MAP[String(raw).trim().toLowerCase()] || null;
  }

  // Real saved greenhouse footprint (m²) becomes the planner's actual
  // starting dimensions instead of a generic placeholder — split into a
  // length x width close to the site's usual ~1.6:1 aspect ratio.
  function dimsFromLandSize(landSizeM2) {
    const width = Math.max(2, Math.round(Math.sqrt(landSizeM2 / 1.6)));
    const length = Math.max(2, Math.round(landSizeM2 / width));
    return { length, width };
  }
  const realDims = REAL.landSize ? dimsFromLandSize(REAL.landSize) : null;
  const realCrop = mapCropType(REAL.cropType);

  const CELL_M = 0.5; // 50cm grid cells
  const CELL_CM = CELL_M * 100;

  // ---------- State ----------
  const state = {
    lengthM: realDims ? realDims.length : 8,
    widthM: realDims ? realDims.width : 5,
    season: 'summer',
    cols: 0,
    rows: 0,
    armedCrop: null,
    searchTerm: '',
    // placements: Map key "col,row" -> cropKey
    placements: new Map(),
  };

  // ---------- DOM refs ----------
  const vegListEl = document.getElementById('gpVegList');
  const vegSearchEl = document.getElementById('gpVegSearch');
  const seasonSegEl = document.getElementById('gpSeasonSeg');
  const sensorTempEl = document.getElementById('gpSensorTemp');
  const sensorHumidityEl = document.getElementById('gpSensorHumidity');
  const sensorSoilEl = document.getElementById('gpSensorSoil');
  const stageEl = document.getElementById('gpStage3D');
  const canvasEl = document.getElementById('gpCanvas3D');
  const lengthInput = document.getElementById('gpLength');
  const widthInput = document.getElementById('gpWidth');
  const resetBtn = document.getElementById('gpResetBtn');
  const clearBtn = document.getElementById('gpClearBtn');
  const generateBtn = document.getElementById('gpGenerateBtn');
  const reviewGridEl = document.getElementById('gpReviewGrid');

  const statPlaced = document.getElementById('gpStatPlaced');
  const statUtilPct = document.getElementById('gpStatUtilPct');
  const statUtilBar = document.getElementById('gpStatUtilBar');
  const statHarvest = document.getElementById('gpStatHarvest');

  const modalBackdrop = document.getElementById('gpPlanModal');
  const modalBody = document.getElementById('gpPlanModalBody');
  const modalClose = document.getElementById('gpPlanModalClose');
  const modalPrintBtn = document.getElementById('gpPlanPrintBtn');

  if (!canvasEl || !vegListEl || !window.GPScene) return; // page not present

  // ---------- Grid geometry ----------
  // The outer ring of cells is a walkway, not plantable — same idea as a
  // real greenhouse needing a border path, and it's why Available Area is
  // always less than the full Greenhouse Size.
  function recomputeGridDims() {
    state.cols = Math.max(3, Math.floor(state.widthM / CELL_M));
    state.rows = Math.max(3, Math.floor(state.lengthM / CELL_M));
  }
  function isBorderCell(col, row) {
    return col === 0 || row === 0 || col === state.cols - 1 || row === state.rows - 1;
  }
  function plantableCells() {
    const cells = [];
    for (let r = 0; r < state.rows; r++) {
      for (let c = 0; c < state.cols; c++) {
        if (!isBorderCell(c, r)) cells.push([c, r]);
      }
    }
    return cells;
  }

  // ---------- Spacing / conflict math ----------
  // Two plants conflict if the real-world distance between their cell
  // centers is less than the more space-hungry crop's required spacing.
  function distanceCm(colA, rowA, colB, rowB) {
    const dx = (colA - colB) * CELL_CM;
    const dy = (rowA - rowB) * CELL_CM;
    return Math.sqrt(dx * dx + dy * dy);
  }
  function requiredSpacingCm(cropA, cropB) {
    return Math.max(VEGGIES[cropA].spacingCm, VEGGIES[cropB].spacingCm);
  }
  function conflictsFor(col, row) {
    const key = `${col},${row}`;
    const crop = state.placements.get(key);
    if (!crop) return [];
    const out = [];
    state.placements.forEach((otherCrop, otherKey) => {
      if (otherKey === key) return;
      const [oc, or_] = otherKey.split(',').map(Number);
      const dist = distanceCm(col, row, oc, or_);
      if (dist < requiredSpacingCm(crop, otherCrop)) out.push(otherKey);
    });
    return out;
  }
  function hasConflict(col, row) {
    return conflictsFor(col, row).length > 0;
  }

  // sizeScale feeds the 3D scene's plant/ring size — bigger real-world
  // spacing (e.g. cucumber) renders visibly larger than a compact crop
  // (e.g. spinach), same idea as the AI Planning Panel's bordered circles.
  function placementsForScene() {
    const out = [];
    state.placements.forEach((crop, key) => {
      const [col, row] = key.split(',').map(Number);
      out.push({
        col, row, crop,
        conflict: hasConflict(col, row),
        sizeScale: Math.max(0.6, Math.min(3, VEGGIES[crop].spacingCm / CELL_CM)),
      });
    });
    return out;
  }

  // ---------- Derived stats ----------
  function computeStats() {
    const plantable = plantableCells();
    const totalPlantable = plantable.length;
    const placedCount = state.placements.size;
    const utilizationPct = totalPlantable ? Math.round((placedCount / totalPlantable) * 100) : 0;

    let conflictCells = 0;
    let poorSeasonFitCount = 0;
    const uniqueCrops = new Set();
    let harvestKg = 0;
    state.placements.forEach((crop, key) => {
      uniqueCrops.add(crop);
      const fit = fitForSeason(crop, state.season);
      if (fit === 'poor') poorSeasonFitCount += 1;
      harvestKg += VEGGIES[crop].kgPerPlant * FIT_YIELD_MUL[fit];
      const [c, r] = key.split(',').map(Number);
      if (hasConflict(c, r)) conflictCells += 1;
    });

    const climate = effectiveClimate();

    // Sunlight coverage: driven by real seasonal daylight/intensity, then
    // knocked down by crowding.
    const seasonSunlightBase = { summer: 96, spring: 90, autumn: 82, winter: 68 }[state.season];
    const sunlightPct = Math.max(50, Math.min(99, seasonSunlightBase - conflictCells * 6 - Math.max(0, utilizationPct - 85)));

    // Water efficiency and disease risk use the real sensor reading when
    // a device is actually connected (soil moisture drifting from the
    // ~60% ideal band, humidity as a fungal-risk driver). With no device,
    // there's no real climate number to react to — fall back to pure
    // spacing/crowding math instead of guessing a soil-moisture percentage.
    let waterEffPct;
    let diseaseScore = conflictCells * 2;
    if (climate.isLive) {
      const idealSoilPct = 60;
      const soilDelta = climate.soilMoisturePct != null ? Math.abs(climate.soilMoisturePct - idealSoilPct) : 0;
      waterEffPct = Math.max(55, Math.min(98, 96 - soilDelta * 0.8 - conflictCells * 4));
      if (climate.humidity != null) diseaseScore += climate.humidity > 75 ? 3 : climate.humidity > 65 ? 1 : 0;
    } else {
      waterEffPct = Math.max(60, Math.min(98, 98 - conflictCells * 5));
    }
    const diseaseRisk = diseaseScore >= 5 ? 'High' : diseaseScore >= 2 ? 'Medium' : 'Low';
    const nutrientBalance = uniqueCrops.size >= 3 ? 'Optimal' : uniqueCrops.size === 2 ? 'Good' : placedCount > 0 ? 'Poor' : '—';

    return {
      totalPlantable, placedCount, utilizationPct,
      conflictCells, poorSeasonFitCount, uniqueCropCount: uniqueCrops.size, harvestKg,
      sunlightPct, waterEffPct, diseaseRisk, nutrientBalance, climate,
    };
  }

  // ---------- Rendering ----------
  // Vegetables are ranked by how well suited they are to the selected
  // season (best fit first) — the AI is "choosing based on season", not
  // just alphabetically listing everything.
  function renderVegList() {
    const term = state.searchTerm;
    const keys = Object.keys(VEGGIES)
      .filter((k) => !term || k.includes(term) || VEGGIES[k].label.toLowerCase().includes(term))
      .sort((a, b) => FIT_RANK[fitForSeason(a, state.season)] - FIT_RANK[fitForSeason(b, state.season)]);

    if (!keys.length) {
      vegListEl.innerHTML = `<p style="font-size:0.78rem;color:#9CA3AF;padding:8px 4px;">No vegetables match "${term.replace(/</g, '&lt;')}".</p>`;
      return;
    }

    vegListEl.innerHTML = keys.map((key) => {
      const v = VEGGIES[key];
      const fit = fitForSeason(key, state.season);
      const suit = FIT_SUITABILITY[fit];
      const armed = key === state.armedCrop ? ' is-armed' : '';
      const poorTag = fit === 'poor' ? ' <span class="gp-veg-offseason">off-season</span>' : '';
      return `
        <button type="button" class="gp-veg-row${armed}" data-crop="${key}" draggable="true">
          <span class="gp-veg-emoji">${v.emoji}</span>
          <span class="gp-veg-info">
            <span class="gp-veg-name">${v.label}${poorTag}</span>
            <span class="gp-veg-meta">${v.spacingLabel} &nbsp;·&nbsp; ${v.growthLabel}</span>
          </span>
          <span class="gp-veg-suit">${suit}%</span>
          <span class="gp-veg-drag-handle"><i data-lucide="grip-vertical"></i></span>
        </button>`;
    }).join('');

    vegListEl.querySelectorAll('.gp-veg-row').forEach((btn) => {
      const key = btn.dataset.crop;
      btn.addEventListener('click', () => armCrop(key));
      btn.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('text/plain', key);
        e.dataTransfer.effectAllowed = 'copy';
      });
    });
    window.lucide?.createIcons();
  }

  // Same honesty rule as the main dashboard: show real numbers when a
  // device is actually connected, otherwise show "--" placeholders — never
  // a guessed temperature/humidity/soil-moisture percentage.
  function renderSensors() {
    const c = effectiveClimate();
    if (sensorTempEl) sensorTempEl.textContent = c.tempC != null ? `${Math.round(c.tempC)}°C` : '--';
    if (sensorHumidityEl) sensorHumidityEl.textContent = c.humidity != null ? `${Math.round(c.humidity)}%` : '--';
    if (sensorSoilEl) sensorSoilEl.textContent = c.soilMoisturePct != null ? `${Math.round(c.soilMoisturePct)}%` : '--';
    document.getElementById('gpSensorRow')?.classList.toggle('is-live', c.isLive);
    const caption = document.getElementById('gpSensorCaption');
    if (caption) {
      caption.innerHTML = c.isLive
        ? `<i data-lucide="wifi"></i> Live from your ESP32`
        : `<i data-lucide="wifi-off"></i> No device connected — <a href="connect-device.php">connect one</a> for live readings`;
      window.lucide?.createIcons();
    }
  }

  // Pushes the current placements into the 3D scene without touching the
  // greenhouse shell/grid markers — used after every plant add/remove.
  function syncScene() {
    window.GPScene.updatePlants(placementsForScene());
  }

  function renderStats() {
    const s = computeStats();

    statPlaced.textContent = `${s.placedCount} / ${s.totalPlantable}`;
    statUtilPct.textContent = `${s.utilizationPct}%`;
    statUtilBar.style.width = `${s.utilizationPct}%`;
    const harvestLow = Math.max(0, Math.round(s.harvestKg * 0.85 * 10) / 10);
    const harvestHigh = Math.round(s.harvestKg * 1.15 * 10) / 10;
    statHarvest.textContent = s.placedCount ? `${harvestLow}–${harvestHigh} kg` : '0 kg';

    renderReview(s);
    return s;
  }

  function renderReview(s) {
    const perfectSpacing = s.conflictCells === 0;
    const goodAirflow = s.utilizationPct <= 85;
    const items = [
      {
        ok: perfectSpacing,
        label: 'Perfect spacing',
        detail: perfectSpacing ? 'All plants are optimally spaced.' : `${s.conflictCells} plant${s.conflictCells === 1 ? '' : 's'} too close together.`,
      },
      {
        ok: goodAirflow,
        label: 'Airflow',
        detail: goodAirflow ? 'Good ventilation across all zones.' : 'Density is high — spread plants out more.',
      },
      {
        ok: s.sunlightPct >= 80,
        label: 'Sunlight coverage',
        badge: s.placedCount ? `${s.sunlightPct}%` : '—',
        detail: `${s.placedCount ? s.sunlightPct : 0}% of plants get optimal sunlight — ${seasonLabel(state.season)} daylight, adjusted for crowding.`,
      },
      {
        ok: s.waterEffPct >= 80,
        label: 'Water efficiency',
        badge: s.placedCount ? `${s.waterEffPct}%` : '—',
        detail: s.climate.isLive && s.climate.soilMoisturePct != null
          ? `Your soil moisture sensor reads ${Math.round(s.climate.soilMoisturePct)}% right now.`
          : `No device connected — based on spacing and layout only. Connect a device for soil-moisture-aware readings.`,
      },
      {
        ok: s.diseaseRisk === 'Low',
        label: 'Disease risk',
        badge: s.diseaseRisk,
        detail: s.climate.isLive && s.climate.humidity != null
          ? `Live humidity is ${Math.round(s.climate.humidity)}%, plus ${s.conflictCells} spacing conflict${s.conflictCells === 1 ? '' : 's'}.`
          : `No device connected — based on ${s.conflictCells} spacing conflict${s.conflictCells === 1 ? '' : 's'} only.`,
      },
      {
        ok: s.nutrientBalance === 'Optimal' || s.nutrientBalance === 'Good',
        label: 'Nutrient balance',
        badge: s.nutrientBalance,
        detail: s.uniqueCropCount >= 2 ? 'Mixed crops draw on different soil nutrients.' : 'A single crop can deplete nutrients unevenly.',
      },
    ];

    reviewGridEl.innerHTML = items.map((item) => `
      <div class="gp-review-item">
        <div class="gp-review-item-left">
          <span class="gp-review-check${item.ok ? '' : ' is-warn'}"><i data-lucide="${item.ok ? 'check' : 'alert-triangle'}"></i></span>
          <div>
            <p class="gp-review-label">${item.label}</p>
            <p class="gp-review-detail">${item.detail}</p>
          </div>
        </div>
        ${item.badge ? `<span class="gp-review-badge${item.ok ? '' : ' is-warn'}">${item.badge}</span>` : ''}
      </div>
    `).join('');
    window.lucide?.createIcons();
  }

  // ---------- Actions ----------
  function armCrop(key) {
    state.armedCrop = state.armedCrop === key ? null : key;
    renderVegList();
  }

  function placeCrop(crop, col, row) {
    const key = `${col},${row}`;
    if (state.placements.has(key)) {
      state.placements.delete(key); // clicking an occupied spot clears it
    } else {
      state.placements.set(key, crop);
    }
    syncScene();
    renderStats();
  }

  function onCellClick(col, row) {
    const key = `${col},${row}`;
    if (state.placements.has(key)) {
      state.placements.delete(key);
      syncScene();
      renderStats();
      return;
    }
    if (!state.armedCrop) return;
    placeCrop(state.armedCrop, col, row);
  }

  function rebuildGrid() {
    recomputeGridDims();
    // Drop any placements that fell outside the new grid bounds.
    const next = new Map();
    state.placements.forEach((crop, key) => {
      const [c, r] = key.split(',').map(Number);
      if (c > 0 && r > 0 && c < state.cols - 1 && r < state.rows - 1) next.set(key, crop);
    });
    state.placements = next;
    window.GPScene.rebuildAll(state.cols, state.rows, CELL_M, state.lengthM, state.widthM, placementsForScene());
    renderStats();
  }

  // ---------- Wiring ----------
  vegSearchEl?.addEventListener('input', () => {
    state.searchTerm = vegSearchEl.value.trim().toLowerCase();
    renderVegList();
  });

  seasonSegEl?.querySelectorAll('.gp-seg-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.classList.contains('is-active')) return;
      seasonSegEl.querySelectorAll('.gp-seg-btn').forEach((b) => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      state.season = btn.dataset.value;
      renderSensors();
      renderVegList();
      renderStats();
    });
  });

  lengthInput?.addEventListener('input', () => {
    state.lengthM = Math.max(2, Number(lengthInput.value) || 2);
    rebuildGrid();
  });
  widthInput?.addEventListener('input', () => {
    state.widthM = Math.max(2, Number(widthInput.value) || 2);
    rebuildGrid();
  });

  resetBtn?.addEventListener('click', () => {
    // Resets to the user's real saved greenhouse footprint/crop when one
    // exists — that's the actual "default" for this account, not a
    // generic placeholder.
    state.lengthM = realDims ? realDims.length : 8;
    state.widthM = realDims ? realDims.width : 5;
    state.season = 'summer';
    if (lengthInput) lengthInput.value = String(state.lengthM);
    if (widthInput) widthInput.value = String(state.widthM);
    seasonSegEl?.querySelectorAll('.gp-seg-btn').forEach((b) => b.classList.toggle('is-active', b.dataset.value === 'summer'));
    state.placements.clear();
    state.armedCrop = realCrop;
    renderSensors();
    renderVegList();
    rebuildGrid();
  });
  clearBtn?.addEventListener('click', () => {
    state.placements.clear();
    syncScene();
    renderStats();
  });

  function openModal(s) {
    const counts = {};
    state.placements.forEach((crop) => { counts[crop] = (counts[crop] || 0) + 1; });
    const cropRows = Object.keys(counts).map((k) => `<div class="gp-modal-row"><span>${VEGGIES[k].emoji} ${VEGGIES[k].label}</span><span>${counts[k]}</span></div>`).join('') || '<p style="color:#9CA3AF;">No plants placed yet.</p>';

    modalBody.innerHTML = `
      <h4>Layout</h4>
      ${REAL.greenhouseName ? `<div class="gp-modal-row"><span>Greenhouse</span><span>${REAL.greenhouseName}</span></div>` : ''}
      <div class="gp-modal-row"><span>Season</span><span>${seasonLabel(state.season)}</span></div>
      <div class="gp-modal-row"><span>Utilization</span><span>${s.utilizationPct}%</span></div>

      <h4>Plants</h4>
      ${cropRows}
      ${s.poorSeasonFitCount ? `<p style="color:#DC2626;font-size:0.76rem;margin-top:6px;">${s.poorSeasonFitCount} plant${s.poorSeasonFitCount === 1 ? ' is' : 's are'} off-season for ${seasonLabel(state.season)}.</p>` : ''}

      <h4>${s.climate.isLive ? 'Live Sensors' : 'Sensors'}</h4>
      ${s.climate.isLive ? `
      <div class="gp-modal-row"><span>Temperature</span><span>${s.climate.tempC != null ? Math.round(s.climate.tempC) + '°C' : '--'}</span></div>
      <div class="gp-modal-row"><span>Humidity</span><span>${s.climate.humidity != null ? Math.round(s.climate.humidity) + '%' : '--'}</span></div>
      <div class="gp-modal-row"><span>Soil moisture</span><span>${s.climate.soilMoisturePct != null ? Math.round(s.climate.soilMoisturePct) + '%' : '--'}</span></div>
      ` : `<p style="color:#9CA3AF;font-size:0.8rem;">No device connected — <a href="connect-device.php">connect one</a> for live readings.</p>`}

      <h4>AI Notes</h4>
      <div class="gp-modal-row"><span>Spacing conflicts</span><span>${s.conflictCells}</span></div>
      <div class="gp-modal-row"><span>Estimated harvest</span><span>${statHarvest.textContent}</span></div>
    `;
    modalBackdrop.classList.add('is-open');
    window.lucide?.createIcons();
  }
  function closeModal() { modalBackdrop.classList.remove('is-open'); }

  generateBtn?.addEventListener('click', () => openModal(computeStats()));
  modalClose?.addEventListener('click', closeModal);
  modalBackdrop?.addEventListener('click', (e) => { if (e.target === modalBackdrop) closeModal(); });
  modalPrintBtn?.addEventListener('click', () => window.print());

  // ---------- Drag-and-drop onto the 3D stage ----------
  // pickCell() does the same camera raycast the scene uses for clicks, so
  // a drop lands on exactly the spot under the cursor.
  stageEl?.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; });
  stageEl?.addEventListener('drop', (e) => {
    e.preventDefault();
    const crop = e.dataTransfer.getData('text/plain');
    if (!crop || !VEGGIES[crop]) return;
    const cell = window.GPScene.pickCell(e.clientX, e.clientY);
    if (cell) placeCrop(crop, cell.col, cell.row);
  });

  // ---------- Boot ----------
  if (lengthInput) lengthInput.value = String(state.lengthM);
  if (widthInput) widthInput.value = String(state.widthM);
  state.armedCrop = realCrop;
  window.GPScene.init(canvasEl);
  window.GPScene.setClickHandler(onCellClick);
  renderSensors();
  renderVegList();
  rebuildGrid();
})();
