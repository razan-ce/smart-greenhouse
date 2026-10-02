// Greenhouse Planner — dedicated 3D scene (Three.js/WebGL). Independent of
// predict-scene.js: separate renderer, separate greenhouse geometry, no
// shared state. Exposes window.GPScene, a small API that
// greenhouse-planner.js drives — it never reaches into Three.js objects
// directly, matching the same separation predict-ui.js/predict-scene.js use.

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const CROP_EMOJI = {
  tomato: '🍅',
  cucumber: '🥒',
  lettuce: '🥬',
  pepper: '🌶️',
  spinach: '🌿',
  strawberry: '🍓',
  eggplant: '🍆',
  broccoli: '🥦',
  carrot: '🥕',
  onion: '🧅',
  greenbean: '🫘',
  corn: '🌽',
  watermelon: '🍉',
};

// Cached per-emoji canvas textures — built once, reused for every plant of
// that crop, so replanting/rebuilding the grid doesn't redraw the canvas.
const emojiTextureCache = new Map();
function getEmojiTexture(emoji) {
  if (emojiTextureCache.has(emoji)) return emojiTextureCache.get(emoji);
  const size = 128;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  ctx.font = `${Math.round(size * 0.72)}px "Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(emoji, size / 2, size / 2 + size * 0.04);
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  emojiTextureCache.set(emoji, texture);
  return texture;
}

const materials = {
  frame: new THREE.MeshStandardMaterial({ color: 0xb8bec7, metalness: 0.85, roughness: 0.32 }),
  glass: new THREE.MeshPhysicalMaterial({
    color: 0xeaf7ef, transparent: true, opacity: 0.2,
    roughness: 0.05, metalness: 0, transmission: 0.92, thickness: 0.08,
    ior: 1.4, side: THREE.DoubleSide,
  }),
  soil: new THREE.MeshStandardMaterial({ color: 0x3b2a1e, roughness: 0.95 }),
  fanGrille: new THREE.MeshStandardMaterial({ color: 0x1f2937, roughness: 0.6, side: THREE.DoubleSide }),
  fanBlade: new THREE.MeshStandardMaterial({ color: 0x4b5563, roughness: 0.45, metalness: 0.35, side: THREE.DoubleSide }),
  sensorBox: new THREE.MeshStandardMaterial({ color: 0x0f2e1e, roughness: 0.5, metalness: 0.2 }),
  sensorLed: new THREE.MeshStandardMaterial({ color: 0x22c55e, emissive: 0x22c55e, emissiveIntensity: 1.4, roughness: 0.4 }),
  probeStake: new THREE.MeshStandardMaterial({ color: 0x6b7280, roughness: 0.4, metalness: 0.5 }),
  probeTip: new THREE.MeshStandardMaterial({ color: 0x0ea5e9, roughness: 0.35, metalness: 0.3 }),
};

let renderer, scene, camera, controls, canvasEl, stageEl;
let greenhouseGroup = null;
let markersGroup = null;
let floorGridGroup = null;
let plantsGroup = null;

let cellSize = 0.5;
let gridCols = 0;
let gridRows = 0;
let gridLengthM = 8;
let gridWidthM = 5;

let fanBladeGroups = [];
let clickHandler = null;
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const floorPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
let downX = 0;
let downY = 0;

function clearGroup(group) {
  if (!group) return;
  while (group.children.length) {
    const child = group.children.pop();
    child.traverse?.((obj) => {
      obj.geometry?.dispose?.();
    });
  }
}

// A wall-mounted ventilation fan: a ring housing, a dark grille disc, and
// a 4-blade pinwheel (built from 3-sided "circles" — the cheapest way to
// get a blade silhouette without a custom shape). Blades spin in animate().
function buildFan(x, y, z) {
  const fanGroup = new THREE.Group();
  fanGroup.position.set(x, y, z);

  const housing = new THREE.Mesh(new THREE.TorusGeometry(0.22, 0.03, 8, 20), materials.frame);
  fanGroup.add(housing);

  const grille = new THREE.Mesh(new THREE.CircleGeometry(0.2, 20), materials.fanGrille);
  grille.position.z = -0.015;
  fanGroup.add(grille);

  const bladeGroup = new THREE.Group();
  for (let i = 0; i < 4; i += 1) {
    const blade = new THREE.Mesh(new THREE.CircleGeometry(0.17, 3), materials.fanBlade);
    blade.rotation.z = (Math.PI / 2) * i + Math.PI / 4;
    bladeGroup.add(blade);
  }
  fanGroup.add(bladeGroup);
  fanBladeGroups.push(bladeGroup);

  greenhouseGroup.add(fanGroup);
}

// A wall-mounted ESP32-style sensor unit (temp/humidity/soil) — a small
// dark box with a status LED and a short antenna, same visual language as
// the real device shown on the Prediction page.
function buildSensorUnit(x, y, z, rotY) {
  const group = new THREE.Group();
  group.position.set(x, y, z);
  group.rotation.y = rotY;

  const box = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.11, 0.04), materials.sensorBox);
  group.add(box);

  const led = new THREE.Mesh(new THREE.SphereGeometry(0.008, 8, 8), materials.sensorLed);
  led.position.set(0.05, 0.03, 0.022);
  group.add(led);

  const antenna = new THREE.Mesh(new THREE.CylinderGeometry(0.003, 0.003, 0.07, 6), materials.frame);
  antenna.position.set(-0.05, 0.09, 0);
  group.add(antenna);

  greenhouseGroup.add(group);
}

// A soil-moisture probe stake pushed into the bed — two thin prongs and a
// small blue sensor head, standing on its own (not tied to any one plant).
function buildSoilProbe(x, z) {
  const group = new THREE.Group();
  group.position.set(x, 0, z);

  const stakeGeo = new THREE.CylinderGeometry(0.006, 0.006, 0.16, 6);
  [-0.012, 0.012].forEach((dx) => {
    const stake = new THREE.Mesh(stakeGeo, materials.probeStake);
    stake.position.set(dx, 0.08, 0);
    group.add(stake);
  });

  const head = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.03, 0.03), materials.probeTip);
  head.position.y = 0.175;
  group.add(head);

  greenhouseGroup.add(group);
}

// Builds a corner-exact quad from 4 world-space points — avoids fiddly
// rotation math for the sloped roof panels (a PlaneGeometry rotated into
// place is error-prone; explicit corners are guaranteed correct).
function quadMesh(p1, p2, p3, p4, material) {
  const geo = new THREE.BufferGeometry();
  const positions = new Float32Array([...p1, ...p2, ...p3, ...p4]);
  geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  geo.setIndex([0, 1, 2, 0, 2, 3]);
  geo.computeVertexNormals();
  return new THREE.Mesh(geo, material);
}
function triMesh(p1, p2, p3, material) {
  const geo = new THREE.BufferGeometry();
  const positions = new Float32Array([...p1, ...p2, ...p3]);
  geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  geo.computeVertexNormals();
  return new THREE.Mesh(geo, material);
}

function buildGreenhouse(lengthM, widthM) {
  if (greenhouseGroup) { scene.remove(greenhouseGroup); clearGroup(greenhouseGroup); }
  greenhouseGroup = new THREE.Group();
  fanBladeGroups = [];

  const wallH = 1.6;
  const roofH = 1.0;
  const halfL = lengthM / 2;
  const halfW = widthM / 2;

  const floor = new THREE.Mesh(new THREE.BoxGeometry(widthM, 0.05, lengthM), materials.soil);
  floor.position.y = -0.025;
  floor.receiveShadow = true;
  greenhouseGroup.add(floor);

  // Walls
  const wallPositions = [
    { geo: new THREE.PlaneGeometry(widthM, wallH), pos: [0, wallH / 2, -halfL], rotY: 0 },
    { geo: new THREE.PlaneGeometry(widthM, wallH), pos: [0, wallH / 2, halfL], rotY: Math.PI },
    { geo: new THREE.PlaneGeometry(lengthM, wallH), pos: [-halfW, wallH / 2, 0], rotY: Math.PI / 2 },
    { geo: new THREE.PlaneGeometry(lengthM, wallH), pos: [halfW, wallH / 2, 0], rotY: -Math.PI / 2 },
  ];
  wallPositions.forEach(({ geo, pos, rotY }) => {
    const wall = new THREE.Mesh(geo, materials.glass);
    wall.position.set(...pos);
    wall.rotation.y = rotY;
    greenhouseGroup.add(wall);
  });

  // Gable roof — explicit corners, no rotation guesswork.
  const ridgeY = wallH + roofH;
  const leftRoof = quadMesh(
    [-halfW, wallH, -halfL], [-halfW, wallH, halfL], [0, ridgeY, halfL], [0, ridgeY, -halfL],
    materials.glass,
  );
  const rightRoof = quadMesh(
    [halfW, wallH, -halfL], [0, ridgeY, -halfL], [0, ridgeY, halfL], [halfW, wallH, halfL],
    materials.glass,
  );
  greenhouseGroup.add(leftRoof, rightRoof);

  // Gable end triangles (front/back), otherwise there's a gap under the ridge.
  const frontGable = triMesh([-halfW, wallH, -halfL], [halfW, wallH, -halfL], [0, ridgeY, -halfL], materials.glass);
  const backGable = triMesh([halfW, wallH, halfL], [-halfW, wallH, halfL], [0, ridgeY, halfL], materials.glass);
  greenhouseGroup.add(frontGable, backGable);

  // Frame: corner posts + ridge beam + base perimeter.
  [[-halfW, -halfL], [halfW, -halfL], [-halfW, halfL], [halfW, halfL]].forEach(([x, z]) => {
    const post = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, wallH, 8), materials.frame);
    post.position.set(x, wallH / 2, z);
    post.castShadow = true;
    greenhouseGroup.add(post);
  });
  const ridge = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, lengthM, 8), materials.frame);
  ridge.rotation.x = Math.PI / 2;
  ridge.position.set(0, ridgeY, 0);
  greenhouseGroup.add(ridge);

  const baseFrontBack = new THREE.BoxGeometry(widthM, 0.06, 0.06);
  [-halfL, halfL].forEach((z) => {
    const bar = new THREE.Mesh(baseFrontBack, materials.frame);
    bar.position.set(0, 0.03, z);
    greenhouseGroup.add(bar);
  });
  const baseSides = new THREE.BoxGeometry(0.06, 0.06, lengthM);
  [-halfW, halfW].forEach((x) => {
    const bar = new THREE.Mesh(baseSides, materials.frame);
    bar.position.set(x, 0.03, 0);
    greenhouseGroup.add(bar);
  });

  // Twin exhaust fans on the back wall, clamped so they never crowd the
  // corner posts on a narrow greenhouse.
  const fanOffsetX = Math.min(widthM * 0.22, Math.max(0.35, halfW - 0.35));
  const fanY = wallH * 0.68;
  const fanZ = -halfL + 0.02;
  buildFan(-fanOffsetX, fanY, fanZ);
  buildFan(fanOffsetX, fanY, fanZ);

  // Sensor hardware: a wall-mounted temp/humidity unit on the left wall,
  // and a soil-moisture probe stake in the bed — these simulated readings
  // are what actually drive the AI Review's water/disease/sunlight math.
  buildSensorUnit(-halfW + 0.025, wallH * 0.72, -halfL * 0.35, Math.PI / 2);
  buildSoilProbe(-widthM * 0.15, halfL * 0.5);

  buildFloorGrid();

  scene.add(greenhouseGroup);
}

function cellWorldPos(col, row) {
  return {
    x: -gridWidthM / 2 + (col + 0.5) * cellSize,
    z: -gridLengthM / 2 + (row + 0.5) * cellSize,
  };
}

// Lined planting bed: thin furrow strips between every row/column of the
// plantable area, plus a bolder frame around the whole bed — turns the
// floor from a blank slab into an organized grid you can actually read,
// like tilled garden rows. Built from real (thin) flat meshes rather than
// WebGL line primitives — line width is capped at ~1px on most browsers
// regardless of material settings, which made an earlier LineSegments
// version essentially invisible.
// Raised wooden bed-divider strips (real height, not a flat decal) — an
// opaque box catches light and casts a soft shadow, so it reads clearly
// from any camera angle instead of depending on exact color contrast.
function buildFloorGrid() {
  floorGridGroup = new THREE.Group();
  const stripH = 0.03;
  const y = stripH / 2;
  const lineW = Math.max(0.035, cellSize * 0.07);
  const lineMat = new THREE.MeshStandardMaterial({ color: 0xc9a876, roughness: 0.75 });
  const frameMat = new THREE.MeshStandardMaterial({ color: 0x8a6a42, roughness: 0.7 });

  const left = -gridWidthM / 2 + cellSize;
  const right = gridWidthM / 2 - cellSize;
  const front = -gridLengthM / 2 + cellSize;
  const back = gridLengthM / 2 - cellSize;
  const bedWidth = right - left;
  const bedLength = back - front;

  for (let c = 1; c < gridCols - 1; c += 1) {
    const x = -gridWidthM / 2 + c * cellSize;
    const strip = new THREE.Mesh(new THREE.BoxGeometry(lineW, stripH, bedLength), lineMat);
    strip.position.set(x, y, (front + back) / 2);
    strip.castShadow = true;
    floorGridGroup.add(strip);
  }
  for (let r = 1; r < gridRows - 1; r += 1) {
    const z = -gridLengthM / 2 + r * cellSize;
    const strip = new THREE.Mesh(new THREE.BoxGeometry(bedWidth, stripH, lineW), lineMat);
    strip.position.set((left + right) / 2, y, z);
    strip.castShadow = true;
    floorGridGroup.add(strip);
  }

  const frameW = lineW * 1.7;
  const frameH = stripH * 1.4;
  [
    { w: bedWidth + frameW, d: frameW, x: (left + right) / 2, z: front },
    { w: bedWidth + frameW, d: frameW, x: (left + right) / 2, z: back },
    { w: frameW, d: bedLength + frameW, x: left, z: (front + back) / 2 },
    { w: frameW, d: bedLength + frameW, x: right, z: (front + back) / 2 },
  ].forEach(({ w, d, x, z }) => {
    const strip = new THREE.Mesh(new THREE.BoxGeometry(w, frameH, d), frameMat);
    strip.position.set(x, frameH / 2, z);
    strip.castShadow = true;
    floorGridGroup.add(strip);
  });

  greenhouseGroup.add(floorGridGroup);
}

function buildMarkers() {
  markersGroup = new THREE.Group();
  const ringGeo = new THREE.RingGeometry(cellSize * 0.3, cellSize * 0.36, 20);
  const ringMat = new THREE.MeshBasicMaterial({ color: 0x9ca3af, transparent: true, opacity: 0.22, side: THREE.DoubleSide });
  for (let r = 0; r < gridRows; r += 1) {
    for (let c = 0; c < gridCols; c += 1) {
      if (c === 0 || r === 0 || c === gridCols - 1 || r === gridRows - 1) continue;
      const { x, z } = cellWorldPos(c, r);
      const ring = new THREE.Mesh(ringGeo, ringMat);
      ring.rotation.x = -Math.PI / 2;
      ring.position.set(x, 0.002, z);
      markersGroup.add(ring);
    }
  }
  greenhouseGroup.add(markersGroup);
}

// placements: [{ col, row, crop, conflict, sizeScale }]
function buildPlants(placements) {
  if (plantsGroup) { greenhouseGroup.remove(plantsGroup); clearGroup(plantsGroup); }
  plantsGroup = new THREE.Group();

  placements.forEach(({ col, row, crop, conflict, sizeScale }) => {
    const { x, z } = cellWorldPos(col, row);
    // The ring scales fully with real spacing (up to 3x — a watermelon's
    // ~180cm footprint should look dramatically bigger than lettuce's
    // ~30cm), but the plant mesh itself is dampened (sqrt) so a big crop
    // reads as "needs room" without rendering as a giant sphere.
    const scale = sizeScale || 1;
    const plantScale = Math.min(1.9, Math.sqrt(scale) * 1.05);
    const group = new THREE.Group();
    group.position.set(x, 0, z);

    const ringGeo = new THREE.RingGeometry(cellSize * 0.34 * scale, cellSize * 0.44 * scale, 24);
    const ringMat = new THREE.MeshBasicMaterial({ color: conflict ? 0xef4444 : 0x22c55e, transparent: true, opacity: 0.55, side: THREE.DoubleSide });
    const ring = new THREE.Mesh(ringGeo, ringMat);
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = 0.003;
    group.add(ring);

    // The vegetable is a flat, 2D emoji icon sitting directly on the soil
    // inside its spacing ring — no stem/stick, just like a real seedling
    // marker on a garden bed plan.
    const spriteMat = new THREE.SpriteMaterial({ map: getEmojiTexture(CROP_EMOJI[crop] || '🌱'), transparent: true, depthTest: false, depthWrite: false });
    const sprite = new THREE.Sprite(spriteMat);
    const spriteSize = 0.5 * plantScale;
    sprite.scale.set(spriteSize, spriteSize, 1);
    sprite.position.y = 0.012;
    sprite.renderOrder = 999;
    group.add(sprite);

    plantsGroup.add(group);
  });

  greenhouseGroup.add(plantsGroup);
}

function resize() {
  if (!stageEl) return;
  const rect = stageEl.getBoundingClientRect();
  renderer.setSize(rect.width, rect.height, false);
  camera.aspect = rect.width / Math.max(1, rect.height);
  camera.updateProjectionMatrix();
}

function animate() {
  requestAnimationFrame(animate);
  controls.update();
  fanBladeGroups.forEach((bg) => { bg.rotation.z += 0.09; });
  renderer.render(scene, camera);
}

function pickCell(clientX, clientY) {
  const rect = canvasEl.getBoundingClientRect();
  pointer.x = ((clientX - rect.left) / rect.width) * 2 - 1;
  pointer.y = -((clientY - rect.top) / rect.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const hit = new THREE.Vector3();
  if (!raycaster.ray.intersectPlane(floorPlane, hit)) return null;
  const col = Math.floor((hit.x + gridWidthM / 2) / cellSize);
  const row = Math.floor((hit.z + gridLengthM / 2) / cellSize);
  if (col <= 0 || row <= 0 || col >= gridCols - 1 || row >= gridRows - 1) return null;
  return { col, row };
}

function init(canvas) {
  canvasEl = canvas;
  stageEl = canvas.parentElement;

  renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0xeefaf1, 0.02);

  camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
  camera.position.set(4.2, 2.9, 4.8);

  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

  controls = new OrbitControls(camera, renderer.domElement);
  controls.target.set(0, 1, 0);
  controls.enableDamping = true;
  controls.dampingFactor = 0.07;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 0.5;
  controls.minDistance = 3;
  controls.maxDistance = 15;
  controls.maxPolarAngle = Math.PI * 0.49;
  controls.enablePan = false;

  const hemi = new THREE.HemisphereLight(0xbfd9ff, 0x1a2332, 0.6);
  scene.add(hemi);
  const sun = new THREE.DirectionalLight(0xfff4e0, 1.3);
  sun.position.set(6, 8, 4);
  sun.castShadow = true;
  sun.shadow.mapSize.set(1024, 1024);
  sun.shadow.camera.left = -6;
  sun.shadow.camera.right = 6;
  sun.shadow.camera.top = 6;
  sun.shadow.camera.bottom = -6;
  scene.add(sun);

  // Safari can leave the canvas holding pointer capture (set internally by
  // OrbitControls on pointerdown) if the matching pointerup/pointercancel
  // doesn't resolve cleanly — that silently swallows every click on the
  // rest of the page. Force-release it ourselves as a safety net.
  const releaseCapture = (e) => {
    try { if (canvas.hasPointerCapture?.(e.pointerId)) canvas.releasePointerCapture(e.pointerId); } catch (err) { /* no-op */ }
  };
  canvas.addEventListener('pointerdown', (e) => {
    downX = e.clientX;
    downY = e.clientY;
    controls.autoRotate = false;
  });
  canvas.addEventListener('pointerup', (e) => {
    const moved = Math.hypot(e.clientX - downX, e.clientY - downY);
    if (moved < 6) {
      const cell = pickCell(e.clientX, e.clientY);
      if (cell) clickHandler?.(cell.col, cell.row);
    }
    releaseCapture(e);
  });
  canvas.addEventListener('pointercancel', releaseCapture);

  new ResizeObserver(resize).observe(stageEl);
  resize();
  animate();
}

function rebuildAll(cols, rows, cellM, lengthM, widthM, placements) {
  gridCols = cols;
  gridRows = rows;
  cellSize = cellM;
  gridLengthM = lengthM;
  gridWidthM = widthM;
  buildGreenhouse(lengthM, widthM);
  buildMarkers();
  buildPlants(placements);
}

function updatePlants(placements) {
  buildPlants(placements);
}

window.GPScene = {
  init,
  rebuildAll,
  updatePlants,
  pickCell,
  setClickHandler(fn) { clickHandler = fn; },
  debugInfo() {
    const info = {
      cameraPos: camera.position.toArray(),
      plantsGroupChildren: plantsGroup ? plantsGroup.children.length : 0,
      floorGridChildren: floorGridGroup ? floorGridGroup.children.length : 0,
      plants: [],
    };
    plantsGroup?.children.forEach((g) => {
      info.plants.push({
        pos: g.position.toArray(),
        parts: g.children.map((c) => ({ type: c.type, isSprite: c.isSprite || false, visible: c.visible, scale: c.scale.toArray(), posY: c.position.y, hasMap: !!(c.material && c.material.map) })),
      });
    });
    return info;
  },
};
