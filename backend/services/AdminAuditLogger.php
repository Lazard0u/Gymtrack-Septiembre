<?php
/**
 * Servicio AdminAuditLogger. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class AdminAuditLogger
{
    public static function record(
        string $action,
        string $entity,
        string $result,
        ?int $userId = null,
        ?int $gymId = null,
        ?string $entityId = null,
        ?string $reason = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        try {
            $stmt = Database::conectar()->prepare(
                'INSERT INTO audit_logs
                 (usuario_id,gimnasio_id,accion,entidad,entidad_id,request_id,resultado,motivo,before_json,after_json,ip_hash,is_demo,demo_dataset_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $userId,
                $gymId,
                mb_substr($action, 0, 100),
                mb_substr($entity, 0, 80),
                $entityId === null ? null : mb_substr($entityId, 0, 80),
                ApiResponder::requestId(),
                in_array($result, ['success', 'denied', 'failed'], true) ? $result : 'failed',
                $reason === null ? null : mb_substr($reason, 0, 255),
                self::json($before),
                self::json($after),
                Security::ipHash(),
                AuthMiddleware::esCuentaDemo() ? 1 : 0,
                AuthMiddleware::obtenerDemoDatasetId(),
            ]);
        } catch (Throwable $error) {
            error_log('[GymTrack audit] No se pudo registrar el evento: ' . $error->getMessage());
        }
    }

    private static function json(?array $value): ?string
    {
        if ($value === null) return null;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
