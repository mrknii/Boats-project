<?php
/**
 * ---------------------------------------------------------------------
 *  GreenAcres Farm Management System
 *  Global configuration
 * ---------------------------------------------------------------------
 *  Edit the DB_* constants below to match your XAMPP setup.
 *  On a default XAMPP install you normally only need to leave these
 *  values exactly as they are.
 * ---------------------------------------------------------------------
 */

// --- Database credentials -------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'farm_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // default XAMPP root password is empty
define('DB_CHARSET', 'utf8mb4');

// --- Application ----------------------------------------------------
define('APP_NAME', 'GreenAcres');
define('APP_TAGLINE', 'Farm Management System');
define('APP_VERSION', '1.0.0');

// Absolute filesystem path to the project root (no trailing slash)
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Session lifetime in seconds (2 hours of inactivity logs the user out)
define('SESSION_TIMEOUT', 7200);

// Rows per page in listing tables
define('PER_PAGE', 10);

// Show detailed PHP errors. Set to false when presenting/deploying.
define('DEBUG_MODE', true);

// ---------------------------------------------------------------------
//  Bootstrapping
// ---------------------------------------------------------------------
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

date_default_timezone_set('Africa/Accra');

/**
 * Work out the base URL of the application automatically, so the project
 * runs from any folder inside htdocs without editing anything.
 * e.g.  http://localhost/farm-management-system
 */
if (!defined('BASE_URL')) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script  = $_SERVER['SCRIPT_NAME'] ?? '';
    // Strip the /pages/... segment so links resolve from any depth
    $dir     = str_replace('\\', '/', dirname($script));
    $dir     = preg_replace('#/(pages|config|includes)(/.*)?$#', '', $dir);
    $dir     = rtrim($dir, '/');
    define('BASE_URL', $scheme . '://' . $host . $dir);
}

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/icons.php';
require_once ROOT_PATH . '/includes/auth.php';
