<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About | Grovia</title>
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
  /* About page — self-contained additions, doesn't touch style.css's
     landing-page-specific rules (hero video, story pin, predict teaser). */

  /* Tri-tone accent palette, scoped to this page — green stays the
     primary brand color (matches the rest of the app), amber and teal
     are new secondary accents for visual variety across sections. */
  .about-hero, .about-section {
    --amber-400: #fbbf24; --amber-500: #d97706; --amber-glow: rgba(217, 119, 6, 0.35);
    --teal-400: #22d3ee; --teal-500: #0891b2; --teal-glow: rgba(8, 145, 178, 0.35);
  }

  /* ---------- Hero: animated mesh-gradient backdrop, giant faded
     wordmark behind the headline, word-by-word blur-in title ---------- */
  .about-hero {
    position: relative; overflow: hidden;
    padding: calc(var(--nav-h, 88px) + 120px) 0 140px;
    background: #0b0d11; text-align: center; isolation: isolate;
  }
  /* Real footage of the actual unit, not an illustration — dimmed and
     desaturated well behind the mesh glow so it reads as atmosphere,
     not a loud autoplay video. Same asset the landing hero already uses. */
  .about-hero-video {
    position: absolute; inset: 0; z-index: -3; width: 100%; height: 100%; object-fit: cover;
    opacity: 0.32; filter: saturate(0.65) brightness(0.5);
  }
  .about-hero-mesh {
    position: absolute; inset: -20%; z-index: -2;
    background:
      radial-gradient(38% 45% at 18% 22%, rgba(76, 175, 80, 0.55), transparent 60%),
      radial-gradient(32% 40% at 82% 15%, rgba(56, 189, 248, 0.35), transparent 60%),
      radial-gradient(45% 50% at 50% 88%, rgba(155, 229, 100, 0.4), transparent 60%),
      rgba(11, 13, 17, 0.72);
    filter: blur(60px);
    animation: aboutMeshDrift 18s ease-in-out infinite alternate;
  }
  @keyframes aboutMeshDrift {
    0% { transform: translate(0, 0) scale(1) rotate(0deg); }
    100% { transform: translate(-3%, 2%) scale(1.08) rotate(4deg); }
  }
  .about-hero-grain { position: absolute; inset: 0; z-index: -1; opacity: 0.05; background-image: radial-gradient(rgba(255,255,255,0.9) 1px, transparent 1px); background-size: 3px 3px; pointer-events: none; }
  .about-hero-word {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    font-family: var(--font-display); font-weight: 800; font-size: clamp(6rem, 22vw, 16rem);
    color: transparent; -webkit-text-stroke: 1.5px rgba(255,255,255,0.06);
    letter-spacing: -0.03em; white-space: nowrap; pointer-events: none; z-index: 0; user-select: none;
  }
  .about-hero-inner { position: relative; z-index: 1; }
  .about-hero .eyebrow { background: rgba(155, 229, 100, 0.14); color: #9be564; }
  .about-hero .eyebrow .dot { background: #9be564; }
  .about-hero h1 {
    font-size: clamp(2.4rem, 5.4vw, 4rem); margin: 22px auto 20px; max-width: 880px; color: #fff; line-height: 1.12;
  }
  .about-hero h1 .ah-word { display: inline-block; opacity: 0; filter: blur(10px); transform: translateY(24px); }
  .about-hero h1 .ah-accent { color: #9be564; }
  .about-hero p { font-family: var(--font-body); font-size: 1.08rem; color: rgba(255,255,255,0.6); line-height: 1.75; max-width: 640px; margin: 0 auto; opacity: 0; }
  .about-hero-scroll {
    margin-top: 56px; display: inline-flex; flex-direction: column; align-items: center; gap: 10px;
    color: rgba(255,255,255,0.4); font-size: 0.78rem; letter-spacing: 0.5px; text-transform: uppercase; opacity: 0;
  }
  .about-hero-scroll svg { width: 16px; height: 16px; animation: aboutScrollBounce 1.8s ease-in-out infinite; }
  @keyframes aboutScrollBounce { 0%, 100% { transform: translateY(0); opacity: 0.5; } 50% { transform: translateY(6px); opacity: 1; } }

  /* ---------- Shared section rhythm ---------- */
  .about-section { padding: 110px 0; position: relative; overflow: hidden; }
  .about-section.alt { background: var(--bg-soft); }
  .about-section h2 { font-size: clamp(1.9rem, 3.4vw, 2.6rem); margin-bottom: 20px; }
  .about-section .lead { font-family: var(--font-body); font-size: 1.06rem; color: var(--ink-soft); line-height: 1.85; max-width: 760px; }

  /* ---------- Mission: big ghost number + copy split ---------- */
  .about-mission-grid { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 60px; align-items: center; position: relative; z-index: 1; }
  .about-mission-num {
    font-family: var(--font-display); font-weight: 800; font-size: clamp(7rem, 14vw, 11rem); line-height: 0.85;
    color: transparent; -webkit-text-stroke: 2px var(--border); letter-spacing: -0.04em;
  }
  .about-mission-copy .lead { margin-top: 0; }
  .about-mission-stats { display: flex; gap: 40px; margin-top: 36px; flex-wrap: wrap; }
  .about-mission-stat .num { font-family: var(--font-display); font-weight: 700; font-size: 2rem; color: var(--green-600); line-height: 1; }
  .about-mission-stat .label { color: var(--ink-dim); font-size: 0.85rem; margin-top: 6px; }
  .about-mission-glow { position: absolute; width: 420px; height: 420px; background: var(--green-400); opacity: 0.1; border-radius: 50%; filter: blur(120px); top: -100px; left: -80px; z-index: 0; }
  /* Same subtle technical grid motif used in the finale, reused here so
     the two bookend sections share a visual language rather than each
     section inventing its own background treatment. */
  .about-mission-grid-bg {
    position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(rgba(15, 122, 62, 0.045) 1px, transparent 1px),
      linear-gradient(90deg, rgba(15, 122, 62, 0.045) 1px, transparent 1px);
    background-size: 64px 64px;
    -webkit-mask-image: linear-gradient(115deg, black 0%, transparent 55%);
    mask-image: linear-gradient(115deg, black 0%, transparent 55%);
  }

  /* ---------- Technology: 4-card grid, each with its own accent ---------- */
  .about-tech-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 24px; margin-top: 44px; }
  .about-tech-card {
    padding: 32px 26px; border-radius: 22px; border: 1px solid var(--border); background: var(--white);
    box-shadow: var(--shadow-sm); transition: transform 0.35s cubic-bezier(.2,.8,.2,1), box-shadow 0.35s ease, border-color 0.35s ease;
  }
  .about-tech-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
  .about-tech-icon {
    width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
    margin-bottom: 18px; color: #fff;
  }
  .about-tech-icon svg { width: 24px; height: 24px; }
  .about-tech-card.c-green .about-tech-icon { background: linear-gradient(135deg, var(--green-400), var(--green-600)); box-shadow: 0 10px 24px var(--green-glow); }
  .about-tech-card.c-amber .about-tech-icon { background: linear-gradient(135deg, var(--amber-400), var(--amber-500)); box-shadow: 0 10px 24px var(--amber-glow); }
  .about-tech-card.c-teal .about-tech-icon { background: linear-gradient(135deg, var(--teal-400), var(--teal-500)); box-shadow: 0 10px 24px var(--teal-glow); }
  .about-tech-card.c-green:hover { border-color: var(--green-400); }
  .about-tech-card.c-amber:hover { border-color: var(--amber-500); }
  .about-tech-card.c-teal:hover { border-color: var(--teal-500); }
  .about-tech-card h3 { font-size: 1.08rem; margin-bottom: 8px; }
  .about-tech-card p { font-family: var(--font-body); font-size: 0.92rem; color: var(--ink-dim); line-height: 1.65; }

  /* ---------- The People Behind Grovia ---------- */
  .people-statement {
    font-family: var(--font-display); font-weight: 600; font-size: clamp(1.2rem, 2.2vw, 1.5rem);
    color: var(--ink); margin: 0 0 10px;
  }
  .people-desc { font-family: var(--font-body); font-size: 0.98rem; color: var(--ink-dim); line-height: 1.7; max-width: 560px; margin: 0 0 56px; }

  /* Blueprint-style connector: RA — GROVIA, resolving into "Smart
     Greenhouse" once drawn. Each piece lights up in sequence via a single
     ScrollTrigger + staggered CSS transition-delays, not chained JS timers. */
  .people-connector { max-width: 620px; margin: 0 0 64px; }
  .pc-track { display: flex; align-items: center; justify-content: flex-start; gap: 0; }
  .pc-node {
    position: relative; padding: 0 4px; white-space: nowrap;
    font-family: var(--font-body); font-size: 0.76rem; font-weight: 700; letter-spacing: 0.12em;
    color: var(--ink-dim); transition: color 0.5s ease;
  }
  .pc-node::before {
    content: ''; position: absolute; left: 50%; top: -13px; transform: translateX(-50%);
    width: 6px; height: 6px; border-radius: 50%; background: var(--border);
    transition: background 0.5s ease, box-shadow 0.5s ease;
  }
  /* Each node + the segment feeding into it lights up in sequence, purely
     via staggered transition-delay off one .is-active toggle — n1 (RA)
     first, then the segment into n2 (Grovia). */
  .pc-node.n1 { transition-delay: 0s; }
  .pc-node.n1::before { transition-delay: 0s; }
  .pc-node.n2 { transition-delay: 0.4s; }
  .pc-node.n2::before { transition-delay: 0.4s; }
  .pc-node.n3 { transition-delay: 0.8s; }
  .pc-node.n3::before { transition-delay: 0.8s; }
  .people-connector.is-active .pc-node::before { background: var(--green-500); box-shadow: 0 0 0 4px var(--green-glow); }
  .people-connector.is-active .pc-node:not(.pc-node-center) { color: var(--green-600); }
  .pc-node-center { font-family: var(--font-display); font-weight: 700; letter-spacing: 0.02em; color: var(--ink); }
  .pc-seg { flex: 1; height: 1px; max-width: 90px; background: var(--border); position: relative; overflow: hidden; }
  .pc-seg::after { content: ''; position: absolute; inset: 0; background: var(--green-500); transform-origin: left; transform: scaleX(0); transition: transform 0.8s ease; }
  .pc-seg.d1::after { transition-delay: 0.15s; }
  .pc-seg.d2::after { transition-delay: 0.55s; }
  .people-connector.is-active .pc-seg::after { transform: scaleX(1); }
  .pc-resolve {
    margin-top: 20px; font-family: var(--font-display); font-weight: 700; font-size: 0.8rem;
    letter-spacing: 0.16em; text-transform: uppercase; color: var(--green-600);
    opacity: 0; transform: translateY(6px); transition: opacity 0.7s ease 1.05s, transform 0.7s ease 1.05s;
  }
  .people-connector.is-active .pc-resolve { opacity: 1; transform: translateY(0); }

  .people-grid { display: grid; grid-template-columns: minmax(0, 380px); justify-content: center; gap: 26px; }
  .person-card {
    position: relative; overflow: hidden; border-radius: var(--radius-lg);
    border: 1px solid var(--border); background: var(--white);
    padding: 40px 40px 36px; min-height: 380px; display: flex; flex-direction: column; justify-content: flex-end;
    transition: transform 0.5s cubic-bezier(.2,.8,.2,1), box-shadow 0.5s ease, border-color 0.5s ease;
  }
  .person-card::after {
    content: ''; position: absolute; inset: -30%; z-index: 0; opacity: 0; pointer-events: none;
    background: radial-gradient(circle at 75% 20%, var(--green-400), transparent 60%);
    filter: blur(70px); transition: opacity 0.6s ease;
  }
  .person-card:hover { transform: translateY(-7px); box-shadow: var(--shadow-lg); border-color: var(--green-400); }
  .person-card:hover::after { opacity: 0.22; }
  .person-initials { position: absolute; top: 14px; left: 34px; z-index: 0; pointer-events: none; user-select: none; }
  .person-initials span {
    display: block; font-family: var(--font-display); font-weight: 800; line-height: 1;
    font-size: clamp(5.5rem, 11vw, 8.5rem); letter-spacing: -0.04em;
  }
  .pi-outline { color: transparent; -webkit-text-stroke: 2px var(--border); transition: -webkit-text-stroke-color 0.6s ease; }
  .pi-fill {
    position: absolute; inset: 0; color: transparent; -webkit-text-fill-color: transparent;
    background: linear-gradient(135deg, var(--green-400), var(--green-600));
    -webkit-background-clip: text; background-clip: text; opacity: 0; transition: opacity 0.6s ease;
  }
  .person-card:hover .pi-outline { -webkit-text-stroke-color: var(--green-400); }
  .person-card:hover .pi-fill { opacity: 0.16; }
  .person-body { position: relative; z-index: 1; }
  .person-body h3 { font-size: 1.25rem; margin-bottom: 4px; }
  .person-role { font-family: var(--font-body); font-size: 0.9rem; color: var(--ink-dim); margin-bottom: 16px; }
  .person-tags { display: flex; flex-wrap: wrap; gap: 8px; opacity: 0.65; transform: translateY(4px); transition: opacity 0.45s ease, transform 0.45s ease; }
  .person-card:hover .person-tags { opacity: 1; transform: translateY(0); }
  .person-tags span {
    padding: 5px 13px; border-radius: 50px; background: var(--green-50); color: var(--green-600);
    font-family: var(--font-body); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
  }
  @media (max-width: 900px) {
    .people-grid { grid-template-columns: 1fr; }
    .pc-seg { max-width: 46px; }
  }

  /* ======================================================
     NEW — Final section: "Let's Grow the Idea"
     A single cinematic closing moment, not a contact card: a hand-drawn
     seed → plant → greenhouse → data → wordmark reveal (SVG stroke/opacity
     staged off one scroll trigger, same technique as the How-It-Works
     line-draws and the People connector, for a consistent visual language
     at the very end of the page), then headline, interactive email, CTA
     and a closing line.
     ====================================================== */
  .finale-section {
    padding: 130px 0 110px; text-align: center; position: relative; overflow: hidden;
    background: linear-gradient(180deg, var(--bg-soft) 0%, #fbfcfa 100%);
  }
  .finale-grid {
    position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(rgba(15, 122, 62, 0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(15, 122, 62, 0.05) 1px, transparent 1px);
    background-size: 64px 64px;
    -webkit-mask-image: radial-gradient(ellipse 60% 55% at 50% 38%, black 25%, transparent 72%);
    mask-image: radial-gradient(ellipse 60% 55% at 50% 38%, black 25%, transparent 72%);
  }
  .finale-inner { position: relative; z-index: 1; max-width: 600px; margin: 0 auto; padding: 0 20px; }

  /* Grow illustration — a one-time staged reveal: seed, stem, leaves,
     greenhouse frame, data particles, wordmark, each timed a beat after
     the last via transition-delay off a single .is-active toggle. */
  .grow-illustration { width: 220px; height: 202px; margin: 0 auto 8px; position: relative; }
  .grow-illustration svg { width: 100%; height: 100%; overflow: visible; }
  .gi-stem, .gi-house, .gi-house2 { fill: none; stroke-dasharray: 100; stroke-dashoffset: 100; }
  .gi-stem { stroke: var(--green-600); stroke-width: 2.2; stroke-linecap: round; transition: stroke-dashoffset 1s ease 0.3s; }
  .gi-house, .gi-house2 { stroke: var(--ink-dim); stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; transition: stroke-dashoffset 1.1s ease 1.6s; }
  .gi-house2 { stroke: var(--border); }
  .gi-seed { fill: var(--green-600); opacity: 0; transform: scale(0.3); transform-origin: 120px 187px; transition: opacity 0.6s ease, transform 0.6s ease; }
  .gi-leaf { opacity: 0; transform: scale(0.4); transition: opacity 0.6s ease, transform 0.6s ease; }
  .gi-leaf-l { fill: var(--green-400); transform-origin: 120px 150px; transition-delay: 1.05s; }
  .gi-leaf-r { fill: var(--green-500); transform-origin: 120px 130px; transition-delay: 1.3s; }
  .gi-particle { fill: var(--green-400); opacity: 0; transition: opacity 0.7s ease; }
  .gi-particle.p1 { transition-delay: 2.9s; }
  .gi-particle.p2 { transition-delay: 3.0s; }
  .gi-particle.p3 { transition-delay: 3.1s; fill: var(--green-500); }
  .gi-particle.p4 { transition-delay: 3.2s; }
  .gi-particle.p5 { transition-delay: 3.3s; }
  .gi-word {
    opacity: 0; transform: translateY(8px); transition: opacity 0.8s ease 3.6s, transform 0.8s ease 3.6s;
    font-family: var(--font-display); font-weight: 700; font-size: 0.92rem; letter-spacing: 0.06em;
    color: var(--ink); margin-top: 6px;
  }
  .gi-word span { color: var(--green-500); }
  .grow-illustration.is-active .gi-stem,
  .grow-illustration.is-active .gi-house,
  .grow-illustration.is-active .gi-house2 { stroke-dashoffset: 0; }
  .grow-illustration.is-active .gi-seed { opacity: 1; transform: scale(1); }
  .grow-illustration.is-active .gi-leaf { opacity: 1; transform: scale(1); }
  .grow-illustration.is-active .gi-particle { opacity: 1; }
  .grow-illustration.is-active .gi-word { opacity: 1; transform: translateY(0); }

  .finale-heading { font-size: clamp(2.1rem, 5vw, 3.2rem); line-height: 1.1; margin: 4px 0 18px; }
  .finale-sub { font-family: var(--font-body); font-size: 1.05rem; color: var(--ink-soft); line-height: 1.75; max-width: 460px; margin: 0 auto; }

  .finale-touch-label {
    display: block; margin: 50px 0 14px; font-family: var(--font-body); font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.14em; text-transform: uppercase; color: var(--green-600);
  }
  .finale-email {
    position: relative; display: inline-flex; align-items: center; gap: 10px; padding-bottom: 5px;
    font-family: var(--font-display); font-weight: 600; font-size: clamp(1.05rem, 2.4vw, 1.3rem);
    color: var(--ink); transition: color 0.35s ease, transform 0.35s ease, text-shadow 0.35s ease;
  }
  .finale-email::after {
    content: ''; position: absolute; left: 0; right: 100%; bottom: 0; height: 2px; background: var(--green-500);
    transition: right 0.45s cubic-bezier(.2,.8,.2,1);
  }
  .finale-email:hover { color: var(--green-600); transform: translateX(3px); text-shadow: 0 0 20px var(--green-glow); }
  .finale-email:hover::after { right: 0; }
  .finale-email .fe-icon { width: 19px; height: 19px; flex-shrink: 0; transition: transform 0.4s cubic-bezier(.2,.8,.2,1); }
  .finale-email:hover .fe-icon { transform: translateY(-2px) rotate(-8deg); }

  .finale-cta {
    position: relative; overflow: hidden; display: inline-flex; align-items: center; gap: 10px;
    margin-top: 40px; padding: 17px 34px; border-radius: 50px;
    background: linear-gradient(135deg, var(--green-500), var(--green-600)); color: #fff;
    font-family: var(--font-body); font-weight: 700; font-size: 0.95rem; letter-spacing: 0.01em;
    box-shadow: 0 16px 40px rgba(76, 175, 80, 0.28);
    transition: transform 0.4s cubic-bezier(.2,.8,.2,1), box-shadow 0.4s ease;
  }
  .finale-cta:hover { transform: translateY(-3px); box-shadow: 0 22px 54px rgba(76, 175, 80, 0.4); }
  .finale-cta .arrow { display: inline-flex; transition: transform 0.4s cubic-bezier(.2,.8,.2,1); }
  .finale-cta:hover .arrow { transform: translateX(6px); }
  .finale-cta::before {
    content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%;
    background: linear-gradient(115deg, transparent, rgba(255,255,255,0.35), transparent);
    transition: left 0.7s ease;
  }
  .finale-cta:hover::before { left: 130%; }
  .fc-particle { position: absolute; top: 50%; width: 4px; height: 4px; border-radius: 50%; background: #fff; opacity: 0; transition: opacity 0.5s ease, transform 0.5s ease; }
  .fc-particle.p1 { left: 22%; transform: translate(-50%, -50%) scale(0.5); }
  .fc-particle.p2 { left: 38%; transform: translate(-50%, -50%) scale(0.5); transition-delay: 0.08s; }
  .finale-cta:hover .fc-particle { opacity: 0.75; }
  .finale-cta:hover .fc-particle.p1 { transform: translate(-50%, -170%) scale(1); }
  .finale-cta:hover .fc-particle.p2 { transform: translate(-50%, -210%) scale(1); }

  .finale-tagline { margin-top: 56px; font-family: var(--font-display); font-weight: 600; font-size: 1.05rem; color: var(--ink); }
  .finale-footnote { margin-top: 6px; font-family: var(--font-body); font-size: 0.8rem; color: var(--ink-dim); letter-spacing: 0.02em; }

  @media (max-width: 900px) {
    .about-mission-grid { grid-template-columns: 1fr; gap: 30px; }
    .about-mission-num { font-size: clamp(4rem, 20vw, 6rem); }
  }

  /* ---------- Per-section eyebrow color variety ---------- */
  .eyebrow.amber { background: rgba(217, 119, 6, 0.12); color: var(--amber-500); }
  .eyebrow.amber .dot { background: var(--amber-500); }
  .eyebrow.teal { background: rgba(8, 145, 178, 0.12); color: var(--teal-500); }
  .eyebrow.teal .dot { background: var(--teal-500); }

  /* ---------- Technology: dark, premium variant — same mood as the hero,
     not a flat white section, so the page has real light/dark rhythm ---------- */
  .about-section.dark { background: #0b0d11; isolation: isolate; }
  .about-section.dark::before {
    content: ''; position: absolute; inset: -20%; z-index: -1;
    background:
      radial-gradient(35% 45% at 15% 20%, rgba(76, 175, 80, 0.28), transparent 60%),
      radial-gradient(30% 42% at 85% 22%, rgba(217, 119, 6, 0.2), transparent 60%),
      radial-gradient(42% 50% at 50% 92%, rgba(8, 145, 178, 0.22), transparent 60%),
      #0b0d11;
    filter: blur(70px);
    animation: aboutMeshDrift 20s ease-in-out infinite alternate;
  }
  .about-section.dark h2 { color: #fff; }
  .about-section.dark .lead { color: rgba(255,255,255,0.62); }
  .about-section.dark .eyebrow.amber { background: rgba(251, 191, 36, 0.14); color: var(--amber-400); }
  .about-section.dark .eyebrow.amber .dot { background: var(--amber-400); }

  .about-tech-card.dark {
    background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.09);
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  }
  .about-tech-card.dark:hover { box-shadow: 0 24px 60px rgba(0,0,0,0.45); }
  .about-tech-card.dark.c-green:hover { border-color: rgba(76, 175, 80, 0.5); }
  .about-tech-card.dark.c-amber:hover { border-color: rgba(251, 191, 36, 0.5); }
  .about-tech-card.dark.c-teal:hover { border-color: rgba(34, 211, 238, 0.5); }
  .about-tech-card.dark h3 { color: #fff; }
  .about-tech-card.dark p { color: rgba(255,255,255,0.6); }

  /* Soft breathing glow behind each icon, color matched to its card */
  .about-tech-icon { position: relative; }
  .about-tech-icon::before {
    content: ''; position: absolute; inset: -10px; z-index: -1; border-radius: 20px;
    animation: aboutIconPulse 3.2s ease-in-out infinite;
  }
  .about-tech-card.c-green .about-tech-icon::before { background: var(--green-400); filter: blur(18px); }
  .about-tech-card.c-amber .about-tech-icon::before { background: var(--amber-400); filter: blur(18px); }
  .about-tech-card.c-teal .about-tech-icon::before { background: var(--teal-400); filter: blur(18px); }
  @keyframes aboutIconPulse { 0%, 100% { opacity: 0.3; transform: scale(0.85); } 50% { opacity: 0.65; transform: scale(1.2); } }

  /* ======================================================
     Signature section — "One System. Every Layer."
     Elevated typography + a continuous ambient particle flow (not just
     the one-time node reveal) since this is one of the page's three
     most important visual moments.
     ====================================================== */
  .layers-heading { font-size: clamp(2.4rem, 6vw, 4.2rem); line-height: 1.03; letter-spacing: -0.02em; margin: 4px 0 20px; }
  .layers-heading span { display: block; }
  .hw-phases { display: flex; justify-content: space-between; max-width: 580px; margin: 48px auto 0; padding: 0 6px; }
  .hw-phases span { font-family: var(--font-body); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ink-dim); }
  .hw-diagram { position: relative; max-width: 720px; margin: 40px auto 0; }
  .hw-line { position: absolute; left: 50%; top: 6px; bottom: 6px; width: 3px; margin-left: -1.5px; background: var(--border); border-radius: 3px; overflow: hidden; }
  /* Ambient data particles — continuous once the diagram is in view, not
     part of the one-time draw-on, symbolizing live data moving between
     layers rather than a single reveal moment. */
  .hw-particle {
    position: absolute; left: 50%; top: 0; width: 5px; height: 5px; margin-left: -2.5px;
    border-radius: 50%; background: var(--green-400); opacity: 0;
    animation: hwParticleFlow 3.4s ease-in-out infinite;
  }
  .hw-particle.p2 { animation-delay: 1.15s; }
  .hw-particle.p3 { animation-delay: 2.3s; }
  @keyframes hwParticleFlow {
    0% { top: 0%; opacity: 0; }
    12% { opacity: 0.9; }
    88% { opacity: 0.9; }
    100% { top: 100%; opacity: 0; }
  }
  .hw-line-fill {
    position: absolute; inset: 0; transform-origin: top center; transform: scaleY(0);
    background: linear-gradient(180deg, var(--green-500), var(--amber-500), var(--teal-500), var(--green-500));
  }
  .hw-node { position: relative; display: flex; align-items: center; gap: 30px; padding: 30px 0; }
  .hw-node:nth-child(even) { flex-direction: row-reverse; text-align: right; }
  .hw-dot {
    position: relative; z-index: 2; flex-shrink: 0; width: 15px; height: 15px; border-radius: 50%;
    background: var(--white); border: 3px solid var(--border); margin: 0 auto;
    transition: border-color 0.4s ease, box-shadow 0.4s ease, transform 0.4s ease;
  }
  .hw-node.is-active .hw-dot { border-color: var(--green-500); box-shadow: 0 0 0 6px var(--green-glow); transform: scale(1.15); }
  .hw-card { flex: 1; opacity: 0; transition: opacity 0.7s ease, transform 0.7s ease; }
  .hw-node:nth-child(odd) .hw-card { transform: translateX(-18px); }
  .hw-node:nth-child(even) .hw-card { transform: translateX(18px); }
  .hw-node.is-active .hw-card { opacity: 1; transform: translateX(0); }
  .hw-icon {
    width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center;
    background: var(--green-50); color: var(--green-600); margin-bottom: 12px;
  }
  .hw-node:nth-child(even) .hw-icon { margin-left: auto; }
  .hw-icon svg { width: 22px; height: 22px; }
  .hw-phase-tag { display: block; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--green-600); margin-bottom: 8px; }
  .hw-card h4 { font-size: 1.02rem; margin: 0 0 6px; }
  .hw-card p { font-family: var(--font-body); font-size: 0.88rem; color: var(--ink-dim); line-height: 1.6; max-width: 320px; }
  .hw-node:nth-child(even) .hw-card p { margin-left: auto; }

  @media (max-width: 720px) {
    .hw-phases { display: none; }
    .hw-diagram { padding-left: 30px; }
    .hw-line { left: 4px; margin-left: 0; }
    .hw-node, .hw-node:nth-child(even) { flex-direction: row; text-align: left; }
    .hw-dot { margin: 0; }
    .hw-node:nth-child(odd) .hw-card, .hw-node:nth-child(even) .hw-card { transform: translateY(16px) translateX(0); }
    .hw-node.is-active .hw-card { transform: translate(0,0); }
    .hw-icon, .hw-node:nth-child(even) .hw-icon { margin-left: 0; }
    .hw-card p, .hw-node:nth-child(even) .hw-card p { margin-left: 0; }
  }

  /* ======================================================
     NEW — Inside the Grovia Greenhouse: 3D hotspot scene
     ====================================================== */
  .gh3d-frame {
    position: relative; display: grid; grid-template-columns: 32% 68%;
    min-height: 560px; height: 78vh; max-height: 780px;
    border-radius: var(--radius-lg); overflow: hidden; background: #f5f6f2; border: 1px solid var(--border);
    margin-top: 44px;
  }
  .gh3d-info { padding: 42px 36px; display: flex; flex-direction: column; justify-content: center; }
  .gh3d-panel-sub { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--green-600); margin-bottom: 12px; transition: opacity 0.25s ease; }
  .gh3d-panel-title { font-family: var(--font-display); font-weight: 700; font-size: 1.4rem; margin-bottom: 14px; color: var(--ink); transition: opacity 0.25s ease; line-height: 1.25; }
  .gh3d-panel-desc { font-family: var(--font-body); font-size: 0.92rem; color: var(--ink-soft); line-height: 1.75; transition: opacity 0.25s ease; }
  .gh3d-stage { position: relative; }
  #gh3dCanvas { position: absolute; inset: 0; width: 100%; height: 100%; display: block; cursor: grab; touch-action: none; }
  #gh3dCanvas:active { cursor: grabbing; }
  .gh3d-hotspots { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
  .gh3d-hotspot {
    position: absolute; top: 0; left: 0; width: 14px; height: 14px; margin: -7px 0 0 -7px;
    border-radius: 50%; background: var(--green-500); border: 2px solid #fff; box-shadow: 0 0 0 4px var(--green-glow);
    cursor: pointer; pointer-events: auto; transition: transform 0.25s ease;
    animation: gh3dPulse 2.6s ease-in-out infinite;
  }
  .gh3d-hotspot:hover, .gh3d-hotspot.is-active { transform: scale(1.5); animation-play-state: paused; }
  @keyframes gh3dPulse { 0%, 100% { box-shadow: 0 0 0 4px var(--green-glow); } 50% { box-shadow: 0 0 0 9px transparent; } }
  .gh3d-drag-hint {
    position: absolute; bottom: 20px; right: 22px; display: flex; align-items: center; gap: 8px;
    font-family: var(--font-body); font-size: 0.72rem; color: var(--ink-dim); background: rgba(255,255,255,0.82);
    padding: 7px 13px; border-radius: 50px; backdrop-filter: blur(6px); pointer-events: none;
  }
  .gh3d-drag-hint svg { width: 14px; height: 14px; }
  @media (max-width: 900px) {
    .gh3d-frame { grid-template-columns: 1fr; grid-template-rows: auto 1fr; height: auto; min-height: 0; }
    .gh3d-stage { height: 56vh; min-height: 380px; }
    .gh3d-info { padding: 26px 24px; }
  }

  /* ======================================================
     NEW — From Data to Action
     ====================================================== */
  .dta-gauge-row { display: flex; align-items: center; gap: 18px; margin: 10px 0 52px; }
  .dta-gauge { position: relative; width: 62px; height: 62px; flex-shrink: 0; }
  .dta-gauge svg { width: 100%; height: 100%; transform: rotate(-90deg); }
  .dta-gauge circle { fill: none; stroke-width: 6; }
  .dta-gauge .track { stroke: var(--border); }
  .dta-gauge .fill { stroke: #d97706; stroke-linecap: round; stroke-dasharray: 163.4; stroke-dashoffset: 163.4; }
  .dta-gauge-label .val { font-family: var(--font-display); font-weight: 700; font-size: 1.25rem; color: var(--ink); display: block; }
  .dta-gauge-label .cap { font-family: var(--font-body); font-size: 0.78rem; color: var(--ink-dim); }
  .dta-steps { position: relative; max-width: 620px; }
  .dta-line { position: absolute; left: 21px; top: 6px; bottom: 6px; width: 2px; background: var(--border); border-radius: 2px; overflow: hidden; }
  .dta-line-fill { position: absolute; inset: 0; transform-origin: top; transform: scaleY(0); background: var(--green-500); }
  .dta-step { position: relative; display: flex; gap: 22px; padding: 18px 0; opacity: 0.4; transition: opacity 0.5s ease; }
  .dta-step.is-active { opacity: 1; }
  .dta-num {
    flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display); font-weight: 700; font-size: 0.82rem; color: var(--ink-dim);
    background: var(--white); border: 2px solid var(--border); z-index: 1; transition: border-color 0.4s ease, color 0.4s ease, box-shadow 0.4s ease;
  }
  .dta-step.is-active .dta-num { border-color: var(--green-500); color: var(--green-600); box-shadow: 0 0 0 5px var(--green-glow); }
  .dta-step p { font-family: var(--font-body); font-size: 0.98rem; color: var(--ink-soft); padding-top: 10px; transition: color 0.4s ease; }
  .dta-step.is-active p { color: var(--ink); }

  /* ======================================================
     NEW — Why Grovia: editorial numbered pillars
     ====================================================== */
  .why-grovia-grid { display: grid; grid-template-columns: repeat(4, 1fr); margin-top: 50px; border-top: 1px solid var(--border); }
  .wg-item { padding: 30px 26px 30px 0; border-bottom: 1px solid var(--border); border-right: 1px solid var(--border); }
  .wg-item:nth-child(4n) { border-right: none; padding-right: 0; }
  .wg-num { font-family: var(--font-display); font-weight: 700; font-size: 0.85rem; color: var(--green-500); margin-bottom: 14px; }
  .wg-item h3 { font-size: 1.02rem; margin-bottom: 10px; letter-spacing: -0.01em; }
  .wg-item p { font-family: var(--font-body); font-size: 0.88rem; color: var(--ink-dim); line-height: 1.6; }
  @media (max-width: 900px) {
    .why-grovia-grid { grid-template-columns: repeat(2, 1fr); }
    .wg-item:nth-child(4n) { border-right: 1px solid var(--border); padding-right: 26px; }
    .wg-item:nth-child(2n) { border-right: none; padding-right: 0; }
  }
  @media (max-width: 560px) {
    .why-grovia-grid { grid-template-columns: 1fr; }
    .wg-item, .wg-item:nth-child(2n), .wg-item:nth-child(4n) { border-right: none; padding-right: 0; }
  }

  /* ======================================================
     "Built From the Ground Up" — horizontal engineering timeline
     ====================================================== */
  .journey-heading { text-align: center; position: relative; z-index: 1; }
  .journey-heading .lead { margin: 0 auto; text-align: center; }
  .journey-section { position: relative; }
  .journey-grid-bg {
    position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(rgba(15, 122, 62, 0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(15, 122, 62, 0.05) 1px, transparent 1px);
    background-size: 56px 56px;
    -webkit-mask-image: radial-gradient(ellipse 75% 80% at 50% 50%, black 20%, transparent 78%);
    mask-image: radial-gradient(ellipse 75% 80% at 50% 50%, black 20%, transparent 78%);
  }
  .journey-pin { position: relative; z-index: 1; height: 100vh; overflow: hidden; display: flex; align-items: center; margin-top: 50px; }
  .journey-track { display: flex; gap: 22px; padding: 0 7vw; will-change: transform; }
  .journey-card {
    flex: 0 0 clamp(210px, 21vw, 270px); padding: 32px 24px; border-radius: var(--radius-lg);
    background: var(--white); border: 1px solid var(--border); box-shadow: var(--shadow-sm);
  }
  .journey-card .jc-num {
    font-family: var(--font-display); font-weight: 800; font-size: 2.1rem;
    color: transparent; -webkit-text-stroke: 1.5px var(--green-400); margin-bottom: 16px;
  }
  .journey-card .jc-photo {
    display: block; width: 44px; height: 44px; border-radius: 12px; object-fit: cover;
    margin-bottom: 14px; border: 1px solid var(--border);
  }
  .journey-card h3 { font-size: 1.02rem; margin-bottom: 10px; }
  .journey-card p { font-family: var(--font-body); font-size: 0.85rem; color: var(--ink-dim); line-height: 1.6; }
  .journey-card.is-final { background: linear-gradient(135deg, var(--green-500), var(--green-600)); border-color: transparent; }
  .journey-card.is-final .jc-num { -webkit-text-stroke-color: rgba(255,255,255,0.5); }
  .journey-card.is-final h3, .journey-card.is-final p { color: #fff; }
  .journey-card.is-final p { color: rgba(255,255,255,0.78); }
  @media (max-width: 900px) {
    .journey-pin { height: auto; display: block; margin-top: 34px; }
    .journey-track { overflow-x: auto; scroll-snap-type: x mandatory; padding: 6px 20px 22px; -webkit-overflow-scrolling: touch; }
    .journey-card { scroll-snap-align: start; flex: 0 0 78vw; }
  }

  /* ======================================================
     NEW — Engineering Behind Grovia
     ====================================================== */
  .eng-grid { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 40px; }
  .eng-item { flex: 1 1 190px; padding: 22px; border-radius: var(--radius-md); border: 1px solid var(--border); background: var(--bg-soft); }
  .eng-item .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-500); margin-bottom: 14px; }
  .eng-item h4 { font-size: 0.92rem; letter-spacing: 0.01em; margin-bottom: 6px; }
  .eng-item p { font-family: var(--font-body); font-size: 0.82rem; color: var(--ink-dim); }

  /* ======================================================
     System Status — a compact, honest product panel. This is a visual
     representation of Grovia's designed architecture, not a live poll of
     the backend, so it never claims real-time data (no timestamp, no
     "live" language beyond the static badge itself).
     ====================================================== */
  .status-section { padding: 70px 0; }
  .status-panel {
    max-width: 440px; margin: 0 auto; padding: 28px 32px; border-radius: var(--radius-lg);
    border: 1px solid var(--border); background: var(--white); box-shadow: var(--shadow-sm);
  }
  .status-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 18px; border-bottom: 1px solid var(--border); }
  .status-label { font-family: var(--font-display); font-weight: 700; font-size: 0.86rem; letter-spacing: 0.03em; color: var(--ink); }
  .status-badge { display: inline-flex; align-items: center; gap: 7px; font-family: var(--font-body); font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--green-600); }
  .status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-500); animation: statusPulse 2.2s ease-in-out infinite; }
  @keyframes statusPulse { 0%, 100% { box-shadow: 0 0 0 0 var(--green-glow); } 50% { box-shadow: 0 0 0 5px transparent; } }
  @media (prefers-reduced-motion: reduce) { .status-dot { animation: none; } }
  .status-rows { display: flex; flex-direction: column; gap: 11px; }
  .status-row { display: flex; align-items: center; justify-content: space-between; font-family: var(--font-body); font-size: 0.85rem; }
  .sr-label { color: var(--ink-dim); letter-spacing: 0.01em; }
  .sr-value { color: var(--ink); font-weight: 600; }
  .status-note { margin: 20px 0 0; font-family: var(--font-body); font-size: 0.72rem; color: var(--ink-dim); text-align: center; }
</style>
</head>
<body>

<!-- ============ NAVIGATION ============ -->
<nav class="nav solid" id="siteNav">
  <div class="nav-inner">
    <div class="logo">
      <div class="logo-icon">
        <img src="assets/logo-mark.png" alt="Grovia logo">
      </div>
      Grov<span class="brand-accent">ia</span>
    </div>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#home">Home</a></li>
      <li><a href="index.php#story">Features</a></li>
      <li><a href="about.php" class="active">About</a></li>
      <li><a href="index.php#predict">Predict</a></li>
      <li><a href="index.php#contact">Contact</a></li>
      <li><a href="login.php" class="btn btn-primary">Login</a></li>
    </ul>
    <div class="burger" id="burger"><span></span><span></span><span></span></div>
  </div>
</nav>

<main>

  <!-- ============ HERO ============ -->
  <section class="about-hero">
    <video class="about-hero-video" autoplay muted loop playsinline aria-hidden="true">
      <source src="assets/greenhouse.mp4" type="video/mp4">
    </video>
    <div class="about-hero-mesh" aria-hidden="true"></div>
    <div class="about-hero-grain" aria-hidden="true"></div>
    <span class="about-hero-word" aria-hidden="true">GROVIA</span>
    <div class="container about-hero-inner">
      <span class="eyebrow"><span class="dot"></span>About Grovia</span>
      <h1 id="aboutHeroTitle">
        <span class="ah-word">Growing</span> <span class="ah-word">the</span>
        <span class="ah-word ah-accent">future,</span> <span class="ah-word ah-accent">intelligently.</span>
      </h1>
      <p id="aboutHeroP">Grovia connects sensors, automation, software and AI into one intelligent greenhouse system — built end-to-end as a Computer Engineering capstone project.</p>
      <div class="about-hero-scroll" id="aboutHeroScroll">
        Explore Grovia
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
      </div>
    </div>
  </section>

  <!-- ============ STORY / MISSION ============ -->
  <section class="about-section" id="mission">
    <div class="about-mission-glow" aria-hidden="true"></div>
    <div class="about-mission-grid-bg" aria-hidden="true"></div>
    <div class="container about-mission-grid">
      <div class="about-mission-num" data-reveal-scale aria-hidden="true">01</div>
      <div class="about-mission-copy">
        <span class="eyebrow"><span class="dot"></span>Our Mission</span>
        <h2 data-reveal-up>From physical sensors to intelligent decisions.</h2>
        <p class="lead" data-reveal-up>
          Small-scale greenhouse growing usually means guesswork — checking soil by hand, watering on a hunch.
          Grovia replaces that guesswork with a real ESP32 sensor rig feeding a live dashboard, plus an AI-assisted
          planner for crop layouts and season-aware predictions — built end-to-end, as a capstone project, to prove
          the whole loop actually works.
        </p>
        <div class="about-mission-stats" data-reveal-up>
          <div class="about-mission-stat"><div class="num" data-count-to="5" data-suffix="">0</div><div class="label">Live Sensor Types</div></div>
          <div class="about-mission-stat"><div class="num" data-count-to="1" data-suffix="">0</div><div class="label">Real ESP32 Rig</div></div>
          <div class="about-mission-stat"><div class="num" data-count-to="24" data-suffix="/7">0</div><div class="label">Data Streaming</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ SIGNATURE: "ONE SYSTEM. EVERY LAYER." ============ -->
  <section class="about-section alt" id="how-it-works">
    <div class="container">
      <span class="eyebrow amber" data-reveal-up><span class="dot"></span>System Architecture</span>
      <h2 class="layers-heading" data-reveal-up><span>One system.</span><span>Every layer.</span></h2>
      <p class="lead" data-reveal-up>From a physical reading in the greenhouse to an action back on the hardware — one continuous loop, five layers.</p>

      <div class="hw-phases" aria-hidden="true">
        <span>Physical</span><span>Connected</span><span>Digital</span><span>Intelligent</span><span>Action</span>
      </div>

      <div class="hw-diagram">
        <div class="hw-line">
          <div class="hw-line-fill"></div>
          <span class="hw-particle p1" aria-hidden="true"></span>
          <span class="hw-particle p2" aria-hidden="true"></span>
          <span class="hw-particle p3" aria-hidden="true"></span>
        </div>

        <div class="hw-node">
          <div class="hw-dot"></div>
          <div class="hw-card">
            <span class="hw-phase-tag">Layer 01</span>
            <div class="hw-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h4l2-7 4 14 2-7h8"/></svg></div>
            <h4>Physical</h4>
            <p>Sensors · Fan · Pump · Servo · Camera</p>
          </div>
        </div>

        <div class="hw-node">
          <div class="hw-dot"></div>
          <div class="hw-card">
            <span class="hw-phase-tag">Layer 02</span>
            <div class="hw-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5a11 11 0 0 1 14 0"/><path d="M8.5 16a6 6 0 0 1 7 0"/><path d="M12 19.5h.01"/></svg></div>
            <h4>Connected</h4>
            <p>ESP32-S3 · Wi-Fi</p>
          </div>
        </div>

        <div class="hw-node">
          <div class="hw-dot"></div>
          <div class="hw-card">
            <span class="hw-phase-tag">Layer 03</span>
            <div class="hw-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="1"/><rect x="3" y="14" width="18" height="6" rx="1"/><path d="M7 7h.01M7 17h.01"/></svg></div>
            <h4>Digital</h4>
            <p>Backend · Database · Web Platform</p>
          </div>
        </div>

        <div class="hw-node">
          <div class="hw-dot"></div>
          <div class="hw-card">
            <span class="hw-phase-tag">Layer 04</span>
            <div class="hw-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 0 0-5 5v2a5 5 0 0 0 10 0V7a5 5 0 0 0-5-5Z"/><path d="M8 14v1a4 4 0 0 0 8 0v-1M12 19v3"/></svg></div>
            <h4>Intelligent</h4>
            <p>AI · Analysis · Recommendations</p>
          </div>
        </div>

        <div class="hw-node">
          <div class="hw-dot"></div>
          <div class="hw-card">
            <span class="hw-phase-tag">Layer 05</span>
            <div class="hw-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg></div>
            <h4>Action</h4>
            <p>Monitor · Decide · Automate</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ INSIDE THE GROVIA GREENHOUSE (NEW, 3D) ============ -->
  <section class="about-section" id="inside-greenhouse">
    <div class="container">
      <span class="eyebrow teal" data-reveal-up><span class="dot"></span>Real Hardware, Real Placement</span>
      <h2 data-reveal-up>Inside the Grovia greenhouse</h2>
      <p class="lead" data-reveal-up>Every part shown here sits exactly where it does on the physical unit. Hover or tap a component to see what it does.</p>

      <div class="gh3d-frame" id="gh3dSection" data-reveal-up>
        <div class="gh3d-info">
          <span class="gh3d-panel-sub" id="gh3dPanelSub">Hover or tap a component</span>
          <h3 class="gh3d-panel-title" id="gh3dPanelTitle">Explore the greenhouse</h3>
          <p class="gh3d-panel-desc" id="gh3dPanelDesc">Every sensor and actuator shown here is a real part of the Grovia hardware, positioned where it actually sits on the unit.</p>
        </div>
        <div class="gh3d-stage" id="gh3dStage">
          <canvas id="gh3dCanvas"></canvas>
          <div class="gh3d-hotspots" id="gh3dHotspots"></div>
          <div class="gh3d-drag-hint">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H5v4M15 5h4v4M5 15v4h4M19 15v4h-4"/></svg>
            Drag to rotate
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ FROM DATA TO ACTION (NEW) ============ -->
  <section class="about-section alt" id="data-to-action">
    <div class="container">
      <span class="eyebrow amber" data-reveal-up><span class="dot"></span>Worked Example</span>
      <h2 data-reveal-up>From data to action</h2>
      <p class="lead" data-reveal-up>Grovia isn't a dashboard that just displays numbers. Here's what actually happens when the soil gets dry.</p>

      <div class="dta-gauge-row" data-reveal-up>
        <div class="dta-gauge">
          <svg viewBox="0 0 60 60">
            <circle class="track" cx="30" cy="30" r="26"/>
            <circle class="fill" cx="30" cy="30" r="26"/>
          </svg>
        </div>
        <div class="dta-gauge-label">
          <span class="val" id="dtaGaugeVal">28%</span>
          <span class="cap">Soil moisture, live in this example</span>
        </div>
      </div>

      <div class="dta-steps">
        <div class="dta-line"><div class="dta-line-fill"></div></div>

        <div class="dta-step"><div class="dta-num">01</div><p>Soil moisture decreases.</p></div>
        <div class="dta-step"><div class="dta-num">02</div><p>The ESP32 detects the change.</p></div>
        <div class="dta-step"><div class="dta-num">03</div><p>The reading is sent to Grovia.</p></div>
        <div class="dta-step"><div class="dta-num">04</div><p>The system evaluates the greenhouse condition.</p></div>
        <div class="dta-step"><div class="dta-num">05</div><p>Irrigation is triggered.</p></div>
        <div class="dta-step"><div class="dta-num">06</div><p>The water pump activates.</p></div>
        <div class="dta-step"><div class="dta-num">07</div><p>Soil moisture returns toward the desired condition.</p></div>
      </div>
    </div>
  </section>

  <!-- ============ TECHNOLOGY ============ -->
  <section class="about-section dark" id="technology">
    <div class="container">
      <span class="eyebrow amber" data-reveal-up><span class="dot"></span>Under the Hood</span>
      <h2 data-reveal-up>What's actually running</h2>
      <div class="about-tech-grid">
        <div class="about-tech-card dark c-green" id="tech-hardware" data-reveal-card>
          <div class="about-tech-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
          </div>
          <h3>Real Hardware</h3>
          <p>An ESP32-S3 reads five live sensors — temperature and humidity, soil moisture, ambient light, air quality, and water tank level — and reports straight to the dashboard over WiFi.</p>
        </div>
        <div class="about-tech-card dark c-amber" id="tech-ai" data-reveal-card>
          <div class="about-tech-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 0 0-5 5v2a5 5 0 0 0 10 0V7a5 5 0 0 0-5-5Z"/><path d="M8 14v1a4 4 0 0 0 8 0v-1M12 19v3"/></svg>
          </div>
          <h3>AI-Powered Insights</h3>
          <p>The same Gemini model handles plant disease detection from a photo, Face ID sign-in, and plain-language summaries of what's happening in the greenhouse — just asked a different question each time.</p>
        </div>
        <div class="about-tech-card dark c-teal" id="tech-automation" data-reveal-card>
          <div class="about-tech-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.5 0 4.8 1 6.4 2.6"/><path d="M21 3v6h-6"/></svg>
          </div>
          <h3>Built to Automate</h3>
          <p>A relay-controlled fan and irrigation pump react to real sensor thresholds on their own, or can be switched to manual on/off control from the dashboard at any time.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ WHY GROVIA (NEW) ============ -->
  <section class="about-section" id="why-grovia">
    <div class="container">
      <span class="eyebrow" data-reveal-up><span class="dot"></span>Why Grovia</span>
      <h2 data-reveal-up>Sensing, software and physical action</h2>
      <p class="lead" data-reveal-up>Grovia is a smart greenhouse system that combines embedded hardware, connected software, automation and AI.</p>
      <div class="why-grovia-grid">
        <div class="wg-item" data-reveal-up>
          <div class="wg-num">01</div>
          <h3>Real Hardware</h3>
          <p>Grovia connects to physical sensors and actuators rather than relying only on simulated data.</p>
        </div>
        <div class="wg-item" data-reveal-up>
          <div class="wg-num">02</div>
          <h3>Connected Intelligence</h3>
          <p>Environmental data moves from the physical greenhouse to the digital platform.</p>
        </div>
        <div class="wg-item" data-reveal-up>
          <div class="wg-num">03</div>
          <h3>AI-Assisted Insights</h3>
          <p>AI can help analyze plant and greenhouse conditions.</p>
        </div>
        <div class="wg-item" data-reveal-up>
          <div class="wg-num">04</div>
          <h3>Automation</h3>
          <p>The system can interact with physical devices such as the fan, pump and roof mechanism.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ "BUILT FROM THE GROUND UP" ============ -->
  <section class="about-section alt journey-section" id="journey">
    <div class="journey-grid-bg" aria-hidden="true"></div>
    <div class="container journey-heading">
      <span class="eyebrow teal" data-reveal-up><span class="dot"></span>How It Was Built</span>
      <h2 data-reveal-up>Built from the ground up</h2>
      <p class="lead" data-reveal-up>An engineering project's journey, not a company history.</p>
    </div>
    <div class="journey-pin">
      <div class="journey-track" id="journeyTrack">
        <div class="journey-card"><div class="jc-num">01</div><h3>Idea</h3><p>Defining the greenhouse monitoring and automation problem.</p></div>
        <div class="journey-card"><div class="jc-num">02</div><h3>Design</h3><p>Planning the system architecture and physical layout.</p></div>
        <div class="journey-card"><img class="jc-photo" src="assets/part-dht11.jpg" alt="A real DHT11 sensor from the Grovia prototype"><h3>Hardware</h3><p>Selecting and wiring the real sensors and actuators.</p></div>
        <div class="journey-card"><img class="jc-photo" src="assets/part-esp32.webp" alt="The real ESP32-S3 board from the Grovia prototype"><h3>ESP32</h3><p>Programming the microcontroller to read and transmit data.</p></div>
        <div class="journey-card"><div class="jc-num">05</div><h3>Connectivity</h3><p>Connecting the physical system to the web platform.</p></div>
        <div class="journey-card"><div class="jc-num">06</div><h3>Web Platform</h3><p>Building the Grovia interface, backend and database.</p></div>
        <div class="journey-card"><div class="jc-num">07</div><h3>AI</h3><p>Adding AI-assisted plant analysis and intelligent insights.</p></div>
        <div class="journey-card"><div class="jc-num">08</div><h3>Integration</h3><p>Bringing hardware, software and AI together into one system.</p></div>
        <div class="journey-card is-final"><div class="jc-num">09</div><h3>Grovia</h3><p>A working smart greenhouse, end to end.</p></div>
      </div>
    </div>
  </section>

  <!-- ============ ENGINEERING BEHIND GROVIA (NEW) ============ -->
  <section class="about-section" id="engineering">
    <div class="container">
      <span class="eyebrow" data-reveal-up><span class="dot"></span>Disciplines</span>
      <h2 data-reveal-up>Engineering behind Grovia</h2>
      <div class="eng-grid">
        <div class="eng-item" data-reveal-up><div class="dot"></div><h4>Embedded Systems</h4><p>ESP32 + sensors + actuators</p></div>
        <div class="eng-item" data-reveal-up><div class="dot"></div><h4>IoT &amp; Connectivity</h4><p>Wi-Fi + communication</p></div>
        <div class="eng-item" data-reveal-up><div class="dot"></div><h4>Web Development</h4><p>Frontend + backend + database</p></div>
        <div class="eng-item" data-reveal-up><div class="dot"></div><h4>AI</h4><p>Plant analysis + intelligent recommendations</p></div>
        <div class="eng-item" data-reveal-up><div class="dot"></div><h4>Automation</h4><p>Fan + pump + servo control</p></div>
      </div>
    </div>
  </section>

  <!-- ============ TEAM ============ -->
  <section class="about-section alt" id="team">
    <div class="container">
      <span class="eyebrow teal" data-reveal-up><span class="dot"></span>The Creator</span>
      <h2 data-reveal-up>The mind behind Grovia</h2>
      <p class="lead" data-reveal-up>One creator. One connected vision.</p>

      <div class="people-intro">
        <p class="people-statement" data-reveal-up>Built by one. Powered by technology.</p>
        <p class="people-desc" data-reveal-up>Grovia brings together hardware, software, automation and AI in one connected smart greenhouse system.</p>
      </div>

      <div class="people-connector" id="peopleConnector" data-reveal-up>
        <div class="pc-track">
          <span class="pc-node n1" id="pcNodeRA">RA</span>
          <span class="pc-seg d1"></span>
          <span class="pc-node pc-node-center n2" id="pcNodeCenter">GROVIA</span>
        </div>
        <div class="pc-resolve">Smart Greenhouse</div>
      </div>

      <div class="people-grid">
        <div class="person-card" data-reveal-card>
          <div class="person-initials"><span class="pi-outline">RA</span><span class="pi-fill">RA</span></div>
          <div class="person-body">
            <h3>Razan Alkassim</h3>
            <p class="person-role">Creator &amp; Developer</p>
            <div class="person-tags">
              <span>Full-Stack</span><span>IoT</span><span>AI</span><span>Automation</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ SYSTEM STATUS ============ -->
  <section class="about-section status-section" id="system-status">
    <div class="container">
      <div class="status-panel" data-reveal-up>
        <div class="status-head">
          <span class="status-label">Grovia System</span>
          <span class="status-badge"><span class="status-dot"></span>Operational</span>
        </div>
        <div class="status-rows">
          <div class="status-row"><span class="sr-label">Hardware</span><span class="sr-value">Connected</span></div>
          <div class="status-row"><span class="sr-label">Backend</span><span class="sr-value">Online</span></div>
          <div class="status-row"><span class="sr-label">AI Engine</span><span class="sr-value">Ready</span></div>
        </div>
        <p class="status-note">A representation of Grovia's system architecture, not a live backend feed.</p>
      </div>
    </div>
  </section>

  <!-- ============ FINALE: "LET'S GROW THE IDEA" ============ -->
  <section class="finale-section" id="get-in-touch">
    <div class="finale-grid" aria-hidden="true"></div>
    <div class="finale-inner">
      <div class="grow-illustration" id="growIllustration" aria-hidden="true">
        <svg viewBox="0 0 240 200" fill="none">
          <path class="gi-house" pathLength="100" d="M55,190 L55,110 L120,62 L185,110 L185,190"/>
          <path class="gi-house2" pathLength="100" d="M38,190 L202,190"/>
          <circle class="gi-particle p1" cx="75" cy="92" r="2.6"/>
          <circle class="gi-particle p2" cx="165" cy="92" r="2.6"/>
          <circle class="gi-particle p3" cx="120" cy="56" r="2.8"/>
          <circle class="gi-particle p4" cx="47" cy="146" r="2.2"/>
          <circle class="gi-particle p5" cx="193" cy="146" r="2.2"/>
          <path class="gi-stem" pathLength="100" d="M120,185 L120,95"/>
          <path class="gi-leaf gi-leaf-l" d="M120,150 Q94,142 90,116 Q113,124 120,150 Z"/>
          <path class="gi-leaf gi-leaf-r" d="M120,130 Q146,120 150,95 Q127,103 120,130 Z"/>
          <ellipse class="gi-seed" cx="120" cy="187" rx="5.5" ry="7.5"/>
        </svg>
        <div class="gi-word">Grov<span>ia</span></div>
      </div>

      <h2 class="finale-heading" data-reveal-up>Let's grow the idea.</h2>
      <p class="finale-sub" data-reveal-up>Curious about how Grovia works?<br>Let's talk technology, automation, AI and smart agriculture.</p>

      <span class="finale-touch-label" data-reveal-up>Get in Touch</span>
      <a href="mailto:grovia.greenhouse@gmail.com" class="finale-email" data-reveal-up>
        <svg class="fe-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
        grovia.greenhouse@gmail.com
      </a>
      <div data-reveal-up>
        <a href="mailto:grovia.greenhouse@gmail.com" class="finale-cta">
          <span class="fc-particle p1" aria-hidden="true"></span><span class="fc-particle p2" aria-hidden="true"></span>
          Start a conversation <span class="arrow">→</span>
        </a>
      </div>

      <p class="finale-tagline" data-reveal-up>Built with curiosity. Designed to grow.</p>
      <p class="finale-footnote" data-reveal-up>Grovia — Smart Greenhouse System</p>
    </div>
  </section>

</main>

<!-- ============ FOOTER ============ -->
<footer>
  <div class="footer-atmosphere" aria-hidden="true"></div>

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
      <a href="index.php#home">Home</a>
      <a href="index.php#story">Features</a>
      <a href="about.php">About</a>
      <a href="index.php#predict">Predict</a>
    </div>
    <div class="footer-col">
      <h4>System</h4>
      <a href="#tech-hardware">Hardware</a>
      <a href="#tech-ai">AI</a>
      <a href="#tech-automation">Automation</a>
      <a href="#inside-greenhouse">ESP32</a>
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

<script src="js/vendor/gsap.min.js"></script>
<script src="js/vendor/ScrollTrigger.min.js"></script>
<script>
  // This page intentionally does NOT load the landing page's main.js
  // (hero video/carousel, pinned scroll story) — none of those sections
  // exist here. Its own animations live in this inline block instead.
  document.addEventListener('DOMContentLoaded', () => {
    const burger = document.getElementById('burger');
    const navLinks = document.getElementById('navLinks');
    if (burger && navLinks) {
      burger.addEventListener('click', () => {
        navLinks.classList.toggle('open');
        burger.classList.toggle('open');
      });
      navLinks.querySelectorAll('a').forEach((a) =>
        a.addEventListener('click', () => navLinks.classList.remove('open'))
      );
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hasGSAP = typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined';
    if (hasGSAP) gsap.registerPlugin(ScrollTrigger);

    if (!hasGSAP || reduceMotion) {
      document.querySelectorAll('.ah-word, #aboutHeroP, #aboutHeroScroll, [data-reveal-up], [data-reveal-card], [data-reveal-scale], [data-reveal]')
        .forEach((el) => { el.style.opacity = 1; el.style.filter = 'none'; el.style.transform = 'none'; });
      document.querySelectorAll('.hw-node, .dta-step').forEach((el) => el.classList.add('is-active'));
      document.querySelectorAll('.hw-line-fill, .dta-line-fill').forEach((el) => { el.style.transform = 'scaleY(1)'; });
      document.querySelectorAll('.hw-particle').forEach((el) => { el.style.animation = 'none'; el.style.opacity = '0'; });
      const connector = document.getElementById('peopleConnector');
      if (connector) {
        connector.classList.add('is-active');
        // Reduced motion: jump straight to the resolved state instead of
        // still playing out the ~1.75s staggered draw-on transition.
        connector.querySelectorAll('.pc-node, .pc-seg, .pc-resolve').forEach((el) => { el.style.transitionDuration = '0s'; el.style.transitionDelay = '0s'; });
      }
      const grow = document.getElementById('growIllustration');
      if (grow) {
        grow.classList.add('is-active');
        // Reduced motion: skip the ~4.4s staged grow reveal, show it resolved.
        grow.querySelectorAll('.gi-stem, .gi-house, .gi-house2, .gi-seed, .gi-leaf, .gi-particle, .gi-word')
          .forEach((el) => { el.style.transitionDuration = '0s'; el.style.transitionDelay = '0s'; });
      }
      return;
    }

    // ---- Hero entrance: title words blur in word-by-word, then the
    // paragraph and scroll cue settle in behind them. ----
    gsap.timeline({ defaults: { ease: 'power3.out' } })
      .to('.ah-word', { opacity: 1, y: 0, filter: 'blur(0px)', duration: 0.8, stagger: 0.07 }, 0.15)
      .to('#aboutHeroP', { opacity: 1, duration: 0.7 }, '-=0.3')
      .to('#aboutHeroScroll', { opacity: 1, duration: 0.6 }, '-=0.2');

    // ---- Generic scroll reveals, three flavors for visual variety ----
    gsap.utils.toArray('[data-reveal-up]').forEach((el) => {
      gsap.from(el, {
        opacity: 0, y: 36, duration: 0.8, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 88%' },
      });
    });
    gsap.utils.toArray('[data-reveal-scale]').forEach((el) => {
      gsap.from(el, {
        opacity: 0, scale: 0.7, duration: 1, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 85%' },
      });
    });
    gsap.utils.toArray('[data-reveal-card]').forEach((el, i) => {
      gsap.from(el, {
        opacity: 0, y: 50, rotateX: -8, duration: 0.9, ease: 'power2.out', delay: i * 0.12,
        scrollTrigger: { trigger: el, start: 'top 88%' },
      });
    });
    document.querySelectorAll('[data-reveal]').forEach((el) => {
      gsap.from(el, {
        opacity: 0, y: 30, duration: 0.7, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 92%' },
      });
    });

    // ---- Mission stat counters, count up once when scrolled to ----
    document.querySelectorAll('.about-mission-stat .num').forEach((el) => {
      const target = parseFloat(el.dataset.countTo);
      const suffix = el.dataset.suffix || '';
      ScrollTrigger.create({
        trigger: el, start: 'top 90%', once: true,
        onEnter: () => {
          const counter = { val: 0 };
          gsap.to(counter, {
            val: target, duration: 1.2, ease: 'power2.out',
            onUpdate() { el.textContent = (Number.isInteger(target) ? Math.round(counter.val) : counter.val.toFixed(1)) + suffix; },
          });
        },
      });
    });

    // ---- Mesh background drifts slightly with scroll for extra depth ----
    gsap.to('.about-hero-mesh', {
      yPercent: 12, ease: 'none',
      scrollTrigger: { trigger: '.about-hero', start: 'top top', end: 'bottom top', scrub: 0.6 },
    });

    // ---- How Grovia Works: line draws on scroll, each node lights up
    // as it's reached (draws forward AND retracts on scroll back up, via
    // onLeaveBack, so the animation reads correctly in both directions). ----
    const hwDiagram = document.querySelector('.hw-diagram');
    if (hwDiagram) {
      const hwFill = hwDiagram.querySelector('.hw-line-fill');
      if (hwFill) {
        gsap.to(hwFill, {
          scaleY: 1, ease: 'none',
          scrollTrigger: { trigger: hwDiagram, start: 'top 75%', end: 'bottom 65%', scrub: 0.4 },
        });
      }
      document.querySelectorAll('.hw-node').forEach((node) => {
        ScrollTrigger.create({
          trigger: node, start: 'top 72%',
          onEnter: () => node.classList.add('is-active'),
          onLeaveBack: () => node.classList.remove('is-active'),
        });
      });
    }

    // ---- From Data to Action: same line-draw + step activation, plus a
    // moisture gauge that visibly fills as the steps complete. ----
    const dtaSteps = document.querySelector('.dta-steps');
    if (dtaSteps) {
      const dtaFill = dtaSteps.querySelector('.dta-line-fill');
      if (dtaFill) {
        gsap.to(dtaFill, {
          scaleY: 1, ease: 'none',
          scrollTrigger: { trigger: dtaSteps, start: 'top 78%', end: 'bottom 60%', scrub: 0.4 },
        });
      }
      document.querySelectorAll('.dta-step').forEach((step) => {
        ScrollTrigger.create({
          trigger: step, start: 'top 78%',
          onEnter: () => step.classList.add('is-active'),
          onLeaveBack: () => step.classList.remove('is-active'),
        });
      });
      const gaugeFill = document.querySelector('.dta-gauge .fill');
      const gaugeVal = document.getElementById('dtaGaugeVal');
      if (gaugeFill) {
        const circumference = 163.4; // 2 * PI * r(26)
        ScrollTrigger.create({
          trigger: dtaSteps, start: 'top 78%', end: 'bottom 60%', scrub: 0.4,
          onUpdate: (self) => {
            const pct = self.progress;
            gaugeFill.style.strokeDashoffset = String(circumference * (1 - pct));
            gaugeFill.style.stroke = pct < 0.35 ? '#d97706' : 'var(--green-500)';
            if (gaugeVal) gaugeVal.textContent = Math.round(28 + pct * 44) + '%';
          },
        });
      }
    }

    // ---- Project Journey: horizontal pin on desktop (vertical scroll
    // drives horizontal translate); on tablet/mobile the CSS switches
    // .journey-track to a native horizontal scroll-snap strip instead,
    // so this rig is only created where it applies. ----
    const journeyPin = document.querySelector('.journey-pin');
    const journeyTrack = document.getElementById('journeyTrack');
    if (journeyPin && journeyTrack) {
      let journeyST = null;
      const setupJourney = () => {
        if (journeyST) { journeyST.kill(); journeyST = null; journeyTrack.style.transform = ''; }
        if (window.innerWidth < 900) return;
        const amount = Math.max(0, journeyTrack.scrollWidth - window.innerWidth);
        if (amount <= 0) return;
        journeyST = ScrollTrigger.create({
          trigger: journeyPin, start: 'top top', end: '+=' + amount, pin: true, scrub: 1,
          onUpdate: (self) => { journeyTrack.style.transform = `translateX(-${amount * self.progress}px)`; },
        });
      };
      setupJourney();
      let resizeTimer;
      window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(setupJourney, 200);
      });
    }

    // ---- People section: blueprint connector (RA — Grovia) lights up
    // once, in sequence, via staggered CSS transition-delays — a single
    // class toggle drives the whole draw-on, not a chain of JS timers.
    const peopleConnector = document.getElementById('peopleConnector');
    if (peopleConnector) {
      ScrollTrigger.create({
        trigger: peopleConnector, start: 'top 80%', once: true,
        onEnter: () => peopleConnector.classList.add('is-active'),
      });
    }

    // ---- Finale: the seed-to-wordmark grow illustration plays once,
    // staged entirely through CSS transition-delays (same one-toggle
    // pattern as the connector above) as the closing section comes into view.
    const growIllustration = document.getElementById('growIllustration');
    if (growIllustration) {
      ScrollTrigger.create({
        trigger: growIllustration, start: 'top 85%', once: true,
        onEnter: () => growIllustration.classList.add('is-active'),
      });
    }

    // ---- Footer: real-photo parallax drift + smooth back-to-top. Same
    // treatment as the landing page's footer, for one consistent closing
    // experience across both pages. ----
    const footerParallaxImg = document.getElementById('footerParallaxImg');
    if (footerParallaxImg) {
      gsap.to(footerParallaxImg, {
        yPercent: 10, ease: 'none',
        scrollTrigger: { trigger: '.footer-visual', start: 'top bottom', end: 'bottom top', scrub: 0.6 },
      });
    }
  });

  // Back-to-top works even in the reduced-motion/no-GSAP early-return path above.
  document.addEventListener('DOMContentLoaded', () => {
    const footerBackTop = document.getElementById('footerBackTop');
    if (footerBackTop) {
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      footerBackTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      });
    }
  });
</script>
<script type="module" src="js/greenhouse-hotspot-3d.js?v=2"></script>

</body>
</html>
