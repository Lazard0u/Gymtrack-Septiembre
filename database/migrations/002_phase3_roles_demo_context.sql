-- GymTrack: roles, permisos, contexto mínimo de gimnasio y trazabilidad demo.
-- Este archivo es idempotente. No inserta el dataset: sólo prepara el esquema.


CREATE TABLE IF NOT EXISTS schema_migrations (
    version      VARCHAR(100) NOT NULL,
    ejecutada_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE roles MODIFY COLUMN nombre VARCHAR(50) NOT NULL;
UPDATE roles SET nombre = 'socio' WHERE id = 1;
UPDATE roles SET nombre = 'administrador_general' WHERE id = 2;
UPDATE roles SET nombre = 'empleado' WHERE id = 3;
INSERT INTO roles (id, nombre) VALUES (4, 'dueno')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

CREATE TABLE IF NOT EXISTS demo_datasets (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100) NOT NULL,
    version         VARCHAR(20) NOT NULL DEFAULT '1.0.0',
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    creado_en       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_demo_datasets_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_phase3_migrate;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase3_migrate()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'demo_datasets' AND column_name = 'version'
    ) THEN
        ALTER TABLE demo_datasets ADD COLUMN version VARCHAR(20) NOT NULL DEFAULT '1.0.0' AFTER nombre;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'is_demo'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'demo_dataset_id'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND index_name = 'idx_usuarios_demo_dataset'
    ) THEN
        ALTER TABLE usuarios ADD INDEX idx_usuarios_demo_dataset (is_demo, demo_dataset_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND table_name = 'usuarios' AND constraint_name = 'fk_usuarios_demo_dataset'
    ) THEN
        ALTER TABLE usuarios
            ADD CONSTRAINT fk_usuarios_demo_dataset
            FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id)
            ON UPDATE CASCADE ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'clases' AND column_name = 'gimnasio_id'
    ) THEN
        ALTER TABLE clases ADD COLUMN gimnasio_id INT UNSIGNED NULL AFTER instructor_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'clases' AND column_name = 'demo_key'
    ) THEN
        ALTER TABLE clases ADD COLUMN demo_key VARCHAR(100) NULL AFTER activa;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'clases' AND column_name = 'is_demo'
    ) THEN
        ALTER TABLE clases ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER demo_key;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'clases' AND column_name = 'demo_dataset_id'
    ) THEN
        ALTER TABLE clases ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'clases' AND index_name = 'uq_clases_demo_key'
    ) THEN
        ALTER TABLE clases ADD UNIQUE KEY uq_clases_demo_key (demo_key);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'clases' AND index_name = 'idx_clases_gimnasio'
    ) THEN
        ALTER TABLE clases ADD INDEX idx_clases_gimnasio (gimnasio_id, activa);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'membresias' AND column_name = 'gimnasio_id'
    ) THEN
        ALTER TABLE membresias ADD COLUMN gimnasio_id INT UNSIGNED NULL AFTER usuario_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'membresias' AND column_name = 'demo_key'
    ) THEN
        ALTER TABLE membresias ADD COLUMN demo_key VARCHAR(100) NULL AFTER creado_en;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'membresias' AND column_name = 'is_demo'
    ) THEN
        ALTER TABLE membresias ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER demo_key;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'membresias' AND column_name = 'demo_dataset_id'
    ) THEN
        ALTER TABLE membresias ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'membresias' AND index_name = 'uq_membresias_demo_key'
    ) THEN
        ALTER TABLE membresias ADD UNIQUE KEY uq_membresias_demo_key (demo_key);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'membresias' AND index_name = 'idx_membresias_gimnasio'
    ) THEN
        ALTER TABLE membresias ADD INDEX idx_membresias_gimnasio (gimnasio_id, estado);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'reservas' AND column_name = 'demo_key'
    ) THEN
        ALTER TABLE reservas ADD COLUMN demo_key VARCHAR(100) NULL AFTER estado;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'reservas' AND column_name = 'is_demo'
    ) THEN
        ALTER TABLE reservas ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER demo_key;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'reservas' AND column_name = 'demo_dataset_id'
    ) THEN
        ALTER TABLE reservas ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'reservas' AND index_name = 'uq_reservas_demo_key'
    ) THEN
        ALTER TABLE reservas ADD UNIQUE KEY uq_reservas_demo_key (demo_key);
    END IF;
END$$
DELIMITER ;

CALL gymtrack_phase3_migrate();
DROP PROCEDURE gymtrack_phase3_migrate;

CREATE TABLE IF NOT EXISTS gimnasios (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre               VARCHAR(120) NOT NULL,
    slug                 VARCHAR(140) NOT NULL,
    descripcion          TEXT NOT NULL,
    direccion            VARCHAR(180) NOT NULL,
    ciudad               VARCHAR(100) NOT NULL,
    departamento         VARCHAR(100) NOT NULL,
    latitud              DECIMAL(10, 7) NOT NULL,
    longitud             DECIMAL(10, 7) NOT NULL,
    zona_horaria         VARCHAR(80) NOT NULL DEFAULT 'America/Montevideo',
    telefono             VARCHAR(40) NULL,
    email                VARCHAR(150) NULL,
    horarios_json        JSON NOT NULL,
    categorias_json      JSON NOT NULL,
    servicios_json       JSON NOT NULL,
    estado               ENUM('borrador', 'publicado', 'temporalmente_cerrado') NOT NULL DEFAULT 'borrador',
    imagen_path          VARCHAR(255) NULL,
    demo_scenario_json   JSON NULL,
    is_demo              TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id      INT UNSIGNED NULL,
    creado_en            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gimnasios_slug (slug),
    KEY idx_gimnasios_publicacion (estado, ciudad),
    KEY idx_gimnasios_demo_dataset (is_demo, demo_dataset_id),
    CONSTRAINT fk_gimnasios_demo_dataset
        FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permisos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo      VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permisos_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rol_permisos (
    rol_id      INT UNSIGNED NOT NULL,
    permiso_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rol_permisos_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rol_permisos_permiso
        FOREIGN KEY (permiso_id) REFERENCES permisos(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuario_gimnasio_roles (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id       INT UNSIGNED NOT NULL,
    gimnasio_id      INT UNSIGNED NOT NULL,
    rol_id           INT UNSIGNED NOT NULL,
    activo           TINYINT(1) NOT NULL DEFAULT 1,
    is_demo          TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id  INT UNSIGNED NULL,
    creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_gimnasio_rol (usuario_id, gimnasio_id, rol_id),
    KEY idx_usuario_gimnasio_activo (usuario_id, activo),
    KEY idx_usuario_gimnasio_demo (is_demo, demo_dataset_id),
    CONSTRAINT fk_usuario_gimnasio_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_gimnasio_gimnasio
        FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_gimnasio_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_gimnasio_demo_dataset
        FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_phase3_foreign_keys;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase3_foreign_keys()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND table_name = 'clases' AND constraint_name = 'fk_clases_gimnasio'
    ) THEN
        ALTER TABLE clases
            ADD CONSTRAINT fk_clases_gimnasio
            FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id)
            ON UPDATE CASCADE ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND table_name = 'clases' AND constraint_name = 'fk_clases_demo_dataset'
    ) THEN
        ALTER TABLE clases
            ADD CONSTRAINT fk_clases_demo_dataset
            FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id)
            ON UPDATE CASCADE ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND table_name = 'membresias' AND constraint_name = 'fk_membresias_gimnasio'
    ) THEN
        ALTER TABLE membresias
            ADD CONSTRAINT fk_membresias_gimnasio
            FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id)
            ON UPDATE CASCADE ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND table_name = 'membresias' AND constraint_name = 'fk_membresias_demo_dataset'
    ) THEN
        ALTER TABLE membresias
            ADD CONSTRAINT fk_membresias_demo_dataset
            FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id)
            ON UPDATE CASCADE ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND table_name = 'reservas' AND constraint_name = 'fk_reservas_demo_dataset'
    ) THEN
        ALTER TABLE reservas
            ADD CONSTRAINT fk_reservas_demo_dataset
            FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id)
            ON UPDATE CASCADE ON DELETE RESTRICT;
    END IF;
END$$
DELIMITER ;

CALL gymtrack_phase3_foreign_keys();
DROP PROCEDURE gymtrack_phase3_foreign_keys;

INSERT INTO permisos (codigo, descripcion) VALUES
    ('platform.manage', 'Administrar la plataforma completa'),
    ('gym.manage', 'Administrar un gimnasio asociado'),
    ('gym.operate', 'Operar clases, reservas y socios del gimnasio'),
    ('gym.view', 'Consultar el contexto del gimnasio'),
    ('class.reserve', 'Reservar clases como socio')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT 2, id FROM permisos;
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT 4, id FROM permisos WHERE codigo IN ('gym.manage', 'gym.operate', 'gym.view');
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT 3, id FROM permisos WHERE codigo IN ('gym.operate', 'gym.view');
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT 1, id FROM permisos WHERE codigo IN ('gym.view', 'class.reserve');

INSERT IGNORE INTO schema_migrations (version) VALUES ('002_phase3_roles_demo_context');
