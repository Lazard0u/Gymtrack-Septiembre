<?php
/**
 * Servicio SecurityLogger. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class SecurityLogger
{
    public static function record(string $event, string $result, ?int $userId = null, array $context = []): void
    {
        try {
            $stmt = Database::conectar()->prepare(
                'INSERT INTO security_events (usuario_id, evento, resultado, ip_hash, contexto_json)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                mb_substr($event, 0, 80),
                mb_substr($result, 0, 30),
                Security::ipHash(),
                $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $error) {
            error_log('[GymTrack security log] ' . $error->getMessage());
        }
    }
}
