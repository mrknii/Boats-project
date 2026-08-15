<?php
/**
 * ---------------------------------------------------------------------
 *  Veterinary health records
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_capability('health.manage');

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'delete') {
        $id = post_int('id');
        if ($id > 0) {
            delete_row('health_records', $id);
            log_activity('health', 'delete', 'Deleted health record #' . $id);
            flash('success', 'The health record was deleted.');
        }
        redirect('pages/health.php');
    }

    $id = post_int('id');

    $errors = validate([
        'livestock_id'   => ['required' => true, 'label' => 'Animal'],
        'record_type'    => ['required' => true, 'label' => 'Record type'],
        'description'    => ['required' => true, 'max' => 255],
        'treatment_date' => ['required' => true, 'date' => true, 'label' => 'Treatment date'],
        'cost'           => ['numeric' => true, 'gte' => 0],
        'next_due_date'  => ['date' => true, 'label' => 'Next due date'],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'livestock_id'   => post_int('livestock_id'),
            'record_type'    => post('record_type'),
            'description'    => post('description'),
            'medication'     => post_or_null('medication'),
            'vet_name'       => post_or_null('vet_name'),
            'cost'           => post_num('cost'),
            'treatment_date' => post('treatment_date'),
            'next_due_date'  => post_or_null('next_due_date'),
        ];

        $tag = (string) scalar('SELECT tag_number FROM livestock WHERE id = ?', [$data['livestock_id']], '');

        if ($id > 0) {
            update('health_records', $data, $id);
            log_activity('health', 'update', 'Updated health record for ' . $tag);
            flash('success', 'The health record for ' . $tag . ' was updated.');
        } else {
            insert('health_records', $data);
            log_activity('health', 'create', 'Logged ' . $data['record_type'] . ' for ' . $tag);
            flash('success', 'A new ' . label($data['record_type']) . ' record was logged for ' . $tag . '.');
        }
        redirect('pages/health.php');
    }
}

// --- Filters ----------------------------------------------------------
$search   = get_param('q');
$type     = get_param('type');
$animalId = (int) get_param('animal');
$due      = get_param('due');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(h.description LIKE ? OR h.vet_name LIKE ? OR l.tag_number LIKE ? OR h.medication LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($type !== '')     { $where[] = 'h.record_type = ?'; $params[] = $type; }
if ($animalId > 0)    { $where[] = 'h.livestock_id = ?'; $params[] = $animalId; }
if ($due === 'upcoming') {
    $where[] = 'h.next_due_date IS NOT NULL AND h.next_due_date >= CURDATE()';
} elseif ($due === 'overdue') {
    $where[] = 'h.next_due_date IS NOT NULL AND h.next_due_date < CURDATE()';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) scalar(
    "SELECT COUNT(*) FROM health_records h JOIN livestock l ON l.id = h.livestock_id $whereSql",
    $params
);
$page = paginate($total);

$records = all(
    "SELECT h.*, l.tag_number, l.name AS animal_name, c.name AS category_name, c.icon AS category_icon
       FROM health_records h
       JOIN livestock l ON l.id = h.livestock_id
       JOIN livestock_categories c ON c.id = l.category_id
       $whereSql
      ORDER BY h.treatment_date DESC, h.id DESC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$animals = all("SELECT id, tag_number, name FROM livestock WHERE status NOT IN ('sold','deceased') ORDER BY tag_number");
$types   = ['vaccination', 'treatment', 'checkup', 'deworming', 'surgery'];

// --- Summary ----------------------------------------------------------
$costYear   = (float) scalar('SELECT COALESCE(SUM(cost),0) FROM health_records WHERE YEAR(treatment_date) = YEAR(CURDATE())');
$dueSoon    = (int) scalar('SELECT COUNT(*) FROM health_records WHERE next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)');
$overdue    = (int) scalar('SELECT COUNT(*) FROM health_records WHERE next_due_date < CURDATE()');
$treatedNum = (int) scalar('SELECT COUNT(DISTINCT livestock_id) FROM health_records');

// Breakdown by record type for the donut
$byType = all(
    'SELECT record_type, COUNT(*) AS total FROM health_records GROUP BY record_type ORDER BY total DESC'
);
$typeChart = [
    'type'        => 'donut',
    'size'        => 190,
    'thickness'   => 26,
    'centerValue' => (string) array_sum(array_column($byType, 'total')),
    'centerLabel' => 'Records',
    'data'        => array_map(fn($r) => ['label' => label($r['record_type']), 'value' => (int) $r['total']], $byType),
];

$focusAnimal = $animalId > 0 ? one('SELECT tag_number, name FROM livestock WHERE id = ?', [$animalId]) : null;

$pageTitle    = 'Health Records';
$pageSubtitle = 'Vaccinations, treatments and veterinary visits.';
$activeNav    = 'health';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Health Records' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('health', 24, 'c-brand') ?> Health Records</h1>
    <p>
      <?php if ($focusAnimal): ?>
        Showing records for <strong><?= e($focusAnimal['tag_number']) ?></strong>
        <a class="auth__link small" href="<?= url('pages/health.php') ?>">· show all</a>
      <?php else: ?>
        <?= number_format($total) ?> records · <?= money($costYear) ?> spent on animal health this year
      <?php endif; ?>
    </p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
    <button class="btn btn--primary" data-modal="healthModal" data-primary-action
            data-fill='{"id":"","description":"","medication":"","vet_name":"","cost":"","next_due_date":"","treatment_date":"<?= date('Y-m-d') ?>"}'
            data-fill-text='{"title":"Log Health Record"}'>
      <?= icon('plus', 17) ?> Log Record
    </button>
  </div>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('health', 22) ?></span>
      <div>
        <div class="stat__label">Animals Treated</div>
        <div class="stat__value" data-count="<?= $treatedNum ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Across all record types</span></div>
  </article>

  <article class="stat stat--gold reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('clock', 22) ?></span>
      <div>
        <div class="stat__label">Due In 14 Days</div>
        <div class="stat__value" data-count="<?= $dueSoon ?>">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <a class="auth__link small" href="<?= url('pages/health.php?due=upcoming') ?>">View schedule</a>
    </div>
  </article>

  <article class="stat stat--red reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('warning', 22) ?></span>
      <div>
        <div class="stat__label">Overdue</div>
        <div class="stat__value" data-count="<?= $overdue ?>">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <a class="auth__link small" href="<?= url('pages/health.php?due=overdue') ?>">Follow up now</a>
    </div>
  </article>

  <article class="stat stat--blue reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('finance', 22) ?></span>
      <div>
        <div class="stat__label">Vet Spend (Year)</div>
        <div class="stat__value" data-count="<?= $costYear ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Medication and visits</span></div>
  </article>
</section>

<section class="grid grid--2-1">
  <article class="card reveal" data-delay="220">
    <form class="toolbar" method="get">
      <?php if ($animalId): ?><input type="hidden" name="animal" value="<?= $animalId ?>"><?php endif; ?>
      <div class="field-inline">
        <?= icon('search', 16) ?>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search description, vet or tag…">
      </div>
      <div class="field-inline">
        <?= icon('filter', 16) ?>
        <select name="type" data-autosubmit>
          <option value="">All types</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= $t ?>"<?= selected($type, $t) ?>><?= e(label($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-inline">
        <?= icon('calendar', 16) ?>
        <select name="due" data-autosubmit>
          <option value="">Any due date</option>
          <option value="upcoming"<?= selected($due, 'upcoming') ?>>Upcoming</option>
          <option value="overdue"<?= selected($due, 'overdue') ?>>Overdue</option>
        </select>
      </div>
      <button class="btn btn--sm btn--soft" type="submit"><?= icon('search', 15) ?> Filter</button>
      <?php if ($search || $type || $due || $animalId): ?>
        <a class="btn btn--sm btn--plain" href="<?= url('pages/health.php') ?>"><?= icon('close', 15) ?> Clear</a>
      <?php endif; ?>
    </form>

    <?php if (!$records): ?>
      <div class="empty">
        <span class="empty__art"><?= icon('health', 30) ?></span>
        <h3>No health records</h3>
        <p>Log a vaccination, treatment or check up to build the animal health history.</p>
      </div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="table">
          <thead>
            <tr>
              <th>Animal</th>
              <th>Type</th>
              <th>Description</th>
              <th>Date</th>
              <th>Next Due</th>
              <th class="num">Cost</th>
              <th class="actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
              <?php $daysDue = days_until($r['next_due_date']); ?>
              <tr>
                <td>
                  <div class="cellmain">
                    <span class="tile tile--sm"><?= icon(category_icon($r['category_icon']), 16) ?></span>
                    <span class="cellmain__text">
                      <span class="cellmain__title mono"><?= e($r['tag_number']) ?></span>
                      <span class="cellmain__sub"><?= e($r['animal_name'] ?: $r['category_name']) ?></span>
                    </span>
                  </div>
                </td>
                <td>
                  <?= badge($r['record_type'], match ($r['record_type']) {
                      'vaccination' => 'info',
                      'treatment'   => 'warning',
                      'surgery'     => 'danger',
                      'deworming'   => 'purple',
                      default       => 'neutral',
                  }) ?>
                </td>
                <td>
                  <span class="small"><?= e($r['description']) ?></span>
                  <?php if ($r['medication'] || $r['vet_name']): ?>
                    <div class="tiny muted">
                      <?= $r['medication'] ? e($r['medication']) : '' ?>
                      <?= $r['medication'] && $r['vet_name'] ? ' · ' : '' ?>
                      <?= $r['vet_name'] ? e($r['vet_name']) : '' ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="small nowrap"><?= fdate($r['treatment_date']) ?></td>
                <td class="nowrap">
                  <?php if (!$r['next_due_date']): ?>
                    <span class="muted">—</span>
                  <?php elseif ($daysDue < 0): ?>
                    <span class="badge badge--danger"><i class="badge__dot"></i><?= abs($daysDue) ?>d overdue</span>
                  <?php elseif ($daysDue <= 14): ?>
                    <span class="badge badge--warning"><i class="badge__dot"></i>in <?= $daysDue ?>d</span>
                  <?php else: ?>
                    <span class="small soft"><?= fdate($r['next_due_date']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="num tnum"><?= money($r['cost'], false) ?></td>
                <td class="actions">
                  <div>
                    <button class="rowbtn" title="Edit"
                            data-modal="healthModal"
                            data-fill='<?= e(json_encode([
                                'id'             => $r['id'],
                                'livestock_id'   => $r['livestock_id'],
                                'record_type'    => $r['record_type'],
                                'description'    => $r['description'],
                                'medication'     => $r['medication'],
                                'vet_name'       => $r['vet_name'],
                                'cost'           => $r['cost'],
                                'treatment_date' => $r['treatment_date'],
                                'next_due_date'  => $r['next_due_date'],
                            ])) ?>'
                            data-fill-text='<?= e(json_encode(['title' => 'Edit record — ' . $r['tag_number']])) ?>'>
                      <?= icon('edit', 16) ?>
                    </button>
                    <button class="rowbtn rowbtn--danger" title="Delete"
                            data-modal="deleteModal"
                            data-fill='<?= e(json_encode(['id' => $r['id']])) ?>'
                            data-fill-text='<?= e(json_encode(['name' => label($r['record_type']) . ' — ' . $r['tag_number']])) ?>'>
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
  </article>

  <div class="grid" style="gap:18px;align-content:start">
    <article class="card reveal" data-delay="260">
      <div class="card__head"><h3><?= icon('chart-pie', 18) ?> By Record Type</h3></div>
      <div class="card__body">
        <?php if ($byType): ?>
          <div data-chart><script type="application/json"><?= json_encode($typeChart) ?></script></div>
        <?php else: ?>
          <p class="small muted text-c">Nothing logged yet.</p>
        <?php endif; ?>
      </div>
    </article>

    <article class="card reveal" data-delay="300">
      <div class="card__head"><h3><?= icon('calendar', 18) ?> Coming Up</h3></div>
      <div class="card__body card__body--flush">
        <?php
        $schedule = all(
            'SELECT h.next_due_date, h.record_type, l.tag_number
               FROM health_records h JOIN livestock l ON l.id = h.livestock_id
              WHERE h.next_due_date IS NOT NULL AND h.next_due_date >= CURDATE()
              ORDER BY h.next_due_date ASC LIMIT 6'
        );
        ?>
        <?php if (!$schedule): ?>
          <div class="empty" style="padding:30px 16px">
            <span class="empty__art"><?= icon('success', 26) ?></span>
            <h3>Nothing scheduled</h3>
            <p>No follow up treatments are pending.</p>
          </div>
        <?php else: ?>
          <?php foreach ($schedule as $s): ?>
            <div class="listrow">
              <span class="tile tile--blue tile--sm"><?= icon('calendar', 15) ?></span>
              <span class="listrow__text">
                <span class="listrow__title"><?= e(label($s['record_type'])) ?></span>
                <span class="listrow__sub"><?= e($s['tag_number']) ?> · <?= fdate($s['next_due_date']) ?></span>
              </span>
              <span class="badge badge--info"><?= days_until($s['next_due_date']) ?>d</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>
  </div>
</section>

<!-- ===================== ADD / EDIT MODAL ===================== -->
<div class="modal" id="healthModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('health', 19) ?> <span data-text="title">Log Health Record</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="form__row">
          <div class="field">
            <label for="livestock_id">Animal <span class="req">*</span></label>
            <select class="select" id="livestock_id" name="livestock_id" required>
              <?php foreach ($animals as $a): ?>
                <option value="<?= $a['id'] ?>"<?= selected($animalId, $a['id']) ?>>
                  <?= e($a['tag_number'] . ($a['name'] ? ' — ' . $a['name'] : '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="record_type">Record type <span class="req">*</span></label>
            <select class="select" id="record_type" name="record_type" required>
              <?php foreach ($types as $t): ?>
                <option value="<?= $t ?>"><?= e(label($t)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="description">Description <span class="req">*</span></label>
          <input class="input" type="text" id="description" name="description"
                 placeholder="e.g. Annual foot and mouth vaccination" required>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="medication">Medication used</label>
            <input class="input" type="text" id="medication" name="medication" placeholder="e.g. Ivermectin">
          </div>
          <div class="field">
            <label for="vet_name">Veterinarian</label>
            <input class="input" type="text" id="vet_name" name="vet_name" placeholder="e.g. Dr. Nii Armah">
          </div>
        </div>

        <div class="form__row--3 form__row">
          <div class="field">
            <label for="treatment_date">Date <span class="req">*</span></label>
            <input class="input" type="date" id="treatment_date" name="treatment_date"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="field">
            <label for="next_due_date">Next due</label>
            <input class="input" type="date" id="next_due_date" name="next_due_date">
          </div>
          <div class="field field--money">
            <label for="cost">Cost</label>
            <span class="prefix"><?= e(currency()) ?></span>
            <input class="input" type="number" step="0.01" min="0" id="cost" name="cost" placeholder="0.00">
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Record</button>
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
        <h3>Delete this record?</h3>
        <p class="soft small mt-8"><strong data-text="name"></strong> will be permanently removed.</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Delete</button>
      </div>
    </form>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
