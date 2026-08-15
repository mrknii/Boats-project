<?php
/**
 * ---------------------------------------------------------------------
 *  Application shell — opening half
 * ---------------------------------------------------------------------
 *  Set these before including this file:
 *    $pageTitle     string  shown in the topbar and the <title>
 *    $pageSubtitle  string  small line under the title (optional)
 *    $activeNav     string  key of the highlighted sidebar item
 *    $breadcrumbs   array   ['Label' => 'href', 'Current' => null]
 * ---------------------------------------------------------------------
 */

require_login();

$pageTitle    = $pageTitle    ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav    = $activeNav    ?? '';
$breadcrumbs  = $breadcrumbs  ?? [];
$me           = current_user();

// --- Live counters shown as badges in the sidebar ---------------------
$navCounts = [
    'tasks' => (int) scalar(
        "SELECT COUNT(*) FROM tasks WHERE status IN ('pending','in_progress')"
    ),
    'inventory' => (int) scalar(
        'SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level'
    ),
];

// --- Notification feed, assembled from real farm data -----------------
$notifications = [];

foreach (all(
    'SELECT item_name, quantity, unit, reorder_level FROM inventory_items
      WHERE quantity <= reorder_level ORDER BY (quantity - reorder_level) ASC LIMIT 4'
) as $hdrRow) {
    $notifications[] = [
        'icon'  => 'inventory',
        'tone'  => 'red',
        'title' => $hdrRow['item_name'] . ' is low',
        'sub'   => qty($hdrRow['quantity']) . ' ' . $hdrRow['unit'] . ' left · reorder at ' . qty($hdrRow['reorder_level']),
        'href'  => 'pages/inventory.php?stock=low',
    ];
}

foreach (all(
    "SELECT t.title, t.due_date FROM tasks t
      WHERE t.status IN ('pending','in_progress') AND t.due_date IS NOT NULL
        AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      ORDER BY t.due_date ASC LIMIT 4"
) as $hdrRow) {
    $hdrDays = days_until($hdrRow['due_date']);
    $notifications[] = [
        'icon'  => 'tasks',
        'tone'  => $hdrDays < 0 ? 'red' : 'gold',
        'title' => $hdrRow['title'],
        'sub'   => $hdrDays < 0
            ? abs($hdrDays) . ' day' . (abs($hdrDays) === 1 ? '' : 's') . ' overdue'
            : ($hdrDays === 0 ? 'Due today' : 'Due in ' . $hdrDays . ' days'),
        'href'  => 'pages/tasks.php',
    ];
}

foreach (all(
    'SELECT h.next_due_date, l.tag_number, h.record_type FROM health_records h
       JOIN livestock l ON l.id = h.livestock_id
      WHERE h.next_due_date IS NOT NULL
        AND h.next_due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      ORDER BY h.next_due_date ASC LIMIT 3'
) as $hdrRow) {
    $hdrDays = days_until($hdrRow['next_due_date']);
    $notifications[] = [
        'icon'  => 'health',
        'tone'  => $hdrDays < 0 ? 'red' : 'blue',
        'title' => label($hdrRow['record_type']) . ' due — ' . $hdrRow['tag_number'],
        'sub'   => $hdrDays < 0 ? 'Overdue since ' . fdate($hdrRow['next_due_date']) : 'Scheduled for ' . fdate($hdrRow['next_due_date']),
        'href'  => 'pages/health.php',
    ];
}

$notifCount = count($notifications);

// --- Sidebar definition ------------------------------------------------
$navSections = [
    'Overview' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => 'pages/dashboard.php', 'cap' => null],
    ],
    'Livestock' => [
        ['key' => 'livestock',  'label' => 'Animals',      'icon' => 'livestock',  'href' => 'pages/livestock.php',  'cap' => 'livestock.view'],
        ['key' => 'health',     'label' => 'Health Records','icon' => 'health',    'href' => 'pages/health.php',     'cap' => 'health.manage'],
        ['key' => 'production', 'label' => 'Production',   'icon' => 'production', 'href' => 'pages/production.php', 'cap' => 'production.manage'],
    ],
    'Crops' => [
        ['key' => 'crops',    'label' => 'Crops',    'icon' => 'crops',   'href' => 'pages/crops.php',    'cap' => 'crops.view'],
        ['key' => 'fields',   'label' => 'Fields',   'icon' => 'fields',  'href' => 'pages/fields.php',   'cap' => 'fields.view'],
        ['key' => 'harvests', 'label' => 'Harvests', 'icon' => 'harvest', 'href' => 'pages/harvests.php', 'cap' => 'harvest.manage'],
    ],
    'Operations' => [
        ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'inventory', 'href' => 'pages/inventory.php', 'cap' => 'inventory.view'],
        ['key' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'suppliers', 'href' => 'pages/suppliers.php', 'cap' => 'suppliers.manage'],
        ['key' => 'tasks',     'label' => 'Tasks',     'icon' => 'tasks',     'href' => 'pages/tasks.php',     'cap' => null],
    ],
    'Management' => [
        ['key' => 'finance',   'label' => 'Finance',   'icon' => 'finance', 'href' => 'pages/finance.php',   'cap' => 'finance.view'],
        ['key' => 'employees', 'label' => 'Employees', 'icon' => 'staff',   'href' => 'pages/employees.php', 'cap' => 'employees.view'],
        ['key' => 'reports',   'label' => 'Reports',   'icon' => 'reports', 'href' => 'pages/reports.php',   'cap' => 'reports.view'],
    ],
    'System' => [
        ['key' => 'users',    'label' => 'User Accounts', 'icon' => 'shield',   'href' => 'pages/users.php',    'cap' => 'users.manage'],
        ['key' => 'activity', 'label' => 'Activity Log',  'icon' => 'activity', 'href' => 'pages/activity.php', 'cap' => 'users.manage'],
        ['key' => 'settings', 'label' => 'Settings',      'icon' => 'settings', 'href' => 'pages/settings.php', 'cap' => 'users.manage'],
    ],
];

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e(setting('farm_name', APP_NAME)) ?> — <?= e(APP_TAGLINE) ?>">
<title><?= e($pageTitle) ?> · <?= e(setting('farm_name', APP_NAME)) ?></title>

<!-- Favicon drawn inline so the project ships with no binary assets -->
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="6" fill="#16874a"/><path d="M12 19v-7M12 12.5c0-3 2-5.4 5.5-5.9-.2 3.7-2.2 5.9-5.5 5.9zM12 15c-3 0-5-1.9-5.4-4.9 3.2.4 5 2.2 5.4 4.9z" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
) ?>">

<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= APP_VERSION ?>">

<script>
  /* Apply the saved theme before first paint to avoid a flash of light mode */
  (function () {
    try {
      var saved = localStorage.getItem('ga-theme');
      if (saved) document.documentElement.setAttribute('data-theme', saved);
    } catch (e) {}
  })();
</script>
</head>
<body>

<div class="app">
  <div class="scrim"></div>

  <!-- ============================ SIDEBAR ============================ -->
  <aside class="sidebar">
    <a class="brand" href="<?= url('pages/dashboard.php') ?>">
      <span class="brand__mark"><?= icon('crops', 22) ?></span>
      <span class="brand__text">
        <span class="brand__name"><?= e(setting('farm_name', APP_NAME)) ?></span>
        <span class="brand__sub">Farm System</span>
      </span>
    </a>

    <nav class="sidebar__scroll">
      <?php foreach ($navSections as $navSection => $navItems): ?>
        <?php
          // Hide a whole section when the user may not see any of its items
          $navVisible = array_filter($navItems, fn($i) => $i['cap'] === null || can($i['cap']));
          if (!$navVisible) continue;
        ?>
        <div class="nav__label"><?= e($navSection) ?></div>
        <?php foreach ($navVisible as $navItem): ?>
          <a class="nav__item<?= $activeNav === $navItem['key'] ? ' is-active' : '' ?>"
             href="<?= url($navItem['href']) ?>"
             data-tip="<?= e($navItem['label']) ?>">
            <span class="nav__icon"><?= icon($navItem['icon'], 19) ?></span>
            <span class="nav__text"><?= e($navItem['label']) ?></span>
            <?php if (!empty($navCounts[$navItem['key']])): ?>
              <span class="nav__count<?= $navItem['key'] === 'tasks' ? ' nav__count--muted' : '' ?>">
                <?= (int) $navCounts[$navItem['key']] ?>
              </span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar__footer">
      <a class="userchip" href="<?= url('pages/profile.php') ?>">
        <span class="avatar avatar--sm"><?= e(initials($me['full_name'])) ?></span>
        <span class="userchip__meta">
          <span class="userchip__name"><?= e($me['full_name']) ?></span>
          <span class="userchip__role"><?= e($me['role']) ?></span>
        </span>
      </a>
    </div>
  </aside>

  <!-- ============================= MAIN ============================== -->
  <div class="main">
    <header class="topbar">
      <button class="iconbtn" data-toggle-sidebar aria-label="Toggle navigation">
        <?= icon('menu', 20) ?>
      </button>

      <div class="topbar__title">
        <h1><?= e($pageTitle) ?></h1>
        <?php if ($pageSubtitle): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?>
      </div>

      <div class="topbar__spacer"></div>

      <span class="small muted nowrap no-print" data-clock></span>

      <!-- Notifications -->
      <div class="dropdown no-print">
        <button class="iconbtn" data-dropdown aria-label="Notifications">
          <?= icon('bell', 20) ?>
          <?php if ($notifCount): ?><span class="iconbtn__dot"></span><?php endif; ?>
        </button>
        <div class="dropdown__menu" style="min-width:330px">
          <div class="dropdown__head flex items-c justify-b">
            <strong>Notifications</strong>
            <span class="badge badge--<?= $notifCount ? 'danger' : 'neutral' ?>"><?= $notifCount ?> new</span>
          </div>
          <div class="dropdown__sep"></div>

          <?php if (!$notifications): ?>
            <div class="text-c" style="padding:22px 12px">
              <?= icon('success', 26, 'c-success') ?>
              <p class="small muted mt-8">Everything is up to date.</p>
            </div>
          <?php else: ?>
            <div style="max-height:320px;overflow-y:auto">
              <?php foreach ($notifications as $hdrNotif): ?>
                <a class="notif" href="<?= url($hdrNotif['href']) ?>">
                  <span class="tile tile--sm tile--<?= e($hdrNotif['tone']) ?>"><?= icon($hdrNotif['icon'], 15) ?></span>
                  <span class="notif__text">
                    <span class="notif__title"><?= e($hdrNotif['title']) ?></span>
                    <span class="notif__sub"><?= e($hdrNotif['sub']) ?></span>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Theme -->
      <button class="iconbtn no-print" data-theme-toggle aria-label="Switch colour theme">
        <span data-theme-icon="light"><?= icon('moon', 19) ?></span>
        <span data-theme-icon="dark" class="hide"><?= icon('sun', 19) ?></span>
      </button>

      <!-- Account -->
      <div class="dropdown no-print">
        <button class="flex items-c gap-8" data-dropdown style="padding:4px;border-radius:var(--r-pill)">
          <span class="avatar avatar--sm"><?= e(initials($me['full_name'])) ?></span>
          <?= icon('chevron-down', 15, 'muted') ?>
        </button>
        <div class="dropdown__menu">
          <div class="dropdown__head">
            <strong><?= e($me['full_name']) ?></strong>
            <span><?= e($me['email']) ?></span>
          </div>
          <div class="dropdown__sep"></div>
          <a class="dropdown__item" href="<?= url('pages/profile.php') ?>"><?= icon('user', 17) ?> My Profile</a>
          <?php if (is_admin()): ?>
            <a class="dropdown__item" href="<?= url('pages/settings.php') ?>"><?= icon('settings', 17) ?> Farm Settings</a>
          <?php endif; ?>
          <a class="dropdown__item" href="<?= url('pages/reports.php') ?>"><?= icon('reports', 17) ?> Reports</a>
          <div class="dropdown__sep"></div>
          <a class="dropdown__item dropdown__item--danger" href="<?= url('logout.php') ?>"><?= icon('logout', 17) ?> Sign out</a>
        </div>
      </div>
    </header>

    <main class="content">
      <?php if ($breadcrumbs): ?>
        <div class="breadcrumb no-print">
          <?php $last = array_key_last($breadcrumbs); ?>
          <?php foreach ($breadcrumbs as $crumbLabel => $crumbHref): ?>
            <?php if ($crumbHref): ?>
              <a href="<?= url($crumbHref) ?>"><?= e($crumbLabel) ?></a>
              <?= icon('chevron-right', 12) ?>
            <?php else: ?>
              <span class="bold" style="color:var(--text-2)"><?= e($crumbLabel) ?></span>
            <?php endif; ?>
            <?php unset($last); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
