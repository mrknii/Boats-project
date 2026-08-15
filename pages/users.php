<?php
/**
 * ---------------------------------------------------------------------
 *  User accounts — administrator only
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_admin();

$me = current_user();

if (is_post()) {
    csrf_verify();

    $action = post('action');
    $id     = post_int('id');

    // ---------- Delete --------------------------------------------------
    if ($action === 'delete') {
        if ($id === (int) $me['id']) {
            flash('danger', 'You cannot delete the account you are signed in with.');
            redirect('pages/users.php');
        }
        $admins = (int) scalar("SELECT COUNT(*) FROM users WHERE role='admin'");
        $target = one('SELECT full_name, role FROM users WHERE id = ?', [$id]);

        if ($target && $target['role'] === 'admin' && $admins <= 1) {
            flash('danger', 'This is the last administrator account. Promote another user first.');
        } elseif ($target) {
            delete_row('users', $id);
            log_activity('users', 'delete', 'Deleted account ' . $target['full_name']);
            flash('success', $target['full_name'] . '\'s account was deleted.');
        }
        redirect('pages/users.php');
    }

    // ---------- Suspend / reactivate -------------------------------------
    if ($action === 'toggle') {
        if ($id === (int) $me['id']) {
            flash('danger', 'You cannot suspend your own account.');
            redirect('pages/users.php');
        }
        $target = one('SELECT full_name, status FROM users WHERE id = ?', [$id]);
        if ($target) {
            $newStatus = $target['status'] === 'active' ? 'suspended' : 'active';
            update('users', ['status' => $newStatus], $id);
            log_activity('users', 'update', label($newStatus) . ' account ' . $target['full_name']);
            flash('success', $target['full_name'] . '\'s account is now ' . $newStatus . '.');
        }
        redirect('pages/users.php');
    }

    // ---------- Reset password -------------------------------------------
    if ($action === 'password') {
        $password = (string) ($_POST['new_password'] ?? '');
        if (mb_strlen($password) < 6) {
            flash('danger', 'The new password must be at least 6 characters long.');
            redirect('pages/users.php');
        }
        $target = one('SELECT full_name FROM users WHERE id = ?', [$id]);
        if ($target) {
            update('users', ['password_hash' => password_hash($password, PASSWORD_BCRYPT)], $id);
            log_activity('users', 'update', 'Reset the password for ' . $target['full_name']);
            flash('success', 'The password for ' . $target['full_name'] . ' was reset.');
        }
        redirect('pages/users.php');
    }

    // ---------- Create or update ------------------------------------------
    $errors = validate([
        'full_name' => ['required' => true, 'min' => 3, 'max' => 120, 'label' => 'Full name'],
        'username'  => ['required' => true, 'min' => 3, 'max' => 60],
        'email'     => ['required' => true, 'email' => true],
        'role'      => ['required' => true, 'in' => ['admin', 'manager', 'worker']],
        'status'    => ['required' => true, 'in' => ['active', 'suspended']],
    ]);

    if (!$errors && !is_unique('users', 'username', post('username'), $id ?: null)) {
        $errors['username'] = 'That username is already taken.';
    }
    if (!$errors && !is_unique('users', 'email', post('email'), $id ?: null)) {
        $errors['email'] = 'That email address is already registered.';
    }
    if (!$errors && $id === 0 && mb_strlen((string) ($_POST['password'] ?? '')) < 6) {
        $errors['password'] = 'Set a password of at least 6 characters for the new account.';
    }
    // Never let an administrator demote or suspend themselves out of access
    if (!$errors && $id === (int) $me['id'] && post('role') !== 'admin') {
        $errors['role'] = 'You cannot remove your own administrator rights.';
    }

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'full_name' => post('full_name'),
            'username'  => post('username'),
            'email'     => post('email'),
            'role'      => post('role'),
            'phone'     => post_or_null('phone'),
            'status'    => post('status'),
        ];

        if ($id > 0) {
            update('users', $data, $id);
            log_activity('users', 'update', 'Updated account ' . $data['username']);
            flash('success', $data['full_name'] . '\'s account was updated.');
        } else {
            $data['password_hash'] = password_hash((string) $_POST['password'], PASSWORD_BCRYPT);
            insert('users', $data);
            log_activity('users', 'create', 'Created account ' . $data['username']);
            flash('success', 'The account for ' . $data['full_name'] . ' was created.');
        }
        redirect('pages/users.php');
    }
}

$users = all(
    'SELECT u.*,
            (SELECT COUNT(*) FROM activity_log a WHERE a.user_id = u.id) AS actions,
            (SELECT e.job_title FROM employees e WHERE e.user_id = u.id LIMIT 1) AS job_title
       FROM users u
      ORDER BY FIELD(u.role,"admin","manager","worker"), u.full_name'
);

$roleInfo = [
    'admin'   => ['tone' => 'danger',  'icon' => 'shield', 'desc' => 'Full control, including accounts and settings'],
    'manager' => ['tone' => 'info',    'icon' => 'staff',  'desc' => 'Runs the farm day to day, no user administration'],
    'worker'  => ['tone' => 'neutral', 'icon' => 'user',   'desc' => 'Records work, read only on money and staff'],
];

$counts = [
    'admin'   => (int) scalar("SELECT COUNT(*) FROM users WHERE role='admin'"),
    'manager' => (int) scalar("SELECT COUNT(*) FROM users WHERE role='manager'"),
    'worker'  => (int) scalar("SELECT COUNT(*) FROM users WHERE role='worker'"),
];
$suspended = (int) scalar("SELECT COUNT(*) FROM users WHERE status='suspended'");

$avatarTones = ['', '--gold', '--blue', '--purple', '--teal', '--red'];

$pageTitle    = 'User Accounts';
$pageSubtitle = 'Who can sign in, and what they are allowed to do.';
$activeNav    = 'users';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'User Accounts' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('shield', 24, 'c-brand') ?> User Accounts</h1>
    <p><?= count($users) ?> accounts · <?= $suspended ?> suspended</p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--primary" data-modal="userModal" data-primary-action
            data-fill='{"id":"","full_name":"","username":"","email":"","phone":"","password":""}'
            data-fill-text='{"title":"Create an Account","pwdnote":"Set an initial password for the new account."}'>
      <?= icon('plus', 17) ?> Add User
    </button>
  </div>
</div>

<!-- Role explainer -->
<section class="grid grid--3 mb-18">
  <?php foreach ($roleInfo as $role => $info): ?>
    <article class="stat stat--<?= $role === 'admin' ? 'red' : ($role === 'manager' ? 'blue' : 'green') ?> reveal">
      <div class="stat__top">
        <span class="stat__icon"><?= icon($info['icon'], 22) ?></span>
        <div>
          <div class="stat__label"><?= e(label($role)) ?>s</div>
          <div class="stat__value" data-count="<?= $counts[$role] ?>">0</div>
        </div>
      </div>
      <div class="stat__foot"><span><?= e($info['desc']) ?></span></div>
    </article>
  <?php endforeach; ?>
</section>

<section class="card reveal" data-delay="140">
  <div class="card__head">
    <h3><?= icon('staff', 18) ?> All Accounts</h3>
    <div class="card__actions">
      <div class="field-inline">
        <?= icon('search', 15) ?>
        <input type="text" data-filter-table="#usersTable" placeholder="Filter accounts…" style="height:33px">
      </div>
    </div>
  </div>

  <div class="tablewrap">
    <table class="table" id="usersTable">
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Contact</th>
          <th>Last Sign In</th>
          <th class="num">Actions Logged</th>
          <th>Status</th>
          <th class="actions">Manage</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $index => $u): ?>
          <?php $isSelf = (int) $u['id'] === (int) $me['id']; ?>
          <tr>
            <td>
              <div class="cellmain">
                <span class="avatar avatar<?= $avatarTones[$index % count($avatarTones)] ?>">
                  <?= e(initials($u['full_name'])) ?>
                </span>
                <span class="cellmain__text">
                  <span class="cellmain__title">
                    <?= e($u['full_name']) ?>
                    <?php if ($isSelf): ?>
                      <span class="badge badge--info" style="margin-left:5px">You</span>
                    <?php endif; ?>
                  </span>
                  <span class="cellmain__sub">
                    @<?= e($u['username']) ?>
                    <?php if ($u['job_title']): ?> · <?= e($u['job_title']) ?><?php endif; ?>
                  </span>
                </span>
              </div>
            </td>
            <td>
              <span class="badge badge--<?= $roleInfo[$u['role']]['tone'] ?>">
                <i class="badge__dot"></i><?= e(label($u['role'])) ?>
              </span>
            </td>
            <td class="small soft">
              <span style="word-break:break-all"><?= e($u['email']) ?></span>
              <?php if ($u['phone']): ?><div class="tiny muted"><?= e($u['phone']) ?></div><?php endif; ?>
            </td>
            <td class="small nowrap">
              <?= $u['last_login'] ? e(time_ago($u['last_login'])) : '<span class="muted">Never</span>' ?>
            </td>
            <td class="num tnum"><?= (int) $u['actions'] ?></td>
            <td><?= badge($u['status']) ?></td>
            <td class="actions">
              <div>
                <button class="rowbtn" title="Reset password"
                        data-modal="pwdModal"
                        data-fill='<?= e(json_encode(['id' => $u['id']])) ?>'
                        data-fill-text='<?= e(json_encode(['name' => $u['full_name']])) ?>'>
                  <?= icon('lock', 16) ?>
                </button>
                <button class="rowbtn" title="Edit"
                        data-modal="userModal"
                        data-fill='<?= e(json_encode([
                            'id'        => $u['id'],
                            'full_name' => $u['full_name'],
                            'username'  => $u['username'],
                            'email'     => $u['email'],
                            'phone'     => $u['phone'],
                            'role'      => $u['role'],
                            'status'    => $u['status'],
                        ])) ?>'
                        data-fill-text='<?= e(json_encode([
                            'title'   => 'Edit ' . $u['full_name'],
                            'pwdnote' => 'Leave the password blank to keep the current one.',
                        ])) ?>'>
                  <?= icon('edit', 16) ?>
                </button>
                <?php if (!$isSelf): ?>
                  <button class="rowbtn" title="<?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?>"
                          data-modal="toggleModal"
                          data-fill='<?= e(json_encode(['id' => $u['id']])) ?>'
                          data-fill-text='<?= e(json_encode([
                              'name'   => $u['full_name'],
                              'action' => $u['status'] === 'active' ? 'suspend' : 'reactivate',
                          ])) ?>'>
                    <?= icon($u['status'] === 'active' ? 'close' : 'check', 16) ?>
                  </button>
                  <button class="rowbtn rowbtn--danger" title="Delete"
                          data-modal="deleteModal"
                          data-fill='<?= e(json_encode(['id' => $u['id']])) ?>'
                          data-fill-text='<?= e(json_encode(['name' => $u['full_name']])) ?>'>
                    <?= icon('trash', 16) ?>
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Permission matrix, useful documentation for the project report -->
<section class="card mt-18 reveal" data-delay="200">
  <div class="card__head">
    <h3><?= icon('lock', 18) ?> Permission Matrix</h3>
    <span class="card__sub">What each role is allowed to do</span>
  </div>
  <div class="tablewrap">
    <table class="table">
      <thead>
        <tr>
          <th>Capability</th>
          <th class="text-c">Administrator</th>
          <th class="text-c">Manager</th>
          <th class="text-c">Worker</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $matrixRows = [
            'View the dashboard'            => [true, true, true],
            'Add and edit livestock'        => [true, true, false],
            'Log health and production'     => [true, true, true],
            'Manage crops and fields'       => [true, true, false],
            'Record harvests'               => [true, true, true],
            'Manage inventory and stock'    => [true, true, false],
            'Manage suppliers'              => [true, true, false],
            'Create and assign tasks'       => [true, true, false],
            'Update task status'            => [true, true, true],
            'View and record finance'       => [true, true, false],
            'View employee records'         => [true, true, false],
            'Add and edit employees'        => [true, false, false],
            'View reports'                  => [true, true, true],
            'Manage user accounts'          => [true, false, false],
            'Change farm settings'          => [true, false, false],
        ];
        ?>
        <?php foreach ($matrixRows as $capability => $allowed): ?>
          <tr>
            <td class="small"><?= e($capability) ?></td>
            <?php foreach ($allowed as $ok): ?>
              <td class="text-c">
                <?= $ok
                    ? '<span class="c-success">' . icon('success', 17) . '</span>'
                    : '<span class="muted">' . icon('close', 17) . '</span>' ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- ===================== MODALS ===================== -->
<div class="modal" id="userModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('shield', 19) ?> <span data-text="title">Create an Account</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field field--icon">
          <label for="full_name">Full name <span class="req">*</span></label>
          <span class="field__icon"><?= icon('user', 17) ?></span>
          <input class="input" type="text" id="full_name" name="full_name"
                 placeholder="e.g. Ama Boateng" required>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="username">Username <span class="req">*</span></label>
            <input class="input" type="text" id="username" name="username" placeholder="amab" required>
          </div>
          <div class="field field--icon">
            <label for="phone">Phone</label>
            <span class="field__icon"><?= icon('phone', 17) ?></span>
            <input class="input" type="text" id="phone" name="phone" placeholder="+233 …">
          </div>
        </div>

        <div class="field field--icon">
          <label for="email">Email <span class="req">*</span></label>
          <span class="field__icon"><?= icon('mail', 17) ?></span>
          <input class="input" type="email" id="email" name="email"
                 placeholder="name@example.com" required>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="role">Role <span class="req">*</span></label>
            <select class="select" id="role" name="role" required>
              <option value="worker">Worker</option>
              <option value="manager">Manager</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
          <div class="field">
            <label for="status">Status <span class="req">*</span></label>
            <select class="select" id="status" name="status" required>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input class="input" type="password" id="password" name="password"
                 placeholder="At least 6 characters" autocomplete="new-password">
          <span class="field__hint" data-text="pwdnote">Set an initial password for the new account.</span>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Account</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="pwdModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('lock', 19) ?> Reset Password</h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <p class="small soft mb-14">
          Setting a new password for <strong data-text="name"></strong>.
          They will need to use it the next time they sign in.
        </p>
        <div class="field">
          <label for="new_password">New password <span class="req">*</span></label>
          <input class="input" type="password" id="new_password" name="new_password"
                 placeholder="At least 6 characters" required autocomplete="new-password">
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('lock', 17) ?> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="toggleModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="id" value="">
      <div class="modal__body text-c">
        <span class="confirm-art" style="color:var(--c-warning);background:color-mix(in srgb,var(--c-warning) 10%,transparent);border-color:color-mix(in srgb,var(--c-warning) 22%,transparent)">
          <?= icon('warning', 26) ?>
        </span>
        <h3>Change account access?</h3>
        <p class="soft small mt-8">
          You are about to <strong data-text="action"></strong> the account belonging to
          <strong data-text="name"></strong>.
        </p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--gold"><?= icon('check', 17) ?> Confirm</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="deleteModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="">
      <div class="modal__body text-c">
        <span class="confirm-art"><?= icon('trash', 26) ?></span>
        <h3>Delete this account?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> will lose access immediately. Records they
          created are kept, but are no longer attributed to a user.
        </p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Delete</button>
      </div>
    </form>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
