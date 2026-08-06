-- ============================================================
--  GymTrack · Sistema de Gestión de Gimnasio
--  Script de creación de base de datos
--  Tecnología Web Aplicada · Año lectivo 2026
--  Autores: Leandro González · Santiago Cáceres · Máximo Díaz
--           Emilio Escobar · Magdalena Belmonti · Hiliana Pereira
-- ============================================================
--  Ejecutar con: mysql -u root -p < gymtrack_database.sql
-- ============================================================

-- ------------------------------------------------------------
-- Crear y seleccionar la base de datos
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS gymtrack
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gymtrack;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version      VARCHAR(100) NOT NULL,
    ejecutada_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla: roles
-- Referenciada por usuarios(rol_id)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales de roles
INSERT INTO roles (id, nombre) VALUES
    (1, 'socio'),
    (2, 'administrador_general'),
    (3, 'empleado'),
    (4, 'dueno')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ------------------------------------------------------------
-- Tabla: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre           VARCHAR(100)  NOT NULL,
    email            VARCHAR(150)  NOT NULL,
    password_hash    VARCHAR(255)  NOT NULL,           -- BCRYPT, factor coste 12
    telefono         VARCHAR(20)   NULL,
    fecha_nacimiento DATE          NULL,
    rol_id           INT UNSIGNED  NOT NULL,
    activo           TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    CONSTRAINT fk_usuarios_rol
        FOREIGN KEY (rol_id) REFERENCES roles (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Las cuentas privilegiadas no se crean con credenciales conocidas.
-- Usar backend/seed_admin.php con ADMIN_INITIAL_PASSWORD sólo en un entorno controlado.

-- ------------------------------------------------------------
-- Tabla: membresias
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membresias (
    id                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    usuario_id        INT UNSIGNED      NOT NULL,
    plan              ENUM('mensual', 'trimestral', 'anual') NOT NULL,
    fecha_inicio      DATE              NOT NULL,
    fecha_vencimiento DATE              NOT NULL,
    estado            ENUM('activa', 'vencida', 'suspendida') NOT NULL DEFAULT 'activa',
    precio_pagado     DECIMAL(10, 2)    NOT NULL,
    creado_en         TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_membresias_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla: clases
-- instructor_id referencia un usuario con rol instructor/admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clases (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre             VARCHAR(100)  NOT NULL,
    instructor_id      INT UNSIGNED  NOT NULL,
    dia_semana         ENUM('lunes', 'martes', 'miercoles', 'jueves',
                            'viernes', 'sabado', 'domingo') NOT NULL,
    hora_inicio        TIME          NOT NULL,
    hora_fin           TIME          NOT NULL,
    cupo_maximo        SMALLINT      NOT NULL,
    cupos_disponibles  SMALLINT      NOT NULL,
    activa             TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_clases_instructor
        FOREIGN KEY (instructor_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla: reservas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservas (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED  NOT NULL,
    clase_id      INT UNSIGNED  NOT NULL,
    fecha_reserva DATETIME      NOT NULL,
    estado        ENUM('confirmada', 'cancelada', 'asistio') NOT NULL DEFAULT 'confirmada',
    PRIMARY KEY (id),
    UNIQUE KEY uq_reservas_usuario_clase (usuario_id, clase_id),
    KEY idx_reservas_clase_estado (clase_id, estado),
    KEY idx_reservas_usuario_fecha (usuario_id, fecha_reserva),
    CONSTRAINT fk_reservas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_reservas_clase
        FOREIGN KEY (clase_id) REFERENCES clases (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla: pagos
-- Registra cada transacción económica asociada a una membresía
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    membresia_id  INT UNSIGNED     NOT NULL,
    monto         DECIMAL(10, 2)   NOT NULL,
    metodo        ENUM('efectivo', 'transferencia', 'tarjeta') NOT NULL,
    fecha_pago    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_pagos_membresia
        FOREIGN KEY (membresia_id) REFERENCES membresias (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla: calificaciones
-- Permite a los socios puntuar clases del 1 al 5 con comentario
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS calificaciones (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED  NOT NULL,
    clase_id   INT UNSIGNED  NOT NULL,
    puntaje    TINYINT       NOT NULL CHECK (puntaje BETWEEN 1 AND 5),
    comentario TEXT          NULL,
    creado_en  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_calificaciones_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_calificaciones_clase
        FOREIGN KEY (clase_id) REFERENCES clases (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla: notificaciones
-- Almacena notificaciones internas del sistema para cada usuario
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificaciones (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED  NOT NULL,
    mensaje    TEXT          NOT NULL,
    leida      TINYINT(1)    NOT NULL DEFAULT 0,
    creado_en  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_notificaciones_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla: asistencias
-- Registra la asistencia efectiva a cada clase
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asistencias (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    reserva_id    INT UNSIGNED  NOT NULL,
    fecha_asist   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_asistencias_reserva
        FOREIGN KEY (reserva_id) REFERENCES reservas (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Fin del script · GymTrack · gymtrack_database.sql
-- ============================================================

INSERT IGNORE INTO schema_migrations (version) VALUES ('000_bootstrap');
