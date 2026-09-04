-- Rollback destructivo de la migración 009. Requiere respaldo de fotos, favoritos y mediciones.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DELETE FROM schema_migrations WHERE version='009_phase10_member_experience';

DROP TABLE IF EXISTS socio_mediciones;
DROP TABLE IF EXISTS socio_actividades_favoritas;
DROP TABLE IF EXISTS socio_gimnasios_favoritos;
DROP TABLE IF EXISTS actividades;
DROP TABLE IF EXISTS socio_preferencias;

DROP PROCEDURE IF EXISTS gymtrack_phase10_user_photo_down;
DELIMITER $$
CREATE PROCEDURE gymtrack_phase10_user_photo_down()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema=DATABASE() AND table_name='usuarios' AND constraint_name='fk_usuario_foto'
    ) THEN
        ALTER TABLE usuarios DROP FOREIGN KEY fk_usuario_foto;
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema=DATABASE() AND table_name='usuarios' AND index_name='idx_usuario_foto'
    ) THEN
        ALTER TABLE usuarios DROP INDEX idx_usuario_foto;
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='foto_archivo_id'
    ) THEN
        ALTER TABLE usuarios DROP COLUMN foto_archivo_id;
    END IF;
END$$
DELIMITER ;
CALL gymtrack_phase10_user_photo_down();
DROP PROCEDURE gymtrack_phase10_user_photo_down;

-- Conserva los metadatos de archivos ya cargados aunque la categoria especifica desaparezca.
UPDATE archivos SET categoria='documento' WHERE categoria='socio_avatar';
ALTER TABLE archivos
    MODIFY COLUMN categoria ENUM('gimnasio_imagen','entrenador_foto','promocion_imagen','documento') NOT NULL;
