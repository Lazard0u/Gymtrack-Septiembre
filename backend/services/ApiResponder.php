<?php

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
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
