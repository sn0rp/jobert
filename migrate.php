<?php
require_once 'config.php';
require_once 'backend/database.php';

$db = db_connect();
$migrations_dir = __DIR__ . '/migrations';

// Create migrations table if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY, name TEXT, applied_at DATETIME)");

$direction = isset($argv[1]) && $argv[1] === 'down' ? 'down' : 'up';
$specific_migrations = isset($argv[2]) ? explode(' ', $argv[2]) : [];

if (empty($specific_migrations)) {
    $result = $db->query("SELECT name FROM migrations ORDER BY id DESC");
    $applied_migrations = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $applied_migrations[] = $row['name'];
    }
    $all_migrations = array_map('basename', glob($migrations_dir . '/*.php'));
    $specific_migrations = array_diff($all_migrations, $applied_migrations);
}

if ($direction === 'up') {
    foreach ($specific_migrations as $migration_name) {
        $migration_file = $migrations_dir . '/' . $migration_name;
        if (file_exists($migration_file)) {
            require_once $migration_file;
            $class_name = 'Migration_' . str_replace('.php', '', $migration_name);
            $migration_instance = new $class_name();
            
            $db->exec('BEGIN');
            try {
                $migration_instance->up($db);
                $db->exec("INSERT INTO migrations (name, applied_at) VALUES ('$migration_name', datetime('now'))");
                $db->exec('COMMIT');
                echo "Migration applied successfully: " . $migration_instance->getDescription() . "\n";
            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                echo "Error applying migration: " . $e->getMessage() . "\n";
                exit(1);
            }
        } else {
            echo "Migration file not found: $migration_name\n";
        }
    }
} else {
    foreach ($specific_migrations as $migration_name) {
        $migration_file = $migrations_dir . '/' . $migration_name;
        if (file_exists($migration_file)) {
            require_once $migration_file;
            $class_name = 'Migration_' . pathinfo($migration_name, PATHINFO_FILENAME);
            $migration_instance = new $class_name();
            
            $db->exec('BEGIN');
            try {
                $migration_instance->down($db);
                $db->exec("DELETE FROM migrations WHERE name = '$migration_name'");
                $db->exec('COMMIT');
                echo "Migration reverted successfully: " . $migration_instance->getDescription() . "\n";
            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                echo "Error reverting migration: " . $e->getMessage() . "\n";
                exit(1);
            }
        } else {
            echo "Migration file not found: $migration_name\n";
        }
    }
}

// Delete old migration files
$cutoff_date = strtotime('-30 days');
foreach (glob($migrations_dir . '/*.php') as $file) {
    if (filemtime($file) < $cutoff_date) {
        unlink($file);
        echo "Deleted old migration file: " . basename($file) . "\n";
        
        // Remove from database
        $migration_name = basename($file);
        $db->exec("DELETE FROM migrations WHERE name = '$migration_name'");
    }
}

echo "All migrations processed.\n";
