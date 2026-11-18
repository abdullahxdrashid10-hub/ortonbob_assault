<?php
/**
 * vaccine_guide.php - Parent Vaccine Education & Component Guide
 * 
 * Shared partial included on index.php and parent/hospitals.php.
 * Queries active vaccines via shared_get_all_active_vaccines() and pairs each
 * with its local Onyx/Indigo/Mint illustration plus parent-friendly guidance on:
 * - Importance (why the child needs it)
 * - Role (how it builds targeted immunity)
 * - Key Components (what is inside the vaccine)
 */

require_once __DIR__ . '/shared_queries.php';

$guideVaccines = shared_get_all_active_vaccines();

// Curated clinical overview metadata keyed by official vaccines.short_code
$vaccineGuideMeta = [
    'BCG' => [
        'img'        => 'vac-bcg.jpg',
        'importance' => 'Protects newborns against severe childhood tuberculosis, including TB meningitis and miliary TB.',
        'role'       => 'Primes the infant immune system right at birth with early cell-mediated defense.',
        'components' => 'Live attenuated Mycobacterium bovis (BCG strain), buffered saline diluent.',
    ],
    'OPV' => [
        'img'        => 'vac-opv.jpg',
        'importance' => 'Prevents poliomyelitis, a viral infection that can cause permanent paralysis in young children.',
        'role'       => 'Builds strong mucosal immunity in the gut to block both infection and person-to-person spread.',
        'components' => 'Live attenuated oral poliovirus types 1 and 3 (bivalent OPV), magnesium chloride stabilizer.',
    ],
    'PENTA' => [
        'img'        => 'vac-penta.jpg',
        'importance' => 'Combines five life-saving protections in one visit, reducing injections for babies.',
        'role'       => 'Trains antibodies simultaneously against 5 serious bacterial and viral threats during early infancy.',
        'components' => 'Diphtheria & Tetanus toxoids, inactivated Pertussis, Hepatitis B surface antigen, Hib conjugate.',
    ],
    'PCV' => [
        'img'        => 'vac-pcv.jpg',
        'importance' => 'Guards infants against pneumococcal pneumonia, bloodstream infections, and bacterial meningitis.',
        'role'       => 'Generates long-lasting antibodies against 10 high-risk Streptococcus pneumoniae serotypes.',
        'components' => 'Purified capsular polysaccharides (10 serotypes) conjugated to carrier protein, aluminum phosphate adjuvant.',
    ],
    'ROTA' => [
        'img'        => 'vac-rota.jpg',
        'importance' => 'Prevents severe rotavirus gastroenteritis, a leading cause of dangerous infant dehydration.',
        'role'       => 'Stimulates rapid intestinal IgA immunity before peak infant exposure.',
        'components' => 'Live attenuated human/bovine rotavirus strain, sucrose-based oral buffer solution.',
    ],
    'IPV' => [
        'img'        => 'vac-ipv.jpg',
        'importance' => 'Reinforces systemic blood immunity against all poliovirus strains alongside OPV drops.',
        'role'       => 'Triggers circulating serum antibodies that protect the central nervous system.',
        'components' => 'Inactivated (killed) poliovirus types 1, 2, and 3, 2-phenoxyethanol/trace stabilizers.',
    ],
    'MR' => [
        'img'        => 'vac-mr.jpg',
        'importance' => 'Prevents measles complications (pneumonia, encephalitis) and protects against rubella.',
        'role'       => 'Establishes durable, lifelong viral memory across two scheduled childhood doses.',
        'components' => 'Live attenuated Edmonston-Zagreb measles & Wistar RA 27/3 rubella viruses, sorbitol-gelatin stabilizer.',
    ],
    'TCV' => [
        'img'        => 'vac-tcv.jpg',
        'importance' => 'Offers durable protection against drug-resistant typhoid fever in children.',
        'role'       => 'Uses protein conjugation so even toddlers under 2 years form strong T-cell memory.',
        'components' => 'Salmonella Typhi Vi capsular polysaccharide conjugated to tetanus toxoid carrier protein.',
    ],
];
?>

<?php if (!empty($guideVaccines)): ?>
<section class="vaccine-section reveal" id="vaccine-guide">
  <div class="vaccine-section-head">
    <div>
      <h2 style="margin-bottom:4px;">Parent Vaccine Guide (<?= count($guideVaccines) ?> Essential Vaccines)</h2>
      <p class="muted" style="margin:0;">Understand each vaccine's importance, immune role, and key components before booking.</p>
    </div>
  </div>

  <div class="vaccine-grid">
    <?php foreach ($guideVaccines as $v): ?>
      <?php
        $code    = strtoupper((string)($v['short_code'] ?? ''));
        $meta    = $vaccineGuideMeta[$code] ?? null;
        $imgFile = $meta['img'] ?? '';
        $imgDisk = $imgFile !== '' ? (__DIR__ . '/../assets/img/' . $imgFile) : '';
        $hasImg  = ($imgDisk !== '' && is_file($imgDisk));
        $doses   = (int)$v['total_doses'];
      ?>
      <article class="vaccine-card">
        <div class="vaccine-thumb">
          <?php if ($hasImg): ?>
            <img src="<?= BASE_URL ?>/assets/img/<?= e($imgFile) ?>" alt="<?= e($v['vaccine_name']) ?> clinical illustration" loading="lazy">
          <?php else: ?>
            <!-- Fallback Onyx/Indigo/Mint SVG emblem if image asset is unavailable -->
            <svg viewBox="0 0 320 200" width="100%" height="100%" aria-hidden="true" style="display:block;">
              <defs>
                <radialGradient id="vg-<?= e($code) ?>" cx="50%" cy="50%" r="55%">
                  <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.45"/>
                  <stop offset="100%" stop-color="#0f111a" stop-opacity="1"/>
                </radialGradient>
              </defs>
              <rect width="320" height="200" fill="url(#vg-<?= e($code) ?>)"/>
              <circle cx="160" cy="100" r="56" fill="none" stroke="#6366f1" stroke-width="2" stroke-opacity="0.55"/>
              <circle cx="160" cy="100" r="40" fill="none" stroke="#2ee59d" stroke-width="2" stroke-dasharray="6 4"/>
              <path d="M160 66 L186 78 V102 C186 120 174 134 160 140 C146 134 134 120 134 102 V78 Z" fill="rgba(99,102,241,0.22)" stroke="#e2e8f0" stroke-width="2"/>
              <path d="M151 102 L157 108 L171 93" fill="none" stroke="#2ee59d" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          <?php endif; ?>

          <?php if ($code !== ''): ?>
            <span class="vaccine-code-pill"><?= e($code) ?></span>
          <?php endif; ?>
          <span class="vaccine-dose-pill"><?= $doses ?> <?= $doses === 1 ? 'Dose' : 'Doses' ?></span>
        </div>

        <div class="vaccine-body">
          <div>
            <h3><?= e($v['vaccine_name']) ?></h3>
            <p class="vaccine-target">Protects against: <?= e($v['disease_prevented']) ?></p>
          </div>

          <div class="vaccine-facts">
            <div class="vaccine-fact">
              <strong>Importance</strong>
              <?= e($meta['importance'] ?? ($v['description'] ?? 'Essential childhood immunization.')) ?>
            </div>
            <div class="vaccine-fact">
              <strong>Immune Role</strong>
              <?= e($meta['role'] ?? 'Stimulates protective antibodies against target pathogens.') ?>
            </div>
            <div class="vaccine-fact">
              <strong>Key Components</strong>
              <?= e($meta['components'] ?? 'Standardized clinical antigen formulation and sterile stabilizer.') ?>
            </div>
          </div>

          <div class="vaccine-foot">
            <span>Route: <strong><?= e($v['route'] ?? 'Standard') ?></strong></span>
            <span>Mild effects: <?= e($v['side_effects'] ?? 'Minimal') ?></span>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
