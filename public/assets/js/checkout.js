// Checkout preview only: updates the delivery fee and total when the delivery method changes.
// The page works without this file, and the server sets the real amount (CTL-BIZ-001).
(function () {
  var box = document.querySelector('[data-checkout]');
  if (!box) return;
  var subtotal = parseInt(box.getAttribute('data-subtotal'), 10) || 0;
  var feeOut = box.querySelector('[data-fee-out]');
  var totalOut = box.querySelector('[data-total-out]');

  function money(p) {
    var cedis = Math.floor(p / 100);
    var rest = String(p % 100).padStart(2, '0');
    return 'GH₵ ' + String(cedis).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + rest;
  }

  document.querySelectorAll('input[name="delivery"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var fee = parseInt(radio.getAttribute('data-fee'), 10) || 0;
      if (feeOut) feeOut.textContent = money(fee);
      if (totalOut) totalOut.textContent = money(subtotal + fee);
    });
  });
})();
