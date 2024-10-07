<?php
require_once '../classes/Auth.php';
require_once '../backend/database.php';
require_once '../backend/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } elseif (!checkRateLimit('login_attempts', 5, 300)) {
        $error = "Too many login attempts. Please try again later.";
    } else {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $password = $_POST['password']; // Don't sanitize password, as it may contain special characters

        $db = db_connect();
        if (Auth::login($db, $username, $password)) {
            Auth::regenerateSession();
            error_log("Login successful for user: " . $username);
            header('Location: /dashboard');
            exit();
        } else {
            error_log("Login failed for user: " . $username);
            $error = "Invalid username or password";
        }
    }
}
?>

<div class="login-container">
    <div class="login-form">
        <div class="logo-container">
            <img src="/icons/briefcase.svg" alt="Jobert logo" class="logo-icon">
            <span class="logo-text">Jobert</span>
        </div>
        <h1>Login</h1>
        <?php if (isset($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="/login">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary">Login</button>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        </form>
        <!--<p class="register-link">Don't have an account? <a href="/register">Register here</a></p>-->
    </div>
</div>