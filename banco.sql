-- ============================================
-- Base de datos: banco
-- ============================================
DROP DATABASE IF EXISTS banco;

CREATE DATABASE banco
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE banco;

-- 1. Cliente
CREATE TABLE cliente (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(100) NOT NULL,
    domicilio VARCHAR(100) NOT NULL,
    estatus VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Sucursal
CREATE TABLE sucursal (
    id_sucursal INT AUTO_INCREMENT PRIMARY KEY,
    nombre_sucursal VARCHAR(100) NOT NULL,
    direccion VARCHAR(100),
    codigo_postal INT,
    horario VARCHAR(100),
    estatus VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Empleado
CREATE TABLE empleado (
    id_empleado INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    puesto VARCHAR(100),
    salario DECIMAL(12,2),
    id_sucursal INT,
    estatus VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sucursal)
        REFERENCES sucursal(id_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Cuenta
CREATE TABLE cuenta (
    id_cuenta INT AUTO_INCREMENT PRIMARY KEY,
    estado VARCHAR(100),
    fecha_creacion_cuenta DATE,
    num_cuenta INT,
    tipo_cuenta VARCHAR(100),
    saldo DECIMAL(12,2) DEFAULT 0,
    id_cliente INT NOT NULL,
    id_empleado INT,
    estatus VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente)
        REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_empleado)
        REFERENCES empleado(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índice para búsqueda de clientes por nombre
CREATE INDEX idx_cliente_nombre ON cliente(nombre);

-- 5. Préstamo
CREATE TABLE prestamo (
    id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    fecha_inicio DATE,
    fecha_vencimiento DATE,
    cantidad DECIMAL(12,2),
    plazo_meses INT,
    tasa_anual DECIMAL(5,2) NOT NULL DEFAULT 0,
    comision_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    comision DECIMAL(12,2) NOT NULL DEFAULT 0,
    mensualidad DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_pagar DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado VARCHAR(100),
    estatus VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente)
        REFERENCES cliente(id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Garantía (estructura solicitada)
CREATE TABLE garantia (
    id_garantia INT(11) AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT(11) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    documento VARCHAR(100) NOT NULL,
    valor_estimado DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_prestamo)
        REFERENCES prestamo(id_prestamo)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Datos de ejemplo
-- ============================================

INSERT INTO sucursal
(nombre_sucursal, direccion, codigo_postal, horario)
VALUES
('Sucursal Centro', 'Centro, Monterrey', 64000, '09:00 - 17:00'),
('Sucursal Apodaca', 'Apodaca, N.L.', 66600, '09:00 - 17:00'),
('Sucursal Guadalupe', 'Guadalupe, N.L.', 67100, '09:00 - 17:00'),
('Sucursal San Nicolas', 'San Nicolás de los Garza, N.L.', 66400, '09:00 - 17:00'),
('Sucursal Escobedo', 'Escobedo, N.L.', 66050, '09:00 - 17:00');

INSERT INTO cliente
(nombre, telefono, correo, domicilio)
VALUES
('Ana Pérez', '8112345678', 'ana.perez@email.com', 'Calle Hidalgo 123'),
('Carlos Gómez', '8187654321', 'carlos.gomez@email.com', 'Av. Constitución 456');

INSERT INTO empleado
(nombre, telefono, puesto, salario, id_sucursal)
VALUES
('María Rodríguez', '8111114321', 'Gerente', 25000.50, 1),
('Luis Martínez', '8133331234', 'Ejecutivo de Cuenta', 15000.00, 2);

INSERT INTO cuenta
(estado, fecha_creacion_cuenta, num_cuenta, tipo_cuenta,
 saldo, id_cliente, id_empleado)
VALUES
('Activa', '2026-01-15', 100123, 'Débito', 5400.50, 1, 1),
('Activa', '2026-02-20', 100456, 'Crédito', 12500.00, 2, 2);

INSERT INTO prestamo
(id_cliente, fecha_inicio, fecha_vencimiento, cantidad,
 plazo_meses, tasa_anual, comision_pct, comision,
 mensualidad, total_pagar, estado)
VALUES
(1, '2026-03-01', '2027-03-01', 30000.00, 12,
 18.00, 3.00, 900.00, 2750.40, 33004.80, 'VIGENTE'),
(2, '2026-01-10', '2026-07-10', 8000.00, 6,
 12.00, 2.00, 160.00, 1380.39, 8282.34, 'VIGENTE');

INSERT INTO garantia
(id_prestamo, tipo, descripcion, documento, valor_estimado)
VALUES
(1, 'VEHICULO',
 'Nissan Versa 2020, color gris, placas ABC-123-A',
 'Factura FAC-A-48213', 120000.00),
(2, 'BIEN',
 'Laptop Lenovo IdeaPad 15 pulgadas, número de serie PF3K91XZ',
 'Nota de compra 7781', 12000.00);

-- ============================================
-- Consultas de prueba
-- ============================================

-- Clientes con sus cuentas
SELECT
    cliente.nombre AS Nombre_Cliente,
    cuenta.num_cuenta AS Numero_Cuenta,
    cuenta.tipo_cuenta AS Tipo_Cuenta,
    cuenta.saldo AS Saldo
FROM cliente
INNER JOIN cuenta
    ON cliente.id_cliente = cuenta.id_cliente;

-- Empleados con su sucursal
SELECT
    empleado.nombre AS Empleado,
    empleado.puesto AS Puesto,
    empleado.salario AS Salario,
    sucursal.nombre_sucursal AS Sucursal,
    sucursal.direccion AS Direccion
FROM empleado
INNER JOIN sucursal
    ON empleado.id_sucursal = sucursal.id_sucursal
ORDER BY empleado.salario DESC;

-- Cuentas con cliente y empleado responsable
SELECT
    cliente.nombre AS Nombre_Cliente,
    cuenta.num_cuenta AS Numero_Cuenta,
    cuenta.tipo_cuenta AS Tipo_Cuenta,
    empleado.nombre AS Empleado_Responsable
FROM cuenta
INNER JOIN cliente
    ON cuenta.id_cliente = cliente.id_cliente
INNER JOIN empleado
    ON cuenta.id_empleado = empleado.id_empleado;