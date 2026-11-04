<?php
/**
 * Formato de respuesta de los controladores nuevos: {ok, error, data, meta, request_id}.
 */

declare(strict_types=1);

final class ApiResponder
{
    public static function requestId(): string
    {
        return (string) ($GLOBALS['gymtrack_request_id'] ?? '');
    }

    public static function success(array $data = [], int $status = 200, array $meta = []): never
    {
        self::send($status, [
            'ok' => true,
            'error' => false,
            'data' => $data,
            'meta' => $meta,
            'request_id' => self::requestId(),
        ]);
    }

    public static function error(int $status, string $code, string $message, array $fields = []): never
    {
        self::send($status, [
            'ok' => false,
            'error' => true,
            'codigo' => $code,
            'mensaje' => $message,
            'fields' => (object) $fields,
            'request_id' => self::requestId(),
        ]);
    }

    private static function send(int $status, array $payload): never
    {
        // Apache convierte algunos códigos no registrados por PHP, como 419,
        // en 500 si sólo se usa http_response_code(). La línea explícita conserva
        // el contrato de sesión expirada en rutas administrativas y públicas.
        if ($status === 419) {
            header('HTTP/1.1 419 Page Expired');
        } else {
            http_response_code($status);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
