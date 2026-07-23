<?php
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'pfep');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_SOCKET',  getenv('DB_SOCKET')  ?: '');
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (DB_SOCKET) {
            // Cloud SQL on Cloud Run via Unix socket
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s',
                DB_SOCKET, DB_NAME, DB_CHARSET);
        } else {
            // External MySQL via TCP — use explicit host+port to avoid Unix socket fallback
            $host = DB_HOST === 'localhost' ? '127.0.0.1' : DB_HOST;
            $dsn  = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host, DB_PORT, DB_NAME, DB_CHARSET);
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}
