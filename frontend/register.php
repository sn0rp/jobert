<?php
require_once '../classes/Auth.php';
require_once '../backend/database.php';
require_once '../backend/functions.php';

$error = '';
$username_requirements = "Username must be 3-20 characters long and contain only letters, numbers, and underscores.";
$password_requirements = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } elseif (!checkRateLimit('register_attempts', 3, 3600)) {
        $error = "Too many registration attempts. Please try again later.";
    } else {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $password = $_POST['password']; // Don't sanitize password
        $confirm_password = $_POST['confirm_password']; // Don't sanitize password

        if ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } else {
            $db = db_connect();
            $result = Auth::register($db, $username, $password);
            if ($result === true) {
                error_log("Registration successful for user: " . $username);
                header('Location: /login');
                exit();
            } elseif ($result === 'exists') {
                error_log("Registration failed: Username already exists - " . $username);
                $error = "Username already exists. Please choose a different username.";
            } elseif ($result === 'invalid_username') {
                error_log("Registration failed: Invalid username - " . $username);
                $error = "Invalid username. " . $username_requirements;
            } elseif ($result === 'weak_password') {
                error_log("Registration failed: Weak password for user - " . $username);
                $error = "Password does not meet the requirements. " . $password_requirements;
            } else {
                error_log("Registration failed: Unknown error for user - " . $username);
                $error = "Registration failed. Please try again.";
            }
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
        <h1>Register</h1>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="/register">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                <p class="password-requirements"><?php echo htmlspecialchars($username_requirements); ?></p>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <p class="password-requirements"><?php echo htmlspecialchars($password_requirements); ?></p>
            </div>
            
            <button type="submit" class="btn-primary">Register</button>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        </form>
        <p class="register-link">Already have an account? <a href="/login">Login here</a></p>
    </div>
</div>