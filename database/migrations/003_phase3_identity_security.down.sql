-- Rollback controlado de 003. Requiere respaldo y ventana de mantenimiento.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS owner_registration_requests;
DROP TABLE IF EXISTS usuario_gimnasio_permisos;
DROP TABLE IF EXISTS role_migration_report;
DROP TABLE IF EXISTS security_events;
DROP TABLE IF EXISTS user_consents;
DROP TABLE IF EXISTS rate_limit_attempts;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS email_verification_tokens;
DROP TABLE IF EXISTS user_sessions;
SET FOREIGN_KEY_CHECKS=1;

DROP PROCEDURE IF EXISTS gymtrack_phase3_identity_rollback;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase3_identity_rollback()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='usuarios' AND index_name='uq_usuarios_email_normalizado') THEN
        ALTER TABLE usuarios DROP INDEX uq_usuarios_email_normalizado;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='actualizado_en') THEN ALTER TABLE usuarios DROP COLUMN actualizado_en; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='marketing_consentido_en') THEN ALTER TABLE usuarios DROP COLUMN marketing_consentido_en; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='privacidad_aceptada_en') THEN ALTER TABLE usuarios DROP COLUMN privacidad_aceptada_en; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='terminos_aceptados_en') THEN ALTER TABLE usuarios DROP COLUMN terminos_aceptados_en; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='debe_cambiar_password') THEN ALTER TABLE usuarios DROP COLUMN debe_cambiar_password; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='email_verificado_en') THEN ALTER TABLE usuarios DROP COLUMN email_verificado_en; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='email_normalizado') THEN ALTER TABLE usuarios DROP COLUMN email_normalizado; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='apellido') THEN ALTER TABLE usuarios DROP COLUMN apellido; END IF;
END$$
DELIMITER ;
CALL gymtrack_phase3_identity_rollback();
DROP PROCEDURE gymtrack_phase3_identity_rollback;

UPDATE roles SET nombre='administrador_general' WHERE nombre='admin_general';
UPDATE roles SET nombre='dueno' WHERE nombre='dueño';
DELETE FROM schema_migrations WHERE version='003_phase3_identity_security';
