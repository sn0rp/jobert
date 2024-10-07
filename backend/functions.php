<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Job.php';

function include_template($template, $data = []) {
    global $config;
    if (!in_array($template, $config['allowed_routes'])) {
        throw new Exception('Invalid template name: ' . $template);
    }
    extract($data);
    include "../frontend/{$template}.php";
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function force_https() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if (!headers_sent()) {
            header("Status: 301 Moved Permanently");
            header(sprintf(
                'Location: https://%s%s',
                $_SERVER['HTTP_HOST'],
                $_SERVER['REQUEST_URI']
            ));
            exit();
        }
    }
}

function checkRateLimit($key, $limit, $period) {
    $file = sys_get_temp_dir() . "/rate_limit_{$key}.txt";
    $current_time = time();
    $data = file_exists($file) ? unserialize(file_get_contents($file)) : [];
    
    // Remove old entries
    $data = array_filter($data, function($time) use ($current_time, $period) {
        return $current_time - $time < $period;
    });
    
    if (count($data) >= $limit) {
        return false;
    }
    
    $data[] = $current_time;
    file_put_contents($file, serialize($data));
    return true;
}