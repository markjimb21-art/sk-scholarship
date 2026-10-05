<?php
// Flash messages for every page: an icon plus the words; errors and warnings are announced (role="alert").
$flashIcons = ['success' => 'bi-check-circle-fill', 'danger' => 'bi-exclamation-octagon-fill', 'warning' => 'bi-exclamation-triangle-fill', 'info' => 'bi-info-circle-fill'];
foreach (get_flashes() as $f):
  $type = (string)$f['type']; ?>
  <div class="alert alert-<?= e($type) ?> d-flex align-items-start gap-2<?= !empty($flashDismissible) ? ' alert-dismissible fade show' : '' ?>"
       role="<?= in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status' ?>">
    <i class="bi <?= e($flashIcons[$type] ?? 'bi-info-circle-fill') ?> flex-shrink-0 mt-1" aria-hidden="true"></i>
    <div><?= e($f['message']) ?></div>
    <?php if (!empty($flashDismissible)): ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close message"></button><?php endif; ?>
  </div>
<?php endforeach; ?>
