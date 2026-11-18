<?php
/** Public landing page. */
$role = null; $title = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero-split reveal">
  <div class="hero-copy">
    <span class="pill"><i class="live-dot" aria-hidden="true"></i> E-Vaccination Portal</span>
    <h1>Never miss a <em>vaccination</em> again.</h1>
    <p>Register your child, follow a personalized immunization timeline, and book a nearby certified hospital in a few clicks.</p>
    <div class="hero-cta">
      <a class="btn" href="parent/register.php">I'm a parent</a>
      <a class="btn btn-ghost" href="hospital/login.php">I'm a hospital</a>
    </div>
  </div>
  <div class="hero-media">
    <img src="<?= BASE_URL ?>/assets/img/hero-immunization.jpg" alt="Pediatric healthcare and child immunization illustration" loading="eager">
  </div>
</section>
<section class="cards">
  <article class="card hover reveal">
    <div class="feature-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <h3>Child profiles</h3>
    <p class="muted">Keep every child's details and history in one place.</p>
  </article>
  <article class="card hover reveal">
    <div class="feature-icon fi-mint" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </div>
    <h3>Due-date alerts</h3>
    <p class="muted">Dashboard highlights vaccines due in the next 30 days or overdue.</p>
  </article>
  <article class="card hover reveal">
    <div class="feature-icon fi-indigo" aria-hidden="true">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
    </div>
    <h3>Easy booking</h3>
    <p class="muted">Search hospitals, pick a date, and track approval live.</p>
  </article>
  <article class="card hover reveal">
    <div class="feature-icon fi-amber" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    </div>
    <h3>Reports</h3>
    <p class="muted">Print an official record of every vaccine taken.</p>
  </article>
</section>
<?php require __DIR__ . '/includes/vaccine_guide.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
