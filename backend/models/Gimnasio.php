<?php
/**
 * Acceso a datos Gimnasio. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

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
               AND g.verificacion_estado = 'verificado'
               AND g.archivado_en IS NULL
               AND g.latitud BETWEEN -90 AND 90 AND g.longitud BETWEEN -180 AND 180
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
               AND g.verificacion_estado = 'verificado'
               AND g.archivado_en IS NULL
               AND g.latitud BETWEEN -90 AND 90 AND g.longitud BETWEEN -180 AND 180
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

    public function buscarPublicoPorSlug(string $slug): array|false
    {
        $datasetActivo = (new SystemContext())->obtenerDatasetActivo();
        $sql =
            "SELECT g.*,
                    (SELECT COUNT(*) FROM clases c WHERE c.gimnasio_id = g.id AND c.activa = 1) AS clases_activas,
                    (SELECT COUNT(*) FROM membresias m WHERE m.gimnasio_id = g.id AND m.estado = 'activa') AS membresias_activas
             FROM gimnasios g
             WHERE g.slug = ? AND g.estado IN ('publicado', 'temporalmente_cerrado')
               AND g.verificacion_estado = 'verificado'
               AND g.archivado_en IS NULL
               AND g.latitud BETWEEN -90 AND 90 AND g.longitud BETWEEN -180 AND 180
               AND g.is_demo = ?";
        $params = [$slug, $datasetActivo === null ? 0 : 1];
        if ($datasetActivo !== null) {
            $sql .= ' AND g.demo_dataset_id = ?';
            $params[] = $datasetActivo['id'];
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $gym = $stmt->fetch();
        if (!$gym) return false;
        $normalized = $this->normalizar($gym);
        $stmt = $this->pdo->prepare(
            'SELECT id,nombre,direccion,ciudad,departamento,pais,latitud,longitud,zona_horaria,telefono,email,horarios_json,es_principal,estado
             FROM gimnasio_sedes WHERE gimnasio_id=? AND estado="activa" AND is_demo=? AND (demo_dataset_id <=> ?) ORDER BY es_principal DESC,nombre'
        );
        $stmt->execute([(int) $gym['id'], $datasetActivo === null ? 0 : 1, $datasetActivo['id'] ?? null]);
        $normalized['sedes'] = array_map(function (array $location): array {
            $location['id'] = (int) $location['id'];
            $location['latitud'] = (float) $location['latitud'];
            $location['longitud'] = (float) $location['longitud'];
            $location['es_principal'] = (bool) $location['es_principal'];
            $location['horarios'] = $this->decodeJson($location['horarios_json'], []);
            unset($location['horarios_json']);
            return $location;
        }, $stmt->fetchAll());
        return $normalized;
    }

    public function planesPublicos(int $gymId): array
    {
        $datasetActivo = (new SystemContext())->obtenerDatasetActivo();
        $stmt = $this->pdo->prepare(
            'SELECT id,nombre,slug,descripcion,duracion_dias,precio,moneda,beneficios_json,version
             FROM planes_membresia WHERE gimnasio_id=? AND estado="activo" AND is_demo=? AND (demo_dataset_id <=> ?)
             ORDER BY precio,duracion_dias'
        );
        $stmt->execute([$gymId, $datasetActivo === null ? 0 : 1, $datasetActivo['id'] ?? null]);
        return array_map(function (array $plan): array {
            $plan['id'] = (int) $plan['id'];
            $plan['duracion_dias'] = (int) $plan['duracion_dias'];
            $plan['precio'] = number_format((float) $plan['precio'], 2, '.', '');
            $plan['beneficios'] = $this->decodeJson($plan['beneficios_json'], []);
            unset($plan['beneficios_json']);
            return $plan;
        }, $stmt->fetchAll());
    }

    public function planesPublicosCatalogo(): array
    {
        $datasetActivo = (new SystemContext())->obtenerDatasetActivo();
        $sql =
            'SELECT g.id AS gimnasio_id, g.nombre AS gimnasio_nombre, g.slug AS gimnasio_slug,
                    g.ciudad, g.departamento,
                    p.id, p.nombre, p.descripcion, p.duracion_dias, p.precio, p.moneda, p.beneficios_json, p.version
             FROM planes_membresia p
             JOIN gimnasios g ON g.id = p.gimnasio_id
             WHERE p.estado = "activo"
               AND g.estado IN ("publicado", "temporalmente_cerrado")
               AND g.verificacion_estado = "verificado"
               AND g.archivado_en IS NULL
               AND g.is_demo = ?';
        $params = [$datasetActivo === null ? 0 : 1];
        if ($datasetActivo !== null) {
            $sql .= ' AND g.demo_dataset_id = ?';
            $params[] = $datasetActivo['id'];
        }
        $sql .= ' ORDER BY g.nombre, p.precio, p.duracion_dias';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $byGym = [];
        foreach ($stmt->fetchAll() as $row) {
            $gymId = (int) $row['gimnasio_id'];
            if (!isset($byGym[$gymId])) {
                $byGym[$gymId] = [
                    'gimnasio_id' => $gymId,
                    'gimnasio_nombre' => $row['gimnasio_nombre'],
                    'gimnasio_slug' => $row['gimnasio_slug'],
                    'ciudad' => $row['ciudad'],
                    'departamento' => $row['departamento'],
                    'planes' => [],
                ];
            }
            $byGym[$gymId]['planes'][] = [
                'id' => (int) $row['id'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'duracion_dias' => (int) $row['duracion_dias'],
                'precio' => number_format((float) $row['precio'], 2, '.', ''),
                'moneda' => $row['moneda'],
                'beneficios' => $this->decodeJson($row['beneficios_json'], []),
                'version' => (int) $row['version'],
            ];
        }

        return array_values($byGym);
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
            'pais' => $gym['pais'],
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
