<?php
/**
 * ---------------------------------------------------------------------
 *  Activity log — the audit trail
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_admin();

if (is_post()) {
    csrf_verify();

    if (post('action') === 'clear') {
        $days = post_int('older_than', 90);
        $removed = q('DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])->rowCount();
        log_activity('system', 'clear', 'Cleared ' . $removed . ' log entries older than ' . $days . ' days');
        flash('success', $removed . ' old log entries were cleared.');
        redirect('pages/activity.php');
    }
}

$module = get_param('module');
$userId = (int) get_param('user');
$action = get_param('action_type');

$where  = [];
$params = [];

if ($module !== '') { $where[] = 'a.module = ?';  $params[] = $module; }
if ($userId > 0)    { $where[] = 'a.user_id = ?'; $params[] = $userId; }
if ($action !== '') { $where[] = 'a.action = ?';  $params[] = $action; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) scalar("SELECT COUNT(*) FROM activity_log a $whereSql", $params);
$page  = paginate($total, 20);

$entries = all(
    "SELECT a.*, u.full_name, u.role
       FROM activity_log a
       LEFT JOIN users u ON u.id = a.user_id
       $whereSql
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$modules = all('SELECT DISTINCT module FROM activity_log ORDER BY module');
$actions = all('SELECT DISTINCT action FROM activity_log ORDER BY action');
$users   = all('SELECT id, full_name FROM users ORDER BY full_name');

$todayCount = (int) scalar('SELECT COUNT(*) FROM activity_log WHERE DATE(created_at) = CURDATE()');
$weekCount  = (int) scalar('SELECT COUNT(*) FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$activeUser = one(
    'SELECT u.full_name, COUNT(*) AS total FROM activity_log a
       JOIN users u ON u.id = a.user_id
      GROUP BY u.id, u.full_name ORDER BY total DESC LIMIT 1'
);

// Activity per day for the last 14 days
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = date('j M', strtotime("-$i day"));
}
$counts = array_fill_keys(array_keys($days), 0);
foreach (all(
    'SELECT DATE(created_at) AS d, COUNT(*) AS total FROM activity_log
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d'
) as $r) {
    if (array_key_exists($r['d'], $counts)) {
        $counts[$r['d']] = (int) $r['total'];
    }
}

$activityChart = [
    'type'   => 'bar',
    'height' => 220,
    'labels' => array_values($days),
    'legend' => false,
    'series' => [['name' => 'Actions', 'data' => array_values($counts), 'color' => '#7355d1']],
];

$actionTone = [
    'create'   => 'success',
    'update'   => 'info',
    'delete'   => 'danger',
    'login'    => 'neutral',
    'logout'   => 'neutral',
    'register' => 'purple',
    'movement' => 'warning',
    'clear'    => 'danger',
];

$moduleIcons = [
    'auth' => 'lock', 'livestock' => 'livestock', 'health' => 'health',
    'production' => 'production', 'crops' => 'crops', 'fields' => 'fields',
    'harvest' => 'harvest', 'inventory' => 'inventory', 'suppliers' => 'suppliers',
    'finance' => 'finance', 'tasks' => 'tasks', 'employees' => 'staff',
    'users' => 'shield', 'settings' => 'settings', 'system' => 'settings',
];

$pageTitle    = 'Activity Log';
$pageSubtitle = 'Every action taken in the system.';
$activeNav    = 'activity';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Activity Log' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('activity', 24, 'c-brand') ?> Activity Log</h1>
    <p><?= number_format($total) ?> entries recorded · full audit trail</p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
    <button class="btn btn--danger" data-modal="clearModal"><?= icon('trash', 17) ?> Clear Old Entries</button>
  </div>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--purple reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('activity', 22) ?></span>
      <div>
        <div class="stat__label">Total Entries</div>
        <div class="stat__value" data-count="<?= $total ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Across all modules</span></div>
  </article>

  <article class="stat stat--green reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('clock', 22) ?></span>
      <div>
        <div class="stat__label">Today</div>
        <div class="stat__value" data-count="<?= $todayCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Actions since midnight</span></div>
  </article>

  <article class="stat stat--blue reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('calendar', 22) ?></span>
      <div>
        <div class="stat__label">This Week</div>
        <div class="stat__value" data-count="<?= $weekCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Last seven days</span></div>
  </article>

  <article class="stat stat--gold reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('user', 22) ?></span>
      <div>
        <div class="stat__label">Most Active</div>
        <div class="stat__value" style="font-size:1.05rem;margin-top:6px">
          <?= e($activeUser['full_name'] ?? '—') ?>
        </div>
      </div>
    </div>
    <div class="stat__foot"><span><?= (int) ($activeUser['total'] ?? 0) ?> actions logged</span></div>
  </article>
</section>

<section class="card mb-18 reveal" data-delay="200">
  <div class="card__head">
    <h3><?= icon('reports', 18) ?> System Usage</h3>
    <span class="card__sub">Actions per day, last 14 days</span>
  </div>
  <div class="card__body">
    <div data-chart><script type="application/json"><?= json_encode($activityChart) ?></script></div>
  </div>
</section>

<section class="card reveal" data-delay="240">
  <form class="toolbar" method="get">
    <div class="field-inline">
      <?= icon('grid', 16) ?>
      <select name="module" data-autosubmit>
        <option value="">All modules</option>
        <?php foreach ($modules as $m): ?>
          <option value="<?= e($m['module']) ?>"<?= selected($module, $m['module']) ?>><?= e(label($m['module'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-inline">
      <?= icon('user', 16) ?>
      <select name="user" data-autosubmit>
        <option value="">All users</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>"<?= selected($userId, $u['id']) ?>><?= e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-inline">
      <?= icon('filter', 16) ?>
      <select name="action_type" data-autosubmit>
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= e($a['action']) ?>"<?= selected($action, $a['action']) ?>><?= e(label($a['action'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($module || $userId || $action): ?>
      <a class="btn btn--sm btn--plain" href="<?= url('pages/activity.php') ?>"><?= icon('close', 15) ?> Clear filters</a>
    <?php endif; ?>
    <div class="toolbar__spacer"></div>
    <span class="small muted"><?= number_format($total) ?> matching entries</span>
  </form>

  <?php if (!$entries): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('activity', 30) ?></span>
      <h3>No activity recorded</h3>
      <p>Actions taken in the system will be listed here as they happen.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Module</th>
            <th>Action</th>
            <th>Description</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entries as $entry): ?>
            <tr>
              <td class="small nowrap">
                <?= e(time_ago($entry['created_at'])) ?>
                <div class="tiny muted"><?= fdatetime($entry['created_at']) ?></div>
              </td>
              <td>
                <?php if ($entry['full_name']): ?>
                  <div class="cellmain">
                    <span class="avatar avatar--sm"><?= e(initials($entry['full_name'])) ?></span>
                    <span class="cellmain__text">
                      <span class="cellmain__title small"><?= e($entry['full_name']) ?></span>
                      <span class="cellmain__sub"><?= e(label($entry['role'] ?? '')) ?></span>
                    </span>
                  </div>
                <?php else: ?>
                  <span class="muted small">System</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="flex items-c gap-6 small">
                  <?= icon($moduleIcons[$entry['module']] ?? 'grid', 15, 'c-brand') ?>
                  <?= e(label($entry['module'])) ?>
                </span>
              </td>
              <td>
                <span class="badge badge--<?= $actionTone[$entry['action']] ?? 'neutral' ?>">
                  <i class="badge__dot"></i><?= e(label($entry['action'])) ?>
                </span>
              </td>
              <td class="small soft"><?= e($entry['description'] ?: '—') ?></td>
              <td class="small muted mono"><?= e($entry['ip_address'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= render_pagination($page) ?>
  <?php endif; ?>
</section>

<div class="modal" id="clearModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear">

      <div class="modal__head">
        <h3><?= icon('trash', 19) ?> Clear Old Entries</h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="alert alert--warning mb-14">
          <?= icon('warning', 17) ?>
          <span>Removing log entries cannot be undone. Keep enough history to satisfy any audit you may need.</span>
        </div>
        <div class="field">
          <label for="older_than">Delete entries older than</label>
          <select class="select" id="older_than" name="older_than">
            <option value="30">30 days</option>
            <option value="90" selected>90 days</option>
            <option value="180">180 days</option>
            <option value="365">1 year</option>
          </select>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Clear Entries</button>
      </div>
    </form>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
