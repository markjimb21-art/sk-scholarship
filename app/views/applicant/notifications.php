<h4 class="fw-bold mb-3">Notifications</h4>
<div class="list-group">
  <?php if (!$notifications): ?><div class="list-group-item text-center text-muted py-4">No notifications yet.</div><?php endif; ?>
  <?php foreach ($notifications as $n): ?>
    <div class="list-group-item <?= $n['is_read'] ? '' : 'bg-light' ?>">
      <div class="d-flex justify-content-between">
        <h6 class="mb-1"><i class="bi bi-info-circle text-<?= e($n['type']) ?> me-1"></i><?= e($n['title']) ?></h6>
        <small class="text-muted"><?= e(date('M d, g:i A', strtotime($n['created_at']))) ?></small>
      </div>
      <p class="mb-0 small"><?= e($n['message']) ?></p>
      <?php if ($n['link']): ?><a href="<?= e($n['link']) ?>" class="small">View details &rarr;</a><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>