<?php
/**
 * Create an account. New sign ups get the "worker" role, except for the
 * very first account created on a fresh installation, which becomes the
 * administrator.
 */
require_once __DIR__ . '/config/config.php';
start_session();

if (is_logged_in()) {
    redirect('pages/dashboard.php');
}

$errors = [];

if (is_post()) {
    csrf_verify();

    [$errors, $newId] = register_user([
        'full_name'        => post('full_name'),
        'username'         => post('username'),
        'email'            => post('email'),
        'phone'            => post('phone'),
        'password'         => (string) ($_POST['password'] ?? ''),
        'confirm_password' => (string) ($_POST['confirm_password'] ?? ''),
    ]);

    if (!$errors) {
        log_activity('auth', 'register', 'New account created: ' . post('username'));
        flash('success', 'Your account is ready. Please sign in.');
        redirect('login.php');
    }
}

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create account · <?= e(setting('farm_name', APP_NAME)) ?></title>
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
      <h2>Join the <em>farm team</em>.</h2>
      <p>
        Create your account to record daily work, log what you produce and
        keep the whole operation moving in step.
      </p>

      <div class="auth__features">
        <div class="auth__feature">
          <span class="auth__feature-icon"><?= icon('tasks', 19) ?></span>
          <span>
            <strong>Your tasks in one place</strong>
            <span>See what is assigned to you and mark it off as you go.</span>
          </span>
        </div>
        <div class="auth__feature">
          <span class="auth__feature-icon"><?= icon('production', 19) ?></span>
          <span>
            <strong>Record production daily</strong>
            <span>Milk, eggs and harvests captured at the point of work.</span>
          </span>
        </div>
        <div class="auth__feature">
          <span class="auth__feature-icon"><?= icon('shield', 19) ?></span>
          <span>
            <strong>Safe by design</strong>
            <span>Hashed passwords and role based access on every page.</span>
          </span>
        </div>
      </div>
    </div>

    <div class="auth__stats">
      <div class="auth__stat"><strong>3</strong><span>Access levels</span></div>
      <div class="auth__stat"><strong>13</strong><span>Modules</span></div>
      <div class="auth__stat"><strong>100%</strong><span>Offline ready</span></div>
    </div>
  </section>

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

      <h1 class="auth__title">Create your account</h1>
      <p class="auth__lead">It takes less than a minute to get started.</p>

      <?php if ($errors): ?>
        <div class="alert alert--danger mb-18">
          <?= icon('danger', 18) ?>
          <span>Please correct the highlighted fields and try again.</span>
        </div>
      <?php endif; ?>

      <form method="post" class="form" data-guard>
        <?= csrf_field() ?>

        <div class="field field--icon">
          <label for="full_name">Full name <span class="req">*</span></label>
          <span class="field__icon"><?= icon('user', 17) ?></span>
          <input class="input <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                 type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>"
                 placeholder="e.g. Ama Boateng" required>
          <?php if (isset($errors['full_name'])): ?>
            <span class="field__error"><?= icon('warning', 13) ?><?= e($errors['full_name']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="username">Username <span class="req">*</span></label>
            <input class="input <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                   type="text" id="username" name="username" value="<?= old('username') ?>"
                   placeholder="amab" autocomplete="username" required>
            <?php if (isset($errors['username'])): ?>
              <span class="field__error"><?= icon('warning', 13) ?><?= e($errors['username']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="phone">Phone</label>
            <input class="input" type="text" id="phone" name="phone" value="<?= old('phone') ?>"
                   placeholder="+233 …">
          </div>
        </div>

        <div class="field field--icon">
          <label for="email">Email address <span class="req">*</span></label>
          <span class="field__icon"><?= icon('mail', 17) ?></span>
          <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                 type="email" id="email" name="email" value="<?= old('email') ?>"
                 placeholder="you@example.com" autocomplete="email" required>
          <?php if (isset($errors['email'])): ?>
            <span class="field__error"><?= icon('warning', 13) ?><?= e($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="password">Password <span class="req">*</span></label>
            <div class="pwd-wrap">
              <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                     type="password" id="password" name="password"
                     placeholder="At least 6 characters" autocomplete="new-password" required>
              <button type="button" class="pwd-toggle" data-pwd-toggle="password" aria-label="Show password">
                <?= icon('eye', 17) ?>
                <span class="hide"><?= icon('close', 17) ?></span>
              </button>
            </div>
            <?php if (isset($errors['password'])): ?>
              <span class="field__error"><?= icon('warning', 13) ?><?= e($errors['password']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="confirm_password">Confirm <span class="req">*</span></label>
            <input class="input <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                   type="password" id="confirm_password" name="confirm_password"
                   placeholder="Repeat the password" autocomplete="new-password" required>
            <?php if (isset($errors['confirm_password'])): ?>
              <span class="field__error"><?= icon('warning', 13) ?><?= e($errors['confirm_password']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <button class="btn btn--primary btn--lg btn--block" type="submit">
          <?= icon('success', 18) ?> Create account
        </button>
      </form>

      <p class="auth__foot">
        Already registered?
        <a class="auth__link" href="<?= url('login.php') ?>">Sign in instead</a>
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
