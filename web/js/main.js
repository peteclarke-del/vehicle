/* ================================================================
   VEHICLE MANAGER — MAIN JS
   ================================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // ─── Navbar scroll effect ──────────────────────────────────
  const navbar = document.querySelector('.navbar');
  const handleScroll = () => {
    navbar?.classList.toggle('scrolled', window.scrollY > 50);
  };
  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();

  // ─── Mobile nav toggle ─────────────────────────────────────
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      navLinks.classList.toggle('active');
    });
    // Close on link click
    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('active');
      });
    });
  }

  // ─── Scroll animations (IntersectionObserver) ──────────────
  const animatedEls = document.querySelectorAll('.fade-up, .stagger');
  if (animatedEls.length > 0) {
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
    animatedEls.forEach(el => observer.observe(el));
  }

  // ─── Animated counters ─────────────────────────────────────
  const counters = document.querySelectorAll('[data-count]');
  if (counters.length > 0) {
    const counterObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.5 }
    );
    counters.forEach(el => counterObserver.observe(el));
  }

  function animateCounter(el) {
    const target = parseInt(el.dataset.count, 10);
    const suffix = el.dataset.suffix || '';
    const duration = 2000;
    const start = performance.now();

    function update(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      // Ease out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(eased * target);
      el.textContent = current + suffix;
      if (progress < 1) {
        requestAnimationFrame(update);
      }
    }
    requestAnimationFrame(update);
  }

  // ─── Floating particles ────────────────────────────────────
  const particleContainer = document.querySelector('.hero-particles');
  if (particleContainer) {
    const count = Math.min(20, Math.floor(window.innerWidth / 80));
    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      p.style.left = Math.random() * 100 + '%';
      p.style.animationDelay = Math.random() * 8 + 's';
      p.style.animationDuration = (6 + Math.random() * 8) + 's';
      p.style.width = p.style.height = (2 + Math.random() * 3) + 'px';
      particleContainer.appendChild(p);
    }
  }

  // ─── Smooth scroll for anchor links ────────────────────────
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const id = anchor.getAttribute('href');
      if (id && id !== '#') {
        const target = document.querySelector(id);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth' });
        }
      }
    });
  });

  // ─── Copy code on click (CTA block) ───────────────────────
  const ctaCode = document.querySelector('.cta-code pre');
  if (ctaCode) {
    ctaCode.style.cursor = 'pointer';
    ctaCode.title = 'Click to copy';
    ctaCode.addEventListener('click', () => {
      const text = ctaCode.textContent.replace(/\$ /g, '').trim();
      navigator.clipboard.writeText(text).then(() => {
        const original = ctaCode.title;
        ctaCode.title = 'Copied!';
        setTimeout(() => { ctaCode.title = original; }, 2000);
      }).catch(() => {});
    });
  }
});
