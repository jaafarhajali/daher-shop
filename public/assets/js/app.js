/**
 * Daher Store — shared UI behavior.
 * Theme toggle · sidebar collapse · toasts · delete confirmation ·
 * table quick-filter · keyboard shortcuts.
 */
(function () {
  'use strict';

  var body = document.body;
  var root = document.documentElement;

  // --- boot state written by the inline <head> script ----------------------
  if (root.className.indexOf('boot-collapsed') !== -1) {
    body.classList.add('sidebar-collapsed');
  }

  // --- theme toggle ---------------------------------------------------------
  var themeBtn = document.getElementById('themeToggle');
  var themeIcon = document.getElementById('themeToggleIcon');

  function paintThemeIcon() {
    if (!themeIcon) return;
    var dark = root.getAttribute('data-bs-theme') === 'dark';
    themeIcon.className = dark ? 'bi bi-sun fs-5' : 'bi bi-moon-stars fs-5';
  }
  paintThemeIcon();

  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-bs-theme', next);
      localStorage.setItem('ds-theme', next);
      paintThemeIcon();
      document.dispatchEvent(new CustomEvent('ds:theme-changed', { detail: next }));
    });
  }

  // --- sidebar ----------------------------------------------------------------
  var sidebarToggle = document.getElementById('sidebarToggle');
  var backdrop = document.getElementById('sidebarBackdrop');

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      if (window.innerWidth <= 991) {
        body.classList.toggle('sidebar-open');
      } else {
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(
          'ds-sidebar',
          body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'
        );
      }
    });
  }
  if (backdrop) {
    backdrop.addEventListener('click', function () {
      body.classList.remove('sidebar-open');
    });
  }

  // --- flash toasts ----------------------------------------------------------
  document.querySelectorAll('.js-flash-toast').forEach(function (el) {
    new bootstrap.Toast(el, { delay: 4200 }).show();
  });

  /**
   * Programmatic toast, used by AJAX pages:
   *   DS.toast('Saved', 'success')
   */
  function toast(message, type) {
    type = type || 'info';
    var container = document.querySelector('.toast-container');
    if (!container) return;
    var el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + type + ' border-0';
    el.setAttribute('role', 'alert');
    el.innerHTML =
      '<div class="d-flex"><div class="toast-body"></div>' +
      '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    el.querySelector('.toast-body').textContent = message;
    container.appendChild(el);
    var t = new bootstrap.Toast(el, { delay: 4200 });
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    t.show();
  }

  // --- confirmation before destructive actions --------------------------------
  // Any <form data-confirm="message"> gets a styled confirmation modal.
  var confirmModalEl = document.getElementById('confirmModal');
  var confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
  var pendingForm = null;

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (form.dataset.confirmed === '1') { form.dataset.confirmed = ''; return; }

    ev.preventDefault();
    pendingForm = form;
    if (confirmModal) {
      document.getElementById('confirmModalText').textContent = form.dataset.confirm;
      confirmModal.show();
    } else if (window.confirm(form.dataset.confirm)) {
      form.dataset.confirmed = '1';
      form.submit();
    }
  });

  var confirmYes = document.getElementById('confirmModalYes');
  if (confirmYes) {
    confirmYes.addEventListener('click', function () {
      if (pendingForm) {
        pendingForm.dataset.confirmed = '1';
        confirmModal.hide();
        pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
        pendingForm = null;
      }
    });
  }

  // --- instant table filter ---------------------------------------------------
  // <input data-table-filter="#someTable"> filters that table's rows as you type.
  document.querySelectorAll('[data-table-filter]').forEach(function (input) {
    var table = document.querySelector(input.dataset.tableFilter);
    if (!table) return;
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
      });
    });
  });

  // --- keyboard shortcuts -------------------------------------------------------
  document.addEventListener('keydown', function (ev) {
    // Ctrl/Cmd+K → focus search (POS search field first if present)
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') {
      var target = document.getElementById('posSearch') || document.getElementById('globalSearch');
      if (target) { ev.preventDefault(); target.focus(); target.select(); }
    }
    // F4 → open POS
    if (ev.key === 'F4') {
      ev.preventDefault();
      window.location.href = 'index.php?r=sales/pos';
    }
  });

  // --- shared helpers -----------------------------------------------------------
  window.DS = {
    toast: toast,
    money: function (n) {
      return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    },
    chartInk: function () {
      var dark = root.getAttribute('data-bs-theme') === 'dark';
      return {
        ink: dark ? '#e5e9f0' : '#1f2937',
        muted: dark ? '#8a94a6' : '#64748b',
        grid: dark ? '#1d2636' : '#eef1f5',
        series1: '#0d9488',
        series2: '#ea580c',
        // 3-series categorical trio (validated for light and dark surfaces;
        // dark mode uses re-stepped hues of the same colors, not a new palette)
        cat3: dark
          ? ['#199e70', '#3987e5', '#d95926']   // aqua, blue, orange (dark steps)
          : ['#1baf7a', '#2a78d6', '#eb6834']   // aqua, blue, orange (light steps)
      };
    }
  };
})();
