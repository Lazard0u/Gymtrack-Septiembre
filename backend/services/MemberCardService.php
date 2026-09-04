<?php

declare(strict_types=1);

/**
 * Emite un token breve para el carne digital. El payload contiene solamente
 * identificadores tecnicos y scope; nombre, correo, telefono y numero de socio
 * se resuelven del lado servidor despues de verificar firma y tenant.
 */
final class MemberCardService
{
    private const AUDIENCE = 'gymtrack-member-card';
    private const TTL_SECONDS = 300;

    private string $secret;
    private int $ttl;

    public function __construct(?string $secret = null, int $ttl = self::TTL_SECONDS)
    {
        $this->secret = $secret ?? (string) (getenv('APP_KEY') ?: '');
        if (strlen($this->secret) < 32) {
            throw new RuntimeException('APP_KEY debe tener al menos 32 caracteres para firmar el carne.');
        }
        $this->ttl = max(60, min($ttl, 900));
    }

    public function issue(int $userId, int $gymId, ?int $membershipId, bool $isDemo, ?int $datasetId): array
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->ttl;
        $claims = [
            'iss' => 'gymtrack',
            'aud' => self::AUDIENCE,
            'sub' => $userId,
            'gym' => $gymId,
            'membership' => $membershipId,
            'demo' => $isDemo,
            'dataset' => $datasetId,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $header = ['alg' => 'HS256', 'typ' => 'GTMC', 'v' => 1];
        $encodedHeader = $this->encodeJson($header);
        $encodedClaims = $this->encodeJson($claims);
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedClaims, $this->secret, true));

        return [
            'token' => $encodedHeader . '.' . $encodedClaims . '.' . $signature,
            'expires_at' => gmdate('c', $expiresAt),
            'ttl_seconds' => $this->ttl,
        ];
    }

    public function verify(string $token): array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new InvalidArgumentException('El carne no tiene un formato valido.');
        }
        [$encodedHeader, $encodedClaims, $providedSignature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedClaims, $this->secret, true));
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidArgumentException('La firma del carne no es valida.');
        }

        $header = $this->decodeJson($encodedHeader);
        $claims = $this->decodeJson($encodedClaims);
        if (($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'GTMC' || (int) ($header['v'] ?? 0) !== 1) {
            throw new InvalidArgumentException('El formato del carne no esta soportado.');
        }
        if (($claims['iss'] ?? null) !== 'gymtrack' || ($claims['aud'] ?? null) !== self::AUDIENCE) {
            throw new InvalidArgumentException('El carne no pertenece a GymTrack.');
        }

        $now = time();
        $issuedAt = filter_var($claims['iat'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT);
        $userId = filter_var($claims['sub'] ?? null, FILTER_VALIDATE_INT);
        $gymId = filter_var($claims['gym'] ?? null, FILTER_VALIDATE_INT);
        if ($issuedAt === false || $expiresAt === false || $userId === false || $gymId === false
            || $userId < 1 || $gymId < 1 || $issuedAt > $now + 30 || $expiresAt <= $now || $expiresAt - $issuedAt > 900) {
            throw new InvalidArgumentException('El carne vencio o contiene datos invalidos.');
        }

        $membershipId = $claims['membership'] ?? null;
        if ($membershipId !== null) {
            $membershipId = filter_var($membershipId, FILTER_VALIDATE_INT);
            if ($membershipId === false || $membershipId < 1) {
                throw new InvalidArgumentException('La membresia del carne no es valida.');
            }
        }
        $datasetId = $claims['dataset'] ?? null;
        if ($datasetId !== null) {
            $datasetId = filter_var($datasetId, FILTER_VALIDATE_INT);
            if ($datasetId === false || $datasetId < 1) {
                throw new InvalidArgumentException('El scope del carne no es valido.');
            }
        }

        return [
            'user_id' => (int) $userId,
            'gym_id' => (int) $gymId,
            'membership_id' => $membershipId === null ? null : (int) $membershipId,
            'is_demo' => (bool) ($claims['demo'] ?? false),
            'dataset_id' => $datasetId === null ? null : (int) $datasetId,
            'issued_at' => (int) $issuedAt,
            'expires_at' => (int) $expiresAt,
            'jti' => (string) ($claims['jti'] ?? ''),
        ];
    }

    private function encodeJson(array $value): string
    {
        return $this->base64UrlEncode(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function decodeJson(string $value): array
    {
        $decoded = $this->base64UrlDecode($value);
        $json = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($json)) {
            throw new InvalidArgumentException('El carne no contiene un objeto valido.');
        }
        return $json;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException('El carne contiene caracteres invalidos.');
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('El carne no se pudo decodificar.');
        }
        return $decoded;
    }
}
