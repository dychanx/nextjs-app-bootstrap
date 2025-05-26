<?php
// config.php
// Database configuration for Oracle DB connection

define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SERVICE_NAME', 'ORCLPDB1'); // Change to your Oracle service name
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');

// Application mode: 'development' or 'production'
define('MODE', 'development'); // Change to 'production' for production mode

function getConnectionString() {
    return "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=" . DB_HOST . ")(PORT=" . DB_PORT . "))(CONNECT_DATA=(SERVICE_NAME=" . DB_SERVICE_NAME . ")))";
}
?>
