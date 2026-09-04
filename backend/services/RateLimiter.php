<?php
/**
 * Servicio RateLimiter. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class RateLimiter
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    /** Rechaza la petición si la combinación acción/sujeto/IP sigue bloqueada. */
    public function ensureAllowed(string $action, string $subject): void
    {
        $row = $this->find($action, $subject);
        if ($row && $row['bloqueado_hasta'] !== null && strtotime($row['bloqueado_hasta']) > time()) {
            $seconds = max(1, strtotime($row['bloqueado_hasta']) - time());
            header('Retry-After: ' . $seconds);
            $this->deny('Demasiados intentos. Esperá unos minutos y volvé a probar.');
        }
    }

    /**
     * Registra un intento. Desde el quinto aplica una espera exponencial con
     * un máximo de quince minutos para frenar automatización sin bloquear días.
     */
    public function fail(string $action, string $subject): void
    {
        $subjectHash = Security::hash(mb_strtolower(trim($subject)));
        $ipHash = Security::ipHash();
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limit_attempts
                (accion, sujeto_hash, ip_hash, intentos, primer_intento_en, ultimo_intento_en, bloqueado_hasta)
             VALUES (?, ?, ?, 1, NOW(), NOW(), NULL)
             ON DUPLICATE KEY UPDATE
                bloqueado_hasta = CASE
                    WHEN intentos + 1 >= 5 THEN DATE_ADD(
                        NOW(),
                        INTERVAL LEAST(900, 30 * POW(2, LEAST(5, intentos + 1 - 5))) SECOND
                    )
                    ELSE bloqueado_hasta
                END,
                intentos = intentos + 1,
                ultimo_intento_en = NOW()'
        );
        $stmt->execute([$action, $subjectHash, $ipHash]);
    }

    /** Borra el contador después de una autenticación correcta. */
    public function clear(string $action, string $subject): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limit_attempts WHERE accion=? AND sujeto_hash=? AND ip_hash=?');
        $stmt->execute([$action, Security::hash(mb_strtolower(trim($subject))), Security::ipHash()]);
    }

    /** El mantenimiento elimina filas antiguas para mantener pequeña la tabla. */
    public function cleanup(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limit_attempts WHERE ultimo_intento_en < DATE_SUB(NOW(), INTERVAL 2 DAY)');
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** Busca usando hashes HMAC; correo e IP no se almacenan en claro. */
    private function find(string $action, string $subject): array|false
    {
        $stmt = $this->pdo->prepare('SELECT intentos, bloqueado_hasta FROM rate_limit_attempts WHERE accion=? AND sujeto_hash=? AND ip_hash=? LIMIT 1');
        $stmt->execute([$action, Security::hash(mb_strtolower(trim($subject))), Security::ipHash()]);
        return $stmt->fetch();
    }

    /** Retry-After permite que clientes accesibles sepan cuándo reintentar. */
    private function deny(string $message): never
    {
        http_response_code(429);
        echo json_encode(['error' => true, 'mensaje' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
