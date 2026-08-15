<?php
/**
 * ---------------------------------------------------------------------
 *  Dashboard — the operational overview of the whole farm
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$me = current_user();

// =====================================================================
//  HEADLINE FIGURES
// =====================================================================
$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('first day of last month'));

$livestockTotal = (int) scalar("SELECT COUNT(*) FROM livestock WHERE status NOT IN ('sold','deceased')");
$livestockLast  = (int) scalar(
    "SELECT COUNT(*) FROM livestock
      WHERE status NOT IN ('sold','deceased')
        AND DATE_FORMAT(created_at,'%Y-%m') < ?", [$thisMonth]
);

$activeCrops = (int) scalar("SELECT COUNT(*) FROM crops WHERE status NOT IN ('harvested','failed')");
$acresUnder  = (float) scalar("SELECT COALESCE(SUM(area_planted),0) FROM crops WHERE status NOT IN ('harvested','failed')");

$incomeThis = (float) scalar(
    "SELECT COALESCE(SUM(amount),0) FROM transactions
      WHERE type='income' AND DATE_FORMAT(transaction_date,'%Y-%m') = ?", [$thisMonth]
);
$incomeLast = (float) scalar(
    "SELECT COALESCE(SUM(amount),0) FROM transactions
      WHERE type='income' AND DATE_FORMAT(transaction_date,'%Y-%m') = ?", [$lastMonth]
);
$expenseThis = (float) scalar(
    "SELECT COALESCE(SUM(amount),0) FROM transactions
      WHERE type='expense' AND DATE_FORMAT(transaction_date,'%Y-%m') = ?", [$thisMonth]
);
$expenseLast = (float) scalar(
    "SELECT COALESCE(SUM(amount),0) FROM transactions
      WHERE type='expense' AND DATE_FORMAT(transaction_date,'%Y-%m') = ?", [$lastMonth]
);

$profitThis = $incomeThis - $expenseThis;
$profitLast = $incomeLast - $expenseLast;

$incomeDelta  = percent_change($incomeThis, $incomeLast);
$expenseDelta = percent_change($expenseThis, $expenseLast);
$profitDelta  = percent_change($profitThis, $profitLast);
$stockDelta   = percent_change($livestockTotal, $livestockLast);

// =====================================================================
//  CHART 1 — income vs expenses across twelve months
// =====================================================================
$months      = month_range(12);
$incomeSeries  = array_fill_keys(array_keys($months), 0.0);
$expenseSeries = array_fill_keys(array_keys($months), 0.0);

foreach (all(
    "SELECT DATE_FORMAT(transaction_date,'%Y-%m') AS m, type, SUM(amount) AS total
       FROM transactions
      WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY m, type"
) as $row) {
    if (!array_key_exists($row['m'], $incomeSeries)) {
        continue;
    }
    if ($row['type'] === 'income') {
        $incomeSeries[$row['m']] = (float) $row['total'];
    } else {
        $expenseSeries[$row['m']] = (float) $row['total'];
    }
}

$cashflowChart = [
    'type'    => 'area',
    'height'  => 290,
    'labels'  => array_values($months),
    'prefix'  => currency() . ' ',
    'compact' => true,
    'series'  => [
        ['name' => 'Income',   'data' => array_values($incomeSeries),  'color' => '#16874a'],
        ['name' => 'Expenses', 'data' => array_values($expenseSeries), 'color' => '#d9911f'],
    ],
];

// =====================================================================
//  CHART 2 — herd composition
// =====================================================================
$herd = all(
    "SELECT c.name, c.icon, COUNT(l.id) AS total
       FROM livestock_categories c
       LEFT JOIN livestock l ON l.category_id = c.id AND l.status NOT IN ('sold','deceased')
      GROUP BY c.id, c.name, c.icon
      HAVING total > 0
      ORDER BY total DESC"
);

$herdChart = [
    'type'        => 'donut',
    'size'        => 214,
    'thickness'   => 28,
    'centerValue' => number_format($livestockTotal),
    'centerLabel' => 'Animals',
    'data'        => array_map(fn($r) => ['label' => $r['name'], 'value' => (int) $r['total']], $herd),
];

// =====================================================================
//  CHART 3 — production over the last seven days
// =====================================================================
$prodDays = [];
for ($i = 6; $i >= 0; $i--) {
    $prodDays[date('Y-m-d', strtotime("-$i day"))] = date('D', strtotime("-$i day"));
}

$milk = array_fill_keys(array_keys($prodDays), 0.0);
$eggs = array_fill_keys(array_keys($prodDays), 0.0);

foreach (all(
    "SELECT record_date, product, SUM(quantity) AS total
       FROM production_records
      WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      GROUP BY record_date, product"
) as $row) {
    if (!array_key_exists($row['record_date'], $milk)) {
        continue;
    }
    if (stripos($row['product'], 'milk') !== false) {
        $milk[$row['record_date']] = (float) $row['total'];
    } elseif (stripos($row['product'], 'egg') !== false) {
        $eggs[$row['record_date']] = (float) $row['total'];
    }
}

$productionChart = [
    'type'   => 'bar',
    'height' => 250,
    'labels' => array_values($prodDays),
    'series' => [
        ['name' => 'Milk (litres)', 'data' => array_values($milk), 'color' => '#2b78d4'],
        ['name' => 'Eggs (pieces)', 'data' => array_values($eggs), 'color' => '#d9911f'],
    ],
];

$milkWeek = array_sum($milk);
$eggsWeek = array_sum($eggs);

// =====================================================================
//  SIDE PANELS
// =====================================================================
$lowStock = all(
    'SELECT i.*, c.name AS category, c.icon
       FROM inventory_items i
       JOIN inventory_categories c ON c.id = i.category_id
      WHERE i.quantity <= i.reorder_level
      ORDER BY (i.quantity / NULLIF(i.reorder_level,0)) ASC
      LIMIT 5'
);

$upcomingTasks = all(
    "SELECT t.*, e.full_name AS assignee
       FROM tasks t
       LEFT JOIN employees e ON e.id = t.assigned_to
      WHERE t.status IN ('pending','in_progress')
      ORDER BY (t.due_date IS NULL), t.due_date ASC,
               FIELD(t.priority,'urgent','high','medium','low')
      LIMIT 6"
);

$recentActivity = all(
    'SELECT a.*, u.full_name
       FROM activity_log a
       LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC
      LIMIT 7'
);

$growingCrops = all(
    "SELECT c.*, f.name AS field_name
       FROM crops c
       JOIN fields f ON f.id = c.field_id
      WHERE c.status NOT IN ('harvested','failed')
      ORDER BY c.expected_harvest ASC
      LIMIT 5"
);

$healthAlerts = (int) scalar("SELECT COUNT(*) FROM livestock WHERE status IN ('sick','quarantine')");
$pendingTasks = (int) scalar("SELECT COUNT(*) FROM tasks WHERE status IN ('pending','in_progress')");
$overdueTasks = (int) scalar("SELECT COUNT(*) FROM tasks WHERE status IN ('pending','in_progress') AND due_date < CURDATE()");
$staffActive  = (int) scalar("SELECT COUNT(*) FROM employees WHERE status='active'");

// Yield recorded this year
$harvestYear = (float) scalar('SELECT COALESCE(SUM(quantity),0) FROM harvests WHERE YEAR(harvest_date)=YEAR(CURDATE())');

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'))
              . ', ' . explode(' ', $me['full_name'])[0] . ' — here is how the farm is doing today.';
$activeNav    = 'dashboard';

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<!-- ===================== HEADLINE STAT TILES ===================== -->
<section class="grid grid--4 mb-18">

  <article class="stat stat--green reveal" data-delay="0">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('livestock', 23) ?></span>
      <div>
        <div class="stat__label">Total Livestock</div>
        <div class="stat__value" data-count="<?= $livestockTotal ?>">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <span class="delta delta--<?= $stockDelta > 0 ? 'up' : ($stockDelta < 0 ? 'down' : 'flat') ?>">
        <?= icon($stockDelta >= 0 ? 'trend-up' : 'trend-down', 13) ?><?= abs($stockDelta) ?>%
      </span>
      <span><?= $healthAlerts ?> needing attention</span>
    </div>
  </article>

  <article class="stat stat--gold reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('crops', 23) ?></span>
      <div>
        <div class="stat__label">Crops Growing</div>
        <div class="stat__value" data-count="<?= $activeCrops ?>">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <span class="delta delta--flat"><?= icon('fields', 13) ?><?= qty($acresUnder) ?> acres</span>
      <span>under cultivation</span>
    </div>
  </article>

  <article class="stat stat--blue reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('finance', 23) ?></span>
      <div>
        <div class="stat__label">Income This Month</div>
        <div class="stat__value" data-count="<?= $incomeThis ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <span class="delta delta--<?= $incomeDelta > 0 ? 'up' : ($incomeDelta < 0 ? 'down' : 'flat') ?>">
        <?= icon($incomeDelta >= 0 ? 'trend-up' : 'trend-down', 13) ?><?= abs($incomeDelta) ?>%
      </span>
      <span>vs last month</span>
    </div>
  </article>

  <article class="stat stat--<?= $profitThis >= 0 ? 'purple' : 'red' ?> reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('reports', 23) ?></span>
      <div>
        <div class="stat__label">Net Profit</div>
        <div class="stat__value" data-count="<?= $profitThis ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <span class="delta delta--<?= $profitDelta > 0 ? 'up' : ($profitDelta < 0 ? 'down' : 'flat') ?>">
        <?= icon($profitDelta >= 0 ? 'trend-up' : 'trend-down', 13) ?><?= abs($profitDelta) ?>%
      </span>
      <span><?= money_short($expenseThis) ?> spent</span>
    </div>
  </article>
</section>

<!-- ===================== CASH FLOW + HERD ===================== -->
<section class="grid grid--2-1 mb-18">

  <article class="card card--hover reveal" data-delay="220">
    <div class="card__head">
      <h3><?= icon('activity', 18) ?> Cash Flow</h3>
      <span class="card__sub">Last 12 months</span>
      <div class="card__actions">
        <span class="badge badge--success"><i class="badge__dot"></i>Income</span>
        <span class="badge badge--warning"><i class="badge__dot"></i>Expenses</span>
      </div>
    </div>
    <div class="card__body">
      <div data-chart>
        <script type="application/json"><?= json_encode($cashflowChart) ?></script>
      </div>
    </div>
    <div class="card__foot flex items-c justify-b wrap gap-14">
      <span class="small muted">Twelve month income <strong class="c-brand"><?= money(array_sum($incomeSeries)) ?></strong></span>
      <a class="btn btn--sm btn--ghost" href="<?= url('pages/finance.php') ?>">
        Open finance <?= icon('arrow-right', 15) ?>
      </a>
    </div>
  </article>

  <article class="card card--hover reveal" data-delay="280">
    <div class="card__head">
      <h3><?= icon('livestock', 18) ?> Herd Composition</h3>
    </div>
    <div class="card__body">
      <?php if ($herd): ?>
        <div data-chart>
          <script type="application/json"><?= json_encode($herdChart) ?></script>
        </div>
      <?php else: ?>
        <div class="empty">
          <span class="empty__art"><?= icon('livestock', 30) ?></span>
          <h3>No animals yet</h3>
          <p>Add your first animal to see the herd breakdown here.</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="card__foot">
      <a class="btn btn--sm btn--ghost btn--block" href="<?= url('pages/livestock.php') ?>">
        <?= icon('livestock', 15) ?> Manage livestock
      </a>
    </div>
  </article>
</section>

<!-- ===================== PRODUCTION + ALERTS ===================== -->
<section class="grid grid--2-1 mb-18">

  <article class="card card--hover reveal" data-delay="320">
    <div class="card__head">
      <h3><?= icon('production', 18) ?> Production This Week</h3>
      <span class="card__sub">Daily milk and egg output</span>
      <div class="card__actions">
        <a class="btn btn--sm btn--soft" href="<?= url('pages/production.php') ?>">
          <?= icon('plus', 15) ?> Record
        </a>
      </div>
    </div>
    <div class="card__body">
      <div data-chart>
        <script type="application/json"><?= json_encode($productionChart) ?></script>
      </div>
      <div class="grid grid--2 mt-14">
        <div class="metric-row" style="border:0;padding:0">
          <span class="tile tile--blue tile--sm"><?= icon('drop', 15) ?></span>
          <span class="metric-row__text">
            <span class="metric-row__name">Milk this week</span>
            <span class="tiny muted"><?= qty($milkWeek / 7, 1) ?> litres daily average</span>
          </span>
          <span class="metric-row__val"><?= qty($milkWeek, 1) ?> L</span>
        </div>
        <div class="metric-row" style="border:0;padding:0">
          <span class="tile tile--gold tile--sm"><?= icon('egg', 15) ?></span>
          <span class="metric-row__text">
            <span class="metric-row__name">Eggs this week</span>
            <span class="tiny muted"><?= qty($eggsWeek / 7, 0) ?> pieces daily average</span>
          </span>
          <span class="metric-row__val"><?= qty($eggsWeek, 0) ?></span>
        </div>
      </div>
    </div>
  </article>

  <article class="card card--hover reveal" data-delay="360">
    <div class="card__head">
      <h3><?= icon('inventory', 18) ?> Low Stock</h3>
      <div class="card__actions">
        <span class="badge badge--<?= $lowStock ? 'danger' : 'success' ?>"><?= count($lowStock) ?></span>
      </div>
    </div>
    <div class="card__body card__body--flush">
      <?php if (!$lowStock): ?>
        <div class="empty" style="padding:38px 20px">
          <span class="empty__art"><?= icon('success', 28) ?></span>
          <h3>Stores are healthy</h3>
          <p>Nothing has fallen below its reorder level.</p>
        </div>
      <?php else: ?>
        <?php foreach ($lowStock as $item): ?>
          <?php $ratio = $item['reorder_level'] > 0 ? percent_of($item['quantity'], $item['reorder_level']) : 0; ?>
          <div class="listrow">
            <span class="tile tile--red"><?= icon(category_icon($item['icon']), 17) ?></span>
            <span class="listrow__text">
              <span class="listrow__title"><?= e($item['item_name']) ?></span>
              <span class="listrow__sub">
                <?= qty($item['quantity']) ?> <?= e($item['unit']) ?> left
                · reorder at <?= qty($item['reorder_level']) ?>
              </span>
              <div class="progress progress--red mt-8" style="height:5px">
                <div class="progress__fill" data-value="<?= min(100, $ratio) ?>"></div>
              </div>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="card__foot">
      <a class="btn btn--sm btn--ghost btn--block" href="<?= url('pages/inventory.php?stock=low') ?>">
        <?= icon('inventory', 15) ?> Review inventory
      </a>
    </div>
  </article>
</section>

<!-- ===================== TASKS / CROPS / ACTIVITY ===================== -->
<section class="grid grid--3">

  <article class="card card--hover reveal" data-delay="400">
    <div class="card__head">
      <h3><?= icon('tasks', 18) ?> Upcoming Tasks</h3>
      <div class="card__actions">
        <?php if ($overdueTasks): ?>
          <span class="badge badge--danger"><i class="badge__dot"></i><?= $overdueTasks ?> overdue</span>
        <?php else: ?>
          <span class="badge badge--neutral"><?= $pendingTasks ?> open</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card__body card__body--flush">
      <?php if (!$upcomingTasks): ?>
        <div class="empty" style="padding:38px 20px">
          <span class="empty__art"><?= icon('check', 28) ?></span>
          <h3>All caught up</h3>
          <p>There are no open tasks on the board.</p>
        </div>
      <?php else: ?>
        <?php foreach ($upcomingTasks as $task): ?>
          <?php $days = days_until($task['due_date']); ?>
          <a class="listrow" href="<?= url('pages/tasks.php') ?>">
            <span class="tile tile--<?= $task['priority'] === 'urgent' ? 'red' : ($task['priority'] === 'high' ? 'gold' : 'blue') ?> tile--sm">
              <?= icon('tasks', 15) ?>
            </span>
            <span class="listrow__text">
              <span class="listrow__title"><?= e($task['title']) ?></span>
              <span class="listrow__sub">
                <?= $task['assignee'] ? e($task['assignee']) : 'Unassigned' ?>
                <?php if ($task['due_date']): ?>
                  · <span class="<?= $days !== null && $days < 0 ? 'c-danger bold' : '' ?>">
                      <?= $days < 0 ? abs($days) . 'd overdue' : ($days === 0 ? 'Due today' : 'in ' . $days . 'd') ?>
                    </span>
                <?php endif; ?>
              </span>
            </span>
            <?= badge($task['priority'], priority_tone($task['priority'])) ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="card__foot">
      <a class="btn btn--sm btn--ghost btn--block" href="<?= url('pages/tasks.php') ?>">
        View task board <?= icon('arrow-right', 15) ?>
      </a>
    </div>
  </article>

  <article class="card card--hover reveal" data-delay="440">
    <div class="card__head">
      <h3><?= icon('crops', 18) ?> Crop Progress</h3>
      <span class="card__sub">Season to harvest</span>
    </div>
    <div class="card__body">
      <?php if (!$growingCrops): ?>
        <div class="empty" style="padding:30px 10px">
          <span class="empty__art"><?= icon('crops', 28) ?></span>
          <h3>Nothing planted</h3>
          <p>Register a crop to track it through the season.</p>
        </div>
      <?php else: ?>
        <?php foreach ($growingCrops as $crop): ?>
          <?php
            $start   = strtotime($crop['planting_date']);
            $end     = $crop['expected_harvest'] ? strtotime($crop['expected_harvest']) : $start;
            $span    = max(1, $end - $start);
            $done    = min(100, max(0, round(((time() - $start) / $span) * 100)));
            $daysOut = days_until($crop['expected_harvest']);
            $tone    = $done >= 95 ? '' : ($done >= 60 ? ' progress--gold' : ' progress--blue');
          ?>
          <div class="mb-14">
            <div class="flex items-c justify-b gap-10 mb-8">
              <span class="small bold"><?= e($crop['crop_name']) ?>
                <span class="muted" style="font-weight:400">· <?= e($crop['field_name']) ?></span>
              </span>
              <span class="tiny muted nowrap">
                <?= $daysOut === null ? '—' : ($daysOut < 0 ? 'Ready now' : $daysOut . ' days') ?>
              </span>
            </div>
            <div class="progress<?= $tone ?>">
              <div class="progress__fill" data-value="<?= $done ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="card__foot flex items-c justify-b gap-10">
      <span class="small muted"><?= qty($harvestYear) ?> kg harvested this year</span>
      <a class="btn btn--sm btn--plain" href="<?= url('pages/crops.php') ?>"><?= icon('arrow-right', 15) ?></a>
    </div>
  </article>

  <article class="card card--hover reveal" data-delay="480">
    <div class="card__head">
      <h3><?= icon('activity', 18) ?> Recent Activity</h3>
    </div>
    <div class="card__body">
      <?php if (!$recentActivity): ?>
        <div class="empty" style="padding:30px 10px">
          <span class="empty__art"><?= icon('activity', 28) ?></span>
          <h3>No activity yet</h3>
          <p>Actions taken in the system will appear here.</p>
        </div>
      <?php else: ?>
        <div class="timeline">
          <?php foreach ($recentActivity as $entry): ?>
            <div class="timeline__item">
              <div class="timeline__title"><?= e($entry['description'] ?: label($entry['action'])) ?></div>
              <div class="timeline__meta">
                <?= icon('user', 12) ?><?= e($entry['full_name'] ?? 'System') ?>
                · <?= e(label($entry['module'])) ?>
                · <?= e(time_ago($entry['created_at'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="card__foot flex items-c justify-b gap-10">
      <span class="small muted"><?= $staffActive ?> staff on duty</span>
      <?php if (is_admin()): ?>
        <a class="btn btn--sm btn--plain" href="<?= url('pages/activity.php') ?>"><?= icon('arrow-right', 15) ?></a>
      <?php endif; ?>
    </div>
  </article>
</section>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
