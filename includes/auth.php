<?php
/**
 * ---------------------------------------------------------------------
 *  Authentication and role based access control
 * ---------------------------------------------------------------------
 *  Roles, from most to least privileged:
 *
 *    admin    — full access, including user accounts and settings
 *    manager  — day to day running of the farm, no user administration
 *    worker   — records their own work, read only on money and staff
 * ---------------------------------------------------------------------
 */

/** Attempt a login. Returns an error string, or null on success. */
function attempt_login(string $identifier, string $password): ?string
{
    $user = one(
        'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
        [$identifier, $identifier]
    );

    // Same message either way — never reveal which accounts exist.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'Those details did not match any account. Please check and try again.';
    }

    if ($user['status'] !== 'active') {
        return 'This account has been suspended. Please contact the administrator.';
    }

    start_session();
    session_regenerate_id(true);   // guards against session fixation

    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'full_name' => $user['full_name'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'avatar'    => $user['avatar'],
    ];
    $_SESSION['last_activity'] = time();

    update('users', ['last_login' => date('Y-m-d H:i:s')], (int) $user['id']);
    log_activity('auth', 'login', $user['full_name'] . ' signed in');

    return null;
}

function logout(): void
{
    start_session();
    if (isset($_SESSION['user'])) {
        log_activity('auth', 'logout', $_SESSION['user']['full_name'] . ' signed out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): string
{
    return current_user()['role'] ?? 'guest';
}

/** Does the current user hold at least the given role? */
function has_role(string $minimum): bool
{
    $rank = ['worker' => 1, 'manager' => 2, 'admin' => 3];
    return ($rank[user_role()] ?? 0) >= ($rank[$minimum] ?? 99);
}

function is_admin(): bool
{
    return user_role() === 'admin';
}

/**
 * Capability check. Keeping permissions in one table makes the rules easy
 * to read and easy to defend in a project write up.
 */
function can(string $capability): bool
{
    $role = user_role();

    $matrix = [
        'admin' => ['*'],
        'manager' => [
            'livestock.manage', 'health.manage', 'production.manage',
            'crops.manage', 'fields.manage', 'harvest.manage',
            'inventory.manage', 'suppliers.manage',
            'finance.manage', 'finance.view',
            'tasks.manage', 'employees.view', 'reports.view',
        ],
        'worker' => [
            'livestock.view', 'health.manage', 'production.manage',
            'crops.view', 'fields.view', 'harvest.manage',
            'inventory.view', 'tasks.own', 'reports.view',
        ],
    ];

    $allowed = $matrix[$role] ?? [];

    if (in_array('*', $allowed, true) || in_array($capability, $allowed, true)) {
        return true;
    }

    // "x.manage" implies "x.view"
    if (str_ends_with($capability, '.view')) {
        return in_array(substr($capability, 0, -5) . '.manage', $allowed, true);
    }

    return false;
}

/** Guard a whole page. Call this at the very top of every protected file. */
function require_login(): void
{
    start_session();

    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }

    // Idle timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logout();
        flash('info', 'Your session expired after a period of inactivity. Please sign in again.');
        redirect('login.php');
    }

    $_SESSION['last_activity'] = time();
}

/** Guard an action. Sends the user back with a message when not allowed. */
function require_capability(string $capability): void
{
    if (!can($capability)) {
        flash('danger', 'You do not have permission to perform that action.');
        redirect('pages/dashboard.php');
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('danger', 'Only an administrator can open that page.');
        redirect('pages/dashboard.php');
    }
}

/** Register a new account. Returns [errors, newUserId]. */
function register_user(array $input): array
{
    $errors = validate([
        'full_name' => ['required' => true, 'min' => 3, 'max' => 120, 'label' => 'Full name'],
        'username'  => ['required' => true, 'min' => 3, 'max' => 60],
        'email'     => ['required' => true, 'email' => true],
        'password'  => ['required' => true, 'min' => 6],
    ]);

    if (($input['password'] ?? '') !== ($input['confirm_password'] ?? '')) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    }
    if (empty($errors['username']) && !is_unique('users', 'username', $input['username'])) {
        $errors['username'] = 'That username is already taken.';
    }
    if (empty($errors['email']) && !is_unique('users', 'email', $input['email'])) {
        $errors['email'] = 'An account with that email already exists.';
    }
    if ($errors) {
        return [$errors, null];
    }

    // The very first account to be created becomes the administrator.
    $isFirstUser = (int) scalar('SELECT COUNT(*) FROM users') === 0;

    $id = insert('users', [
        'full_name'     => $input['full_name'],
        'username'      => $input['username'],
        'email'         => $input['email'],
        'password_hash' => password_hash($input['password'], PASSWORD_BCRYPT),
        'role'          => $isFirstUser ? 'admin' : 'worker',
        'phone'         => $input['phone'] ?: null,
        'status'        => 'active',
    ]);

    return [[], $id];
}
