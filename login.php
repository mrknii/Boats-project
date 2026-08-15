<?php
/**
 * Sign in screen.
 */
require_once __DIR__ . '/config/config.php';
start_session();

if (is_logged_in()) {
    redirect('pages/dashboard.php');
}

$error = null;

if (is_post()) {
    csrf_verify();

    $identifier = post('identifier');
    $password   = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Please enter both your username and your password.';
    } else {
        $error = attempt_login($identifier, $password);

        if ($error === null) {
            $target = $_SESSION['redirect_after_login'] ?? 'pages/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            flash('success', 'Welcome back, ' . current_user()['full_name'] . '.');
            redirect($target ?: 'pages/dashboard.php');
        }
    }
}

// Live figures for the showcase panel — a nice touch during a demo
try {
    $showAnimals = (int) scalar("SELECT COUNT(*) FROM livestock WHERE status NOT IN ('sold','deceased')");
    $showAcres   = (float) scalar('SELECT COALESCE(SUM(size_acres),0) FROM fields');
    $showCrops   = (int) scalar("SELECT COUNT(*) FROM crops WHERE status NOT IN ('harvested','failed')");
} catch (Throwable $e) {
    $showAnimals = $showCrops = 0;
    $showAcres   = 0.0;
}

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(setting('farm_name', APP_NAME)) ?></title>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="6" fill="#16874a"/><path d="M12 19v-7M12 12.5c0-3 2-5.4 5.5-5.9-.2 3.7-2.2 5.9-5.5 5.9zM12 15c-3 0-5-1.9-5.4-4.9 3.2.4 5 2.2 5.4 4.9z" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
) ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="<?= asset('css/auth.css') ?>?v=<?= APP_VERSION ?>">
<script>
  (function () {
    try {
      var saved = localStorage.getItem('ga-theme');
      if (saved) document.documentElement.setAttribute('data-theme', saved);
    } catch (e) {}
  })();
</script>
</head>
<body>

<div class="auth">

  <!-- ------------------------- Showcase ------------------------- -->
  <section class="auth__show">
    <div class="auth__rows"></div>

    <div class="auth__brand">
      <span class="auth__mark"><?= icon('crops', 24) ?></span>
      <span>
        <span class="auth__brandname"><?= e(setting('farm_name', APP_NAME)) ?></span>
        <span class="auth__brandsub">Farm Management</span>
      </span>
    </div>

    <div class="auth__pitch">
      <h2>Run the whole farm from <em>one screen</em>.</h2>
      <p>
        Livestock, crops, stores, staff and money — tracked together, updated
        as the work happens, and turned into the numbers you need at the end
        of the season.
      </p>

      <div class="auth__features">
        <div class="auth__feature">
          <span class="auth__feature-icon"><?= icon('livestock', 19) ?></span>
          <span>
            <strong>Livestock &amp; health</strong>
            <span>Tag every animal, log treatments, never miss a vaccination.</span>
          </span>
        </div>
        <div class="auth__feature">
          <span class="auth__feature-icon"><?= icon('crops', 19) ?></span>
          <span>
            <strong>Crops &amp; harvests</strong>
            <span>Plan the season by field and record what each one yields.</span>
          </span>
        </div>
        <div class="auth__feature">
          <span class="auth__feature-icon"><?= icon('finance', 19) ?></span>
          <span>
            <strong>Income &amp; expenses</strong>
            <span>See profit per month without opening a single spreadsheet.</span>
          </span>
        </div>
      </div>
    </div>

    <div class="auth__stats">
      <div class="auth__stat">
        <strong><?= number_format($showAnimals) ?></strong>
        <span>Animals</span>
      </div>
      <div class="auth__stat">
        <strong><?= qty($showAcres) ?></strong>
        <span>Acres</span>
      </div>
      <div class="auth__stat">
        <strong><?= number_format($showCrops) ?></strong>
        <span>Active crops</span>
      </div>
    </div>
  </section>

  <!-- --------------------------- Form --------------------------- -->
  <section class="auth__form">
    <button class="iconbtn auth__toggle" data-theme-toggle aria-label="Switch colour theme">
      <span data-theme-icon="light"><?= icon('moon', 19) ?></span>
      <span data-theme-icon="dark" class="hide"><?= icon('sun', 19) ?></span>
    </button>

    <div class="auth__card">
      <div class="auth__mobilebrand">
        <span class="auth__mark"><?= icon('crops', 22) ?></span>
        <span>
          <span class="auth__brandname" style="color:var(--text)"><?= e(setting('farm_name', APP_NAME)) ?></span>
          <span class="auth__brandsub">Farm Management</span>
        </span>
      </div>

      <h1 class="auth__title">Welcome back</h1>
      <p class="auth__lead">Sign in to continue to your farm dashboard.</p>

      <?php if ($error): ?>
        <div class="alert alert--danger mb-18">
          <?= icon('danger', 18) ?>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" class="form" data-guard>
        <?= csrf_field() ?>

        <div class="field field--icon">
          <label for="identifier">Username or email</label>
          <span class="field__icon"><?= icon('user', 17) ?></span>
          <input class="input" type="text" id="identifier" name="identifier"
                 value="<?= old('identifier') ?>" placeholder="e.g. admin"
                 autocomplete="username" required autofocus>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="pwd-wrap">
            <input class="input" type="password" id="password" name="password"
                   placeholder="Enter your password" autocomplete="current-password" required>
            <button type="button" class="pwd-toggle" data-pwd-toggle="password" aria-label="Show password">
              <?= icon('eye', 17) ?>
              <span class="hide"><?= icon('close', 17) ?></span>
            </button>
          </div>
        </div>

        <div class="auth__meta">
          <label class="switch">
            <input type="checkbox" name="remember" value="1">
            <span class="switch__track"></span>
            <span class="switch__label">Keep me signed in</span>
          </label>
        </div>

        <button class="btn btn--primary btn--lg btn--block" type="submit">
          <?= icon('login', 18) ?> Sign in
        </button>
      </form>

      <div class="demo">
        <div class="demo__head"><?= icon('sparkle', 14) ?> Demonstration accounts</div>
        <div class="demo__grid">
          <button type="button" class="demo__row" data-demo="admin|password123">
            <span class="demo__role">Admin</span>
            <span class="demo__creds">admin / password123</span>
            <span class="demo__hint"><?= icon('arrow-right', 15) ?></span>
          </button>
          <button type="button" class="demo__row" data-demo="manager|password123">
            <span class="demo__role">Manager</span>
            <span class="demo__creds">manager / password123</span>
            <span class="demo__hint"><?= icon('arrow-right', 15) ?></span>
          </button>
          <button type="button" class="demo__row" data-demo="worker|password123">
            <span class="demo__role">Worker</span>
            <span class="demo__creds">worker / password123</span>
            <span class="demo__hint"><?= icon('arrow-right', 15) ?></span>
          </button>
        </div>
      </div>

      <p class="auth__foot">
        No account yet?
        <a class="auth__link" href="<?= url('register.php') ?>">Create one</a>
      </p>
    </div>
  </section>
</div>

<script>
  window.__flashes = <?= json_encode(array_map(fn($f) => [
      'type' => $f['type'], 'title' => 'Notice', 'message' => $f['message'],
  ], $flashes)) ?>;
</script>
<script src="<?= asset('js/app.js') ?>?v=<?= APP_VERSION ?>"></script>
</body>
</html>
