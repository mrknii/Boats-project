<?php
/**
 * ---------------------------------------------------------------------
 *  Fields / land parcels
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$canManage = can('fields.manage');

if (is_post()) {
    csrf_verify();
    require_capability('fields.manage');

    if (post('action') === 'delete') {
        $id    = post_int('id');
        $field = one('SELECT name FROM fields WHERE id = ?', [$id]);
        $used  = (int) scalar('SELECT COUNT(*) FROM crops WHERE field_id = ?', [$id]);

        if ($used > 0) {
            flash('danger', 'That field still has ' . $used . ' crop record(s) attached. Remove them first.');
        } elseif ($field) {
            delete_row('fields', $id);
            log_activity('fields', 'delete', 'Deleted field ' . $field['name']);
            flash('success', $field['name'] . ' was deleted.');
        }
        redirect('pages/fields.php');
    }

    $id = post_int('id');

    $errors = validate([
        'name'       => ['required' => true, 'max' => 80, 'label' => 'Field name'],
        'size_acres' => ['required' => true, 'numeric' => true, 'gte' => 0, 'label' => 'Size'],
        'soil_type'  => ['required' => true, 'label' => 'Soil type'],
        'status'     => ['required' => true],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'name'       => post('name'),
            'size_acres' => post_num('size_acres'),
            'soil_type'  => post('soil_type'),
            'location'   => post_or_null('location'),
            'status'     => post('status'),
        ];

        if ($id > 0) {
            update('fields', $data, $id);
            log_activity('fields', 'update', 'Updated field ' . $data['name']);
            flash('success', $data['name'] . ' was updated.');
        } else {
            insert('fields', $data);
            log_activity('fields', 'create', 'Added field ' . $data['name']);
            flash('success', $data['name'] . ' was added.');
        }
        redirect('pages/fields.php');
    }
}

$fields = all(
    "SELECT f.*,
            (SELECT COUNT(*) FROM crops c WHERE c.field_id = f.id AND c.status NOT IN ('harvested','failed')) AS active_crops,
            (SELECT COALESCE(SUM(c.area_planted),0) FROM crops c WHERE c.field_id = f.id AND c.status NOT IN ('harvested','failed')) AS used_acres
       FROM fields f
      ORDER BY f.name"
);

$totalAcres  = array_sum(array_column($fields, 'size_acres'));
$usedAcres   = array_sum(array_column($fields, 'used_acres'));
$soilTypes   = ['loamy', 'clay', 'sandy', 'silt', 'peaty', 'chalky'];
$fieldStates = ['available', 'cultivated', 'fallow', 'preparation'];

$soilChart = [
    'type'        => 'donut',
    'size'        => 200,
    'thickness'   => 26,
    'centerValue' => qty($totalAcres, 1),
    'centerLabel' => 'Acres',
    'suffix'      => ' ac',
    'data'        => [],
];
foreach (all('SELECT soil_type, SUM(size_acres) AS total FROM fields GROUP BY soil_type') as $row) {
    $soilChart['data'][] = ['label' => label($row['soil_type']), 'value' => (float) $row['total']];
}

$pageTitle    = 'Fields';
$pageSubtitle = 'The land you farm, parcel by parcel.';
$activeNav    = 'fields';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Fields' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('fields', 24, 'c-brand') ?> Fields &amp; Land</h1>
    <p><?= count($fields) ?> parcels · <?= qty($totalAcres, 2) ?> acres total · <?= qty($usedAcres, 2) ?> acres planted</p>
  </div>
  <?php if ($canManage): ?>
    <div class="pagehead__actions">
      <button class="btn btn--primary" data-modal="fieldModal" data-primary-action
              data-fill='{"id":"","name":"","size_acres":"","location":""}'
              data-fill-text='{"title":"Add a Field"}'>
        <?= icon('plus', 17) ?> Add Field
      </button>
    </div>
  <?php endif; ?>
</div>

<section class="grid grid--1-2 mb-18">
  <article class="card reveal">
    <div class="card__head"><h3><?= icon('chart-pie', 18) ?> Land by Soil Type</h3></div>
    <div class="card__body">
      <?php if ($soilChart['data']): ?>
        <div data-chart><script type="application/json"><?= json_encode($soilChart) ?></script></div>
      <?php else: ?>
        <p class="muted small text-c">Add a field to see the breakdown.</p>
      <?php endif; ?>
    </div>
  </article>

  <article class="card reveal" data-delay="80">
    <div class="card__head">
      <h3><?= icon('activity', 18) ?> Land Utilisation</h3>
      <span class="card__sub">Planted area against parcel size</span>
    </div>
    <div class="card__body">
      <?php if (!$fields): ?>
        <p class="muted small text-c">No fields registered yet.</p>
      <?php else: ?>
        <?php foreach ($fields as $f): ?>
          <?php $use = $f['size_acres'] > 0 ? percent_of($f['used_acres'], $f['size_acres']) : 0; ?>
          <div class="metric-row">
            <span class="tile tile--sm <?= $use > 90 ? 'tile--gold' : '' ?>"><?= icon('fields', 15) ?></span>
            <span class="metric-row__text">
              <span class="metric-row__name"><?= e($f['name']) ?></span>
              <div class="progress <?= $use > 90 ? 'progress--gold' : '' ?> mt-8" style="height:6px">
                <div class="progress__fill" data-value="<?= min(100, $use) ?>"></div>
              </div>
            </span>
            <span class="metric-row__val small">
              <?= qty($f['used_acres'], 1) ?> / <?= qty($f['size_acres'], 1) ?> ac
              <div class="tiny muted text-r"><?= $use ?>% used</div>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="card reveal" data-delay="140">
  <div class="card__head">
    <h3><?= icon('list', 18) ?> All Fields</h3>
    <div class="card__actions">
      <div class="field-inline">
        <?= icon('search', 15) ?>
        <input type="text" data-filter-table="#fieldsTable" placeholder="Filter fields…" style="height:33px">
      </div>
    </div>
  </div>

  <?php if (!$fields): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('fields', 30) ?></span>
      <h3>No fields yet</h3>
      <p>Register the parcels of land you farm so crops can be assigned to them.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table" id="fieldsTable">
        <thead>
          <tr>
            <th>Field</th>
            <th>Soil</th>
            <th class="num">Size</th>
            <th class="num">Planted</th>
            <th class="num">Crops</th>
            <th>Status</th>
            <?php if ($canManage): ?><th class="actions">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fields as $f): ?>
            <tr>
              <td>
                <div class="cellmain">
                  <span class="tile"><?= icon('fields', 19) ?></span>
                  <span class="cellmain__text">
                    <span class="cellmain__title"><?= e($f['name']) ?></span>
                    <span class="cellmain__sub"><?= e($f['location'] ?: 'No location noted') ?></span>
                  </span>
                </div>
              </td>
              <td><span class="badge badge--neutral"><?= e(label($f['soil_type'])) ?></span></td>
              <td class="num tnum"><?= qty($f['size_acres'], 2) ?> ac</td>
              <td class="num tnum"><?= qty($f['used_acres'], 2) ?> ac</td>
              <td class="num tnum"><?= (int) $f['active_crops'] ?></td>
              <td><?= badge($f['status']) ?></td>
              <?php if ($canManage): ?>
                <td class="actions">
                  <div>
                    <a class="rowbtn" title="View crops"
                       href="<?= url('pages/crops.php?field=' . $f['id']) ?>"><?= icon('crops', 16) ?></a>
                    <button class="rowbtn" title="Edit"
                            data-modal="fieldModal"
                            data-fill='<?= e(json_encode([
                                'id'         => $f['id'],
                                'name'       => $f['name'],
                                'size_acres' => $f['size_acres'],
                                'soil_type'  => $f['soil_type'],
                                'location'   => $f['location'],
                                'status'     => $f['status'],
                            ])) ?>'
                            data-fill-text='<?= e(json_encode(['title' => 'Edit ' . $f['name']])) ?>'>
                      <?= icon('edit', 16) ?>
                    </button>
                    <button class="rowbtn rowbtn--danger" title="Delete"
                            data-modal="deleteModal"
                            data-fill='<?= e(json_encode(['id' => $f['id']])) ?>'
                            data-fill-text='<?= e(json_encode(['name' => $f['name']])) ?>'>
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
  <?php endif; ?>
</section>

<?php if ($canManage): ?>
<div class="modal" id="fieldModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('fields', 19) ?> <span data-text="title">Add a Field</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label for="name">Field name <span class="req">*</span></label>
          <input class="input" type="text" id="name" name="name" placeholder="e.g. North Block" required>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="size_acres">Size (acres) <span class="req">*</span></label>
            <input class="input" type="number" step="0.01" min="0" id="size_acres"
                   name="size_acres" placeholder="0.00" required>
          </div>
          <div class="field">
            <label for="soil_type">Soil type <span class="req">*</span></label>
            <select class="select" id="soil_type" name="soil_type" required>
              <?php foreach ($soilTypes as $s): ?>
                <option value="<?= $s ?>"><?= e(label($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field field--icon">
          <label for="location">Location description</label>
          <span class="field__icon"><?= icon('pin', 17) ?></span>
          <input class="input" type="text" id="location" name="location"
                 placeholder="e.g. Along the river bank">
        </div>

        <div class="field">
          <label for="status">Status <span class="req">*</span></label>
          <select class="select" id="status" name="status" required>
            <?php foreach ($fieldStates as $s): ?>
              <option value="<?= $s ?>"><?= e(label($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Field</button>
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
        <h3>Delete this field?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> will be removed. Fields that still carry
          crop records cannot be deleted.
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
