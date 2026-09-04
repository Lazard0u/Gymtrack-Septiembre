-- GymTrack: perfil, favoritos, progreso y carné digital del socio.
-- Idempotente para MySQL 8.0. Ejecutar despues de 008 y de un respaldo verificado.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    ejecutada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Los avatares reutilizan el almacenamiento privado, validado y fuera del webroot.
ALTER TABLE archivos
    MODIFY COLUMN categoria ENUM(
        'gimnasio_imagen','entrenador_foto','promocion_imagen','socio_avatar','documento'
    ) NOT NULL;

DROP PROCEDURE IF EXISTS gymtrack_phase10_user_photo;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase10_user_photo()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='foto_archivo_id'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN foto_archivo_id BIGINT UNSIGNED NULL AFTER fecha_nacimiento;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema=DATABASE() AND table_name='usuarios' AND index_name='idx_usuario_foto'
    ) THEN
        ALTER TABLE usuarios ADD KEY idx_usuario_foto (foto_archivo_id);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema=DATABASE() AND table_name='usuarios' AND constraint_name='fk_usuario_foto'
    ) THEN
        ALTER TABLE usuarios
            ADD CONSTRAINT fk_usuario_foto FOREIGN KEY (foto_archivo_id)
            REFERENCES archivos(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase10_user_photo();
DROP PROCEDURE gymtrack_phase10_user_photo;

CREATE TABLE IF NOT EXISTS socio_preferencias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    gimnasio_id INT UNSIGNED NOT NULL,
    objetivo_asistencias_mes TINYINT UNSIGNED NOT NULL DEFAULT 8,
    mostrar_imc_orientativo TINYINT(1) NOT NULL DEFAULT 1,
    horarios_preferidos_json JSON NOT NULL DEFAULT (JSON_ARRAY()),
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_socio_preferencia_contexto (usuario_id,gimnasio_id),
    KEY idx_socio_preferencia_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_socio_preferencia_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_preferencia_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_preferencia_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT,
    CONSTRAINT chk_socio_objetivo_mes CHECK (objetivo_asistencias_mes BETWEEN 1 AND 31)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permite volver a ejecutar 009 sobre una instalación que alcanzó una versión
-- preliminar del esquema sin perder las preferencias ya guardadas.
DROP PROCEDURE IF EXISTS gymtrack_phase10_preferred_schedules;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase10_preferred_schedules()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='socio_preferencias'
          AND column_name='horarios_preferidos_json'
    ) THEN
        ALTER TABLE socio_preferencias
            ADD COLUMN horarios_preferidos_json JSON NOT NULL DEFAULT (JSON_ARRAY())
            AFTER mostrar_imc_orientativo;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase10_preferred_schedules();
DROP PROCEDURE gymtrack_phase10_preferred_schedules;

-- Catalogo estable para que una preferencia no dependa de una sesion fechada.
CREATE TABLE IF NOT EXISTS actividades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gimnasio_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    descripcion VARCHAR(600) NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_actividad_gimnasio_nombre (gimnasio_id,nombre),
    UNIQUE KEY uq_actividad_gimnasio_slug (gimnasio_id,slug),
    KEY idx_actividad_gimnasio_activa (gimnasio_id,activa,nombre),
    KEY idx_actividad_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_actividad_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_actividad_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Convierte las definiciones de clase existentes en un catalogo inicial reproducible.
INSERT IGNORE INTO actividades
    (gimnasio_id,nombre,slug,descripcion,activa,is_demo,demo_dataset_id)
SELECT c.gimnasio_id,c.nombre,CONCAT('actividad-',MIN(c.id)),MIN(c.descripcion),MAX(c.activa),c.is_demo,c.demo_dataset_id
FROM clases c
WHERE c.gimnasio_id IS NOT NULL
GROUP BY c.gimnasio_id,c.nombre,c.is_demo,c.demo_dataset_id;

CREATE TABLE IF NOT EXISTS socio_gimnasios_favoritos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    gimnasio_id INT UNSIGNED NOT NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_socio_gimnasio_favorito (usuario_id,gimnasio_id),
    KEY idx_socio_gimnasio_favorito_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_socio_gimnasio_favorito_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_gimnasio_favorito_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_gimnasio_favorito_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS socio_actividades_favoritas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    actividad_id BIGINT UNSIGNED NOT NULL,
    gimnasio_id INT UNSIGNED NOT NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_socio_actividad_favorita (usuario_id,actividad_id),
    KEY idx_socio_actividad_contexto (usuario_id,gimnasio_id),
    KEY idx_socio_actividad_favorita_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_socio_actividad_favorita_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_actividad_favorita_actividad FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_actividad_favorita_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_actividad_favorita_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS socio_mediciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    gimnasio_id INT UNSIGNED NOT NULL,
    fecha_medicion DATE NOT NULL,
    altura_cm DECIMAL(5,2) NULL,
    peso_kg DECIMAL(6,2) NULL,
    notas VARCHAR(500) NULL,
    idempotency_key CHAR(36) NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_socio_medicion_idempotency (usuario_id,gimnasio_id,idempotency_key),
    KEY idx_socio_medicion_historial (usuario_id,gimnasio_id,fecha_medicion,id),
    KEY idx_socio_medicion_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_socio_medicion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_medicion_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    CONSTRAINT fk_socio_medicion_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT,
    CONSTRAINT chk_socio_medicion_valor CHECK (altura_cm IS NOT NULL OR peso_kg IS NOT NULL),
    CONSTRAINT chk_socio_medicion_altura CHECK (altura_cm IS NULL OR altura_cm BETWEEN 80 AND 250),
    CONSTRAINT chk_socio_medicion_peso CHECK (peso_kg IS NULL OR peso_kg BETWEEN 20 AND 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version) VALUES ('009_phase10_member_experience');
