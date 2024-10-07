<?php
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net/npm/chart.js; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://placewaifu.com; connect-src 'self';");

header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

require_once '../config.php';
require_once '../backend/functions.php';
require_once '../backend/database.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start([
    'cookie_lifetime' => 14400,
    'gc_maxlifetime' => 14400,
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

$request_uri = $_SERVER['REQUEST_URI'];
$path = trim(parse_url($request_uri, PHP_URL_PATH), '/');

$route = $path ?: 'dashboard';
if (!in_array($route, $config['allowed_routes'])) {
    $route = 'dashboard';
}

error_log("Requested route: " . $route);

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function validate_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}

validate_csrf_token();

if (!in_array($route, ['login', 'logout', 'register']) && !Auth::isLoggedIn()) {
    header('Location: /login');
    exit();
}

$username = '';
if (Auth::isLoggedIn()) {
    $db = db_connect();
    $user = User::getById($db, $_SESSION['user_id']);
    $username = $user ? $user->getUsername() : '';
}

if ($route === 'logout') {
    Auth::logout();
    header('Location: /login');
    exit();
}

if (isAjaxRequest()) {
    // Handle AJAX requests
    $ajax_route = basename(filter_input(INPUT_POST, 'route', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) ?: 
                  basename(filter_input(INPUT_GET, 'route', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (in_array($ajax_route, $config['allowed_routes'])) {
        require_once "../frontend/" . $ajax_route . ".php";
    } else {
        // Handle invalid AJAX route
        http_response_code(404);
        echo json_encode(['error' => 'Invalid route']);
        exit;
    }
} else {
    // Handle regular page loads
    include_template('layout', ['content' => $route, 'nonce' => $nonce, 'username' => $username]);
}