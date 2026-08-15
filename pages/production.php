<?php
/**
 * ---------------------------------------------------------------------
 *  Daily production log — milk, eggs and other animal output
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_capability('production.manage');

if (is_post()) {
    csrf_verify();

    if (post('action') === 'delete') {
        $id = post_int('id');
        if ($id > 0) {
            delete_row('production_records', $id);
            log_activity('production', 'delete', 'Deleted production record #' . $id);
            flash('success', 'The production entry was deleted.');
        }
        redirect('pages/production.php');
    }

    $id = post_int('id');

    $errors = validate([
        'category_id' => ['required' => true, 'label' => 'Livestock category'],
        'product'     => ['required' => true, 'max' => 60],
        'quantity'    => ['required' => true, 'numeric' => true, 'gte' => 0],
        'unit'        => ['required' => true, 'max' => 20],
        'record_date' => ['required' => true, 'date' => true, 'label' => 'Date'],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'category_id' => post_int('category_id'),
            'product'     => post('product'),
            'quantity'    => post_num('quantity'),
            'unit'        => post('unit'),
            'record_date' => post('record_date'),
            'notes'       => post_or_null('notes'),
        ];

        if ($id > 0) {
            update('production_records', $data, $id);
            log_activity('production', 'update', 'Updated ' . $data['product'] . ' record');
            flash('success', 'The production entry was updated.');
        } else {
            $data['recorded_by'] = current_user()['id'];
            insert('production_records', $data);
            log_activity('production', 'create',
                'Recorded ' . qty($data['quantity']) . ' ' . $data['unit'] . ' of ' . $data['product']);
            flash('success', 'Recorded ' . qty($data['quantity']) . ' ' . $data['unit'] . ' of ' . $data['product'] . '.');
        }
        redirect('pages/production.php');
    }
}

// --- Filters ----------------------------------------------------------
$product  = get_param('product');
$category = get_param('category');
$from     = get_param('from', date('Y-m-d', strtotime('-30 days')));
$to       = get_param('to', date('Y-m-d'));

$where  = ['p.record_date BETWEEN ? AND ?'];
$params = [$from, $to];

if ($product !== '')  { $where[] = 'p.product = ?';     $params[] = $product; }
if ($category !== '') { $where[] = 'p.category_id = ?'; $params[] = (int) $category; }

$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int) scalar("SELECT COUNT(*) FROM production_records p $whereSql", $params);
$page  = paginate($total);

$records = all(
    "SELECT p.*, c.name AS category_name, c.icon AS category_icon, u.full_name AS recorder
       FROM production_records p
       JOIN livestock_categories c ON c.id = p.category_id
       LEFT JOIN users u ON u.id = p.recorded_by
       $whereSql
      ORDER BY p.record_date DESC, p.id DESC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$categories = all('SELECT * FROM livestock_categories ORDER BY name');
$products   = all('SELECT DISTINCT product FROM production_records ORDER BY product');

// --- Trend chart: last 14 days, one line per product -------------------
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = date('j M', strtotime("-$i day"));
}

$productNames = array_column($products, 'product');
$seriesData   = [];
foreach ($productNames as $name) {
    $seriesData[$name] = array_fill_keys(array_keys($days), 0.0);
}

foreach (all(
    'SELECT record_date, product, SUM(quantity) AS total
       FROM production_records
      WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      GROUP BY record_date, product'
) as $row) {
    if (isset($seriesData[$row['product']][$row['record_date']])) {
        $seriesData[$row['product']][$row['record_date']] = (float) $row['total'];
    }
}

$palette   = ['#2b78d4', '#d9911f', '#16874a', '#7355d1', '#0e9c96'];
$trendChart = [
    'type'   => 'area',
    'height' => 270,
    'labels' => array_values($days),
    'series' => [],
];
$idx = 0;
foreach ($seriesData as $name => $values) {
    $trendChart['series'][] = [
        'name'  => $name,
        'data'  => array_values($values),
        'color' => $palette[$idx++ % count($palette)],
    ];
}

// --- Totals -----------------------------------------------------------
$periodTotals = all(
    "SELECT p.product, p.unit, SUM(p.quantity) AS total, COUNT(*) AS entries
       FROM production_records p
       $whereSql
      GROUP BY p.product, p.unit
      ORDER BY total DESC",
    $params
);

$todayTotal = all(
    'SELECT product, unit, SUM(quantity) AS total
       FROM production_records WHERE record_date = CURDATE() GROUP BY product, unit'
);

$pageTitle    = 'Production';
$pageSubtitle = 'Daily output from the livestock enterprise.';
$activeNav    = 'production';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Production' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('production', 24, 'c-brand') ?> Production Log</h1>
    <p>Recording <?= fdate($from) ?> to <?= fdate($to) ?> · <?= number_format($total) ?> entries</p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
    <button class="btn btn--primary" data-modal="prodModal" data-primary-action
            data-fill='{"id":"","quantity":"","notes":"","record_date":"<?= date('Y-m-d') ?>"}'
            data-fill-text='{"title":"Record Production"}'>
      <?= icon('plus', 17) ?> Record Output
    </button>
  </div>
</div>

<!-- Today at a glance -->
<section class="grid grid--4 mb-18">
  <?php
    $tones = ['blue', 'gold', 'green', 'purple'];
    $t = 0;
  ?>
  <?php if (!$todayTotal): ?>
    <article class="stat stat--blue reveal">
      <div class="stat__top">
        <span class="stat__icon"><?= icon('production', 22) ?></span>
        <div>
          <div class="stat__label">Today</div>
          <div class="stat__value">—</div>
        </div>
      </div>
      <div class="stat__foot"><span>Nothing recorded yet today</span></div>
    </article>
  <?php else: ?>
    <?php foreach ($todayTotal as $row): ?>
      <article class="stat stat--<?= $tones[$t % 4] ?> reveal" data-delay="<?= $t * 60 ?>">
        <div class="stat__top">
          <span class="stat__icon">
            <?= icon(stripos($row['product'], 'egg') !== false ? 'egg' : 'drop', 22) ?>
          </span>
          <div>
            <div class="stat__label"><?= e($row['product']) ?> Today</div>
            <div class="stat__value" data-count="<?= (float) $row['total'] ?>" data-decimals="1">0</div>
          </div>
        </div>
        <div class="stat__foot"><span><?= e($row['unit']) ?></span></div>
      </article>
      <?php $t++; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php foreach (array_slice($periodTotals, 0, max(0, 4 - max(1, count($todayTotal)))) as $row): ?>
    <article class="stat stat--<?= $tones[$t % 4] ?> reveal" data-delay="<?= $t * 60 ?>">
      <div class="stat__top">
        <span class="stat__icon"><?= icon('reports', 22) ?></span>
        <div>
          <div class="stat__label"><?= e($row['product']) ?> (period)</div>
          <div class="stat__value" data-count="<?= (float) $row['total'] ?>" data-decimals="0">0</div>
        </div>
      </div>
      <div class="stat__foot"><span><?= e($row['unit']) ?> from <?= (int) $row['entries'] ?> entries</span></div>
    </article>
    <?php $t++; ?>
  <?php endforeach; ?>
</section>

<!-- Trend -->
<section class="card mb-18 reveal" data-delay="200">
  <div class="card__head">
    <h3><?= icon('activity', 18) ?> Output Trend</h3>
    <span class="card__sub">Last 14 days</span>
  </div>
  <div class="card__body">
    <?php if ($trendChart['series']): ?>
      <div data-chart><script type="application/json"><?= json_encode($trendChart) ?></script></div>
    <?php else: ?>
      <div class="empty">
        <span class="empty__art"><?= icon('activity', 28) ?></span>
        <h3>No data to chart</h3>
        <p>Record some output and the trend will appear here.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Log -->
<section class="card reveal" data-delay="240">
  <form class="toolbar" method="get">
    <div class="field-inline">
      <?= icon('calendar', 16) ?>
      <input type="date" name="from" value="<?= e($from) ?>" style="min-width:150px">
    </div>
    <div class="field-inline">
      <?= icon('arrow-right', 16) ?>
      <input type="date" name="to" value="<?= e($to) ?>" style="min-width:150px">
    </div>
    <div class="field-inline">
      <?= icon('production', 16) ?>
      <select name="product" data-autosubmit>
        <option value="">All products</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= e($p['product']) ?>"<?= selected($product, $p['product']) ?>><?= e($p['product']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--sm btn--soft" type="submit"><?= icon('search', 15) ?> Apply</button>
    <a class="btn btn--sm btn--plain" href="<?= url('pages/production.php') ?>"><?= icon('refresh', 15) ?> Reset</a>
    <div class="toolbar__spacer"></div>
    <?php foreach ($periodTotals as $row): ?>
      <span class="chip"><?= icon('check', 13) ?><?= e($row['product']) ?>: <strong><?= qty($row['total'], 1) ?></strong> <?= e($row['unit']) ?></span>
    <?php endforeach; ?>
  </form>

  <?php if (!$records): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('production', 30) ?></span>
      <h3>No entries in this period</h3>
      <p>Widen the date range, or record today's output to start the log.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Product</th>
            <th>Source</th>
            <th class="num">Quantity</th>
            <th>Recorded By</th>
            <th>Notes</th>
            <th class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
            <tr>
              <td class="nowrap small"><?= fdate($r['record_date']) ?></td>
              <td>
                <div class="cellmain">
                  <span class="tile tile--<?= stripos($r['product'], 'egg') !== false ? 'gold' : 'blue' ?> tile--sm">
                    <?= icon(stripos($r['product'], 'egg') !== false ? 'egg' : 'drop', 15) ?>
                  </span>
                  <span class="cellmain__text">
                    <span class="cellmain__title"><?= e($r['product']) ?></span>
                  </span>
                </div>
              </td>
              <td class="soft small"><?= e($r['category_name']) ?></td>
              <td class="num tnum bold"><?= qty($r['quantity'], 1) ?> <span class="muted small"><?= e($r['unit']) ?></span></td>
              <td class="small soft"><?= e($r['recorder'] ?? '—') ?></td>
              <td class="small muted"><?= e($r['notes'] ?: '—') ?></td>
              <td class="actions">
                <div>
                  <button class="rowbtn" title="Edit"
                          data-modal="prodModal"
                          data-fill='<?= e(json_encode([
                              'id'          => $r['id'],
                              'category_id' => $r['category_id'],
                              'product'     => $r['product'],
                              'quantity'    => $r['quantity'],
                              'unit'        => $r['unit'],
                              'record_date' => $r['record_date'],
                              'notes'       => $r['notes'],
                          ])) ?>'
                          data-fill-text='<?= e(json_encode(['title' => 'Edit ' . $r['product'] . ' entry'])) ?>'>
                    <?= icon('edit', 16) ?>
                  </button>
                  <button class="rowbtn rowbtn--danger" title="Delete"
                          data-modal="deleteModal"
                          data-fill='<?= e(json_encode(['id' => $r['id']])) ?>'
                          data-fill-text='<?= e(json_encode(['name' => qty($r['quantity'], 1) . ' ' . $r['unit'] . ' of ' . $r['product'] . ' on ' . fdate($r['record_date'])])) ?>'>
                    <?= icon('trash', 16) ?>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= render_pagination($page) ?>
  <?php endif; ?>
</section>

<!-- ===================== ADD / EDIT MODAL ===================== -->
<div class="modal" id="prodModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('production', 19) ?> <span data-text="title">Record Production</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="form__row">
          <div class="field">
            <label for="category_id">Source category <span class="req">*</span></label>
            <select class="select" id="category_id" name="category_id" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="record_date">Date <span class="req">*</span></label>
            <input class="input" type="date" id="record_date" name="record_date"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form__row--3 form__row">
          <div class="field">
            <label for="product">Product <span class="req">*</span></label>
            <input class="input" type="text" id="product" name="product" list="productList"
                   placeholder="e.g. Milk" required>
            <datalist id="productList">
              <?php foreach ($products as $p): ?>
                <option value="<?= e($p['product']) ?>"></option>
              <?php endforeach; ?>
              <option value="Milk"></option><option value="Eggs"></option><option value="Honey"></option>
            </datalist>
          </div>
          <div class="field">
            <label for="quantity">Quantity <span class="req">*</span></label>
            <input class="input" type="number" step="0.01" min="0" id="quantity" name="quantity"
                   placeholder="0.00" required>
          </div>
          <div class="field">
            <label for="unit">Unit <span class="req">*</span></label>
            <select class="select" id="unit" name="unit" required>
              <option value="litres">litres</option>
              <option value="pieces">pieces</option>
              <option value="kg">kg</option>
              <option value="crates">crates</option>
              <option value="trays">trays</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="notes">Notes</label>
          <textarea class="textarea" id="notes" name="notes" style="min-height:70px"
                    placeholder="e.g. Morning and evening milking"></textarea>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Entry</button>
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
        <h3>Delete this entry?</h3>
        <p class="soft small mt-8"><strong data-text="name"></strong> will be removed from the log.</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Delete</button>
      </div>
    </form>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
