<?php
// error_handler.php
// Custom error and exception handler for production and development modes

require_once 'config.php';
require_once 'Database.php';

function log_error_to_db($errno, $errstr, $errfile, $errline) {
    try {
        $db = new Database();
        $userId = isset($_SESSION['user']) ? unserialize($_SESSION['user'])->getId() : null;
        $action = "Error";
        $menu = $_SERVER['REQUEST_URI'] ?? '';
        $status = "Error $errno";
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $message = "$errstr in $errfile on line $errline";

        $sql = "INSERT INTO LOGS (USER_ID, ACTION, MENU, STATUS, BROWSER, IP_ADDRESS, TIMESTAMP, DURATION_SECONDS)
                VALUES (:user_id, :action, :menu, :status, :browser, :ip_address, SYSDATE, NULL)";
        $stid = oci_parse($db->getConnection(), $sql);
        oci_bind_by_name($stid, ':user_id', $userId);
        oci_bind_by_name($stid, ':action', $action);
        oci_bind_by_name($stid, ':menu', $menu);
        oci_bind_by_name($stid, ':status', $message);
        oci_bind_by_name($stid, ':browser', $browser);
        oci_bind_by_name($stid, ':ip_address', $ip);
        oci_execute($stid);
        $db->close();
    } catch (Exception $e) {
        // Fail silently to avoid recursive errors
    }
}

function custom_error_handler($errno, $errstr, $errfile, $errline) {
    if (MODE === 'production') {
        log_error_to_db($errno, $errstr, $errfile, $errline);
        // Don't display errors to users
        return true;
    } else {
        // In development, use PHP's default error handler
        return false;
    }
}

function custom_exception_handler($exception) {
    if (MODE === 'production') {
        log_error_to_db(E_ERROR, $exception->getMessage(), $exception->getFile(), $exception->getLine());
        // Display generic error message
        http_response_code(500);
        echo "An internal error occurred. Please try again later.";
    } else {
        // In development, display full exception
        echo "<pre>";
        echo $exception;
        echo "</pre>";
    }
}

// Set error reporting and handlers based on mode
if (MODE === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    set_error_handler('custom_error_handler');
    set_exception_handler('custom_exception_handler');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    restore_error_handler();
    restore_exception_handler();
}
?>
