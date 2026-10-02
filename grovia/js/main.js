/* =========================================================
   GreenHouse — Premium Smart Greenhouse
   Cinematic scrollytelling powered by GSAP + ScrollTrigger,
   smooth-scrolled by Lenis. Degrades to a plain stacked
   layout if any library fails to load or the visitor prefers
   reduced motion.
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hasGSAP = typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined';

  if (hasGSAP) gsap.registerPlugin(ScrollTrigger);

  /* ---------- Smooth scroll (Lenis) ---------- */
  let lenis = null;
  if (!reduceMotion && hasGSAP && typeof window.Lenis !== 'undefined') {
    lenis = new Lenis({ duration: 1.05, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
  }

  /* ---------- Mobile nav ---------- */
  const burger = document.getElementById('burger');
  const navLinks = document.getElementById('navLinks');
  if (burger && navLinks) {
    burger.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      burger.classList.toggle('active');
    });
    navLinks.querySelectorAll('a').forEach((a) =>
      a.addEventListener('click', () => navLinks.classList.remove('open'))
    );
  }

  /* ---------- Nav solid-on-scroll ---------- */
  const nav = document.getElementById('siteNav');
  function updateNavSolid() {
    if (!nav || nav.dataset.navStatic) return;
    if (window.scrollY > 40) nav.classList.add('solid');
    else nav.classList.remove('solid');
  }
  window.addEventListener('scroll', updateNavSolid, { passive: true });
  updateNavSolid();

  /* ---------- Nav active-link highlighting ---------- */
  const navAnchors = Array.from(document.querySelectorAll('.nav-links a[data-nav]'));
  const navSections = navAnchors
    .map((a) => document.getElementById(a.dataset.nav))
    .filter(Boolean);
  if (navSections.length) {
    const sectionObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          navAnchors.forEach((a) => a.classList.toggle('active', a.dataset.nav === entry.target.id));
        });
      },
      { rootMargin: '-45% 0px -45% 0px' }
    );
    navSections.forEach((s) => sectionObserver.observe(s));
  }

  /* ---------- Hero entrance + fallback video ---------- */
  const heroVideo = document.getElementById('heroVideo');
  const heroFallback = document.getElementById('heroFallback');
  function spawnLeafParticles(count) {
    if (!heroFallback) return;
    for (let i = 0; i < count; i++) {
      const leaf = document.createElement('div');
      leaf.className = 'illu-particle';
      leaf.style.width = leaf.style.height = 6 + Math.random() * 14 + 'px';
      leaf.style.left = Math.random() * 100 + '%';
      leaf.style.top = Math.random() * 100 + '%';
      leaf.style.animationDuration = 5 + Math.random() * 6 + 's';
      leaf.style.animationDelay = Math.random() * 5 + 's';
      heroFallback.appendChild(leaf);
    }
  }
  function showHeroFallback() {
    if (!heroVideo || !heroFallback) return;
    heroVideo.style.display = 'none';
    heroFallback.style.display = 'block';
    spawnLeafParticles(20);
  }
  if (heroVideo) {
    heroVideo.addEventListener('error', showHeroFallback);
    setTimeout(() => { if (heroVideo.readyState === 0) showHeroFallback(); }, 2500);
  }

  const heroTitle = document.querySelector('.hero h1');
  const heroTag = document.querySelector('.hero-tag');
  const heroP = document.querySelector('.hero p');
  const heroBtns = document.querySelector('.hero-btns');
  const heroMeta = document.querySelector('.hero-meta');

  if (hasGSAP && !reduceMotion) {
    gsap.set([heroTag, heroTitle, heroP, heroBtns, heroMeta], { opacity: 0, y: 26 });
    gsap.timeline({ defaults: { ease: 'power3.out' } })
      .to(heroTag, { opacity: 1, y: 0, duration: 0.7 }, 0.1)
      .to(heroTitle, { opacity: 1, y: 0, duration: 0.9 }, 0.28)
      .to(heroP, { opacity: 1, y: 0, duration: 0.9 }, 0.45)
      .fromTo(heroBtns, { opacity: 0, scale: 0.9 }, { opacity: 1, scale: 1, duration: 0.7 }, 0.62)
      .to(heroMeta, { opacity: 1, y: 0, duration: 0.7 }, 0.8);

    // "Walking into the greenhouse": as the user scrolls through the hero,
    // the video pushes forward gently (zoom) while the intro copy falls
    // away and a branded green wipe rises from the bottom, revealing the
    // Features section underneath — no darkening-to-black, just a clean
    // hand-off into the page's own color.
    gsap.timeline({
      scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: 0.4 },
    })
      .to('.hero-media, .hero-fallback', { scale: 1.18, ease: 'none' }, 0)
      .to('.hero-content', { y: -90, opacity: 0, scale: 0.92, ease: 'none' }, 0)
      .to('#heroReveal', { height: '112%', ease: 'none' }, 0.15);
  } else {
    [heroTag, heroTitle, heroP, heroBtns, heroMeta].forEach((el) => { if (el) el.style.opacity = 1; });
  }

  /* ---------- Why Choose: panel entrance.
     As the section enters the viewport: the panel fades in, moves up
     60px and scales from 0.95 to 1 over 1.2s; the heading's words each
     animate in individually (blurred + invisible + shifted down, then
     sharp + visible + settled, staggered word by word); the paragraph
     fades in right after; each of the three feature columns slides up
     40px and fades in, 0.2s apart; and the button/tagline appear last
     of all. Hover states (icon scale/rotate/glow, title color, column
     lift) are pure CSS and need no JS. ---------- */
  (function () {
    const panel = document.getElementById('whyPanel');
    if (!panel) return;
    const words = Array.from(panel.querySelectorAll('.why-word'));
    const desc = panel.querySelector('.why-head p');
    const items = Array.from(panel.querySelectorAll('.why-item'));
    const footer = panel.querySelector('.why-footer');
    const ctaCorner = panel.querySelector('.why-cta-corner');

    if (!hasGSAP || reduceMotion) {
      words.forEach((el) => { el.style.opacity = 1; el.style.filter = 'none'; });
      if (desc) desc.style.opacity = 1;
      items.forEach((el) => { el.style.opacity = 1; });
      if (footer) footer.style.opacity = 1;
      if (ctaCorner) ctaCorner.style.opacity = 1;
      return;
    }

    gsap.set(panel, { opacity: 0, y: 60, scale: 0.95 });
    gsap.set(words, { opacity: 0, y: 34, filter: 'blur(8px)' });
    gsap.set(desc, { opacity: 0, y: 28 });
    gsap.set(items, { opacity: 0, y: 55 });
    gsap.set(footer, { opacity: 0, y: 30 });
    gsap.set(ctaCorner, { opacity: 0, y: 30, scale: 0.9 });

    gsap.timeline({ scrollTrigger: { trigger: panel, start: 'top 85%' } })
      .to(panel, { opacity: 1, y: 0, scale: 1, duration: 0.8, ease: 'power2.inOut' })
      .to(words, { opacity: 1, y: 0, filter: 'blur(0px)', duration: 0.45, stagger: 0.08, ease: 'power2.out' }, 0.22)
      .to(desc, { opacity: 1, y: 0, duration: 0.4, ease: 'power2.out' }, '>-0.15')
      .to(items, { opacity: 1, y: 0, duration: 0.55, stagger: 0.13, ease: 'power2.out' }, '>-0.1')
      .to(footer, { opacity: 1, y: 0, duration: 0.4, ease: 'power2.out' }, '>-0.1')
      .to(ctaCorner, { opacity: 1, y: 0, scale: 1, duration: 0.4, ease: 'back.out(1.7)' }, '>-0.2');
  })();

  /* ---------- Story: animated stat counters ---------- */
  function animateStats(card) {
    card.querySelectorAll('[data-count-to]').forEach((el) => {
      const target = parseFloat(el.dataset.countTo);
      const suffix = el.dataset.suffix || '';
      if (!hasGSAP || reduceMotion) { el.textContent = target + suffix; return; }
      const counter = { val: 0 };
      gsap.to(counter, {
        val: target, duration: 1.1, ease: 'power2.out',
        onUpdate() { el.textContent = (Number.isInteger(target) ? Math.round(counter.val) : counter.val.toFixed(1)) + suffix; },
      });
    });
  }

  /* ---------- Story: pinned "book slider".
     On desktop the section pins in place — the page itself never moves
     — while each card crossfades to the next as the user scrolls. This
     is a single, self-contained pin: nothing else on the page is
     pinned, and nothing is nested inside it, which is the one
     combination that's been reliable all along (the earlier version's
     bugs all came from pins nested inside other pins, each fighting
     over refreshPriority and spacer sizing — not from pinning by
     itself). Falls back to a plain stacked scroll-reveal on touch
     screens, where a full-viewport pin is a poor fit. ---------- */
  const storySection = document.getElementById('story');
  const storyPin = document.querySelector('.story-pin');
  const featureCards = Array.from(document.querySelectorAll('.feature-card'));
  const dots = Array.from(document.querySelectorAll('.story-dot'));

  function setActiveCard(index) {
    featureCards.forEach((c, i) => c.classList.toggle('is-active', i === index));
    dots.forEach((d, i) => d.classList.toggle('is-active', i === index));
    if (featureCards[index]) animateStats(featureCards[index]);
  }
  setActiveCard(0);

  if (storySection && storyPin && hasGSAP && featureCards.length) {
    ScrollTrigger.matchMedia({
      '(min-width: 900px)': function () {
        document.body.classList.add('is-pinned-story');
        const current = { idx: -1 };

        // Distance-from-active drives opacity continuously (never a
        // discrete class-swap) — progress maps onto raw across [0, n-1]
        // (card indices, not slot count) so progress 0 lands exactly on
        // card 0's own peak and progress 1 lands exactly on the last
        // card's peak. Mapping onto [0, n) instead would leave both
        // ends mid-fade: card 0 starting from blank, and the last card
        // fading back out with nothing left to crossfade into.
        //
        // FADE_END caps how far the blend extends (previously it ran
        // all the way to the neighbor's own peak, absT===1, which meant
        // two adjacent scenes sat at ~70% opacity each for a wide
        // stretch of scroll — invisible back when every card had its
        // own opaque frosted-glass panel to hide what was behind it,
        // but once that chrome was removed for the cinematic redesign,
        // that wide blend became two scenes' text visibly overlapping.
        // Keeping DWELL+FADE_END symmetric at 1 still guarantees a
        // gapless handoff (something is always fully opaque right up
        // to the midpoint), just over a much narrower window.
        const DWELL = 0.4;
        const FADE_END = 0.6;
        function update(progress) {
          const n = featureCards.length;
          const raw = Math.min(n - 1, Math.max(0, progress * (n - 1)));
          const idx = Math.min(n - 1, Math.round(raw));

          featureCards.forEach((card, i) => {
            const absT = Math.abs(i - raw);
            const k = absT <= DWELL ? 0 : Math.min(1, (absT - DWELL) / (FADE_END - DWELL));
            const opacity = 1 - k;
            gsap.set(card, { opacity, scale: 0.98 + 0.02 * opacity, zIndex: i === idx ? 2 : 1 });
          });

          if (idx !== current.idx) {
            current.idx = idx;
            featureCards.forEach((c, i) => c.classList.toggle('is-active', i === idx));
            dots.forEach((d, i) => d.classList.toggle('is-active', i === idx));
            animateStats(featureCards[idx]);
          }
        }
        update(0);

        const st = ScrollTrigger.create({
          trigger: storySection,
          start: 'top top',
          end: () => '+=' + featureCards.length * window.innerHeight * 0.8,
          pin: storyPin,
          scrub: 0.25,
          anticipatePin: 1,
          onUpdate(self) { update(self.progress); },
        });
        return () => { document.body.classList.remove('is-pinned-story'); st.kill(); };
      },
      '(max-width: 899px)': function () {
        featureCards.forEach((c) => c.classList.add('is-active'));
        featureCards.forEach((card) => {
          if (reduceMotion) { animateStats(card); return; }
          gsap.from(card, {
            opacity: 0, y: 40, duration: 0.8, ease: 'power2.out',
            scrollTrigger: { trigger: card, start: 'top 82%' },
            onStart: () => animateStats(card),
          });
        });
      },
    });
  } else {
    featureCards.forEach((c) => animateStats(c));
    featureCards.forEach((c) => c.classList.add('is-active'));
  }

  /* ---------- Predict: crop selector.
     Real reference numbers for these four crops, matching the same
     VEGGIES entries the Greenhouse Planner itself uses (spacingLabel /
     growthLabel / best-season / spacingCm — see js/greenhouse-planner.js)
     rather than inventing per-crop stats. Density is the only derived
     value (simple area math off the real spacing), everything else is
     copied straight from that reference. Only crops that actually exist
     there are offered here. ---------- */
  (function () {
    const cropButtons = document.querySelectorAll('.predict-crop');
    if (!cropButtons.length) return;

    const CROPS = {
      tomato: { season: 'Summer, Spring', spacing: '45 × 60 cm', growth: '70–90 days', density: '~4 / m²' },
      cucumber: { season: 'Summer', spacing: '60 × 60 cm', growth: '50–70 days', density: '~3 / m²' },
      lettuce: { season: 'Autumn, Winter, Spring', spacing: '30 × 30 cm', growth: '30–45 days', density: '~11 / m²' },
      strawberry: { season: 'Spring, Autumn', spacing: '30 × 40 cm', growth: '60–90 days', density: '~8 / m²' },
    };

    const seasonEl = document.getElementById('predictBestSeason');
    const spacingEl = document.getElementById('predictSpacing');
    const growthEl = document.getElementById('predictGrowth');
    const densityEl = document.getElementById('predictDensity');
    const infoEls = [seasonEl, spacingEl, growthEl, densityEl].filter(Boolean);

    function applyCrop(key) {
      const c = CROPS[key];
      if (!c) return;
      if (seasonEl) seasonEl.textContent = c.season;
      if (spacingEl) spacingEl.textContent = c.spacing;
      if (growthEl) growthEl.textContent = c.growth;
      if (densityEl) densityEl.textContent = c.density;
    }

    cropButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.crop;
        if (!CROPS[key] || btn.classList.contains('is-active')) return;
        cropButtons.forEach((b) => { b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
        btn.classList.add('is-active');
        btn.setAttribute('aria-selected', 'true');

        if (hasGSAP && !reduceMotion) {
          gsap.timeline()
            .to(infoEls, { opacity: 0, y: 6, duration: 0.2, ease: 'power1.in' })
            .call(() => applyCrop(key))
            .to(infoEls, { opacity: 1, y: 0, duration: 0.3, ease: 'power2.out' });
        } else {
          applyCrop(key);
        }
      });
    });
  })();

  /* ---------- Predict: AI growth reveal (accumulating, canvas-based).
     The bed starts completely empty. As the cursor explores it, the AI
     "paints" growth into the soil: each pass stamps a softly-feathered
     patch that grows in over ~0.6s — starting as a small, faint sprout
     and opening up to a full, healthy patch — and then PERSISTS, so the
     garden fills in more and more as different areas are explored rather
     than resetting when the cursor leaves. ---------- */
  (function () {
    const reveal = document.getElementById('predictReveal');
    if (!reveal) return;
    const canvas = reveal.querySelector('.predict-reveal-canvas');
    const sourceImg = reveal.querySelector('.predict-reveal-source');
    const ring = reveal.querySelector('.predict-reveal-ring');
    if (!canvas || !sourceImg || !ring) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const RADIUS = 85;
    const GROW_MS = reduceMotion ? 1 : 650;
    const MAX_POINTS = 260;
    const MIN_STAMP_DIST = 10;

    const points = [];
    let w = 0, h = 0;
    let sourceReady = false;
    let rafRunning = false;
    let lastX = null, lastY = null;

    function sizeCanvas() {
      const rect = reveal.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = rect.width;
      h = rect.height;
      canvas.width = Math.round(w * dpr);
      canvas.height = Math.round(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      render();
    }
    sizeCanvas();
    window.addEventListener('resize', sizeCanvas);

    function onSourceReady() {
      sourceReady = true;
      render();
    }
    if (sourceImg.complete && sourceImg.naturalWidth) onSourceReady();
    else sourceImg.addEventListener('load', onSourceReady, { once: true });

    function render() {
      if (!sourceReady || !points.length) { ctx.clearRect(0, 0, w, h); return; }
      ctx.clearRect(0, 0, w, h);
      ctx.save();
      const now = performance.now();
      let stillGrowing = false;
      points.forEach((p) => {
        const t = Math.min(1, (now - p.t0) / GROW_MS);
        if (t < 1) stillGrowing = true;
        const eased = 1 - Math.pow(1 - t, 3);
        const r = RADIUS * eased;
        if (r <= 0) return;
        const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, r);
        grad.addColorStop(0, `rgba(0,0,0,${eased})`);
        grad.addColorStop(0.65, `rgba(0,0,0,${eased})`);
        grad.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
        ctx.fill();
      });
      ctx.globalCompositeOperation = 'source-in';
      ctx.drawImage(sourceImg, 0, 0, w, h);
      ctx.restore();

      if (stillGrowing) requestAnimationFrame(render);
      else rafRunning = false;
    }
    function ensureRendering() {
      if (rafRunning) return;
      rafRunning = true;
      requestAnimationFrame(render);
    }

    function addPoint(x, y) {
      points.push({ x, y, t0: performance.now() });
      if (points.length > MAX_POINTS) points.shift();
      ensureRendering();
    }
    function maybeStampAt(x, y) {
      if (lastX === null || Math.hypot(x - lastX, y - lastY) >= MIN_STAMP_DIST) {
        addPoint(x, y);
        lastX = x; lastY = y;
      }
    }

    reveal.addEventListener('pointerenter', () => reveal.classList.add('is-active'));
    reveal.addEventListener('pointermove', (e) => {
      const rect = reveal.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      ring.style.left = x + 'px';
      ring.style.top = y + 'px';
      maybeStampAt(x, y);
    });
    reveal.addEventListener('pointerleave', () => reveal.classList.remove('is-active'));
  })();


  /* ---------- Login gate: gated CTAs (feature cards, Predict) don't just
     jump straight to login.php — they pause on a brief, creative "login
     required" beat first, then route through. Marked via data-gate="login"
     on the anchor so nav/footer login links are unaffected. ---------- */
  (function () {
    const gate = document.getElementById('loginGate');
    const gatedLinks = document.querySelectorAll('[data-gate="login"]');
    if (!gate || !gatedLinks.length) return;

    const card = gate.querySelector('.login-gate-card');
    const icon = gate.querySelector('.login-gate-icon');
    const fill = gate.querySelector('.login-gate-bar-fill');
    let navigating = false;

    function openGate(href) {
      if (navigating) return;
      navigating = true;
      gate.classList.add('is-visible');
      gate.setAttribute('aria-hidden', 'false');

      if (!hasGSAP || reduceMotion) {
        window.setTimeout(() => { window.location.href = href; }, 500);
        return;
      }

      gsap.set(fill, { width: '0%' });
      gsap.timeline({ onComplete: () => { window.location.href = href; } })
        .fromTo(card, { opacity: 0, scale: 0.82, y: 24 }, { opacity: 1, scale: 1, y: 0, duration: 0.45, ease: 'back.out(1.8)' })
        .fromTo(icon, { scale: 0, rotate: -25 }, { scale: 1, rotate: 0, duration: 0.4, ease: 'back.out(2.6)' }, '-=0.25')
        .to(fill, { width: '100%', duration: 1.1, ease: 'power1.inOut' }, '-=0.05');
    }

    gatedLinks.forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        openGate(link.getAttribute('href'));
      });
    });

    // If the user hits the browser back button after being routed to
    // login.php, the browser can restore this page from bfcache exactly as
    // it was mid-redirect — gate still open, page still "navigating". Reset
    // it on pageshow so browsing continues without needing a manual refresh.
    window.addEventListener('pageshow', () => {
      navigating = false;
      gate.classList.remove('is-visible');
      gate.setAttribute('aria-hidden', 'true');
      if (hasGSAP) {
        gsap.killTweensOf([card, icon, fill]);
        gsap.set(fill, { width: '0%' });
      }
    });
  })();

  /* ---------- Generic reveal for footer / simple blocks ---------- */
  document.querySelectorAll('[data-reveal]').forEach((el) => {
    if (hasGSAP && !reduceMotion) {
      gsap.from(el, {
        opacity: 0, y: 30, duration: 0.7, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 88%' },
      });
    } else {
      el.style.opacity = 1;
    }
  });

  /* ---------- Footer: real-photo parallax drift + smooth back-to-top.
     The image is pre-scaled 1.1x in CSS so this small translate never
     reveals an edge. Skipped entirely under reduced motion (CSS also
     zeroes the transform there as a belt-and-braces fallback). ---------- */
  const footerParallaxImg = document.getElementById('footerParallaxImg');
  if (footerParallaxImg && hasGSAP && !reduceMotion) {
    gsap.to(footerParallaxImg, {
      yPercent: 10, ease: 'none',
      scrollTrigger: { trigger: '.footer-visual', start: 'top bottom', end: 'bottom top', scrub: 0.6 },
    });
  }
  const footerBackTop = document.getElementById('footerBackTop');
  if (footerBackTop) {
    footerBackTop.addEventListener('click', () => {
      // Lenis owns scroll position while active — a plain window.scrollTo()
      // gets overridden on its next raf tick since Lenis's own internal
      // target never learns about it, so route through Lenis here instead.
      if (lenis) lenis.scrollTo(0, { duration: reduceMotion ? 0 : 1.1 });
      else window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }

  /* ---------- Re-measure every pin/trigger once everything (images,
     video, fonts) has actually finished loading. The page ships several
     multi-megabyte photos with no reserved width/height — while any of
     them are still downloading, ScrollTrigger's initial measurements can
     go stale the moment one finishes and nudges layout height, which
     desyncs every pin positioned after it (this is what made Story's
     card-stack look like it had stopped sliding: its trigger start/end
     no longer lined up with where the section actually sat on the
     page). One refresh against the final, fully-loaded layout fixes
     the whole chain at once. ---------- */
  if (hasGSAP) {
    window.addEventListener('load', () => ScrollTrigger.refresh());
  }
});
