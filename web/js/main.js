/* ============================================
   Vehicle Manager - Promo Site JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {
  initNavbar();
  initScrollAnimations();
  initCounters();
  initParticles();
  initDemoLinks();
});

/* ===================== NAVBAR ===================== */
function initNavbar() {
  const navbar = document.getElementById('navbar');
  const toggle = document.getElementById('navToggle');
  const links = document.getElementById('navLinks');

  // Scroll effect
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(() => {
        navbar.classList.toggle('scrolled', window.scrollY > 50);
        ticking = false;
      });
      ticking = true;
    }
  });

  // Mobile toggle
  toggle.addEventListener('click', () => {
    links.classList.toggle('active');
    toggle.classList.toggle('active');
  });

  // Close menu on link click
  links.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      links.classList.remove('active');
      toggle.classList.remove('active');
    });
  });
}

/* ===================== SCROLL ANIMATIONS ===================== */
function initScrollAnimations() {
  const elements = document.querySelectorAll('[data-animate]');

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    elements.forEach(el => observer.observe(el));
  } else {
    // Fallback: show all
    elements.forEach(el => el.classList.add('visible'));
  }
}

/* ===================== ANIMATED COUNTERS ===================== */
function initCounters() {
  const counters = document.querySelectorAll('[data-count]');

  if (!counters.length) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );

  counters.forEach(el => observer.observe(el));
}

function animateCounter(el) {
  const target = parseInt(el.getAttribute('data-count'), 10);
  const suffix = el.getAttribute('data-suffix') || '';
  const duration = 2000;
  const start = performance.now();

  function update(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);

    // Ease-out cubic
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.round(eased * target);

    el.textContent = current + suffix;

    if (progress < 1) {
      requestAnimationFrame(update);
    }
  }

  requestAnimationFrame(update);
}

/* ===================== PARTICLES ===================== */
function initParticles() {
  const container = document.getElementById('particles');
  if (!container) return;

  const count = 20;
  const colors = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#f59e0b'];

  for (let i = 0; i < count; i++) {
    const particle = document.createElement('div');
    particle.classList.add('particle');
    particle.style.left = Math.random() * 100 + '%';
    particle.style.animationDelay = Math.random() * 8 + 's';
    particle.style.animationDuration = (6 + Math.random() * 6) + 's';
    particle.style.background = colors[Math.floor(Math.random() * colors.length)];
    particle.style.width = (2 + Math.random() * 4) + 'px';
    particle.style.height = particle.style.width;
    container.appendChild(particle);
  }
}

/* ===================== SMOOTH ANCHOR SCROLLING ===================== */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const id = this.getAttribute('href');
    if (id === '#') return;
    
    const target = document.querySelector(id);
    if (target) {
      e.preventDefault();
      const offset = 80; // navbar height
      const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  });
});

/* ===================== DEMO LINKS ===================== */
function initDemoLinks() {
  const cfg = window.SITE_CONFIG || {};
  const url = (cfg.demoUrl || '').trim();

  if (!url) return; // No demo URL configured – buttons stay hidden

  document.querySelectorAll('#navDemoLink, #heroDemoLink').forEach(el => {
    el.href = url;
    el.style.display = '';
  });
}
