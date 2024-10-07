<?php
$config = [
    'db_path' => __DIR__ . '/jobert.db',
    'allowed_routes' => ['layout', 'dashboard', 'login', 'logout'],
];

if (!file_exists($config['db_path'])) {
    error_log("Database file not found: " . $config['db_path']);
}