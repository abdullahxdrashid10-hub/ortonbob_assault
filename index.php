<?php
/** Public landing page. */
$role = null; $title = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero reveal">
  <span class="pill">E-Vaccination Portal</span>
  <h1>Never miss a <em>vaccination</em> again.</h1>
  <p>Register your child, get reminders for upcoming doses, and book a nearby hospital in a few clicks.</p>
  <div class="hero-cta">
    <a class="btn" href="parent/register.php">I'm a parent</a>
    <a class="btn btn-ghost" href="hospital/login.php">I'm a hospital</a>
  </div>
</section>
<section class="cards">
  <article class="card hover reveal"><h3>&#128118; Child profiles</h3><p class="muted">Keep every child's details and history in one place.</p></article>
  <article class="card hover reveal"><h3>&#128276; Due-date alerts</h3><p class="muted">Dashboard highlights vaccines due in the next 30 days or overdue.</p></article>
  <article class="card hover reveal"><h3>&#127973; Easy booking</h3><p class="muted">Search hospitals, pick a date, and track approval live.</p></article>
  <article class="card hover reveal"><h3>&#128196; Reports</h3><p class="muted">Print a record of every vaccine taken.</p></article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
