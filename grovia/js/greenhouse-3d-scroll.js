
import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

(function () {
  const section = document.getElementById('tech3dSection');
  const canvas = document.getElementById('tech3dCanvas');
  const finaleEl = document.getElementById('tech3dFinale');
  const infoEl = document.querySelector('.tech-3d-info');
  const kickerEl = document.getElementById('tech3dKicker');
  const titleEl = document.getElementById('tech3dTitle');
  const descEl = document.getElementById('tech3dDesc');
  const tagEl = document.getElementById('tech3dTag');
  const tagTextEl = document.getElementById('tech3dTagText');
  const dotEls = document.querySelectorAll('#tech3dDots span');
  if (!section || !canvas) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isNarrow = window.innerWidth < 900;
  const hasGSAP = typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined';
  
  const STAGE_FRACTION = isNarrow ? 1 : 0.6;

  
  if (reduceMotion || !hasGSAP) {
    section.classList.add('is-static-fallback');
    return;
  }

  const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);
  const easeOutBack = (t) => {
    const c1 = 1.70158, c3 = c1 + 1;
    return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2);
  };
  const clamp01 = (v) => Math.max(0, Math.min(1, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const lerpVec = (v, a, b, t) => v.set(lerp(a.x, b.x, t), lerp(a.y, b.y, t), lerp(a.z, b.z, t));

  /*  Renderer / scene / camera */
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
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

  scene.add(new THREE.AmbientLight(0xbfe6c8, 0.4));
  const keyLight = new THREE.DirectionalLight(0xffffff, 1.3);
  keyLight.position.set(4, 7, 5);
  keyLight.target.position.set(0, -1, 0);
  scene.add(keyLight, keyLight.target);
  if (enableShadows) {
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(1024, 1024);
    keyLight.shadow.camera.left = -8; keyLight.shadow.camera.right = 8;
    keyLight.shadow.camera.top = 8; keyLight.shadow.camera.bottom = -8;
    keyLight.shadow.camera.near = 1; keyLight.shadow.camera.far = 22;
    keyLight.shadow.bias = -0.0015;
  }
  const rimLight = new THREE.PointLight(0x4ade80, 1.2, 30);
  rimLight.position.set(-5, 2, -4);
  scene.add(rimLight);
  const fillLight = new THREE.PointLight(0xbae6fd, 0.5, 25);
  fillLight.position.set(5, 1, 6);
  scene.add(fillLight);

  const rootGroup = new THREE.Group();
  scene.add(rootGroup);

  /* ---------- Material helpers ---------- */
  const mat = (color, opts = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.45, metalness: 0.25, ...opts });
  const glassMat = (color, opacity) => new THREE.MeshPhysicalMaterial({
    color, transparent: true, opacity, roughness: 0.08, metalness: 0,
    transmission: 0.82, thickness: 0.4, side: THREE.DoubleSide, envMapIntensity: 1,
  });
  const glowMat = (color) => new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0 });
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

  function statusLed(color = 0x4ade80) {
    const led = new THREE.Mesh(new THREE.SphereGeometry(0.028, 8, 8), new THREE.MeshStandardMaterial({ color, emissive: color, emissiveIntensity: 0 }));
    return led;
  }

  /* ---------- Component builders — real multi-part 3D geometry,
     one per physical part, no photo textures. ---------- */

  function buildESP32() {
    // Mounted flush against the electronics wall: thin along X so its
    // face reads clean and flat from inside the greenhouse.
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
    g.userData.statusLed = statusLed(0x4ade80);
    g.userData.statusLed.position.set(0.05, -0.2, 0.3);
    g.add(g.userData.statusLed);
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
    g.userData.statusLed = statusLed(0xf87171);
    g.userData.statusLed.position.set(0.5, 0.08, -0.2);
    g.add(g.userData.statusLed);
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
    const hornGroup = new THREE.Group();
    hornGroup.position.y = 0.23;
    const horn = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.03, 0.045), mat(0xe5e7eb, { roughness: 0.5 }));
    hornGroup.add(horn);
    const linkage = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 0.5, 8), mat(0x9ca3af, { metalness: 0.6, roughness: 0.35 }));
    linkage.position.set(0.14, 0.25, 0);
    linkage.rotation.z = 0.35;
    hornGroup.add(linkage);
    g.add(hornGroup);
    g.userData.servoHorn = hornGroup;
    return g;
  }

  /* ---------- Layout ----------
     Two positions per part. `installPos` is a tight staging cluster right
     beside the ESP32 hub, sized to actually stay inside the camera's
     close-up frame during the electronics phase — parts wire in here, all
     visible at once, instead of flying straight to their real (often far
     away) functional spot and leaving nothing but a bare wire on screen.
     `pos` is where the part actually lives on the real unit; components
     migrate from installPos to pos during the shell-assembly phase, once
     the wires have faded out, arriving as the camera orbits the finished
     greenhouse. ---------- */
  const ESP32_POS = new THREE.Vector3(-3.35, 0.3, 0.6);

  // Order matches the narrative, not the wiring order: Environment (DHT11 +
  // Light) → Soil → Air → Water (level sensor + pump) → Vision (cam) →
  // Control (relay + fan + servo). Each component's installPos/pos are its
  // own fixed positions regardless of array order, so reordering here only
  // changes fly-in timing/sequence — not where anything actually sits.
  const COMPONENTS = [
    { key: 'dht11', build: buildDHT11, installPos: new THREE.Vector3(-2.85, 1.05, 1.0), pos: new THREE.Vector3(-3.35, 1.15, 0.6), short: 'DHT11' },
    { key: 'light', build: buildLightSensor, installPos: new THREE.Vector3(-1.35, 1.05, 0.75), pos: new THREE.Vector3(-2.0, 3.3, 0.3), short: 'Light Sensor' },
    { key: 'soil', build: buildSoilSensor, installPos: new THREE.Vector3(-2.85, -0.95, 1.0), pos: new THREE.Vector3(0.2, -1.85, 0.5), short: 'Soil Probe' },
    { key: 'gas', build: buildGasSensor, installPos: new THREE.Vector3(-1.85, 1.05, 1.0), pos: new THREE.Vector3(-3.35, 0.3, -0.7), short: 'MQ-135' },
    { key: 'water', build: buildWaterLevelSensor, installPos: new THREE.Vector3(-2.35, -0.95, 0.75), pos: new THREE.Vector3(2.35, -1.4, -0.75), short: 'Water Level Sensor' },
    { key: 'pump', build: buildPump, installPos: new THREE.Vector3(-1.85, -0.95, 1.0), pos: new THREE.Vector3(1.35, -2.15, -0.65), short: 'Water Pump' },
    { key: 'cam', build: buildESP32Cam, installPos: new THREE.Vector3(-0.85, 1.05, 1.0), pos: new THREE.Vector3(0.6, 3.25, 1.3), short: 'ESP32-CAM' },
    { key: 'relay', build: buildRelay, installPos: new THREE.Vector3(-2.35, 1.05, 0.75), pos: new THREE.Vector3(-3.32, -0.65, 0.55), short: 'Relay Module' },
    { key: 'fan', build: buildFan, installPos: new THREE.Vector3(-1.35, -0.95, 0.75), pos: new THREE.Vector3(3.35, 1.3, -0.6), short: 'Cooling Fan' },
    { key: 'servo', build: buildServo, installPos: new THREE.Vector3(-0.85, -0.95, 1.0), pos: new THREE.Vector3(0.8, 3.6, 0), short: 'Vent Servo' },
  ];

  const esp32 = buildESP32();
  esp32.position.copy(ESP32_POS);
  esp32.scale.setScalar(0.001);
  rootGroup.add(esp32);

  COMPONENTS.forEach((c, i) => {
    c.group = c.build();
    c.group.position.copy(c.installPos);
    c.startPos = new THREE.Vector3(
      c.installPos.x * 2.6 + (i % 2 === 0 ? -2.5 : 2.5),
      c.installPos.y * 1.8 + 6,
      c.installPos.z - 10
    );
    c.group.position.copy(c.startPos);
    c.group.scale.setScalar(0.001);
    rootGroup.add(c.group);

    // Curved glowing "data cable" from this part's staging spot to the
    // ESP32 hub — a gentle upward bulge so it reads as a short routed
    // wire rather than a beam cutting through open air. Built once (both
    // ends are fixed), only its opacity animates.
    const mid = c.installPos.clone().add(ESP32_POS).multiplyScalar(0.5);
    mid.y += 0.55;
    const curve = new THREE.QuadraticBezierCurve3(c.installPos.clone(), mid, ESP32_POS.clone());
    const tubeGeo = new THREE.TubeGeometry(curve, 24, 0.014, 6, false);
    c.tubeMat = glowMat(0x15803d);
    c.tube = new THREE.Mesh(tubeGeo, c.tubeMat);
    rootGroup.add(c.tube);
    c.curve = curve;
    c.pulses = [0, 0.5].map((offset) => {
      const p = new THREE.Mesh(new THREE.SphereGeometry(0.024, 8, 8), new THREE.MeshBasicMaterial({ color: 0x4ade80, transparent: true, opacity: 0 }));
      p.userData.offset = offset;
      rootGroup.add(p);
      return p;
    });
    c.connT = 0;
  });

  /* ---------- Greenhouse shell (assembles in the final stage) ---------- */
  const greenhouse = new THREE.Group();
  greenhouse.scale.setScalar(0.001);
  rootGroup.add(greenhouse);

  const shellRefs = (function buildGreenhouseShell() {
    // Modeled on the real unit's own photo, not a generic greenhouse:
    // aluminum-extrusion frame with visible black corner brackets, a real
    // soil bed (not potted plants) running most of the floor, the water
    // tank in its own back-right corner, and the right roof panel driven
    // open on its hinge by the servo — matching the actual vent mechanism.
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

    // Ground shadow-catcher — invisible except for the soft shadow it
    // picks up, so the finished greenhouse reads as sitting on a lit
    // stage instead of floating with no ground contact.
    if (enableShadows) {
      const catcher = new THREE.Mesh(new THREE.PlaneGeometry(30, 30), new THREE.ShadowMaterial({ opacity: 0.32 }));
      catcher.rotation.x = -Math.PI / 2;
      catcher.position.y = FLOOR_Y - 0.02;
      catcher.receiveShadow = true;
      greenhouse.add(catcher);
    }

    // vertical corner posts + their black corner-bracket joints top & bottom
    [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => {
      edge(0.12, WALL_H, 0.12, sx * W / 2, WALL_H / 2 + FLOOR_Y, sz * D / 2);
      joint(sx * W / 2, FLOOR_Y, sz * D / 2);
      joint(sx * W / 2, roofBaseY, sz * D / 2);
    });
    // base + roof-base rails
    edge(W, 0.12, 0.12, 0, FLOOR_Y, D / 2);
    edge(W, 0.12, 0.12, 0, FLOOR_Y, -D / 2);
    edge(0.12, 0.12, D, -W / 2, FLOOR_Y, 0);
    edge(0.12, 0.12, D, W / 2, FLOOR_Y, 0);
    edge(W, 0.12, 0.12, 0, roofBaseY, D / 2);
    edge(W, 0.12, 0.12, 0, roofBaseY, -D / 2);
    edge(0.12, 0.12, D, -W / 2, roofBaseY, 0);
    edge(0.12, 0.12, D, W / 2, roofBaseY, 0);
    // ridge beam + its end joints
    edge(W, 0.12, 0.12, 0, peakY, 0);
    joint(-W / 2, peakY, 0);
    joint(W / 2, peakY, 0);
    // sloped roof edge beams (the rafters running from wall-top to ridge)
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

    // acrylic walls (frosted, not clear glass — matches the real unit)
    const wallGlass = glassMat(0xd8ecf5, 0.09);
    const front = new THREE.Mesh(new THREE.PlaneGeometry(W, WALL_H), wallGlass);
    front.position.set(0, roofBaseY - WALL_H / 2, D / 2);
    front.receiveShadow = true;
    greenhouse.add(front);
    const back = front.clone(); back.position.z = -D / 2; back.rotation.y = Math.PI; greenhouse.add(back);
    const left = new THREE.Mesh(new THREE.PlaneGeometry(D, WALL_H), wallGlass);
    left.rotation.y = Math.PI / 2; left.position.set(-W / 2, roofBaseY - WALL_H / 2, 0); greenhouse.add(left);
    const right = left.clone(); right.rotation.y = -Math.PI / 2; right.position.x = W / 2; greenhouse.add(right);

    // Roof: left panel fixed closed. Right panel starts closed too, and is
    // driven open by updateScene() later in the scroll — the servo
    // "physically" lifting it, rather than it being permanently ajar.
    const roofGlass = glassMat(0xdff2fb, 0.11);
    const roofL = new THREE.Mesh(new THREE.PlaneGeometry(roofLen, D), roofGlass);
    roofL.rotation.y = Math.PI / 2;
    roofL.rotation.x = roofAngle;
    roofL.position.set(-W / 4, roofBaseY + ROOF_H / 2, 0);
    greenhouse.add(roofL);

    const roofRHinge = new THREE.Group();
    roofRHinge.position.set(0, peakY, 0);
    roofRHinge.rotation.x = roofAngle; // starts closed, matching the left panel's slope
    const roofR = new THREE.Mesh(new THREE.PlaneGeometry(roofLen, D), roofGlass);
    roofR.rotation.y = Math.PI / 2;
    roofR.position.set(roofLen / 2, 0, 0);
    roofRHinge.add(roofR);
    const strut = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, roofLen * 0.55, 8), mat(0x8a8f96, { metalness: 0.7 }));
    strut.rotation.z = Math.PI / 2.6;
    strut.position.set(roofLen * 0.32, -0.4, 0);
    roofRHinge.add(strut);
    greenhouse.add(roofRHinge);

    // grow light strip along the ridge, interior side
    const growLight = new THREE.Mesh(new THREE.BoxGeometry(W - 0.6, 0.06, 0.1), new THREE.MeshStandardMaterial({ color: 0xfff4d6, emissive: 0xffedb3, emissiveIntensity: 0.6 }));
    growLight.position.set(-0.3, roofBaseY - 0.1, D / 2 - 0.35);
    greenhouse.add(growLight);

    // Soil bed — a real trough running most of the floor, not scattered
    // potted plants, leaving the back-right corner clear for the tank.
    const bedW = 4.6, bedD = 2.9, bedH = 0.55;
    const bedX = -1.0;
    const soilMat = mat(0x3b2a1e, { roughness: 0.95 });
    const bed = new THREE.Mesh(new THREE.BoxGeometry(bedW, bedH, bedD), soilMat);
    bed.position.set(bedX, FLOOR_Y + bedH / 2, 0);
    bed.castShadow = bed.receiveShadow = true;
    greenhouse.add(bed);

    // plants growing directly out of the soil bed (basil, lettuce, tomato
    // with small visible fruit — matching the real photo's mix)
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

    // Water tank — its own corner, back-right, sitting on the floor.
    const tankW = 1.5, tankD = 1.4, tankH = 1.3;
    const tankX = 2.35, tankZ = -0.75;
    const tankGlass = glassMat(0xdbeafe, 0.22);
    const tank = new THREE.Mesh(new THREE.BoxGeometry(tankW, tankH, tankD), tankGlass);
    tank.position.set(tankX, FLOOR_Y + tankH / 2, tankZ);
    greenhouse.add(tank);
    const water = new THREE.Mesh(new THREE.BoxGeometry(tankW - 0.15, tankH * 0.65, tankD - 0.15), mat(0x38bdf8, { transparent: true, opacity: 0.75, roughness: 0.1 }));
    water.position.set(tankX, FLOOR_Y + tankH * 0.35, tankZ);
    greenhouse.add(water);

    // irrigation tubing from the pump (beside the bed) to the tank
    const tube = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, tankX - (bedX + bedW / 2) + 0.3, 8), mat(0xcbd5e1, { metalness: 0.3, roughness: 0.4 }));
    tube.rotation.z = Math.PI / 2;
    tube.position.set((bedX + bedW / 2 + tankX) / 2, FLOOR_Y + 0.15, -0.4);
    greenhouse.add(tube);

    return { roofRHinge, roofAngle, roofOpenX: -0.55 };
  })();

  /* ---------- Camera path ----------
     Electronics phase: close-up on the electronics wall as parts wire
     themselves in. Shell phase splits in two: pulls back to an
     establishing 3/4 view (CAM_END, computed as the orbit's own starting
     point so the two sub-phases join seamlessly), then ORBITS across the
     front of the finished greenhouse. */
  const CAM_START = new THREE.Vector3(-2.2, 0.5, 4.6);
  const CAM_MID = new THREE.Vector3(-0.6, 1.3, 6.9);
  const LOOK_START = new THREE.Vector3(-2.1, 0.15, 0.7);
  const LOOK_END = new THREE.Vector3(0, -0.9, 0);

  // Tighter than a typical wide establishing shot — the split-screen stage
  // is a narrower, more portrait-ish viewport than a full-bleed hero, so
  // pulling the orbit in keeps the greenhouse reading as large and close
  // rather than a small object lost in a wide frame.
  const ORBIT_RADIUS = 11, ORBIT_HEIGHT = 4.2;
  const ORBIT_START_ANGLE = 1.05, ORBIT_END_ANGLE = -1.05;
  function orbitPosition(angle, out) {
    return out.set(
      LOOK_END.x + Math.sin(angle) * ORBIT_RADIUS,
      ORBIT_HEIGHT,
      LOOK_END.z + Math.cos(angle) * ORBIT_RADIUS
    );
  }
  const CAM_END = orbitPosition(ORBIT_START_ANGLE, new THREE.Vector3());

  const STAGE_SPLIT = 0.5; // progress fraction where "electronics" phase ends, "greenhouse" phase begins
  const SHELL_PULLBACK_FRAC = 0.35; // portion of the shell phase spent pulling back, before orbiting
  const ROOF_OPEN_START = 0.55, ROOF_OPEN_END = 0.8; // shellT range for the "power on" roof-opening beat
  let systemActive = false;
  const tmpV = new THREE.Vector3();
  const tmpV2 = new THREE.Vector3();

  /* ---------- Split-screen info panel ----------
     Eight short scenes, stepped through (not scrubbed) as progress crosses
     each boundary — the left panel's copy changes once per scene rather
     than continuously animating, which reads as calmer and more editorial
     than tying text directly to the scrub. Boundaries are timed against
     when each scene's own components actually finish flying in (see the
     COMPONENTS array above — same narrative order), so the text always
     describes what's visibly arriving, not a generic sensor-1/2/3 list. */
  const CHAPTERS = [
    { from: 0, kicker: 'Inside Grovia', title: 'Every part of the greenhouse has a role.', desc: 'Scroll to meet each system, one at a time.' },
    { from: 0.03, kicker: 'Scene 01 — The Environment', title: 'DHT11 + Light Sensor', desc: 'Grovia continuously monitors the greenhouse environment.' },
    { from: 0.16, kicker: 'Scene 02 — The Soil', title: 'HW-080', desc: 'Soil conditions provide the system with information needed for irrigation decisions.' },
    { from: 0.2, kicker: 'Scene 03 — The Air', title: 'MQ-135', desc: 'Air-quality information adds another layer of environmental awareness.' },
    { from: 0.24, kicker: 'Scene 04 — The Water', title: 'Water-Level Sensor + Pump', desc: 'Tank level and irrigation stay linked, so the system always knows what it can act on.' },
    { from: 0.32, kicker: 'Scene 05 — The Vision', title: 'ESP32-CAM', desc: 'Plant images can be analyzed using AI-assisted plant health detection.' },
    { from: 0.36, kicker: 'Scene 06 — The Control', title: 'ESP32 + Relay + Fan + Servo', desc: 'Every reading becomes a decision the system can act on.' },
    { from: 0.65, kicker: 'Scene 07', title: 'Everything connects.', desc: 'One greenhouse. One intelligent system.' },
  ];
  let activeChapter = -1;

  function updateInfoPanel(progress) {
    let idx = 0;
    for (let i = 0; i < CHAPTERS.length; i++) if (progress >= CHAPTERS[i].from) idx = i;
    if (idx !== activeChapter) {
      activeChapter = idx;
      const c = CHAPTERS[idx];
      kickerEl.textContent = c.kicker;
      titleEl.textContent = c.title;
      descEl.textContent = c.desc;
      dotEls.forEach((d, i) => d.classList.toggle('is-active', i === idx));
    }
    // Fade the whole panel out as the finale takes over, and back in at the very start.
    const panelFade = progress < 0.03 ? progress / 0.03 : (progress > 0.86 ? Math.max(0, 1 - (progress - 0.86) / 0.08) : 1);
    infoEl.style.opacity = String(panelFade);
    finaleEl.style.opacity = String(progress > 0.9 ? clamp01((progress - 0.9) / 0.08) : 0);
  }

  function updateScene(progress) {
    const electronicsT = clamp01(progress / STAGE_SPLIT);
    const shellT = clamp01((progress - STAGE_SPLIT) / (1 - STAGE_SPLIT));

    updateInfoPanel(progress);

    if (shellT <= 0) {
      lerpVec(tmpV, CAM_START, CAM_MID, easeOutCubic(electronicsT));
      lerpVec(tmpV2, LOOK_START, LOOK_START, 0);
    } else if (shellT <= SHELL_PULLBACK_FRAC) {
      const t = easeOutCubic(shellT / SHELL_PULLBACK_FRAC);
      lerpVec(tmpV, CAM_MID, CAM_END, t);
      lerpVec(tmpV2, LOOK_START, LOOK_END, t);
    } else {
      const orbitT = (shellT - SHELL_PULLBACK_FRAC) / (1 - SHELL_PULLBACK_FRAC);
      const angle = lerp(ORBIT_START_ANGLE, ORBIT_END_ANGLE, orbitT);
      orbitPosition(angle, tmpV);
      tmpV2.copy(LOOK_END);
    }
    camera.position.copy(tmpV);
    camera.lookAt(tmpV2);

    // ESP32 hub itself settles onto the wall during the very first slice.
    const esp32T = easeOutBack(clamp01(electronicsT / 0.12));
    esp32.scale.setScalar(lerp(0.001, 1, clamp01(esp32T)));

    const slot = 1 / (COMPONENTS.length + 0.4);
    let servoComponent = null;
    let activeLabel = null, bestLocal = -1;
    COMPONENTS.forEach((c, i) => {
      const localStart = 0.12 + i * slot * 0.82;
      const local = clamp01((electronicsT - localStart) / (slot * 1.15));
      const eased = easeOutCubic(local);
      lerpVec(c.group.position, c.startPos, c.installPos, eased);
      c.group.scale.setScalar(lerp(0.001, 1, easeOutBack(local)));
      c.group.rotation.y = lerp(Math.PI * 1.4, 0, eased);

      // the glowing cable only lights up once the part has actually arrived
      const arrived = clamp01((local - 0.82) / 0.18);
      c.connT = arrived;
      c.tubeMat.opacity = arrived * 0.55;
      c.pulses.forEach((p) => { p.material.opacity = arrived * 0.85; });

      // track whichever part is currently mid-flight, for the info panel's live tag
      if (local > 0.01 && local < 1 && local > bestLocal) { bestLocal = local; activeLabel = c.short; }

      if (c.key === 'servo') servoComponent = c;
    });

    // Live "now installing" tag — shown throughout the electronics phase,
    // across all six scene chapters (activeLabel is already null once
    // nothing is actively mid-flight, so this doesn't need a chapter check).
    if (electronicsT < 1 && activeLabel) {
      tagTextEl.textContent = activeLabel;
      tagEl.style.opacity = '1';
    } else {
      tagEl.style.opacity = '0';
    }

    // Greenhouse shell assembly
    const shellEase = easeOutCubic(shellT);
    greenhouse.scale.setScalar(lerp(0.001, 1, shellEase));
    greenhouse.rotation.y = lerp(0.6, 0, shellEase);

    // fade out connection tubes/pulses once the shell is mostly built,
    // decluttering the final cinematic shot
    if (shellT > 0.4) {
      const fade = 1 - clamp01((shellT - 0.4) / 0.4);
      COMPONENTS.forEach((c) => {
        c.tubeMat.opacity *= fade;
        c.pulses.forEach((p) => { p.material.opacity *= fade; });
      });
    }

    // Once the wires have faded, each part leaves the staging cluster and
    // travels to its real installed spot on the greenhouse — timed to land
    // as the camera orbits the finished shell, so nothing just teleports.
    const migrateT = easeOutCubic(clamp01((shellT - 0.42) / (0.72 - 0.42)));
    COMPONENTS.forEach((c) => {
      lerpVec(c.group.position, c.installPos, c.pos, migrateT);
    });

    // The "power on" beat: servo turns, roof vent lifts open, fan spins up.
    const roofT = easeOutCubic(clamp01((shellT - ROOF_OPEN_START) / (ROOF_OPEN_END - ROOF_OPEN_START)));
    shellRefs.roofRHinge.rotation.x = lerp(shellRefs.roofAngle, shellRefs.roofOpenX, roofT);
    if (servoComponent && servoComponent.group.userData.servoHorn) {
      servoComponent.group.userData.servoHorn.rotation.y = lerp(0, 1.1, roofT);
    }
    systemActive = roofT > 0.05;

    rootGroup.rotation.y = lerp(0, 0.15, shellEase);
  }

  /* ---------- Render-on-demand (not a continuous requestAnimationFrame
     loop) — a scene this size re-rendering every single frame forever,
     whether or not the scroll position is even changing, is unnecessary
     GPU work. Instead: render once whenever updateScene() actually
     changes something (scroll, resize), plus a lightweight throttled tick
     just to keep the fan spin / data pulses alive while visible. ---------- */
  const clock = new THREE.Clock();
  let idleTimer = null;

  function renderFrame() {
    const t = clock.getElapsedTime();
    COMPONENTS.forEach((c) => {
      if (systemActive && c.group.userData.spin) c.group.userData.spin.rotation.z = t * 5;
      if (c.connT > 0.05) {
        c.pulses.forEach((p) => {
          const pos = c.curve.getPointAt((t * 0.18 + p.userData.offset) % 1);
          p.position.copy(pos);
        });
      }
      if (c.group.userData.statusLed) {
        c.group.userData.statusLed.material.emissiveIntensity = systemActive ? 0.7 + Math.sin(t * 3) * 0.35 : 0;
      }
    });
    if (esp32.userData.statusLed) {
      esp32.userData.statusLed.material.emissiveIntensity = systemActive ? 0.8 + Math.sin(t * 3) * 0.3 : 0.15;
    }
    renderer.render(scene, camera);
  }

  function startIdleTicks() {
    if (idleTimer) return;
    idleTimer = setInterval(renderFrame, 200); // ~5fps idle motion — cheap, still visibly alive
  }
  function stopIdleTicks() {
    if (idleTimer) { clearInterval(idleTimer); idleTimer = null; }
  }

  /* ---------- Resize ----------
     Deliberately NOT measuring the canvas, `.tech-3d-stage`, or
     `.tech-3d-pin` itself — once GSAP pins that element it wraps it in a
     "pin-spacer" div and zeroes out the pinned element's own box (the
     spacer carries the size instead), so any descendant's clientWidth
     reads 0 after pinning. The outer `.tech-3d-section` keeps its real
     size throughout, so measure that and derive the stage column's width
     arithmetically via STAGE_FRACTION (must match the CSS grid's
     grid-template-columns split), with window dimensions as a last-resort
     fallback. */
  function handleResize() {
    const totalW = section.clientWidth || window.innerWidth;
    const w = totalW * STAGE_FRACTION;
    const h = window.innerHeight;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderFrame();
  }
  window.addEventListener('resize', handleResize);
  handleResize();

  // Only render idle ticks while the section is actually on screen.
  const io = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) startIdleTicks(); else stopIdleTicks();
  }, { threshold: 0.01 });
  io.observe(section);

  /* ---------- Scroll pin + drive ---------- */
  const scrollDistance = isNarrow ? 6800 : 9200;
  ScrollTrigger.create({
    trigger: section,
    start: 'top top',
    end: '+=' + scrollDistance,
    pin: '.tech-3d-pin',
    scrub: 1,
    onUpdate: (self) => { updateScene(self.progress); renderFrame(); },
  });

  handleResize(); // re-measure once more now that the pin-spacer exists
  updateScene(0);
  renderFrame();
})();
