<?php

declare(strict_types=1);

final class Gimnasio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function listarPublicados(): array
    {
        $datasetActivo = (new SystemContext())->obtenerDatasetActivo();
        $sql =
            "SELECT g.*,
                    (SELECT COUNT(*) FROM clases c WHERE c.gimnasio_id = g.id AND c.activa = 1) AS clases_activas,
                    (SELECT COUNT(*) FROM membresias m WHERE m.gimnasio_id = g.id AND m.estado = 'activa') AS membresias_activas
             FROM gimnasios g
             WHERE g.estado IN ('publicado', 'temporalmente_cerrado')
               AND g.is_demo = ?";
        $params = [$datasetActivo === null ? 0 : 1];
        if ($datasetActivo !== null) {
            $sql .= ' AND g.demo_dataset_id = ?';
            $params[] = $datasetActivo['id'];
        }
        $sql .= " ORDER BY g.estado = 'temporalmente_cerrado', g.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'normalizar'], $stmt->fetchAll());
    }

    public function buscarPublicoPorId(int $id): array|false
    {
        $datasetActivo = (new SystemContext())->obtenerDatasetActivo();
        $sql =
            "SELECT g.*,
                    (SELECT COUNT(*) FROM clases c WHERE c.gimnasio_id = g.id AND c.activa = 1) AS clases_activas,
                    (SELECT COUNT(*) FROM membresias m WHERE m.gimnasio_id = g.id AND m.estado = 'activa') AS membresias_activas
             FROM gimnasios g
             WHERE g.id = ? AND g.estado IN ('publicado', 'temporalmente_cerrado')
               AND g.is_demo = ?";
        $params = [$id, $datasetActivo === null ? 0 : 1];
        if ($datasetActivo !== null) {
            $sql .= ' AND g.demo_dataset_id = ?';
            $params[] = $datasetActivo['id'];
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $gym = $stmt->fetch();

        return $gym ? $this->normalizar($gym) : false;
    }

    private function normalizar(array $gym): array
    {
        return [
            'id' => (int) $gym['id'],
            'nombre' => $gym['nombre'],
            'slug' => $gym['slug'],
            'descripcion' => $gym['descripcion'],
            'direccion' => $gym['direccion'],
            'ciudad' => $gym['ciudad'],
            'departamento' => $gym['departamento'],
            'latitud' => (float) $gym['latitud'],
            'longitud' => (float) $gym['longitud'],
            'zona_horaria' => $gym['zona_horaria'],
            'telefono' => $gym['telefono'],
            'email' => $gym['email'],
            'horarios' => $this->decodeJson($gym['horarios_json'], []),
            'categorias' => $this->decodeJson($gym['categorias_json'], []),
            'servicios' => $this->decodeJson($gym['servicios_json'], []),
            'estado' => $gym['estado'],
            'imagen_path' => $gym['imagen_path'],
            'clases_activas' => (int) $gym['clases_activas'],
            'membresias_activas' => (int) $gym['membresias_activas'],
            'is_demo' => (bool) $gym['is_demo'],
            'demo_scenario' => (bool) $gym['is_demo']
                ? $this->decodeJson($gym['demo_scenario_json'], null)
                : null,
        ];
    }

    private function decodeJson(?string $value, mixed $fallback): mixed
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }
}
