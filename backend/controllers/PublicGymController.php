<?php
/**
 * Controlador HTTP PublicGymController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class PublicGymController
{
    public function index(): void
    {
        $gyms = (new Gimnasio())->listarPublicados();
        $cities = [];
        $categories = [];
        foreach ($gyms as $gym) {
            $cities[$gym['ciudad']] = true;
            foreach ($gym['categorias'] as $category) {
                $categories[$category] = true;
            }
        }

        $this->respond(200, [
            'error' => false,
            'gimnasios' => $gyms,
            'meta' => [
                'total' => count($gyms),
                'demo_data' => count(array_filter($gyms, fn (array $gym): bool => $gym['is_demo'])) > 0,
                'filtros' => [
                    'ciudades' => array_keys($cities),
                    'categorias' => array_keys($categories),
                ],
            ],
        ]);
    }

    public function show(string $slug): void
    {
        if (!preg_match('/^[a-z0-9-]{2,140}$/', $slug)) {
            $this->respond(404, ['error' => true, 'mensaje' => 'Gimnasio no encontrado.']);
            return;
        }
        $gym = (new Gimnasio())->buscarPublicoPorSlug($slug);
        if (!$gym) {
            $this->respond(404, ['error' => true, 'mensaje' => 'Gimnasio no encontrado.']);
            return;
        }
        $this->respond(200, ['error' => false, 'gimnasio' => $gym]);
    }

    public function plans(string $gymId): void
    {
        $id = (int) $gymId;
        $gym = (new Gimnasio())->buscarPublicoPorId($id);
        if (!$gym) {
            $this->respond(404, ['error' => true, 'mensaje' => 'Gimnasio no encontrado.']);
            return;
        }
        $this->respond(200, ['error' => false, 'planes' => (new Gimnasio())->planesPublicos($id)]);
    }

    public function plansCatalog(): void
    {
        $gimnasios = (new Gimnasio())->planesPublicosCatalogo();
        $this->respond(200, [
            'error' => false,
            'gimnasios' => $gimnasios,
            'meta' => ['total' => array_sum(array_map(fn (array $gym): int => count($gym['planes']), $gimnasios))],
        ]);
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
