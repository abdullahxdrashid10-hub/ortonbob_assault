/* app.js - small UI behaviours: mobile menu, scroll-reveal, count-up numbers,
   toast auto-fade, and subtle 2D canvas particle backdrop (plain JS, no external libraries). */
document.addEventListener('DOMContentLoaded', function () {
  // Mobile navigation toggle with aria-expanded sync.
  var t = document.querySelector('.nav-toggle'), l = document.querySelector('.nav-links');
  if (t && l) {
    t.addEventListener('click', function () {
      var open = l.classList.toggle('open');
      t.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

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

  // Subtle 2D floating particle & constellation backdrop on #bg-canvas.
  var canvas = document.getElementById('bg-canvas');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (canvas && canvas.getContext && !reduceMotion) {
    var ctx = canvas.getContext('2d');
    var particles = [];
    var count = 34;

    function resize() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    for (var i = 0; i < count; i++) {
      particles.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        vx: (Math.random() - 0.5) * 0.35,
        vy: (Math.random() - 0.5) * 0.35,
        r: Math.random() * 1.8 + 0.8
      });
    }

    function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      for (var i = 0; i < count; i++) {
        var p = particles[i];
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > canvas.width) p.vx *= -1;
        if (p.y < 0 || p.y > canvas.height) p.vy *= -1;

        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(44, 82, 130, 0.22)';
        ctx.fill();

        for (var j = i + 1; j < count; j++) {
          var q = particles[j];
          var dx = p.x - q.x, dy = p.y - q.y;
          var dist = dx * dx + dy * dy;
          if (dist < 16000) {
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.lineTo(q.x, q.y);
            ctx.strokeStyle = 'rgba(74, 124, 155, ' + (0.11 * (1 - dist / 16000)).toFixed(3) + ')';
            ctx.lineWidth = 1;
            ctx.stroke();
          }
        }
      }
      requestAnimationFrame(draw);
    }
    requestAnimationFrame(draw);
  }
});
