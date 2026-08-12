-- GymTrack Fase 7: pagos verificables, finanzas y reportes descargables.
-- Idempotente para MySQL 8.0. Ejecutar después de 006 y de un respaldo verificado.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    ejecutada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_payment_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_payment_columns()
BEGIN
    ALTER TABLE pagos MODIFY COLUMN monto DECIMAL(12,2) NOT NULL;
    ALTER TABLE pagos MODIFY COLUMN metodo VARCHAR(40) NOT NULL;
    ALTER TABLE pagos MODIFY COLUMN fecha_pago DATETIME NULL DEFAULT NULL;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='gimnasio_id') THEN ALTER TABLE pagos ADD COLUMN gimnasio_id INT UNSIGNED NULL AFTER membresia_id; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='moneda') THEN ALTER TABLE pagos ADD COLUMN moneda CHAR(3) NOT NULL DEFAULT 'UYU' AFTER monto; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='estado') THEN ALTER TABLE pagos ADD COLUMN estado ENUM('pendiente','aprobado','rechazado','vencido','reembolsado','cancelado') NOT NULL DEFAULT 'aprobado' AFTER metodo; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='proveedor') THEN ALTER TABLE pagos ADD COLUMN proveedor ENUM('manual','mercado_pago') NOT NULL DEFAULT 'manual' AFTER estado; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='referencia_externa') THEN ALTER TABLE pagos ADD COLUMN referencia_externa VARCHAR(120) NULL AFTER proveedor; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='preferencia_externa') THEN ALTER TABLE pagos ADD COLUMN preferencia_externa VARCHAR(120) NULL AFTER referencia_externa; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='idempotency_key') THEN ALTER TABLE pagos ADD COLUMN idempotency_key CHAR(36) NULL AFTER preferencia_externa; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='modo') THEN ALTER TABLE pagos ADD COLUMN modo ENUM('prueba','produccion') NOT NULL DEFAULT 'prueba' AFTER idempotency_key; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='concepto') THEN ALTER TABLE pagos ADD COLUMN concepto VARCHAR(180) NOT NULL DEFAULT 'Membresía GymTrack' AFTER modo; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='fecha_vencimiento') THEN ALTER TABLE pagos ADD COLUMN fecha_vencimiento DATETIME NULL AFTER fecha_pago; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='aprobado_en') THEN ALTER TABLE pagos ADD COLUMN aprobado_en DATETIME NULL AFTER fecha_vencimiento; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='rechazado_en') THEN ALTER TABLE pagos ADD COLUMN rechazado_en DATETIME NULL AFTER aprobado_en; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='reembolsado_en') THEN ALTER TABLE pagos ADD COLUMN reembolsado_en DATETIME NULL AFTER rechazado_en; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='metadata_json') THEN ALTER TABLE pagos ADD COLUMN metadata_json JSON NULL AFTER reembolsado_en; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='is_demo') THEN ALTER TABLE pagos ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER metadata_json; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='demo_dataset_id') THEN ALTER TABLE pagos ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='creado_por') THEN ALTER TABLE pagos ADD COLUMN creado_por INT UNSIGNED NULL AFTER demo_dataset_id; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='creado_en') THEN ALTER TABLE pagos ADD COLUMN creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER creado_por; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pagos' AND column_name='actualizado_en') THEN ALTER TABLE pagos ADD COLUMN actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en; END IF;
END$$
DELIMITER ;
CALL gymtrack_payment_columns();
DROP PROCEDURE gymtrack_payment_columns;

UPDATE pagos p JOIN membresias m ON m.id=p.membresia_id
LEFT JOIN planes_membresia pm ON pm.id=m.plan_id
SET p.gimnasio_id=m.gimnasio_id,p.moneda=COALESCE(pm.moneda,'UYU'),p.estado='aprobado',p.proveedor='manual',
    p.referencia_externa=COALESCE(p.referencia_externa,CONCAT('GT-LEGACY-',p.id)),p.aprobado_en=COALESCE(p.aprobado_en,p.fecha_pago),
    p.is_demo=m.is_demo,p.demo_dataset_id=m.demo_dataset_id
WHERE p.referencia_externa IS NULL OR p.gimnasio_id IS NULL;

DROP PROCEDURE IF EXISTS gymtrack_payment_constraints;
DELIMITER $$
CREATE PROCEDURE gymtrack_payment_constraints()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='pagos' AND index_name='uq_pago_referencia') THEN ALTER TABLE pagos ADD UNIQUE KEY uq_pago_referencia (referencia_externa); END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='pagos' AND index_name='uq_pago_idempotency') THEN ALTER TABLE pagos ADD UNIQUE KEY uq_pago_idempotency (gimnasio_id,idempotency_key); END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='pagos' AND index_name='idx_pago_gym_status_date') THEN ALTER TABLE pagos ADD KEY idx_pago_gym_status_date (gimnasio_id,estado,fecha_pago); END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='pagos' AND index_name='idx_pago_demo') THEN ALTER TABLE pagos ADD KEY idx_pago_demo (is_demo,demo_dataset_id); END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='pagos' AND constraint_name='fk_pago_gimnasio') THEN ALTER TABLE pagos ADD CONSTRAINT fk_pago_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE RESTRICT; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='pagos' AND constraint_name='fk_pago_demo') THEN ALTER TABLE pagos ADD CONSTRAINT fk_pago_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='pagos' AND constraint_name='fk_pago_creador') THEN ALTER TABLE pagos ADD CONSTRAINT fk_pago_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL; END IF;
END$$
DELIMITER ;
CALL gymtrack_payment_constraints();
DROP PROCEDURE gymtrack_payment_constraints;

ALTER TABLE membresias MODIFY COLUMN estado ENUM('pendiente_pago','activa','vencida','suspendida') NOT NULL DEFAULT 'pendiente_pago';

CREATE TABLE IF NOT EXISTS pago_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,pago_id INT UNSIGNED NOT NULL,proveedor VARCHAR(40) NOT NULL,
    evento_externo_id VARCHAR(140) NULL,tipo VARCHAR(80) NOT NULL,estado_anterior VARCHAR(30) NULL,estado_nuevo VARCHAR(30) NOT NULL,
    payload_hash CHAR(64) NOT NULL,payload_json JSON NULL,request_id CHAR(36) NOT NULL,creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),UNIQUE KEY uq_pago_evento_externo (proveedor,evento_externo_id),KEY idx_pago_evento (pago_id,creado_en),
    CONSTRAINT fk_pago_evento_pago FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pago_reembolsos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,pago_id INT UNSIGNED NOT NULL,monto DECIMAL(12,2) NOT NULL,
    estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',referencia_externa VARCHAR(140) NULL,
    idempotency_key CHAR(36) NOT NULL,motivo VARCHAR(255) NOT NULL,solicitado_por INT UNSIGNED NULL,
    solicitado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,completado_en DATETIME NULL,
    PRIMARY KEY (id),UNIQUE KEY uq_reembolso_idempotency (pago_id,idempotency_key),KEY idx_reembolso_pago (pago_id,estado),
    CONSTRAINT fk_reembolso_pago FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE CASCADE,
    CONSTRAINT fk_reembolso_actor FOREIGN KEY (solicitado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT chk_reembolso_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_export_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_export_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='exports' AND column_name='archivo_nombre') THEN ALTER TABLE exports ADD COLUMN archivo_nombre VARCHAR(180) NULL AFTER archivo_path; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='exports' AND column_name='mime_type') THEN ALTER TABLE exports ADD COLUMN mime_type VARCHAR(100) NULL AFTER archivo_nombre; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='exports' AND column_name='tamano_bytes') THEN ALTER TABLE exports ADD COLUMN tamano_bytes BIGINT UNSIGNED NULL AFTER mime_type; END IF;
END$$
DELIMITER ;
CALL gymtrack_export_columns();
DROP PROCEDURE gymtrack_export_columns;

INSERT IGNORE INTO schema_migrations (version) VALUES ('007_payments_finance_reports');
