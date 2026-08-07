-- Rollback controlado de Fase 5. Destructivo: requiere respaldo y ventana de mantenimiento.
SET FOREIGN_KEY_CHECKS=0;
ALTER TABLE membresias DROP FOREIGN KEY fk_membresia_plan;
ALTER TABLE membresias DROP INDEX idx_membresias_numero_socio;
ALTER TABLE membresias DROP COLUMN actualizado_en, DROP COLUMN numero_socio, DROP COLUMN plan_id;
ALTER TABLE gimnasios DROP COLUMN archivado_en, DROP COLUMN verificacion_estado, DROP COLUMN nombre_legal;
DROP TABLE IF EXISTS archivos, invitaciones_gimnasio, membresia_historial, entrenador_gimnasios,
    entrenador_perfiles, empleado_perfiles, socio_perfiles, planes_membresia, gimnasio_sedes;
DELETE FROM schema_migrations WHERE version='005_phase5_tenant_operations';
SET FOREIGN_KEY_CHECKS=1;
