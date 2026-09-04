<?php
/**
 * Servicio FileStorage. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

interface FileStorage
{
    public function putUploaded(string $temporaryPath,string $key): void;
    public function path(string $key): string;
    public function delete(string $key): void;
}
