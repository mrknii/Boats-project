<?php
/**
 * ---------------------------------------------------------------------
 *  Farm settings — administrator only
 * ---------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_admin();

/** Write a setting, inserting it when the key does not exist yet. */
function save_setting(string $key, string $value): void
{
    q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
      [$key, $value]);
}

if (is_post()) {
    csrf_verify();

    $section = post('section');

    if ($section === 'farm') {
        $errors = validate([
            'farm_name'  => ['required' => true, 'max' => 120, 'label' => 'Farm name'],
            'farm_email' => ['email' => true, 'label' => 'Farm email'],
        ]);

        if ($errors) {
            flash('danger', reset($errors));
        } else {
            foreach (['farm_name', 'farm_owner', 'farm_location', 'farm_email',
                      'farm_phone', 'established'] as $key) {
                save_setting($key, post($key));
            }
            log_activity('settings', 'update', 'Updated the farm profile');
            flash('success', 'The farm profile was saved.');
        }
        redirect('pages/settings.php');
    }

    if ($section === 'system') {
        save_setting('currency_symbol', post('currency_symbol') ?: 'GHS');
        save_setting('currency_code',   post('currency_code') ?: 'GHS');
        save_setting('date_format',     post('date_format') ?: 'd M Y');
        save_setting('low_stock_alerts', isset($_POST['low_stock_alerts']) ? '1' : '0');
        log_activity('settings', 'update', 'Updated system preferences');
        flash('success', 'System preferences were saved.');
        redirect('pages/settings.php');
    }

    if ($section === 'category_livestock') {
        $name = post('name');
        if ($name === '') {
            flash('danger', 'Give the category a name.');
        } elseif (!is_unique('livestock_categories', 'name', $name)) {
            flash('danger', 'A livestock category with that name already exists.');
        } else {
            insert('livestock_categories', [
                'name'        => $name,
                'icon'        => post('icon') ?: 'cow',
                'description' => post_or_null('description'),
            ]);
            log_activity('settings', 'create', 'Added livestock category ' . $name);
            flash('success', $name . ' was added as a livestock category.');
        }
        redirect('pages/settings.php');
    }

    if ($section === 'category_inventory') {
        $name = post('name');
        if ($name === '') {
            flash('danger', 'Give the category a name.');
        } elseif (!is_unique('inventory_categories', 'name', $name)) {
            flash('danger', 'An inventory category with that name already exists.');
        } else {
            insert('inventory_categories', ['name' => $name, 'icon' => post('icon') ?: 'box']);
            log_activity('settings', 'create', 'Added inventory category ' . $name);
            flash('success', $name . ' was added as an inventory category.');
        }
        redirect('pages/settings.php');
    }

    if ($section === 'delete_category') {
        $table = post('table') === 'livestock' ? 'livestock_categories' : 'inventory_categories';
        $id    = post_int('id');
        $col   = $table === 'livestock_categories' ? 'livestock' : 'inventory_items';
        $used  = (int) scalar("SELECT COUNT(*) FROM `$col` WHERE category_id = ?", [$id]);

        if ($used > 0) {
            flash('danger', 'That category is still used by ' . $used . ' record(s) and cannot be deleted.');
        } else {
            delete_row($table, $id);
            log_activity('settings', 'delete', 'Deleted a category');
            flash('success', 'The category was deleted.');
        }
        redirect('pages/settings.php');
    }
}

$livestockCats = all(
    'SELECT c.*, (SELECT COUNT(*) FROM livestock l WHERE l.category_id = c.id) AS in_use
       FROM livestock_categories c ORDER BY c.name'
);
$inventoryCats = all(
    'SELECT c.*, (SELECT COUNT(*) FROM inventory_items i WHERE i.category_id = c.id) AS in_use
       FROM inventory_categories c ORDER BY c.name'
);

$iconChoices = ['cow', 'goat', 'sheep', 'poultry', 'pig'];
$invIcons    = ['feed', 'seed', 'fertilizer', 'spray', 'medical', 'tool', 'fuel', 'box'];

$dbStats = [
    'Livestock records'   => (int) scalar('SELECT COUNT(*) FROM livestock'),
    'Health records'      => (int) scalar('SELECT COUNT(*) FROM health_records'),
    'Production entries'  => (int) scalar('SELECT COUNT(*) FROM production_records'),
    'Crop records'        => (int) scalar('SELECT COUNT(*) FROM crops'),
    'Harvest records'     => (int) scalar('SELECT COUNT(*) FROM harvests'),
    'Inventory items'     => (int) scalar('SELECT COUNT(*) FROM inventory_items'),
    'Transactions'        => (int) scalar('SELECT COUNT(*) FROM transactions'),
    'Tasks'               => (int) scalar('SELECT COUNT(*) FROM tasks'),
    'Employees'           => (int) scalar('SELECT COUNT(*) FROM employees'),
    'User accounts'       => (int) scalar('SELECT COUNT(*) FROM users'),
];

$pageTitle    = 'Settings';
$pageSubtitle = 'Farm profile, preferences and reference data.';
$activeNav    = 'settings';
$breadcrumbs  = ['Dashboard' => 'pages/dashboard.php', 'Settings' => null];

require_once ROOT_PATH . '/includes/layout_head.php';
?>

<div class="pagehead">
  <div class="pagehead__text">
    <h1><?= icon('settings', 24, 'c-brand') ?> System Settings</h1>
    <p>Configure how the system describes and calculates things.</p>
  </div>
</div>

<section class="grid grid--2 mb-18">
  <!-- ---------------- Farm profile ---------------- -->
  <article class="card reveal">
    <div class="card__head">
      <h3><?= icon('home', 18) ?> Farm Profile</h3>
      <span class="card__sub">Appears on reports and printouts</span>
    </div>
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="farm">

      <div class="card__body">
        <div class="field field--icon">
          <label for="farm_name">Farm name <span class="req">*</span></label>
          <span class="field__icon"><?= icon('home', 17) ?></span>
          <input class="input" type="text" id="farm_name" name="farm_name"
                 value="<?= e(setting('farm_name', APP_NAME)) ?>" required>
        </div>

        <div class="form__row">
          <div class="field field--icon">
            <label for="farm_owner">Owner</label>
            <span class="field__icon"><?= icon('user', 17) ?></span>
            <input class="input" type="text" id="farm_owner" name="farm_owner"
                   value="<?= e(setting('farm_owner')) ?>">
          </div>
          <div class="field">
            <label for="established">Established</label>
            <input class="input" type="text" id="established" name="established"
                   value="<?= e(setting('established')) ?>" placeholder="e.g. 2016">
          </div>
        </div>

        <div class="field field--icon">
          <label for="farm_location">Location</label>
          <span class="field__icon"><?= icon('pin', 17) ?></span>
          <input class="input" type="text" id="farm_location" name="farm_location"
                 value="<?= e(setting('farm_location')) ?>" placeholder="Town, region, country">
        </div>

        <div class="form__row">
          <div class="field field--icon">
            <label for="farm_email">Email</label>
            <span class="field__icon"><?= icon('mail', 17) ?></span>
            <input class="input" type="email" id="farm_email" name="farm_email"
                   value="<?= e(setting('farm_email')) ?>">
          </div>
          <div class="field field--icon">
            <label for="farm_phone">Phone</label>
            <span class="field__icon"><?= icon('phone', 17) ?></span>
            <input class="input" type="text" id="farm_phone" name="farm_phone"
                   value="<?= e(setting('farm_phone')) ?>">
          </div>
        </div>
      </div>

      <div class="card__foot text-r">
        <button class="btn btn--primary" type="submit"><?= icon('save', 17) ?> Save Profile</button>
      </div>
    </form>
  </article>

  <!-- ---------------- System preferences ---------------- -->
  <article class="card reveal" data-delay="80">
    <div class="card__head">
      <h3><?= icon('settings', 18) ?> Preferences</h3>
      <span class="card__sub">Currency and formatting</span>
    </div>
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="system">

      <div class="card__body">
        <div class="form__row">
          <div class="field">
            <label for="currency_symbol">Currency symbol</label>
            <input class="input" type="text" id="currency_symbol" name="currency_symbol"
                   value="<?= e(setting('currency_symbol', 'GHS')) ?>" maxlength="6">
            <span class="field__hint">Shown before every amount, e.g. GHS 1,250.00</span>
          </div>
          <div class="field">
            <label for="currency_code">Currency code</label>
            <input class="input" type="text" id="currency_code" name="currency_code"
                   value="<?= e(setting('currency_code', 'GHS')) ?>" maxlength="6">
          </div>
        </div>

        <div class="field">
          <label for="date_format">Date format</label>
          <select class="select" id="date_format" name="date_format">
            <?php foreach (['d M Y' => '15 Aug 2026', 'd/m/Y' => '15/08/2026',
                            'Y-m-d' => '2026-08-15', 'M d, Y' => 'Aug 15, 2026'] as $fmt => $sample): ?>
              <option value="<?= e($fmt) ?>"<?= selected(setting('date_format', 'd M Y'), $fmt) ?>>
                <?= e($sample) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="divider"></div>

        <label class="switch">
          <input type="checkbox" name="low_stock_alerts" value="1"
                 <?= checked(setting('low_stock_alerts', '1') === '1') ?>>
          <span class="switch__track"></span>
          <span class="switch__label">Show low stock alerts in the notification bell</span>
        </label>

        <div class="alert alert--info mt-14">
          <?= icon('info', 17) ?>
          <span>
            The currency symbol is a label only — no exchange rate conversion is applied
            to figures already stored in the database.
          </span>
        </div>
      </div>

      <div class="card__foot text-r">
        <button class="btn btn--primary" type="submit"><?= icon('save', 17) ?> Save Preferences</button>
      </div>
    </form>
  </article>
</section>

<!-- ---------------- Reference data ---------------- -->
<section class="grid grid--2 mb-18">
  <article class="card reveal" data-delay="140">
    <div class="card__head">
      <h3><?= icon('livestock', 18) ?> Livestock Categories</h3>
      <div class="card__actions">
        <button class="btn btn--sm btn--soft" data-modal="lsCatModal"><?= icon('plus', 15) ?> Add</button>
      </div>
    </div>
    <div class="card__body card__body--flush">
      <?php foreach ($livestockCats as $c): ?>
        <div class="listrow">
          <span class="tile"><?= icon(category_icon($c['icon']), 18) ?></span>
          <span class="listrow__text">
            <span class="listrow__title"><?= e($c['name']) ?></span>
            <span class="listrow__sub"><?= e($c['description'] ?: 'No description') ?></span>
          </span>
          <span class="badge badge--neutral"><?= (int) $c['in_use'] ?> animals</span>
          <?php if ((int) $c['in_use'] === 0): ?>
            <button class="rowbtn rowbtn--danger" title="Delete"
                    data-modal="delCatModal"
                    data-fill='<?= e(json_encode(['id' => $c['id'], 'table' => 'livestock'])) ?>'
                    data-fill-text='<?= e(json_encode(['name' => $c['name']])) ?>'>
              <?= icon('trash', 15) ?>
            </button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="card reveal" data-delay="180">
    <div class="card__head">
      <h3><?= icon('inventory', 18) ?> Inventory Categories</h3>
      <div class="card__actions">
        <button class="btn btn--sm btn--soft" data-modal="invCatModal"><?= icon('plus', 15) ?> Add</button>
      </div>
    </div>
    <div class="card__body card__body--flush">
      <?php foreach ($inventoryCats as $c): ?>
        <div class="listrow">
          <span class="tile tile--gold"><?= icon(category_icon($c['icon']), 18) ?></span>
          <span class="listrow__text">
            <span class="listrow__title"><?= e($c['name']) ?></span>
          </span>
          <span class="badge badge--neutral"><?= (int) $c['in_use'] ?> items</span>
          <?php if ((int) $c['in_use'] === 0): ?>
            <button class="rowbtn rowbtn--danger" title="Delete"
                    data-modal="delCatModal"
                    data-fill='<?= e(json_encode(['id' => $c['id'], 'table' => 'inventory'])) ?>'
                    data-fill-text='<?= e(json_encode(['name' => $c['name']])) ?>'>
              <?= icon('trash', 15) ?>
            </button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>

<!-- ---------------- System information ---------------- -->
<section class="grid grid--2">
  <article class="card reveal" data-delay="220">
    <div class="card__head"><h3><?= icon('reports', 18) ?> Database Contents</h3></div>
    <div class="card__body">
      <?php foreach ($dbStats as $labelText => $count): ?>
        <div class="metric-row">
          <span class="metric-row__text">
            <span class="metric-row__name"><?= e($labelText) ?></span>
          </span>
          <span class="metric-row__val"><?= number_format($count) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="card reveal" data-delay="260">
    <div class="card__head"><h3><?= icon('info', 18) ?> System Information</h3></div>
    <div class="card__body">
      <div class="kv">
        <div class="kv__row">
          <span class="kv__key"><?= icon('sparkle', 14) ?> Application</span>
          <span class="kv__val"><?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?></span>
        </div>
        <div class="kv__row">
          <span class="kv__key"><?= icon('tag', 14) ?> Version</span>
          <span class="kv__val"><?= e(APP_VERSION) ?></span>
        </div>
        <div class="kv__row">
          <span class="kv__key"><?= icon('grid', 14) ?> PHP</span>
          <span class="kv__val"><?= e(PHP_VERSION) ?></span>
        </div>
        <div class="kv__row">
          <span class="kv__key"><?= icon('inventory', 14) ?> Database</span>
          <span class="kv__val mono"><?= e(DB_NAME) ?> on <?= e(DB_HOST) ?></span>
        </div>
        <div class="kv__row">
          <span class="kv__key"><?= icon('clock', 14) ?> Server time</span>
          <span class="kv__val"><?= fdatetime(date('Y-m-d H:i:s')) ?></span>
        </div>
        <div class="kv__row">
          <span class="kv__key"><?= icon('shield', 14) ?> Session timeout</span>
          <span class="kv__val"><?= round(SESSION_TIMEOUT / 60) ?> minutes</span>
        </div>
      </div>

      <div class="alert alert--warning mt-14">
        <?= icon('warning', 17) ?>
        <span>
          Before presenting or deploying, set <code>DEBUG_MODE</code> to
          <code>false</code> in <code>config/config.php</code> so PHP errors are
          hidden from users.
        </span>
      </div>
    </div>
  </article>
</section>

<!-- ===================== MODALS ===================== -->
<div class="modal" id="lsCatModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="category_livestock">

      <div class="modal__head">
        <h3><?= icon('livestock', 19) ?> Add Livestock Category</h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label for="ls_name">Category name <span class="req">*</span></label>
          <input class="input" type="text" id="ls_name" name="name" placeholder="e.g. Rabbits" required>
        </div>
        <div class="field">
          <label for="ls_icon">Icon</label>
          <select class="select" id="ls_icon" name="icon">
            <?php foreach ($iconChoices as $ic): ?>
              <option value="<?= $ic ?>"><?= e(label($ic)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="ls_desc">Description</label>
          <input class="input" type="text" id="ls_desc" name="description" placeholder="Optional">
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Add Category</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="invCatModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" class="form" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="category_inventory">

      <div class="modal__head">
        <h3><?= icon('inventory', 19) ?> Add Inventory Category</h3>
        <button type="button" class="iconbtn modal__close" data-close-modal><?= icon('close', 18) ?></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <label for="inv_name">Category name <span class="req">*</span></label>
          <input class="input" type="text" id="inv_name" name="name" placeholder="e.g. Packaging" required>
        </div>
        <div class="field">
          <label for="inv_icon">Icon</label>
          <select class="select" id="inv_icon" name="icon">
            <?php foreach ($invIcons as $ic): ?>
              <option value="<?= $ic ?>"><?= e(label($ic)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn--primary"><?= icon('save', 17) ?> Add Category</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="delCatModal">
  <div class="modal__panel modal__panel--sm">
    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="delete_category">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="table" value="">
      <div class="modal__body text-c">
        <span class="confirm-art"><?= icon('trash', 26) ?></span>
        <h3>Delete this category?</h3>
        <p class="soft small mt-8">
          <strong data-text="name"></strong> will be removed. Categories still in use
          by existing records cannot be deleted.
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
