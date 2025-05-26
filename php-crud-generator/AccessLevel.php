<?php
// AccessLevel.php
// AccessLevel class for dynamic permissions

class AccessLevel {
    private $levelName;
    private $permissions; // array of permission strings

    public function __construct($levelName, array $permissions = []) {
        $this->levelName = $levelName;
        $this->permissions = $permissions;
    }

    public function getLevelName() {
        return $this->levelName;
    }

    public function getPermissions() {
        return $this->permissions;
    }

    public function hasPermission($permission) {
        return in_array($permission, $this->permissions);
    }
}
?>
