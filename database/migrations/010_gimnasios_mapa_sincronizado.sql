-- Ubicación normalizada y caché de geocodificación para el catálogo de gimnasios.
-- Idempotente para MySQL 8.0.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    ejecutada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_gimnasios_mapa;
DELIMITER $$
CREATE PROCEDURE gymtrack_gimnasios_mapa()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='gimnasios' AND column_name='pais'
    ) THEN
        ALTER TABLE gimnasios ADD COLUMN pais VARCHAR(100) NOT NULL DEFAULT 'Uruguay' AFTER departamento;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='gimnasio_sedes' AND column_name='pais'
    ) THEN
        ALTER TABLE gimnasio_sedes ADD COLUMN pais VARCHAR(100) NOT NULL DEFAULT 'Uruguay' AFTER departamento;
    END IF;

    ALTER TABLE gimnasios
        MODIFY COLUMN estado ENUM('borrador','publicado','inactivo','temporalmente_cerrado') NOT NULL DEFAULT 'borrador';
END$$
DELIMITER ;
CALL gymtrack_gimnasios_mapa();
DROP PROCEDURE gymtrack_gimnasios_mapa;

CREATE TABLE IF NOT EXISTS geocodificacion_cache (
    consulta_hash CHAR(64) NOT NULL,
    consulta VARCHAR(500) NOT NULL,
    resultados_json JSON NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en DATETIME NOT NULL,
    PRIMARY KEY (consulta_hash),
    KEY idx_geocodificacion_expira (expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version) VALUES ('010_gimnasios_mapa_sincronizado');
