-- GymTrack Fase 3: identidad, autorización y sesiones seguras.
-- Idempotente para MySQL 8.0. Ejecutar únicamente después de un respaldo.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    ejecutada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE roles SET nombre = 'admin_general' WHERE nombre = 'administrador_general';
UPDATE roles SET nombre = 'dueño' WHERE nombre = 'dueno';
INSERT INTO roles (nombre) VALUES ('socio'), ('empleado'), ('dueño'), ('admin_general')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

DROP PROCEDURE IF EXISTS gymtrack_phase3_identity_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase3_identity_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='apellido') THEN
        ALTER TABLE usuarios ADD COLUMN apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='email_normalizado') THEN
        ALTER TABLE usuarios ADD COLUMN email_normalizado VARCHAR(150) NULL AFTER email;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='email_verificado_en') THEN
        ALTER TABLE usuarios ADD COLUMN email_verificado_en DATETIME NULL AFTER email_normalizado;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='debe_cambiar_password') THEN
        ALTER TABLE usuarios ADD COLUMN debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='terminos_aceptados_en') THEN
        ALTER TABLE usuarios ADD COLUMN terminos_aceptados_en DATETIME NULL AFTER fecha_nacimiento;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='privacidad_aceptada_en') THEN
        ALTER TABLE usuarios ADD COLUMN privacidad_aceptada_en DATETIME NULL AFTER terminos_aceptados_en;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='marketing_consentido_en') THEN
        ALTER TABLE usuarios ADD COLUMN marketing_consentido_en DATETIME NULL AFTER privacidad_aceptada_en;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='actualizado_en') THEN
        ALTER TABLE usuarios ADD COLUMN actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='usuarios' AND index_name='uq_usuarios_email_normalizado') THEN
        ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_email_normalizado (email_normalizado);
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase3_identity_columns();
DROP PROCEDURE gymtrack_phase3_identity_columns;

UPDATE usuarios
SET email_normalizado = LOWER(TRIM(email)),
    email_verificado_en = COALESCE(email_verificado_en, creado_en)
WHERE email_normalizado IS NULL OR email_verificado_en IS NULL;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    session_hash CHAR(64) NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    gimnasio_contexto_id INT UNSIGNED NULL,
    csrf_hash CHAR(64) NOT NULL,
    creado_en DATETIME NOT NULL,
    ultima_actividad_en DATETIME NOT NULL,
    expira_inactividad_en DATETIME NOT NULL,
    expira_absoluta_en DATETIME NOT NULL,
    revocada_en DATETIME NULL,
    motivo_revocacion VARCHAR(80) NULL,
    ip_hash CHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    dispositivo VARCHAR(120) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_sessions_public_id (public_id),
    UNIQUE KEY uq_user_sessions_hash (session_hash),
    KEY idx_user_sessions_user_active (usuario_id, revocada_en, expira_absoluta_en),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_sessions_gym FOREIGN KEY (gimnasio_contexto_id) REFERENCES gimnasios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_verification_hash (token_hash),
    KEY idx_email_verification_user (usuario_id, usado_en, expira_en),
    CONSTRAINT fk_email_verification_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_hash (token_hash),
    KEY idx_password_reset_user (usuario_id, usado_en, expira_en),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limit_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    accion VARCHAR(60) NOT NULL,
    sujeto_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    intentos INT UNSIGNED NOT NULL DEFAULT 0,
    primer_intento_en DATETIME NOT NULL,
    ultimo_intento_en DATETIME NOT NULL,
    bloqueado_hasta DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_limit_key (accion, sujeto_hash, ip_hash),
    KEY idx_rate_limit_cleanup (ultimo_intento_en, bloqueado_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    tipo ENUM('terminos','privacidad','marketing') NOT NULL,
    version_documento VARCHAR(40) NOT NULL,
    aceptado_en DATETIME NOT NULL,
    revocado_en DATETIME NULL,
    ip_hash CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_user_consents_user (usuario_id, tipo, revocado_en),
    CONSTRAINT fk_user_consents_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NULL,
    evento VARCHAR(80) NOT NULL,
    resultado VARCHAR(30) NOT NULL,
    ip_hash CHAR(64) NULL,
    contexto_json JSON NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_security_events_user_date (usuario_id, creado_en),
    KEY idx_security_events_type_date (evento, creado_en),
    CONSTRAINT fk_security_events_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_migration_report (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NULL,
    email VARCHAR(150) NOT NULL,
    rol_anterior VARCHAR(80) NOT NULL,
    rol_nuevo VARCHAR(80) NULL,
    estado ENUM('resuelto','ambiguo','ignorado') NOT NULL,
    detalle VARCHAR(255) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resuelto_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_migration_user (usuario_id),
    CONSTRAINT fk_role_migration_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuario_gimnasio_permisos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_gimnasio_rol_id INT UNSIGNED NOT NULL,
    permiso_id INT UNSIGNED NOT NULL,
    permitido TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gym_role_permission (usuario_gimnasio_rol_id, permiso_id),
    CONSTRAINT fk_gym_role_permission_assignment FOREIGN KEY (usuario_gimnasio_rol_id) REFERENCES usuario_gimnasio_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_gym_role_permission_permission FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS owner_registration_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    gimnasio_nombre VARCHAR(120) NOT NULL,
    mensaje VARCHAR(500) NULL,
    estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_owner_request_pending (usuario_id, estado),
    CONSTRAINT fk_owner_request_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permisos (codigo, descripcion) VALUES
('profile.read','Consultar el perfil propio'),
('profile.write','Actualizar el perfil propio'),
('gyms.read','Consultar gimnasios autorizados'),
('members.read','Consultar socios del gimnasio'),
('members.write','Gestionar socios del gimnasio'),
('classes.read','Consultar clases'),
('classes.write','Gestionar clases'),
('reservations.read','Consultar reservas'),
('reservations.write','Gestionar reservas'),
('attendance.write','Registrar asistencia'),
('memberships.read','Consultar membresías'),
('memberships.write','Gestionar membresías'),
('payments.read','Consultar pagos'),
('payments.manual','Registrar pagos manuales'),
('finance.read','Consultar finanzas'),
('reports.export','Exportar reportes'),
('staff.manage','Gestionar personal'),
('gym.configure','Configurar el gimnasio')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion);

DELETE rp FROM rol_permisos rp
JOIN roles r ON r.id=rp.rol_id
JOIN permisos p ON p.id=rp.permiso_id
WHERE p.codigo IN ('platform.manage','gym.manage','gym.operate','gym.view','class.reserve','profile.read','profile.write','gyms.read','members.read','members.write','classes.read','classes.write','reservations.read','reservations.write','attendance.write','memberships.read','memberships.write','payments.read','payments.manual','finance.read','reports.export','staff.manage','gym.configure');

INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p WHERE r.nombre='admin_general';
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('profile.read','profile.write','gyms.read','classes.read','reservations.read','reservations.write','memberships.read','payments.read') WHERE r.nombre='socio';
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('profile.read','profile.write','gyms.read','members.read','classes.read','classes.write','reservations.read','reservations.write','attendance.write','memberships.read','payments.read') WHERE r.nombre='empleado';
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p WHERE r.nombre='dueño';

-- Las cuentas administrativas heredadas deben renovar la contraseña tras la
-- migración. Los comandos locales de aprovisionamiento pueden dejar en cero
-- esta marca únicamente después de establecer una credencial nueva.
UPDATE usuarios u
JOIN roles r ON r.id=u.rol_id
SET u.debe_cambiar_password=1
WHERE r.nombre='admin_general' AND u.is_demo=0
  AND NOT EXISTS (
      SELECT 1 FROM schema_migrations sm
      WHERE sm.version='003_phase3_identity_security'
  );

INSERT IGNORE INTO role_migration_report (usuario_id,email,rol_anterior,rol_nuevo,estado,detalle)
SELECT u.id,u.email,
       CASE r.nombre WHEN 'admin_general' THEN 'administrador_general' WHEN 'dueño' THEN 'dueno' ELSE r.nombre END,
       r.nombre,'resuelto','Migración automática por rol conocido'
FROM usuarios u JOIN roles r ON r.id=u.rol_id;

-- También normaliza reportes creados por una ejecución anterior con una
-- configuración de cliente distinta a utf8mb4.
UPDATE role_migration_report reporte
JOIN usuarios u ON u.id=reporte.usuario_id
JOIN roles r ON r.id=u.rol_id
SET reporte.rol_nuevo=r.nombre,
    reporte.detalle='Migración automática por rol conocido';
UPDATE role_migration_report
SET rol_nuevo=CASE WHEN rol_anterior='dueno' THEN 'dueño' ELSE rol_nuevo END,
    detalle='Migración automática por rol conocido'
WHERE estado='resuelto';

INSERT IGNORE INTO schema_migrations (version) VALUES ('003_phase3_identity_security');
