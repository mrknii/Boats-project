<?php
/**
 * ---------------------------------------------------------------------
 *  Inventory / farm stores
 * ---------------------------------------------------------------------
 *  Stock levels are never edited by hand from the movement form. Every
 *  receipt, issue and correction is written to inventory_movements and
 *  the running quantity is adjusted inside a database transaction, so
 *  the stock card always reconciles with the item balance.
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$canManage = can('inventory.manage');

if (is_post()) {
    csrf_verify();
    require_capability('inventory.manage');

    $action = post('action');

    // ---------- Delete an item -----------------------------------------
    if ($action === 'delete') {
        $id   = post_int('id');
        $item = one('SELECT item_name FROM inventory_items WHERE id = ?', [$id]);
        if ($item) {
            delete_row('inventory_items', $id);
            log_activity('inventory', 'delete', 'Deleted item ' . $item['item_name']);
            flash('success', $item['item_name'] . ' was removed from the store list.');
        }
        redirect('pages/inventory.php');
    }

    // ---------- Record a stock movement --------------------------------
    if ($action === 'movement') {
        $itemId   = post_int('item_id');
        $type     = post('movement_type');
        $quantity = post_num('quantity');
        $item     = one('SELECT * FROM inventory_items WHERE id = ?', [$itemId]);

        if (!$item) {
            flash('danger', 'That stock item could not be found.');
            redirect('pages/inventory.php');
        }
        if ($quantity <= 0) {
            flash('danger', 'Enter a quantity greater than zero.');
            redirect('pages/inventory.php');
        }
        if ($type === 'out' && $quantity > (float) $item['quantity']) {
            flash('danger', 'You cannot issue ' . qty($quantity) . ' ' . $item['unit']
                . ' — only ' . qty($item['quantity']) . ' is in stock.');
            redirect('pages/inventory.php');
        }

        // New balance
        $newQty = match ($type) {
            'in'         => (float) $item['quantity'] + $quantity,
            'out'        => (float) $item['quantity'] - $quantity,
            'adjustment' => $quantity,          // an adjustment sets the counted figure
            default      => (float) $item['quantity'],
        };

        try {
            db()->beginTransaction();

            insert('inventory_movements', [
                'item_id'   => $itemId,
                'type'      => $type,
                'quantity'  => $quantity,
                'reference' => post_or_null('reference'),
                'note'      => post_or_null('note'),
                'user_id'   => current_user()['id'],
            ]);

            update('inventory_items', ['quantity' => max(0, $newQty)], $itemId);

            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            flash('danger', 'The stock movement could not be saved. Please try again.');
            redirect('pages/inventory.php');
        }

        log_activity('inventory', 'movement',
            label($type) . ' of ' . qty($quantity) . ' ' . $item['unit'] . ' — ' . $item['item_name']);

        flash('success', 'Stock updated. ' . $item['item_name'] . ' now stands at '
            . qty(max(0, $newQty)) . ' ' . $item['unit'] . '.');
        redirect('pages/inventory.php');
    }

    // ---------- Create or update an item -------------------------------
    $id = post_int('id');

    $errors = validate([
        'item_name'     => ['required' => true, 'max' => 120, 'label' => 'Item name'],
        'sku'           => ['required' => true, 'max' => 40, 'label' => 'SKU'],
        'category_id'   => ['required' => true, 'label' => 'Category'],
        'unit'          => ['required' => true, 'max' => 20],
        'quantity'      => ['numeric' => true, 'gte' => 0],
        'reorder_level' => ['numeric' => true, 'gte' => 0, 'label' => 'Reorder level'],
        'unit_cost'     => ['numeric' => true, 'gte' => 0, 'label' => 'Unit cost'],
    ]);

    if (!$errors && !is_unique('inventory_items', 'sku', post('sku'), $id ?: null)) {
        $errors['sku'] = 'That SKU is already in use by another item.';
    }

    if ($errors) {
        flash('danger', reset($errors));
    } else {
        $data = [
            'item_name'     => post('item_name'),
            'sku'           => post('sku'),
            'category_id'   => post_int('category_id'),
            'supplier_id'   => post_int('supplier_id') ?: null,
            'unit'          => post('unit'),
            'reorder_level' => post_num('reorder_level'),
            'unit_cost'     => post_num('unit_cost'),
            'location'      => post_or_null('location'),
            'expiry_date'   => post_or_null('expiry_date'),
        ];

        if ($id > 0) {
            // The quantity is deliberately left out — it only moves through
            // the stock movement form, so the audit trail stays complete.
            update('inventory_items', $data, $id);
            log_activity('inventory', 'update', 'Updated item ' . $data['item_name']);
            flash('success', $data['item_name'] . ' was updated.');
        } else {
            $data['quantity'] = post_num('quantity');
            $newId = insert('inventory_items', $data);

            if ($data['quantity'] > 0) {
                insert('inventory_movements', [
                    'item_id'   => $newId,
                    'type'      => 'in',
                    'quantity'  => $data['quantity'],
                    'reference' => 'OPENING',
                    'note'      => 'Opening stock balance',
                    'user_id'   => current_user()['id'],
                ]);
            }
            log_activity('inventory', 'create', 'Added item ' . $data['item_name']);
            flash('success', $data['item_name'] . ' was added to the store.');
        }
        redirect('pages/inventory.php');
    }
}

// --- Filters ----------------------------------------------------------
$search   = get_param('q');
$category = get_param('category');
$stock    = get_param('stock');

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(i.item_name LIKE ? OR i.sku LIKE ? OR i.location LIKE ?)';
    $like    = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($category !== '') { $where[] = 'i.category_id = ?'; $params[] = (int) $category; }
if ($stock === 'low')    { $where[] = 'i.quantity <= i.reorder_level'; }
if ($stock === 'out')    { $where[] = 'i.quantity <= 0'; }
if ($stock === 'expiring') {
    $where[] = 'i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) scalar("SELECT COUNT(*) FROM inventory_items i $whereSql", $params);
$page  = paginate($total);

$items = all(
    "SELECT i.*, c.name AS category_name, c.icon AS category_icon, s.name AS supplier_name
       FROM inventory_items i
       JOIN inventory_categories c ON c.id = i.category_id
       LEFT JOIN suppliers s ON s.id = i.supplier_id
       $whereSql
      ORDER BY (i.quantity <= i.reorder_level) DESC, i.item_name ASC
      LIMIT {$page['per_page']} OFFSET {$page['offset']}",
    $params
);

$categories = all('SELECT * FROM inventory_categories ORDER BY name');
$suppliers  = all('SELECT id, name FROM suppliers ORDER BY name');

// --- Summary ----------------------------------------------------------
$itemCount  = (int) scalar('SELECT COUNT(*) FROM inventory_items');
$stockValue = (float) scalar('SELECT COALESCE(SUM(quantity * unit_cost),0) FROM inventory_items');
$lowCount   = (int) scalar('SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level');
$expCount   = (int) scalar('SELECT COUNT(*) FROM inventory_items
                             WHERE expiry_date IS NOT NULL
                               AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)');

// Value by category
$byCategory = all(
    'SELECT c.name, SUM(i.quantity * i.unit_cost) AS value
       FROM inventory_items i JOIN inventory_categories c ON c.id = i.category_id
      GROUP BY c.id, c.name HAVING value > 0 ORDER BY value DESC'
);
$valueChart = [
    'type'        => 'donut',
    'size'        => 200,
    'thickness'   => 26,
    'centerValue' => money_short($stockValue),
    'centerLabel' => 'Stock value',
    'prefix'      => currency() . ' ',
    'compact'     => true,
    'data'        => array_map(fn($r) => ['label' => $r['name'], 'value' => (float) $r['value']], $byCategory),
];

$recentMoves = all(
    'SELECT m.*, i.item_name, i.unit, u.full_name
       FROM inventory_movements m
       JOIN inventory_items i ON i.id = m.item_id
       LEFT JOIN users u ON u.id = m.user_id
      ORDER BY m.created_at DESC, m.id DESC
      LIMIT 8'
);

$pageTitle    = 'Inventory';
$pageSubtitle = 'Feed, seed, chemicals, tools and fuel.';
$activeNav    = 'inventory';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Inventory' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('inventory', 24, 'c-brand') ?> Farm Stores</h1>
    <p><?= number_format($itemCount) ?> items · stock on hand worth <?= money($stockValue) ?></p>
  </div>
  <?php if ($canManage): ?>
    <div class="pagehead__actions">
      <button class="btn btn--ghost" data-print><?= icon('print', 17) ?> Print</button>
      <button class="btn btn--gold" data-modal="moveModal"><?= icon('refresh', 17) ?> Stock Movement</button>
      <button class="btn btn--primary" data-modal="itemModal" data-primary-action
              data-fill='{"id":"","item_name":"","sku":"","quantity":"","reorder_level":"","unit_cost":"","location":"","expiry_date":""}'
              data-fill-text='{"title":"Add Stock Item"}'>
        <?= icon('plus', 17) ?> Add Item
      </button>
    </div>
  <?php endif; ?>
</div>

<section class="grid grid--4 mb-18">
  <article class="stat stat--green reveal">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('inventory', 22) ?></span>
      <div>
        <div class="stat__label">Items Tracked</div>
        <div class="stat__value" data-count="<?= $itemCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot"><span><?= count($categories) ?> categories</span></div>
  </article>

  <article class="stat stat--blue reveal" data-delay="60">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('finance', 22) ?></span>
      <div>
        <div class="stat__label">Stock Value</div>
        <div class="stat__value" data-count="<?= $stockValue ?>"
             data-prefix="<?= e(currency()) ?> " data-decimals="0">0</div>
      </div>
    </div>
    <div class="stat__foot"><span>At current unit cost</span></div>
  </article>

  <article class="stat stat--red reveal" data-delay="120">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('warning', 22) ?></span>
      <div>
        <div class="stat__label">Below Reorder</div>
        <div class="stat__value" data-count="<?= $lowCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <a class="auth__link small" href="<?= url('pages/inventory.php?stock=low') ?>">Show these items</a>
    </div>
  </article>

  <article class="stat stat--gold reveal" data-delay="180">
    <div class="stat__top">
      <span class="stat__icon"><?= icon('clock', 22) ?></span>
      <div>
        <div class="stat__label">Expiring Soon</div>
        <div class="stat__value" data-count="<?= $expCount ?>">0</div>
      </div>
    </div>
    <div class="stat__foot">
      <a class="auth__link small" href="<?= url('pages/inventory.php?stock=expiring') ?>">Within 90 days</a>
    </div>
  </article>
</section>

<section class="grid grid--2-1 mb-18">
  <article class="card reveal" data-delay="200">
    <form class="toolbar" method="get">
      <div class="field-inline">
        <?= icon('search', 16) ?>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search item, SKU or location…">
      </div>
      <div class="field-inline">
        <?= icon('inventory', 16) ?>
        <select name="category" data-autosubmit>
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"<?= selected($category, $c['id']) ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-inline">
        <?= icon('filter', 16) ?>
        <select name="stock" data-autosubmit>
          <option value="">All stock</option>
          <option value="low"<?= selected($stock, 'low') ?>>Below reorder level</option>
          <option value="out"<?= selected($stock, 'out') ?>>Out of stock</option>
          <option value="expiring"<?= selected($stock, 'expiring') ?>>Expiring soon</option>
        </select>
      </div>
      <button class="btn btn--sm btn--soft" type="submit"><?= icon('search', 15) ?> Filter</button>
      <?php if ($search || $category || $stock): ?>
        <a class="btn btn--sm btn--plain" href="<?= url('pages/inventory.php') ?>"><?= icon('close', 15) ?> Clear</a>
      <?php endif; ?>
    </form>

    <?php if (!$items): ?>
      <div class="empty">
        <span class="empty__art"><?= icon('inventory', 30) ?></span>
        <h3>No stock items found</h3>
        <p>Nothing matches the current filters. Add an item, or clear the filters.</p>
      </div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Category</th>
              <th class="num">In Stock</th>
              <th>Level</th>
              <th class="num">Unit Cost</th>
              <th class="num">Value</th>
              <?php if ($canManage): ?><th class="actions">Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $i): ?>
              <?php
                $isLow  = $i['quantity'] <= $i['reorder_level'];
                $isOut  = $i['quantity'] <= 0;
                $ratio  = $i['reorder_level'] > 0
                        ? min(100, percent_of($i['quantity'], $i['reorder_level'] * 2))
                        : 100;
                $expDays = days_until($i['expiry_date']);
              ?>
              <tr>
                <td>
                  <div class="cellmain">
                    <span class="tile <?= $isOut ? 'tile--red' : ($isLow ? 'tile--gold' : '') ?>">
                      <?= icon(category_icon($i['category_icon']), 19) ?>
                    </span>
                    <span class="cellmain__text">
                      <span class="cellmain__title"><?= e($i['item_name']) ?></span>
                      <span class="cellmain__sub mono"><?= e($i['sku']) ?>
                        <?php if ($i['location']): ?> · <?= e($i['location']) ?><?php endif; ?>
                      </span>
                    </span>
                  </div>
                </td>
                <td class="small soft"><?= e($i['category_name']) ?></td>
                <td class="num tnum bold">
                  <?= qty($i['quantity']) ?> <span class="muted small"><?= e($i['unit']) ?></span>
                  <?php if ($expDays !== null && $expDays <= 90): ?>
                    <div class="tiny <?= $expDays < 0 ? 'c-danger' : 'c-warning' ?>">
                      <?= $expDays < 0 ? 'Expired' : 'Expires in ' . $expDays . 'd' ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="min-width:120px">
                  <div class="progress <?= $isOut ? 'progress--red' : ($isLow ? 'progress--gold' : '') ?>" style="height:6px">
                    <div class="progress__fill" data-value="<?= $ratio ?>"></div>
                  </div>
                  <div class="tiny muted mt-8">reorder at <?= qty($i['reorder_level']) ?></div>
                </td>
                <td class="num tnum"><?= money($i['unit_cost'], false) ?></td>
                <td class="num tnum"><?= money($i['quantity'] * $i['unit_cost'], false) ?></td>
                <?php if ($canManage): ?>
                  <td class="actions">
                    <div>
                      <button class="rowbtn" title="Stock movement"
                              data-modal="moveModal"
                              data-fill='<?= e(json_encode(['item_id' => $i['id']])) ?>'
                              data-fill-text='<?= e(json_encode(['item' => $i['item_name']])) ?>'>
                        <?= icon('refresh', 16) ?>
                      </button>
                      <button class="rowbtn" title="Edit"
                              data-modal="itemModal"
                              data-fill='<?= e(json_encode([
                                  'id'            => $i['id'],
                                  'item_name'     => $i['item_name'],
                                  'sku'           => $i['sku'],
                                  'category_id'   => $i['category_id'],
                                  'supplier_id'   => $i['supplier_id'],
                                  'unit'          => $i['unit'],
                                  'reorder_level' => $i['reorder_level'],
                                  'unit_cost'     => $i['unit_cost'],
                                  'location'      => $i['location'],
                                  'expiry_date'   => $i['expiry_date'],
                              ])) ?>'
                              data-fill-text='<?= e(json_encode(['title' => 'Edit ' . $i['item_name']])) ?>'>
                        <?= icon('edit', 16) ?>
                      </button>
                      <button class="rowbtn rowbtn--danger" title="Delete"
                              data-modal="deleteModal"
                              data-fill='<?= e(json_encode(['id' => $i['id']])) ?>'
                              data-fill-text='<?= e(json_encode(['name' => $i['item_name']])) ?>'>
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
  </article>

  <div class="grid" style="gap:18px;align-content:start">
    <article class="card reveal" data-delay="240">
      <div class="card__head"><h3><?= icon('chart-pie', 18) ?> Value by Category</h3></div>
      <div class="card__body">
        <?php if ($byCategory): ?>
          <div data-chart><script type="application/json"><?= json_encode($valueChart) ?></script></div>
        <?php else: ?>
          <p class="muted small text-c">No stock on hand.</p>
        <?php endif; ?>
      </div>
    </article>

    <article class="card reveal" data-delay="280">
      <div class="card__head">
        <h3><?= icon('activity', 18) ?> Recent Movements</h3>
      </div>
      <div class="card__body card__body--flush">
        <?php if (!$recentMoves): ?>
          <div class="empty" style="padding:30px 16px">
            <span class="empty__art"><?= icon('refresh', 26) ?></span>
            <h3>No movements yet</h3>
            <p>Receipts and issues will be listed here.</p>
          </div>
        <?php else: ?>
          <?php foreach ($recentMoves as $m): ?>
            <div class="listrow">
              <span class="tile tile--sm tile--<?= $m['type'] === 'in' ? '' : ($m['type'] === 'out' ? 'red' : 'blue') ?>">
                <?= icon($m['type'] === 'in' ? 'arrow-down' : ($m['type'] === 'out' ? 'arrow-up' : 'refresh'), 15) ?>
              </span>
              <span class="listrow__text">
                <span class="listrow__title"><?= e($m['item_name']) ?></span>
                <span class="listrow__sub">
                  <?= e($m['full_name'] ?? 'System') ?> · <?= e(time_ago($m['created_at'])) ?>
                  <?php if ($m['reference']): ?> · <?= e($m['reference']) ?><?php endif; ?>
                </span>
              </span>
              <span class="bold small nowrap <?= $m['type'] === 'in' ? 'c-success' : ($m['type'] === 'out' ? 'c-danger' : '') ?>">
                <?= $m['type'] === 'in' ? '+' : ($m['type'] === 'out' ? '−' : '=') ?><?= qty($m['quantity']) ?>
                <span class="muted tiny"><?= e($m['unit']) ?></span>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>
  </div>
</section>

<?php if ($canManage): ?>
<!-- ===================== ITEM MODAL ===================== -->
<div class="modal" id="itemModal">
  <div class="modal__panel modal__panel--wide">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">

      <div class="modal__head">
        <h3><?= icon('inventory', 19) ?> <span data-text="title">Add Stock Item</span></h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="form__row">
          <div class="field">
            <label for="item_name">Item name <span class="req">*</span></label>
            <input class="input" type="text" id="item_name" name="item_name"
                   placeholder="e.g. Layer Mash" required>
          </div>
          <div class="field">
            <label for="sku">SKU / code <span class="req">*</span></label>
            <input class="input mono" type="text" id="sku" name="sku" placeholder="e.g. FD-1001" required>
          </div>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="category_id">Category <span class="req">*</span></label>
            <select class="select" id="category_id" name="category_id" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="supplier_id">Supplier</label>
            <select class="select" id="supplier_id" name="supplier_id">
              <option value="">Not specified</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form__row--3 form__row">
          <div class="field">
            <label for="quantity">Opening quantity</label>
            <input class="input" type="number" step="0.01" min="0" id="quantity" name="quantity" placeholder="0.00">
            <span class="field__hint">Only used when creating. Later changes go through stock movements.</span>
          </div>
          <div class="field">
            <label for="unit">Unit <span class="req">*</span></label>
            <select class="select" id="unit" name="unit" required>
              <?php foreach (['bags','kg','litres','units','doses','vials','metres','pieces','tonnes'] as $u): ?>
                <option value="<?= $u ?>"><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="reorder_level">Reorder level</label>
            <input class="input" type="number" step="0.01" min="0" id="reorder_level"
                   name="reorder_level" placeholder="0.00">
          </div>
        </div>

        <div class="form__row--3 form__row">
          <div class="field field--money">
            <label for="unit_cost">Unit cost</label>
            <span class="prefix"><?= e(currency()) ?></span>
            <input class="input" type="number" step="0.01" min="0" id="unit_cost" name="unit_cost" placeholder="0.00">
          </div>
          <div class="field">
            <label for="location">Storage location</label>
            <input class="input" type="text" id="location" name="location" placeholder="e.g. Feed Store A">
          </div>
          <div class="field">
            <label for="expiry_date">Expiry date</label>
            <input class="input" type="date" id="expiry_date" name="expiry_date">
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Save Item</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== STOCK MOVEMENT MODAL ===================== -->
<div class="modal" id="moveModal">
  <div class="modal__panel">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="movement">

      <div class="modal__head">
        <h3><?= icon('refresh', 19) ?> Record Stock Movement</h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label for="item_id">Stock item <span class="req">*</span></label>
          <select class="select" id="item_id" name="item_id" required>
            <?php foreach (all('SELECT id, item_name, quantity, unit FROM inventory_items ORDER BY item_name') as $i): ?>
              <option value="<?= $i['id'] ?>">
                <?= e($i['item_name']) ?> — <?= qty($i['quantity']) ?> <?= e($i['unit']) ?> in stock
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Movement type <span class="req">*</span></label>
          <div class="radiocards">
            <label class="radiocard">
              <input type="radio" name="movement_type" value="in" checked>
              <span class="radiocard__body">
                <?= icon('arrow-down', 18) ?>
                <span>
                  <span class="radiocard__title">Stock In</span>
                  <span class="radiocard__sub">Delivery received</span>
                </span>
              </span>
            </label>
            <label class="radiocard">
              <input type="radio" name="movement_type" value="out">
              <span class="radiocard__body">
                <?= icon('arrow-up', 18) ?>
                <span>
                  <span class="radiocard__title">Stock Out</span>
                  <span class="radiocard__sub">Issued for use</span>
                </span>
              </span>
            </label>
            <label class="radiocard">
              <input type="radio" name="movement_type" value="adjustment">
              <span class="radiocard__body">
                <?= icon('edit', 18) ?>
                <span>
                  <span class="radiocard__title">Adjustment</span>
                  <span class="radiocard__sub">Set counted figure</span>
                </span>
              </span>
            </label>
          </div>
        </div>

        <div class="form__row">
          <div class="field">
            <label for="mq">Quantity <span class="req">*</span></label>
            <input class="input" type="number" step="0.01" min="0.01" id="mq" name="quantity"
                   placeholder="0.00" required>
          </div>
          <div class="field">
            <label for="reference">Reference</label>
            <input class="input" type="text" id="reference" name="reference"
                   placeholder="e.g. PO-2026-041">
          </div>
        </div>

        <div class="field">
          <label for="note">Note</label>
          <input class="input" type="text" id="note" name="note"
                 placeholder="e.g. Issued to the poultry house">
        </div>

        <div class="alert alert--info mt-8">
          <?= icon('info', 17) ?>
          <span>
            Every movement is written to the stock card, so the balance can always
            be traced back to who moved what and when.
          </span>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--gold"><?= icon('save', 17) ?> Update Stock</button>
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
        <h3>Delete this item?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> and its entire stock movement history
          will be removed.
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
