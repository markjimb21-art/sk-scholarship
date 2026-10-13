</main>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $s): ?>
  <script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
