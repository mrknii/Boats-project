<?php
/**
 * ---------------------------------------------------------------------
 *  Suppliers directory
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_capability('suppliers.manage');

if (is_post()) {
    csrf_verify();

    if (post('action') === 'delete') {
        $id       = post_int('id');
        $supplier = one('SELECT name FROM suppliers WHERE id = ?', [$id]);
        if ($supplier) {
            delete_row('suppliers', $id);
            log_activity('suppliers', 'delete', 'Deleted supplier ' . $supplier['name']);
            flash('success', $supplier['name'] . ' was removed. Items they supplied are kept.');
        }
        redirect('pages/suppliers.php');
    }

    $id = post_int('id');

    $errors = validate([
        'name'  => ['required' => true, 'max' => 120, 'label' => 'Supplier name'],
        'email' => ['email' => true],
    ]);

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'name'           => post('name'),
            'contact_person' => post_or_null('contact_person'),
            'phone'          => post_or_null('phone'),
            'email'          => post_or_null('email'),
            'address'        => post_or_null('address'),
        ];

        if ($id > 0) {
            update('suppliers', $data, $id);
            log_activity('suppliers', 'update', 'Updated supplier ' . $data['name']);
            flash('success', $data['name'] . ' was updated.');
        } else {
            insert('suppliers', $data);
            log_activity('suppliers', 'create', 'Added supplier ' . $data['name']);
            flash('success', $data['name'] . ' was added to the directory.');
        }
        redirect('pages/suppliers.php');
    }
}

$suppliers = all(
    'SELECT s.*,
            (SELECT COUNT(*) FROM inventory_items i WHERE i.supplier_id = s.id) AS item_count,
            (SELECT COALESCE(SUM(i.quantity * i.unit_cost),0) FROM inventory_items i WHERE i.supplier_id = s.id) AS supplied_value
       FROM suppliers s
      ORDER BY s.name'
);

$avatarTones = ['', '--gold', '--blue', '--purple', '--teal', '--red'];

$pageTitle    = 'Suppliers';
$pageSubtitle = 'Who you buy inputs from.';
$activeNav    = 'suppliers';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Suppliers' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('suppliers', 24, 'c-brand') ?> Supplier Directory</h1>
    <p><?= count($suppliers) ?> registered suppliers</p>
  </div>
  <div class="pagehead__actions">
    <button class="btn btn--primary" data-modal="supplierModal" data-primary-action
            data-fill='{"id":"","name":"","contact_person":"","phone":"","email":"","address":""}'
            data-fill-text='{"title":"Add a Supplier"}'>
      <?= icon('plus', 17) ?> Add Supplier
    </button>
  </div>
</div>

<?php if (!$suppliers): ?>
  <section class="card reveal">
    <div class="empty">
      <span class="empty__art"><?= icon('suppliers', 30) ?></span>
      <h3>No suppliers yet</h3>
      <p>Add the businesses you buy feed, seed and chemicals from so purchase orders can reference them.</p>
      <button class="btn btn--primary mt-14" data-modal="supplierModal"><?= icon('plus', 17) ?> Add Supplier</button>
    </div>
  </section>
<?php else: ?>

  <section class="grid grid--3">
    <?php foreach ($suppliers as $index => $s): ?>
      <article class="card card--hover reveal" data-delay="<?= $index * 55 ?>">
        <div class="card__body">
          <div class="flex items-c gap-14 mb-14">
            <span class="avatar avatar--lg avatar<?= $avatarTones[$index % count($avatarTones)] ?>">
              <?= e(initials($s['name'])) ?>
            </span>
            <div style="min-width:0;flex:1">
              <h3 style="font-size:1rem"><?= e($s['name']) ?></h3>
              <p class="small muted"><?= e($s['contact_person'] ?: 'No named contact') ?></p>
            </div>
            <div class="dropdown">
              <button class="iconbtn" data-dropdown aria-label="Actions"><?= icon('more', 18) ?></button>
              <div class="dropdown__menu">
                <button class="dropdown__item"
                        data-modal="supplierModal"
                        data-fill='<?= e(json_encode([
                            'id'             => $s['id'],
                            'name'           => $s['name'],
                            'contact_person' => $s['contact_person'],
                            'phone'          => $s['phone'],
                            'email'          => $s['email'],
                            'address'        => $s['address'],
                        ])) ?>'
                        data-fill-text='<?= e(json_encode(['title' => 'Edit ' . $s['name']])) ?>'>
                  <?= icon('edit', 17) ?> Edit supplier
                </button>
                <a class="dropdown__item" href="<?= url('pages/inventory.php?q=' . urlencode($s['name'])) ?>">
                  <?= icon('inventory', 17) ?> Items supplied
                </a>
                <div class="dropdown__sep"></div>
                <button class="dropdown__item dropdown__item--danger"
                        data-modal="deleteModal"
                        data-fill='<?= e(json_encode(['id' => $s['id']])) ?>'
                        data-fill-text='<?= e(json_encode(['name' => $s['name']])) ?>'>
                  <?= icon('trash', 17) ?> Delete
                </button>
              </div>
            </div>
          </div>

          <div class="kv">
            <div class="kv__row">
              <span class="kv__key"><?= icon('phone', 14) ?> Phone</span>
              <span class="kv__val"><?= e($s['phone'] ?: '—') ?></span>
            </div>
            <div class="kv__row">
              <span class="kv__key"><?= icon('mail', 14) ?> Email</span>
              <span class="kv__val" style="word-break:break-all"><?= e($s['email'] ?: '—') ?></span>
            </div>
            <div class="kv__row">
              <span class="kv__key"><?= icon('pin', 14) ?> Address</span>
              <span class="kv__val"><?= e($s['address'] ?: '—') ?></span>
            </div>
          </div>
        </div>

        <div class="card__foot flex items-c justify-b gap-10">
          <span class="small muted"><?= (int) $s['item_count'] ?> item<?= $s['item_count'] == 1 ? '' : 's' ?> supplied</span>
          <span class="badge badge--success"><?= money_short($s['supplied_value']) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<div class="modal" id="supplierModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('suppliers', 19) ?> <span data-text="title">Add a Supplier</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label for="name">Business name <span class="req">*</span></label>
          <input class="input" type="text" id="name" name="name"
                 placeholder="e.g. AgriMax Ghana Ltd" required>
        </div>

        <div class="field field--icon">
          <label for="contact_person">Contact person</label>
          <span class="field__icon"><?= icon('user', 17) ?></span>
          <input class="input" type="text" id="contact_person" name="contact_person" placeholder="e.g. Samuel Adjei">
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
            <input class="input" type="email" id="email" name="email" placeholder="sales@example.com">
          </div>
        </div>

        <div class="field field--icon">
          <label for="address">Address</label>
          <span class="field__icon"><?= icon('pin', 17) ?></span>
          <input class="input" type="text" id="address" name="address" placeholder="e.g. Adum, Kumasi">
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Supplier</button>
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
        <h3>Delete this supplier?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> will be removed from the directory.
          Stock items they supplied are kept, but lose the supplier link.
        </p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--danger"><?= icon('trash', 17) ?> Delete</button>
      </div>
    </form>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/layout_foot.php'; ?>
