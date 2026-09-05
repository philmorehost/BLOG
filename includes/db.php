<?php
if (!file_exists(__DIR__ . '/config.php')) {
    // If config doesn't exist, we might be in middle of installation or something is wrong
    return null;
}

require_once __DIR__ . '/config.php';

function get_db_connection() {
    try {
        if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
            $conn = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
        } else {
            try {
                $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            } catch (PDOException $e) {
                // Fallback to SQLite if MySQL fails in this environment
                $conn = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
            }
        }
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $conn;
    } catch (PDOException $e) {
        error_log("Connection failed: " . $e->getMessage());
        return null;
    }
}