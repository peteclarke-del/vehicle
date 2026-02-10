/* ============================================
   Vehicle Manager - Documentation JS
   Sidebar toggling, search, scrollspy
   ============================================ */

(function () {
  'use strict';

  // ─── Sidebar toggle (mobile) ───
  const toggle = document.querySelector('.sidebar-toggle');
  const sidebar = document.querySelector('.docs-sidebar');

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });

    // close sidebar when clicking a link (mobile)
    sidebar.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 900) sidebar.classList.remove('open');
      });
    });
  }

  // ─── Collapsible sidebar sections ───
  document.querySelectorAll('.sidebar-section-title').forEach(title => {
    title.addEventListener('click', () => {
      title.parentElement.classList.toggle('collapsed');
    });
  });

  // ─── Expandable endpoint blocks ───
  document.querySelectorAll('.endpoint-header').forEach(header => {
    header.addEventListener('click', () => {
      header.parentElement.classList.toggle('open');
    });
  });

  // ─── Expandable entity cards ───
  document.querySelectorAll('.entity-card-header').forEach(header => {
    header.addEventListener('click', () => {
      header.parentElement.classList.toggle('open');
    });
  });

  // ─── Back-to-top button ───
  const btt = document.querySelector('.back-to-top');
  if (btt) {
    window.addEventListener('scroll', () => {
      btt.classList.toggle('visible', window.scrollY > 400);
    }, { passive: true });
    btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  // ─── Scroll-spy ───
  const sidebarLinks = document.querySelectorAll('.sidebar-links a[href^="#"]');
  const sections = [];

  sidebarLinks.forEach(link => {
    const id = link.getAttribute('href').slice(1);
    const el = document.getElementById(id);
    if (el) sections.push({ id, el, link });
  });

  function updateScrollSpy() {
    let current = '';
    const scrollY = window.scrollY + 100;
    for (let i = sections.length - 1; i >= 0; i--) {
      if (scrollY >= sections[i].el.offsetTop) {
        current = sections[i].id;
        break;
      }
    }
    sidebarLinks.forEach(l => l.classList.remove('active'));
    if (current) {
      const active = document.querySelector(`.sidebar-links a[href="#${current}"]`);
      if (active) active.classList.add('active');
    }
  }

  window.addEventListener('scroll', updateScrollSpy, { passive: true });
  updateScrollSpy();

  // ─── Search ───
  const searchInput = document.querySelector('.docs-search input');
  const searchResults = document.querySelector('.search-results');

  // Build a simple search index from sections
  const searchIndex = [];
  document.querySelectorAll('.docs-content section[id]').forEach(sec => {
    const heading = sec.querySelector('h2, h3');
    if (heading) {
      searchIndex.push({
        id: sec.id,
        title: heading.textContent.trim(),
        text: sec.textContent.toLowerCase().slice(0, 500),
        section: sec.closest('[data-section-name]')?.dataset.sectionName || ''
      });
    }
  });

  if (searchInput && searchResults) {
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      if (q.length < 2) {
        searchResults.classList.remove('active');
        return;
      }
      const hits = searchIndex.filter(s =>
        s.title.toLowerCase().includes(q) || s.text.includes(q)
      ).slice(0, 10);

      if (hits.length === 0) {
        searchResults.innerHTML = '<div style="padding:12px 14px;color:var(--text-dim);font-size:13px;">No results found.</div>';
      } else {
        searchResults.innerHTML = hits.map(h => `
          <a class="search-result-item" href="#${h.id}">
            <div class="result-title">${h.title}</div>
            <div class="result-section">${h.section}</div>
          </a>
        `).join('');
      }
      searchResults.classList.add('active');
    });

    searchInput.addEventListener('blur', () => {
      setTimeout(() => searchResults.classList.remove('active'), 200);
    });

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        searchResults.classList.remove('active');
        searchInput.blur();
      }
    });

    // Global keyboard shortcut: Ctrl/Cmd+K to focus search
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchInput.focus();
      }
    });
  }

  // ─── Smooth reveal animation on load ───
  document.body.style.opacity = '0';
  document.body.style.transition = 'opacity 0.3s';
  requestAnimationFrame(() => { document.body.style.opacity = '1'; });

})();
