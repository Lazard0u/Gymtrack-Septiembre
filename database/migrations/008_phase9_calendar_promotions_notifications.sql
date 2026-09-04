-- GymTrack: promociones, preferencias y entregas multicanal.
-- Idempotente para MySQL 8.0. Ejecutar después de 007 y de un respaldo verificado.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    ejecutada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Las imágenes promocionales reutilizan el almacenamiento privado incorporado por la migración 005.
-- Si la migración 009 ya está aplicada, una repetición de 008 conserva socio_avatar.
DROP PROCEDURE IF EXISTS gymtrack_phase9_file_categories;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase9_file_categories()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='archivos' AND column_name='categoria'
          AND column_type LIKE '%socio_avatar%'
    ) THEN
        ALTER TABLE archivos MODIFY COLUMN categoria ENUM(
            'gimnasio_imagen','entrenador_foto','promocion_imagen','socio_avatar','documento'
        ) NOT NULL;
    ELSE
        ALTER TABLE archivos MODIFY COLUMN categoria ENUM(
            'gimnasio_imagen','entrenador_foto','promocion_imagen','documento'
        ) NOT NULL;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase9_file_categories();
DROP PROCEDURE gymtrack_phase9_file_categories;

CREATE TABLE IF NOT EXISTS promociones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gimnasio_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NOT NULL,
    estado ENUM('borrador','programada','activa','pausada','finalizada') NOT NULL DEFAULT 'borrador',
    audiencia ENUM('todos_socios','socios_activos','socios_con_deuda','socios_inactivos') NOT NULL DEFAULT 'todos_socios',
    inicio_en DATETIME NOT NULL,
    fin_en DATETIME NOT NULL,
    zona_horaria VARCHAR(80) NOT NULL DEFAULT 'America/Montevideo',
    tipo_descuento ENUM('sin_descuento','porcentaje','monto_fijo') NOT NULL DEFAULT 'sin_descuento',
    valor_descuento DECIMAL(10,2) NULL,
    moneda CHAR(3) NULL,
    codigo_descuento VARCHAR(60) NULL,
    imagen_archivo_id BIGINT UNSIGNED NULL,
    canales_json JSON NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    creado_por INT UNSIGNED NOT NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    eliminado_en DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_promocion_gym_estado_fecha (gimnasio_id,estado,inicio_en,fin_en),
    KEY idx_promocion_demo (is_demo,demo_dataset_id),
    KEY idx_promocion_image (imagen_archivo_id),
    CONSTRAINT fk_promocion_gym FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_promocion_image FOREIGN KEY (imagen_archivo_id) REFERENCES archivos(id) ON DELETE SET NULL,
    CONSTRAINT fk_promocion_actor FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_promocion_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT,
    CONSTRAINT chk_promocion_fechas CHECK (fin_en > inicio_en),
    CONSTRAINT chk_promocion_descuento CHECK (
        (tipo_descuento='sin_descuento' AND valor_descuento IS NULL)
        OR (tipo_descuento='porcentaje' AND valor_descuento>0 AND valor_descuento<=100)
        OR (tipo_descuento='monto_fijo' AND valor_descuento>0 AND moneda IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promocion_gimnasios (
    promocion_id BIGINT UNSIGNED NOT NULL,
    gimnasio_id INT UNSIGNED NOT NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (promocion_id,gimnasio_id),
    KEY idx_promocion_gimnasio_lookup (gimnasio_id,promocion_id),
    KEY idx_promocion_gimnasio_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_promocion_gimnasio_promotion FOREIGN KEY (promocion_id) REFERENCES promociones(id) ON DELETE CASCADE,
    CONSTRAINT fk_promocion_gimnasio_gym FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_promocion_gimnasio_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conserva la tabla heredada y la convierte en un inbox tipado e idempotente.
DROP PROCEDURE IF EXISTS gymtrack_phase9_notification_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase9_notification_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='gimnasio_id') THEN
        ALTER TABLE notificaciones ADD COLUMN gimnasio_id INT UNSIGNED NULL AFTER usuario_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='titulo') THEN
        ALTER TABLE notificaciones ADD COLUMN titulo VARCHAR(160) NULL AFTER gimnasio_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='cuerpo') THEN
        ALTER TABLE notificaciones ADD COLUMN cuerpo TEXT NULL AFTER titulo;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='categoria') THEN
        ALTER TABLE notificaciones ADD COLUMN categoria ENUM('transaccional','marketing') NOT NULL DEFAULT 'transaccional' AFTER cuerpo;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='link_url') THEN
        ALTER TABLE notificaciones ADD COLUMN link_url VARCHAR(500) NULL AFTER categoria;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='leida_en') THEN
        ALTER TABLE notificaciones ADD COLUMN leida_en DATETIME NULL AFTER leida;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='idempotency_key') THEN
        ALTER TABLE notificaciones ADD COLUMN idempotency_key VARCHAR(191) NULL AFTER leida_en;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='is_demo') THEN
        ALTER TABLE notificaciones ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER idempotency_key;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='demo_dataset_id') THEN
        ALTER TABLE notificaciones ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notificaciones' AND index_name='uq_notificacion_idempotency') THEN
        ALTER TABLE notificaciones ADD UNIQUE KEY uq_notificacion_idempotency (idempotency_key);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notificaciones' AND index_name='idx_notificacion_inbox') THEN
        ALTER TABLE notificaciones ADD KEY idx_notificacion_inbox (usuario_id,gimnasio_id,leida,creado_en);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notificaciones' AND index_name='idx_notificacion_demo') THEN
        ALTER TABLE notificaciones ADD KEY idx_notificacion_demo (is_demo,demo_dataset_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='notificaciones' AND constraint_name='fk_notificacion_gym') THEN
        ALTER TABLE notificaciones ADD CONSTRAINT fk_notificacion_gym FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE SET NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='notificaciones' AND constraint_name='fk_notificacion_demo') THEN
        ALTER TABLE notificaciones ADD CONSTRAINT fk_notificacion_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase9_notification_columns();
DROP PROCEDURE gymtrack_phase9_notification_columns;

UPDATE notificaciones
SET titulo=COALESCE(titulo,'Notificación'),
    cuerpo=COALESCE(cuerpo,mensaje),
    leida_en=IF(leida=1,COALESCE(leida_en,creado_en),NULL)
WHERE titulo IS NULL OR cuerpo IS NULL OR (leida=1 AND leida_en IS NULL);

UPDATE notificaciones n JOIN usuarios u ON u.id=n.usuario_id
SET n.is_demo=u.is_demo,n.demo_dataset_id=u.demo_dataset_id
WHERE n.is_demo<>u.is_demo OR NOT (n.demo_dataset_id <=> u.demo_dataset_id);

CREATE TABLE IF NOT EXISTS notificacion_preferencias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    internal_transactional TINYINT(1) NOT NULL DEFAULT 1,
    internal_marketing TINYINT(1) NOT NULL DEFAULT 0,
    email_transactional TINYINT(1) NOT NULL DEFAULT 1,
    email_marketing TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_marketing TINYINT(1) NOT NULL DEFAULT 0,
    marketing_unsubscribed_at DATETIME NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_preferences_user (usuario_id),
    KEY idx_notification_preferences_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_notification_preferences_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_preferences_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_unsubscribe_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketing_unsubscribe_hash (token_hash),
    KEY idx_marketing_unsubscribe_user (usuario_id,usado_en,expira_en),
    KEY idx_marketing_unsubscribe_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_marketing_unsubscribe_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_marketing_unsubscribe_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notificacion_envios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    promocion_id BIGINT UNSIGNED NULL,
    notificacion_id INT UNSIGNED NULL,
    gimnasio_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    canal ENUM('internal','email','whatsapp') NOT NULL,
    categoria ENUM('transaccional','marketing') NOT NULL,
    estado ENUM('pendiente','procesando','enviado','fallido','omitido') NOT NULL DEFAULT 'pendiente',
    idempotency_key VARCHAR(191) NOT NULL,
    intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_intentos SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    proximo_intento_en DATETIME NULL,
    programado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    provider_message_id VARCHAR(191) NULL,
    error_codigo VARCHAR(80) NULL,
    error_seguro VARCHAR(255) NULL,
    payload_json JSON NOT NULL,
    procesando_en DATETIME NULL,
    enviado_en DATETIME NULL,
    fallido_en DATETIME NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_delivery_idempotency (idempotency_key),
    KEY idx_notification_delivery_queue (estado,proximo_intento_en,programado_en),
    KEY idx_notification_delivery_promotion (promocion_id,canal,estado),
    KEY idx_notification_delivery_user (usuario_id,creado_en),
    KEY idx_notification_delivery_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_notification_delivery_promotion FOREIGN KEY (promocion_id) REFERENCES promociones(id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_delivery_notification FOREIGN KEY (notificacion_id) REFERENCES notificaciones(id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_delivery_gym FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_delivery_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_delivery_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permisos (codigo,descripcion) VALUES
('promotions.read','Consultar promociones del gimnasio'),
('promotions.write','Gestionar promociones del gimnasio')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion);

INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('promotions.read','promotions.write')
WHERE r.nombre IN ('dueño','admin_general');
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='promotions.read'
WHERE r.nombre='empleado';

INSERT IGNORE INTO schema_migrations (version) VALUES ('008_phase9_calendar_promotions_notifications');
