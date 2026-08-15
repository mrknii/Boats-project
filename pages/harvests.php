<?php
/**
 * ---------------------------------------------------------------------
 *  Harvest records — what actually came off each field
 * ---------------------------------------------------------------------
 *  Recording a harvest optionally posts the revenue straight into the
 *  finance ledger, so the books stay in step with the field work.
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_capability('harvest.manage');

if (is_post()) {
    csrf_verify();

    if (post('action') === 'delete') {
        $id = post_int('id');
        if ($id > 0) {
            delete_row('harvests', $id);
            log_activity('harvest', 'delete', 'Deleted harvest record #' . $id);
            flash('success', 'The harvest record was deleted.');
        }
        redirect('pages/harvests.php');
    }

    $id = post_int('id');

    $errors = validate([
        'crop_id'       => ['required' => true, 'label' => 'Crop'],
        'harvest_date'  => ['required' => true, 'date' => true, 'label' => 'Harvest date'],
        'quantity'      => ['required' => true, 'numeric' => true, 'gte' => 0],
        'unit'          => ['required' => true, 'max' => 20],
        'quality_grade' => ['required' => true, 'in' => ['A', 'B', 'C'], 'label' => 'Quality grade'],
        'revenue'       => ['numeric' => true, 'gte' => 0],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'crop_id'       => post_int('crop_id'),
            'harvest_date'  => post('harvest_date'),
            'quantity'      => post_num('quantity'),
            'unit'          => post('unit'),
            'quality_grade' => post('quality_grade'),
            'revenue'       => post_num('revenue'),
            'notes'         => post_or_null('notes'),
        ];

        $crop = one('SELECT crop_name FROM crops WHERE id = ?', [$data['crop_id']]);
        $name = $crop['crop_name'] ?? 'crop';

        if ($id > 0) {
            update('harvests', $data, $id);
            log_activity('harvest', 'update', 'Updated harvest of ' . $name);
            flash('success', 'The harvest record for ' . $name . ' was updated.');
        } else {
            insert('harvests', $data);
            log_activity('harvest', 'create',
                'Harvested ' . qty($data['quantity']) . ' ' . $data['unit'] . ' of ' . $name);

            // Optionally mark the crop as harvested
            if (post('mark_harvested') === '1') {
                update('crops', ['status' => 'harvested'], $data['crop_id']);
            }

            // Optionally post the revenue to the finance ledger
            if (post('post_to_finance') === '1' && $data['revenue'] > 0 && can('finance.manage')) {
                insert('transactions', [
                    'type'             => 'income',
                    'category'         => 'Crop Sales',
                    'amount'           => $data['revenue'],
                    'description'      => 'Harvest sale — ' . $name . ' (' . qty($data['quantity']) . ' ' . $data['unit'] . ')',
                    'reference_no'     => 'HRV-' . date('Ymd') . '-' . random_int(100, 999),
                    'payment_method'   => 'cash',
                    'transaction_date' => $data['harvest_date'],
                    'recorded_by'      => current_user()['id'],
                ]);
                flash('info', money($data['revenue']) . ' was also posted to the finance ledger as income.');
            }

            flash('success', 'Recorded ' . qty($data['quantity']) . ' ' . $data['unit'] . ' of ' . $name . '.');
        }
        redirect('pages/harvests.php');
    }
}

// --- Filters ----------------------------------------------------------
$cropId = (int) get_param('crop');
$grade  = get_param('grade');
$from   = get_param('from', date('Y-m-d', strtotime('-1 year')));
$to     = get_param('to', date('Y-m-d'));

$where  = ['h.harvest_date BETWEEN ? AND ?'];
$params = [$from, $to];

if ($cropId > 0)  { $where[] = 'h.crop_id = ?';       $params[] = $cropId; }
if ($grade !== ''){ $where[] = 'h.quality_grade = ?'; $params[] = $grade; }

$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int) scalar("SELECT COUNT(*) FROM harvests h $whereSql", $params);
$page  = paginate($total);

$harvests = all(
    "SELECT h.*, c.crop_name, c.variety, f.name AS field_name
       FROM harvests h
       JOIN crops c ON c.id = h.crop_id
       JOIN fields f ON f.id = c.field_id
       $whereSql
      ORDER BY h.harvest_date DESC, h.id DESC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$crops = all('SELECT c.id, c.crop_name, c.variety, f.name AS field_name
                FROM crops c JOIN fields f ON f.id = c.field_id
               ORDER BY c.crop_name');

// --- Summary ----------------------------------------------------------
$qtyPeriod = (float) scalar("SELECT COALESCE(SUM(quantity),0) FROM harvests h $whereSql", $params);
$revPeriod = (float) scalar("SELECT COALESCE(SUM(revenue),0)  FROM harvests h $whereSql", $params);
$gradeA    = (float) scalar("SELECT COALESCE(SUM(quantity),0) FROM harvests h $whereSql AND h.quality_grade='A'", $params);
$harvestCount = $total;

// Monthly harvest volume
$months     = month_range(12);
$volumes    = array_fill_keys(array_keys($months), 0.0);
$revenues   = array_fill_keys(array_keys($months), 0.0);

foreach (all(
    "SELECT DATE_FORMAT(harvest_date,'%Y-%m') AS m, SUM(quantity) AS q, SUM(revenue) AS r
       FROM harvests
      WHERE harvest_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY m"
) as $row) {
    if (array_key_exists($row['m'], $volumes)) {
        $volumes[$row['m']]  = (float) $row['q'];
        $revenues[$row['m']] = (float) $row['r'];
    }
}

$harvestChart = [
    'type'   => 'bar',
    'height' => 260,
    'labels' => array_values($months),
    'series' => [
        ['name' => 'Volume (kg)', 'data' => array_values($volumes), 'color' => '#16874a'],
    ],
];

$focusCrop = $cropId > 0 ? one('SELECT crop_name FROM crops WHERE id = ?', [$cropId]) : null;

$pageTitle    = 'Harvests';
$pageSubtitle = 'What each crop actually produced.';
$activeNav    = 'harvests';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Crops' => 'pages/crops.php', 'Harvests' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('harvest', 24, 'c-brand') ?> Harvest Records</h1>
    <p>
      <?php if ($focusCrop): ?>
        Showing harvests of <strong><?= e($focusCrop['crop_name']) ?></strong>
        <a class="auth__link small" href="<?= url('pages/harvests.php') ?>">· show all</a>
      <?php else: ?>
        <?= number_format($harvestCount) ?> harvests between <?= fdate($from) ?> and <?= fdate($to) ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
    <button class="btn btn--primary" data-modal="harvestModal" data-primary-action
            data-fill='{"id":"","quantity":"","revenue":"","notes":"","harvest_date":"<?= date('Y-m-d') ?>"}'
            data-fill-text='{"title":"Record a Harvest"}'>
      <?= icon('plus', 17) ?> Record Harvest
    </button>
  </div>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('harvest', 22) ?></span>
      <div>
        <div class="stat__label">Total Volume</div>
        <div class="stat__value" data-count="<?= $qtyPeriod ?>" data-decimals="0" data-suffix=" kg">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Over the selected period</span></div>
  </article>

  <article class="stat stat--gold reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('finance', 22) ?></span>
      <div>
        <div class="stat__label">Harvest Revenue</div>
        <div class="stat__value" data-count="<?= $revPeriod ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>From <?= $harvestCount ?> harvests</span></div>
  </article>

  <article class="stat stat--teal reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('success', 22) ?></span>
      <div>
        <div class="stat__label">Grade A Output</div>
        <div class="stat__value" data-count="<?= $gradeA ?>" data-decimals="0" data-suffix=" kg">0</div>
      </div>
    </div>
    <div class="stat__foot"><span><?= percent_of($gradeA, $qtyPeriod) ?>% of total volume</span></div>
  </article>

  <article class="stat stat--purple reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('reports', 22) ?></span>
      <div>
        <div class="stat__label">Average Price</div>
        <div class="stat__value" data-count="<?= $qtyPeriod > 0 ? $revPeriod / $qtyPeriod : 0 ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="2">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Per kilogram sold</span></div>
  </article>
</section>

<section class="card mb-18 reveal" data-delay="200">
  <div class="card__head">
    <h3><?= icon('activity', 18) ?> Monthly Harvest Volume</h3>
    <span class="card__sub">Last 12 months</span>
  </div>
  <div class="card__body">
    <div data-chart><script type="application/json"><?= json_encode($harvestChart) ?></script></div>
  </div>
</section>

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
      <?= icon('crops', 16) ?>
      <select name="crop" data-autosubmit>
        <option value="">All crops</option>
        <?php foreach ($crops as $c): ?>
          <option value="<?= $c['id'] ?>"<?= selected($cropId, $c['id']) ?>>
            <?= e($c['crop_name'] . ' — ' . $c['field_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-inline">
      <?= icon('filter', 16) ?>
      <select name="grade" data-autosubmit>
        <option value="">Any grade</option>
        <option value="A"<?= selected($grade, 'A') ?>>Grade A</option>
        <option value="B"<?= selected($grade, 'B') ?>>Grade B</option>
        <option value="C"<?= selected($grade, 'C') ?>>Grade C</option>
      </select>
    </div>
    <button class="btn btn--sm btn--soft" type="submit"><?= icon('search', 15) ?> Apply</button>
    <a class="btn btn--sm btn--plain" href="<?= url('pages/harvests.php') ?>"><?= icon('refresh', 15) ?> Reset</a>
  </form>

  <?php if (!$harvests): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('harvest', 30) ?></span>
      <h3>No harvests in this period</h3>
      <p>Record a harvest as soon as produce comes off the field.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>Crop</th>
            <th>Field</th>
            <th>Date</th>
            <th class="num">Quantity</th>
            <th>Grade</th>
            <th class="num">Revenue</th>
            <th class="num">Unit Price</th>
            <th class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($harvests as $h): ?>
            <tr>
              <td>
                <div class="cellmain">
                  <span class="tile tile--gold"><?= icon('harvest', 19) ?></span>
                  <span class="cellmain__text">
                    <span class="cellmain__title"><?= e($h['crop_name']) ?></span>
                    <span class="cellmain__sub"><?= e($h['variety'] ?: 'Standard') ?></span>
                  </span>
                </div>
              </td>
              <td class="small soft"><?= e($h['field_name']) ?></td>
              <td class="small nowrap"><?= fdate($h['harvest_date']) ?></td>
              <td class="num tnum bold"><?= qty($h['quantity']) ?> <span class="muted small"><?= e($h['unit']) ?></span></td>
              <td>
                <span class="badge badge--<?= $h['quality_grade'] === 'A' ? 'success' : ($h['quality_grade'] === 'B' ? 'warning' : 'neutral') ?>">
                  <i class="badge__dot"></i>Grade <?= e($h['quality_grade']) ?>
                </span>
              </td>
              <td class="num tnum"><?= money($h['revenue'], false) ?></td>
              <td class="num tnum muted small">
                <?= $h['quantity'] > 0 ? money($h['revenue'] / $h['quantity'], false) : '—' ?>
              </td>
              <td class="actions">
                <div>
                  <button class="rowbtn" title="Edit"
                          data-modal="harvestModal"
                          data-fill='<?= e(json_encode([
                              'id'            => $h['id'],
                              'crop_id'       => $h['crop_id'],
                              'harvest_date'  => $h['harvest_date'],
                              'quantity'      => $h['quantity'],
                              'unit'          => $h['unit'],
                              'quality_grade' => $h['quality_grade'],
                              'revenue'       => $h['revenue'],
                              'notes'         => $h['notes'],
                          ])) ?>'
                          data-fill-text='<?= e(json_encode(['title' => 'Edit harvest — ' . $h['crop_name']])) ?>'>
                    <?= icon('edit', 16) ?>
                  </button>
                  <button class="rowbtn rowbtn--danger" title="Delete"
                          data-modal="deleteModal"
                          data-fill='<?= e(json_encode(['id' => $h['id']])) ?>'
                          data-fill-text='<?= e(json_encode(['name' => qty($h['quantity']) . ' ' . $h['unit'] . ' of ' . $h['crop_name']])) ?>'>
                    <?= icon('trash', 16) ?>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--surface-2);font-weight:700">
            <td colspan="3">Page total</td>
            <td class="num tnum"><?= qty(array_sum(array_column($harvests, 'quantity'))) ?></td>
            <td></td>
            <td class="num tnum"><?= money(array_sum(array_column($harvests, 'revenue')), false) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?= render_pagination($page) ?>
  <?php endif; ?>
</section>

<div class="modal" id="harvestModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('harvest', 19) ?> <span data-text="title">Record a Harvest</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label for="crop_id">Crop <span class="req">*</span></label>
          <select class="select" id="crop_id" name="crop_id" required>
            <?php foreach ($crops as $c): ?>
              <option value="<?= $c['id'] ?>"<?= selected($cropId, $c['id']) ?>>
                <?= e($c['crop_name'] . ($c['variety'] ? ' (' . $c['variety'] . ')' : '') . ' — ' . $c['field_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form__row--3 form__row">
          <div class="field">
            <label for="harvest_date">Date <span class="req">*</span></label>
            <input class="input" type="date" id="harvest_date" name="harvest_date"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="field">
            <label for="quantity">Quantity <span class="req">*</span></label>
            <input class="input" type="number" step="0.01" min="0" id="quantity" name="quantity"
                   placeholder="0.00" required>
          </div>
          <div class="field">
            <label for="unit">Unit <span class="req">*</span></label>
            <select class="select" id="unit" name="unit" required>
              <option value="kg">kg</option>
              <option value="bags">bags</option>
              <option value="tonnes">tonnes</option>
              <option value="crates">crates</option>
              <option value="bunches">bunches</option>
            </select>
          </div>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="quality_grade">Quality grade <span class="req">*</span></label>
            <select class="select" id="quality_grade" name="quality_grade" required>
              <option value="A">Grade A — premium</option>
              <option value="B">Grade B — standard</option>
              <option value="C">Grade C — low</option>
            </select>
          </div>
          <div class="field field--money">
            <label for="revenue">Revenue</label>
            <span class="prefix"><?= e(currency()) ?></span>
            <input class="input" type="number" step="0.01" min="0" id="revenue" name="revenue" placeholder="0.00">
          </div>
        </div>

        <div class="field">
          <label for="notes">Notes</label>
          <textarea class="textarea" id="notes" name="notes" style="min-height:64px"
                    placeholder="Buyer, transport, storage…"></textarea>
        </div>

        <div class="divider"></div>

        <label class="switch mb-8">
          <input type="checkbox" name="mark_harvested" value="1">
          <span class="switch__track"></span>
          <span class="switch__label">Mark this crop as fully harvested</span>
        </label>

        <?php if (can('finance.manage')): ?>
          <label class="switch">
            <input type="checkbox" name="post_to_finance" value="1" checked>
            <span class="switch__track"></span>
            <span class="switch__label">Post the revenue to the finance ledger</span>
          </label>
        <?php endif; ?>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Harvest</button>
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
        <h3>Delete this harvest?</h3>
        <p class="soft small mt-8"><strong data-text="name"></strong> will be removed from the records.</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Delete</button>
      </div>
    </form>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
