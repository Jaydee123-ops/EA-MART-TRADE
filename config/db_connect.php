<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ea_smart_trade');

// Create database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection - if it fails, we'll work in demo mode
if ($conn->connect_error) {
    $conn = null;
    define('DB_OFFLINE', true);
} else {
    define('DB_OFFLINE', false);
    $conn->set_charset("utf8mb4");
}

?>