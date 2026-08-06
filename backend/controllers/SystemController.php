<?php

declare(strict_types=1);

final class SystemController
{
    public function context(): void
    {
        http_response_code(200);
        echo json_encode([
            'error' => false,
            ...(new SystemContext())->obtener(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
