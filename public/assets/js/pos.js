/**
 * Point of Sale — cart state, live search, customer picker, checkout.
 *
 * Pricing model (v1.2):
 *   · every product has an optional DEFAULT selling price (suggestion only)
 *   · the cart line holds the ACTUAL sale price — editable per sale
 *   · unpriced products can be added; the till must type a price before checkout
 *   · selling below cost asks for confirmation
 */
(function () {
  'use strict';

  var CUR = (window.POS && POS.currency) || '$';
  var cart = [];   // {id, name, price|null, defaultPrice|null, cost, qty, stock}
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
      el.className = 'pos-product';
      el.innerHTML =
        '<div class="min-w-0">' +
          '<div class="fw-semibold text-truncate"></div>' +
          '<div class="small text-secondary"><span class="data"></span> in stock' +
          (p.barcode ? ' · <span class="data barcode"></span>' : '') + '</div>' +
        '</div>' +
        '<div class="text-end"></div>';
      el.querySelector('.fw-semibold.text-truncate').textContent = p.name;
      el.querySelector('.small .data').textContent = p.stock;
      if (p.barcode) el.querySelector('.barcode').textContent = p.barcode;

      var right = el.querySelector('.text-end');
      if (noPrice) {
        right.innerHTML = '<span class="badge text-bg-warning">Set price at checkout</span>';
      } else {
        right.innerHTML = '<div class="data fw-semibold text-accent"></div>' +
                          '<div class="small text-secondary">default</div>';
        right.querySelector('.data').textContent = CUR + DS.money(p.price);
      }
      el.addEventListener('click', function () { addToCart(p); });
      $results.appendChild(el);
    });
  }

  // ------------------------------------------------------------- cart ----
  function addToCart(p) {
    var noPrice = p.price === null || p.price === undefined;
    var line = cart.find(function (l) { return l.id === p.id; });
    if (line) {
      if (line.qty + 1 > p.stock) {
        DS.toast('Only ' + p.stock + ' in stock for "' + p.name + '".', 'warning');
        return;
      }
      line.qty += 1;
    } else {
      cart.push({
        id: p.id,
        name: p.name,
        price: noPrice ? null : p.price,        // ACTUAL sale price (editable)
        defaultPrice: noPrice ? null : p.price, // suggestion from the catalog
        cost: Number(p.cost) || 0,
        qty: 1,
        stock: p.stock
      });
      if (noPrice) {
        DS.toast('"' + p.name + '" has no selling price — type one in the cart before completing the sale.', 'warning');
        renderCart();
        // Put the cursor straight into the empty price field.
        var inputs = $body.querySelectorAll('.cart-price-input');
        if (inputs.length) inputs[inputs.length - 1].focus();
        return;
      }
    }
    renderCart();
  }

  function renderCart() {
    if (!cart.length) {
      $body.innerHTML =
        '<div class="empty-state py-4"><i class="bi bi-cart"></i>Cart is empty.</div>';
    } else {
      $body.innerHTML = '';
      cart.forEach(function (line, i) {
        var priceMissing = line.price === null;
        var belowCost = !priceMissing && line.cost > 0 && line.price < line.cost;

        var el = document.createElement('div');
        el.className = 'cart-line';
        el.innerHTML =
          // Row 1: name (+hint) ..... line total, remove
          '<div class="d-flex justify-content-between align-items-start gap-2">' +
            '<div class="min-w-0">' +
              '<div class="fw-semibold text-truncate line-name"></div>' +
              '<div class="small line-hint"></div>' +
            '</div>' +
            '<div class="d-flex align-items-center gap-2 flex-shrink-0">' +
              '<span class="data fw-semibold line-total"></span>' +
              '<button class="btn btn-link text-danger p-0 btn-remove" type="button" title="Remove">' +
                '<i class="bi bi-x-lg"></i></button>' +
            '</div>' +
          '</div>' +
          // Row 2: qty stepper ..... editable sale price
          '<div class="d-flex justify-content-between align-items-center gap-2 mt-2">' +
            '<div class="input-group input-group-sm qty-group">' +
              '<button class="btn btn-outline-secondary btn-qty-minus" type="button">−</button>' +
              '<input class="form-control data cart-qty-input" type="number" min="1" value="' + line.qty + '">' +
              '<button class="btn btn-outline-secondary btn-qty-plus" type="button">+</button>' +
            '</div>' +
            '<div class="input-group input-group-sm price-group">' +
              '<span class="input-group-text"></span>' +
              '<input class="form-control data cart-price-input" ' +
                     'type="number" min="0" step="0.01" placeholder="sale price">' +
            '</div>' +
          '</div>';

        var nameEl = el.querySelector('.line-name');
        nameEl.textContent = line.name;
        nameEl.title = line.name;

        el.querySelector('.price-group .input-group-text').textContent = CUR;

        // Hint under the name: missing price / below cost / edited vs default.
        var hint = el.querySelector('.line-hint');
        if (priceMissing) {
          hint.className = 'small line-hint text-danger';
          hint.textContent = 'enter a sale price';
        } else if (belowCost) {
          hint.className = 'small line-hint text-warning-emphasis';
          hint.textContent = 'below cost ' + CUR + DS.money(line.cost);
        } else if (line.defaultPrice !== null && Number(line.price) !== Number(line.defaultPrice)) {
          hint.className = 'small line-hint text-secondary';
          hint.textContent = 'default ' + CUR + DS.money(line.defaultPrice);
        } else {
          hint.remove();
        }

        var priceInput = el.querySelector('.cart-price-input');
        priceInput.value = priceMissing ? '' : line.price;
        if (priceMissing) priceInput.classList.add('border-danger');
        if (belowCost) priceInput.classList.add('border-warning');

        el.querySelector('.line-total').textContent =
          priceMissing ? '—' : CUR + DS.money(line.price * line.qty);

        el.querySelector('.btn-qty-minus').addEventListener('click', function () {
          line.qty > 1 ? (line.qty--, renderCart()) : removeLine(i);
        });
        el.querySelector('.btn-qty-plus').addEventListener('click', function () {
          if (line.qty + 1 > line.stock) {
            DS.toast('Only ' + line.stock + ' in stock.', 'warning');
            return;
          }
          line.qty++; renderCart();
        });
        el.querySelector('.cart-qty-input').addEventListener('change', function (ev) {
          var v = parseInt(ev.target.value, 10) || 1;
          if (v > line.stock) { v = line.stock; DS.toast('Only ' + line.stock + ' in stock.', 'warning'); }
          line.qty = Math.max(1, v);
          renderCart();
        });
        priceInput.addEventListener('change', function (ev) {
          var raw = ev.target.value;
          line.price = raw === '' ? null : Math.max(0, parseFloat(raw) || 0);
          renderCart();
        });
        el.querySelector('.btn-remove').addEventListener('click', function () { removeLine(i); });

        $body.appendChild(el);
      });
    }
    refreshTotals();
  }

  function removeLine(i) {
    cart.splice(i, 1);
    renderCart();
  }

  function refreshTotals() {
    var priced = cart.filter(function (l) { return l.price !== null; });
    var missing = cart.length - priced.length;
    var subtotal = priced.reduce(function (sum, l) { return sum + l.price * l.qty; }, 0);
    var discount = Math.min(Math.max(0, parseFloat($discount.value) || 0), subtotal);

    $subtotal.textContent = CUR + DS.money(subtotal);
    $total.textContent = CUR + DS.money(subtotal - discount);
    $checkout.disabled = cart.length === 0 || missing > 0;
    $checkout.title = missing > 0
      ? 'Enter a sale price for every item first'
      : '';
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

    // Every line needs a real sale price before the invoice can exist.
    var unpriced = cart.filter(function (l) { return l.price === null; });
    if (unpriced.length) {
      DS.toast('This product does not have a selling price. Please enter a selling price before completing the sale. (' + unpriced[0].name + ')', 'danger');
      return;
    }

    // A debt needs a debtor — block credit checkout without a customer.
    if ($method.value === 'credit' && !customer) {
      DS.toast('Credit (دين) sales must have a customer — please select the customer first.', 'danger');
      refreshCreditHint();
      return;
    }

    // Selling below cost is allowed, but never silently.
    var belowCost = cart.filter(function (l) { return l.cost > 0 && l.price < l.cost; });
    if (belowCost.length) {
      var msg = belowCost.length === 1
        ? 'Warning: "' + belowCost[0].name + '" is being sold below cost price. Continue?'
        : 'Warning: ' + belowCost.length + ' products are being sold below cost price. Continue?';
      DS.confirm(msg).then(function (ok) {
        if (ok) submitSale();
      });
      return;
    }

    submitSale();
  }

  function submitSale() {
    $checkout.disabled = true;
    $spinner.classList.remove('d-none');

    var form = new FormData();
    form.append('_token', token);
    form.append('items', JSON.stringify(cart.map(function (l) {
      return { id: l.id, qty: l.qty, price: l.price };
    })));
    form.append('discount', parseFloat($discount.value) || 0);
    form.append('payment_method', $method.value);
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
