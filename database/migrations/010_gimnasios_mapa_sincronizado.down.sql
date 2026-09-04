-- Reversión conservadora: elimina sólo la caché. El país y el estado inactivo se
-- conservan para no perder datos registrados después de aplicar la migración.

DROP TABLE IF EXISTS geocodificacion_cache;
DELETE FROM schema_migrations WHERE version='010_gimnasios_mapa_sincronizado';
