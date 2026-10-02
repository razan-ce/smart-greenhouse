/* =========================================================
   "Inside the Grovia Greenhouse" — About page centerpiece.
   A mostly-static, drag-to-orbit 3D scene of the real greenhouse
   layout (same component positions as the landing page's scroll
   scene) with hover/click hotspots instead of a scroll narrative.
   No pinning, no scroll-jacking — this is a normal in-flow section,
   so unlike greenhouse-3d-scroll.js there's no GSAP pin-spacer
   collapse to work around: the stage element's own clientWidth/
   Height can be measured directly.
   ========================================================= */
import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

(function () {
  const section = document.getElementById('gh3dSection');
  const stage = document.getElementById('gh3dStage');
  const canvas = document.getElementById('gh3dCanvas');
  const hotspotLayer = document.getElementById('gh3dHotspots');
  const panelTitle = document.getElementById('gh3dPanelTitle');
  const panelSub = document.getElementById('gh3dPanelSub');
  const panelDesc = document.getElementById('gh3dPanelDesc');
  if (!section || !stage || !canvas) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isNarrow = window.innerWidth < 900;

  const DEFAULT_PANEL = {
    title: 'Explore the greenhouse',
    sub: 'Hover or tap a component',
    desc: 'Every sensor and actuator shown here is a real part of the Grovia hardware, positioned where it actually sits on the unit.',
  };

  const HOTSPOT_INFO = {
    esp32: { title: 'ESP32-S3-N16R8', sub: 'Central Controller', desc: 'Reads every sensor and coordinates the whole system.' },
    dht11: { title: 'DHT11', sub: 'Temperature & Humidity', desc: 'Monitors the greenhouse environment in real time.' },
    light: { title: 'Light Sensor', sub: 'Ambient Light', desc: 'Tracks how much light is reaching the plants.' },
    soil: { title: 'HW-080', sub: 'Soil Moisture', desc: 'Measures soil moisture to help determine irrigation needs.' },
    gas: { title: 'MQ-135', sub: 'Air Quality', desc: 'Watches for changes in air quality inside the greenhouse.' },
    water: { title: 'Water Level Sensor', sub: 'Tank Level', desc: 'Keeps track of the water available for irrigation.' },
    cam: { title: 'ESP32-CAM', sub: 'Plant Vision', desc: 'Captures photos used for AI-assisted plant analysis.' },
    relay: { title: 'Relay Module', sub: 'Device Control', desc: 'Switches the fan, pump and other devices on and off.' },
    fan: { title: 'Cooling Fan', sub: 'Ventilation', desc: 'Circulates air to help regulate greenhouse temperature.' },
    pump: { title: 'Water Pump', sub: 'Irrigation', desc: 'Delivers water from the tank when irrigation is triggered.' },
    servo: { title: 'Vent Servo', sub: 'Roof Mechanism', desc: 'Opens the roof vent to help manage heat and airflow.' },
  };

  const clamp01 = (v) => Math.max(0, Math.min(1, v));
  const lerp = (a, b, t) => a + (b - a) * t;

  /* ---------- Renderer / scene / camera ---------- */
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 100);
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, isNarrow ? 1.5 : 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;

  const enableShadows = !isNarrow;
  if (enableShadows) {
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  }

  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.045).texture;

  scene.add(new THREE.AmbientLight(0xbfe6c8, 0.42));
  const keyLight = new THREE.DirectionalLight(0xffffff, 1.25);
  keyLight.position.set(5, 7, 6);
  keyLight.target.position.set(0, -1, 0);
  scene.add(keyLight, keyLight.target);
  if (enableShadows) {
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(1024, 1024);
    keyLight.shadow.camera.left = -8; keyLight.shadow.camera.right = 8;
    keyLight.shadow.camera.top = 8; keyLight.shadow.camera.bottom = -8;
    keyLight.shadow.camera.near = 1; keyLight.shadow.camera.far = 24;
    keyLight.shadow.bias = -0.0015;
  }
  const rimLight = new THREE.PointLight(0x4ade80, 1.1, 30);
  rimLight.position.set(-5, 2, -4);
  scene.add(rimLight);
  const fillLight = new THREE.PointLight(0xbae6fd, 0.5, 25);
  fillLight.position.set(5, 1, 6);
  scene.add(fillLight);

  const rootGroup = new THREE.Group();
  scene.add(rootGroup);

  /* ---------- Material helpers (same recipe as the landing page's
     hardware scene, kept as an intentional parallel copy rather than a
     shared import — this page's scene is a simpler, non-scroll variant
     and duplicating ~200 lines of builders is safer than risking a
     regression on the already-shipped landing page via a refactor). ---------- */
  const mat = (color, opts = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.45, metalness: 0.25, ...opts });
  const glassMat = (color, opacity) => new THREE.MeshPhysicalMaterial({
    color, transparent: true, opacity, roughness: 0.08, metalness: 0,
    transmission: 0.82, thickness: 0.4, side: THREE.DoubleSide, envMapIntensity: 1,
  });
  const pinMat = mat(0xd4af37, { metalness: 0.9, roughness: 0.3 });

  function pinRow(count, w, x0, y, z, size = 0.03) {
    const g = new THREE.Group();
    for (let i = 0; i < count; i++) {
      const pin = new THREE.Mesh(new THREE.BoxGeometry(size, size * 1.8, size), pinMat);
      pin.position.set(x0 + (i * w) / (count - 1 || 1), y, z);
      g.add(pin);
    }
    return g;
  }

  function buildESP32() {
    const g = new THREE.Group();
    const board = new THREE.Mesh(new THREE.BoxGeometry(0.05, 1.5, 0.9), mat(0x12151a, { roughness: 0.55, metalness: 0.15 }));
    board.castShadow = board.receiveShadow = true;
    g.add(board);
    const shield = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.42, 0.42), mat(0xc7cdd4, { metalness: 0.85, roughness: 0.25 }));
    shield.position.set(0.03, 0.35, 0.05);
    g.add(shield);
    const usb = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.14, 0.22), mat(0xaeb4bb, { metalness: 0.8, roughness: 0.3 }));
    usb.position.set(0.05, -0.68, -0.3);
    g.add(usb);
    const pins1 = pinRow(19, 1.15, -0.5, 0.68, 0.42); pins1.rotation.z = Math.PI / 2; pins1.position.set(0.03, 0, 0);
    const pins2 = pins1.clone(); pins2.position.z = -0.42;
    g.add(pins1, pins2);
    return g;
  }

  function buildDHT11() {
    const g = new THREE.Group();
    const body = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.5, 0.35), mat(0x2563eb, { roughness: 0.5 }));
    body.castShadow = true;
    g.add(body);
    const grille = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.34, 0.24), mat(0xe2e8f0, { roughness: 0.7 }));
    grille.position.x = 0.11;
    g.add(grille);
    const pins = pinRow(4, 0.2, -0.09, -0.3, 0.1, 0.025); pins.rotation.x = Math.PI / 2;
    g.add(pins);
    return g;
  }

  function buildLightSensor() {
    const g = new THREE.Group();
    const pcb = new THREE.Mesh(new THREE.BoxGeometry(0.42, 0.05, 0.42), mat(0x7c3aed, { roughness: 0.5 }));
    pcb.castShadow = true;
    g.add(pcb);
    const lens = new THREE.Mesh(new THREE.SphereGeometry(0.1, 16, 16), mat(0x1e1b3a, { metalness: 0.5, roughness: 0.1, envMapIntensity: 1.2 }));
    lens.position.y = 0.1;
    g.add(lens);
    const pins = pinRow(3, 0.16, -0.08, -0.06, 0.19, 0.02);
    g.add(pins);
    return g;
  }

  function buildGasSensor() {
    const g = new THREE.Group();
    const pcb = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.05, 0.4), mat(0x1d4ed8, { roughness: 0.5 }));
    pcb.castShadow = true;
    g.add(pcb);
    const can = new THREE.Mesh(new THREE.CylinderGeometry(0.15, 0.16, 0.22, 16), mat(0xb0b6bd, { metalness: 0.8, roughness: 0.3 }));
    can.position.y = 0.135;
    can.castShadow = true;
    g.add(can);
    const grille = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 0.02, 16), mat(0x2a2a2a, { metalness: 0.3, roughness: 0.7 }));
    grille.position.y = 0.25;
    g.add(grille);
    const pins = pinRow(4, 0.24, -0.12, -0.06, 0.17, 0.02);
    g.add(pins);
    return g;
  }

  function buildSoilSensor() {
    const g = new THREE.Group();
    const board = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.55, 0.24), mat(0x15803d, { roughness: 0.55 }));
    board.position.y = 0.35;
    g.add(board);
    const chip = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.1, 0.12), mat(0x111214, { roughness: 0.4 }));
    chip.position.set(0.03, 0.55, 0);
    g.add(chip);
    [-0.06, 0.06].forEach((z) => {
      const prong = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.55, 0.05), mat(0xc0c0c8, { metalness: 0.85, roughness: 0.25 }));
      prong.position.set(0, -0.2, z);
      g.add(prong);
    });
    return g;
  }

  function buildWaterLevelSensor() {
    const g = new THREE.Group();
    const strip = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.65, 0.18), mat(0x14532d, { roughness: 0.6 }));
    g.add(strip);
    for (let i = 0; i < 4; i++) {
      const trace = new THREE.Mesh(new THREE.BoxGeometry(0.045, 0.03, 0.13), mat(0xd4af37, { metalness: 0.8, roughness: 0.35 }));
      trace.position.set(0, -0.28 + i * 0.13, 0);
      g.add(trace);
    }
    const head = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.12, 0.2), mat(0x0f172a, { roughness: 0.5 }));
    head.position.y = 0.38;
    g.add(head);
    return g;
  }

  function buildESP32Cam() {
    const g = new THREE.Group();
    const board = new THREE.Mesh(new THREE.BoxGeometry(0.32, 0.32, 0.04), mat(0x14171a, { roughness: 0.5 }));
    board.castShadow = true;
    g.add(board);
    const lensRing = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 0.03, 16), mat(0x8a8f96, { metalness: 0.8, roughness: 0.25 }));
    lensRing.rotation.x = Math.PI / 2;
    lensRing.position.z = 0.05;
    g.add(lensRing);
    const lens = new THREE.Mesh(new THREE.CylinderGeometry(0.065, 0.065, 0.05, 16), mat(0x0a0a0a, { metalness: 0.4, roughness: 0.1, envMapIntensity: 1.3 }));
    lens.rotation.x = Math.PI / 2;
    lens.position.z = 0.08;
    g.add(lens);
    const antenna = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.02, 0.16), mat(0x1d4ed8, { roughness: 0.5 }));
    antenna.position.set(-0.12, 0.08, -0.06);
    g.add(antenna);
    return g;
  }

  function buildRelay() {
    const g = new THREE.Group();
    const pcb = new THREE.Mesh(new THREE.BoxGeometry(1.3, 0.08, 0.6), mat(0x1d4ed8, { roughness: 0.5 }));
    pcb.castShadow = true;
    g.add(pcb);
    for (let i = 0; i < 4; i++) {
      const relayBlock = new THREE.Mesh(new THREE.BoxGeometry(0.26, 0.22, 0.32), mat(0x1e40af, { roughness: 0.4 }));
      relayBlock.position.set(-0.48 + i * 0.32, 0.15, -0.04);
      g.add(relayBlock);
    }
    const termMat = mat(0x2a2a2a, { metalness: 0.5 });
    for (let i = 0; i < 4; i++) {
      const term = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.09, 0.14), termMat);
      term.position.set(-0.48 + i * 0.32, 0.06, 0.26);
      g.add(term);
    }
    return g;
  }

  function buildFan() {
    const g = new THREE.Group();
    const back = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.8, 0.08), mat(0x1c1f22, { roughness: 0.55 }));
    back.castShadow = true;
    g.add(back);
    const ring = new THREE.Mesh(new THREE.TorusGeometry(0.33, 0.02, 8, 24), mat(0x8a8f96, { metalness: 0.7, roughness: 0.3 }));
    ring.position.z = 0.1;
    g.add(ring);
    for (let i = 0; i < 4; i++) {
      const spoke = new THREE.Mesh(new THREE.BoxGeometry(0.66, 0.015, 0.015), mat(0x8a8f96, { metalness: 0.7, roughness: 0.3 }));
      spoke.rotation.z = (i / 4) * Math.PI;
      spoke.position.z = 0.1;
      g.add(spoke);
    }
    const spinGroup = new THREE.Group();
    spinGroup.position.z = 0.09;
    const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 0.06, 12), mat(0x334155, { roughness: 0.4 }));
    hub.rotation.x = Math.PI / 2;
    spinGroup.add(hub);
    const bladeMat = mat(0x475569, { roughness: 0.35, transparent: true, opacity: 0.92 });
    for (let i = 0; i < 5; i++) {
      const blade = new THREE.Mesh(new THREE.BoxGeometry(0.26, 0.09, 0.012), bladeMat);
      const a = (i / 5) * Math.PI * 2;
      blade.position.set(Math.cos(a) * 0.16, Math.sin(a) * 0.16, 0);
      blade.rotation.z = a + Math.PI / 2;
      blade.rotation.y = 0.5;
      spinGroup.add(blade);
    }
    g.add(spinGroup);
    g.userData.spin = spinGroup;
    return g;
  }

  function buildPump() {
    const g = new THREE.Group();
    const body = new THREE.Mesh(new THREE.CylinderGeometry(0.17, 0.19, 0.32, 16), mat(0x1e293b, { roughness: 0.45, metalness: 0.2 }));
    body.castShadow = true;
    g.add(body);
    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.21, 0.21, 0.04, 16), mat(0x0f172a, { roughness: 0.6 }));
    base.position.y = -0.17;
    g.add(base);
    const outlet = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, 0.18, 10), mat(0x334155, { roughness: 0.4 }));
    outlet.rotation.z = Math.PI / 2;
    outlet.position.set(0.15, 0.12, 0);
    g.add(outlet);
    const grille = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.13, 0.01, 16), mat(0x111827, { roughness: 0.7 }));
    grille.position.y = -0.19;
    g.add(grille);
    return g;
  }

  function buildServo() {
    const g = new THREE.Group();
    const body = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.34, 0.2), mat(0x1e3a5f, { roughness: 0.45 }));
    body.castShadow = true;
    g.add(body);
    const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 0.06, 10), mat(0x8a8f96, { metalness: 0.7 }));
    shaft.position.y = 0.2;
    g.add(shaft);
    const horn = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.03, 0.045), mat(0xe5e7eb, { roughness: 0.5 }));
    horn.position.y = 0.23;
    horn.rotation.y = 0.5;
    g.add(horn);
    return g;
  }

  /* ---------- Layout — identical real-world positions to the landing
     page's hardware scene, so the two pages agree on where everything
     actually is. ---------- */
  const ESP32_POS = new THREE.Vector3(-3.35, 0.3, 0.6);
  const PARTS = [
    { key: 'esp32', build: buildESP32, pos: ESP32_POS.clone(), topOffset: 0.85 },
    { key: 'dht11', build: buildDHT11, pos: new THREE.Vector3(-3.35, 1.15, 0.6), topOffset: 0.32 },
    { key: 'relay', build: buildRelay, pos: new THREE.Vector3(-3.32, -0.65, 0.55), topOffset: 0.2 },
    { key: 'gas', build: buildGasSensor, pos: new THREE.Vector3(-3.35, 0.3, -0.7), topOffset: 0.3 },
    { key: 'light', build: buildLightSensor, pos: new THREE.Vector3(-2.0, 3.3, 0.3), topOffset: 0.2 },
    { key: 'cam', build: buildESP32Cam, pos: new THREE.Vector3(0.6, 3.25, 1.3), topOffset: 0.2 },
    { key: 'soil', build: buildSoilSensor, pos: new THREE.Vector3(0.2, -1.85, 0.5), topOffset: 0.55 },
    { key: 'water', build: buildWaterLevelSensor, pos: new THREE.Vector3(2.35, -1.4, -0.75), topOffset: 0.4 },
    { key: 'pump', build: buildPump, pos: new THREE.Vector3(1.35, -2.15, -0.65), topOffset: 0.2 },
    { key: 'fan', build: buildFan, pos: new THREE.Vector3(3.35, 1.3, -0.6), topOffset: 0.45 },
    { key: 'servo', build: buildServo, pos: new THREE.Vector3(0.8, 3.6, 0), topOffset: 0.25 },
  ];
  PARTS.forEach((p) => {
    p.group = p.build();
    p.group.position.copy(p.pos);
    rootGroup.add(p.group);
    p.anchor = p.pos.clone();
    p.anchor.y += p.topOffset;
  });

  /* ---------- Greenhouse shell — same geometry as the landing page's
     scene, roof shown open (matching the real unit's own reference
     photo), no animated assembly here since this is a static showcase. ---------- */
  const greenhouse = new THREE.Group();
  rootGroup.add(greenhouse);

  (function buildGreenhouseShell() {
    const FLOOR_Y = -2.4;
    const W = 7.0, D = 3.6, WALL_H = 4.2, ROOF_H = 1.8;
    const frameMat = mat(0xb0b6bd, { metalness: 0.75, roughness: 0.25 });
    const jointMat = mat(0x1c1f22, { roughness: 0.6, metalness: 0.1 });
    const edge = (w, h, d, x, y, z, rz = 0) => {
      const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), frameMat);
      m.position.set(x, y, z);
      m.rotation.z = rz;
      m.castShadow = m.receiveShadow = true;
      greenhouse.add(m);
    };
    const joint = (x, y, z) => {
      const m = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.22, 0.22), jointMat);
      m.position.set(x, y, z);
      m.castShadow = true;
      greenhouse.add(m);
    };
    const roofBaseY = WALL_H + FLOOR_Y;
    const peakY = roofBaseY + ROOF_H;

    if (enableShadows) {
      const catcher = new THREE.Mesh(new THREE.PlaneGeometry(30, 30), new THREE.ShadowMaterial({ opacity: 0.3 }));
      catcher.rotation.x = -Math.PI / 2;
      catcher.position.y = FLOOR_Y - 0.02;
      catcher.receiveShadow = true;
      greenhouse.add(catcher);
    }

    [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => {
      edge(0.12, WALL_H, 0.12, sx * W / 2, WALL_H / 2 + FLOOR_Y, sz * D / 2);
      joint(sx * W / 2, FLOOR_Y, sz * D / 2);
      joint(sx * W / 2, roofBaseY, sz * D / 2);
    });
    edge(W, 0.12, 0.12, 0, FLOOR_Y, D / 2);
    edge(W, 0.12, 0.12, 0, FLOOR_Y, -D / 2);
    edge(0.12, 0.12, D, -W / 2, FLOOR_Y, 0);
    edge(0.12, 0.12, D, W / 2, FLOOR_Y, 0);
    edge(W, 0.12, 0.12, 0, roofBaseY, D / 2);
    edge(W, 0.12, 0.12, 0, roofBaseY, -D / 2);
    edge(0.12, 0.12, D, -W / 2, roofBaseY, 0);
    edge(0.12, 0.12, D, W / 2, roofBaseY, 0);
    edge(W, 0.12, 0.12, 0, peakY, 0);
    joint(-W / 2, peakY, 0);
    joint(W / 2, peakY, 0);

    const roofLen = Math.sqrt((W / 2) ** 2 + ROOF_H ** 2);
    const roofAngle = Math.atan2(ROOF_H, W / 2);
    [-1, 1].forEach((sz) => {
      const rafterL = new THREE.Mesh(new THREE.BoxGeometry(roofLen, 0.1, 0.1), frameMat);
      rafterL.rotation.z = roofAngle;
      rafterL.position.set(-W / 4, roofBaseY + ROOF_H / 2, sz * D / 2);
      rafterL.castShadow = true;
      greenhouse.add(rafterL);
      const rafterR = new THREE.Mesh(new THREE.BoxGeometry(roofLen, 0.1, 0.1), frameMat);
      rafterR.rotation.z = -roofAngle;
      rafterR.position.set(W / 4, roofBaseY + ROOF_H / 2, sz * D / 2);
      rafterR.castShadow = true;
      greenhouse.add(rafterR);
    });

    const wallGlass = glassMat(0xd8ecf5, 0.09);
    const front = new THREE.Mesh(new THREE.PlaneGeometry(W, WALL_H), wallGlass);
    front.position.set(0, roofBaseY - WALL_H / 2, D / 2);
    front.receiveShadow = true;
    greenhouse.add(front);
    const back = front.clone(); back.position.z = -D / 2; back.rotation.y = Math.PI; greenhouse.add(back);
    const left = new THREE.Mesh(new THREE.PlaneGeometry(D, WALL_H), wallGlass);
    left.rotation.y = Math.PI / 2; left.position.set(-W / 2, roofBaseY - WALL_H / 2, 0); greenhouse.add(left);
    const right = left.clone(); right.rotation.y = -Math.PI / 2; right.position.x = W / 2; greenhouse.add(right);

    const roofGlass = glassMat(0xdff2fb, 0.11);
    const roofL = new THREE.Mesh(new THREE.PlaneGeometry(roofLen, D), roofGlass);
    roofL.rotation.y = Math.PI / 2;
    roofL.rotation.x = roofAngle;
    roofL.position.set(-W / 4, roofBaseY + ROOF_H / 2, 0);
    greenhouse.add(roofL);

    const roofRHinge = new THREE.Group();
    roofRHinge.position.set(0, peakY, 0);
    roofRHinge.rotation.x = -0.55; // shown open, matching the real unit's reference photo
    const roofR = new THREE.Mesh(new THREE.PlaneGeometry(roofLen, D), roofGlass);
    roofR.rotation.y = Math.PI / 2;
    roofR.position.set(roofLen / 2, 0, 0);
    roofRHinge.add(roofR);
    const strut = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, roofLen * 0.55, 8), mat(0x8a8f96, { metalness: 0.7 }));
    strut.rotation.z = Math.PI / 2.6;
    strut.position.set(roofLen * 0.32, -0.4, 0);
    roofRHinge.add(strut);
    greenhouse.add(roofRHinge);

    const growLight = new THREE.Mesh(new THREE.BoxGeometry(W - 0.6, 0.06, 0.1), new THREE.MeshStandardMaterial({ color: 0xfff4d6, emissive: 0xffedb3, emissiveIntensity: 0.6 }));
    growLight.position.set(-0.3, roofBaseY - 0.1, D / 2 - 0.35);
    greenhouse.add(growLight);

    const bedW = 4.6, bedD = 2.9, bedH = 0.55;
    const bedX = -1.0;
    const soilMat = mat(0x3b2a1e, { roughness: 0.95 });
    const bed = new THREE.Mesh(new THREE.BoxGeometry(bedW, bedH, bedD), soilMat);
    bed.position.set(bedX, FLOOR_Y + bedH / 2, 0);
    bed.castShadow = bed.receiveShadow = true;
    greenhouse.add(bed);

    const leafMat = mat(0x2f9e44, { roughness: 0.6 });
    const tomatoMat = mat(0xdc2626, { roughness: 0.4 });
    const bedTopY = FLOOR_Y + bedH;
    [{ x: bedX - 1.6, tall: 0.5 }, { x: bedX - 0.3, tall: 0.7 }, { x: bedX + 1.4, tall: 1.15, tomato: true }].forEach((p) => {
      const plant = new THREE.Group();
      const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.04, p.tall, 6), mat(0x3f6212));
      stem.position.y = p.tall / 2;
      plant.add(stem);
      for (let i = 0; i < 6; i++) {
        const leaf = new THREE.Mesh(new THREE.ConeGeometry(0.14, 0.4, 8), leafMat);
        const a = (i / 6) * Math.PI * 2;
        const h = p.tall * (0.35 + 0.55 * (i / 6));
        leaf.position.set(Math.cos(a) * 0.14, h, Math.sin(a) * 0.14);
        leaf.rotation.z = Math.cos(a) * 0.5;
        leaf.rotation.x = Math.sin(a) * 0.5;
        leaf.castShadow = true;
        plant.add(leaf);
      }
      if (p.tomato) {
        for (let i = 0; i < 4; i++) {
          const tomato = new THREE.Mesh(new THREE.SphereGeometry(0.055, 8, 8), tomatoMat);
          tomato.position.set((Math.random() - 0.5) * 0.3, p.tall * 0.6 + Math.random() * 0.25, (Math.random() - 0.5) * 0.3);
          plant.add(tomato);
        }
      }
      plant.position.set(p.x, bedTopY, 0.4);
      greenhouse.add(plant);
    });

    const tankW = 1.5, tankD = 1.4, tankH = 1.3;
    const tankX = 2.35, tankZ = -0.75;
    const tankGlass = glassMat(0xdbeafe, 0.22);
    const tank = new THREE.Mesh(new THREE.BoxGeometry(tankW, tankH, tankD), tankGlass);
    tank.position.set(tankX, FLOOR_Y + tankH / 2, tankZ);
    greenhouse.add(tank);
    const water = new THREE.Mesh(new THREE.BoxGeometry(tankW - 0.15, tankH * 0.65, tankD - 0.15), mat(0x38bdf8, { transparent: true, opacity: 0.75, roughness: 0.1 }));
    water.position.set(tankX, FLOOR_Y + tankH * 0.35, tankZ);
    greenhouse.add(water);

    const tube = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, tankX - (bedX + bedW / 2) + 0.3, 8), mat(0xcbd5e1, { metalness: 0.3, roughness: 0.4 }));
    tube.rotation.z = Math.PI / 2;
    tube.position.set((bedX + bedW / 2 + tankX) / 2, FLOOR_Y + 0.15, -0.4);
    greenhouse.add(tube);
  })();

  /* ---------- Fixed hero camera angle — tuned so every hotspot anchor
     (including the roof-peak servo and the far-wall fan) stays inside
     frame. The desktop split-screen stage is landscape (~1.4 aspect);
     the mobile-stacked stage is portrait (~0.75 aspect), which needs a
     noticeably wider effective view to keep the same horizontal spread
     in frame — a single camera tuned for one clips the other, so this
     branches like the pixel-ratio/shadow settings above. ---------- */
  const CAM_POS = isNarrow ? new THREE.Vector3(13, 5.5, 16.5) : new THREE.Vector3(8.8, 4.2, 11.2);
  const LOOK_AT = new THREE.Vector3(0, 0.05, 0.1);
  if (isNarrow) camera.fov = 30;
  camera.position.copy(CAM_POS);
  camera.lookAt(LOOK_AT);

  /* ---------- Drag-to-orbit (azimuth only) + gentle idle sway ---------- */
  let dragRotation = 0;
  let isDragging = false;
  let dragStartX = 0, dragStartRotation = 0;
  let hoverActive = false;

  canvas.addEventListener('pointerdown', (e) => {
    isDragging = true;
    dragStartX = e.clientX;
    dragStartRotation = dragRotation;
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!isDragging) return;
    const dx = e.clientX - dragStartX;
    dragRotation = Math.max(-1.1, Math.min(1.1, dragStartRotation + dx * 0.006));
    renderFrame();
  });
  ['pointerup', 'pointercancel', 'pointerleave'].forEach((evt) => {
    canvas.addEventListener(evt, () => { isDragging = false; });
  });

  /* ---------- Hotspots ---------- */
  const hotspotEls = {};
  PARTS.forEach((p) => {
    const el = document.createElement('div');
    el.className = 'gh3d-hotspot';
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    el.setAttribute('aria-label', (HOTSPOT_INFO[p.key] || {}).title || p.key);
    hotspotLayer.appendChild(el);
    hotspotEls[p.key] = el;

    const activate = () => {
      Object.values(hotspotEls).forEach((h) => h.classList.remove('is-active'));
      el.classList.add('is-active');
      const info = HOTSPOT_INFO[p.key];
      if (info) {
        panelTitle.textContent = info.title;
        panelSub.textContent = info.sub;
        panelDesc.textContent = info.desc;
      }
    };
    el.addEventListener('mouseenter', () => { hoverActive = true; activate(); });
    el.addEventListener('mouseleave', () => {
      hoverActive = false;
      el.classList.remove('is-active');
      panelTitle.textContent = DEFAULT_PANEL.title;
      panelSub.textContent = DEFAULT_PANEL.sub;
      panelDesc.textContent = DEFAULT_PANEL.desc;
    });
    el.addEventListener('click', activate);
    el.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(); } });
  });

  const tmpV = new THREE.Vector3();
  function updateHotspots() {
    const w = stage.clientWidth, h = stage.clientHeight;
    // Project each anchor through the rotated root group's world matrix,
    // so hotspot dots track the greenhouse as it's dragged/idly swayed.
    rootGroup.updateMatrixWorld(true);
    PARTS.forEach((p) => {
      tmpV.copy(p.anchor).applyMatrix4(rootGroup.matrixWorld).project(camera);
      const x = (tmpV.x * 0.5 + 0.5) * w;
      const y = (-tmpV.y * 0.5 + 0.5) * h;
      const el = hotspotEls[p.key];
      el.style.transform = `translate(${x}px, ${y}px)`;
      el.style.display = tmpV.z < 1 ? 'block' : 'none';
    });
  }

  /* ---------- Render-on-demand + idle tick (same pattern as the
     landing page scene): render once per meaningful change, plus a
     light throttled tick for the fan spin / idle sway / hotspot
     tracking while the section is on screen. ---------- */
  const clock = new THREE.Clock();
  let idleTimer = null;

  function renderFrame() {
    const t = clock.getElapsedTime();
    if (!reduceMotion && !isDragging && !hoverActive) {
      rootGroup.rotation.y = dragRotation + Math.sin(t * 0.15) * 0.05;
    } else {
      rootGroup.rotation.y = dragRotation;
    }
    PARTS.forEach((p) => {
      if (p.group.userData.spin) p.group.userData.spin.rotation.z = t * 4.5;
    });
    updateHotspots();
    renderer.render(scene, camera);
  }

  function startIdleTicks() {
    if (idleTimer || reduceMotion) return;
    idleTimer = setInterval(renderFrame, 200);
  }
  function stopIdleTicks() {
    if (idleTimer) { clearInterval(idleTimer); idleTimer = null; }
  }

  function handleResize() {
    const w = stage.clientWidth || window.innerWidth;
    const h = stage.clientHeight || 600;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderFrame();
  }
  window.addEventListener('resize', handleResize);
  handleResize();

  const io = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) { renderFrame(); startIdleTicks(); } else { stopIdleTicks(); }
  }, { threshold: 0.01 });
  io.observe(section);

  renderFrame();
})();
