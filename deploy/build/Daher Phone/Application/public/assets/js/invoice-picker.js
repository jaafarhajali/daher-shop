/**
 * Live invoice picker (Returns & Refunds).
 * Live multi-field search · sorting · pagination · match highlighting.
 * Selecting a row navigates to the page's own details section:
 *   returns/create&sale_id=N   or   refunds/create&sale_id=N
 */
(function () {
  'use strict';

  var $root = document.getElementById('invoicePicker');
  if (!$root) return;

  var mode = $root.dataset.mode;                       // 'return' | 'refund'
  var target = mode === 'refund' ? 'refunds/create' : 'returns/create';

  var $search = document.getElementById('ipSearch');
  var $sort = document.getElementById('ipSort');
  var $body = document.getElementById('ipBody');
  var $empty = document.getElementById('ipEmpty');
  var $emptyText = document.getElementById('ipEmptyText');
  var $loading = document.getElementById('ipLoading');
  var $prev = document.getElementById('ipPrev');
  var $next = document.getElementById('ipNext');
  var $info = document.getElementById('ipPageInfo');

  var state = { q: '', sort: 'date_desc', page: 1, pages: 1 };
  var timer = null;
  var seq = 0;                                          // drop stale responses
  var lastRows = [];

  // --- helpers ---------------------------------------------------------------
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  /** Escape + wrap query matches in <mark> (case-insensitive, no regex traps). */
  function hl(text) {
    var q = state.q;
    text = String(text == null ? '' : text);
    if (!q) return esc(text);
    var out = '', i = 0, lower = text.toLowerCase(), ql = q.toLowerCase(), idx;
    while ((idx = lower.indexOf(ql, i)) !== -1) {
      out += esc(text.slice(i, idx)) + '<mark>' + esc(text.substr(idx, q.length)) + '</mark>';
      i = idx + q.length;
    }
    return out + esc(text.slice(i));
  }

  // --- data ------------------------------------------------------------------
  function load() {
    var mySeq = ++seq;
    $loading.classList.remove('d-none');

    fetch('index.php?r=sales/search-invoices-json'
        + '&q=' + encodeURIComponent(state.q)
        + '&sort=' + encodeURIComponent(state.sort)
        + '&page=' + state.page)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (mySeq !== seq || !data.ok) return;          // an older request finished late
        state.pages = data.pages;
        lastRows = data.rows;
        render(data);
      })
      .catch(function () {
        if (mySeq === seq) DS.toast('Search failed — is the server running?', 'danger');
      })
      .then(function () {
        if (mySeq === seq) $loading.classList.add('d-none');
      });
  }

  // --- rendering ----------------------------------------------------------------
  function render(data) {
    $body.innerHTML = '';

    if (!data.rows.length) {
      $empty.classList.remove('d-none');
      $emptyText.textContent = state.q
        ? 'No invoices match "' + state.q + '".'
        : 'No completed invoices yet.';
      $info.textContent = '';
      $prev.disabled = $next.disabled = true;
      return;
    }
    $empty.classList.add('d-none');

    data.rows.forEach(function (r) {
      var tr = document.createElement('tr');

      var canAct = mode === 'refund' ? r.refundable > 0 : r.has_returnable;
      var disabledReason = mode === 'refund'
        ? 'Nothing refundable on this invoice'
        : 'Everything on this invoice was already returned';

      var invoiceCell =
        '<td><span class="data fw-semibold">' + hl(r.invoice_no) + '</span>' +
        (r.matched_product
          ? '<div class="small text-secondary">contains: ' + hl(r.matched_product) + '</div>'
          : '') +
        '</td>';

      if (mode === 'return') {
        tr.innerHTML =
          invoiceCell +
          '<td class="small">' + esc(r.date) + '</td>' +
          '<td>' + hl(r.customer) + '</td>' +
          '<td class="data small">' + hl(r.phone) + '</td>' +
          '<td class="num">' + esc(r.total_fmt) + '</td>' +
          '<td class="small">' + esc(r.payment) + '</td>' +
          '<td><span class="badge text-bg-' + esc(r.pay_color) + '">' + esc(r.pay_label) + '</span></td>' +
          '<td class="text-end"></td>';
      } else {
        tr.innerHTML =
          invoiceCell +
          '<td class="small">' + esc(r.date) + '</td>' +
          '<td>' + hl(r.customer) + '</td>' +
          '<td class="num">' + esc(r.total_fmt) + '</td>' +
          '<td class="num">' + esc(r.paid_fmt) + '</td>' +
          '<td class="num fw-semibold ' + (r.refundable > 0 ? 'text-success' : 'text-secondary') + '">' +
            esc(r.refundable_fmt) + '</td>' +
          '<td class="text-end"></td>';
      }

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = canAct ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm';
      btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Select';
      if (!canAct) {
        btn.disabled = true;
        btn.title = disabledReason;
      } else {
        btn.addEventListener('click', function () { select(r.id); });
      }
      tr.lastElementChild.appendChild(btn);

      if (canAct) {
        tr.style.cursor = 'pointer';
        tr.addEventListener('dblclick', function () { select(r.id); });
      }

      $body.appendChild(tr);
    });

    $info.textContent = data.total + ' invoice' + (data.total === 1 ? '' : 's')
                      + ' · page ' + data.page + ' of ' + data.pages;
    $prev.disabled = data.page <= 1;
    $next.disabled = data.page >= data.pages;
  }

  function select(id) {
    window.location.href = 'index.php?r=' + target + '&sale_id=' + id;
  }

  // --- events -----------------------------------------------------------------
  $search.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(function () {
      state.q = $search.value.trim();
      state.page = 1;
      load();
    }, 250);
  });

  $search.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      // One usable match → straight through, no extra click.
      var usable = lastRows.filter(function (r) {
        return mode === 'refund' ? r.refundable > 0 : r.has_returnable;
      });
      if (usable.length === 1) select(usable[0].id);
    }
  });

  $sort.addEventListener('change', function () {
    state.sort = $sort.value;
    state.page = 1;
    load();
  });

  $prev.addEventListener('click', function () {
    if (state.page > 1) { state.page--; load(); }
  });
  $next.addEventListener('click', function () {
    if (state.page < state.pages) { state.page++; load(); }
  });

  // Latest invoices are shown immediately — no typing needed for recent sales.
  load();
})();
