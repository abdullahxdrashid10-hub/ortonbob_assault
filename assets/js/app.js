/* app.js - small UI behaviours: mobile menu, scroll-reveal, count-up numbers.
   No external libraries. (three.js background will be added last in its own file.) */
document.addEventListener('DOMContentLoaded', function () {
  // Mobile navigation toggle.
  var t = document.querySelector('.nav-toggle'), l = document.querySelector('.nav-links');
  if (t) t.addEventListener('click', function () { l.classList.toggle('open'); });

  // Reveal elements when they scroll into view.
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.1 });
  document.querySelectorAll('.reveal').forEach(function (el, i) {
    el.style.transitionDelay = (i % 6) * 60 + 'ms'; // gentle stagger
    io.observe(el);
  });

  // Animate dashboard numbers from 0 to their data-count value.
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var end = parseInt(el.dataset.count, 10), n = 0, step = Math.max(1, Math.ceil(end / 30));
    if (!end) { el.textContent = '0'; return; }
    var id = setInterval(function () { n = Math.min(end, n + step); el.textContent = n; if (n >= end) clearInterval(id); }, 30);
  });

  // Auto-fade success flash messages like a toast after 4 seconds (keep errors visible).
  document.querySelectorAll('.alert-success').forEach(function (el) {
    setTimeout(function () {
      el.classList.add('fade-out');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 480);
    }, 4000);
  });
});
