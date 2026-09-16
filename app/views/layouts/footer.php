    </main>
    <footer class="app-footer small text-muted">
      &copy; <?= date('Y') ?> Sangguniang Kabataan · Barangay Estefania · All rights reserved.
    </footer>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $s): ?>
  <script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>