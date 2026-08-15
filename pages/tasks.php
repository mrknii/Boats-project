<?php
/**
 * ---------------------------------------------------------------------
 *  Task board — farm work assignment and tracking
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$canManage = can('tasks.manage');

if (is_post()) {
    csrf_verify();

    // ---------- Change status (any signed in user may do this) ---------
    if (post('action') === 'status') {
        $id     = post_int('id');
        $status = post('status');

        if (!in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
            flash('danger', 'That is not a valid task status.');
            redirect('pages/tasks.php');
        }

        $task = one('SELECT title FROM tasks WHERE id = ?', [$id]);
        if ($task) {
            update('tasks', [
                'status'       => $status,
                'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
            ], $id);
            log_activity('tasks', 'update', 'Task "' . $task['title'] . '" set to ' . label($status));
            flash('success', '"' . $task['title'] . '" is now ' . strtolower(label($status)) . '.');
        }
        redirect('pages/tasks.php' . (get_param('view') ? '?view=' . get_param('view') : ''));
    }

    require_capability('tasks.manage');

    if (post('action') === 'delete') {
        $id   = post_int('id');
        $task = one('SELECT title FROM tasks WHERE id = ?', [$id]);
        if ($task) {
            delete_row('tasks', $id);
            log_activity('tasks', 'delete', 'Deleted task ' . $task['title']);
            flash('success', 'The task was deleted.');
        }
        redirect('pages/tasks.php');
    }

    $id = post_int('id');

    $errors = validate([
        'title'    => ['required' => true, 'max' => 160],
        'category' => ['required' => true],
        'priority' => ['required' => true],
        'status'   => ['required' => true],
        'due_date' => ['date' => true, 'label' => 'Due date'],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $status = post('status');

        $data = [
            'title'       => post('title'),
            'description' => post_or_null('description'),
            'category'    => post('category'),
            'assigned_to' => post_int('assigned_to') ?: null,
            'priority'    => post('priority'),
            'status'      => $status,
            'due_date'    => post_or_null('due_date'),
            'completed_at'=> $status === 'completed' ? date('Y-m-d H:i:s') : null,
        ];

        if ($id > 0) {
            update('tasks', $data, $id);
            log_activity('tasks', 'update', 'Updated task ' . $data['title']);
            flash('success', 'The task was updated.');
        } else {
            $data['created_by'] = current_user()['id'];
            insert('tasks', $data);
            log_activity('tasks', 'create', 'Created task ' . $data['title']);
            flash('success', 'Task "' . $data['title'] . '" was created.');
        }
        redirect('pages/tasks.php');
    }
}

// --- View mode: board or list -----------------------------------------
$view     = get_param('view', 'board');
$assignee = get_param('assignee');
$priority = get_param('priority');
$category = get_param('category');

$where  = [];
$params = [];

if ($assignee !== '') { $where[] = 't.assigned_to = ?'; $params[] = (int) $assignee; }
if ($priority !== '') { $where[] = 't.priority = ?';    $params[] = $priority; }
if ($category !== '') { $where[] = 't.category = ?';    $params[] = $category; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$tasks = all(
    "SELECT t.*, e.full_name AS assignee, e.job_title, u.full_name AS creator
       FROM tasks t
       LEFT JOIN employees e ON e.id = t.assigned_to
       LEFT JOIN users u ON u.id = t.created_by
       $whereSql
      ORDER BY FIELD(t.priority,'urgent','high','medium','low'),
               (t.due_date IS NULL), t.due_date ASC",
    $params
);

$employees  = all("SELECT id, full_name, job_title FROM employees WHERE status <> 'terminated' ORDER BY full_name");
$categories = ['livestock', 'crops', 'maintenance', 'harvest', 'irrigation', 'general'];
$priorities = ['urgent', 'high', 'medium', 'low'];
$columns    = [
    'pending'     => ['label' => 'To Do',       'icon' => 'list',     'tone' => 'gold'],
    'in_progress' => ['label' => 'In Progress', 'icon' => 'refresh',  'tone' => 'blue'],
    'completed'   => ['label' => 'Completed',   'icon' => 'success',  'tone' => ''],
    'cancelled'   => ['label' => 'Cancelled',   'icon' => 'close',    'tone' => 'red'],
];

$grouped = array_fill_keys(array_keys($columns), []);
foreach ($tasks as $t) {
    $grouped[$t['status']][] = $t;
}

// --- Summary ----------------------------------------------------------
$countOpen      = count($grouped['pending']) + count($grouped['in_progress']);
$countOverdue   = 0;
$countToday     = 0;
foreach (array_merge($grouped['pending'], $grouped['in_progress']) as $t) {
    $d = days_until($t['due_date']);
    if ($d === null) continue;
    if ($d < 0)  $countOverdue++;
    if ($d === 0) $countToday++;
}
$countDone = (int) scalar("SELECT COUNT(*) FROM tasks WHERE status='completed'");
$totalAll  = (int) scalar('SELECT COUNT(*) FROM tasks');
$completionRate = percent_of($countDone, $totalAll);

$pageTitle    = 'Tasks';
$pageSubtitle = 'Who is doing what, and by when.';
$activeNav    = 'tasks';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Tasks' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('tasks', 24, 'c-brand') ?> Task Board</h1>
    <p><?= $countOpen ?> open · <?= $countOverdue ?> overdue · <?= $completionRate ?>% completion rate</p>
  </div>
  <div class="pagehead__actions">
    <div class="tabs">
      <a class="tab<?= $view === 'board' ? ' is-active' : '' ?>" href="<?= e(query_with(['view' => 'board'])) ?>">
        <?= icon('grid', 15) ?> Board
      </a>
      <a class="tab<?= $view === 'list' ? ' is-active' : '' ?>" href="<?= e(query_with(['view' => 'list'])) ?>">
        <?= icon('list', 15) ?> List
      </a>
    </div>
    <?php if ($canManage): ?>
      <button class="btn btn--primary" data-modal="taskModal" data-primary-action
              data-fill='{"id":"","title":"","description":"","due_date":"","assigned_to":""}'
              data-fill-text='{"title":"Create a Task"}'>
        <?= icon('plus', 17) ?> New Task
      </button>
    <?php endif; ?>
  </div>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--gold reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('tasks', 22) ?></span>
      <div>
        <div class="stat__label">Open Tasks</div>
        <div class="stat__value" data-count="<?= $countOpen ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span><?= count($grouped['in_progress']) ?> in progress</span></div>
  </article>

  <article class="stat stat--red reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('warning', 22) ?></span>
      <div>
        <div class="stat__label">Overdue</div>
        <div class="stat__value" data-count="<?= $countOverdue ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Past their due date</span></div>
  </article>

  <article class="stat stat--blue reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('clock', 22) ?></span>
      <div>
        <div class="stat__label">Due Today</div>
        <div class="stat__value" data-count="<?= $countToday ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>Needs finishing today</span></div>
  </article>

  <article class="stat stat--green reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('success', 22) ?></span>
      <div>
        <div class="stat__label">Completed</div>
        <div class="stat__value" data-count="<?= $countDone ?>">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <div class="progress" style="width:100%">
        <div class="progress__fill" data-value="<?= $completionRate ?>"></div>
      </div>
    </div>
  </article>
</section>

<!-- Filters -->
<section class="card mb-18 reveal" data-delay="200">
  <form class="toolbar" method="get" style="border-radius:var(--r-lg);border-bottom:0">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="field-inline">
      <?= icon('user', 16) ?>
      <select name="assignee" data-autosubmit>
        <option value="">Everyone</option>
        <?php foreach ($employees as $emp): ?>
          <option value="<?= $emp['id'] ?>"<?= selected($assignee, $emp['id']) ?>><?= e($emp['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-inline">
      <?= icon('warning', 16) ?>
      <select name="priority" data-autosubmit>
        <option value="">Any priority</option>
        <?php foreach ($priorities as $p): ?>
          <option value="<?= $p ?>"<?= selected($priority, $p) ?>><?= e(label($p)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-inline">
      <?= icon('filter', 16) ?>
      <select name="category" data-autosubmit>
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c ?>"<?= selected($category, $c) ?>><?= e(label($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($assignee || $priority || $category): ?>
      <a class="btn btn--sm btn--plain" href="<?= url('pages/tasks.php?view=' . e($view)) ?>">
        <?= icon('close', 15) ?> Clear filters
      </a>
    <?php endif; ?>
    <div class="toolbar__spacer"></div>
    <span class="small muted"><?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?> shown</span>
  </form>
</section>

<?php if ($view === 'board'): ?>
<!-- ===================== BOARD VIEW ===================== -->
<section class="grid grid--4" style="align-items:start">
  <?php $colIndex = 0; ?>
  <?php foreach ($columns as $key => $col): ?>
    <div class="card reveal" data-delay="<?= 220 + $colIndex * 60 ?>">
      <div class="card__head">
        <h3>
          <span class="tile tile--sm <?= $col['tone'] ? 'tile--' . $col['tone'] : '' ?>">
            <?= icon($col['icon'], 15) ?>
          </span>
          <?= e($col['label']) ?>
        </h3>
        <div class="card__actions">
          <span class="badge badge--neutral"><?= count($grouped[$key]) ?></span>
        </div>
      </div>

      <div class="card__body" style="display:grid;gap:11px;min-height:90px">
        <?php if (!$grouped[$key]): ?>
          <p class="small muted text-c" style="padding:18px 0">Nothing here.</p>
        <?php else: ?>
          <?php foreach ($grouped[$key] as $t): ?>
            <?php
              $days = days_until($t['due_date']);
              $late = $days !== null && $days < 0 && in_array($t['status'], ['pending', 'in_progress'], true);
            ?>
            <article class="card card--hover" style="border-radius:var(--r-md)">
              <div style="padding:13px 14px">
                <div class="flex items-c justify-b gap-8 mb-8">
                  <?= badge($t['priority'], priority_tone($t['priority'])) ?>
                  <?php if ($canManage): ?>
                    <div class="dropdown">
                      <button class="rowbtn" data-dropdown aria-label="Task actions"><?= icon('more', 15) ?></button>
                      <div class="dropdown__menu">
                        <?php foreach ($columns as $sKey => $sCol): ?>
                          <?php if ($sKey === $t['status']) continue; ?>
                          <button class="dropdown__item"
                                  data-modal="statusModal"
                                  data-fill='<?= e(json_encode(['id' => $t['id'], 'status' => $sKey])) ?>'
                                  data-fill-text='<?= e(json_encode([
                                      'name'  => $t['title'],
                                      'state' => $sCol['label'],
                                  ])) ?>'>
                            <?= icon($sCol['icon'], 16) ?> Move to <?= e($sCol['label']) ?>
                          </button>
                        <?php endforeach; ?>
                        <div class="dropdown__sep"></div>
                        <button class="dropdown__item"
                                data-modal="taskModal"
                                data-fill='<?= e(json_encode([
                                    'id'          => $t['id'],
                                    'title'       => $t['title'],
                                    'description' => $t['description'],
                                    'category'    => $t['category'],
                                    'assigned_to' => $t['assigned_to'],
                                    'priority'    => $t['priority'],
                                    'status'      => $t['status'],
                                    'due_date'    => $t['due_date'],
                                ])) ?>'
                                data-fill-text='<?= e(json_encode(['title' => 'Edit Task'])) ?>'>
                          <?= icon('edit', 16) ?> Edit task
                        </button>
                        <button class="dropdown__item dropdown__item--danger"
                                data-modal="deleteModal"
                                data-fill='<?= e(json_encode(['id' => $t['id']])) ?>'
                                data-fill-text='<?= e(json_encode(['name' => $t['title']])) ?>'>
                          <?= icon('trash', 16) ?> Delete
                        </button>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>

                <h4 style="font-size:.88rem;line-height:1.4"><?= e($t['title']) ?></h4>

                <?php if ($t['description']): ?>
                  <p class="tiny muted mt-8" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                    <?= e($t['description']) ?>
                  </p>
                <?php endif; ?>

                <div class="flex items-c justify-b gap-8 mt-14">
                  <span class="flex items-c gap-6" title="<?= e($t['assignee'] ?? 'Unassigned') ?>">
                    <?php if ($t['assignee']): ?>
                      <span class="avatar avatar--sm" style="width:24px;height:24px;font-size:.6rem">
                        <?= e(initials($t['assignee'])) ?>
                      </span>
                      <span class="tiny muted" style="max-width:82px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= e($t['assignee']) ?>
                      </span>
                    <?php else: ?>
                      <span class="tiny muted"><?= icon('user', 13) ?> Unassigned</span>
                    <?php endif; ?>
                  </span>

                  <?php if ($t['due_date']): ?>
                    <span class="tiny nowrap <?= $late ? 'c-danger bold' : 'muted' ?>">
                      <?= icon('calendar', 12) ?>
                      <?= $late ? abs($days) . 'd late' : fdate($t['due_date'], 'd M') ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php $colIndex++; ?>
  <?php endforeach; ?>
</section>

<?php else: ?>
<!-- ===================== LIST VIEW ===================== -->
<section class="card reveal" data-delay="220">
  <div class="card__head">
    <h3><?= icon('list', 18) ?> All Tasks</h3>
    <div class="card__actions">
      <div class="field-inline">
        <?= icon('search', 15) ?>
        <input type="text" data-filter-table="#tasksTable" placeholder="Filter tasks…" style="height:33px">
      </div>
    </div>
  </div>

  <?php if (!$tasks): ?>
    <div class="empty">
      <span class="empty__art"><?= icon('tasks', 30) ?></span>
      <h3>No tasks</h3>
      <p>Create a task to start assigning work to the team.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table" id="tasksTable">
        <thead>
          <tr>
            <th>Task</th>
            <th>Category</th>
            <th>Assigned To</th>
            <th>Due</th>
            <th>Priority</th>
            <th>Status</th>
            <?php if ($canManage): ?><th class="actions">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $t): ?>
            <?php
              $days = days_until($t['due_date']);
              $late = $days !== null && $days < 0 && in_array($t['status'], ['pending', 'in_progress'], true);
            ?>
            <tr>
              <td>
                <div class="cellmain">
                  <span class="tile tile--sm <?= $t['priority'] === 'urgent' ? 'tile--red' : ($t['priority'] === 'high' ? 'tile--gold' : '') ?>">
                    <?= icon('tasks', 15) ?>
                  </span>
                  <span class="cellmain__text">
                    <span class="cellmain__title"><?= e($t['title']) ?></span>
                    <span class="cellmain__sub"><?= e($t['description'] ?: 'No description') ?></span>
                  </span>
                </div>
              </td>
              <td><span class="badge badge--neutral"><?= e(label($t['category'])) ?></span></td>
              <td>
                <?php if ($t['assignee']): ?>
                  <div class="flex items-c gap-8">
                    <span class="avatar avatar--sm"><?= e(initials($t['assignee'])) ?></span>
                    <span class="small"><?= e($t['assignee']) ?></span>
                  </div>
                <?php else: ?>
                  <span class="muted small">Unassigned</span>
                <?php endif; ?>
              </td>
              <td class="small nowrap <?= $late ? 'c-danger bold' : '' ?>">
                <?= $t['due_date'] ? fdate($t['due_date']) : '—' ?>
                <?php if ($late): ?><div class="tiny">Overdue by <?= abs($days) ?>d</div><?php endif; ?>
              </td>
              <td><?= badge($t['priority'], priority_tone($t['priority'])) ?></td>
              <td><?= badge($t['status']) ?></td>
              <?php if ($canManage): ?>
                <td class="actions">
                  <div>
                    <?php if ($t['status'] !== 'completed'): ?>
                      <button class="rowbtn" title="Mark completed"
                              data-modal="statusModal"
                              data-fill='<?= e(json_encode(['id' => $t['id'], 'status' => 'completed'])) ?>'
                              data-fill-text='<?= e(json_encode(['name' => $t['title'], 'state' => 'Completed'])) ?>'>
                        <?= icon('check', 16) ?>
                      </button>
                    <?php endif; ?>
                    <button class="rowbtn" title="Edit"
                            data-modal="taskModal"
                            data-fill='<?= e(json_encode([
                                'id'          => $t['id'],
                                'title'       => $t['title'],
                                'description' => $t['description'],
                                'category'    => $t['category'],
                                'assigned_to' => $t['assigned_to'],
                                'priority'    => $t['priority'],
                                'status'      => $t['status'],
                                'due_date'    => $t['due_date'],
                            ])) ?>'
                            data-fill-text='<?= e(json_encode(['title' => 'Edit Task'])) ?>'>
                      <?= icon('edit', 16) ?>
                    </button>
                    <button class="rowbtn rowbtn--danger" title="Delete"
                            data-modal="deleteModal"
                            data-fill='<?= e(json_encode(['id' => $t['id']])) ?>'
                            data-fill-text='<?= e(json_encode(['name' => $t['title']])) ?>'>
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
<?php endif; ?>

<!-- ===================== MODALS ===================== -->
<div class="modal" id="statusModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="status" value="">
      <div class="modal__body text-c">
        <span class="confirm-art" style="color:var(--brand-500);background:var(--brand-50);border-color:var(--brand-100)">
          <?= icon('refresh', 26) ?>
        </span>
        <h3>Move this task?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> will be moved to
          <strong data-text="state"></strong>.
        </p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('check', 17) ?> Confirm</button>
      </div>
    </form>
  </div>
</div>

<?php if ($canManage): ?>
<div class="modal" id="taskModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('tasks', 19) ?> <span data-text="title">Create a Task</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label for="title">Task title <span class="req">*</span></label>
          <input class="input" type="text" id="title" name="title"
                 placeholder="e.g. Complete maize harvest — North Block" required>
        </div>

        <div class="field">
          <label for="description">Description</label>
          <textarea class="textarea" id="description" name="description" style="min-height:80px"
                    placeholder="What exactly needs doing?"></textarea>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="assigned_to">Assign to</label>
            <select class="select" id="assigned_to" name="assigned_to">
              <option value="">Unassigned</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>">
                  <?= e($emp['full_name']) ?> — <?= e($emp['job_title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="category">Category <span class="req">*</span></label>
            <select class="select" id="category" name="category" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c ?>"><?= e(label($c)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form__row--3 form__row">
          <div class="field">
            <label for="due_date">Due date</label>
            <input class="input" type="date" id="due_date" name="due_date">
          </div>
          <div class="field">
            <label for="priority">Priority <span class="req">*</span></label>
            <select class="select" id="priority" name="priority" required>
              <?php foreach ($priorities as $p): ?>
                <option value="<?= $p ?>"<?= $p === 'medium' ? ' selected' : '' ?>><?= e(label($p)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="status">Status <span class="req">*</span></label>
            <select class="select" id="status" name="status" required>
              <?php foreach ($columns as $key => $col): ?>
                <option value="<?= $key ?>"><?= e($col['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Task</button>
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
        <h3>Delete this task?</h3>
        <p class="soft small mt-8"><strong data-text="name"></strong> will be permanently removed.</p>
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
