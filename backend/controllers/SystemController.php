<?php
/**
 * Controlador HTTP SystemController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

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
