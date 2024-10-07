<?php
class Application {
    private $id;
    private $user_id;
    private $job_title;
    private $company;
    private $status;
    private $applied_date;
    private $last_updated;
    private $url;
    private $response_time;

    public function __construct($id, $user_id, $job_title, $company, $status, $applied_date, $last_updated, $url, $response_time) {
        $this->id = $id;
        $this->user_id = $user_id;
        $this->job_title = $job_title;
        $this->company = $company;
        $this->status = $status;
        $this->applied_date = $applied_date;
        $this->last_updated = $last_updated;
        $this->url = $url;
        $this->response_time = $response_time;
    }

    // Getter and setter methods for all properties

    public function getId() {
        return $this->id;
    }

    public function getJobTitle() {
        return $this->job_title;
    }

    public function getCompany() {
        return $this->company;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getAppliedDate() {
        return $this->applied_date;
    }

    public function getLastUpdated() {
        return $this->last_updated;
    }

    public function getUrl() {
        return $this->url;
    }

    public function getUserId() {
        return $this->user_id;
    }

    public function getResponseTime() {
        return $this->response_time;
    }

    public static function createTable($db) {
        $query = "CREATE TABLE IF NOT EXISTS applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            job_title TEXT,
            company TEXT,
            status TEXT,
            applied_date TEXT,
            last_updated TEXT,
            url TEXT
        )";
        $db->exec($query);
    }

    public static function create($db, $user_id, $job_title, $company, $status, $applied_date, $url) {
        $query = "INSERT INTO applications (user_id, job_title, company, status, applied_date, last_updated, url) 
                  VALUES (:user_id, :job_title, :company, :status, :applied_date, :last_updated, :url)";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':job_title', $job_title, SQLITE3_TEXT);
        $stmt->bindValue(':company', $company, SQLITE3_TEXT);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':applied_date', $applied_date, SQLITE3_TEXT);
        $stmt->bindValue(':last_updated', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $stmt->execute();
        return $db->lastInsertRowID();
    }

    public static function getAllForUser($db, $user_id, $sort_by = 'applied_date', $sort_order = 'DESC', $limit = null, $offset = null, $status_filter = []) {
        $query = "SELECT * FROM applications WHERE user_id = :user_id";
        
        if (!empty($status_filter)) {
            $placeholders = [];
            foreach ($status_filter as $index => $status) {
                $placeholders[] = ":status_$index";
            }
            $query .= " AND status IN (" . implode(',', $placeholders) . ")";
        }
        
        $query .= " ORDER BY $sort_by $sort_order";
        
        if ($limit !== null && $offset !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        error_log("Query: " . $query);
        error_log("User ID: " . $user_id);
        error_log("Status filter: " . json_encode($status_filter));
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        
        if (!empty($status_filter)) {
            foreach ($status_filter as $index => $status) {
                $stmt->bindValue(":status_$index", $status, SQLITE3_TEXT);
            }
        }
        
        if ($limit !== null && $offset !== null) {
            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
            $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        }
        
        $result = $stmt->execute();

        $applications = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $applications[] = new Application(
                $row['id'],
                $row['user_id'],
                $row['job_title'],
                $row['company'],
                $row['status'],
                $row['applied_date'],
                $row['last_updated'],
                $row['url'],
                $row['response_time']
            );
        }
        error_log("Retrieved applications: " . count($applications));
        return $applications;
    }

    public static function getTotalForUser($db, $user_id, $status_filter = []) {
        $query = "SELECT COUNT(*) as total FROM applications WHERE user_id = :user_id";
        
        if (!empty($status_filter)) {
            $placeholders = [];
            foreach ($status_filter as $index => $status) {
                $placeholders[] = ":status_$index";
            }
            $query .= " AND status IN (" . implode(',', $placeholders) . ")";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        
        if (!empty($status_filter)) {
            foreach ($status_filter as $index => $status) {
                $stmt->bindValue(":status_$index", $status, SQLITE3_TEXT);
            }
        }
        
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['total'];
    }

    public function update($db) {
        $query = "UPDATE applications SET 
              status = :status, 
              last_updated = :last_updated";
        
        $params = [
            ':status' => $this->status,
            ':last_updated' => date('Y-m-d H:i:s'),
            ':id' => $this->id,
            ':user_id' => $this->user_id
        ];

        if ($this->status !== 'applied' && $this->response_time === null) {
            $query .= ", response_time = :response_time";
            $params[':response_time'] = floor((strtotime($params[':last_updated']) - strtotime($this->applied_date)) / (60 * 60 * 24));
        }

        $query .= " WHERE id = :id AND user_id = :user_id";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $result = $stmt->execute();
        return $result !== false;
    }

    public static function delete($db, $id) {
        $query = "DELETE FROM applications WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result !== false;
    }

    public static function getById($db, $id) {
        $query = "SELECT * FROM applications WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return new Application(
                $row['id'],
                $row['user_id'],
                $row['job_title'],
                $row['company'],
                $row['status'],
                $row['applied_date'],
                $row['last_updated'],
                $row['url'],
                $row['response_time']
            );
        }
        return null;
    }

    public function setStatus($status) {
        $this->status = $status;
    }
}