-- GymTrack Fase 5: tenant operativo, personas, planes y membresías.
-- Idempotente para MySQL 8.0. Ejecutar únicamente después de un respaldo.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    ejecutada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_phase5_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase5_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='gimnasios' AND column_name='nombre_legal') THEN
        ALTER TABLE gimnasios ADD COLUMN nombre_legal VARCHAR(160) NULL AFTER nombre;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='gimnasios' AND column_name='verificacion_estado') THEN
        ALTER TABLE gimnasios ADD COLUMN verificacion_estado ENUM('pendiente','verificado','rechazado') NOT NULL DEFAULT 'pendiente' AFTER estado;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='gimnasios' AND column_name='archivado_en') THEN
        ALTER TABLE gimnasios ADD COLUMN archivado_en DATETIME NULL AFTER actualizado_en;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='membresias' AND column_name='plan_id') THEN
        ALTER TABLE membresias ADD COLUMN plan_id BIGINT UNSIGNED NULL AFTER gimnasio_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='membresias' AND column_name='numero_socio') THEN
        ALTER TABLE membresias ADD COLUMN numero_socio VARCHAR(40) NULL AFTER usuario_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='membresias' AND column_name='actualizado_en') THEN
        ALTER TABLE membresias ADD COLUMN actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='membresias' AND index_name='uq_membresias_numero_socio') THEN
        ALTER TABLE membresias DROP INDEX uq_membresias_numero_socio;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='membresias' AND index_name='idx_membresias_numero_socio') THEN
        ALTER TABLE membresias ADD KEY idx_membresias_numero_socio (gimnasio_id, numero_socio);
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase5_columns();
DROP PROCEDURE gymtrack_phase5_columns;

UPDATE gimnasios SET nombre_legal=nombre WHERE nombre_legal IS NULL OR nombre_legal='';
ALTER TABLE gimnasios MODIFY COLUMN nombre_legal VARCHAR(160) NOT NULL;
UPDATE gimnasios SET verificacion_estado=IF(estado='borrador','pendiente','verificado') WHERE is_demo=1;

CREATE TABLE IF NOT EXISTS gimnasio_sedes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gimnasio_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    direccion VARCHAR(180) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    departamento VARCHAR(100) NOT NULL,
    latitud DECIMAL(10,7) NOT NULL,
    longitud DECIMAL(10,7) NOT NULL,
    zona_horaria VARCHAR(80) NOT NULL DEFAULT 'America/Montevideo',
    telefono VARCHAR(40) NULL,
    email VARCHAR(150) NULL,
    horarios_json JSON NOT NULL,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    estado ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sede_nombre (gimnasio_id,nombre),
    KEY idx_sede_gimnasio_estado (gimnasio_id,estado),
    KEY idx_sede_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_sede_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_sede_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO gimnasio_sedes
    (gimnasio_id,nombre,direccion,ciudad,departamento,latitud,longitud,zona_horaria,telefono,email,horarios_json,es_principal,estado,is_demo,demo_dataset_id)
SELECT g.id,'Sede principal',g.direccion,g.ciudad,g.departamento,g.latitud,g.longitud,g.zona_horaria,g.telefono,g.email,g.horarios_json,1,'activa',g.is_demo,g.demo_dataset_id
FROM gimnasios g
WHERE NOT EXISTS (SELECT 1 FROM gimnasio_sedes s WHERE s.gimnasio_id=g.id AND s.es_principal=1);

CREATE TABLE IF NOT EXISTS socio_perfiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_gimnasio_rol_id INT UNSIGNED NOT NULL,
    numero_socio VARCHAR(40) NOT NULL,
    estado ENUM('activo','inactivo','archivado') NOT NULL DEFAULT 'activo',
    fecha_alta DATE NOT NULL,
    notas_operativas TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_socio_perfil_asignacion (usuario_gimnasio_rol_id),
    UNIQUE KEY uq_socio_numero (numero_socio),
    CONSTRAINT fk_socio_perfil_asignacion FOREIGN KEY (usuario_gimnasio_rol_id) REFERENCES usuario_gimnasio_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO socio_perfiles (usuario_gimnasio_rol_id,numero_socio,estado,fecha_alta)
SELECT ugr.id,CONCAT('GT-',LPAD(ugr.gimnasio_id,4,'0'),'-',LPAD(ugr.usuario_id,6,'0')),IF(ugr.activo=1,'activo','inactivo'),DATE(ugr.creado_en)
FROM usuario_gimnasio_roles ugr JOIN roles r ON r.id=ugr.rol_id WHERE r.nombre='socio';

CREATE TABLE IF NOT EXISTS empleado_perfiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_gimnasio_rol_id INT UNSIGNED NOT NULL,
    cargo VARCHAR(100) NOT NULL DEFAULT 'Operación',
    estado ENUM('activo','inactivo','archivado') NOT NULL DEFAULT 'activo',
    fecha_ingreso DATE NULL,
    notas_operativas TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_empleado_perfil_asignacion (usuario_gimnasio_rol_id),
    CONSTRAINT fk_empleado_perfil_asignacion FOREIGN KEY (usuario_gimnasio_rol_id) REFERENCES usuario_gimnasio_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO empleado_perfiles (usuario_gimnasio_rol_id,cargo,estado,fecha_ingreso)
SELECT ugr.id,'Operación',IF(ugr.activo=1,'activo','inactivo'),DATE(ugr.creado_en)
FROM usuario_gimnasio_roles ugr JOIN roles r ON r.id=ugr.rol_id WHERE r.nombre='empleado';

CREATE TABLE IF NOT EXISTS entrenador_perfiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    biografia VARCHAR(1000) NULL,
    especialidades_json JSON NOT NULL,
    disponibilidad_json JSON NULL,
    foto_archivo_id BIGINT UNSIGNED NULL,
    estado ENUM('activo','inactivo','archivado') NOT NULL DEFAULT 'activo',
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entrenador_usuario (usuario_id),
    KEY idx_entrenador_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_entrenador_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_entrenador_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entrenador_gimnasios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entrenador_perfil_id BIGINT UNSIGNED NOT NULL,
    gimnasio_id INT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entrenador_gimnasio (entrenador_perfil_id,gimnasio_id),
    KEY idx_entrenador_gym (gimnasio_id,activo),
    CONSTRAINT fk_entrenador_gym_perfil FOREIGN KEY (entrenador_perfil_id) REFERENCES entrenador_perfiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrenador_gym_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrenador_gym_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planes_membresia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gimnasio_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    descripcion VARCHAR(500) NULL,
    duracion_dias SMALLINT UNSIGNED NOT NULL,
    precio DECIMAL(12,2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'UYU',
    beneficios_json JSON NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    estado ENUM('borrador','activo','archivado') NOT NULL DEFAULT 'borrador',
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_por INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_plan_version (gimnasio_id,slug,version),
    KEY idx_plan_gimnasio_estado (gimnasio_id,estado),
    KEY idx_plan_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_plan_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_plan_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_plan_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_phase5_membership_fk;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase5_membership_fk()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='membresias' AND constraint_name='fk_membresia_plan') THEN
        ALTER TABLE membresias ADD CONSTRAINT fk_membresia_plan FOREIGN KEY (plan_id) REFERENCES planes_membresia(id) ON DELETE RESTRICT;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase5_membership_fk();
DROP PROCEDURE gymtrack_phase5_membership_fk;

CREATE TABLE IF NOT EXISTS membresia_historial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    membresia_id INT UNSIGNED NOT NULL,
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NOT NULL,
    motivo VARCHAR(255) NULL,
    actor_usuario_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_historial_membresia (membresia_id,creado_en),
    CONSTRAINT fk_historial_membresia FOREIGN KEY (membresia_id) REFERENCES membresias(id) ON DELETE CASCADE,
    CONSTRAINT fk_historial_actor FOREIGN KEY (actor_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO membresia_historial (membresia_id,estado_anterior,estado_nuevo,motivo,actor_usuario_id)
SELECT m.id,NULL,m.estado,'Importación de membresía existente',NULL FROM membresias m
WHERE NOT EXISTS (SELECT 1 FROM membresia_historial h WHERE h.membresia_id=m.id);

CREATE TABLE IF NOT EXISTS invitaciones_gimnasio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gimnasio_id INT UNSIGNED NOT NULL,
    email VARCHAR(150) NOT NULL,
    email_normalizado VARCHAR(150) NOT NULL,
    tipo ENUM('socio','empleado','entrenador') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    permisos_json JSON NULL,
    perfil_json JSON NULL,
    estado ENUM('pendiente','aceptada','revocada','vencida') NOT NULL DEFAULT 'pendiente',
    invitado_por INT UNSIGNED NOT NULL,
    expira_en DATETIME NOT NULL,
    aceptada_por INT UNSIGNED NULL,
    aceptada_en DATETIME NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invitacion_token (token_hash),
    KEY idx_invitacion_gym_estado (gimnasio_id,estado,expira_en),
    KEY idx_invitacion_email (email_normalizado,estado),
    CONSTRAINT fk_invitacion_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_invitacion_actor FOREIGN KEY (invitado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invitacion_aceptada FOREIGN KEY (aceptada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_invitacion_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS archivos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gimnasio_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    categoria ENUM('gimnasio_imagen','entrenador_foto','documento') NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    storage_key VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamano_bytes INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    estado ENUM('activo','eliminado') NOT NULL DEFAULT 'activo',
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_por INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    eliminado_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_archivo_storage (storage_key),
    KEY idx_archivo_gym_category (gimnasio_id,categoria,estado),
    CONSTRAINT fk_archivo_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_archivo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_archivo_actor FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_archivo_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_phase5_trainer_photo_fk;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase5_trainer_photo_fk()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='entrenador_perfiles' AND constraint_name='fk_entrenador_foto') THEN
        ALTER TABLE entrenador_perfiles ADD CONSTRAINT fk_entrenador_foto FOREIGN KEY (foto_archivo_id) REFERENCES archivos(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase5_trainer_photo_fk();
DROP PROCEDURE gymtrack_phase5_trainer_photo_fk;

INSERT IGNORE INTO schema_migrations (version) VALUES ('005_phase5_tenant_operations');
