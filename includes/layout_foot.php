<?php
/**
 * Application shell — closing half.
 * Any flash messages queued by PHP are handed to the JavaScript toast
 * system so they animate in rather than sitting statically on the page.
 */
$toastTitles = [
    'success' => 'Done',
    'danger'  => 'Something went wrong',
    'warning' => 'Please note',
    'info'    => 'Information',
];
?>
    </main>

    <footer class="content" style="padding-top:0">
      <div class="flex items-c justify-b wrap gap-10 small muted"
           style="border-top:1px solid var(--border);padding-top:16px">
        <span>
          &copy; <?= date('Y') ?> <?= e(setting('farm_name', APP_NAME)) ?> ·
          <?= e(setting('farm_location', '')) ?>
        </span>
        <span class="flex items-c gap-6">
          <?= icon('shield', 14) ?>
          <?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?> v<?= e(APP_VERSION) ?>
        </span>
      </div>
    </footer>
  </div><!-- /.main -->
</div><!-- /.app -->

<script>
  window.__flashes = <?= json_encode(array_map(fn($f) => [
      'type'    => $f['type'],
      'title'   => $toastTitles[$f['type']] ?? 'Notice',
      'message' => $f['message'],
  ], $flashes ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= asset('js/app.js') ?>?v=<?= APP_VERSION ?>"></script>
<script src="<?= asset('js/charts.js') ?>?v=<?= APP_VERSION ?>"></script>
<?php if (!empty($pageScript)): ?>
<script><?= $pageScript ?></script>
<?php endif; ?>
</body>
</html>
