<?php
if (!defined('DATABASE_INCLUDED')) {
    define('DATABASE_INCLUDED', true);

function db_connect() {
    global $config;
    if (!isset($config) || !isset($config['db_path']) || empty($config['db_path'])) {
        $config_file = __DIR__ . '/../config.php';
        if (file_exists($config_file)) {
            require $config_file;
            error_log("Config file loaded successfully.");
        } else {
            throw new Exception("Config file not found: " . $config_file);
        }
    }
    if (!isset($config['db_path']) || empty($config['db_path'])) {
        throw new Exception("Database path is not set in the configuration");
    }
    $db_path = $config['db_path'];
    error_log("Attempting to connect to database.");
    if (!file_exists($db_path)) {
        throw new Exception("Database file not found: " . $db_path);
    }
    $db = new SQLite3($db_path);
    $db->enableExceptions(true);
    error_log("Successfully connected to database");
    return $db;
}

function db_query($query, $params = []) {
    $db = db_connect();
    try {
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $result = $stmt->execute();
        return $result;
    } finally {
        db_close($db);
    }
}

function db_close($db) {
    if ($db instanceof SQLite3) {
        $db->close();
    }
}

}  // End of the if (!defined('DATABASE_INCLUDED')) block