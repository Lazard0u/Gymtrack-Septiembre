<?php
/**
 * Servicio LocalFileStorage. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class LocalFileStorage implements FileStorage
{
    private string $root;

    public function __construct()
    {
        $this->root=rtrim((string)(getenv('UPLOAD_STORAGE_PATH')?:'/var/lib/gymtrack/uploads'),'/');
    }

    public function putUploaded(string $temporaryPath,string $key): void
    {
        $target=$this->path($key);$directory=dirname($target);
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('No se pudo preparar el almacenamiento.');
        if(!is_uploaded_file($temporaryPath)||!move_uploaded_file($temporaryPath,$target))throw new RuntimeException('No se pudo guardar el archivo recibido.');
        chmod($target,0600);
    }

    public function path(string $key): string
    {
        if(!preg_match('/^[a-z0-9\/_-]+\.[a-z0-9]{2,8}$/',$key)||str_contains($key,'..'))throw new RuntimeException('Clave de almacenamiento inválida.');
        return $this->root.'/'.$key;
    }

    public function delete(string $key): void
    {
        $path=$this->path($key);if(is_file($path))unlink($path);
    }
}
