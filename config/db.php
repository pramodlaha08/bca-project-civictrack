<?php
// config/db.php

define('DB_HOST',    'localhost');
define('DB_NAME',    'civictrack_db');
define('DB_USER',    'root');
define('DB_PASS',    'admin');          // Change if your MySQL has a password
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="padding:40px;font-family:sans-serif;color:#dc2626;">
                 <h2>Database connection failed</h2>
                 <p>Check config/db.php — make sure civictrack_db exists and credentials are correct.</p>
                 <pre>' . $e->getMessage() . '</pre></div>');
        }
    }
    return $pdo;
}
