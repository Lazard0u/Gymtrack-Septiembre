-- GymTrack fase 1: restricciones urgentes para instalaciones existentes.
-- Ejecutar sobre la base seleccionada después de un backup verificado.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version      VARCHAR(100) NOT NULL,
    ejecutada_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Deshabilita cuentas cuyo password_hash no usa un formato reconocido por password_verify().
-- Deben reactivarse únicamente mediante seed_admin.php o un futuro flujo de recuperación.
UPDATE usuarios
SET activo = 0
WHERE password_hash NOT LIKE '$2y$%'
  AND password_hash NOT LIKE '$2a$%'
  AND password_hash NOT LIKE '$2b$%'
  AND password_hash NOT LIKE '$argon2i$%'
  AND password_hash NOT LIKE '$argon2id$%';

SET @sql_roles = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'roles' AND index_name = 'uq_roles_nombre') = 0,
    'ALTER TABLE roles ADD CONSTRAINT uq_roles_nombre UNIQUE (nombre)',
    'SELECT 1'
);
PREPARE stmt_roles FROM @sql_roles;
EXECUTE stmt_roles;
DEALLOCATE PREPARE stmt_roles;

SET @sql_reserva_unique = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'reservas' AND index_name = 'uq_reservas_usuario_clase') = 0,
    'ALTER TABLE reservas ADD CONSTRAINT uq_reservas_usuario_clase UNIQUE (usuario_id, clase_id)',
    'SELECT 1'
);
PREPARE stmt_reserva_unique FROM @sql_reserva_unique;
EXECUTE stmt_reserva_unique;
DEALLOCATE PREPARE stmt_reserva_unique;

SET @sql_reserva_clase = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'reservas' AND index_name = 'idx_reservas_clase_estado') = 0,
    'ALTER TABLE reservas ADD INDEX idx_reservas_clase_estado (clase_id, estado)',
    'SELECT 1'
);
PREPARE stmt_reserva_clase FROM @sql_reserva_clase;
EXECUTE stmt_reserva_clase;
DEALLOCATE PREPARE stmt_reserva_clase;

SET @sql_reserva_usuario = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'reservas' AND index_name = 'idx_reservas_usuario_fecha') = 0,
    'ALTER TABLE reservas ADD INDEX idx_reservas_usuario_fecha (usuario_id, fecha_reserva)',
    'SELECT 1'
);
PREPARE stmt_reserva_usuario FROM @sql_reserva_usuario;
EXECUTE stmt_reserva_usuario;
DEALLOCATE PREPARE stmt_reserva_usuario;

INSERT IGNORE INTO schema_migrations (version) VALUES ('001_phase1_integrity');
