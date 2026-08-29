<?php
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'materials');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'L3aNnM43@ja!');
define('DB_SOCKET',  getenv('DB_SOCKET')  ?: '');
define('DB_CHARSET', 'utf8mb4');

// Holgura (safety/slack) factor applied to the required storage volume.
define('FACTOR_HOLGURA', (float)(getenv('FACTOR_HOLGURA') ?: 1.2));

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
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
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
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
                daily_demand      INT                           NOT NULL DEFAULT 0,
                safety_stock_days INT                           NOT NULL DEFAULT 0,
                lead_time_days    INT                           NOT NULL DEFAULT 0,
                created_at      TIMESTAMP NOT NULL              DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL              DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Ensure demand columns exist on databases created before this feature
        $pdo->exec("
            ALTER TABLE componentes
                ADD COLUMN IF NOT EXISTS daily_demand      INT NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS safety_stock_days INT NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS lead_time_days    INT NOT NULL DEFAULT 0
        ");
    }
    return $pdo;
}
