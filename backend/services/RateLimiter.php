<?php

declare(strict_types=1);

final class RateLimiter
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function ensureAllowed(string $action, string $subject): void
    {
        $row = $this->find($action, $subject);
        if ($row && $row['bloqueado_hasta'] !== null && strtotime($row['bloqueado_hasta']) > time()) {
            $seconds = max(1, strtotime($row['bloqueado_hasta']) - time());
            header('Retry-After: ' . $seconds);
            $this->deny('Demasiados intentos. Esperá unos minutos y volvé a probar.');
        }
    }

    public function fail(string $action, string $subject): void
    {
        $subjectHash = Security::hash(mb_strtolower(trim($subject)));
        $ipHash = Security::ipHash();
        $row = $this->find($action, $subject);
        $attempts = (int) ($row['intentos'] ?? 0) + 1;
        $blockedUntil = null;
        if ($attempts >= 5) {
            $seconds = min(900, 30 * (2 ** min(5, $attempts - 5)));
            $blockedUntil = date('Y-m-d H:i:s', time() + $seconds);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limit_attempts
                (accion, sujeto_hash, ip_hash, intentos, primer_intento_en, ultimo_intento_en, bloqueado_hasta)
             VALUES (?, ?, ?, ?, NOW(), NOW(), ?)
             ON DUPLICATE KEY UPDATE intentos=VALUES(intentos), ultimo_intento_en=NOW(), bloqueado_hasta=VALUES(bloqueado_hasta)'
        );
        $stmt->execute([$action, $subjectHash, $ipHash, $attempts, $blockedUntil]);
    }

    public function clear(string $action, string $subject): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limit_attempts WHERE accion=? AND sujeto_hash=? AND ip_hash=?');
        $stmt->execute([$action, Security::hash(mb_strtolower(trim($subject))), Security::ipHash()]);
    }

    public function cleanup(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limit_attempts WHERE ultimo_intento_en < DATE_SUB(NOW(), INTERVAL 2 DAY)');
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function find(string $action, string $subject): array|false
    {
        $stmt = $this->pdo->prepare('SELECT intentos, bloqueado_hasta FROM rate_limit_attempts WHERE accion=? AND sujeto_hash=? AND ip_hash=? LIMIT 1');
        $stmt->execute([$action, Security::hash(mb_strtolower(trim($subject))), Security::ipHash()]);
        return $stmt->fetch();
    }

    private function deny(string $message): never
    {
        http_response_code(429);
        echo json_encode(['error' => true, 'mensaje' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
