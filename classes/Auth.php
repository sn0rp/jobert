<?php
class Auth {
    public static function login($db, $username, $password) {
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            session_regenerate_id(true);
            return true;
        }
        return false;
    }

    public static function logout() {
        unset($_SESSION['user_id']);
        session_destroy();
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit();
        }
    }

    public static function register($db, $username, $password) {
        // Check if username already exists
        if (strlen($username) < 3 || strlen($username) > 20 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return 'invalid_username';
        }
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            return 'exists';
        }

        if (!self::isPasswordComplex($password)) {
            return 'weak_password';
        }

        $query = "INSERT INTO users (username, password) VALUES (:username, :password)";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function isPasswordComplex($password) {
        return (strlen($password) >= 8 &&
                preg_match('/[A-Z]/', $password) &&
                preg_match('/[a-z]/', $password) &&
                preg_match('/[0-9]/', $password) &&
                preg_match('/[^A-Za-z0-9]/', $password));
    }

    public static function regenerateSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}