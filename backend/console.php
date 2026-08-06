<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/seeders/DemoDatasetSeeder.php';

$command = $argv[1] ?? '';
$option = $argv[2] ?? '';

if ($command !== 'seed:demo' || !in_array($option, ['', '--reset', '--remove', '--status'], true)) {
    fwrite(STDERR, "Uso: php console.php seed:demo [--reset|--remove|--status]\n");
    exit(2);
}

try {
    $seeder = new DemoDatasetSeeder();
    match ($option) {
        '--reset' => $seeder->seed(true),
        '--remove' => $seeder->remove(),
        '--status' => $seeder->status(),
        default => $seeder->seed(false),
    };
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudo completar seed:demo: {$error->getMessage()}\n");
    exit(1);
}
