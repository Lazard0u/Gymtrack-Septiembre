-- GymTrack: administración operativa, auditoría y exportaciones.
-- Idempotente. No crea datos de negocio ni ejecuta exportaciones.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NULL,
    gimnasio_id INT UNSIGNED NULL,
    accion VARCHAR(100) NOT NULL,
    entidad VARCHAR(80) NOT NULL,
    entidad_id VARCHAR(80) NULL,
    request_id CHAR(36) NOT NULL,
    resultado ENUM('success','denied','failed') NOT NULL,
    motivo VARCHAR(255) NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    ip_hash CHAR(64) NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_gym_date (gimnasio_id, creado_en),
    KEY idx_audit_user_date (usuario_id, creado_en),
    KEY idx_audit_request (request_id),
    KEY idx_audit_demo_dataset (is_demo, demo_dataset_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_gym FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_demo_dataset FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NULL,
    gimnasio_id INT UNSIGNED NULL,
    tipo ENUM('xlsx','pdf') NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    filtros_json JSON NULL,
    estado ENUM('pendiente','procesando','completado','fallido','expirado') NOT NULL DEFAULT 'pendiente',
    archivo_path VARCHAR(255) NULL,
    error_seguro VARCHAR(255) NULL,
    request_id CHAR(36) NOT NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completado_en DATETIME NULL,
    expira_en DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_exports_gym_date (gimnasio_id, creado_en),
    KEY idx_exports_user_date (usuario_id, creado_en),
    KEY idx_exports_demo_dataset (is_demo, demo_dataset_id),
    CONSTRAINT fk_exports_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_exports_gym FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE SET NULL,
    CONSTRAINT fk_exports_demo_dataset FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_phase4_demo_scope;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase4_demo_scope()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='audit_logs' AND column_name='is_demo') THEN
        ALTER TABLE audit_logs ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER ip_hash;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='audit_logs' AND column_name='demo_dataset_id') THEN
        ALTER TABLE audit_logs ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='audit_logs' AND index_name='idx_audit_demo_dataset') THEN
        ALTER TABLE audit_logs ADD INDEX idx_audit_demo_dataset (is_demo, demo_dataset_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='audit_logs' AND constraint_name='fk_audit_demo_dataset') THEN
        ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_demo_dataset FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='exports' AND column_name='is_demo') THEN
        ALTER TABLE exports ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER request_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='exports' AND column_name='demo_dataset_id') THEN
        ALTER TABLE exports ADD COLUMN demo_dataset_id INT UNSIGNED NULL AFTER is_demo;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='exports' AND index_name='idx_exports_demo_dataset') THEN
        ALTER TABLE exports ADD INDEX idx_exports_demo_dataset (is_demo, demo_dataset_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='exports' AND constraint_name='fk_exports_demo_dataset') THEN
        ALTER TABLE exports ADD CONSTRAINT fk_exports_demo_dataset FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase4_demo_scope();
DROP PROCEDURE gymtrack_phase4_demo_scope;

UPDATE audit_logs a
LEFT JOIN usuarios u ON u.id=a.usuario_id
LEFT JOIN gimnasios g ON g.id=a.gimnasio_id
SET a.is_demo=GREATEST(COALESCE(u.is_demo,0),COALESCE(g.is_demo,0)),
    a.demo_dataset_id=COALESCE(u.demo_dataset_id,g.demo_dataset_id)
WHERE a.demo_dataset_id IS NULL AND (COALESCE(u.is_demo,0)=1 OR COALESCE(g.is_demo,0)=1);

UPDATE exports e
LEFT JOIN usuarios u ON u.id=e.usuario_id
LEFT JOIN gimnasios g ON g.id=e.gimnasio_id
SET e.is_demo=GREATEST(COALESCE(u.is_demo,0),COALESCE(g.is_demo,0)),
    e.demo_dataset_id=COALESCE(u.demo_dataset_id,g.demo_dataset_id)
WHERE e.demo_dataset_id IS NULL AND (COALESCE(u.is_demo,0)=1 OR COALESCE(g.is_demo,0)=1);

INSERT IGNORE INTO schema_migrations (version) VALUES ('004_phase4_administration');
