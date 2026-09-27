    </main>
    <footer class="app-footer small text-muted">
      &copy; <?= date('Y') ?> Sangguniang Kabataan · Barangay Estefania · All rights reserved.
    </footer>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js served locally (copied from node_modules/chart.js/dist) so charts work without internet. -->
<script src="<?= asset('vendor/chart.js/chart.umd.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $s): ?>
  <script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>