<?php
/**
 * ---------------------------------------------------------------------
 *  Crop management — what is planted where, and how far along it is
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$canManage = can('crops.manage');

if (is_post()) {
    csrf_verify();
    require_capability('crops.manage');

    if (post('action') === 'delete') {
        $id   = post_int('id');
        $crop = one('SELECT crop_name FROM crops WHERE id = ?', [$id]);
        if ($crop) {
            delete_row('crops', $id);
            log_activity('crops', 'delete', 'Deleted crop ' . $crop['crop_name']);
            flash('success', $crop['crop_name'] . ' was removed, along with its harvest records.');
        }
        redirect('pages/crops.php');
    }

    $id = post_int('id');

    $errors = validate([
        'crop_name'     => ['required' => true, 'max' => 80, 'label' => 'Crop name'],
        'field_id'      => ['required' => true, 'label' => 'Field'],
        'planting_date' => ['required' => true, 'date' => true, 'label' => 'Planting date'],
        'status'        => ['required' => true],
        'area_planted'  => ['numeric' => true, 'gte' => 0, 'label' => 'Area planted'],
        'expected_yield'=> ['numeric' => true, 'gte' => 0, 'label' => 'Expected yield'],
        'input_cost'    => ['numeric' => true, 'gte' => 0, 'label' => 'Input cost'],
    ]);

    if (!$errors && post('expected_harvest') !== ''
        && strtotime(post('expected_harvest')) < strtotime(post('planting_date'))) {
        $errors['expected_harvest'] = 'The expected harvest date cannot be before the planting date.';
    }

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'crop_name'        => post('crop_name'),
            'variety'          => post_or_null('variety'),
            'field_id'         => post_int('field_id'),
            'area_planted'     => post_num('area_planted'),
            'planting_date'    => post('planting_date'),
            'expected_harvest' => post_or_null('expected_harvest'),
            'expected_yield'   => post_num('expected_yield'),
            'status'           => post('status'),
            'input_cost'       => post_num('input_cost'),
            'notes'            => post_or_null('notes'),
        ];

        if ($id > 0) {
            update('crops', $data, $id);
            log_activity('crops', 'update', 'Updated crop ' . $data['crop_name']);
            flash('success', $data['crop_name'] . ' was updated.');
        } else {
            $data['created_by'] = current_user()['id'];
            insert('crops', $data);
            // Keep the field status in step with reality
            update('fields', ['status' => 'cultivated'], $data['field_id']);
            log_activity('crops', 'create', 'Planted ' . $data['crop_name']);
            flash('success', $data['crop_name'] . ' was added to the season plan.');
        }
        redirect('pages/crops.php');
    }
}

// --- Filters ----------------------------------------------------------
$search = get_param('q');
$status = get_param('status');
$field  = get_param('field');

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(c.crop_name LIKE ? OR c.variety LIKE ?)';
    $like    = '%' . $search . '%';
    array_push($params, $like, $like);
}
if ($status !== '') { $where[] = 'c.status = ?';   $params[] = $status; }
if ($field !== '')  { $where[] = 'c.field_id = ?'; $params[] = (int) $field; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) scalar("SELECT COUNT(*) FROM crops c $whereSql", $params);
$page  = paginate($total);

$crops = all(
    "SELECT c.*, f.name AS field_name, f.soil_type,
            (SELECT COALESCE(SUM(h.quantity),0) FROM harvests h WHERE h.crop_id = c.id) AS harvested,
            (SELECT COALESCE(SUM(h.revenue),0)  FROM harvests h WHERE h.crop_id = c.id) AS revenue
       FROM crops c
       JOIN fields f ON f.id = c.field_id
       $whereSql
      ORDER BY FIELD(c.status,'ready','flowering','growing','planted','harvested','failed'),
               c.expected_harvest ASC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$fields   = all('SELECT * FROM fields ORDER BY name');
$statuses = ['planted', 'growing', 'flowering', 'ready', 'harvested', 'failed'];

// --- Summary ----------------------------------------------------------
$activeCount = (int) scalar("SELECT COUNT(*) FROM crops WHERE status NOT IN ('harvested','failed')");
$readyCount  = (int) scalar("SELECT COUNT(*) FROM crops WHERE status = 'ready'");
$areaTotal   = (float) scalar("SELECT COALESCE(SUM(area_planted),0) FROM crops WHERE status NOT IN ('harvested','failed')");
$inputTotal  = (float) scalar("SELECT COALESCE(SUM(input_cost),0) FROM crops WHERE status NOT IN ('harvested','failed')");

// Expected vs harvested by crop, for the comparison chart
$yieldRows = all(
    "SELECT c.crop_name,
            SUM(c.expected_yield) AS expected,
            COALESCE(SUM((SELECT SUM(h.quantity) FROM harvests h WHERE h.crop_id = c.id)),0) AS actual
       FROM crops c
      GROUP BY c.crop_name
      ORDER BY expected DESC
      LIMIT 7"
);

$yieldChart = [
    'type'   => 'bar',
    'height' => 260,
    'labels' => array_column($yieldRows, 'crop_name'),
    'suffix' => ' kg',
    'series' => [
        ['name' => 'Expected yield', 'data' => array_map('floatval', array_column($yieldRows, 'expected')), 'color' => '#63bd90'],
        ['name' => 'Harvested',      'data' => array_map('floatval', array_column($yieldRows, 'actual')),   'color' => '#d9911f'],
    ],
];

$pageTitle    = 'Crops';
$pageSubtitle = 'The season plan, field by field.';
$activeNav    = 'crops';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Crops' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('crops', 24, 'c-brand') ?> Crop Management</h1>
    <p><?= $activeCount ?> crops growing across <?= qty($areaTotal) ?> acres</p>
  </div>
  <div class="pagehead__actions">
    <a class="btn btn--ghost" href="<?= url('pages/harvests.php') ?>"><?= icon('harvest', 17) ?> Harvests</a>
    <?php if ($canManage): ?>
      <button class="btn btn--primary" data-modal="cropModal" data-primary-action
              data-fill='{"id":"","crop_name":"","variety":"","area_planted":"","expected_yield":"","input_cost":"","notes":"","expected_harvest":"","planting_date":"<?= date('Y-m-d') ?>"}'
              data-fill-text='{"title":"Plant a New Crop"}'>
        <?= icon('plus', 17) ?> Add Crop
      </button>
    <?php endif; ?>
  </div>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('crops', 22) ?></span>
      <div>
        <div class="stat__label">Crops Growing</div>
        <div class="stat__value" data-count="<?= $activeCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Across <?= count($fields) ?> fields</span></div>
  </article>

  <article class="stat stat--gold reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('harvest', 22) ?></span>
      <div>
        <div class="stat__label">Ready To Harvest</div>
        <div class="stat__value" data-count="<?= $readyCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Needs attention now</span></div>
  </article>

  <article class="stat stat--teal reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('fields', 22) ?></span>
      <div>
        <div class="stat__label">Area Planted</div>
        <div class="stat__value" data-count="<?= $areaTotal ?>" data-decimals="1" data-suffix=" ac">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Under active cultivation</span></div>
  </article>

  <article class="stat stat--purple reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('finance', 22) ?></span>
      <div>
        <div class="stat__label">Input Cost</div>
        <div class="stat__value" data-count="<?= $inputTotal ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Invested in standing crops</span></div>
  </article>
</section>

<section class="card mb-18 reveal" data-delay="200">
  <div class="card__head">
    <h3><?= icon('reports', 18) ?> Expected Yield vs Harvested</h3>
    <span class="card__sub">Totals per crop, all seasons</span>
  </div>
  <div class="card__body">
    <?php if ($yieldRows): ?>
      <div data-chart><script type="application/json"><?= json_encode($yieldChart) ?></script></div>
    <?php else: ?>
      <p class="muted small text-c">Add a crop to see the yield comparison.</p>
    <?php endif; ?>
  </div>
</section>

<section class="card reveal" data-delay="240">
  <form class="toolbar" method="get">
    <div class="field-inline">
      <?= icon('search', 16) ?>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search crop or variety…">
    </div>
    <div class="field-inline">
      <?= icon('fields', 16) ?>
      <select name="field" data-autosubmit>
        <option value="">All fields</option>
        <?php foreach ($fields as $f): ?>
          <option value="<?= $f['id'] ?>"<?= selected($field, $f['id']) ?>><?= e($f['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-inline">
      <?= icon('filter', 16) ?>
      <select name="status" data-autosubmit>
        <option value="">Any status</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>"<?= selected($status, $s) ?>><?= e(label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--sm btn--soft" type="submit"><?= icon('search', 15) ?> Filter</button>
    <?php if ($search || $status || $field): ?>
      <a class="btn btn--sm btn--plain" href="<?= url('pages/crops.php') ?>"><?= icon('close', 15) ?> Clear</a>
    <?php endif; ?>
    <div class="toolbar__spacer"></div>
    <span class="small muted nowrap"><?= number_format($total) ?> crops</span>
  </form>

  <?php if (!$crops): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('crops', 30) ?></span>
      <h3>No crops recorded</h3>
      <p>Register what you have planted so the system can track it through to harvest.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>Crop</th>
            <th>Field</th>
            <th class="num">Area</th>
            <th>Planted</th>
            <th>Progress</th>
            <th class="num">Yield</th>
            <th>Status</th>
            <?php if ($canManage): ?><th class="actions">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($crops as $c): ?>
            <?php
              $start   = strtotime($c['planting_date']);
              $end     = $c['expected_harvest'] ? strtotime($c['expected_harvest']) : $start;
              $span    = max(1, $end - $start);
              $done    = min(100, max(0, round(((time() - $start) / $span) * 100)));
              $daysOut = days_until($c['expected_harvest']);
              $tone    = $done >= 95 ? '' : ($done >= 60 ? ' progress--gold' : ' progress--blue');
              $finished = in_array($c['status'], ['harvested', 'failed'], true);
            ?>
            <tr>
              <td>
                <div class="cellmain">
                  <span class="tile"><?= icon('crops', 19) ?></span>
                  <span class="cellmain__text">
                    <span class="cellmain__title"><?= e($c['crop_name']) ?></span>
                    <span class="cellmain__sub"><?= e($c['variety'] ?: 'Standard variety') ?></span>
                  </span>
                </div>
              </td>
              <td>
                <span class="small"><?= e($c['field_name']) ?></span>
                <div class="tiny muted"><?= e(label($c['soil_type'])) ?> soil</div>
              </td>
              <td class="num tnum"><?= qty($c['area_planted'], 2) ?> ac</td>
              <td class="small nowrap"><?= fdate($c['planting_date']) ?></td>
              <td style="min-width:150px">
                <?php if ($finished): ?>
                  <span class="small muted">Season closed</span>
                <?php else: ?>
                  <div class="progress<?= $tone ?>" style="height:6px">
                    <div class="progress__fill" data-value="<?= $done ?>"></div>
                  </div>
                  <div class="tiny muted mt-8">
                    <?= $daysOut === null ? 'No harvest date' : ($daysOut < 0 ? 'Overdue by ' . abs($daysOut) . 'd' : $daysOut . ' days to harvest') ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="num tnum">
                <span class="bold"><?= qty($c['harvested']) ?></span>
                <div class="tiny muted">of <?= qty($c['expected_yield']) ?> kg</div>
              </td>
              <td><?= badge($c['status']) ?></td>
              <?php if ($canManage): ?>
                <td class="actions">
                  <div>
                    <a class="rowbtn" title="Record harvest"
                       href="<?= url('pages/harvests.php?crop=' . $c['id']) ?>"><?= icon('harvest', 16) ?></a>
                    <button class="rowbtn" title="Edit"
                            data-modal="cropModal"
                            data-fill='<?= e(json_encode([
                                'id'               => $c['id'],
                                'crop_name'        => $c['crop_name'],
                                'variety'          => $c['variety'],
                                'field_id'         => $c['field_id'],
                                'area_planted'     => $c['area_planted'],
                                'planting_date'    => $c['planting_date'],
                                'expected_harvest' => $c['expected_harvest'],
                                'expected_yield'   => $c['expected_yield'],
                                'status'           => $c['status'],
                                'input_cost'       => $c['input_cost'],
                                'notes'            => $c['notes'],
                            ])) ?>'
                            data-fill-text='<?= e(json_encode(['title' => 'Edit ' . $c['crop_name']])) ?>'>
                      <?= icon('edit', 16) ?>
                    </button>
                    <button class="rowbtn rowbtn--danger" title="Delete"
                            data-modal="deleteModal"
                            data-fill='<?= e(json_encode(['id' => $c['id']])) ?>'
                            data-fill-text='<?= e(json_encode(['name' => $c['crop_name'] . ' on ' . $c['field_name']])) ?>'>
                      <?= icon('trash', 16) ?>
                    </button>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= render_pagination($page) ?>
  <?php endif; ?>
</section>

<?php if ($canManage): ?>
<div class="modal" id="cropModal">
  <div class="modal__panel modal__panel--wide">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('crops', 19) ?> <span data-text="title">Plant a New Crop</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="formsection">
          <div class="formsection__head"><?= icon('seed', 16) ?><h4>What and where</h4></div>
          <div class="form__row--3 form__row">
            <div class="field">
              <label for="crop_name">Crop <span class="req">*</span></label>
              <input class="input" type="text" id="crop_name" name="crop_name" list="cropList"
                     placeholder="e.g. Maize" required>
              <datalist id="cropList">
                <option value="Maize"></option><option value="Rice"></option><option value="Cassava"></option>
                <option value="Yam"></option><option value="Plantain"></option><option value="Tomato"></option>
                <option value="Pepper"></option><option value="Okra"></option><option value="Cowpea"></option>
                <option value="Groundnut"></option><option value="Cocoa"></option><option value="Soybean"></option>
              </datalist>
            </div>
            <div class="field">
              <label for="variety">Variety</label>
              <input class="input" type="text" id="variety" name="variety" placeholder="e.g. Obatanpa">
            </div>
            <div class="field">
              <label for="field_id">Field <span class="req">*</span></label>
              <select class="select" id="field_id" name="field_id" required>
                <?php foreach ($fields as $f): ?>
                  <option value="<?= $f['id'] ?>">
                    <?= e($f['name']) ?> — <?= qty($f['size_acres'], 2) ?> ac
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="divider"></div>

        <div class="formsection">
          <div class="formsection__head"><?= icon('calendar', 16) ?><h4>Season timing</h4></div>
          <div class="form__row--3 form__row">
            <div class="field">
              <label for="planting_date">Planted on <span class="req">*</span></label>
              <input class="input" type="date" id="planting_date" name="planting_date"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="field">
              <label for="expected_harvest">Expected harvest</label>
              <input class="input" type="date" id="expected_harvest" name="expected_harvest">
            </div>
            <div class="field">
              <label for="status">Status <span class="req">*</span></label>
              <select class="select" id="status" name="status" required>
                <?php foreach ($statuses as $s): ?>
                  <option value="<?= $s ?>"><?= e(label($s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form__row--3 form__row">
            <div class="field">
              <label for="area_planted">Area planted (acres)</label>
              <input class="input" type="number" step="0.01" min="0" id="area_planted"
                     name="area_planted" placeholder="0.00">
            </div>
            <div class="field">
              <label for="expected_yield">Expected yield (kg)</label>
              <input class="input" type="number" step="0.01" min="0" id="expected_yield"
                     name="expected_yield" placeholder="0.00">
            </div>
            <div class="field field--money">
              <label for="input_cost">Input cost</label>
              <span class="prefix"><?= e(currency()) ?></span>
              <input class="input" type="number" step="0.01" min="0" id="input_cost"
                     name="input_cost" placeholder="0.00">
            </div>
          </div>

          <div class="field">
            <label for="notes">Notes</label>
            <textarea class="textarea" id="notes" name="notes" style="min-height:70px"
                      placeholder="Seed source, spacing, irrigation plan…"></textarea>
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Crop</button>
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
        <h3>Delete this crop?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> and all of its harvest records will be removed.
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
