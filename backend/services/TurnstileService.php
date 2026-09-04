<?php
/**
 * Servicio TurnstileService. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class TurnstileService
{
    public function enabled(): bool
    {
        return filter_var(getenv('TURNSTILE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    public function assertConfiguration(): void
    {
        if ((getenv('APP_ENV') ?: 'production') === 'production'
            && (!$this->enabled() || trim((string) getenv('TURNSTILE_SECRET_KEY')) === '')) {
            throw new RuntimeException('Turnstile debe estar habilitado y configurado en producción.');
        }
    }

    public function verify(string $token): bool
    {
        $this->assertConfiguration();
        if (!$this->enabled()) {
            return (getenv('APP_ENV') ?: 'production') !== 'production';
        }
        $secret = trim((string) getenv('TURNSTILE_SECRET_KEY'));
        if ($secret === '' || trim($token) === '') {
            return false;
        }
        $url = (string) (getenv('TURNSTILE_VERIFY_URL') ?: 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
        $payload = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => Security::ip()]);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return false;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) && ($decoded['success'] ?? false) === true;
    }
}
