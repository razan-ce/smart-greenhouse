<?php header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grovia | Smart Greenhouse Monitoring</title>
<meta name="theme-color" content="#16a34a">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=81">
 <script type="importmap">      
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<style>

  .hero-btns { gap: 16px; flex-wrap: wrap; }
  .btn-ghost {
    background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.28);
    backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
  }
  .btn-ghost:hover { background: rgba(255,255,255,0.16); transform: translateY(-3px); border-color: rgba(255,255,255,0.45); }
  .btn-ghost .arrow-down { display: inline-flex; transition: transform 0.3s ease; }
  .btn-ghost .arrow-down svg { width: 18px; height: 18px; }
  .btn-ghost:hover .arrow-down { transform: translateY(3px); }
  .hero-meta {
    margin-top: 46px; font-family: 'Inter', sans-serif; font-size: 0.7rem; font-weight: 600;
    letter-spacing: 0.22em; text-transform: uppercase; color: rgba(255,255,255,0.38);
  }

  
  .tech-3d-section { position: relative; background: #f5f6f2; }
  .tech-3d-section.is-static-fallback {
    min-height: 60vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 60px 24px;
  }
  .tech-3d-pin { position: relative; width: 100%; height: 100vh; overflow: hidden; background: #f5f6f2; }
  .tech-3d-grid { position: absolute; inset: 0; display: grid; grid-template-columns: 40% 60%; }
  .tech-3d-info { display: flex; flex-direction: column; justify-content: center; padding: 0 clamp(28px, 5vw, 72px); z-index: 4; }
  .tech-3d-kicker {
    font-family: 'Inter', sans-serif; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.16em;
    text-transform: uppercase; color: #15803d; margin: 0 0 18px; transition: opacity 0.35s ease;
  }
  .tech-3d-title {
    font-family: 'Manrope', sans-serif; font-size: clamp(1.5rem, 2.4vw, 2.15rem); font-weight: 600;
    color: #14181a; line-height: 1.24; letter-spacing: -0.01em; margin: 0 0 14px; transition: opacity 0.35s ease;
  }
  .tech-3d-desc {
    font-family: 'Inter', sans-serif; font-size: 0.98rem; font-weight: 400; color: #5b6560;
    line-height: 1.65; max-width: 32ch; margin: 0; transition: opacity 0.35s ease;
  }
  .tech-3d-tag {
    display: inline-flex; align-items: center; gap: 8px; margin-top: 28px; padding: 7px 14px;
    border-radius: 999px; border: 1px solid #d8ddd4; width: fit-content; opacity: 0;
    font-family: 'Inter', sans-serif; font-size: 0.74rem; font-weight: 600; color: #2f3b34;
    transition: opacity 0.3s ease;
  }
  .tech-3d-tag-dot { width: 6px; height: 6px; border-radius: 50%; background: #22c55e; flex-shrink: 0; }
  .tech-3d-dots { display: flex; gap: 6px; margin-top: 40px; flex-wrap: wrap; max-width: 200px; }
  .tech-3d-dots span { width: 16px; height: 3px; border-radius: 2px; background: #dfe3dc; transition: background 0.3s ease; }
  .tech-3d-dots span.is-active { background: #15803d; }
  .tech-3d-stage { position: relative; }
  #tech3dCanvas { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
  .tech-3d-finale {
    position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; z-index: 6; pointer-events: none; opacity: 0;
  }
  .tech-3d-finale .mark {
    font-family: 'Poppins', sans-serif; font-weight: 700; letter-spacing: 0.01em;
    font-size: clamp(2rem, 5vw, 3.6rem); color: #14181a; margin: 0 0 12px;
  }
  .tech-3d-finale .mark span { color: #15803d; }
  .tech-3d-finale .tagline {
    font-family: 'Inter', sans-serif; font-size: clamp(0.9rem, 1.4vw, 1.05rem); font-weight: 500;
    color: #4b5550; margin: 0 0 8px; letter-spacing: 0.01em;
  }
  .tech-3d-finale .sub {
    font-family: 'Inter', sans-serif; font-size: 0.74rem; font-weight: 600; letter-spacing: 0.14em;
    text-transform: uppercase; color: #15803d; margin: 0;
  }
  @media (max-width: 900px) {
    .tech-3d-grid { grid-template-columns: 1fr; }
    .tech-3d-stage { grid-column: 1; grid-row: 1; }
    .tech-3d-info {
      position: absolute; inset: auto 0 0 0; padding: 20px 20px 30px; z-index: 5;
      background: linear-gradient(180deg, rgba(245,246,242,0) 0%, rgba(245,246,242,0.92) 32%, #f5f6f2 100%);
    }
    .tech-3d-desc { max-width: none; }
    .tech-3d-dots { margin-top: 22px; }
  }
</style>
</head>
<body>


<nav class="nav" id="siteNav">
  <div class="nav-inner">
    <div class="logo">
      <div class="logo-icon">
        <img src="assets/logo-mark.png" alt="Grovia logo">
      </div>
      Grov<span class="brand-accent">ia</span>
    </div>
    <ul class="nav-links" id="navLinks">
      <li><a href="#home" data-nav="home" class="active">Home</a></li>
      <li><a href="#story" data-nav="story">Features</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="#predict" data-nav="predict">Predict</a></li>
      <li><a href="#contact" data-nav="contact">Contact</a></li>
      <li><a href="login.php" class="btn btn-primary">Login</a></li>
    </ul>
    <div class="burger" id="burger"><span></span><span></span><span></span></div>
  </div>
</nav>

<main>

  
  <section class="hero" id="home">
    <video class="hero-media" id="heroVideo" autoplay muted loop playsinline poster="">
      <source src="assets/greenhouse.mp4" type="video/mp4">
    </video>
    <div class="hero-fallback" id="heroFallback"></div>
    <div class="hero-overlay"></div>
    <div class="hero-reveal" id="heroReveal" aria-hidden="true"></div>
    <div class="hero-content">
      <div class="hero-tag"><span class="dot"></span> Live sensor network online</div>
      <h1>Grow smarter.<br>See everything.</h1>
      <p>Grovia connects your greenhouse's physical environment with intelligent monitoring, automation, and AI-powered insights.</p>
      <div class="hero-btns">
        <a href="#story" class="btn btn-primary">
          <span>Explore Grovia</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
        <a href="#tech3dSection" class="btn btn-ghost">
          <span>See how it works</span>
          <span class="arrow-down"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg></span>
        </a>
      </div>
      <div class="hero-meta">Smart Greenhouse System / 2026</div>
    </div>
  </section>

  
<div class="ticker-wrap" aria-hidden="true">
  <div class="ticker-track">
    <span>
      <span class="accent">Real-Time Sensing</span> • 
      Smart Irrigation • 
      AI Disease Detection • 
      24/7 Monitoring • 
      Climate Control • 
      Auto Watering •
    </span>

    <span>
      <span class="accent">Real-Time Sensing</span> • 
      Smart Irrigation • 
      AI Disease Detection • 
      24/7 Monitoring • 
      Climate Control • 
      Auto Watering •
    </span>
  </div>
</div>


<section class="tech-3d-section" id="tech3dSection">
  <div class="tech-3d-pin">
    <div class="tech-3d-grid">
      <div class="tech-3d-info">
        <span class="tech-3d-kicker" id="tech3dKicker">01 — Hardware</span>
        <h2 class="tech-3d-title" id="tech3dTitle">Every reading starts with a real sensor.</h2>
        <p class="tech-3d-desc" id="tech3dDesc">Ten sensors and a central controller wire directly into the frame — nothing simulated, nothing mocked.</p>
        <span class="tech-3d-tag" id="tech3dTag"><span class="tech-3d-tag-dot"></span><span id="tech3dTagText">DHT11</span></span>
        <div class="tech-3d-dots" id="tech3dDots">
          <span class="is-active"></span><span></span><span></span><span></span>
          <span></span><span></span><span></span><span></span>
        </div>
      </div>
      <div class="tech-3d-stage">
        <canvas id="tech3dCanvas"></canvas>
      </div>
    </div>
    <div class="tech-3d-finale" id="tech3dFinale">
      <p class="mark">GROV<span>IA</span></p>
      <p class="tagline">Grow Smarter. Waste Less.</p>
      <p class="sub">AI-Powered Smart Greenhouse</p>
    </div>
  </div>
</section>


<section class="why-choose section-pad" id="why-choose">
  <div class="container">
    <div class="why-panel" id="whyPanel">
      <svg class="why-panel-shape" viewBox="0 0 1200 600" preserveAspectRatio="none" aria-hidden="true">
        <defs>
          <radialGradient id="whyFillGrad" cx="50%" cy="35%" r="80%">
            <stop offset="0%" stop-color="#191c20"/>
            <stop offset="60%" stop-color="#111318"/>
            <stop offset="100%" stop-color="#0b0d11"/>
          </radialGradient>
          <linearGradient id="whyStrokeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#4caf50"/>
            <stop offset="50%" stop-color="#9be564"/>
            <stop offset="100%" stop-color="#4caf50"/>
          </linearGradient>
        </defs>
        <path d="M0,184 C0,164.3 19.7,140 44,140 L157,140 C172.5,140 185,127.5 185,112 L185,28 C185,12.5 197.5,0 213,0 L987,0 C1002.5,0 1015,12.5 1015,28 L1015,112 C1015,127.5 1027.5,140 1043,140 L1156,140 C1175.7,140 1200,159.7 1200,184 L1200,436 C1200,455.7 1180.3,480 1156,480 L892,480 C874.3,480 860,494.3 860,512 L860,568 C860,585.7 845.7,600 828,600 L44,600 C24.3,600 0,580.3 0,556 L0,184 Z" fill="url(#whyFillGrad)" stroke="url(#whyStrokeGrad)" stroke-width="2"/>
      </svg>
      <div class="why-panel-glow" aria-hidden="true"></div>

      <div class="why-head">
        <h2 class="why-heading">
          <span class="why-word">Why</span>
          <span class="why-word">Choose</span>
          <span class="why-word why-accent">Grovia?</span>
        </h2>
        <p>Grovia combines smart technology with nature to help you grow healthier plants, save resources, and manage your greenhouse with ease.</p>
      </div>

      <div class="why-body">
        <div class="why-grid">
          <div class="why-item">
            <div class="why-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2.5" y="4" width="19" height="13" rx="2"/>
                <path d="M6 12.5l2.3-4 2 4.5 2-3 2.2 2.5H18"/>
                <path d="M9 21h6M12 17v4"/>
                <circle cx="18" cy="7" r="1.1" fill="currentColor" stroke="none"/>
              </svg>
            </div>
            <h3>Real-Time Monitoring</h3>
            <p>Check live greenhouse conditions and control your system from anywhere.</p>
          </div>

          <div class="why-item">
            <div class="why-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 3a3 3 0 0 0-3 3 3 3 0 0 0-1.2 5.5A3 3 0 0 0 5 14a3 3 0 0 0 3 3v1a3 3 0 0 0 3 3 1 1 0 0 0 1-1V6a3 3 0 0 0-3-3Z"/>
                <path d="M15 3a3 3 0 0 1 3 3 3 3 0 0 1 1.2 5.5A3 3 0 0 1 19 14a3 3 0 0 1-3 3v1a3 3 0 0 1-3 3 1 1 0 0 1-1-1V6a3 3 0 0 1 3-3Z"/>
                <circle cx="8.5" cy="9" r="0.6" fill="currentColor" stroke="none"/>
                <circle cx="15.5" cy="12.5" r="0.6" fill="currentColor" stroke="none"/>
              </svg>
            </div>
            <h3>AI-Powered Insights</h3>
            <p>Detect plant problems and receive intelligent recommendations.</p>
          </div>

          <div class="why-item">
            <div class="why-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
              </svg>
            </div>
            <h3>Automated Control</h3>
            <p>Automatically manage watering, lighting, and ventilation.</p>
          </div>
        </div>

        <div class="why-footer">
          <p class="why-tagline">Join the future of smart greenhouse management.</p>
        </div>
      </div>

      <div class="why-cta-corner">
        <a href="login.php" class="btn why-cta" data-gate="login">
          <span>Learn More</span>
        </a>
      </div>
    </div>
  </div>
</section>

  <section class="story" id="story">
    <div class="story-bg-orbs" aria-hidden="true">
      <span class="orb orb1"></span>
      <span class="orb orb2"></span>
    </div>
    <div class="story-pin">

      <div class="story-head container">
        <span class="eyebrow"><span class="dot"></span>How Grovia Works / 01—06</span>
        <h2 class="section-title">One Greenhouse. Complete Control.</h2>
      </div>

      <div class="card-stack" id="cardStack">

        
        <article class="feature-card is-active" data-feature="dashboard">
          <div class="feature-card-content">
            <span class="scene-label">Live Monitoring</span>
            <h3 class="scene-headline">See everything.</h3>
            <p class="scene-sub">Every sensor. One screen.</p>
            <div class="scene-stats">
              <div class="scene-stat"><span class="ss-label">Temperature</span><span class="ss-value" data-count-to="25.6" data-suffix="°C">0°C</span></div>
              <div class="scene-stat"><span class="ss-label">Humidity</span><span class="ss-value" data-count-to="60" data-suffix="%">0%</span></div>
              <div class="scene-stat"><span class="ss-label">Soil</span><span class="ss-value" data-count-to="45" data-suffix="%">0%</span></div>
            </div>
            <span class="scene-demo-note">Example greenhouse reading</span>
            <a href="login.php" class="scene-cta" data-gate="login">
              Open the dashboard <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>
          </div>
          <div class="feature-card-media">
            <div class="photo-fx dash-fx" aria-hidden="true">
              <span class="fx-pulse p1"></span>
              <span class="fx-pulse p2"></span>
              <span class="fx-pulse p3"></span>
            </div>
            <img class="story-photo" src="assets/dashboard.png" alt="Live greenhouse dashboard screen">
          </div>
        </article>

        
        <article class="feature-card" data-feature="camera">
          <div class="feature-card-content">
            <span class="scene-label">Computer Vision</span>
            <h3 class="scene-headline">See what your plants see.</h3>
            <p class="scene-sub">Grovia uses plant vision to monitor plant health and identify potential problems early.</p>
            <a href="login.php" class="scene-cta" data-gate="login">
              Try the AI model <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>
          </div>
          <div class="feature-card-media">
            <div class="photo-fx cam-fx" aria-hidden="true"><div class="fx-scan"></div></div>
            <img class="story-photo" src="assets/camera.png" alt="AI camera plant detection screen">
          </div>
        </article>

        
        <article class="feature-card" data-feature="temperature">
          <div class="feature-card-content">
            <span class="scene-label">Climate</span>
            <h3 class="scene-headline">Know your climate.</h3>
            <p class="scene-sub">The DHT sensor continuously monitors greenhouse temperature.</p>
            <div class="scene-stats">
              <div class="scene-stat"><span class="ss-label">Current Reading</span><span class="ss-value" data-count-to="25.6" data-suffix="°C">0°C</span></div>
              <div class="scene-stat"><span class="ss-label">Safe Max</span><span class="ss-value" data-count-to="40" data-suffix="°C">0°C</span></div>
            </div>
            <span class="scene-demo-note">Example greenhouse reading</span>
            <a href="login.php" class="scene-cta" data-gate="login">
              See it in action <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>
          </div>
          <div class="feature-card-media">
            <div class="photo-fx temp-fx" aria-hidden="true">
              <span class="fx-sun-rays"></span>
              <span class="fx-sun-glow"></span>
              <svg class="fx-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
              <svg class="fx-cloud fx-cloud-2" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
              <svg class="fx-cloud fx-cloud-1" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
              <svg class="fx-graph" viewBox="0 0 200 60" fill="none" preserveAspectRatio="none">
                <path class="fx-graph-line" d="M0 40 Q 20 20 40 32 T 80 24 T 120 36 T 160 18 T 200 28" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </div>
            <img class="story-photo" src="assets/temp.png" alt="Temperature monitoring screen">
          </div>
        </article>

        
        <article class="feature-card" data-feature="humidity">
          <div class="feature-card-content">
            <span class="scene-label">Air Conditions</span>
            <h3 class="scene-headline">Keep the air in range.</h3>
            <p class="scene-sub">Monitor humidity continuously and understand how conditions change over time.</p>
            <div class="scene-stats">
              <div class="scene-stat"><span class="ss-label">Current Humidity</span><span class="ss-value" data-count-to="60" data-suffix="%">0%</span></div>
              <div class="scene-stat"><span class="ss-label">Trend History</span><span class="ss-value" data-count-to="24" data-suffix="h">0h</span></div>
            </div>
            <span class="scene-demo-note">Example greenhouse reading</span>
            <a href="login.php" class="scene-cta" data-gate="login">
              See it in action <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>
          </div>
          <div class="feature-card-media">
            <div class="photo-fx humidity-fx" aria-hidden="true">
              <span class="fx-drop d1"></span>
              <span class="fx-drop d2"></span>
              <span class="fx-drop d3"></span>
            </div>
            <img class="story-photo" src="assets/humidity.png" alt="Humidity monitoring screen">
          </div>
        </article>

        
        <article class="feature-card" data-feature="irrigation">
          <div class="feature-card-content">
            <span class="scene-label">Automation</span>
            <h3 class="scene-headline">Water only when needed.</h3>
            <div class="scene-sequence">
              <div class="seq-step"><span class="seq-label">Soil Moisture</span><span class="seq-value" data-count-to="45" data-suffix="%">0%</span></div>
              <span class="seq-arrow" aria-hidden="true">↓</span>
              <div class="seq-step"><span class="seq-label">Irrigation Recommended</span></div>
              <span class="seq-arrow" aria-hidden="true">↓</span>
              <div class="seq-step"><span class="seq-label">Pump</span><span class="seq-value is-on">ON</span></div>
            </div>
            <span class="scene-demo-note">Example sequence</span>
            <a href="login.php" class="scene-cta" data-gate="login">
              See it in action <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>
          </div>
          <div class="feature-card-media">
            <div class="photo-fx irrigation-fx" aria-hidden="true">
              <svg class="fx-flow" viewBox="0 0 200 40" fill="none" preserveAspectRatio="none">
                <path class="fx-flow-line" d="M0 20 H200" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="1 11"/>
              </svg>
            </div>
            <img class="story-photo" src="assets/irrigation.jpg" alt="Smart irrigation control screen" loading="lazy">
          </div>
        </article>

        
        <article class="feature-card" data-feature="fan">
          <div class="feature-card-content">
            <span class="scene-label">Cooling</span>
            <h3 class="scene-headline">Respond when conditions change.</h3>
            <div class="scene-sequence">
              <div class="seq-step"><span class="seq-label">Temperature</span><span class="seq-value" data-count-to="28" data-suffix="°C">0°C</span></div>
              <span class="seq-arrow" aria-hidden="true">↓</span>
              <div class="seq-step"><span class="seq-label">Threshold Reached</span></div>
              <span class="seq-arrow" aria-hidden="true">↓</span>
              <div class="seq-step"><span class="seq-label">Fan</span><span class="seq-value is-on">ON</span></div>
            </div>
            <span class="scene-demo-note">Example sequence</span>
            <a href="login.php" class="scene-cta" data-gate="login">
              See it in action <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>
          </div>
          <div class="feature-card-media">
            <div class="photo-fx fan-fx" aria-hidden="true">
              <svg class="fx-wind" viewBox="0 0 140 90" fill="none">
                <path class="wind-stream s1" d="M-20 16 Q0 6 20 16 T60 16 T100 16 T140 16 T180 16"/>
                <path class="wind-stream s2" d="M-20 45 Q0 33 20 45 T60 45 T100 45 T140 45 T180 45"/>
                <path class="wind-stream s3" d="M-20 72 Q0 60 20 72 T60 72 T100 72 T140 72 T180 72"/>
              </svg>
              <span class="fx-gust g1"></span>
              <span class="fx-gust g2"></span>
              <span class="fx-gust g3"></span>
            </div>
            <img class="story-photo" src="assets/fan.png" alt="Fan controller screen">
          </div>
        </article>

        
        <article class="feature-card is-closing" data-feature="closing">
          <div class="feature-card-content">
            <span class="scene-label">One Connected System</span>
            <h3 class="scene-headline">Sense. Think. Act.</h3>
            <p class="scene-sub">One connected system for a smarter greenhouse.</p>
          </div>
        </article>

      </div>

      <div class="story-dots">
        <span class="story-dot is-active"></span>
        <span class="story-dot"></span>
        <span class="story-dot"></span>
        <span class="story-dot"></span>
        <span class="story-dot"></span>
        <span class="story-dot"></span>
        <span class="story-dot"></span>
      </div>

    </div>
  </section>


  <section class="predict section-pad" id="predict">
    <div class="predict-atmosphere" aria-hidden="true"></div>
    <div class="container">
      <div class="predict-intro" data-reveal>
        <span class="eyebrow"><span class="dot"></span>Grovia / Prediction</span>
        <h2 class="predict-heading">See what your<br>greenhouse could become.</h2>
        <p class="predict-sub">Explore the transformation from an empty growing space to a planted greenhouse.</p>
      </div>

      <div class="predict-stage" data-reveal>
        <div class="predict-reveal" id="predictReveal">
          <img class="predict-reveal-base" src="assets/notplanted.jpg" alt="Empty greenhouse bed ready for planting" draggable="false">
          <img class="predict-reveal-source" src="assets/planted.jpg" alt="" draggable="false" aria-hidden="true">
          <canvas class="predict-reveal-canvas" aria-hidden="true"></canvas>
          <span class="predict-reveal-ring" aria-hidden="true"></span>
          <div class="predict-hint" id="predictHint">Hover to explore</div>
        </div>

        <div class="predict-annotations" id="predictAnnotations">
          <div class="predict-annotation"><span class="pa-label">Best Season</span><span class="pa-value" id="predictBestSeason">Summer, Spring</span></div>
          <div class="predict-annotation"><span class="pa-label">Spacing</span><span class="pa-value" id="predictSpacing">45 × 60 cm</span></div>
          <div class="predict-annotation"><span class="pa-label">Growth Time</span><span class="pa-value" id="predictGrowth">70–90 days</span></div>
          <div class="predict-annotation"><span class="pa-label">Density</span><span class="pa-value" id="predictDensity">~4 / m²</span></div>
        </div>
      </div>

      <div class="predict-crop-row" data-reveal>
        <div class="predict-crops" id="predictCrops" role="tablist" aria-label="Choose a crop">
          <button type="button" class="predict-crop is-active" data-crop="tomato" role="tab" aria-selected="true">Tomato</button>
          <button type="button" class="predict-crop" data-crop="cucumber" role="tab" aria-selected="false">Cucumber</button>
          <button type="button" class="predict-crop" data-crop="lettuce" role="tab" aria-selected="false">Lettuce</button>
          <button type="button" class="predict-crop" data-crop="strawberry" role="tab" aria-selected="false">Strawberry</button>
        </div>
        <a href="login.php" class="predict-cta" data-gate="login">
          View full prediction <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
      </div>
    </div>
  </section>

</main>


<footer id="contact">
  <div class="footer-atmosphere" aria-hidden="true"></div>

  <div class="footer-mega" data-reveal>
    <h2 class="footer-mega-heading">Let's grow the idea.</h2>
    <p class="footer-mega-sub">Technology that connects the greenhouse to intelligence.</p>
    <div class="footer-mega-actions">
      <a href="mailto:grovia.greenhouse@gmail.com" class="footer-mega-link">
        Start a conversation
        <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
      </a>
      <a href="mailto:grovia.greenhouse@gmail.com" class="footer-mega-email">grovia.greenhouse@gmail.com</a>
    </div>
  </div>

  <div class="footer-visual" data-reveal>
    <div class="footer-visual-media">
      <img id="footerParallaxImg" src="assets/hardware.jpg" alt="The real Grovia greenhouse prototype — sensors, controller and actuators wired inside the physical unit">
      <div class="footer-visual-overlay" aria-hidden="true"></div>
    </div>
    <span class="footer-visual-caption">Grovia / Physical System</span>
    <div class="footer-visual-statement">
      <p class="fvs-line">From an idea<span class="arrow-char">→</span>to a real system.</p>
      <ul class="fvs-list">
        <li>Hardware.</li><li>Connectivity.</li><li>Software.</li><li>Intelligence.</li><li>Automation.</li>
      </ul>
    </div>
  </div>

  <div class="container footer-main" data-reveal>
    <div class="footer-brand">
      <div class="logo">
        <div class="logo-icon"><img src="assets/logo-mark.png" alt="Grovia logo"></div>
        Grov<span class="brand-accent">ia</span>
      </div>
      <span class="footer-brand-tag">Smart Greenhouse System</span>
      <p class="footer-brand-line">Built with curiosity. Designed to grow.</p>
      <div class="footer-social">
        <a href="#" aria-label="Grovia on Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg></a>
        <a href="#" aria-label="Grovia on LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05C21.8 8.65 22 11.3 22 14.5V21h-4v-5.7c0-1.36-.02-3.1-1.9-3.1-1.9 0-2.2 1.48-2.2 3v5.8h-4V9Z"/></svg></a>
      </div>
    </div>
    <div class="footer-col">
      <h4>Explore</h4>
      <a href="#home">Home</a>
      <a href="#story">Features</a>
      <a href="about.php">About</a>
      <a href="#predict">Predict</a>
    </div>
    <div class="footer-col">
      <h4>System</h4>
      <a href="about.php#tech-hardware">Hardware</a>
      <a href="about.php#tech-ai">AI</a>
      <a href="about.php#tech-automation">Automation</a>
      <a href="about.php#inside-greenhouse">ESP32</a>
    </div>
    <div class="footer-col">
      <h4>Connect</h4>
      <a href="mailto:grovia.greenhouse@gmail.com">grovia.greenhouse@gmail.com</a>
      <a href="#">Instagram</a>
      <a href="#">LinkedIn</a>
    </div>
  </div>

  <div class="container footer-bottom">
    <span class="footer-signature">Grovia / Smart Agriculture / Hardware + Software + AI / 2026</span>
    <button type="button" class="footer-top" id="footerBackTop">
      Back to top
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
    </button>
  </div>
</footer>


<div class="login-gate" id="loginGate" role="alertdialog" aria-live="assertive" aria-hidden="true">
  <div class="login-gate-card">
    <div class="login-gate-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2.5"/><path d="M7.5 10.5V7a4.5 4.5 0 0 1 9 0v3.5"/><circle cx="12" cy="15.2" r="1.6" fill="currentColor" stroke="none"/></svg>
    </div>
    <div class="login-gate-text">
      <strong>Login required</strong>
      <span>Sign in to unlock this feature — taking you there now</span>
    </div>
    <div class="login-gate-bar"><span class="login-gate-bar-fill"></span></div>
  </div>
</div>


<script src="js/vendor/gsap.min.js"></script>
<script src="js/vendor/ScrollTrigger.min.js"></script>
<script src="js/vendor/lenis.min.js"></script>
<script src="js/main.js?v=76"></script>
<script type="module" src="js/greenhouse-3d-scroll.js?v=2"></script>

</body>
</html>
