<?php
if ($argc < 3) {
    echo "Usage: php create_migration.php <migration_name> <description>\n";
    exit(1);
}

$migration_name = $argv[1];
$description = $argv[2];
$timestamp = date('YmdHis');
$filename = $timestamp . '_' . $migration_name . '.php';
$migrations_dir = __DIR__ . '/migrations';

if (!is_dir($migrations_dir)) {
    mkdir($migrations_dir, 0755, true);
}

$template = <<<EOT
<?php
class Migration_{$timestamp}_{$migration_name} {
    private \$id = '{$timestamp}_{$migration_name}';
    private \$description = '{$description}';

    public function getId() {
        return \$this->id;
    }

    public function getDescription() {
        return \$this->description;
    }

    public function up(\$db) {
        // Perform database changes
        // \$db->exec("CREATE TABLE IF NOT EXISTS new_table (id INTEGER PRIMARY KEY, name TEXT)");
    }

    public function down(\$db) {
        // Revert database changes
        // \$db->exec("DROP TABLE IF EXISTS new_table");
    }
}
EOT;

file_put_contents($migrations_dir . '/' . $filename, $template);
echo "Migration created: $filename\n";
