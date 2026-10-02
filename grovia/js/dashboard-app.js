document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  /* ---------- Mobile drawer ---------- */
  const drawer = document.getElementById('dashSidebarMobile');
  const backdrop = document.getElementById('dashBackdrop');
  const openBtn = document.getElementById('dashMenuBtn');
  const closeBtn = document.getElementById('dashDrawerClose');

  function openDrawer() {
    drawer.classList.add('open');
    backdrop.classList.add('open');
  }
  function closeDrawer() {
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
  }
  openBtn && openBtn.addEventListener('click', openDrawer);
  closeBtn && closeBtn.addEventListener('click', closeDrawer);
  backdrop && backdrop.addEventListener('click', closeDrawer);

  /* ---------- Sidebar nav active state ---------- */
  document.querySelectorAll('.nav-item').forEach((btn) => {
    btn.addEventListener('click', () => {
      const label = btn.querySelector('.sidebar-label')?.textContent?.trim();
      document.querySelectorAll('.nav-item').forEach((other) => {
        const otherLabel = other.querySelector('.sidebar-label')?.textContent?.trim();
        const shouldActivate = otherLabel === label;
        other.classList.toggle('is-active', shouldActivate);
        other.classList.toggle('text-white', shouldActivate);
        other.classList.toggle('text-text-secondary', !shouldActivate);

        const pill = other.querySelector('.nav-pill');
        const icon = other.querySelector('[data-lucide]');
        const dot = other.querySelectorAll('.sidebar-label')[1];

        if (shouldActivate) {
          if (!pill) {
            const span = document.createElement('span');
            span.className = 'nav-pill absolute inset-0 rounded-2xl bg-green-gradient shadow-glow';
            other.prepend(span);
          }
          icon && icon.classList.add('text-white');
          icon && icon.classList.remove('text-text-secondary', 'group-hover:text-primary');
        } else {
          pill && pill.remove();
          icon && icon.classList.remove('text-white');
          icon && icon.classList.add('text-text-secondary', 'group-hover:text-primary');
        }
      });
      closeDrawer();
    });
  });

  /* ---------- Circular progress rings ---------- */
  document.querySelectorAll('.ring-progress').forEach((circle) => {
    const pct = parseFloat(circle.getAttribute('data-pct') || '0');
    const circumference = parseFloat(circle.getAttribute('stroke-dasharray'));
    const offset = circumference * (1 - pct / 100);
    requestAnimationFrame(() => {
      setTimeout(() => {
        circle.style.strokeDashoffset = offset;
      }, 80);
    });
  });

  /* ---------- Chart.js ---------- */
  if (!window.Chart) return;

  function greenAreaGradient(ctx, chartArea) {
    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, 'rgba(34,197,94,0.35)');
    gradient.addColorStop(1, 'rgba(34,197,94,0)');
    return gradient;
  }

  const sparklineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 900 },
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
    scales: {
      x: { display: false },
      y: { display: false },
    },
    elements: {
      point: { radius: 0 },
    },
  };

  document.querySelectorAll('.climate-sparkline').forEach((canvas) => {
    const trend = JSON.parse(canvas.getAttribute('data-trend') || '[]');
    new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: trend.map((_, i) => i),
        datasets: [{
          data: trend,
          borderColor: '#22C55E',
          borderWidth: 2,
          fill: true,
          tension: 0.4,
          backgroundColor: (context) => {
            const { chart } = context;
            const { ctx, chartArea } = chart;
            if (!chartArea) return null;
            return greenAreaGradient(ctx, chartArea);
          },
        }],
      },
      options: sparklineOptions,
    });
  });

  const soilCanvas = document.getElementById('soilTrendChart');
  if (soilCanvas && window.GH_DATA) {
    new Chart(soilCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: window.GH_DATA.soilMoistureTrend.map((_, i) => i),
        datasets: [{
          data: window.GH_DATA.soilMoistureTrend,
          borderColor: '#22C55E',
          borderWidth: 2,
          fill: true,
          tension: 0.4,
          backgroundColor: (context) => {
            const { chart } = context;
            const { ctx, chartArea } = chart;
            if (!chartArea) return null;
            return greenAreaGradient(ctx, chartArea);
          },
        }],
      },
      options: sparklineOptions,
    });
  }

});
