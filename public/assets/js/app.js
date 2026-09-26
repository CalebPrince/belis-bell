// Small progressive enhancements. Every page works without this file (CSP: served from our own origin).
document.documentElement.classList.add('js');

document.querySelectorAll('[data-carousel]').forEach(function (root) {
  var track = root.querySelector('[data-track]');
  if (!track) return;
  var dotsBox = root.querySelector('[data-dots]');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var pages = 1;

  function scrollToPage(index) {
    var max = track.scrollWidth - track.clientWidth;
    var left = pages > 1 ? (max * index) / (pages - 1) : 0;
    track.scrollTo({ left: left, behavior: reduce ? 'auto' : 'smooth' });
  }

  function currentPage() {
    var max = track.scrollWidth - track.clientWidth;
    if (max <= 0 || pages <= 1) return 0;
    return Math.round((track.scrollLeft / max) * (pages - 1));
  }

  function paintDots() {
    if (!dotsBox) return;
    var active = currentPage();
    Array.prototype.forEach.call(dotsBox.children, function (dot, i) {
      if (i === active) dot.setAttribute('aria-current', 'true');
      else dot.removeAttribute('aria-current');
    });
  }

  function buildDots() {
    if (!dotsBox) return;
    // At most three dots: start, middle and end of the row, however many products there are.
    pages = Math.min(3, Math.max(1, Math.ceil((track.scrollWidth - 1) / track.clientWidth)));
    dotsBox.textContent = '';
    dotsBox.hidden = pages <= 1;
    for (var i = 0; i < pages; i++) {
      var dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'carousel-dot';
      dot.setAttribute('aria-label', 'Show products, page ' + (i + 1) + ' of ' + pages);
      (function (index) {
        dot.addEventListener('click', function () { scrollToPage(index); });
      })(i);
      dotsBox.appendChild(dot);
    }
    paintDots();
  }

  root.querySelectorAll('[data-dir]').forEach(function (button) {
    button.addEventListener('click', function () {
      var dir = Number(button.getAttribute('data-dir')) || 1;
      track.scrollBy({ left: dir * track.clientWidth * 0.8, behavior: reduce ? 'auto' : 'smooth' });
    });
  });

  var ticking = false;
  track.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () { paintDots(); ticking = false; });
  }, { passive: true });
  window.addEventListener('resize', buildDots);
  buildDots();
});

// Filters start closed on small screens (they are open in the HTML so everything works without JavaScript).
document.querySelectorAll('[data-filters]').forEach(function (box) {
  if (window.matchMedia('(max-width: 1023px)').matches) box.removeAttribute('open');
});

// Forms marked data-autosubmit submit when their select or number changes (the button is hidden with JavaScript on).
document.querySelectorAll('[data-autosubmit]').forEach(function (form) {
  form.querySelectorAll('select, input[type="number"]').forEach(function (field) {
    field.addEventListener('change', function () { form.submit(); });
  });
});
