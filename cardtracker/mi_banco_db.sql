-- =========================================================================
-- CardTracker - Base de datos
-- Ejecutar completo en phpMyAdmin (pestaña SQL) o con:  mysql -u root < mi_banco_db.sql
-- =========================================================================
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS mi_banco_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mi_banco_db;

DROP TABLE IF EXISTS liquidaciones;
DROP TABLE IF EXISTS tarjetas;
DROP TABLE IF EXISTS consultas;
DROP TABLE IF EXISTS usuarios;

-- 1. Usuarios ---------------------------------------------------------------
-- La contraseña NUNCA se guarda en texto plano: se guarda el hash de password_hash().
-- VARCHAR(255) porque el largo del hash puede crecer si PHP cambia de algoritmo.
CREATE TABLE usuarios (
    documento        VARCHAR(20)  PRIMARY KEY,
    tipo_doc         ENUM('DNI', 'PASAPORTE') NOT NULL,
    nombre           VARCHAR(50)  NOT NULL,
    apellido         VARCHAR(50)  NOT NULL,
    fecha_nacimiento DATE         NOT NULL,
    email            VARCHAR(100) NOT NULL UNIQUE,
    usuario          VARCHAR(30)  NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    rol              ENUM('General', 'Administrador') NOT NULL DEFAULT 'General',
    creado_el        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tarjetas ---------------------------------------------------------------
-- Por seguridad solo se guardan los últimos 4 dígitos, nunca el número completo.
-- Un usuario puede tener varias tarjetas (se quitó el UNIQUE de dni_titular).
CREATE TABLE tarjetas (
    id_tarjeta   INT AUTO_INCREMENT PRIMARY KEY,
    dni_titular  VARCHAR(20) NOT NULL,
    banco_emisor VARCHAR(40) NOT NULL,           -- validado en PHP contra la lista BANCOS
    red          VARCHAR(20) NOT NULL,           -- Visa, Mastercard, American Express, Cabal, Naranja
    ultimos4     CHAR(4)     NOT NULL,
    alias        VARCHAR(40) NULL,               -- nombre personalizado, ej.: "Gastos del súper"
    estado       ENUM('Activa', 'Inactiva', 'Bloqueada') NOT NULL DEFAULT 'Activa',
    creado_el    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tarjeta (dni_titular, banco_emisor, red, ultimos4),
    FOREIGN KEY (dni_titular) REFERENCES usuarios(documento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Liquidaciones (resúmenes de cuenta) ------------------------------------
CREATE TABLE liquidaciones (
    id_liquidacion    INT AUTO_INCREMENT PRIMARY KEY,
    id_tarjeta        INT           NOT NULL,
    periodo           CHAR(7)       NOT NULL,     -- formato AAAA-MM
    fecha_cierre      DATE          NOT NULL,
    fecha_vencimiento DATE          NOT NULL,
    total_a_pagar     DECIMAL(12,2) NOT NULL,
    pago_minimo       DECIMAL(12,2) NOT NULL,
    estado_pago       ENUM('Pendiente', 'Pagado') NOT NULL DEFAULT 'Pendiente',
    fecha_pago        DATE          NULL,
    UNIQUE KEY uq_periodo (id_tarjeta, periodo),  -- un resumen por tarjeta y período
    CHECK (pago_minimo <= total_a_pagar),
    FOREIGN KEY (id_tarjeta) REFERENCES tarjetas(id_tarjeta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Consultas de contacto --------------------------------------------------
CREATE TABLE consultas (
    id_consulta INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL,
    categoria   ENUM('Alta', 'Baja', 'Mensaje') NOT NULL DEFAULT 'Mensaje',
    mensaje     TEXT NOT NULL,
    resuelto    ENUM('Si', 'No') NOT NULL DEFAULT 'No',
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- DATOS DE PRUEBA
-- Contraseñas: admin -> admin123 | carlos85 -> clave123 | anamar -> clave123
-- (lo que se guarda es el hash generado con password_hash)
-- Las fechas se calculan desde HOY, así siempre hay avisos para mostrar.
-- =========================================================================
INSERT INTO usuarios (documento, tipo_doc, nombre, apellido, fecha_nacimiento, email, usuario, password_hash, rol) VALUES
('11111111', 'DNI', 'Admin',  'Sistema',  '1980-01-01', 'admin@cardtracker.com',    'admin',    '$2y$10$g/lzim2t38zFMY/D0XmspeRagriP6gl5kGKWXlMQrM4otH/IDdbFK', 'Administrador'),
('20123456', 'DNI', 'Carlos', 'Gómez',    '1985-04-12', 'carlos.gomez@example.com', 'carlos85', '$2y$10$I4yIYNHjcbEm6Taeni/b6uBb8iteoWT4AKIt2htZZ6Ayl8.Uqc39O', 'General'),
('30987654', 'DNI', 'Ana',    'Martínez', '1992-09-23', 'ana.mtz@example.com',      'anamar',   '$2y$10$I4yIYNHjcbEm6Taeni/b6uBb8iteoWT4AKIt2htZZ6Ayl8.Uqc39O', 'General');

INSERT INTO tarjetas (id_tarjeta, dni_titular, banco_emisor, red, ultimos4, alias, estado) VALUES
(1, '20123456', 'Banco Galicia',   'Visa',             '9876', 'Gastos del súper', 'Activa'),
(2, '20123456', 'Banco Santander', 'Mastercard',       '4410', 'Viajes',           'Activa'),
(3, '20123456', 'Banco Nación',    'Cabal',            '5566', NULL,               'Inactiva'),
(4, '30987654', 'Banco Nación',    'Visa',             '2231', 'Principal',        'Activa'),
(5, '30987654', 'Banco Macro',     'American Express', '1007', 'Trabajo',          'Activa');

INSERT INTO liquidaciones (id_tarjeta, periodo, fecha_cierre, fecha_vencimiento, total_a_pagar, pago_minimo, estado_pago, fecha_pago) VALUES
(1, DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m'), CURDATE() - INTERVAL 5 MONTH - INTERVAL 8 DAY, CURDATE() - INTERVAL 5 MONTH + INTERVAL 4 DAY, 41250.00, 8250.00, 'Pagado', CURDATE() - INTERVAL 5 MONTH + INTERVAL 2 DAY),
(1, DATE_FORMAT(CURDATE() - INTERVAL 4 MONTH, '%Y-%m'), CURDATE() - INTERVAL 4 MONTH - INTERVAL 8 DAY, CURDATE() - INTERVAL 4 MONTH + INTERVAL 4 DAY, 38900.50, 7780.00, 'Pagado', CURDATE() - INTERVAL 4 MONTH + INTERVAL 2 DAY),
(1, DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m'), CURDATE() - INTERVAL 3 MONTH - INTERVAL 8 DAY, CURDATE() - INTERVAL 3 MONTH + INTERVAL 4 DAY, 52310.00, 10462.00, 'Pagado', CURDATE() - INTERVAL 3 MONTH + INTERVAL 2 DAY),
(1, DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m'), CURDATE() - INTERVAL 2 MONTH - INTERVAL 8 DAY, CURDATE() - INTERVAL 2 MONTH + INTERVAL 4 DAY, 47120.75, 9424.00, 'Pagado', CURDATE() - INTERVAL 2 MONTH + INTERVAL 2 DAY),
(1, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m'), CURDATE() - INTERVAL 1 MONTH - INTERVAL 8 DAY, CURDATE() - INTERVAL 1 MONTH + INTERVAL 4 DAY, 44680.00, 8936.00, 'Pagado', CURDATE() - INTERVAL 1 MONTH + INTERVAL 2 DAY),
(1, DATE_FORMAT(CURDATE() - INTERVAL 0 MONTH, '%Y-%m'), CURDATE() - INTERVAL 0 MONTH - INTERVAL 8 DAY, CURDATE() - INTERVAL 0 MONTH + INTERVAL 4 DAY, 48210.40, 9650.00, 'Pendiente', NULL),
(2, DATE_FORMAT(CURDATE() - INTERVAL 4 MONTH, '%Y-%m'), CURDATE() - INTERVAL 4 MONTH - INTERVAL 16 DAY, CURDATE() - INTERVAL 4 MONTH - INTERVAL 3 DAY, 120500.00, 18075.00, 'Pagado', CURDATE() - INTERVAL 4 MONTH - INTERVAL 5 DAY),
(2, DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m'), CURDATE() - INTERVAL 3 MONTH - INTERVAL 16 DAY, CURDATE() - INTERVAL 3 MONTH - INTERVAL 3 DAY, 98000.00, 14700.00, 'Pagado', CURDATE() - INTERVAL 3 MONTH - INTERVAL 5 DAY),
(2, DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m'), CURDATE() - INTERVAL 2 MONTH - INTERVAL 16 DAY, CURDATE() - INTERVAL 2 MONTH - INTERVAL 3 DAY, 134250.30, 20138.00, 'Pagado', CURDATE() - INTERVAL 2 MONTH - INTERVAL 5 DAY),
(2, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m'), CURDATE() - INTERVAL 1 MONTH - INTERVAL 16 DAY, CURDATE() - INTERVAL 1 MONTH - INTERVAL 3 DAY, 87430.90, 13115.00, 'Pagado', CURDATE() - INTERVAL 1 MONTH - INTERVAL 5 DAY),
(2, DATE_FORMAT(CURDATE() - INTERVAL 0 MONTH, '%Y-%m'), CURDATE() - INTERVAL 0 MONTH - INTERVAL 16 DAY, CURDATE() - INTERVAL 0 MONTH - INTERVAL 3 DAY, 64300.00, 9650.00, 'Pendiente', NULL),
(3, DATE_FORMAT(CURDATE() - INTERVAL 6 MONTH, '%Y-%m'), CURDATE() - INTERVAL 6 MONTH - INTERVAL 10 DAY, CURDATE() - INTERVAL 6 MONTH + INTERVAL 2 DAY, 15200.00, 3800.00, 'Pagado', CURDATE() - INTERVAL 6 MONTH + INTERVAL 0 DAY),
(3, DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m'), CURDATE() - INTERVAL 5 MONTH - INTERVAL 10 DAY, CURDATE() - INTERVAL 5 MONTH + INTERVAL 2 DAY, 9800.00, 2450.00, 'Pagado', CURDATE() - INTERVAL 5 MONTH + INTERVAL 0 DAY),
(4, DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m'), CURDATE() - INTERVAL 3 MONTH - INTERVAL 9 DAY, CURDATE() - INTERVAL 3 MONTH + INTERVAL 6 DAY, 23100.00, 4620.00, 'Pagado', CURDATE() - INTERVAL 3 MONTH + INTERVAL 4 DAY),
(4, DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m'), CURDATE() - INTERVAL 2 MONTH - INTERVAL 9 DAY, CURDATE() - INTERVAL 2 MONTH + INTERVAL 6 DAY, 19850.50, 3970.00, 'Pagado', CURDATE() - INTERVAL 2 MONTH + INTERVAL 4 DAY),
(4, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m'), CURDATE() - INTERVAL 1 MONTH - INTERVAL 9 DAY, CURDATE() - INTERVAL 1 MONTH + INTERVAL 6 DAY, 27400.00, 5480.00, 'Pagado', CURDATE() - INTERVAL 1 MONTH + INTERVAL 4 DAY),
(4, DATE_FORMAT(CURDATE() - INTERVAL 0 MONTH, '%Y-%m'), CURDATE() - INTERVAL 0 MONTH - INTERVAL 9 DAY, CURDATE() - INTERVAL 0 MONTH + INTERVAL 6 DAY, 25120.00, 5030.00, 'Pendiente', NULL),
(5, DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m'), CURDATE() - INTERVAL 2 MONTH - INTERVAL 2 DAY, CURDATE() - INTERVAL 2 MONTH + INTERVAL 13 DAY, 61000.00, 12200.00, 'Pagado', CURDATE() - INTERVAL 2 MONTH + INTERVAL 11 DAY),
(5, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m'), CURDATE() - INTERVAL 1 MONTH - INTERVAL 2 DAY, CURDATE() - INTERVAL 1 MONTH + INTERVAL 13 DAY, 58750.00, 11750.00, 'Pagado', CURDATE() - INTERVAL 1 MONTH + INTERVAL 11 DAY),
(5, DATE_FORMAT(CURDATE() - INTERVAL 0 MONTH, '%Y-%m'), CURDATE() - INTERVAL 0 MONTH - INTERVAL 2 DAY, CURDATE() - INTERVAL 0 MONTH + INTERVAL 13 DAY, 73990.00, 14800.00, 'Pendiente', NULL);

INSERT INTO consultas (nombre, email, categoria, mensaje) VALUES
('Juan Pérez', 'juanperez@mail.com', 'Mensaje', 'Hola, quisiera saber si puedo cargar tarjetas de un banco que no figura en la lista. Gracias.'),
('Ana Martínez', 'ana.mtz@example.com', 'Baja', 'Quiero dar de baja la tarjeta Visa terminada en 2231 porque la voy a cerrar.');
