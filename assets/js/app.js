/* =====================================================================
   GreenAcres — application behaviour
   Vanilla JavaScript, no libraries, no network requests.
   ===================================================================== */
(function () {
  'use strict';

  /* ------------------------------------------------------------------
     Theme (light / dark) — applied before paint by an inline script in
     the page head, this part only handles the toggle.
     ------------------------------------------------------------------ */
  const Theme = {
    get() {
      return document.documentElement.getAttribute('data-theme') || 'light';
    },
    set(mode) {
      document.documentElement.setAttribute('data-theme', mode);
      try { localStorage.setItem('ga-theme', mode); } catch (e) {}
      document.querySelectorAll('[data-theme-icon]').forEach((el) => {
        el.classList.toggle('hide', el.dataset.themeIcon !== mode);
      });
    },
    toggle() {
      this.set(this.get() === 'dark' ? 'light' : 'dark');
    }
  };

  /* ------------------------------------------------------------------
     Sidebar
     ------------------------------------------------------------------ */
  const Sidebar = {
    init() {
      const app = document.querySelector('.app');
      if (!app) return;

      try {
        if (localStorage.getItem('ga-sidebar') === 'collapsed' && window.innerWidth > 860) {
          app.classList.add('is-collapsed');
        }
      } catch (e) {}

      document.querySelectorAll('[data-toggle-sidebar]').forEach((btn) => {
        btn.addEventListener('click', () => {
          if (window.innerWidth <= 860) {
            app.classList.toggle('is-mobile-open');
          } else {
            app.classList.toggle('is-collapsed');
            try {
              localStorage.setItem('ga-sidebar',
                app.classList.contains('is-collapsed') ? 'collapsed' : 'expanded');
            } catch (e) {}
          }
        });
      });

      const scrim = document.querySelector('.scrim');
      if (scrim) scrim.addEventListener('click', () => app.classList.remove('is-mobile-open'));
    }
  };

  /* ------------------------------------------------------------------
     Modals
     ------------------------------------------------------------------ */
  const Modal = {
    open(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      const focusable = el.querySelector('input:not([type=hidden]), select, textarea, button');
      if (focusable) setTimeout(() => focusable.focus(), 120);
    },
    close(el) {
      if (typeof el === 'string') el = document.getElementById(el);
      if (!el) return;
      el.classList.remove('is-open');
      if (!document.querySelector('.modal.is-open')) document.body.style.overflow = '';
    },
    closeAll() {
      document.querySelectorAll('.modal.is-open').forEach((m) => this.close(m));
    },
    init() {
      // Openers — data-modal="modalId", optional data-fill='{"field":"value"}'
      document.addEventListener('click', (ev) => {
        const opener = ev.target.closest('[data-modal]');
        if (opener) {
          ev.preventDefault();
          const id = opener.dataset.modal;
          const modal = document.getElementById(id);

          if (modal && opener.dataset.fill) {
            try {
              const data = JSON.parse(opener.dataset.fill);
              Object.keys(data).forEach((key) => {
                const input = modal.querySelector('[name="' + key + '"]');
                if (!input) return;
                if (input.type === 'checkbox') {
                  input.checked = !!data[key] && data[key] !== '0';
                } else {
                  input.value = data[key] === null ? '' : data[key];
                }
              });
            } catch (e) { console.warn('data-fill could not be parsed', e); }
          }

          // Text placeholders, e.g. <span data-fill-text="name">
          if (modal && opener.dataset.fillText) {
            try {
              const text = JSON.parse(opener.dataset.fillText);
              Object.keys(text).forEach((key) => {
                modal.querySelectorAll('[data-text="' + key + '"]').forEach((node) => {
                  node.textContent = text[key];
                });
              });
            } catch (e) {}
          }

          // Retarget a form action, used by the delete confirmations
          if (modal && opener.dataset.action) {
            const form = modal.querySelector('form');
            if (form) form.action = opener.dataset.action;
          }

          this.open(id);
          return;
        }

        // Closers
        if (ev.target.closest('[data-close-modal]')) {
          ev.preventDefault();
          this.close(ev.target.closest('.modal'));
          return;
        }

        // Click on the backdrop
        if (ev.target.classList.contains('modal')) this.close(ev.target);
      });

      document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') this.closeAll();
      });
    }
  };

  /* ------------------------------------------------------------------
     Dropdowns
     ------------------------------------------------------------------ */
  const Dropdown = {
    init() {
      document.addEventListener('click', (ev) => {
        const trigger = ev.target.closest('[data-dropdown]');
        const openMenus = document.querySelectorAll('.dropdown.is-open');

        if (trigger) {
          ev.preventDefault();
          const parent = trigger.closest('.dropdown');
          const wasOpen = parent.classList.contains('is-open');
          openMenus.forEach((m) => m.classList.remove('is-open'));
          if (!wasOpen) parent.classList.add('is-open');
          return;
        }

        if (!ev.target.closest('.dropdown__menu')) {
          openMenus.forEach((m) => m.classList.remove('is-open'));
        }
      });
    }
  };

  /* ------------------------------------------------------------------
     Toasts
     ------------------------------------------------------------------ */
  const ICONS = {
    success: '<path d="M12 3.2a8.8 8.8 0 1 0 0 17.6 8.8 8.8 0 0 0 0-17.6z"/><path d="m8 12.3 2.7 2.7 5.3-5.6"/>',
    danger:  '<circle cx="12" cy="12" r="8.8"/><path d="M12 7.6v5"/><path d="M12 16.2h.01"/>',
    warning: '<path d="M12 3.6 21 19.4H3z"/><path d="M12 9.5v4.2"/><path d="M12 17h.01"/>',
    info:    '<circle cx="12" cy="12" r="8.8"/><path d="M12 11.2v5"/><path d="M12 7.9h.01"/>'
  };

  const Toast = {
    show(type, title, message) {
      let stack = document.querySelector('.toasts');
      if (!stack) {
        stack = document.createElement('div');
        stack.className = 'toasts';
        document.body.appendChild(stack);
      }

      const tone = ICONS[type] ? type : 'info';
      const el = document.createElement('div');
      el.className = 'toast toast--' + tone;
      el.setAttribute('role', 'status');
      el.innerHTML =
        '<svg class="icon toast__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" ' +
        'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' +
        ICONS[tone] + '</svg>' +
        '<div class="toast__text"><div class="toast__title"></div>' +
        (message ? '<div class="toast__msg"></div>' : '') + '</div>' +
        '<button class="iconbtn" style="width:28px;height:28px" aria-label="Dismiss">' +
        '<svg class="icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round"><path d="M6 6 18 18M18 6 6 18"/></svg></button>';

      el.querySelector('.toast__title').textContent = title;
      if (message) el.querySelector('.toast__msg').textContent = message;

      const dismiss = () => {
        el.classList.add('is-hiding');
        setTimeout(() => el.remove(), 300);
      };
      el.querySelector('button').addEventListener('click', dismiss);
      stack.appendChild(el);
      setTimeout(dismiss, 5000);
    }
  };

  /* ------------------------------------------------------------------
     Button ripple — the tactile part of "interactive"
     ------------------------------------------------------------------ */
  function initRipple() {
    document.addEventListener('pointerdown', (ev) => {
      const btn = ev.target.closest('.btn');
      if (!btn) return;
      const rect = ev.currentTarget === btn ? btn.getBoundingClientRect() : btn.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = (ev.clientX - rect.left - size / 2) + 'px';
      ripple.style.top = (ev.clientY - rect.top - size / 2) + 'px';
      btn.appendChild(ripple);
      setTimeout(() => ripple.remove(), 600);
    });
  }

  /* ------------------------------------------------------------------
     Staggered reveal on load
     ------------------------------------------------------------------ */
  function initReveal() {
    const items = document.querySelectorAll('.reveal');
    if (!items.length) return;

    if (!('IntersectionObserver' in window)) {
      items.forEach((el) => el.classList.add('is-in'));
      return;
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry, i) => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const delay = parseInt(el.dataset.delay || (i * 55), 10);
        setTimeout(() => el.classList.add('is-in'), delay);
        observer.unobserve(el);
      });
    }, { threshold: .06, rootMargin: '0px 0px -30px 0px' });

    items.forEach((el) => observer.observe(el));
  }

  /* ------------------------------------------------------------------
     Count-up numbers on the dashboard
     ------------------------------------------------------------------ */
  function initCounters() {
    const nodes = document.querySelectorAll('[data-count]');
    if (!nodes.length) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    nodes.forEach((node) => {
      const target = parseFloat(node.dataset.count);
      if (isNaN(target)) return;

      const prefix   = node.dataset.prefix || '';
      const suffix   = node.dataset.suffix || '';
      const decimals = parseInt(node.dataset.decimals || 0, 10);

      const render = (value) => {
        node.textContent = prefix + value.toLocaleString(undefined, {
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals
        }) + suffix;
      };

      if (reduced) { render(target); return; }

      const duration = 1000;
      const start = performance.now();

      const step = (now) => {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);   // ease-out cubic
        render(target * eased);
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }

  /* ------------------------------------------------------------------
     Animated progress bars
     ------------------------------------------------------------------ */
  function initProgress() {
    document.querySelectorAll('.progress__fill[data-value]').forEach((bar, i) => {
      const value = Math.max(0, Math.min(100, parseFloat(bar.dataset.value) || 0));
      setTimeout(() => { bar.style.width = value + '%'; }, 180 + i * 70);
    });
  }

  /* ------------------------------------------------------------------
     Client side table filtering (instant, no page reload)
     ------------------------------------------------------------------ */
  function initTableFilter() {
    document.querySelectorAll('[data-filter-table]').forEach((input) => {
      const table = document.querySelector(input.dataset.filterTable);
      if (!table) return;

      input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        let shown = 0;

        table.querySelectorAll('tbody tr').forEach((row) => {
          if (row.dataset.emptyRow !== undefined) return;
          const match = row.textContent.toLowerCase().includes(term);
          row.style.display = match ? '' : 'none';
          if (match) shown++;
        });

        const note = document.querySelector(input.dataset.filterEmpty || '#filterEmpty');
        if (note) note.classList.toggle('hide', shown !== 0);
      });
    });
  }

  /* ------------------------------------------------------------------
     Form guards: double submit protection + required field marking
     ------------------------------------------------------------------ */
  function initForms() {
    document.querySelectorAll('form[data-guard]').forEach((form) => {
      form.addEventListener('submit', (ev) => {
        let firstInvalid = null;

        form.querySelectorAll('[required]').forEach((input) => {
          const empty = !String(input.value).trim();
          input.classList.toggle('is-invalid', empty);
          if (empty && !firstInvalid) firstInvalid = input;
        });

        if (firstInvalid) {
          ev.preventDefault();
          firstInvalid.focus();
          firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
          Toast.show('warning', 'Missing information', 'Please fill in the highlighted fields.');
          return;
        }

        const submit = form.querySelector('[type=submit]');
        if (submit) {
          submit.disabled = true;
          submit.dataset.originalHtml = submit.innerHTML;
          submit.innerHTML =
            '<svg class="icon spin" width="17" height="17" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
            '<path d="M12 3.5a8.5 8.5 0 1 1-8.5 8.5"/></svg> Working…';

          // Re-enable if the browser restores the page from cache
          setTimeout(() => {
            if (submit.dataset.originalHtml) {
              submit.disabled = false;
              submit.innerHTML = submit.dataset.originalHtml;
            }
          }, 9000);
        }
      });

      form.querySelectorAll('.is-invalid').forEach((input) => {
        input.addEventListener('input', () => input.classList.remove('is-invalid'));
      });
    });
  }

  /* ------------------------------------------------------------------
     Auto submit filter selects
     ------------------------------------------------------------------ */
  function initAutoSubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
      el.addEventListener('change', () => el.form && el.form.submit());
    });
  }

  /* ------------------------------------------------------------------
     Password visibility toggle
     ------------------------------------------------------------------ */
  function initPasswordToggles() {
    document.querySelectorAll('[data-pwd-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.pwdToggle);
        if (!input) return;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.querySelectorAll('svg').forEach((svg, i) => svg.classList.toggle('hide', showing ? i === 1 : i === 0));
      });
    });
  }

  /* ------------------------------------------------------------------
     Demo credential shortcut on the login screen
     ------------------------------------------------------------------ */
  function initDemoFill() {
    document.querySelectorAll('[data-demo]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const [user, pass] = btn.dataset.demo.split('|');
        const u = document.getElementById('identifier');
        const p = document.getElementById('password');
        if (u) u.value = user;
        if (p) p.value = pass;
        Toast.show('info', 'Credentials filled', 'Press "Sign in" to continue.');
        if (u) u.focus();
      });
    });
  }

  /* ------------------------------------------------------------------
     Live clock in the topbar
     ------------------------------------------------------------------ */
  function initClock() {
    const el = document.querySelector('[data-clock]');
    if (!el) return;
    const tick = () => {
      const now = new Date();
      el.textContent = now.toLocaleDateString(undefined, {
        weekday: 'short', day: 'numeric', month: 'short'
      }) + ' · ' + now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    };
    tick();
    setInterval(tick, 30000);
  }

  /* ------------------------------------------------------------------
     Keyboard shortcuts
     ------------------------------------------------------------------ */
  function initShortcuts() {
    document.addEventListener('keydown', (ev) => {
      const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);

      // "/" focuses search
      if (ev.key === '/' && !typing) {
        const search = document.querySelector('.searchbox input, [data-filter-table]');
        if (search) { ev.preventDefault(); search.focus(); }
      }
      // Ctrl/Cmd + K also focuses search
      if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') {
        const search = document.querySelector('.searchbox input, [data-filter-table]');
        if (search) { ev.preventDefault(); search.focus(); search.select(); }
      }
      // "n" opens the primary create button on a page
      if (ev.key === 'n' && !typing && !ev.ctrlKey && !ev.metaKey) {
        const create = document.querySelector('[data-primary-action]');
        if (create) { ev.preventDefault(); create.click(); }
      }
    });
  }

  /* ------------------------------------------------------------------
     Print
     ------------------------------------------------------------------ */
  function initPrint() {
    document.querySelectorAll('[data-print]').forEach((btn) => {
      btn.addEventListener('click', () => window.print());
    });
  }

  /* ------------------------------------------------------------------
     Boot
     ------------------------------------------------------------------ */
  function boot() {
    Sidebar.init();
    Modal.init();
    Dropdown.init();
    initRipple();
    initReveal();
    initCounters();
    initProgress();
    initTableFilter();
    initForms();
    initAutoSubmit();
    initPasswordToggles();
    initDemoFill();
    initClock();
    initShortcuts();
    initPrint();

    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => Theme.toggle());
    });
    Theme.set(Theme.get());

    // Render any flash messages handed over by PHP
    if (window.__flashes) {
      window.__flashes.forEach((f, i) => {
        setTimeout(() => Toast.show(f.type, f.title, f.message), 220 + i * 160);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Expose a small public surface for inline page scripts
  window.GA = { Toast, Modal, Theme };
})();
