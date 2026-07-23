-- PFEP - Plan For Every Part
-- Catálogo para Plataforma - Componentes

CREATE DATABASE IF NOT EXISTS pfep CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE pfep;

CREATE TABLE IF NOT EXISTS componentes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero_parte    VARCHAR(50)             NOT NULL UNIQUE,
    foto_producto   VARCHAR(255)            DEFAULT NULL,
    foto_empaque    VARCHAR(255)            DEFAULT NULL,
    estandar_pack   INT                     DEFAULT NULL,
    niveles_pallet  INT                     DEFAULT NULL,
    cajas_nivel     INT                     DEFAULT NULL,
    ancho           DECIMAL(10,3)           DEFAULT NULL COMMENT 'En pulgadas',
    fondo           DECIMAL(10,3)           DEFAULT NULL COMMENT 'En pulgadas',
    alto            DECIMAL(10,3)           DEFAULT NULL COMMENT 'En pulgadas',
    peso            DECIMAL(10,3)           DEFAULT NULL COMMENT 'En libras',
    clasificacion   ENUM('Chico','Mediano','Grande') DEFAULT NULL,
    created_at      TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
