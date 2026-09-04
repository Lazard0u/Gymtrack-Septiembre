<?php
/**
 * Servicio Security. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class Security
{
    public static function appKey(): string
    {
        $key = (string) (getenv('APP_KEY') ?: '');
        if ($key === '' && (getenv('APP_ENV') ?: 'production') === 'production') {
            throw new RuntimeException('APP_KEY es obligatoria en producción.');
        }

        return $key !== '' ? $key : 'gymtrack-local-development-key-not-for-production';
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, self::appKey());
    }

    public static function ip(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $trustProxy = filter_var(getenv('TRUST_PROXY_HEADERS') ?: 'false', FILTER_VALIDATE_BOOL);
        if ($trustProxy) {
            // Producción sólo expone PHP detrás del Nginx de la red privada. Nginx
            // reemplaza X-Real-IP, así que nunca se acepta una cadena reenviada por
            // el cliente ni un valor que no sea una dirección IP válida.
            $proxied = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
            if (filter_var($proxied, FILTER_VALIDATE_IP) !== false) {
                return $proxied;
            }
        }

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : 'unknown';
    }

    public static function ipHash(): string
    {
        return self::hash(self::ip());
    }

    public static function randomToken(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    public static function passwordError(string $password, string $email = ''): ?string
    {
        if (strlen($password) < 12 || strlen($password) > 200) {
            return 'La contraseña debe tener entre 12 y 200 caracteres.';
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Usá al menos una mayúscula, una minúscula y un número.';
        }
        $local = explode('@', self::normalizeEmail($email))[0] ?? '';
        if (strlen($local) >= 4 && str_contains(mb_strtolower($password), $local)) {
            return 'La contraseña no puede contener tu correo electrónico.';
        }

        return null;
    }
}
