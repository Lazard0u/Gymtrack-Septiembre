<?php

declare(strict_types=1);

interface FileStorage
{
    public function putUploaded(string $temporaryPath,string $key): void;
    public function path(string $key): string;
    public function delete(string $key): void;
}
