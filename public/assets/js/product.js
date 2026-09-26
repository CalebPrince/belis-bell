// Product page enhancements. The page works without this file: sizes are links, all tab panels show
// one under another, and photos are listed. Prices here are a preview only; the server sets prices (CTL-BIZ-001).
(function () {
  var root = document.querySelector('[data-product]');
  if (!root) return;

  function money(p) {
    var cedis = Math.floor(p / 100);
    var rest = String(p % 100).padStart(2, '0');
    return 'GH₵ ' + String(cedis).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + rest;
  }

  function parseTiers(text) {
    return (text || '').split(',').filter(Boolean).map(function (pair) {
      var p = pair.split(':');
      return { min: parseInt(p[0], 10), unit: parseInt(p[1], 10) };
    }).filter(function (t) { return t.min >= 1 && t.unit >= 0; }).sort(function (a, b) { return a.min - b.min; });
  }

  function unitFor(base, tiers, qty) {
    var best = null;
    tiers.forEach(function (t) { if (qty >= t.min && (best === null || t.min > best.min)) best = t; });
    return best === null ? base : best.unit;
  }

  function clampQty(v) {
    var n = parseInt(v, 10);
    if (isNaN(n) || n < 1) return 1;
    return Math.min(n, 100000);
  }

  // ---------- sizes, quantity and the bulk estimate ----------
  var priceEl = root.querySelector('[data-price]');
  var stockEl = root.querySelector('[data-stock]');
  var sizes = Array.prototype.slice.call(root.querySelectorAll('[data-size]'));
  var tierBody = document.querySelector('[data-tier-rows]');
  var bulkQty = document.getElementById('bulk-qty');
  var bulkTotal = document.querySelector('[data-bulk-total]');
  var bulkUnit = document.querySelector('[data-bulk-unit]');
  var stockText = { in_stock: 'In stock', low: 'Low stock', out: 'Out of stock' };
  var base = parseInt(root.getAttribute('data-base'), 10) || 0;
  var tiers = [];
  var current = sizes.filter(function (s) { return s.getAttribute('aria-current') === 'true'; })[0] || sizes[0];
  if (current) tiers = parseTiers(current.getAttribute('data-tiers'));

  function renderEstimate() {
    if (!bulkQty || !bulkTotal) return;
    var qty = clampQty(bulkQty.value);
    var unit = unitFor(base, tiers, qty);
    bulkTotal.textContent = money(unit * qty);
    if (bulkUnit) bulkUnit.textContent = '(' + money(unit) + ' each)';
  }

  function cell(tag, text, scope) {
    var el = document.createElement(tag);
    el.textContent = text;
    if (scope) el.setAttribute('scope', scope);
    return el;
  }

  function renderTable() {
    if (!tierBody) return;
    tierBody.textContent = '';
    if (tiers.length === 0) return;
    function addRow(label, unit) {
      var tr = document.createElement('tr');
      var pct = unit < base ? Math.floor(((base - unit) * 100) / base) : 0;
      tr.appendChild(cell('th', label, 'row'));
      tr.appendChild(cell('td', money(unit)));
      tr.appendChild(cell('td', pct > 0 ? pct + '%' : 'None'));
      tierBody.appendChild(tr);
    }
    function range(from, to) { return to === null ? from + '+' : (from === to ? String(from) : from + ' to ' + to); }
    if (tiers[0].min > 1) addRow(range(1, tiers[0].min - 1), base);
    tiers.forEach(function (t, i) {
      addRow(range(t.min, i + 1 < tiers.length ? tiers[i + 1].min - 1 : null), t.unit);
    });
  }

  sizes.forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      sizes.forEach(function (s) { s.removeAttribute('aria-current'); });
      link.setAttribute('aria-current', 'true');
      var variantInput = document.querySelector('[data-variant-input]');
      if (variantInput) variantInput.value = link.getAttribute('data-variant') || variantInput.value;
      base = parseInt(link.getAttribute('data-price'), 10) || 0;
      tiers = parseTiers(link.getAttribute('data-tiers'));
      if (priceEl) priceEl.textContent = money(base);
      if (stockEl) {
        var s = link.getAttribute('data-stock');
        stockEl.textContent = stockText[s] || '';
        stockEl.className = 'pdp-stock ' + (s === 'out' ? 'is-out' : 'is-in');
      }
      renderTable();
      renderEstimate();
      try { window.history.replaceState(null, '', link.getAttribute('href')); } catch (e) { /* not important */ }
    });
  });

  if (bulkQty) bulkQty.addEventListener('input', renderEstimate);

  var qtyBox = root.querySelector('[data-qty]');
  if (qtyBox) {
    var input = qtyBox.querySelector('input');
    qtyBox.querySelectorAll('[data-step]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        input.value = clampQty(clampQty(input.value) + parseInt(btn.getAttribute('data-step'), 10));
      });
    });
    input.addEventListener('change', function () { input.value = clampQty(input.value); });
  }

  // ---------- gallery and lightbox ----------
  var photos = Array.prototype.slice.call(root.querySelectorAll('[data-photo]'));
  var thumbs = Array.prototype.slice.call(root.querySelectorAll('[data-thumb]'));
  function showPhoto(index) {
    photos.forEach(function (p, i) { p.hidden = i !== index; });
    thumbs.forEach(function (t, i) {
      if (i === index) t.setAttribute('aria-current', 'true'); else t.removeAttribute('aria-current');
    });
  }
  if (photos.length > 1) {
    showPhoto(0);
    thumbs.forEach(function (t, i) { t.addEventListener('click', function () { showPhoto(i); }); });
  }

  var dialog = document.querySelector('[data-lightbox]');
  var expand = root.querySelector('[data-expand]');
  function visibleImg() {
    var shown = photos.filter(function (p) { return !p.hidden; })[0] || photos[0];
    return shown ? shown.querySelector('img') : null;
  }
  if (expand && dialog && typeof dialog.showModal === 'function') {
    expand.hidden = false;
    expand.addEventListener('click', function () {
      var img = visibleImg();
      if (!img) return;
      var srcset = (img.getAttribute('srcset') || '').split(',').map(function (s) { return s.trim().split(' ')[0]; }).filter(Boolean);
      var big = dialog.querySelector('img');
      big.src = srcset.length ? srcset[srcset.length - 1] : img.currentSrc;
      big.alt = img.alt;
      dialog.showModal();
    });
    dialog.querySelector('[data-close]').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
  } else if (expand) {
    expand.hidden = true;
  }
  if (!visibleImg() && expand) expand.hidden = true;

  // ---------- tabs ----------
  var tabs = document.querySelector('[data-tabs]');
  if (tabs) {
    var buttons = Array.prototype.slice.call(tabs.querySelectorAll('[role="tab"]'));
    var panels = Array.prototype.slice.call(tabs.querySelectorAll('[role="tabpanel"]'));
    tabs.classList.add('is-tabbed');
    var select = function (index, focus) {
      buttons.forEach(function (b, i) {
        b.setAttribute('aria-selected', i === index ? 'true' : 'false');
        b.tabIndex = i === index ? 0 : -1;
      });
      panels.forEach(function (p, i) { p.hidden = i !== index; });
      if (focus) buttons[index].focus();
    };
    buttons.forEach(function (b, i) {
      b.addEventListener('click', function () { select(i, false); });
      b.addEventListener('keydown', function (event) {
        var n = buttons.length;
        if (event.key === 'ArrowRight') { event.preventDefault(); select((i + 1) % n, true); }
        else if (event.key === 'ArrowLeft') { event.preventDefault(); select((i + n - 1) % n, true); }
        else if (event.key === 'Home') { event.preventDefault(); select(0, true); }
        else if (event.key === 'End') { event.preventDefault(); select(n - 1, true); }
      });
    });
    select(0, false);
  }
})();
