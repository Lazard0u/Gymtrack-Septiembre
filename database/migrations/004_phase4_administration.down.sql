-- Rollback controlado de la migración 004.
-- Requiere respaldo previo porque elimina únicamente historial administrativo.

DELETE FROM schema_migrations WHERE version='004_phase4_administration';
DROP TABLE IF EXISTS exports;
DROP TABLE IF EXISTS audit_logs;
