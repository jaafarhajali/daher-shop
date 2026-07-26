/**
 * Point of Sale — cart state, live search, customer picker, checkout.
 */
(function () {
  'use strict';

  var CUR = (window.POS && POS.currency) || '$';
  var cart = [];                     // {id, name, price, qty, stock}
  var customer = (window.POS && POS.customer) || null;

  var $search = document.getElementById('posSearch');
  var $results = document.getElementById('posResults');
  var $body = document.getElementById('posCartBody');
  var $subtotal = document.getElementById('posSubtotal');
  var $total = document.getElementById('posTotal');
  var $discount = document.getElementById('posDiscount');
  var $checkout = document.getElementById('posCheckout');
  var $spinner = document.getElementById('posSpinner');
  var token = document.getElementById('posToken').value;

  var lastResults = [];

  // ------------------------------------------------------------ search ----
  var searchTimer = null;
  $search.addEventListener('input', function () {
    clearTimeout(searchTimer);
    var q = $search.value.trim();
    if (!q) { renderResults([]); return; }
    searchTimer = setTimeout(function () { doSearch(q); }, 220);
  });

  $search.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      if (lastResults.length > 0) {
        addToCart(lastResults[0]);
        $search.value = '';
        renderResults([]);
      }
    }
  });

  function doSearch(q) {
    fetch('index.php?r=products/search-json&q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        lastResults = data.items;
        // Barcode scanners type + press Enter fast; an exact match adds itself.
        if (data.exact && data.items.length === 1) {
          addToCart(data.items[0]);
          $search.value = '';
          renderResults([]);
          return;
        }
        renderResults(data.items);
      })
      .catch(function () { DS.toast('Search failed — is the server running?', 'danger'); });
  }

  function renderResults(items) {
    lastResults = items;
    if (!items.length) {
      $results.innerHTML =
        '<div class="empty-state"><i class="bi bi-search"></i>' +
        ($search.value.trim() ? 'No matching products in stock.' : 'Search results appear here.') +
        '</div>';
      return;
    }
    $results.innerHTML = '';
    items.forEach(function (p) {
      var noPrice = p.price === null || p.price === undefined;
      var el = document.createElement('div');
      el.className = 'pos-product' + (noPrice ? ' opacity-75' : '');
      el.innerHTML =
        '<div class="min-w-0">' +
          '<div class="fw-semibold text-truncate"></div>' +
          '<div class="small text-secondary"><span class="data"></span> in stock' +
          (p.barcode ? ' · <span class="data barcode"></span>' : '') + '</div>' +
        '</div>' +
        '<div class="data fw-semibold text-accent"></div>';
      el.querySelector('.fw-semibold.text-truncate').textContent = p.name;
      el.querySelector('.small .data').textContent = p.stock;
      if (p.barcode) el.querySelector('.barcode').textContent = p.barcode;
      var priceEl = el.querySelector('.data.fw-semibold.text-accent');
      if (noPrice) {
        priceEl.className = 'badge text-bg-danger';
        priceEl.textContent = 'No price';
      } else {
        priceEl.textContent = CUR + DS.money(p.price);
      }
      el.addEventListener('click', function () { addToCart(p); });
      $results.appendChild(el);
    });
  }

  // ------------------------------------------------------------- cart ----
  function addToCart(p) {
    // Products without a selling price cannot be sold (server enforces it too).
    if (p.price === null || p.price === undefined) {
      DS.toast('This product does not have a selling price. Please enter a selling price before completing the sale.', 'danger');
      return;
    }
    var line = cart.find(function (l) { return l.id === p.id; });
    if (line) {
      if (line.qty + 1 > p.stock) {
        DS.toast('Only ' + p.stock + ' in stock for "' + p.name + '".', 'warning');
        return;
      }
      line.qty += 1;
    } else {
      cart.push({ id: p.id, name: p.name, price: p.price, qty: 1, stock: p.stock });
    }
    renderCart();
  }

  function renderCart() {
    if (!cart.length) {
      $body.innerHTML =
        '<tr class="pos-empty-row"><td colspan="5">' +
        '<div class="empty-state py-4"><i class="bi bi-cart"></i>Cart is empty.</div></td></tr>';
    } else {
      $body.innerHTML = '';
      cart.forEach(function (line, i) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="text-truncate" style="max-width:150px"></td>' +
          '<td><div class="input-group input-group-sm">' +
            '<button class="btn btn-outline-secondary btn-qty-minus" type="button">−</button>' +
            '<input class="form-control data text-center cart-qty-input" type="number" min="1" value="' + line.qty + '">' +
            '<button class="btn btn-outline-secondary btn-qty-plus" type="button">+</button>' +
          '</div></td>' +
          '<td><input class="form-control form-control-sm data text-end cart-price-input" ' +
                'type="number" min="0" step="0.01" value="' + line.price + '"></td>' +
          '<td class="num line-total"></td>' +
          '<td><button class="btn btn-link text-danger p-0 btn-remove" type="button" title="Remove">' +
            '<i class="bi bi-x-lg"></i></button></td>';

        tr.querySelector('td').textContent = line.name;
        tr.querySelector('td').title = line.name;
        tr.querySelector('.line-total').textContent = CUR + DS.money(line.price * line.qty);

        tr.querySelector('.btn-qty-minus').addEventListener('click', function () {
          line.qty > 1 ? (line.qty--, renderCart()) : removeLine(i);
        });
        tr.querySelector('.btn-qty-plus').addEventListener('click', function () {
          if (line.qty + 1 > line.stock) {
            DS.toast('Only ' + line.stock + ' in stock.', 'warning');
            return;
          }
          line.qty++; renderCart();
        });
        tr.querySelector('.cart-qty-input').addEventListener('change', function (ev) {
          var v = parseInt(ev.target.value, 10) || 1;
          if (v > line.stock) { v = line.stock; DS.toast('Only ' + line.stock + ' in stock.', 'warning'); }
          line.qty = Math.max(1, v);
          renderCart();
        });
        tr.querySelector('.cart-price-input').addEventListener('change', function (ev) {
          line.price = Math.max(0, parseFloat(ev.target.value) || 0);
          renderCart();
        });
        tr.querySelector('.btn-remove').addEventListener('click', function () { removeLine(i); });

        $body.appendChild(tr);
      });
    }
    refreshTotals();
  }

  function removeLine(i) {
    cart.splice(i, 1);
    renderCart();
  }

  function refreshTotals() {
    var subtotal = cart.reduce(function (sum, l) { return sum + l.price * l.qty; }, 0);
    var discount = Math.min(Math.max(0, parseFloat($discount.value) || 0), subtotal);
    $subtotal.textContent = CUR + DS.money(subtotal);
    $total.textContent = CUR + DS.money(subtotal - discount);
    $checkout.disabled = cart.length === 0;
  }

  $discount.addEventListener('input', refreshTotals);

  document.getElementById('posClearCart').addEventListener('click', function () {
    cart = [];
    renderCart();
  });

  // -------------------------------------------------------- customer ----
  var $custSearch = document.getElementById('posCustomerSearch');
  var $custResults = document.getElementById('posCustomerResults');
  var $custSelected = document.getElementById('posCustomerSelected');
  var $custPicker = document.getElementById('posCustomerPicker');
  var $custName = document.getElementById('posCustomerName');
  var custTimer = null;

  function paintCustomer() {
    if (customer) {
      $custName.textContent = customer.name + (customer.phone ? ' · ' + customer.phone : '');
      $custSelected.classList.remove('d-none');
      $custSelected.classList.add('d-flex');
      $custPicker.classList.add('d-none');
    } else {
      $custSelected.classList.add('d-none');
      $custSelected.classList.remove('d-flex');
      $custPicker.classList.remove('d-none');
    }
  }
  paintCustomer();

  $custSearch.addEventListener('input', function () {
    clearTimeout(custTimer);
    var q = $custSearch.value.trim();
    if (!q) { $custResults.classList.add('d-none'); return; }
    custTimer = setTimeout(function () {
      fetch('index.php?r=customers/search-json&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          $custResults.innerHTML = '';
          if (!data.items.length) {
            $custResults.classList.add('d-none');
            return;
          }
          data.items.forEach(function (c) {
            var a = document.createElement('button');
            a.type = 'button';
            a.className = 'list-group-item list-group-item-action';
            a.textContent = c.name + (c.phone ? ' · ' + c.phone : '');
            a.addEventListener('click', function () {
              customer = c;
              $custResults.classList.add('d-none');
              $custSearch.value = '';
              paintCustomer();
              refreshCreditHint();
            });
            $custResults.appendChild(a);
          });
          $custResults.classList.remove('d-none');
        });
    }, 220);
  });

  document.getElementById('posCustomerClear').addEventListener('click', function () {
    customer = null;
    paintCustomer();
    refreshCreditHint();
  });

  // -------------------------------------------------------- checkout ----
  var $method = document.getElementById('posMethod');
  var $creditHint = document.getElementById('posCreditHint');

  function refreshCreditHint() {
    if ($creditHint) {
      $creditHint.classList.toggle('d-none', !($method.value === 'credit' && !customer));
    }
  }
  $method.addEventListener('change', refreshCreditHint);

  function checkout() {
    if (!cart.length || $checkout.disabled) return;

    // A debt needs a debtor — block credit checkout without a customer.
    if ($method.value === 'credit' && !customer) {
      DS.toast('Credit (دين) sales must have a customer — please select the customer first.', 'danger');
      refreshCreditHint();
      return;
    }

    $checkout.disabled = true;
    $spinner.classList.remove('d-none');

    var form = new FormData();
    form.append('_token', token);
    form.append('items', JSON.stringify(cart.map(function (l) {
      return { id: l.id, qty: l.qty, price: l.price };
    })));
    form.append('discount', parseFloat($discount.value) || 0);
    form.append('payment_method', document.getElementById('posMethod').value);
    form.append('notes', document.getElementById('posNotes').value);
    form.append('customer_id', customer ? customer.id : 0);

    fetch('index.php?r=sales/checkout', { method: 'POST', body: form })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          window.location.href = 'index.php?r=sales/show&id=' + data.sale_id;
        } else {
          DS.toast(data.error || 'Checkout failed.', 'danger');
          $checkout.disabled = false;
          $spinner.classList.add('d-none');
        }
      })
      .catch(function () {
        DS.toast('Checkout failed — check the server.', 'danger');
        $checkout.disabled = false;
        $spinner.classList.add('d-none');
      });
  }

  $checkout.addEventListener('click', checkout);
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'F9') { ev.preventDefault(); checkout(); }
  });

  renderCart();
})();
