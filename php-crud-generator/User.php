<?php
// User.php
// User class with dynamic access level

class User {
    private $id;
    private $username;
    private $accessLevel;

    public function __construct($id, $username, AccessLevel $accessLevel) {
        $this->id = $id;
        $this->username = $username;
        $this->accessLevel = $accessLevel;
    }

    public function getId() {
        return $this->id;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getAccessLevel() {
        return $this->accessLevel;
    }

    public function hasPermission($permission) {
        return $this->accessLevel->hasPermission($permission);
    }
}
?>
