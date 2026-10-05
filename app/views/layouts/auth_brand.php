<?php
// Logo block at the top of an auth card. $bothLogos (login): SK logo + Barangay seal side by side; otherwise the SK logo.
// $authHeading: the page's own heading (h1) under the logos; the system name sits above it.
$bothLogos = $bothLogos ?? false;
?>
<div class="brand-logos">
  <img src="<?= e(asset('img/sk-logo.png')) ?>" alt="SK Barangay Estefania logo" width="84" height="84">
  <?php if ($bothLogos): ?><img src="<?= e(asset('img/barangay-seal.png')) ?>" alt="Barangay Estefania seal" width="84" height="84"><?php endif; ?>
</div>
<p class="brand-title"><?= e(APP_NAME_SHORT) ?><span class="brand-title-sub">Educational Assistance for College Students</span></p>
<p class="brand-subtitle"><?= e(APP_SUBTITLE) ?></p>
<?php if (!empty($authHeading)): ?><h1 class="h3 text-center mb-3"><?= e($authHeading) ?></h1><?php endif; ?>
