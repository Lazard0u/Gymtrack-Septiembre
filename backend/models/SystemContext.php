<?php
/**
 * Acceso a datos SystemContext. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class SystemContext
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function obtener(): array
    {
        $datasetActivo = $this->obtenerDatasetActivo();

        return [
            'demo_data_active' => $datasetActivo !== null,
            'demo_dataset_name' => $datasetActivo['nombre'] ?? null,
            'turnstile_enabled' => filter_var(getenv('TURNSTILE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
            'turnstile_site_key' => filter_var(getenv('TURNSTILE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL)
                ? (getenv('TURNSTILE_SITE_KEY') ?: null)
                : null,
            'features' => [
                'whatsapp' => $this->flag('FEATURE_WHATSAPP'),
                'google_calendar_sync' => $this->flag('FEATURE_GOOGLE_CALENDAR_SYNC'),
                'advanced_analytics' => $this->flag('FEATURE_ADVANCED_ANALYTICS'),
                'personal_recommendations' => $this->flag('FEATURE_PERSONAL_RECOMMENDATIONS'),
            ],
        ];
    }

    public function obtenerDatasetActivo(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT d.id, d.nombre
             FROM demo_datasets d
             WHERE d.activo = 1
               AND (EXISTS (SELECT 1 FROM gimnasios g WHERE g.demo_dataset_id = d.id AND g.is_demo = 1)
                    OR EXISTS (SELECT 1 FROM usuarios u WHERE u.demo_dataset_id = d.id AND u.is_demo = 1))
             ORDER BY d.actualizado_en DESC
             LIMIT 1'
        );
        $dataset = $stmt->fetch();

        return $dataset ? ['id' => (int) $dataset['id'], 'nombre' => $dataset['nombre']] : null;
    }

    private function flag(string $name): bool
    {
        return filter_var(getenv($name) ?: 'false', FILTER_VALIDATE_BOOL);
    }
}
