<?php
// CRUDGenerator.php
// Class to generate CRUD operations dynamically based on Oracle DB table metadata

require_once 'Database.php';

class CRUDGenerator {
    private $db;
    private $tableName;
    private $columns = [];
    private $primaryKey;

    public function __construct(Database $db, $tableName) {
        $this->db = $db;
        $this->tableName = strtoupper($tableName);
        $this->loadTableMetadata();
    }

    private function loadTableMetadata() {
        // Load columns and primary key info from Oracle data dictionary
        $sql = "SELECT column_name, data_type, nullable FROM all_tab_columns WHERE table_name = :table_name";
        $stid = $this->db->query($sql, [':table_name' => $this->tableName]);
        $this->columns = $this->db->fetchAll($stid);

        $pkSql = "SELECT cols.column_name
                  FROM all_constraints cons, all_cons_columns cols
                  WHERE cons.constraint_type = 'P'
                    AND cons.constraint_name = cols.constraint_name
                    AND cons.owner = cols.owner
                    AND cols.table_name = :table_name";
        $pkStid = $this->db->query($pkSql, [':table_name' => $this->tableName]);
        $pkCols = $this->db->fetchAll($pkStid);
        if (count($pkCols) > 0) {
            $this->primaryKey = $pkCols[0]['COLUMN_NAME'];
        } else {
            $this->primaryKey = null;
        }
    }

    public function getColumns() {
        return $this->columns;
    }

    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    // Fetch all rows from the table
    public function fetchAll() {
        $sql = "SELECT * FROM " . $this->tableName;
        $stid = $this->db->query($sql);
        return $this->db->fetchAll($stid);
    }

    // Fetch a single row by primary key
    public function fetchById($id) {
        if (!$this->primaryKey) {
            throw new Exception("Primary key not defined for table " . $this->tableName);
        }
        $sql = "SELECT * FROM " . $this->tableName . " WHERE " . $this->primaryKey . " = :id";
        $stid = $this->db->query($sql, [':id' => $id]);
        $rows = $this->db->fetchAll($stid);
        return count($rows) > 0 ? $rows[0] : null;
    }

    // Insert a new row
    public function insert($data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
        $sql = "INSERT INTO " . $this->tableName . " (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $params = [];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }
        $this->db->query($sql, $params);
    }

    // Update a row by primary key
    public function update($id, $data) {
        if (!$this->primaryKey) {
            throw new Exception("Primary key not defined for table " . $this->tableName);
        }
        $setClauses = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setClauses[] = $key . " = :" . $key;
            $params[':' . $key] = $value;
        }
        $params[':id'] = $id;
        $sql = "UPDATE " . $this->tableName . " SET " . implode(',', $setClauses) . " WHERE " . $this->primaryKey . " = :id";
        $this->db->query($sql, $params);
    }

    // Delete a row by primary key
    public function delete($id) {
        if (!$this->primaryKey) {
            throw new Exception("Primary key not defined for table " . $this->tableName);
        }
        $sql = "DELETE FROM " . $this->tableName . " WHERE " . $this->primaryKey . " = :id";
        $this->db->query($sql, [':id' => $id]);
    }
}
?>
