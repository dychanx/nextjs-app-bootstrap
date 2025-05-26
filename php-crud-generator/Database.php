<?php
// Database.php
// Oracle DB connection class using OOP

require_once 'config.php';

class Database {
    private $conn;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $connStr = getConnectionString();
        $this->conn = oci_connect(DB_USERNAME, DB_PASSWORD, $connStr);
        if (!$this->conn) {
            $e = oci_error();
            die("Oracle DB Connection failed: " . $e['message']);
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stid = oci_parse($this->conn, $sql);
        foreach ($params as $key => $val) {
            oci_bind_by_name($stid, $key, $params[$key]);
        }
        $r = oci_execute($stid);
        if (!$r) {
            $e = oci_error($stid);
            die("Oracle DB Query failed: " . $e['message']);
        }
        return $stid;
    }

    public function fetchAll($stid) {
        $rows = [];
        while (($row = oci_fetch_assoc($stid)) != false) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function close() {
        oci_close($this->conn);
    }
}
?>
