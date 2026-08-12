-- GymTrack: agenda fechada, cupos, lista de espera y asistencia.
-- Idempotente para MySQL 8.0. Ejecutar después de 005 y de un respaldo verificado.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    ejecutada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS gymtrack_schedule_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_schedule_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='clases' AND column_name='descripcion') THEN
        ALTER TABLE clases ADD COLUMN descripcion VARCHAR(600) NULL AFTER nombre;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='clases' AND column_name='sede_id') THEN
        ALTER TABLE clases ADD COLUMN sede_id BIGINT UNSIGNED NULL AFTER gimnasio_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='clases' AND column_name='color') THEN
        ALTER TABLE clases ADD COLUMN color CHAR(7) NOT NULL DEFAULT '#0D7A56' AFTER cupos_disponibles;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='clases' AND column_name='cancelacion_minutos') THEN
        ALTER TABLE clases ADD COLUMN cancelacion_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 120 AFTER color;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='clases' AND column_name='actualizado_en') THEN
        ALTER TABLE clases ADD COLUMN actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='clases' AND constraint_name='fk_clases_sede') THEN
        ALTER TABLE clases ADD CONSTRAINT fk_clases_sede FOREIGN KEY (sede_id) REFERENCES gimnasio_sedes(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_schedule_columns();
DROP PROCEDURE gymtrack_schedule_columns;

UPDATE clases c
JOIN gimnasio_sedes s ON s.gimnasio_id=c.gimnasio_id AND s.es_principal=1
SET c.sede_id=s.id
WHERE c.sede_id IS NULL;

CREATE TABLE IF NOT EXISTS sesiones_clase (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clase_id INT UNSIGNED NOT NULL,
    gimnasio_id INT UNSIGNED NOT NULL,
    sede_id BIGINT UNSIGNED NULL,
    instructor_id INT UNSIGNED NOT NULL,
    inicio_en DATETIME NOT NULL,
    fin_en DATETIME NOT NULL,
    zona_horaria VARCHAR(80) NOT NULL DEFAULT 'America/Montevideo',
    cupo_maximo SMALLINT UNSIGNED NOT NULL,
    cupos_reservados SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    espera_total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    estado ENUM('programada','cancelada','finalizada') NOT NULL DEFAULT 'programada',
    motivo_cancelacion VARCHAR(255) NULL,
    notas VARCHAR(600) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    demo_dataset_id INT UNSIGNED NULL,
    creado_por INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sesion_clase_inicio (clase_id,inicio_en),
    KEY idx_sesion_gimnasio_inicio (gimnasio_id,inicio_en,estado),
    KEY idx_sesion_instructor_inicio (instructor_id,inicio_en),
    KEY idx_sesion_demo (is_demo,demo_dataset_id),
    CONSTRAINT fk_sesion_clase FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sesion_gimnasio FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sesion_sede FOREIGN KEY (sede_id) REFERENCES gimnasio_sedes(id) ON DELETE SET NULL,
    CONSTRAINT fk_sesion_instructor FOREIGN KEY (instructor_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sesion_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_sesion_demo FOREIGN KEY (demo_dataset_id) REFERENCES demo_datasets(id) ON DELETE RESTRICT,
    CONSTRAINT chk_sesion_horario CHECK (fin_en > inicio_en),
    CONSTRAINT chk_sesion_cupo CHECK (cupo_maximo BETWEEN 1 AND 500),
    CONSTRAINT chk_sesion_contadores CHECK (cupos_reservados <= cupo_maximo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Convierte cada definición semanal existente en una próxima sesión concreta.
INSERT IGNORE INTO sesiones_clase
    (clase_id,gimnasio_id,sede_id,instructor_id,inicio_en,fin_en,zona_horaria,cupo_maximo,cupos_reservados,espera_total,estado,is_demo,demo_dataset_id)
SELECT c.id,c.gimnasio_id,c.sede_id,c.instructor_id,
       TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL IF(MOD(
           FIELD(c.dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado','domingo') - WEEKDAY(CURRENT_DATE) - 1 + 7, 7)=0,7,MOD(
           FIELD(c.dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado','domingo') - WEEKDAY(CURRENT_DATE) - 1 + 7, 7)) DAY),c.hora_inicio),
       TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL IF(MOD(
           FIELD(c.dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado','domingo') - WEEKDAY(CURRENT_DATE) - 1 + 7, 7)=0,7,MOD(
           FIELD(c.dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado','domingo') - WEEKDAY(CURRENT_DATE) - 1 + 7, 7)) DAY),c.hora_fin),
       COALESCE(g.zona_horaria,'America/Montevideo'),c.cupo_maximo,
       GREATEST(0,c.cupo_maximo-c.cupos_disponibles),0,IF(c.activa=1,'programada','cancelada'),c.is_demo,c.demo_dataset_id
FROM clases c JOIN gimnasios g ON g.id=c.gimnasio_id
WHERE c.gimnasio_id IS NOT NULL;

DROP PROCEDURE IF EXISTS gymtrack_booking_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_booking_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas' AND column_name='sesion_clase_id') THEN
        ALTER TABLE reservas ADD COLUMN sesion_clase_id BIGINT UNSIGNED NULL AFTER clase_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas' AND column_name='posicion_espera') THEN
        ALTER TABLE reservas ADD COLUMN posicion_espera SMALLINT UNSIGNED NULL AFTER estado;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas' AND column_name='origen') THEN
        ALTER TABLE reservas ADD COLUMN origen ENUM('socio','administracion','sistema') NOT NULL DEFAULT 'socio' AFTER posicion_espera;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas' AND column_name='idempotency_key') THEN
        ALTER TABLE reservas ADD COLUMN idempotency_key CHAR(36) NULL AFTER origen;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas' AND column_name='confirmada_en') THEN
        ALTER TABLE reservas ADD COLUMN confirmada_en DATETIME NULL AFTER fecha_reserva;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas' AND column_name='cancelada_en') THEN
        ALTER TABLE reservas ADD COLUMN cancelada_en DATETIME NULL AFTER confirmada_en;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas' AND column_name='actualizado_en') THEN
        ALTER TABLE reservas ADD COLUMN actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;
    ALTER TABLE reservas MODIFY COLUMN estado ENUM('confirmada','lista_espera','cancelada','asistio','no_asistio') NOT NULL DEFAULT 'confirmada';
END$$
DELIMITER ;
CALL gymtrack_booking_columns();
DROP PROCEDURE gymtrack_booking_columns;

UPDATE reservas r
JOIN sesiones_clase s ON s.clase_id=r.clase_id
SET r.sesion_clase_id=s.id,
    r.confirmada_en=IF(r.estado IN ('confirmada','asistio'),COALESCE(r.confirmada_en,r.fecha_reserva),r.confirmada_en),
    r.cancelada_en=IF(r.estado='cancelada',COALESCE(r.cancelada_en,r.fecha_reserva),r.cancelada_en)
WHERE r.sesion_clase_id IS NULL;

DROP PROCEDURE IF EXISTS gymtrack_booking_indexes;
DELIMITER $$
CREATE PROCEDURE gymtrack_booking_indexes()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='reservas' AND index_name='uq_reservas_usuario_clase') THEN
        ALTER TABLE reservas DROP INDEX uq_reservas_usuario_clase;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='reservas' AND index_name='uq_reserva_usuario_sesion') THEN
        ALTER TABLE reservas ADD UNIQUE KEY uq_reserva_usuario_sesion (usuario_id,sesion_clase_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='reservas' AND index_name='uq_reserva_idempotency') THEN
        ALTER TABLE reservas ADD UNIQUE KEY uq_reserva_idempotency (usuario_id,idempotency_key);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='reservas' AND index_name='idx_reserva_sesion_estado') THEN
        ALTER TABLE reservas ADD KEY idx_reserva_sesion_estado (sesion_clase_id,estado,posicion_espera);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='reservas' AND constraint_name='fk_reserva_sesion') THEN
        ALTER TABLE reservas ADD CONSTRAINT fk_reserva_sesion FOREIGN KEY (sesion_clase_id) REFERENCES sesiones_clase(id) ON DELETE RESTRICT;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_booking_indexes();
DROP PROCEDURE gymtrack_booking_indexes;

CREATE TABLE IF NOT EXISTS reserva_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reserva_id INT UNSIGNED NOT NULL,
    tipo ENUM('creada','espera','promovida','cancelada','asistencia') NOT NULL,
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NOT NULL,
    actor_usuario_id INT UNSIGNED NULL,
    motivo VARCHAR(255) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reserva_evento (reserva_id,creado_en),
    CONSTRAINT fk_reserva_evento_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT fk_reserva_evento_actor FOREIGN KEY (actor_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO reserva_eventos (reserva_id,tipo,estado_anterior,estado_nuevo,motivo)
SELECT r.id,'creada',NULL,r.estado,'Importación de reserva existente'
FROM reservas r WHERE NOT EXISTS (SELECT 1 FROM reserva_eventos e WHERE e.reserva_id=r.id);

DROP PROCEDURE IF EXISTS gymtrack_attendance_columns;
DELIMITER $$
CREATE PROCEDURE gymtrack_attendance_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='asistencias' AND column_name='estado') THEN
        ALTER TABLE asistencias ADD COLUMN estado ENUM('presente','ausente','tarde','justificada') NOT NULL DEFAULT 'presente' AFTER reserva_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='asistencias' AND column_name='registrada_por') THEN
        ALTER TABLE asistencias ADD COLUMN registrada_por INT UNSIGNED NULL AFTER fecha_asist;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='asistencias' AND column_name='notas') THEN
        ALTER TABLE asistencias ADD COLUMN notas VARCHAR(255) NULL AFTER registrada_por;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='asistencias' AND column_name='actualizado_en') THEN
        ALTER TABLE asistencias ADD COLUMN actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='asistencias' AND index_name='uq_asistencia_reserva') THEN
        ALTER TABLE asistencias ADD UNIQUE KEY uq_asistencia_reserva (reserva_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='asistencias' AND constraint_name='fk_asistencia_actor') THEN
        ALTER TABLE asistencias ADD CONSTRAINT fk_asistencia_actor FOREIGN KEY (registrada_por) REFERENCES usuarios(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_attendance_columns();
DROP PROCEDURE gymtrack_attendance_columns;

-- Recalcula contadores derivados para que los datos migrados sean coherentes.
UPDATE sesiones_clase s
SET s.cupos_reservados=(SELECT COUNT(*) FROM reservas r WHERE r.sesion_clase_id=s.id AND r.estado IN ('confirmada','asistio','no_asistio')),
    s.espera_total=(SELECT COUNT(*) FROM reservas r WHERE r.sesion_clase_id=s.id AND r.estado='lista_espera');

INSERT IGNORE INTO schema_migrations (version) VALUES ('006_class_schedule_booking_attendance');
