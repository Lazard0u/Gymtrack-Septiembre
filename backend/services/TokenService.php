<?php

declare(strict_types=1);

/**
 * Emite y consume credenciales temporales de un solo uso.
 *
 * El navegador recibe el token en texto plano una sola vez. La base de datos
 * conserva únicamente SHA-256(token), por lo que una lectura accidental de la
 * tabla no alcanza para reconstruir enlaces de verificación o recuperación.
 */
final class TokenService
{
    private PDO $pdo;

    public function __construct()
    {
        // Se reutiliza la conexión central para mantener transacciones y charset.
        $this->pdo = Database::conectar();
    }

    /**
     * Crea un candidato de verificación sin invalidar todavía enlaces anteriores.
     *
     * AuthController confirma el envío antes de llamar a confirmEmailDelivery().
     * Así, un fallo del proveedor no deja al usuario sin su enlace anterior.
     */
    public function issueEmailVerification(int $userId): string
    {
        return $this->issue('email_verification_tokens', $userId, $this->emailVerificationTtlSeconds());
    }

    /** Conserva el token entregado e invalida candidatos anteriores. */
    public function confirmEmailDelivery(int $userId, string $plainToken): void
    {
        $hash = hash('sha256', $plainToken);
        $stmt = $this->pdo->prepare(
            'UPDATE email_verification_tokens
             SET usado_en=COALESCE(usado_en,NOW())
             WHERE usuario_id=? AND token_hash<>? AND usado_en IS NULL'
        );
        $stmt->execute([$userId, $hash]);
    }

    /** Descarta sólo el candidato cuyo correo no pudo entregarse. */
    public function discardEmailVerification(string $plainToken): void
    {
        if (!$this->validTokenShape($plainToken)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE email_verification_tokens
             SET usado_en=COALESCE(usado_en,NOW())
             WHERE token_hash=?'
        );
        $stmt->execute([hash('sha256', $plainToken)]);
    }

    /**
     * Verifica correo y consume el token dentro de una sola transacción.
     *
     * El bloqueo FOR UPDATE evita que dos pestañas puedan consumir el mismo
     * enlace al mismo tiempo. El estado devuelto permite explicar si el enlace
     * venció, ya se usó o simplemente no existe.
     *
     * @return array{status:string,user_id:?int}
     */
    public function verifyEmail(string $plainToken): array
    {
        if (!$this->validTokenShape($plainToken)) {
            return ['status' => 'invalid', 'user_id' => null];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT evt.id,evt.usuario_id,evt.expira_en,evt.usado_en,u.email_verificado_en
                 FROM email_verification_tokens evt
                 JOIN usuarios u ON u.id=evt.usuario_id
                 WHERE evt.token_hash=?
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([hash('sha256', $plainToken)]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->pdo->rollBack();
                return ['status' => 'invalid', 'user_id' => null];
            }

            $userId = (int) $row['usuario_id'];
            if ($row['usado_en'] !== null) {
                $this->pdo->rollBack();
                return ['status' => 'used', 'user_id' => $userId];
            }
            if (strtotime((string) $row['expira_en']) <= time()) {
                $this->pdo->rollBack();
                return ['status' => 'expired', 'user_id' => $userId];
            }

            // La cuenta puede haberse verificado desde otra pestaña o enlace.
            if ($row['email_verificado_en'] !== null) {
                $this->invalidateEmailTokens($userId);
                $this->pdo->commit();
                return ['status' => 'already_verified', 'user_id' => $userId];
            }

            $this->pdo->prepare('UPDATE usuarios SET email_verificado_en=NOW() WHERE id=?')->execute([$userId]);
            $this->invalidateEmailTokens($userId);
            $this->pdo->commit();

            return ['status' => 'verified', 'user_id' => $userId];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** La recuperación conserva únicamente el enlace más reciente. */
    public function issuePasswordReset(int $userId): string
    {
        $this->pdo->prepare(
            'UPDATE password_reset_tokens
             SET usado_en=COALESCE(usado_en,NOW())
             WHERE usuario_id=? AND usado_en IS NULL'
        )->execute([$userId]);

        return $this->issue('password_reset_tokens', $userId, 3600);
    }

    /** Consumo genérico utilizado por recuperación de contraseña. */
    public function consume(string $table, string $plainToken): ?int
    {
        $allowedTables = ['email_verification_tokens', 'password_reset_tokens'];
        if (!in_array($table, $allowedTables, true) || !$this->validTokenShape($plainToken)) {
            return null;
        }

        $this->pdo->beginTransaction();
        try {
            // El nombre de tabla no viene del cliente: se validó contra una lista cerrada.
            $stmt = $this->pdo->prepare(
                "SELECT id,usuario_id FROM {$table}
                 WHERE token_hash=? AND usado_en IS NULL AND expira_en>NOW()
                 FOR UPDATE"
            );
            $stmt->execute([hash('sha256', $plainToken)]);
            $row = $stmt->fetch();
            if (!$row) {
                $this->pdo->rollBack();
                return null;
            }

            $this->pdo->prepare("UPDATE {$table} SET usado_en=NOW() WHERE id=? AND usado_en IS NULL")
                ->execute([(int) $row['id']]);
            $this->pdo->commit();
            return (int) $row['usuario_id'];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** El mantenimiento elimina tokens antiguos ya inútiles. */
    public function cleanup(): int
    {
        $total = 0;
        foreach (['email_verification_tokens', 'password_reset_tokens'] as $table) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$table}
                 WHERE expira_en<DATE_SUB(NOW(),INTERVAL 1 DAY)
                    OR usado_en<DATE_SUB(NOW(),INTERVAL 7 DAY)"
            );
            $stmt->execute();
            $total += $stmt->rowCount();
        }
        return $total;
    }

    /** Devuelve el vencimiento configurable usado también por la plantilla. */
    public function emailVerificationTtlSeconds(): int
    {
        $configured = (int) (getenv('EMAIL_VERIFICATION_TTL_SECONDS') ?: 86400);
        return max(300, min(604800, $configured));
    }

    /** Inserta el hash y devuelve sólo el secreto que debe viajar por correo. */
    private function issue(string $table, int $userId, int $ttl): string
    {
        $plain = Security::randomToken(48);
        $hash = hash('sha256', $plain);
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$table}(usuario_id,token_hash,creado_en,expira_en)
             VALUES(?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? SECOND))"
        );
        $stmt->execute([$userId, $hash, $ttl]);
        return $plain;
    }

    /** Invalida todos los enlaces pendientes después de confirmar la cuenta. */
    private function invalidateEmailTokens(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE email_verification_tokens
             SET usado_en=COALESCE(usado_en,NOW())
             WHERE usuario_id=? AND usado_en IS NULL'
        );
        $stmt->execute([$userId]);
    }

    /** Rechaza entradas absurdas antes de consultar la base. */
    private function validTokenShape(string $token): bool
    {
        $length = strlen($token);
        return $length >= 32 && $length <= 160 && preg_match('/^[A-Za-z0-9_-]+$/', $token) === 1;
    }
}
