<?php
/**
 * ---------------------------------------------------------------------
 *  Shared helper functions
 * ---------------------------------------------------------------------
 */

// ---------------------------------------------------------------------
//  Session
// ---------------------------------------------------------------------
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// ---------------------------------------------------------------------
//  Output escaping — used on every single piece of dynamic output
// ---------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------
//  URLs and redirects
// ---------------------------------------------------------------------
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Rebuild the current query string with some parameters replaced. */
function query_with(array $params): string
{
    $merged = array_merge($_GET, $params);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}

// ---------------------------------------------------------------------
//  CSRF protection
// ---------------------------------------------------------------------
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verify the posted token; abort the request when it does not match. */
function csrf_verify(): void
{
    start_session();
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(419);
        exit('Security token expired or invalid. Please go back and try again.');
    }
}

// ---------------------------------------------------------------------
//  Flash messages
// ---------------------------------------------------------------------
function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    start_session();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---------------------------------------------------------------------
//  Formatting
// ---------------------------------------------------------------------
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function currency(): string
{
    return setting('currency_symbol', 'GHS');
}

/** Format a number as money, e.g. GHS 12,875.00 */
function money($amount, bool $withSymbol = true): string
{
    $formatted = number_format((float) $amount, 2);
    return $withSymbol ? currency() . ' ' . $formatted : $formatted;
}

/** Compact money for stat cards, e.g. GHS 128.5K */
function money_short($amount): string
{
    $amount = (float) $amount;
    $abs    = abs($amount);
    if ($abs >= 1000000) {
        return currency() . ' ' . round($amount / 1000000, 2) . 'M';
    }
    if ($abs >= 1000) {
        return currency() . ' ' . round($amount / 1000, 1) . 'K';
    }
    return currency() . ' ' . number_format($amount, 2);
}

function qty($number, int $decimals = 2): string
{
    $number = (float) $number;
    return $number == (int) $number && $decimals === 2
        ? number_format($number)
        : number_format($number, $decimals);
}

function fdate(?string $date, string $format = 'd M Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function fdatetime(?string $date): string
{
    return fdate($date, 'd M Y, g:i A');
}

/** Human readable relative time, e.g. "3 days ago". */
function time_ago(?string $datetime): string
{
    if (empty($datetime)) {
        return '—';
    }
    $ts   = strtotime($datetime);
    $diff = time() - $ts;

    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . ' min ago';
    if ($diff < 86400)   return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800)  return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' week' . (floor($diff / 604800) > 1 ? 's' : '') . ' ago';

    return date('d M Y', $ts);
}

/** Days between today and a date. Negative means the date has passed. */
function days_until(?string $date): ?int
{
    if (empty($date)) {
        return null;
    }
    $target = strtotime(date('Y-m-d', strtotime($date)));
    $today  = strtotime(date('Y-m-d'));
    return (int) round(($target - $today) / 86400);
}

function age_from(?string $dob): string
{
    if (empty($dob)) {
        return '—';
    }
    $diff = (new DateTime($dob))->diff(new DateTime());
    if ($diff->y > 0) {
        return $diff->y . 'y ' . $diff->m . 'm';
    }
    if ($diff->m > 0) {
        return $diff->m . ' months';
    }
    return $diff->d . ' days';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Turn a snake_case enum value into a readable label. */
function label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

/** Percentage change between two numbers, guarding against divide by zero. */
function percent_change($current, $previous): float
{
    $current  = (float) $current;
    $previous = (float) $previous;
    if ($previous == 0.0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / abs($previous)) * 100, 1);
}

function percent_of($part, $total): float
{
    $total = (float) $total;
    return $total == 0.0 ? 0.0 : round(((float) $part / $total) * 100, 1);
}

// ---------------------------------------------------------------------
//  UI badge colour mapping
// ---------------------------------------------------------------------
function status_tone(string $status): string
{
    return match (strtolower($status)) {
        'healthy', 'active', 'completed', 'available', 'harvested', 'ready', 'in'      => 'success',
        'pregnant', 'growing', 'in_progress', 'flowering', 'cultivated', 'adjustment'  => 'info',
        'quarantine', 'on_leave', 'pending', 'preparation', 'planted', 'fallow'        => 'warning',
        'sick', 'deceased', 'failed', 'cancelled', 'terminated', 'out', 'suspended'    => 'danger',
        'sold'                                                                          => 'neutral',
        default                                                                         => 'neutral',
    };
}

function priority_tone(string $priority): string
{
    return match (strtolower($priority)) {
        'urgent' => 'danger',
        'high'   => 'warning',
        'medium' => 'info',
        default  => 'neutral',
    };
}

function badge(string $value, ?string $tone = null): string
{
    $tone = $tone ?? status_tone($value);
    return '<span class="badge badge--' . e($tone) . '"><i class="badge__dot"></i>' . e(label($value)) . '</span>';
}

// ---------------------------------------------------------------------
//  Input handling
// ---------------------------------------------------------------------
function post(string $key, $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function post_num(string $key, float $default = 0): float
{
    $value = str_replace(',', '', (string) ($_POST[$key] ?? $default));
    return is_numeric($value) ? (float) $value : $default;
}

function post_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

/** Return null for empty optional fields so the column stores NULL. */
function post_or_null(string $key): ?string
{
    $value = post($key);
    return $value === '' ? null : $value;
}

function get_param(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function current_page_number(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

// ---------------------------------------------------------------------
//  Validation
// ---------------------------------------------------------------------
function validate(array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $checks) {
        $value = trim((string) ($_POST[$field] ?? ''));
        $name  = $checks['label'] ?? label($field);

        if (!empty($checks['required']) && $value === '') {
            $errors[$field] = "$name is required.";
            continue;
        }
        if ($value === '') {
            continue;   // optional and empty — nothing else to check
        }
        if (!empty($checks['email']) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field] = "Enter a valid email address for $name.";
        }
        if (!empty($checks['numeric']) && !is_numeric(str_replace(',', '', $value))) {
            $errors[$field] = "$name must be a number.";
        }
        if (isset($checks['min']) && mb_strlen($value) < $checks['min']) {
            $errors[$field] = "$name must be at least {$checks['min']} characters.";
        }
        if (isset($checks['max']) && mb_strlen($value) > $checks['max']) {
            $errors[$field] = "$name may not exceed {$checks['max']} characters.";
        }
        if (isset($checks['gte']) && (float) $value < $checks['gte']) {
            $errors[$field] = "$name may not be less than {$checks['gte']}.";
        }
        if (!empty($checks['date']) && !strtotime($value)) {
            $errors[$field] = "$name must be a valid date.";
        }
        if (!empty($checks['in']) && !in_array($value, $checks['in'], true)) {
            $errors[$field] = "Choose a valid option for $name.";
        }
    }

    return $errors;
}

/** Check a value is unique in a table, optionally ignoring one row. */
function is_unique(string $table, string $column, string $value, ?int $ignoreId = null): bool
{
    $sql    = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
    $params = [$value];

    if ($ignoreId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }

    return (int) scalar($sql, $params) === 0;
}

// ---------------------------------------------------------------------
//  Audit trail
// ---------------------------------------------------------------------
function log_activity(string $module, string $action, string $description = ''): void
{
    try {
        insert('activity_log', [
            'user_id'     => current_user()['id'] ?? null,
            'module'      => $module,
            'action'      => $action,
            'description' => mb_substr($description, 0, 255),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Logging must never break the actual operation the user asked for.
    }
}

// ---------------------------------------------------------------------
//  Pagination
// ---------------------------------------------------------------------
function paginate(int $totalRows, int $perPage = PER_PAGE): array
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page       = min(current_page_number(), $totalPages);

    return [
        'page'       => $page,
        'per_page'   => $perPage,
        'offset'     => ($page - 1) * $perPage,
        'total'      => $totalRows,
        'total_pages'=> $totalPages,
        'from'       => $totalRows === 0 ? 0 : (($page - 1) * $perPage) + 1,
        'to'         => min($page * $perPage, $totalRows),
    ];
}

function render_pagination(array $p): string
{
    if ($p['total_pages'] <= 1) {
        return '<div class="pagination"><span class="pagination__info">Showing ' . $p['from'] . '–' . $p['to'] . ' of ' . $p['total'] . '</span></div>';
    }

    $html  = '<div class="pagination">';
    $html .= '<span class="pagination__info">Showing ' . $p['from'] . '–' . $p['to'] . ' of ' . $p['total'] . '</span>';
    $html .= '<div class="pagination__pages">';

    $prevDisabled = $p['page'] <= 1 ? ' is-disabled' : '';
    $html .= '<a class="pagination__btn' . $prevDisabled . '" href="' . e(query_with(['page' => $p['page'] - 1])) . '">' . icon('chevron-left', 16) . '</a>';

    $start = max(1, $p['page'] - 2);
    $end   = min($p['total_pages'], $start + 4);
    $start = max(1, $end - 4);

    if ($start > 1) {
        $html .= '<a class="pagination__btn" href="' . e(query_with(['page' => 1])) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="pagination__gap">…</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $p['page'] ? ' is-active' : '';
        $html  .= '<a class="pagination__btn' . $active . '" href="' . e(query_with(['page' => $i])) . '">' . $i . '</a>';
    }

    if ($end < $p['total_pages']) {
        if ($end < $p['total_pages'] - 1) {
            $html .= '<span class="pagination__gap">…</span>';
        }
        $html .= '<a class="pagination__btn" href="' . e(query_with(['page' => $p['total_pages']])) . '">' . $p['total_pages'] . '</a>';
    }

    $nextDisabled = $p['page'] >= $p['total_pages'] ? ' is-disabled' : '';
    $html .= '<a class="pagination__btn' . $nextDisabled . '" href="' . e(query_with(['page' => $p['page'] + 1])) . '">' . icon('chevron-right', 16) . '</a>';

    return $html . '</div></div>';
}

// ---------------------------------------------------------------------
//  File uploads
// ---------------------------------------------------------------------
function handle_upload(string $field, string $subfolder, ?string &$error = null): ?string
{
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The file could not be uploaded. Please try again.';
        return null;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        $error = 'Images may not be larger than 2 MB.';
        return null;
    }

    $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime     = mime_content_type($file['tmp_name']) ?: '';

    if (!isset($allowed[$mime])) {
        $error = 'Only JPG, PNG, WEBP and GIF images are accepted.';
        return null;
    }

    $dir = UPLOAD_PATH . '/' . $subfolder;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $name = $subfolder . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $error = 'The uploaded file could not be saved to the uploads folder.';
        return null;
    }

    return $subfolder . '/' . $name;
}

// ---------------------------------------------------------------------
//  Misc
// ---------------------------------------------------------------------
/** Build the list of month buckets used by the dashboard charts. */
function month_range(int $months = 12): array
{
    $range = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i month");
        $range[date('Y-m', $ts)] = date('M', $ts);
    }
    return $range;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Value for repopulating a form field after a validation failure. */
function old(string $key, $fallback = ''): string
{
    return e((string) ($_POST[$key] ?? $fallback));
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked($condition): string
{
    return $condition ? ' checked' : '';
}
