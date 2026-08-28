<?php
// Optional single-URL config (Render / Railway / Heroku style): mysql://user:pass@host:port/dbname
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('JAWSDB_URL') ?: '';
$urlParts = ['host' => '', 'port' => '3306', 'user' => '', 'pass' => '', 'name' => ''];
if ($dbUrl !== '') {
    $p = parse_url($dbUrl);
    if (is_array($p) && !empty($p['host'])) {
        $urlParts = [
            'host' => $p['host'],
            'port' => (string)($p['port'] ?? '3306'),
            'user' => isset($p['user']) ? rawurldecode($p['user']) : '',
            'pass' => isset($p['pass']) ? rawurldecode($p['pass']) : '',
            'name' => isset($p['path']) ? ltrim($p['path'], '/') : '',
        ];
    }
}

define('DB_HOST',    getenv('DB_HOST')    ?: $urlParts['host']);
define('DB_PORT',    getenv('DB_PORT')    ?: $urlParts['port']);
define('DB_NAME',    getenv('DB_NAME')    ?: $urlParts['name']);
define('DB_USER',    getenv('DB_USER')    ?: $urlParts['user']);
define('DB_PASS',    getenv('DB_PASS')    ?: $urlParts['pass']);
define('DB_SOCKET',  getenv('DB_SOCKET')  ?: '');
define('DB_SSL',     getenv('DB_SSL')     ?: '');   // "1" to enable TLS
define('DB_SSL_CA',  getenv('DB_SSL_CA')  ?: '');   // optional path to CA bundle
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!DB_SOCKET && (DB_HOST === '' || DB_NAME === '' || DB_USER === '')) {
            http_response_code(500);
            exit(
                'Error de configuración: faltan variables de entorno de la base de datos. '
                . 'Defina DB_HOST, DB_NAME, DB_USER y DB_PASS (o DATABASE_URL) en el panel de Render.'
            );
        }

        if (DB_SOCKET) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s',
                DB_SOCKET, DB_NAME, DB_CHARSET);
        } else {
            $host = DB_HOST === 'localhost' ? '127.0.0.1' : DB_HOST;
            $dsn  = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host, DB_PORT, DB_NAME, DB_CHARSET);
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ];

        if (DB_SSL === '1' || DB_SSL_CA !== '') {
            if (DB_SSL_CA !== '') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
            }
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('DB connect failed: ' . $e->getMessage());
            exit('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage()));
        }

        // Auto-create table on first connection
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS componentes (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                numero_parte    VARCHAR(50)                     NOT NULL UNIQUE,
                foto_producto   VARCHAR(255)                    DEFAULT NULL,
                foto_empaque    VARCHAR(255)                    DEFAULT NULL,
                estandar_pack   INT                             DEFAULT NULL,
                niveles_pallet  INT                             DEFAULT NULL,
                cajas_nivel     INT                             DEFAULT NULL,
                ancho           DECIMAL(10,3)                   DEFAULT NULL,
                fondo           DECIMAL(10,3)                   DEFAULT NULL,
                alto            DECIMAL(10,3)                   DEFAULT NULL,
                peso            DECIMAL(10,3)                   DEFAULT NULL,
                clasificacion   ENUM('Chico','Mediano','Grande') DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL              DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL              DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    return $pdo;
}
