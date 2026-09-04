-- Reversión controlada de la migración 008. Requiere respaldo verificado.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- No permite revertir una migración intermedia mientras la migración 009 siga aplicada.
DROP PROCEDURE IF EXISTS gymtrack_phase9_rollback_guard;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase9_rollback_guard()
BEGIN
    IF EXISTS (
        SELECT 1 FROM schema_migrations
        WHERE version='009_phase10_member_experience'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Revertí primero 009_phase10_member_experience.';
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase9_rollback_guard();
DROP PROCEDURE gymtrack_phase9_rollback_guard;

DELETE rp FROM rol_permisos rp JOIN permisos p ON p.id=rp.permiso_id
WHERE p.codigo IN ('promotions.read','promotions.write');
DELETE FROM permisos WHERE codigo IN ('promotions.read','promotions.write');

DROP TABLE IF EXISTS notificacion_envios;
DROP TABLE IF EXISTS marketing_unsubscribe_tokens;
DROP TABLE IF EXISTS notificacion_preferencias;
DROP TABLE IF EXISTS promocion_gimnasios;
DROP TABLE IF EXISTS promociones;

DROP PROCEDURE IF EXISTS gymtrack_phase9_notification_rollback;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase9_notification_rollback()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='notificaciones' AND constraint_name='fk_notificacion_gym') THEN
        ALTER TABLE notificaciones DROP FOREIGN KEY fk_notificacion_gym;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='notificaciones' AND constraint_name='fk_notificacion_demo') THEN
        ALTER TABLE notificaciones DROP FOREIGN KEY fk_notificacion_demo;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notificaciones' AND index_name='uq_notificacion_idempotency') THEN
        ALTER TABLE notificaciones DROP INDEX uq_notificacion_idempotency;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notificaciones' AND index_name='idx_notificacion_inbox') THEN
        ALTER TABLE notificaciones DROP INDEX idx_notificacion_inbox;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notificaciones' AND index_name='idx_notificacion_demo') THEN
        ALTER TABLE notificaciones DROP INDEX idx_notificacion_demo;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='demo_dataset_id') THEN ALTER TABLE notificaciones DROP COLUMN demo_dataset_id; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='is_demo') THEN ALTER TABLE notificaciones DROP COLUMN is_demo; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='idempotency_key') THEN ALTER TABLE notificaciones DROP COLUMN idempotency_key; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='leida_en') THEN ALTER TABLE notificaciones DROP COLUMN leida_en; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='link_url') THEN ALTER TABLE notificaciones DROP COLUMN link_url; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='categoria') THEN ALTER TABLE notificaciones DROP COLUMN categoria; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='cuerpo') THEN ALTER TABLE notificaciones DROP COLUMN cuerpo; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='titulo') THEN ALTER TABLE notificaciones DROP COLUMN titulo; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificaciones' AND column_name='gimnasio_id') THEN ALTER TABLE notificaciones DROP COLUMN gimnasio_id; END IF;
END$$
DELIMITER ;
CALL gymtrack_phase9_notification_rollback();
DROP PROCEDURE gymtrack_phase9_notification_rollback;

UPDATE archivos SET categoria='documento' WHERE categoria='promocion_imagen';
ALTER TABLE archivos
    MODIFY COLUMN categoria ENUM('gimnasio_imagen','entrenador_foto','documento') NOT NULL;

DELETE FROM schema_migrations WHERE version='008_phase9_calendar_promotions_notifications';
