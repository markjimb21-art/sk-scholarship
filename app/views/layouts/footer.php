    </main>
    <footer class="app-footer">
      &copy; <?= date('Y') ?> <?= e(APP_NAME_SHORT) ?> · Sangguniang Kabataan · Barangay Estefania · All rights reserved.
    </footer>
  </div>
</div>

<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<!-- Chart.js served locally (copied from node_modules/chart.js/dist) so charts work without internet. -->
<script src="<?= e(asset('vendor/chart.js/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $s): ?>
  <script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
