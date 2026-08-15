<?php
/**
 * ---------------------------------------------------------------------
 *  Finance — the farm ledger
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_capability('finance.view');

$canManage = can('finance.manage');

if (is_post()) {
    csrf_verify();
    require_capability('finance.manage');

    if (post('action') === 'delete') {
        $id = post_int('id');
        $tx = one('SELECT amount, type FROM transactions WHERE id = ?', [$id]);
        if ($tx) {
            delete_row('transactions', $id);
            log_activity('finance', 'delete', 'Deleted ' . $tx['type'] . ' of ' . money($tx['amount']));
            flash('success', 'The transaction was deleted.');
        }
        redirect('pages/finance.php');
    }

    $id = post_int('id');

    $errors = validate([
        'type'             => ['required' => true, 'in' => ['income', 'expense']],
        'category'         => ['required' => true, 'max' => 60],
        'amount'           => ['required' => true, 'numeric' => true, 'gte' => 0.01],
        'transaction_date' => ['required' => true, 'date' => true, 'label' => 'Date'],
        'payment_method'   => ['required' => true, 'label' => 'Payment method'],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'type'             => post('type'),
            'category'         => post('category'),
            'amount'           => post_num('amount'),
            'description'      => post_or_null('description'),
            'reference_no'     => post_or_null('reference_no'),
            'payment_method'   => post('payment_method'),
            'transaction_date' => post('transaction_date'),
        ];

        if ($id > 0) {
            update('transactions', $data, $id);
            log_activity('finance', 'update', 'Updated ' . $data['type'] . ' of ' . money($data['amount']));
            flash('success', 'The transaction was updated.');
        } else {
            $data['recorded_by'] = current_user()['id'];
            insert('transactions', $data);
            log_activity('finance', 'create', 'Recorded ' . $data['type'] . ' of ' . money($data['amount']));
            flash('success', ucfirst($data['type']) . ' of ' . money($data['amount']) . ' was recorded.');
        }
        redirect('pages/finance.php');
    }
}

// --- Filters ----------------------------------------------------------
$type     = get_param('type');
$category = get_param('category');
$from     = get_param('from', date('Y-m-01', strtotime('-5 months')));
$to       = get_param('to', date('Y-m-d'));
$search   = get_param('q');

$where  = ['t.transaction_date BETWEEN ? AND ?'];
$params = [$from, $to];

if ($type !== '')     { $where[] = 't.type = ?';     $params[] = $type; }
if ($category !== '') { $where[] = 't.category = ?'; $params[] = $category; }
if ($search !== '') {
    $where[] = '(t.description LIKE ? OR t.reference_no LIKE ? OR t.category LIKE ?)';
    $like    = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int) scalar("SELECT COUNT(*) FROM transactions t $whereSql", $params);
$page  = paginate($total);

$transactions = all(
    "SELECT t.*, u.full_name AS recorder
       FROM transactions t
       LEFT JOIN users u ON u.id = t.recorded_by
       $whereSql
      ORDER BY t.transaction_date DESC, t.id DESC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

// --- Period totals ----------------------------------------------------
$periodIncome  = (float) scalar("SELECT COALESCE(SUM(amount),0) FROM transactions t $whereSql AND t.type='income'", $params);
$periodExpense = (float) scalar("SELECT COALESCE(SUM(amount),0) FROM transactions t $whereSql AND t.type='expense'", $params);
$periodProfit  = $periodIncome - $periodExpense;
$margin        = $periodIncome > 0 ? round(($periodProfit / $periodIncome) * 100, 1) : 0.0;

// --- Charts -----------------------------------------------------------
$months  = month_range(12);
$inSer   = array_fill_keys(array_keys($months), 0.0);
$exSer   = array_fill_keys(array_keys($months), 0.0);

foreach (all(
    "SELECT DATE_FORMAT(transaction_date,'%Y-%m') AS m, type, SUM(amount) AS total
       FROM transactions
      WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY m, type"
) as $row) {
    if (!array_key_exists($row['m'], $inSer)) continue;
    if ($row['type'] === 'income') $inSer[$row['m']] = (float) $row['total'];
    else                           $exSer[$row['m']] = (float) $row['total'];
}

$profitSer = [];
foreach ($months as $key => $lab) {
    $profitSer[] = $inSer[$key] - $exSer[$key];
}

$ledgerChart = [
    'type'    => 'bar',
    'height'  => 280,
    'labels'  => array_values($months),
    'prefix'  => currency() . ' ',
    'compact' => true,
    'series'  => [
        ['name' => 'Income',   'data' => array_values($inSer), 'color' => '#16874a'],
        ['name' => 'Expenses', 'data' => array_values($exSer), 'color' => '#d6453f'],
    ],
];

$profitChart = [
    'type'    => 'area',
    'height'  => 220,
    'labels'  => array_values($months),
    'prefix'  => currency() . ' ',
    'compact' => true,
    'legend'  => false,
    'series'  => [
        ['name' => 'Net profit', 'data' => $profitSer, 'color' => '#7355d1'],
    ],
];

$expenseByCat = all(
    "SELECT category, SUM(amount) AS total FROM transactions t
      $whereSql AND t.type = 'expense'
      GROUP BY category ORDER BY total DESC LIMIT 8",
    $params
);
$expenseChart = [
    'type'        => 'donut',
    'size'        => 200,
    'thickness'   => 26,
    'centerValue' => money_short($periodExpense),
    'centerLabel' => 'Expenses',
    'prefix'      => currency() . ' ',
    'compact'     => true,
    'data'        => array_map(fn($r) => ['label' => $r['category'], 'value' => (float) $r['total']], $expenseByCat),
];

$incomeByCat = all(
    "SELECT category, SUM(amount) AS total FROM transactions t
      $whereSql AND t.type = 'income'
      GROUP BY category ORDER BY total DESC LIMIT 8",
    $params
);

$allCategories = all('SELECT DISTINCT category FROM transactions ORDER BY category');
$methods = ['cash', 'bank', 'mobile_money', 'cheque', 'credit'];

$incomeCats  = ['Crop Sales', 'Livestock Sales', 'Dairy', 'Poultry', 'Grants', 'Other Income'];
$expenseCats = ['Feed', 'Seeds', 'Fertilizer', 'Pesticides', 'Veterinary', 'Labour',
                'Fuel', 'Equipment', 'Maintenance', 'Utilities', 'Transport', 'Other Expense'];

$pageTitle    = 'Finance';
$pageSubtitle = 'Income, expenses and what is left over.';
$activeNav    = 'finance';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Finance' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('finance', 24, 'c-brand') ?> Financial Ledger</h1>
    <p><?= fdate($from) ?> to <?= fdate($to) ?> · <?= number_format($total) ?> transactions</p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
    <?php if ($canManage): ?>
      <button class="btn btn--gold" data-modal="txModal"
              data-fill='{"id":"","type":"expense","amount":"","description":"","reference_no":"","transaction_date":"<?= date('Y-m-d') ?>"}'
              data-fill-text='{"title":"Record an Expense"}'>
        <?= icon('arrow-up', 17) ?> Add Expense
      </button>
      <button class="btn btn--primary" data-modal="txModal" data-primary-action
              data-fill='{"id":"","type":"income","amount":"","description":"","reference_no":"","transaction_date":"<?= date('Y-m-d') ?>"}'
              data-fill-text='{"title":"Record Income"}'>
        <?= icon('arrow-down', 17) ?> Add Income
      </button>
    <?php endif; ?>
  </div>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('arrow-down', 22) ?></span>
      <div>
        <div class="stat__label">Total Income</div>
        <div class="stat__value" data-count="<?= $periodIncome ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Money received in the period</span></div>
  </article>

  <article class="stat stat--red reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('arrow-up', 22) ?></span>
      <div>
        <div class="stat__label">Total Expenses</div>
        <div class="stat__value" data-count="<?= $periodExpense ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Money spent in the period</span></div>
  </article>

  <article class="stat stat--<?= $periodProfit >= 0 ? 'purple' : 'red' ?> reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('reports', 22) ?></span>
      <div>
        <div class="stat__label">Net Profit</div>
        <div class="stat__value" data-count="<?= $periodProfit ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <span class="delta delta--<?= $periodProfit >= 0 ? 'up' : 'down' ?>">
        <?= icon($periodProfit >= 0 ? 'trend-up' : 'trend-down', 13) ?><?= abs($margin) ?>%
      </span>
      <span>profit margin</span>
    </div>
  </article>

  <article class="stat stat--blue reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('activity', 22) ?></span>
      <div>
        <div class="stat__label">Transactions</div>
        <div class="stat__value" data-count="<?= $total ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Recorded entries</span></div>
  </article>
</section>

<section class="grid grid--2-1 mb-18">
  <article class="card reveal" data-delay="200">
    <div class="card__head">
      <h3><?= icon('reports', 18) ?> Income vs Expenses</h3>
      <span class="card__sub">Last 12 months</span>
    </div>
    <div class="card__body">
      <div data-chart><script type="application/json"><?= json_encode($ledgerChart) ?></script></div>
    </div>
  </article>

  <article class="card reveal" data-delay="240">
    <div class="card__head"><h3><?= icon('chart-pie', 18) ?> Where Money Goes</h3></div>
    <div class="card__body">
      <?php if ($expenseByCat): ?>
        <div data-chart><script type="application/json"><?= json_encode($expenseChart) ?></script></div>
      <?php else: ?>
        <p class="muted small text-c">No expenses in this period.</p>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="grid grid--2-1 mb-18">
  <article class="card reveal" data-delay="260">
    <div class="card__head">
      <h3><?= icon('activity', 18) ?> Net Profit Trend</h3>
      <span class="card__sub">Income minus expenses, month by month</span>
    </div>
    <div class="card__body">
      <div data-chart><script type="application/json"><?= json_encode($profitChart) ?></script></div>
    </div>
  </article>

  <article class="card reveal" data-delay="300">
    <div class="card__head"><h3><?= icon('trend-up', 18) ?> Income Sources</h3></div>
    <div class="card__body">
      <?php if (!$incomeByCat): ?>
        <p class="muted small text-c">No income in this period.</p>
      <?php else: ?>
        <?php foreach ($incomeByCat as $row): ?>
          <div class="metric-row">
            <span class="metric-row__text">
              <span class="metric-row__name"><?= e($row['category']) ?></span>
              <div class="progress mt-8" style="height:6px">
                <div class="progress__fill" data-value="<?= percent_of($row['total'], $periodIncome) ?>"></div>
              </div>
            </span>
            <span class="metric-row__val">
              <?= money_short($row['total']) ?>
              <div class="tiny muted text-r"><?= percent_of($row['total'], $periodIncome) ?>%</div>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="card reveal" data-delay="340">
  <form class="toolbar" method="get">
    <div class="field-inline">
      <?= icon('search', 16) ?>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search description or reference…">
    </div>
    <div class="field-inline">
      <?= icon('calendar', 16) ?>
      <input type="date" name="from" value="<?= e($from) ?>" style="min-width:148px">
    </div>
    <div class="field-inline">
      <?= icon('arrow-right', 16) ?>
      <input type="date" name="to" value="<?= e($to) ?>" style="min-width:148px">
    </div>
    <div class="field-inline">
      <?= icon('filter', 16) ?>
      <select name="type" data-autosubmit>
        <option value="">All types</option>
        <option value="income"<?= selected($type, 'income') ?>>Income only</option>
        <option value="expense"<?= selected($type, 'expense') ?>>Expenses only</option>
      </select>
    </div>
    <div class="field-inline">
      <?= icon('tag', 16) ?>
      <select name="category" data-autosubmit>
        <option value="">All categories</option>
        <?php foreach ($allCategories as $c): ?>
          <option value="<?= e($c['category']) ?>"<?= selected($category, $c['category']) ?>><?= e($c['category']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--sm btn--soft" type="submit"><?= icon('search', 15) ?> Apply</button>
    <a class="btn btn--sm btn--plain" href="<?= url('pages/finance.php') ?>"><?= icon('refresh', 15) ?> Reset</a>
  </form>

  <?php if (!$transactions): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('finance', 30) ?></span>
      <h3>No transactions found</h3>
      <p>Nothing was recorded in this period, or the filters are too narrow.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Category</th>
            <th>Method</th>
            <th>Reference</th>
            <th class="num">Amount</th>
            <?php if ($canManage): ?><th class="actions">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $t): ?>
            <?php $isIncome = $t['type'] === 'income'; ?>
            <tr>
              <td class="small nowrap"><?= fdate($t['transaction_date']) ?></td>
              <td>
                <div class="cellmain">
                  <span class="tile tile--sm <?= $isIncome ? '' : 'tile--red' ?>">
                    <?= icon($isIncome ? 'arrow-down' : 'arrow-up', 15) ?>
                  </span>
                  <span class="cellmain__text">
                    <span class="cellmain__title"><?= e($t['description'] ?: label($t['type'])) ?></span>
                    <span class="cellmain__sub"><?= e($t['recorder'] ?? 'System') ?></span>
                  </span>
                </div>
              </td>
              <td><span class="badge badge--neutral"><?= e($t['category']) ?></span></td>
              <td class="small soft"><?= e(label($t['payment_method'])) ?></td>
              <td class="small muted mono"><?= e($t['reference_no'] ?: '—') ?></td>
              <td class="num tnum bold <?= $isIncome ? 'c-success' : 'c-danger' ?>">
                <?= $isIncome ? '+' : '−' ?> <?= money($t['amount'], false) ?>
              </td>
              <?php if ($canManage): ?>
                <td class="actions">
                  <div>
                    <button class="rowbtn" title="Edit"
                            data-modal="txModal"
                            data-fill='<?= e(json_encode([
                                'id'               => $t['id'],
                                'type'             => $t['type'],
                                'category'         => $t['category'],
                                'amount'           => $t['amount'],
                                'description'      => $t['description'],
                                'reference_no'     => $t['reference_no'],
                                'payment_method'   => $t['payment_method'],
                                'transaction_date' => $t['transaction_date'],
                            ])) ?>'
                            data-fill-text='<?= e(json_encode(['title' => 'Edit Transaction'])) ?>'>
                      <?= icon('edit', 16) ?>
                    </button>
                    <button class="rowbtn rowbtn--danger" title="Delete"
                            data-modal="deleteModal"
                            data-fill='<?= e(json_encode(['id' => $t['id']])) ?>'
                            data-fill-text='<?= e(json_encode(['name' => label($t['type']) . ' of ' . money($t['amount'])])) ?>'>
                      <?= icon('trash', 16) ?>
                    </button>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--surface-2);font-weight:700">
            <td colspan="5">Period totals</td>
            <td class="num tnum">
              <span class="c-success">+<?= money($periodIncome, false) ?></span><br>
              <span class="c-danger">−<?= money($periodExpense, false) ?></span>
            </td>
            <?php if ($canManage): ?><td></td><?php endif; ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <?= render_pagination($page) ?>
  <?php endif; ?>
</section>

<?php if ($canManage): ?>
<div class="modal" id="txModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('finance', 19) ?> <span data-text="title">Record a Transaction</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label>Transaction type <span class="req">*</span></label>
          <div class="radiocards" style="grid-template-columns:1fr 1fr">
            <label class="radiocard">
              <input type="radio" name="type" value="income" checked>
              <span class="radiocard__body">
                <?= icon('arrow-down', 18) ?>
                <span>
                  <span class="radiocard__title">Income</span>
                  <span class="radiocard__sub">Money coming in</span>
                </span>
              </span>
            </label>
            <label class="radiocard">
              <input type="radio" name="type" value="expense">
              <span class="radiocard__body">
                <?= icon('arrow-up', 18) ?>
                <span>
                  <span class="radiocard__title">Expense</span>
                  <span class="radiocard__sub">Money going out</span>
                </span>
              </span>
            </label>
          </div>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="category">Category <span class="req">*</span></label>
            <input class="input" type="text" id="category" name="category" list="catList"
                   placeholder="e.g. Crop Sales" required>
            <datalist id="catList">
              <?php foreach (array_merge($incomeCats, $expenseCats) as $c): ?>
                <option value="<?= e($c) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="field field--money">
            <label for="amount">Amount <span class="req">*</span></label>
            <span class="prefix"><?= e(currency()) ?></span>
            <input class="input" type="number" step="0.01" min="0.01" id="amount" name="amount"
                   placeholder="0.00" required>
          </div>
        </div>

        <div class="field">
          <label for="description">Description</label>
          <input class="input" type="text" id="description" name="description"
                 placeholder="e.g. Maize harvest sold to Ejisu aggregator">
        </div>

        <div class="form__row--3 form__row">
          <div class="field">
            <label for="transaction_date">Date <span class="req">*</span></label>
            <input class="input" type="date" id="transaction_date" name="transaction_date"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="field">
            <label for="payment_method">Method <span class="req">*</span></label>
            <select class="select" id="payment_method" name="payment_method" required>
              <?php foreach ($methods as $m): ?>
                <option value="<?= $m ?>"><?= e(label($m)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="reference_no">Reference</label>
            <input class="input mono" type="text" id="reference_no" name="reference_no" placeholder="INV-001">
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Transaction</button>
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
        <h3>Delete this transaction?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> will be removed from the ledger and the
          totals will be recalculated.
        </p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Delete</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
