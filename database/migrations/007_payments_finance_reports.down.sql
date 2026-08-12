-- Rollback destructivo de pagos avanzados y reportes. Requiere respaldo.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS pago_reembolsos;
DROP TABLE IF EXISTS pago_eventos;
UPDATE membresias SET estado='suspendida' WHERE estado='pendiente_pago';
ALTER TABLE membresias MODIFY COLUMN estado ENUM('activa','vencida','suspendida') NOT NULL DEFAULT 'activa';
ALTER TABLE exports DROP COLUMN tamano_bytes, DROP COLUMN mime_type, DROP COLUMN archivo_nombre;
UPDATE pagos SET estado='aprobado' WHERE estado<>'aprobado';
UPDATE pagos SET metodo='tarjeta' WHERE metodo NOT IN ('efectivo','transferencia','tarjeta');
ALTER TABLE pagos DROP FOREIGN KEY fk_pago_creador, DROP FOREIGN KEY fk_pago_demo, DROP FOREIGN KEY fk_pago_gimnasio;
ALTER TABLE pagos DROP INDEX idx_pago_demo, DROP INDEX idx_pago_gym_status_date, DROP INDEX uq_pago_idempotency, DROP INDEX uq_pago_referencia;
ALTER TABLE pagos DROP COLUMN actualizado_en, DROP COLUMN creado_en, DROP COLUMN creado_por,
    DROP COLUMN demo_dataset_id, DROP COLUMN is_demo, DROP COLUMN metadata_json,
    DROP COLUMN reembolsado_en, DROP COLUMN rechazado_en, DROP COLUMN aprobado_en,
    DROP COLUMN fecha_vencimiento, DROP COLUMN concepto, DROP COLUMN modo,
    DROP COLUMN idempotency_key, DROP COLUMN preferencia_externa, DROP COLUMN referencia_externa,
    DROP COLUMN proveedor, DROP COLUMN estado, DROP COLUMN moneda, DROP COLUMN gimnasio_id;
ALTER TABLE pagos MODIFY COLUMN monto DECIMAL(10,2) NOT NULL;
ALTER TABLE pagos MODIFY COLUMN metodo ENUM('efectivo','transferencia','tarjeta') NOT NULL;
UPDATE pagos SET fecha_pago=CURRENT_TIMESTAMP WHERE fecha_pago IS NULL;
ALTER TABLE pagos MODIFY COLUMN fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
DELETE FROM schema_migrations WHERE version='007_payments_finance_reports';
SET FOREIGN_KEY_CHECKS=1;
