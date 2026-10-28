CREATE DATABASE IF NOT EXISTS mi_banco_db;
USE mi_banco_db;

-- Borramos las tablas si existen para poder recargar todo limpio
DROP TABLE IF EXISTS liquidaciones;
DROP TABLE IF EXISTS tarjetas;
DROP TABLE IF EXISTS consultas;
DROP TABLE IF EXISTS usuarios;

-- 1. Tabla de Usuarios (¡Ahora con columna ROL!)
CREATE TABLE IF NOT EXISTS usuarios (
    documento VARCHAR(20) PRIMARY KEY,
    tipo_doc ENUM('DNI', 'PASAPORTE') NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    usuario VARCHAR(50) NULL UNIQUE,
    password VARCHAR(50) NULL, 
    rol ENUM('General', 'Administrador') DEFAULT 'General', -- NUEVA COLUMNA
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabla de Tarjetas (Sin cambios)
CREATE TABLE IF NOT EXISTS tarjetas (
    num_cuenta INT AUTO_INCREMENT PRIMARY KEY,
    numero_tarjeta VARCHAR(16) NOT NULL UNIQUE,
    banco_emisor ENUM(
        'Banco Nación', 
        'Banco Provincia', 
        'Banco Galicia', 
        'Banco Santander', 
        'Banco BBVA', 
        'Banco Macro'
    ) NOT NULL,
    estado ENUM('Activa', 'Bloqueada') DEFAULT 'Activa',
    saldo DECIMAL(10,2) DEFAULT 0.00,
    dni_titular VARCHAR(20) NOT NULL UNIQUE,
    FOREIGN KEY (dni_titular) REFERENCES usuarios(documento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla de Liquidaciones (Sin cambios)
CREATE TABLE IF NOT EXISTS liquidaciones (
    id_liquidacion INT AUTO_INCREMENT PRIMARY KEY,
    num_cuenta INT NOT NULL,
    periodo VARCHAR(7) NOT NULL, 
    fecha_vencimiento DATE NOT NULL,
    total_a_pagar DECIMAL(10,2) NOT NULL,
    pago_minimo DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (num_cuenta) REFERENCES tarjetas(num_cuenta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. NUEVA TABLA: Consultas de Contacto
CREATE TABLE IF NOT EXISTS consultas (
    id_consulta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    categoria ENUM('Alta', 'Baja', 'Mensaje') NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- REGISTROS DE PRUEBA (SEED DATA)
-- =========================================================================

-- Inserción de Usuarios (Agregamos al Jefe/Administrador)
INSERT INTO usuarios (documento, tipo_doc, nombre, apellido, fecha_nacimiento, email, usuario, password, rol) VALUES
('11111111', 'DNI', 'Admin', 'Sistema', '1980-01-01', 'admin@mibanco.com', 'admin', 'admin123', 'Administrador'), -- NUEVO ADMIN
('20123456', 'DNI', 'Carlos', 'Gómez', '1985-04-12', 'carlos.gomez@example.com', 'carlos85', 'clave123', 'General'), 
('30987654', 'DNI', 'Ana', 'Martínez', '1992-09-23', 'ana.mtz@example.com', 'anamar', 'clave123', 'General');   

-- Inserción de Tarjetas de Crédito de ejemplo
INSERT INTO tarjetas (num_cuenta, numero_tarjeta, banco_emisor, estado, saldo, dni_titular) VALUES
(1001, '4512765432109876', 'Banco Galicia', 'Activa', 15250.75, '20123456'),
(1002, '4512112233445566', 'Banco Nación', 'Activa', 0.00, '30987654');

-- Inserción de Liquidaciones de ejemplo
INSERT INTO liquidaciones (num_cuenta, periodo, fecha_vencimiento, total_a_pagar, pago_minimo) VALUES
(1001, '2026-04', '2026-05-10', 25000.00, 5000.00),
(1001, '2026-05', '2026-06-10', 32000.50, 7000.00), 
(1002, '2026-05', '2026-06-15', 12300.00, 2500.00);

-- Inserción de una Consulta de prueba
INSERT INTO consultas (nombre, email, mensaje) VALUES
('Juan Perez', 'juanperez@mail.com', 'Hola, quisiera saber cómo solicito una tarjeta adicional. Gracias.');