<?php
/**
 * ---------------------------------------------------------------------
 *  Reports — the analytical view of the whole operation
 * ---------------------------------------------------------------------
 *  Designed to be printed: the sidebar, toolbars and buttons are hidden
 *  by the print stylesheet so the output reads as a clean report.
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_capability('reports.view');

$from = get_param('from', date('Y-01-01'));
$to   = get_param('to', date('Y-m-d'));
$showMoney = can('finance.view');

$range = [$from, $to];

// =====================================================================
//  FINANCIAL SUMMARY
// =====================================================================
$income  = (float) scalar("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='income'  AND transaction_date BETWEEN ? AND ?", $range);
$expense = (float) scalar("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='expense' AND transaction_date BETWEEN ? AND ?", $range);
$profit  = $income - $expense;
$margin  = $income > 0 ? round(($profit / $income) * 100, 1) : 0.0;

// =====================================================================
//  PRODUCTION AND YIELD
// =====================================================================
$harvestQty = (float) scalar('SELECT COALESCE(SUM(quantity),0) FROM harvests WHERE harvest_date BETWEEN ? AND ?', $range);
$harvestRev = (float) scalar('SELECT COALESCE(SUM(revenue),0)  FROM harvests WHERE harvest_date BETWEEN ? AND ?', $range);
$prodTotals = all(
    'SELECT product, unit, SUM(quantity) AS total, COUNT(*) AS entries
       FROM production_records WHERE record_date BETWEEN ? AND ?
      GROUP BY product, unit ORDER BY total DESC',
    $range
);

// =====================================================================
//  HERD AND CROP POSITION
// =====================================================================
$herd = all(
    "SELECT c.name, COUNT(l.id) AS total,
            COALESCE(SUM(l.acquisition_cost),0) AS value,
            SUM(l.status='healthy') AS healthy
       FROM livestock_categories c
       LEFT JOIN livestock l ON l.category_id = c.id AND l.status NOT IN ('sold','deceased')
      GROUP BY c.id, c.name ORDER BY total DESC"
);

$cropSummary = all(
    "SELECT c.crop_name,
            COUNT(*) AS plantings,
            SUM(c.area_planted) AS area,
            SUM(c.input_cost) AS cost,
            COALESCE(SUM((SELECT SUM(h.quantity) FROM harvests h WHERE h.crop_id=c.id)),0) AS yielded,
            COALESCE(SUM((SELECT SUM(h.revenue)  FROM harvests h WHERE h.crop_id=c.id)),0) AS revenue
       FROM crops c
      GROUP BY c.crop_name ORDER BY revenue DESC"
);

// =====================================================================
//  MONTHLY SERIES
// =====================================================================
$months = month_range(12);
$inSer  = array_fill_keys(array_keys($months), 0.0);
$exSer  = array_fill_keys(array_keys($months), 0.0);

foreach (all(
    "SELECT DATE_FORMAT(transaction_date,'%Y-%m') AS m, type, SUM(amount) AS total
       FROM transactions WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY m, type"
) as $r) {
    if (!array_key_exists($r['m'], $inSer)) continue;
    if ($r['type'] === 'income') $inSer[$r['m']] = (float) $r['total'];
    else                          $exSer[$r['m']] = (float) $r['total'];
}

$financeChart = [
    'type'    => 'area',
    'height'  => 280,
    'labels'  => array_values($months),
    'prefix'  => currency() . ' ',
    'compact' => true,
    'series'  => [
        ['name' => 'Income',   'data' => array_values($inSer), 'color' => '#16874a'],
        ['name' => 'Expenses', 'data' => array_values($exSer), 'color' => '#d6453f'],
    ],
];

$expenseBreakdown = all(
    "SELECT category, SUM(amount) AS total FROM transactions
      WHERE type='expense' AND transaction_date BETWEEN ? AND ?
      GROUP BY category ORDER BY total DESC LIMIT 8",
    $range
);
$expenseChart = [
    'type'        => 'donut',
    'size'        => 210,
    'thickness'   => 28,
    'centerValue' => money_short($expense),
    'centerLabel' => 'Expenses',
    'prefix'      => currency() . ' ',
    'compact'     => true,
    'data'        => array_map(fn($r) => ['label' => $r['category'], 'value' => (float) $r['total']], $expenseBreakdown),
];

$herdChart = [
    'type'        => 'donut',
    'size'        => 210,
    'thickness'   => 28,
    'centerValue' => (string) array_sum(array_column($herd, 'total')),
    'centerLabel' => 'Animals',
    'data'        => array_values(array_filter(
        array_map(fn($r) => ['label' => $r['name'], 'value' => (int) $r['total']], $herd),
        fn($d) => $d['value'] > 0
    )),
];

// =====================================================================
//  OPERATIONAL KPIs
// =====================================================================
$kpis = [
    'animals'     => (int) scalar("SELECT COUNT(*) FROM livestock WHERE status NOT IN ('sold','deceased')"),
    'sick'        => (int) scalar("SELECT COUNT(*) FROM livestock WHERE status IN ('sick','quarantine')"),
    'crops'       => (int) scalar("SELECT COUNT(*) FROM crops WHERE status NOT IN ('harvested','failed')"),
    'acres'       => (float) scalar('SELECT COALESCE(SUM(size_acres),0) FROM fields'),
    'staff'       => (int) scalar("SELECT COUNT(*) FROM employees WHERE status='active'"),
    'stockValue'  => (float) scalar('SELECT COALESCE(SUM(quantity*unit_cost),0) FROM inventory_items'),
    'lowStock'    => (int) scalar('SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level'),
    'tasksDone'   => (int) scalar("SELECT COUNT(*) FROM tasks WHERE status='completed'"),
    'tasksOpen'   => (int) scalar("SELECT COUNT(*) FROM tasks WHERE status IN ('pending','in_progress')"),
    'vetSpend'    => (float) scalar('SELECT COALESCE(SUM(cost),0) FROM health_records WHERE treatment_date BETWEEN ? AND ?', $range),
];

$pageTitle    = 'Reports';
$pageSubtitle = 'Analytics across the whole farm.';
$activeNav    = 'reports';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Reports' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('reports', 24, 'c-brand') ?> Farm Report</h1>
    <p><?= e(setting('farm_name', APP_NAME)) ?> · <?= fdate($from) ?> to <?= fdate($to) ?></p>
  </div>
  <div class="pagehead__actions no-print">
    <form method="get" class="flex items-c gap-8 wrap">
      <div class="field-inline">
        <?= icon('calendar', 16) ?>
        <input type="date" name="from" value="<?= e($from) ?>" style="min-width:150px">
      </div>
      <div class="field-inline">
        <?= icon('arrow-right', 16) ?>
        <input type="date" name="to" value="<?= e($to) ?>" style="min-width:150px">
      </div>
      <button class="btn btn--ghost" type="submit"><?= icon('refresh', 17) ?> Update</button>
    </form>
    <button class="btn btn--primary" data-print><?= icon('print', 17) ?> Print Report</button>
  </div>
</div>

<!-- Printed letterhead, hidden on screen -->
<div class="print-only" style="margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid #16874a">
  <h2><?= e(setting('farm_name', APP_NAME)) ?></h2>
  <p><?= e(setting('farm_location', '')) ?> · <?= e(setting('farm_phone', '')) ?></p>
  <p><strong>Farm Performance Report</strong> — <?= fdate($from) ?> to <?= fdate($to) ?>
     · generated <?= fdatetime(date('Y-m-d H:i:s')) ?></p>
</div>

<!-- ===================== EXECUTIVE SUMMARY ===================== -->
<?php if ($showMoney): ?>
<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('arrow-down', 22) ?></span>
      <div>
        <div class="stat__label">Revenue</div>
        <div class="stat__value" data-count="<?= $income ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Total money received</span></div>
  </article>

  <article class="stat stat--red reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('arrow-up', 22) ?></span>
      <div>
        <div class="stat__label">Operating Cost</div>
        <div class="stat__value" data-count="<?= $expense ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Total money spent</span></div>
  </article>

  <article class="stat stat--<?= $profit >= 0 ? 'purple' : 'red' ?> reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('reports', 22) ?></span>
      <div>
        <div class="stat__label">Net Profit</div>
        <div class="stat__value" data-count="<?= $profit ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <span class="delta delta--<?= $profit >= 0 ? 'up' : 'down' ?>">
        <?= icon($profit >= 0 ? 'trend-up' : 'trend-down', 13) ?><?= abs($margin) ?>%
      </span>
      <span>margin</span>
    </div>
  </article>

  <article class="stat stat--gold reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('harvest', 22) ?></span>
      <div>
        <div class="stat__label">Harvest Volume</div>
        <div class="stat__value" data-count="<?= $harvestQty ?>" data-decimals="0" data-suffix=" kg">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Worth <?= money_short($harvestRev) ?></span></div>
  </article>
</section>
<?php endif; ?>

<!-- ===================== KEY INDICATORS ===================== -->
<section class="card mb-18 reveal" data-delay="200">
  <div class="card__head">
    <h3><?= icon('activity', 18) ?> Key Performance Indicators</h3>
    <span class="card__sub">Current position across every module</span>
  </div>
  <div class="card__body">
    <div class="grid grid--4" style="gap:14px">
      <?php
      $kpiCards = [
          ['Livestock on farm',  number_format($kpis['animals']),          'livestock',  $kpis['sick'] . ' needing care'],
          ['Crops growing',      number_format($kpis['crops']),            'crops',      qty($kpis['acres']) . ' acres of land'],
          ['Active staff',       number_format($kpis['staff']),            'staff',      $kpis['tasksOpen'] . ' open tasks'],
          ['Tasks completed',    number_format($kpis['tasksDone']),        'success',    'All time'],
          ['Stock value',        money_short($kpis['stockValue']),         'inventory',  $kpis['lowStock'] . ' below reorder'],
          ['Veterinary spend',   money_short($kpis['vetSpend']),           'health',     'In this period'],
          ['Harvest revenue',    money_short($harvestRev),                 'harvest',    qty($harvestQty) . ' kg sold'],
          ['Land holding',       qty($kpis['acres'], 2) . ' ac',           'fields',     'Across all parcels'],
      ];
      ?>
      <?php foreach ($kpiCards as $index => $k): ?>
        <?php if (!$showMoney && in_array($k[0], ['Stock value', 'Veterinary spend', 'Harvest revenue'], true)) continue; ?>
        <div class="metric-row" style="border:1px solid var(--border);border-radius:var(--r-md);padding:13px 14px">
          <span class="tile tile--sm"><?= icon($k[2], 16) ?></span>
          <span class="metric-row__text">
            <span class="tiny muted" style="text-transform:uppercase;letter-spacing:.08em;font-weight:650">
              <?= e($k[0]) ?>
            </span>
            <span class="metric-row__name bold" style="font-size:1.05rem"><?= e($k[1]) ?></span>
            <span class="tiny muted"><?= e($k[3]) ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== FINANCE CHARTS ===================== -->
<?php if ($showMoney): ?>
<section class="grid grid--2-1 mb-18">
  <article class="card reveal" data-delay="240">
    <div class="card__head">
      <h3><?= icon('finance', 18) ?> Income and Expenditure</h3>
      <span class="card__sub">Rolling 12 months</span>
    </div>
    <div class="card__body">
      <div data-chart><script type="application/json"><?= json_encode($financeChart) ?></script></div>
    </div>
  </article>

  <article class="card reveal" data-delay="280">
    <div class="card__head"><h3><?= icon('chart-pie', 18) ?> Cost Structure</h3></div>
    <div class="card__body">
      <?php if ($expenseBreakdown): ?>
        <div data-chart><script type="application/json"><?= json_encode($expenseChart) ?></script></div>
      <?php else: ?>
        <p class="muted small text-c">No expenses in this period.</p>
      <?php endif; ?>
    </div>
  </article>
</section>
<?php endif; ?>

<!-- ===================== LIVESTOCK REPORT ===================== -->
<section class="grid grid--1-2 mb-18">
  <article class="card reveal" data-delay="300">
    <div class="card__head"><h3><?= icon('livestock', 18) ?> Herd Structure</h3></div>
    <div class="card__body">
      <?php if ($herdChart['data']): ?>
        <div data-chart><script type="application/json"><?= json_encode($herdChart) ?></script></div>
      <?php else: ?>
        <p class="muted small text-c">No livestock on record.</p>
      <?php endif; ?>
    </div>
  </article>

  <article class="card reveal" data-delay="340">
    <div class="card__head">
      <h3><?= icon('list', 18) ?> Livestock by Category</h3>
    </div>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>Category</th>
            <th class="num">Head</th>
            <th class="num">Healthy</th>
            <th>Health Rate</th>
            <?php if ($showMoney): ?><th class="num">Herd Value</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($herd as $h): ?>
            <?php $rate = percent_of($h['healthy'], $h['total']); ?>
            <tr>
              <td class="bold"><?= e($h['name']) ?></td>
              <td class="num tnum"><?= (int) $h['total'] ?></td>
              <td class="num tnum"><?= (int) $h['healthy'] ?></td>
              <td style="min-width:120px">
                <div class="progress <?= $rate < 70 ? 'progress--gold' : '' ?>" style="height:6px">
                  <div class="progress__fill" data-value="<?= $rate ?>"></div>
                </div>
                <div class="tiny muted mt-8"><?= $rate ?>%</div>
              </td>
              <?php if ($showMoney): ?>
                <td class="num tnum"><?= money($h['value'], false) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>

<!-- ===================== CROP PERFORMANCE ===================== -->
<section class="card mb-18 reveal" data-delay="380">
  <div class="card__head">
    <h3><?= icon('crops', 18) ?> Crop Performance</h3>
    <span class="card__sub">Yield and return by crop, all seasons</span>
  </div>
  <?php if (!$cropSummary): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('crops', 30) ?></span>
      <h3>No crop data</h3>
      <p>Register crops and harvests to build this report.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>Crop</th>
            <th class="num">Plantings</th>
            <th class="num">Area</th>
            <th class="num">Yield</th>
            <th class="num">Yield / Acre</th>
            <?php if ($showMoney): ?>
              <th class="num">Input Cost</th>
              <th class="num">Revenue</th>
              <th class="num">Gross Margin</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cropSummary as $c): ?>
            <?php
              $perAcre = $c['area'] > 0 ? $c['yielded'] / $c['area'] : 0;
              $gross   = $c['revenue'] - $c['cost'];
            ?>
            <tr>
              <td>
                <div class="cellmain">
                  <span class="tile tile--sm"><?= icon('crops', 15) ?></span>
                  <span class="cellmain__text">
                    <span class="cellmain__title"><?= e($c['crop_name']) ?></span>
                  </span>
                </div>
              </td>
              <td class="num tnum"><?= (int) $c['plantings'] ?></td>
              <td class="num tnum"><?= qty($c['area'], 2) ?> ac</td>
              <td class="num tnum"><?= qty($c['yielded']) ?> kg</td>
              <td class="num tnum"><?= qty($perAcre, 1) ?> kg</td>
              <?php if ($showMoney): ?>
                <td class="num tnum"><?= money($c['cost'], false) ?></td>
                <td class="num tnum"><?= money($c['revenue'], false) ?></td>
                <td class="num tnum bold <?= $gross >= 0 ? 'c-success' : 'c-danger' ?>">
                  <?= money($gross, false) ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($showMoney): ?>
          <tfoot>
            <tr style="background:var(--surface-2);font-weight:700">
              <td>Totals</td>
              <td class="num tnum"><?= array_sum(array_column($cropSummary, 'plantings')) ?></td>
              <td class="num tnum"><?= qty(array_sum(array_column($cropSummary, 'area')), 2) ?> ac</td>
              <td class="num tnum"><?= qty(array_sum(array_column($cropSummary, 'yielded'))) ?> kg</td>
              <td></td>
              <td class="num tnum"><?= money(array_sum(array_column($cropSummary, 'cost')), false) ?></td>
              <td class="num tnum"><?= money(array_sum(array_column($cropSummary, 'revenue')), false) ?></td>
              <td class="num tnum">
                <?= money(array_sum(array_column($cropSummary, 'revenue')) - array_sum(array_column($cropSummary, 'cost')), false) ?>
              </td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- ===================== PRODUCTION SUMMARY ===================== -->
<section class="card reveal" data-delay="420">
  <div class="card__head">
    <h3><?= icon('production', 18) ?> Production Summary</h3>
    <span class="card__sub">Livestock output in the selected period</span>
  </div>
  <div class="card__body">
    <?php if (!$prodTotals): ?>
      <p class="muted small text-c">No production was recorded in this period.</p>
    <?php else: ?>
      <div class="grid grid--3" style="gap:14px">
        <?php foreach ($prodTotals as $p): ?>
          <div class="metric-row" style="border:1px solid var(--border);border-radius:var(--r-md);padding:14px 15px">
            <span class="tile tile--<?= stripos($p['product'], 'egg') !== false ? 'gold' : 'blue' ?>">
              <?= icon(stripos($p['product'], 'egg') !== false ? 'egg' : 'drop', 17) ?>
            </span>
            <span class="metric-row__text">
              <span class="metric-row__name"><?= e($p['product']) ?></span>
              <span class="tiny muted"><?= (int) $p['entries'] ?> entries recorded</span>
            </span>
            <span class="metric-row__val">
              <?= qty($p['total'], 1) ?>
              <div class="tiny muted text-r"><?= e($p['unit']) ?></div>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card__foot flex items-c justify-b wrap gap-10">
    <span class="small muted">
      Report generated by <?= e(current_user()['full_name']) ?> on <?= fdatetime(date('Y-m-d H:i:s')) ?>
    </span>
    <span class="small muted"><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></span>
  </div>
</section>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
