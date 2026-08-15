<?php
/**
 * ---------------------------------------------------------------------
 *  My profile — any signed in user can manage their own account
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$me   = current_user();
$user = one('SELECT * FROM users WHERE id = ?', [$me['id']]);

if (!$user) {
    logout();
    redirect('login.php');
}

if (is_post()) {
    csrf_verify();

    // ---------- Update the profile details -----------------------------
    if (post('section') === 'details') {
        $errors = validate([
            'full_name' => ['required' => true, 'min' => 3, 'max' => 120, 'label' => 'Full name'],
            'email'     => ['required' => true, 'email' => true],
        ]);

        if (!$errors && !is_unique('users', 'email', post('email'), (int) $user['id'])) {
            $errors['email'] = 'That email address belongs to another account.';
        }

        if ($errors) {
            flash('danger', reset($errors));
        } else {
            update('users', [
                'full_name' => post('full_name'),
                'email'     => post('email'),
                'phone'     => post_or_null('phone'),
            ], (int) $user['id']);

            // Keep the session copy in step with the database
            $_SESSION['user']['full_name'] = post('full_name');
            $_SESSION['user']['email']     = post('email');

            log_activity('users', 'update', 'Updated their own profile');
            flash('success', 'Your profile was updated.');
        }
        redirect('pages/profile.php');
    }

    // ---------- Change the password ------------------------------------
    if (post('section') === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            flash('danger', 'Your current password is not correct.');
        } elseif (mb_strlen($new) < 6) {
            flash('danger', 'The new password must be at least 6 characters long.');
        } elseif ($new !== $confirm) {
            flash('danger', 'The two new passwords do not match.');
        } else {
            update('users', ['password_hash' => password_hash($new, PASSWORD_BCRYPT)], (int) $user['id']);
            log_activity('users', 'update', 'Changed their own password');
            flash('success', 'Your password was changed.');
        }
        redirect('pages/profile.php');
    }
}

// --- Personal statistics ----------------------------------------------
$myActions = (int) scalar('SELECT COUNT(*) FROM activity_log WHERE user_id = ?', [$user['id']]);
$myRecent  = all(
    'SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 8',
    [$user['id']]
);
$employee = one('SELECT * FROM employees WHERE user_id = ? LIMIT 1', [$user['id']]);

$myTasks = $employee ? all(
    "SELECT * FROM tasks WHERE assigned_to = ? AND status IN ('pending','in_progress')
      ORDER BY (due_date IS NULL), due_date ASC LIMIT 6",
    [$employee['id']]
) : [];

$myTasksDone = $employee
    ? (int) scalar("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status='completed'", [$employee['id']])
    : 0;

$roleDescriptions = [
    'admin'   => 'Full access to every module, including user accounts and system settings.',
    'manager' => 'Runs the farm day to day. No access to user administration.',
    'worker'  => 'Records daily work. Read only on finance and staff records.',
];

$pageTitle    = 'My Profile';
$pageSubtitle = 'Your account details and recent activity.';
$activeNav    = '';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'My Profile' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<section class="grid grid--1-2">

  <!-- ---------------- Identity card ---------------- -->
  <div class="grid" style="gap:18px;align-content:start">
    <article class="card reveal">
      <div class="card__body text-c">
        <span class="avatar avatar--xl" style="margin:0 auto 14px">
          <?= e(initials($user['full_name'])) ?>
        </span>
        <h2 style="font-size:1.15rem"><?= e($user['full_name']) ?></h2>
        <p class="small muted">@<?= e($user['username']) ?></p>

        <div class="flex items-c gap-8 mt-14" style="justify-content:center">
          <span class="badge badge--<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'info' : 'neutral') ?>">
            <i class="badge__dot"></i><?= e(label($user['role'])) ?>
          </span>
          <?= badge($user['status']) ?>
        </div>

        <p class="tiny muted mt-14"><?= e($roleDescriptions[$user['role']] ?? '') ?></p>
      </div>

      <div class="card__foot">
        <div class="kv" style="background:transparent">
          <div class="kv__row" style="background:transparent;padding:8px 0">
            <span class="kv__key"><?= icon('mail', 14) ?> Email</span>
            <span class="kv__val small" style="word-break:break-all"><?= e($user['email']) ?></span>
          </div>
          <div class="kv__row" style="background:transparent;padding:8px 0">
            <span class="kv__key"><?= icon('phone', 14) ?> Phone</span>
            <span class="kv__val small"><?= e($user['phone'] ?: '—') ?></span>
          </div>
          <div class="kv__row" style="background:transparent;padding:8px 0">
            <span class="kv__key"><?= icon('calendar', 14) ?> Joined</span>
            <span class="kv__val small"><?= fdate($user['created_at']) ?></span>
          </div>
          <div class="kv__row" style="background:transparent;padding:8px 0">
            <span class="kv__key"><?= icon('clock', 14) ?> Last sign in</span>
            <span class="kv__val small"><?= $user['last_login'] ? e(time_ago($user['last_login'])) : 'First session' ?></span>
          </div>
        </div>
      </div>
    </article>

    <?php if ($employee): ?>
      <article class="card reveal" data-delay="60">
        <div class="card__head"><h3><?= icon('staff', 18) ?> Employment</h3></div>
        <div class="card__body">
          <div class="kv">
            <div class="kv__row">
              <span class="kv__key">Job title</span>
              <span class="kv__val"><?= e($employee['job_title']) ?></span>
            </div>
            <div class="kv__row">
              <span class="kv__key">Department</span>
              <span class="kv__val"><?= e(label($employee['department'])) ?></span>
            </div>
            <div class="kv__row">
              <span class="kv__key">Hired</span>
              <span class="kv__val"><?= fdate($employee['hire_date']) ?></span>
            </div>
            <div class="kv__row">
              <span class="kv__key">Service</span>
              <span class="kv__val"><?= e(age_from($employee['hire_date'])) ?></span>
            </div>
          </div>
        </div>
      </article>
    <?php endif; ?>

    <article class="card reveal" data-delay="100">
      <div class="card__head"><h3><?= icon('activity', 18) ?> Your Numbers</h3></div>
      <div class="card__body">
        <div class="metric-row">
          <span class="tile tile--sm"><?= icon('activity', 15) ?></span>
          <span class="metric-row__text"><span class="metric-row__name">Actions logged</span></span>
          <span class="metric-row__val"><?= number_format($myActions) ?></span>
        </div>
        <div class="metric-row">
          <span class="tile tile--sm tile--gold"><?= icon('tasks', 15) ?></span>
          <span class="metric-row__text"><span class="metric-row__name">Open tasks</span></span>
          <span class="metric-row__val"><?= count($myTasks) ?></span>
        </div>
        <div class="metric-row">
          <span class="tile tile--sm tile--teal"><?= icon('success', 15) ?></span>
          <span class="metric-row__text"><span class="metric-row__name">Tasks completed</span></span>
          <span class="metric-row__val"><?= number_format($myTasksDone) ?></span>
        </div>
      </div>
    </article>
  </div>

  <!-- ---------------- Forms and activity ---------------- -->
  <div class="grid" style="gap:18px;align-content:start">

    <article class="card reveal" data-delay="140">
      <div class="card__head">
        <h3><?= icon('user', 18) ?> Account Details</h3>
        <span class="card__sub">Keep your contact information current</span>
      </div>
      <form method="post" class="form" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="details">

        <div class="card__body">
          <div class="form__row">
            <div class="field field--icon">
              <label for="full_name">Full name <span class="req">*</span></label>
              <span class="field__icon"><?= icon('user', 17) ?></span>
              <input class="input" type="text" id="full_name" name="full_name"
                     value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="field">
              <label for="username_display">Username</label>
              <input class="input" type="text" id="username_display"
                     value="<?= e($user['username']) ?>" disabled>
              <span class="field__hint">Only an administrator can change your username.</span>
            </div>
          </div>

          <div class="form__row">
            <div class="field field--icon">
              <label for="email">Email <span class="req">*</span></label>
              <span class="field__icon"><?= icon('mail', 17) ?></span>
              <input class="input" type="email" id="email" name="email"
                     value="<?= e($user['email']) ?>" required>
            </div>
            <div class="field field--icon">
              <label for="phone">Phone</label>
              <span class="field__icon"><?= icon('phone', 17) ?></span>
              <input class="input" type="text" id="phone" name="phone"
                     value="<?= e($user['phone']) ?>" placeholder="+233 …">
            </div>
          </div>
        </div>

        <div class="card__foot text-r">
          <button class="btn btn--primary" type="submit"><?= icon('save', 17) ?> Save Details</button>
        </div>
      </form>
    </article>

    <article class="card reveal" data-delay="180">
      <div class="card__head">
        <h3><?= icon('lock', 18) ?> Change Password</h3>
        <span class="card__sub">Use something only you would know</span>
      </div>
      <form method="post" class="form" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="password">

        <div class="card__body">
          <div class="field">
            <label for="current_password">Current password <span class="req">*</span></label>
            <div class="pwd-wrap">
              <input class="input" type="password" id="current_password" name="current_password"
                     required autocomplete="current-password">
              <button type="button" class="pwd-toggle" data-pwd-toggle="current_password" aria-label="Show password">
                <?= icon('eye', 17) ?>
                <span class="hide"><?= icon('close', 17) ?></span>
              </button>
            </div>
          </div>

          <div class="form__row">
            <div class="field">
              <label for="new_password">New password <span class="req">*</span></label>
              <input class="input" type="password" id="new_password" name="new_password"
                     placeholder="At least 6 characters" required autocomplete="new-password">
            </div>
            <div class="field">
              <label for="confirm_password">Confirm new password <span class="req">*</span></label>
              <input class="input" type="password" id="confirm_password" name="confirm_password"
                     required autocomplete="new-password">
            </div>
          </div>

          <div class="alert alert--info">
            <?= icon('shield', 17) ?>
            <span>
              Passwords are stored as bcrypt hashes — even an administrator cannot read
              your password, only reset it.
            </span>
          </div>
        </div>

        <div class="card__foot text-r">
          <button class="btn btn--primary" type="submit"><?= icon('lock', 17) ?> Change Password</button>
        </div>
      </form>
    </article>

    <?php if ($myTasks): ?>
      <article class="card reveal" data-delay="220">
        <div class="card__head">
          <h3><?= icon('tasks', 18) ?> Your Open Tasks</h3>
          <div class="card__actions">
            <a class="btn btn--sm btn--ghost" href="<?= url('pages/tasks.php') ?>">
              Task board <?= icon('arrow-right', 15) ?>
            </a>
          </div>
        </div>
        <div class="card__body card__body--flush">
          <?php foreach ($myTasks as $t): ?>
            <?php $d = days_until($t['due_date']); ?>
            <div class="listrow">
              <span class="tile tile--sm tile--<?= $t['priority'] === 'urgent' ? 'red' : 'gold' ?>">
                <?= icon('tasks', 15) ?>
              </span>
              <span class="listrow__text">
                <span class="listrow__title"><?= e($t['title']) ?></span>
                <span class="listrow__sub">
                  <?= e(label($t['category'])) ?>
                  <?php if ($t['due_date']): ?>
                    · <span class="<?= $d < 0 ? 'c-danger bold' : '' ?>">
                        <?= $d < 0 ? abs($d) . 'd overdue' : ($d === 0 ? 'due today' : 'in ' . $d . 'd') ?>
                      </span>
                  <?php endif; ?>
                </span>
              </span>
              <?= badge($t['priority'], priority_tone($t['priority'])) ?>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endif; ?>

    <article class="card reveal" data-delay="260">
      <div class="card__head"><h3><?= icon('activity', 18) ?> Your Recent Activity</h3></div>
      <div class="card__body">
        <?php if (!$myRecent): ?>
          <p class="muted small text-c">Nothing recorded yet.</p>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($myRecent as $a): ?>
              <div class="timeline__item">
                <div class="timeline__title"><?= e($a['description'] ?: label($a['action'])) ?></div>
                <div class="timeline__meta">
                  <?= e(label($a['module'])) ?> · <?= e(time_ago($a['created_at'])) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </article>
  </div>
</section>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
