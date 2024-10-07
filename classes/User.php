<?php
class User {
    private $id;
    private $username;

    public function __construct($id, $username) {
        $this->id = $id;
        $this->username = $username;
    }

    public function getUsername() {
        return $this->username;
    }

    public static function createTable($db) {
        $query = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT
        )";
        $db->exec($query);
    }

    public static function create($db, $username, $password) {
        $query = "INSERT INTO users (username, password) VALUES (:username, :password)";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->execute();
        return $db->lastInsertRowID();
    }

    public static function getById($db, $id) {
        try {
            $query = "SELECT * FROM users WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row) {
                return new User($row['id'], $row['username']);
            }
            return null;
        } catch (Exception $e) {
            error_log("Error in User::getById: " . $e->getMessage());
            throw new Exception("An error occurred while retrieving user data: " . $e->getMessage());
        }
    }

    public function getApplications($db, $sort_by = 'applied_date', $sort_order = 'DESC', $limit = null, $offset = null, $status_filter = []) {
        return Application::getAllForUser($db, $this->id, $sort_by, $sort_order, $limit, $offset, $status_filter);
    }

    public function getTotalApplications($db, $status_filter = []) {
        return Application::getTotalForUser($db, $this->id, $status_filter);
    }
}
