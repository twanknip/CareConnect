<?php
/**
 * MediPortal - Database Configuration
 * All sensitive values stored here
 * Keep this file secure and never commit to version control
 */

// Database Configuration
define('DB_HOST', 'localhost');      // MySQL server hostname
define('DB_USER', 'root');           // MySQL username
define('DB_PASS', '');               // MySQL password (empty for default XAMPP/WAMP)
define('DB_NAME', 'mediportal');     // Database name

// Database connection timeout
define('DB_TIMEOUT', 5);

// Application Settings
define('APP_NAME', 'MediPortal');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');

// Session Configuration
define('SESSION_TIMEOUT', 3600);     // 1 hour in seconds
define('SESSION_NAME', 'mediportal_session');

// Security Settings
define('ENABLE_ERRORS', true);       // Set to false in production
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', __DIR__ . '/logs/error.log');

/**
 * Database Connection Function
 * Creates and returns a MySQLi connection
 */
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        
        // Set charset to UTF-8
        $conn->set_charset('utf8mb4');
        
        return $conn;
    } catch (Exception $e) {
        logError($e->getMessage());
        die('Database error. Please try again later.');
    }
}

/**
 * Close Database Connection
 */
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

/**
 * Log Errors to File
 */
function logError($message) {
    if (LOG_ERRORS) {
        $logDir = dirname(ERROR_LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents(ERROR_LOG_FILE, $logMessage, FILE_APPEND);
    }
    
    if (ENABLE_ERRORS) {
        echo $message;
    }
}

?>