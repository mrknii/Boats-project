<?php
/**
 * ---------------------------------------------------------------------
 *  Employee register
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_capability('employees.view');

$canManage = is_admin();

if (is_post()) {
    csrf_verify();
    require_admin();

    if (post('action') === 'delete') {
        $id  = post_int('id');
        $emp = one('SELECT full_name FROM employees WHERE id = ?', [$id]);
        if ($emp) {
            delete_row('employees', $id);
            log_activity('employees', 'delete', 'Removed employee ' . $emp['full_name']);
            flash('success', $emp['full_name'] . ' was removed from the staff register.');
        }
        redirect('pages/employees.php');
    }

    $id = post_int('id');

    $errors = validate([
        'full_name'  => ['required' => true, 'max' => 120, 'label' => 'Full name'],
        'job_title'  => ['required' => true, 'max' => 80, 'label' => 'Job title'],
        'department' => ['required' => true],
        'hire_date'  => ['required' => true, 'date' => true, 'label' => 'Hire date'],
        'status'     => ['required' => true],
        'salary'     => ['numeric' => true, 'gte' => 0],
        'email'      => ['email' => true],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'full_name'  => post('full_name'),
            'job_title'  => post('job_title'),
            'department' => post('department'),
            'phone'      => post_or_null('phone'),
            'email'      => post_or_null('email'),
            'address'    => post_or_null('address'),
            'salary'     => post_num('salary'),
            'hire_date'  => post('hire_date'),
            'status'     => post('status'),
            'user_id'    => post_int('user_id') ?: null,
        ];

        if ($id > 0) {
            update('employees', $data, $id);
            log_activity('employees', 'update', 'Updated employee ' . $data['full_name']);
            flash('success', $data['full_name'] . ' was updated.');
        } else {
            insert('employees', $data);
            log_activity('employees', 'create', 'Added employee ' . $data['full_name']);
            flash('success', $data['full_name'] . ' joined the staff register.');
        }
        redirect('pages/employees.php');
    }
}

// --- Filters ----------------------------------------------------------
$search     = get_param('q');
$department = get_param('department');
$status     = get_param('status');

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(e.full_name LIKE ? OR e.job_title LIKE ? OR e.phone LIKE ?)';
    $like    = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($department !== '') { $where[] = 'e.department = ?'; $params[] = $department; }
if ($status !== '')     { $where[] = 'e.status = ?';     $params[] = $status; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$employees = all(
    "SELECT e.*, u.username, u.role,
            (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = e.id AND t.status IN ('pending','in_progress')) AS open_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = e.id AND t.status = 'completed') AS done_tasks
       FROM employees e
       LEFT JOIN users u ON u.id = e.user_id
       $whereSql
      ORDER BY FIELD(e.status,'active','on_leave','terminated'), e.full_name",
    $params
);

$departments = ['livestock', 'crops', 'general', 'administration', 'maintenance'];
$states      = ['active', 'on_leave', 'terminated'];

$activeCount = (int) scalar("SELECT COUNT(*) FROM employees WHERE status='active'");
$leaveCount  = (int) scalar("SELECT COUNT(*) FROM employees WHERE status='on_leave'");
$payroll     = (float) scalar("SELECT COALESCE(SUM(salary),0) FROM employees WHERE status='active'");
$avgTenure   = (float) scalar("SELECT COALESCE(AVG(DATEDIFF(CURDATE(), hire_date))/365,0) FROM employees WHERE status='active'");

$byDept = all(
    "SELECT department, COUNT(*) AS total FROM employees
      WHERE status <> 'terminated' GROUP BY department ORDER BY total DESC"
);
$deptChart = [
    'type'        => 'donut',
    'size'        => 200,
    'thickness'   => 26,
    'centerValue' => (string) array_sum(array_column($byDept, 'total')),
    'centerLabel' => 'Staff',
    'data'        => array_map(fn($r) => ['label' => label($r['department']), 'value' => (int) $r['total']], $byDept),
];

$linkableUsers = all(
    'SELECT id, full_name, username FROM users ORDER BY full_name'
);

$avatarTones = ['', '--gold', '--blue', '--purple', '--teal', '--red'];

$pageTitle    = 'Employees';
$pageSubtitle = 'The people who run the farm.';
$activeNav    = 'employees';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Employees' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('staff', 24, 'c-brand') ?> Staff Register</h1>
    <p><?= $activeCount ?> active staff · monthly payroll <?= money($payroll) ?></p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
    <?php if ($canManage): ?>
      <button class="btn btn--primary" data-modal="empModal" data-primary-action
              data-fill='{"id":"","full_name":"","job_title":"","phone":"","email":"","address":"","salary":"","user_id":"","hire_date":"<?= date('Y-m-d') ?>"}'
              data-fill-text='{"title":"Add an Employee"}'>
        <?= icon('plus', 17) ?> Add Employee
      </button>
    <?php endif; ?>
  </div>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('staff', 22) ?></span>
      <div>
        <div class="stat__label">Active Staff</div>
        <div class="stat__value" data-count="<?= $activeCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span><?= count($byDept) ?> departments</span></div>
  </article>

  <article class="stat stat--gold reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('clock', 22) ?></span>
      <div>
        <div class="stat__label">On Leave</div>
        <div class="stat__value" data-count="<?= $leaveCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Temporarily away</span></div>
  </article>

  <article class="stat stat--blue reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('finance', 22) ?></span>
      <div>
        <div class="stat__label">Monthly Payroll</div>
        <div class="stat__value" data-count="<?= $payroll ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Active staff salaries</span></div>
  </article>

  <article class="stat stat--purple reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('shield', 22) ?></span>
      <div>
        <div class="stat__label">Average Tenure</div>
        <div class="stat__value" data-count="<?= $avgTenure ?>" data-decimals="1" data-suffix=" yrs">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Years of service</span></div>
  </article>
</section>

<section class="grid grid--2-1 mb-18">
  <article class="card reveal" data-delay="200">
    <form class="toolbar" method="get">
      <div class="field-inline">
        <?= icon('search', 16) ?>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, role or phone…">
      </div>
      <div class="field-inline">
        <?= icon('grid', 16) ?>
        <select name="department" data-autosubmit>
          <option value="">All departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d ?>"<?= selected($department, $d) ?>><?= e(label($d)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-inline">
        <?= icon('filter', 16) ?>
        <select name="status" data-autosubmit>
          <option value="">Any status</option>
          <?php foreach ($states as $s): ?>
            <option value="<?= $s ?>"<?= selected($status, $s) ?>><?= e(label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--sm btn--soft" type="submit"><?= icon('search', 15) ?> Filter</button>
      <?php if ($search || $department || $status): ?>
        <a class="btn btn--sm btn--plain" href="<?= url('pages/employees.php') ?>"><?= icon('close', 15) ?> Clear</a>
      <?php endif; ?>
    </form>

    <?php if (!$employees): ?>
      <div class="empty">
        <span class="empty__art"><?= icon('staff', 30) ?></span>
        <h3>No employees found</h3>
        <p>Add the people working on the farm so tasks can be assigned to them.</p>
      </div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Department</th>
              <th>Contact</th>
              <th>Hired</th>
              <th class="num">Salary</th>
              <th>Tasks</th>
              <th>Status</th>
              <?php if ($canManage): ?><th class="actions">Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $index => $emp): ?>
              <tr>
                <td>
                  <div class="cellmain">
                    <span class="avatar avatar<?= $avatarTones[$index % count($avatarTones)] ?>">
                      <?= e(initials($emp['full_name'])) ?>
                    </span>
                    <span class="cellmain__text">
                      <span class="cellmain__title"><?= e($emp['full_name']) ?></span>
                      <span class="cellmain__sub">
                        <?= e($emp['job_title']) ?>
                        <?php if ($emp['username']): ?>
                          · <span class="c-brand">@<?= e($emp['username']) ?></span>
                        <?php endif; ?>
                      </span>
                    </span>
                  </div>
                </td>
                <td><span class="badge badge--neutral"><?= e(label($emp['department'])) ?></span></td>
                <td class="small soft">
                  <?= e($emp['phone'] ?: '—') ?>
                  <?php if ($emp['email']): ?>
                    <div class="tiny muted" style="word-break:break-all"><?= e($emp['email']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="small nowrap">
                  <?= fdate($emp['hire_date']) ?>
                  <div class="tiny muted"><?= e(age_from($emp['hire_date'])) ?> of service</div>
                </td>
                <td class="num tnum"><?= money($emp['salary'], false) ?></td>
                <td>
                  <div class="flex items-c gap-6">
                    <?php if ($emp['open_tasks'] > 0): ?>
                      <span class="badge badge--warning"><?= (int) $emp['open_tasks'] ?> open</span>
                    <?php endif; ?>
                    <?php if ($emp['done_tasks'] > 0): ?>
                      <span class="badge badge--success"><?= (int) $emp['done_tasks'] ?> done</span>
                    <?php endif; ?>
                    <?php if (!$emp['open_tasks'] && !$emp['done_tasks']): ?>
                      <span class="muted small">—</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?= badge($emp['status']) ?></td>
                <?php if ($canManage): ?>
                  <td class="actions">
                    <div>
                      <a class="rowbtn" title="Their tasks"
                         href="<?= url('pages/tasks.php?assignee=' . $emp['id'] . '&view=list') ?>">
                        <?= icon('tasks', 16) ?>
                      </a>
                      <button class="rowbtn" title="Edit"
                              data-modal="empModal"
                              data-fill='<?= e(json_encode([
                                  'id'         => $emp['id'],
                                  'full_name'  => $emp['full_name'],
                                  'job_title'  => $emp['job_title'],
                                  'department' => $emp['department'],
                                  'phone'      => $emp['phone'],
                                  'email'      => $emp['email'],
                                  'address'    => $emp['address'],
                                  'salary'     => $emp['salary'],
                                  'hire_date'  => $emp['hire_date'],
                                  'status'     => $emp['status'],
                                  'user_id'    => $emp['user_id'],
                              ])) ?>'
                              data-fill-text='<?= e(json_encode(['title' => 'Edit ' . $emp['full_name']])) ?>'>
                        <?= icon('edit', 16) ?>
                      </button>
                      <button class="rowbtn rowbtn--danger" title="Delete"
                              data-modal="deleteModal"
                              data-fill='<?= e(json_encode(['id' => $emp['id']])) ?>'
                              data-fill-text='<?= e(json_encode(['name' => $emp['full_name']])) ?>'>
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
  </article>

  <div class="grid" style="gap:18px;align-content:start">
    <article class="card reveal" data-delay="240">
      <div class="card__head"><h3><?= icon('chart-pie', 18) ?> Staff by Department</h3></div>
      <div class="card__body">
        <?php if ($byDept): ?>
          <div data-chart><script type="application/json"><?= json_encode($deptChart) ?></script></div>
        <?php else: ?>
          <p class="muted small text-c">No staff on record.</p>
        <?php endif; ?>
      </div>
    </article>

    <article class="card reveal" data-delay="280">
      <div class="card__head"><h3><?= icon('success', 18) ?> Task Leaderboard</h3></div>
      <div class="card__body card__body--flush">
        <?php
        $leaders = all(
            "SELECT e.full_name, COUNT(t.id) AS done
               FROM employees e JOIN tasks t ON t.assigned_to = e.id AND t.status='completed'
              GROUP BY e.id, e.full_name ORDER BY done DESC LIMIT 5"
        );
        ?>
        <?php if (!$leaders): ?>
          <div class="empty" style="padding:30px 16px">
            <span class="empty__art"><?= icon('tasks', 26) ?></span>
            <h3>No completed tasks</h3>
            <p>Finished work will be credited here.</p>
          </div>
        <?php else: ?>
          <?php $maxDone = max(array_column($leaders, 'done')); ?>
          <?php foreach ($leaders as $rank => $l): ?>
            <div class="listrow">
              <span class="avatar avatar--sm avatar<?= $avatarTones[$rank % count($avatarTones)] ?>">
                <?= e(initials($l['full_name'])) ?>
              </span>
              <span class="listrow__text">
                <span class="listrow__title"><?= e($l['full_name']) ?></span>
                <div class="progress mt-8" style="height:5px">
                  <div class="progress__fill" data-value="<?= percent_of($l['done'], $maxDone) ?>"></div>
                </div>
              </span>
              <span class="badge badge--success"><?= (int) $l['done'] ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>
  </div>
</section>

<?php if ($canManage): ?>
<div class="modal" id="empModal">
  <div class="modal__panel modal__panel--wide">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('staff', 19) ?> <span data-text="title">Add an Employee</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="formsection">
          <div class="formsection__head"><?= icon('user', 16) ?><h4>Personal details</h4></div>

          <div class="form__row">
            <div class="field">
              <label for="full_name">Full name <span class="req">*</span></label>
              <input class="input" type="text" id="full_name" name="full_name"
                     placeholder="e.g. Akosua Danso" required>
            </div>
            <div class="field">
              <label for="job_title">Job title <span class="req">*</span></label>
              <input class="input" type="text" id="job_title" name="job_title"
                     placeholder="e.g. Crop Supervisor" required>
            </div>
          </div>

          <div class="form__row">
            <div class="field field--icon">
              <label for="phone">Phone</label>
              <span class="field__icon"><?= icon('phone', 17) ?></span>
              <input class="input" type="text" id="phone" name="phone" placeholder="+233 …">
            </div>
            <div class="field field--icon">
              <label for="email">Email</label>
              <span class="field__icon"><?= icon('mail', 17) ?></span>
              <input class="input" type="email" id="email" name="email" placeholder="name@example.com">
            </div>
          </div>

          <div class="field field--icon">
            <label for="address">Address</label>
            <span class="field__icon"><?= icon('pin', 17) ?></span>
            <input class="input" type="text" id="address" name="address" placeholder="e.g. Ejisu, Ashanti">
          </div>
        </div>

        <div class="divider"></div>

        <div class="formsection">
          <div class="formsection__head"><?= icon('shield', 16) ?><h4>Employment</h4></div>

          <div class="form__row--3 form__row">
            <div class="field">
              <label for="department">Department <span class="req">*</span></label>
              <select class="select" id="department" name="department" required>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d ?>"><?= e(label($d)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="hire_date">Hire date <span class="req">*</span></label>
              <input class="input" type="date" id="hire_date" name="hire_date"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="field">
              <label for="status">Status <span class="req">*</span></label>
              <select class="select" id="status" name="status" required>
                <?php foreach ($states as $s): ?>
                  <option value="<?= $s ?>"><?= e(label($s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form__row">
            <div class="field field--money">
              <label for="salary">Monthly salary</label>
              <span class="prefix"><?= e(currency()) ?></span>
              <input class="input" type="number" step="0.01" min="0" id="salary" name="salary" placeholder="0.00">
            </div>
            <div class="field">
              <label for="user_id">Linked system account</label>
              <select class="select" id="user_id" name="user_id">
                <option value="">No login account</option>
                <?php foreach ($linkableUsers as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= e($u['full_name'] . ' (@' . $u['username'] . ')') ?></option>
                <?php endforeach; ?>
              </select>
              <span class="field__hint">Links this employee to a user who can sign in.</span>
            </div>
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Employee</button>
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
        <h3>Remove this employee?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> will be removed from the register.
          Tasks assigned to them become unassigned.
        </p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Remove</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
