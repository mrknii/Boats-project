<?php
/**
 * ---------------------------------------------------------------------
 *  Database layer — a thin PDO wrapper
 * ---------------------------------------------------------------------
 *  Every query in this application goes through these helpers, and every
 *  one of them uses prepared statements. That is what keeps the system
 *  safe from SQL injection.
 * ---------------------------------------------------------------------
 */

class Database
{
    private static ?PDO $instance = null;

    /** Return the single shared PDO connection (singleton pattern). */
    public static function connect(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            self::fail($e);
        }

        return self::$instance;
    }

    /** Friendly connection-failure screen instead of a raw stack trace. */
    private static function fail(PDOException $e): void
    {
        $msg  = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $hint = str_contains($e->getMessage(), 'Unknown database')
            ? 'The database <code>' . DB_NAME . '</code> does not exist yet. Run the installer at <code>install.php</code>, or import <code>database/farm_db.sql</code> through phpMyAdmin.'
            : 'Make sure <strong>Apache</strong> and <strong>MySQL</strong> are both running in the XAMPP control panel, then check the credentials in <code>config/config.php</code>.';

        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>Database connection failed</title>';
        echo '<style>
            body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0d1512;color:#e8f0ec;
                 font:15px/1.6 system-ui,Segoe UI,Roboto,sans-serif}
            .box{max-width:640px;padding:40px;background:#152019;border:1px solid #24352b;
                 border-radius:18px;box-shadow:0 30px 60px -20px rgba(0,0,0,.6)}
            h1{margin:0 0 6px;font-size:20px;color:#f4b942}
            code{background:#0d1512;padding:2px 6px;border-radius:5px;color:#6fe0a0;font-size:13px}
            .err{margin-top:18px;padding:12px 14px;background:#0d1512;border-left:3px solid #e5484d;
                 border-radius:8px;font-family:ui-monospace,Consolas,monospace;font-size:12.5px;color:#ff9ea0}
        </style>';
        echo '<div class="box"><h1>&#9888; Database connection failed</h1>';
        echo '<p>' . $hint . '</p>';
        if (DEBUG_MODE) {
            echo '<div class="err">' . $msg . '</div>';
        }
        echo '</div>';
        exit;
    }
}

// ---------------------------------------------------------------------
//  Query shortcuts used throughout the application
// ---------------------------------------------------------------------

/** Get the shared PDO handle. */
function db(): PDO
{
    return Database::connect();
}

/** Run a prepared statement and return the PDOStatement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Fetch every matching row. */
function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Fetch a single row, or null when there is no match. */
function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch a single scalar value from the first column of the first row. */
function scalar(string $sql, array $params = [], $default = 0)
{
    $val = q($sql, $params)->fetchColumn();
    return $val === false || $val === null ? $default : $val;
}

/** Insert a row from an associative array and return the new id. */
function insert(string $table, array $data): int
{
    $cols         = array_keys($data);
    $placeholders = array_map(fn($c) => ':' . $c, $cols);

    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table,
        implode('`, `', $cols),
        implode(', ', $placeholders)
    );

    q($sql, $data);
    return (int) db()->lastInsertId();
}

/** Update a row by primary key from an associative array. */
function update(string $table, array $data, int $id, string $pk = 'id'): int
{
    $sets = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($data)));
    $data['__pk'] = $id;

    $sql = "UPDATE `$table` SET $sets WHERE `$pk` = :__pk";
    return q($sql, $data)->rowCount();
}

/** Delete a row by primary key. */
function delete_row(string $table, int $id, string $pk = 'id'): int
{
    return q("DELETE FROM `$table` WHERE `$pk` = ?", [$id])->rowCount();
}
