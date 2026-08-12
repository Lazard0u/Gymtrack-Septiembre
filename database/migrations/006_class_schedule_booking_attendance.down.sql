-- Rollback destructivo de agenda fechada. Requiere respaldo y ventana de mantenimiento.
SET FOREIGN_KEY_CHECKS=0;

UPDATE reservas SET estado='cancelada' WHERE estado IN ('lista_espera','no_asistio');
ALTER TABLE reservas MODIFY COLUMN estado ENUM('confirmada','cancelada','asistio') NOT NULL DEFAULT 'confirmada';

ALTER TABLE asistencias DROP FOREIGN KEY fk_asistencia_actor;
ALTER TABLE asistencias ADD KEY idx_asistencia_reserva_fk (reserva_id);
ALTER TABLE asistencias DROP INDEX uq_asistencia_reserva;
ALTER TABLE asistencias DROP COLUMN actualizado_en, DROP COLUMN notas, DROP COLUMN registrada_por, DROP COLUMN estado;

DROP TABLE IF EXISTS reserva_eventos;
ALTER TABLE reservas DROP FOREIGN KEY fk_reserva_sesion;
ALTER TABLE reservas DROP INDEX idx_reserva_sesion_estado;
ALTER TABLE reservas DROP INDEX uq_reserva_idempotency;
ALTER TABLE reservas DROP INDEX uq_reserva_usuario_sesion;
ALTER TABLE reservas ADD UNIQUE KEY uq_reservas_usuario_clase (usuario_id,clase_id);
ALTER TABLE reservas DROP COLUMN actualizado_en, DROP COLUMN cancelada_en, DROP COLUMN confirmada_en,
    DROP COLUMN idempotency_key, DROP COLUMN origen, DROP COLUMN posicion_espera, DROP COLUMN sesion_clase_id;

DROP TABLE IF EXISTS sesiones_clase;
ALTER TABLE clases DROP FOREIGN KEY fk_clases_sede;
ALTER TABLE clases DROP COLUMN actualizado_en, DROP COLUMN cancelacion_minutos, DROP COLUMN color, DROP COLUMN sede_id, DROP COLUMN descripcion;

DELETE FROM schema_migrations WHERE version='006_class_schedule_booking_attendance';
SET FOREIGN_KEY_CHECKS=1;
