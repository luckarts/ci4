<?php

// Guard against double-loading
if (defined('BASEPATH')) {
    return;
}

// Load environment variables from .env.test
$envFile = dirname(__DIR__) . '/.env.test';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false || strpos($line, '#') === 0) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Set testing environment
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

// Set up CI paths
$systemPath = dirname(__DIR__) . '/system';
$appPath = dirname(__DIR__) . '/application';
$viewPath = $appPath . '/views';

if (!is_dir($systemPath) || !is_dir($appPath)) {
    throw new RuntimeException('CodeIgniter paths not found');
}

define('SELF', 'index.php');
define('BASEPATH', $systemPath . DIRECTORY_SEPARATOR);
define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));
define('APPPATH', $appPath . DIRECTORY_SEPARATOR);
define('VIEWPATH', $viewPath . DIRECTORY_SEPARATOR);

// Load CodeIgniter base classes and core libraries
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'core/Exceptions.php';
require_once BASEPATH . 'core/Loader.php';

// Provide a minimal CI_Migration base class for tests
if (!class_exists('CI_Migration')) {
    class CI_Migration
    {
        public $dbforge;
        public $db;

        public function up() {}
        public function down() {}
    }
}

// Load database configuration
$dbConfig = require_once APPPATH . 'config/database.php';

// Get database config and apply environment overrides
$dbDefault = $dbConfig['default'] ?? [];
$dbDefault['hostname'] = getenv('DB_HOST') ?: ($dbDefault['hostname'] ?? 'localhost');
$dbDefault['port'] = getenv('DB_PORT') ?: ($dbDefault['port'] ?? 5432);
$dbDefault['database'] = getenv('DB_NAME') ?: ($dbDefault['database'] ?? '');
$dbDefault['username'] = getenv('DB_USER') ?: ($dbDefault['username'] ?? '');
$dbDefault['password'] = getenv('DB_PASSWORD') ?: ($dbDefault['password'] ?? '');

// Create a simple database connection object
class TestDatabase
{
    private $connection;
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function connect()
    {
        if ($this->connection) {
            return $this->connection;
        }

        try {
            $port = isset($this->config['port']) ? (int)$this->config['port'] : 5432;
            $this->connection = new PDO(
                'pgsql:host=' . $this->config['hostname'] .
                ';port=' . $port .
                ';dbname=' . $this->config['database'],
                $this->config['username'],
                $this->config['password']
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->connection;
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    public function __call($method, $args)
    {
        // Delegate to PDO methods
        if ($this->connection && method_exists($this->connection, $method)) {
            return call_user_func_array([$this->connection, $method], $args);
        }
        throw new RuntimeException("Method {$method} not found on PDO connection");
    }

    public function query($sql)
    {
        return $this->connect()->query($sql);
    }

    public function exec($sql)
    {
        return $this->connect()->exec($sql);
    }

    public function prepare($sql)
    {
        return $this->connect()->prepare($sql);
    }

    public function table_exists($table)
    {
        $query = $this->query("
            SELECT EXISTS(
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = 'public' AND table_name = '{$table}'
            )
        ");
        return $query->fetchColumn() === 1 || $query->fetchColumn() === 't';
    }

    public function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    public function select($table, $where = [])
    {
        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $col => $val) {
                $conditions[] = "{$col} = ?";
            }
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $stmt = $this->prepare($sql);
        $stmt->execute(array_values($where));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Store database in a global for test access
$GLOBALS['test_db'] = new TestDatabase($dbDefault);
$GLOBALS['test_db']->connect();

// Create a simple migration runner
class TestMigration
{
    private $db;
    private $migrationPath;

    public function __construct($db, $appPath)
    {
        $this->db = $db;
        $this->migrationPath = $appPath . 'migrations';
    }

    public function latest()
    {
        if (!is_dir($this->migrationPath)) {
            return false;
        }

        // Get all migration files
        $migrations = array_diff(scandir($this->migrationPath), ['.', '..']);
        sort($migrations);

        foreach ($migrations as $file) {
            if (!preg_match('/^\d+_.*\.php$/', $file)) {
                continue;
            }

            // Load the migration class
            $migrationFile = $this->migrationPath . '/' . $file;
            require_once $migrationFile;

            // Extract class name from filename - remove leading digits
            $baseName = pathinfo($file, PATHINFO_FILENAME);
            // Remove leading digits and underscore
            $withoutNum = preg_replace('/^\d+_/', '', $baseName);
            $className = 'Migration_' . str_replace(' ', '_', ucwords(str_replace('_', ' ', $withoutNum)));

            if (!class_exists($className)) {
                error_log("Migration class not found: {$className} (file: {$file})");
                continue;
            }

            // Create migration instance with database access
            $migration = new $className();

            // Provide database access to the migration
            $migration->dbforge = new SimpleDbForge($this->db);
            $migration->db = $this->db;

            // Run the migration
            if (method_exists($migration, 'up')) {
                try {
                    error_log("Running migration: {$file}");
                    $migration->up();
                    error_log("Migration {$file} completed");
                } catch (Throwable $e) {
                    error_log("Migration failed in {$file}: {$e->getMessage()} at " . $e->getFile() . ":" . $e->getLine());
                    throw new RuntimeException("Migration failed in {$file}: {$e->getMessage()} at " . $e->getFile() . ":" . $e->getLine());
                }
            }
        }

        return true;
    }
}

// Simple database forge implementation for migrations
class SimpleDbForge
{
    private $db;
    private $fields = [];
    private $keys = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function add_field($fields)
    {
        $this->fields = array_merge($this->fields, $fields);
        return $this;
    }

    public function add_key($field, $primary = false)
    {
        if ($primary) {
            $this->keys['primary'] = $field;
        }
        return $this;
    }

    public function create_table($table)
    {
        $sql = "CREATE TABLE {$table} (";
        $columns = [];

        foreach ($this->fields as $name => $field) {
            $col = "{$name} " . $this->_formatColumn($field);
            $columns[] = $col;
        }

        if (isset($this->keys['primary'])) {
            $columns[] = "PRIMARY KEY ({$this->keys['primary']})";
        }

        $sql .= implode(", ", $columns) . ")";

        error_log("Executing SQL: {$sql}");
        try {
            $this->db->exec($sql);
            error_log("Table {$table} created successfully");
        } catch (Throwable $e) {
            error_log("Error creating table: " . $e->getMessage());
            throw $e;
        }
        $this->fields = [];
        $this->keys = [];
    }

    public function drop_table($table)
    {
        $this->db->exec("DROP TABLE IF EXISTS {$table}");
    }

    private function _formatColumn($field)
    {
        $type = strtoupper($field['type'] ?? 'VARCHAR');

        // Handle constraint/length
        $constraint = '';
        if (isset($field['constraint'])) {
            $constraint = "({$field['constraint']})";
        }

        // Handle unique
        $unique = (isset($field['unique']) && $field['unique']) ? ' UNIQUE' : '';

        // Handle null
        $null = (isset($field['null']) && $field['null']) ? ' NULL' : '';

        // Handle default
        $default = '';
        if (isset($field['default']) && $field['default'] !== null) {
            if (is_bool($field['default'])) {
                $default = " DEFAULT " . ($field['default'] ? 'true' : 'false');
            } elseif (preg_match('/^\w+\(.*\)$/', (string)$field['default'])) {
                // If the default looks like a function call, don't quote it
                $default = " DEFAULT {$field['default']}";
            } else {
                $default = " DEFAULT '{$field['default']}'";
            }
        }

        return "{$type}{$constraint}{$unique}{$null}{$default}";
    }
}

$GLOBALS['test_migration'] = new TestMigration($GLOBALS['test_db'], APPPATH);
