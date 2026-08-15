<?php
/**
 * ---------------------------------------------------------------------
 *  Livestock register — full create / read / update / delete
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$canManage = can('livestock.manage');
$errors    = [];

// =====================================================================
//  WRITE OPERATIONS
// =====================================================================
if (is_post()) {
    csrf_verify();
    require_capability('livestock.manage');

    $action = post('action');

    if ($action === 'delete') {
        $id     = post_int('id');
        $animal = one('SELECT tag_number FROM livestock WHERE id = ?', [$id]);

        if ($animal) {
            delete_row('livestock', $id);
            log_activity('livestock', 'delete', 'Removed animal ' . $animal['tag_number']);
            flash('success', 'Animal ' . $animal['tag_number'] . ' was removed from the register.');
        }
        redirect('pages/livestock.php' . (get_param('q') ? '?q=' . urlencode(get_param('q')) : ''));
    }

    // --- Validate a create or an update -------------------------------
    $id = post_int('id');

    $errors = validate([
        'tag_number'  => ['required' => true, 'max' => 40, 'label' => 'Tag number'],
        'category_id' => ['required' => true, 'label' => 'Category'],
        'gender'      => ['required' => true, 'in' => ['male', 'female']],
        'status'      => ['required' => true],
        'weight_kg'   => ['numeric' => true, 'gte' => 0, 'label' => 'Weight'],
        'acquisition_cost' => ['numeric' => true, 'gte' => 0, 'label' => 'Acquisition cost'],
        'date_of_birth'    => ['date' => true, 'label' => 'Date of birth'],
    ]);

    if (!$errors && !is_unique('livestock', 'tag_number', post('tag_number'), $id ?: null)) {
        $errors['tag_number'] = 'That tag number is already used by another animal.';
    }

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'tag_number'       => post('tag_number'),
            'category_id'      => post_int('category_id'),
            'name'             => post_or_null('name'),
            'breed'            => post_or_null('breed'),
            'gender'           => post('gender'),
            'date_of_birth'    => post_or_null('date_of_birth'),
            'weight_kg'        => post_num('weight_kg'),
            'status'           => post('status'),
            'acquisition_date' => post_or_null('acquisition_date'),
            'acquisition_cost' => post_num('acquisition_cost'),
            'notes'            => post_or_null('notes'),
        ];

        if ($id > 0) {
            update('livestock', $data, $id);
            log_activity('livestock', 'update', 'Updated animal ' . $data['tag_number']);
            flash('success', 'Animal ' . $data['tag_number'] . ' was updated.');
        } else {
            $data['created_by'] = current_user()['id'];
            insert('livestock', $data);
            log_activity('livestock', 'create', 'Added animal ' . $data['tag_number']);
            flash('success', 'Animal ' . $data['tag_number'] . ' was added to the register.');
        }
        redirect('pages/livestock.php');
    }
}

// =====================================================================
//  FILTERS, SEARCH AND PAGINATION
// =====================================================================
$search   = get_param('q');
$category = get_param('category');
$status   = get_param('status');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(l.tag_number LIKE ? OR l.name LIKE ? OR l.breed LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($category !== '') {
    $where[]  = 'l.category_id = ?';
    $params[] = (int) $category;
}
if ($status !== '') {
    $where[]  = 'l.status = ?';
    $params[] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) scalar("SELECT COUNT(*) FROM livestock l $whereSql", $params);
$page  = paginate($total);

$animals = all(
    "SELECT l.*, c.name AS category_name, c.icon AS category_icon,
            (SELECT COUNT(*) FROM health_records h WHERE h.livestock_id = l.id) AS health_count
       FROM livestock l
       JOIN livestock_categories c ON c.id = l.category_id
       $whereSql
      ORDER BY l.tag_number ASC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$categories = all('SELECT * FROM livestock_categories ORDER BY name');

// --- Summary strip ----------------------------------------------------
$sumTotal     = (int) scalar("SELECT COUNT(*) FROM livestock WHERE status NOT IN ('sold','deceased')");
$sumHealthy   = (int) scalar("SELECT COUNT(*) FROM livestock WHERE status = 'healthy'");
$sumAttention = (int) scalar("SELECT COUNT(*) FROM livestock WHERE status IN ('sick','quarantine')");
$sumPregnant  = (int) scalar("SELECT COUNT(*) FROM livestock WHERE status = 'pregnant'");
$herdValue    = (float) scalar("SELECT COALESCE(SUM(acquisition_cost),0) FROM livestock WHERE status NOT IN ('sold','deceased')");

$statuses = ['healthy', 'sick', 'quarantine', 'pregnant', 'sold', 'deceased'];

$pageTitle    = 'Livestock';
$pageSubtitle = 'Every animal on the farm, with its health and breeding status.';
$activeNav    = 'livestock';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Livestock' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('livestock', 24, 'c-brand') ?> Livestock Register</h1>
    <p><?= number_format($sumTotal) ?> animals on the farm · herd value <?= money($herdValue) ?></p>
  </div>
  <?php if ($canManage): ?>
    <div class="pagehead__actions">
      <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
      <button class="btn btn--primary" data-modal="animalModal" data-primary-action
              data-fill='{"id":"","tag_number":"","name":"","breed":"","weight_kg":"","date_of_birth":"","acquisition_date":"","acquisition_cost":"","notes":""}'
              data-fill-text='{"title":"Add New Animal"}'>
        <?= icon('plus', 17) ?> Add Animal
      </button>
    </div>
  <?php endif; ?>
</div>

<!-- Summary tiles -->
<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('livestock', 22) ?></span>
      <div>
        <div class="stat__label">On Farm</div>
        <div class="stat__value" data-count="<?= $sumTotal ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span><?= count($categories) ?> categories tracked</span></div>
  </article>

  <article class="stat stat--teal reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('success', 22) ?></span>
      <div>
        <div class="stat__label">Healthy</div>
        <div class="stat__value" data-count="<?= $sumHealthy ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span><?= percent_of($sumHealthy, $sumTotal) ?>% of the herd</span></div>
  </article>

  <article class="stat stat--red reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('health', 22) ?></span>
      <div>
        <div class="stat__label">Need Attention</div>
        <div class="stat__value" data-count="<?= $sumAttention ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Sick or in quarantine</span></div>
  </article>

  <article class="stat stat--purple reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('sparkle', 22) ?></span>
      <div>
        <div class="stat__label">Expecting</div>
        <div class="stat__value" data-count="<?= $sumPregnant ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Pregnant animals</span></div>
  </article>
</section>

<!-- Register -->
<section class="card reveal" data-delay="220">
  <form class="toolbar" method="get">
    <div class="field-inline">
      <?= icon('search', 16) ?>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search tag, name or breed…">
    </div>

    <div class="field-inline">
      <?= icon('livestock', 16) ?>
      <select name="category" data-autosubmit>
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"<?= selected($category, $cat['id']) ?>><?= e($cat['name']) ?></option>
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
    <?php if ($search || $category || $status): ?>
      <a class="btn btn--sm btn--plain" href="<?= url('pages/livestock.php') ?>"><?= icon('close', 15) ?> Clear</a>
    <?php endif; ?>

    <div class="toolbar__spacer"></div>
    <span class="small muted nowrap"><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?></span>
  </form>

  <?php if (!$animals): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('livestock', 30) ?></span>
      <h3>No animals found</h3>
      <p>
        <?= $search || $category || $status
            ? 'No animal matches the filters you selected. Try clearing them.'
            : 'The register is empty. Add your first animal to get started.' ?>
      </p>
      <?php if ($canManage && !$search && !$category && !$status): ?>
        <button class="btn btn--primary mt-14" data-modal="animalModal"><?= icon('plus', 17) ?> Add Animal</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th>Animal</th>
            <th>Category</th>
            <th>Breed</th>
            <th>Sex</th>
            <th>Age</th>
            <th class="num">Weight</th>
            <th>Status</th>
            <th class="num">Value</th>
            <?php if ($canManage): ?><th class="actions">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($animals as $a): ?>
            <tr>
              <td>
                <div class="cellmain">
                  <span class="tile"><?= icon(category_icon($a['category_icon']), 19) ?></span>
                  <span class="cellmain__text">
                    <span class="cellmain__title mono"><?= e($a['tag_number']) ?></span>
                    <span class="cellmain__sub"><?= e($a['name'] ?: 'Unnamed') ?></span>
                  </span>
                </div>
              </td>
              <td><?= e($a['category_name']) ?></td>
              <td class="soft"><?= e($a['breed'] ?: '—') ?></td>
              <td>
                <span class="badge badge--<?= $a['gender'] === 'female' ? 'purple' : 'info' ?>">
                  <i class="badge__dot"></i><?= e(label($a['gender'])) ?>
                </span>
              </td>
              <td class="soft small"><?= e(age_from($a['date_of_birth'])) ?></td>
              <td class="num tnum"><?= qty($a['weight_kg'], 1) ?> kg</td>
              <td><?= badge($a['status']) ?></td>
              <td class="num tnum"><?= money($a['acquisition_cost'], false) ?></td>
              <?php if ($canManage): ?>
                <td class="actions">
                  <div>
                    <a class="rowbtn" href="<?= url('pages/health.php?animal=' . $a['id']) ?>"
                       title="Health records (<?= (int) $a['health_count'] ?>)"><?= icon('health', 16) ?></a>
                    <button class="rowbtn" title="Edit"
                            data-modal="animalModal"
                            data-fill='<?= e(json_encode([
                                'id'               => $a['id'],
                                'tag_number'       => $a['tag_number'],
                                'category_id'      => $a['category_id'],
                                'name'             => $a['name'],
                                'breed'            => $a['breed'],
                                'gender'           => $a['gender'],
                                'date_of_birth'    => $a['date_of_birth'],
                                'weight_kg'        => $a['weight_kg'],
                                'status'           => $a['status'],
                                'acquisition_date' => $a['acquisition_date'],
                                'acquisition_cost' => $a['acquisition_cost'],
                                'notes'            => $a['notes'],
                            ])) ?>'
                            data-fill-text='<?= e(json_encode(['title' => 'Edit ' . $a['tag_number']])) ?>'>
                      <?= icon('edit', 16) ?>
                    </button>
                    <button class="rowbtn rowbtn--danger" title="Delete"
                            data-modal="deleteModal"
                            data-fill='<?= e(json_encode(['id' => $a['id']])) ?>'
                            data-fill-text='<?= e(json_encode(['name' => $a['tag_number'] . ($a['name'] ? ' (' . $a['name'] . ')' : '')])) ?>'>
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
<!-- ===================== ADD / EDIT MODAL ===================== -->
<div class="modal" id="animalModal">
  <div class="modal__panel modal__panel--wide">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('livestock', 19) ?> <span data-text="title">Add New Animal</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="formsection">
          <div class="formsection__head"><?= icon('tag', 16) ?><h4>Identification</h4></div>

          <div class="form__row--3 form__row">
            <div class="field">
              <label for="tag_number">Tag number <span class="req">*</span></label>
              <input class="input mono" type="text" id="tag_number" name="tag_number"
                     placeholder="CT-001" required>
            </div>
            <div class="field">
              <label for="name">Name</label>
              <input class="input" type="text" id="name" name="name" placeholder="Optional">
            </div>
            <div class="field">
              <label for="category_id">Category <span class="req">*</span></label>
              <select class="select" id="category_id" name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form__row--3 form__row">
            <div class="field">
              <label for="breed">Breed</label>
              <input class="input" type="text" id="breed" name="breed" placeholder="e.g. Friesian Cross">
            </div>
            <div class="field">
              <label for="gender">Sex <span class="req">*</span></label>
              <select class="select" id="gender" name="gender" required>
                <option value="female">Female</option>
                <option value="male">Male</option>
              </select>
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
        </div>

        <div class="divider"></div>

        <div class="formsection">
          <div class="formsection__head"><?= icon('weight', 16) ?><h4>Physical &amp; acquisition</h4></div>

          <div class="form__row--3 form__row">
            <div class="field">
              <label for="date_of_birth">Date of birth</label>
              <input class="input" type="date" id="date_of_birth" name="date_of_birth">
            </div>
            <div class="field">
              <label for="weight_kg">Weight (kg)</label>
              <input class="input" type="number" step="0.01" min="0" id="weight_kg" name="weight_kg" placeholder="0.00">
            </div>
            <div class="field">
              <label for="acquisition_date">Acquired on</label>
              <input class="input" type="date" id="acquisition_date" name="acquisition_date">
            </div>
          </div>

          <div class="field field--money">
            <label for="acquisition_cost">Acquisition cost</label>
            <span class="prefix"><?= e(currency()) ?></span>
            <input class="input" type="number" step="0.01" min="0" id="acquisition_cost"
                   name="acquisition_cost" placeholder="0.00">
          </div>

          <div class="field">
            <label for="notes">Notes</label>
            <textarea class="textarea" id="notes" name="notes"
                      placeholder="Anything worth remembering about this animal…"></textarea>
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Animal</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== DELETE CONFIRMATION ===================== -->
<div class="modal" id="deleteModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="">

      <div class="modal__body text-c">
        <span class="confirm-art"><?= icon('trash', 26) ?></span>
        <h3>Remove this animal?</h3>
        <p class="soft small mt-8">
          You are about to delete <strong data-text="name"></strong> from the register.
          Its health records will be removed as well. This cannot be undone.
        </p>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Keep it</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Yes, delete</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
