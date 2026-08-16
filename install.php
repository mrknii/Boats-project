<?php
/**
 * ---------------------------------------------------------------------
 *  Web installer
 * ---------------------------------------------------------------------
 *  Creates the database and loads database/farm_db.sql, so the project
 *  can be set up without opening phpMyAdmin.
 *
 *  Visit  http://localhost/farm-management-system/install.php
 *
 *  SECURITY: delete this file once the system is installed.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';

$step    = $_GET['step'] ?? 'check';
$results = [];
$fatal   = null;

// ---------------------------------------------------------------------
//  Environment checks
// ---------------------------------------------------------------------
$sqlFile = ROOT_PATH . '/database/farm_db.sql';

$checks = [
    [
        'label'  => 'PHP version 8.0 or newer',
        'ok'     => version_compare(PHP_VERSION, '8.0.0', '>='),
        'detail' => 'Running PHP ' . PHP_VERSION,
    ],
    [
        'label'  => 'PDO MySQL extension enabled',
        'ok'     => extension_loaded('pdo_mysql'),
        'detail' => extension_loaded('pdo_mysql')
                    ? 'pdo_mysql is available'
                    : 'Enable extension=pdo_mysql in php.ini',
    ],
    [
        'label'  => 'SQL file present',
        'ok'     => is_readable($sqlFile),
        'detail' => is_readable($sqlFile)
                    ? 'database/farm_db.sql found (' . round(filesize($sqlFile) / 1024) . ' KB)'
                    : 'database/farm_db.sql is missing',
    ],
    [
        'label'  => 'Uploads folder writable',
        'ok'     => is_writable(UPLOAD_PATH) || @mkdir(UPLOAD_PATH, 0777, true),
        'detail' => 'uploads/ is used for optional images',
    ],
];

// Can we reach the MySQL server at all?
$serverUp = false;
$serverMsg = '';
try {
    $probe = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $serverUp  = true;
    $serverMsg = 'Connected to MySQL at ' . DB_HOST . ':' . DB_PORT
               . ' (' . $probe->getAttribute(PDO::ATTR_SERVER_VERSION) . ')';
} catch (PDOException $e) {
    $serverMsg = $e->getMessage();
}

$checks[] = [
    'label'  => 'MySQL server reachable',
    'ok'     => $serverUp,
    'detail' => $serverUp ? $serverMsg : 'Start MySQL in the XAMPP control panel. ' . $serverMsg,
];

$allOk = !in_array(false, array_column($checks, 'ok'), true);

// ---------------------------------------------------------------------
//  Run the installation
// ---------------------------------------------------------------------
if ($step === 'run' && $allOk) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException('The SQL file could not be read.');
        }

        // Strip whole-line "--" comments first. Doing this before splitting
        // matters: a statement preceded by a comment block would otherwise
        // look like a comment and be skipped, taking CREATE DATABASE with it.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        // Execute the dump, splitting on semicolons at the end of a line.
        $statements = preg_split('/;\s*[\r\n]+/', $sql);
        $executed   = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
            $executed++;
        }

        $results[] = 'Executed ' . $executed . ' SQL statements.';

        // Verify by counting a few tables
        $pdo->exec('USE `' . DB_NAME . '`');
        foreach (['users', 'livestock', 'crops', 'inventory_items', 'transactions', 'tasks'] as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $results[] = 'Table ' . $table . ': ' . $count . ' rows.';
        }

        $step = 'done';
    } catch (Throwable $e) {
        $fatal = $e->getMessage();
        $step  = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · <?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
  body { display: grid; place-items: center; padding: 34px 18px; }
  .install { width: 100%; max-width: 700px; }
  .install__logo {
    width: 58px; height: 58px; display: grid; place-items: center;
    border-radius: 17px; color: #fff; margin: 0 auto 16px;
    background: linear-gradient(140deg, var(--brand-400), var(--brand-600));
    box-shadow: var(--sh-brand), inset 0 1px 0 rgba(255,255,255,.3);
  }
  .check {
    display: flex; align-items: center; gap: 13px;
    padding: 13px 16px; border-bottom: 1px solid var(--border);
  }
  .check:last-child { border-bottom: 0; }
  .check__mark {
    width: 30px; height: 30px; flex: none; display: grid; place-items: center;
    border-radius: 9px;
  }
  .check__mark--ok   { color: var(--c-success); background: color-mix(in srgb, var(--c-success) 12%, transparent); }
  .check__mark--bad  { color: var(--c-danger);  background: color-mix(in srgb, var(--c-danger) 12%, transparent); }
  .check__text { flex: 1; min-width: 0; }
  .check__label { font-size: .9rem; font-weight: 600; }
  .check__detail { font-size: .78rem; color: var(--text-3); word-break: break-word; }
</style>
</head>
<body>

<div class="install">
  <div class="text-c mb-18">
    <span class="install__logo"><?= icon('crops', 27) ?></span>
    <h1><?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?></h1>
    <p class="soft small">Setup wizard · version <?= e(APP_VERSION) ?></p>
  </div>

  <?php if ($step === 'done'): ?>

    <div class="card">
      <div class="card__body text-c">
        <span class="confirm-art" style="color:var(--c-success);background:color-mix(in srgb,var(--c-success) 10%,transparent);border-color:color-mix(in srgb,var(--c-success) 22%,transparent)">
          <?= icon('success', 28) ?>
        </span>
        <h2>Installation complete</h2>
        <p class="soft small mt-8">
          The database <code><?= e(DB_NAME) ?></code> was created and filled with
          demonstration data.
        </p>
      </div>

      <div class="card__body" style="padding-top:0">
        <div class="alert alert--success mb-14">
          <?= icon('success', 17) ?>
          <div>
            <?php foreach ($results as $line): ?>
              <div class="small"><?= e($line) ?></div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="alert alert--warning">
          <?= icon('warning', 17) ?>
          <span>
            Now <strong>delete install.php</strong> from the project folder so nobody
            can rebuild the database and wipe your data.
          </span>
        </div>

        <div class="divider"></div>

        <h4 class="mb-8">Sign in with any of these accounts</h4>
        <div class="kv">
          <div class="kv__row"><span class="kv__key">Administrator</span><span class="kv__val mono">admin / password123</span></div>
          <div class="kv__row"><span class="kv__key">Manager</span><span class="kv__val mono">manager / password123</span></div>
          <div class="kv__row"><span class="kv__key">Worker</span><span class="kv__val mono">worker / password123</span></div>
        </div>
      </div>

      <div class="card__foot text-r">
        <a class="btn btn--primary btn--lg" href="<?= url('login.php') ?>">
          <?= icon('login', 18) ?> Go to the sign in page
        </a>
      </div>
    </div>

  <?php elseif ($step === 'error'): ?>

    <div class="card">
      <div class="card__body text-c">
        <span class="confirm-art"><?= icon('danger', 28) ?></span>
        <h2>Installation failed</h2>
        <p class="soft small mt-8">The database could not be prepared.</p>
      </div>
      <div class="card__body" style="padding-top:0">
        <div class="alert alert--danger">
          <?= icon('danger', 17) ?>
          <span class="mono" style="font-size:.78rem"><?= e($fatal ?? 'Unknown error') ?></span>
        </div>
        <p class="small soft mt-14">
          The usual cause is that the MySQL user in <code>config/config.php</code> lacks
          permission to create databases. You can also import
          <code>database/farm_db.sql</code> manually through phpMyAdmin.
        </p>
      </div>
      <div class="card__foot text-r">
        <a class="btn btn--ghost" href="<?= url('install.php') ?>"><?= icon('refresh', 17) ?> Try again</a>
      </div>
    </div>

  <?php else: ?>

    <div class="card mb-18">
      <div class="card__head">
        <h3><?= icon('shield', 18) ?> Environment Checks</h3>
        <div class="card__actions">
          <span class="badge badge--<?= $allOk ? 'success' : 'danger' ?>">
            <i class="badge__dot"></i><?= $allOk ? 'Ready' : 'Not ready' ?>
          </span>
        </div>
      </div>
      <div class="card__body card__body--flush">
        <?php foreach ($checks as $check): ?>
          <div class="check">
            <span class="check__mark check__mark--<?= $check['ok'] ? 'ok' : 'bad' ?>">
              <?= icon($check['ok'] ? 'check' : 'close', 16) ?>
            </span>
            <span class="check__text">
              <span class="check__label"><?= e($check['label']) ?></span>
              <span class="check__detail"><?= e($check['detail']) ?></span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h3><?= icon('inventory', 18) ?> What the installer will do</h3></div>
      <div class="card__body">
        <ul class="grid" style="gap:10px">
          <li class="flex items-c gap-10 small">
            <?= icon('arrow-right', 15, 'c-brand') ?>
            Create the database <code><?= e(DB_NAME) ?></code> if it does not exist.
          </li>
          <li class="flex items-c gap-10 small">
            <?= icon('arrow-right', 15, 'c-brand') ?>
            Create all 17 tables with their keys and relationships.
          </li>
          <li class="flex items-c gap-10 small">
            <?= icon('arrow-right', 15, 'c-brand') ?>
            Load demonstration data — livestock, crops, stock, staff and a year of accounts.
          </li>
          <li class="flex items-c gap-10 small">
            <?= icon('arrow-right', 15, 'c-brand') ?>
            Create three sign in accounts, one for each role.
          </li>
        </ul>

        <div class="alert alert--danger mt-18">
          <?= icon('warning', 17) ?>
          <span>
            If a database called <code><?= e(DB_NAME) ?></code> already exists, its tables
            will be <strong>dropped and rebuilt</strong>. Any data you have entered will be lost.
          </span>
        </div>
      </div>
      <div class="card__foot flex items-c justify-b wrap gap-10">
        <span class="small muted">Connecting as <code><?= e(DB_USER) ?>@<?= e(DB_HOST) ?></code></span>
        <?php if ($allOk): ?>
          <a class="btn btn--primary btn--lg" href="<?= url('install.php?step=run') ?>">
            <?= icon('sparkle', 18) ?> Install now
          </a>
        <?php else: ?>
          <a class="btn btn--ghost btn--lg" href="<?= url('install.php') ?>">
            <?= icon('refresh', 18) ?> Re-run checks
          </a>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
